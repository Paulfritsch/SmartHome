<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use PHPUnit\Framework\TestCase;
use SmartSave\BookingRepository;
use SmartSave\Database;
use SmartSave\OrderRepository;
use SmartSave\User;
use SmartSave\UserRepository;
use SmartSave\ValidationException;

final class OrderRepositoryTest extends TestCase
{
    use RejectsInvalidInput;

    private OrderRepository $orders;
    private BookingRepository $bookings;
    private UserRepository $users;
    private User $anna;
    private User $ben;

    /** @var array<string, string> */
    private array $address = ['name' => 'Anna Beispiel', 'street' => 'Hauptstraße 1', 'zip' => '20095', 'city' => 'Hamburg'];

    protected function setUp(): void
    {
        $pdo = Database::connect(':memory:');
        $this->users = new UserRepository($pdo);
        $this->bookings = new BookingRepository($pdo);
        $this->orders = new OrderRepository($pdo, $this->bookings);
        $this->anna = $this->users->register('Anna', 'anna@example.de', 'geheim123', 0.3);
        $this->ben = $this->users->register('Ben', 'ben@example.de', 'geheim456', 0.3);
    }

    public function testOrderStoresAddressStatusAndPriceOfTheCurrentMonth(): void
    {
        $this->users->setMonthsElapsed($this->anna->id, 12);
        $anna = $this->users->findById($this->anna->id);

        $id = $this->orders->place($anna, 'thermostat', 1, $this->address);

        $list = $this->orders->listForUser($anna->id);
        $this->assertCount(1, $list);
        $this->assertSame($id, $list[0]['id']);
        $this->assertSame('offen', $list[0]['status']);
        $this->assertSame(4.0, $list[0]['monthlyPrice']);
        $this->assertSame(12, $list[0]['startMonth']);
        $this->assertSame('vor_ort', $list[0]['paymentMethod']);
        $this->assertSame('Hauptstraße 1', $list[0]['street']);
        $this->assertSame('20095', $list[0]['zip']);
    }

    public function testOrderCreatesOneBookingPerUnitAndAcceptsFormStrings(): void
    {
        $this->orders->place($this->anna, 'led', 3, $this->address);
        $this->orders->place($this->ben, 'led', '2', $this->address); // Menge kommt als String aus dem Formular

        $this->assertCount(3, $this->bookings->listForUser($this->anna->id));
        $this->assertSame(3, $this->orders->listForUser($this->anna->id)[0]['quantity']);
        $this->assertCount(2, $this->bookings->listForUser($this->ben->id));
    }

    public function testInvalidQuantitiesAreRejected(): void
    {
        $this->assertAllRejected(self::invalidQuantities(), fn (int|string $quantity) => $this->orders->place($this->anna, 'led', $quantity, $this->address));
    }

    /** @return array<string, array{int|string}> */
    public static function invalidQuantities(): array
    {
        return ['null' => [0], 'negativ' => [-1], 'zu viel' => [OrderRepository::MAX_QUANTITY + 1], 'Text' => ['viele'], 'Dezimal' => ['1.5']];
    }

    public function testInvalidOrderInputIsRejectedAndStoresNothing(): void
    {
        $this->assertAllRejected(
            self::invalidOrders(),
            fn (string $product, string $field, string $value, string $payment) => $this->orders->place(
                $this->anna,
                $product,
                1,
                [$field => $value] + $this->address,
                $payment
            )
        );
        $this->assertSame([], $this->orders->listForUser($this->anna->id));

        // gegen Ende der Laufzeit ist nichts mehr buchbar
        $this->users->setMonthsElapsed($this->anna->id, 20);
        $this->assertAllRejected(
            ['Laufzeit fast vorbei' => ['led']],
            fn (string $product) => $this->orders->place($this->users->findById($this->anna->id), $product, 1, $this->address)
        );
        $this->assertSame([], $this->orders->listForUser($this->anna->id));
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function invalidOrders(): array
    {
        return [
            'unbekanntes Produkt' => ['rakete', 'name', 'Anna', 'vor_ort'],
            'unbekannte Zahlungsart' => ['led', 'name', 'Anna', 'bitcoin'],
            'Name leer' => ['led', 'name', '   ', 'vor_ort'],
            'Strasse leer' => ['led', 'street', '', 'vor_ort'],
            'Strasse zu lang' => ['led', 'street', str_repeat('a', 81), 'vor_ort'],
            'PLZ Buchstaben' => ['led', 'zip', 'ABCDE', 'vor_ort'],
            'PLZ zu kurz' => ['led', 'zip', '123', 'vor_ort'],
            'PLZ zu lang' => ['led', 'zip', '123456', 'vor_ort'],
            'Ort leer' => ['led', 'city', '', 'vor_ort'],
        ];
    }

    public function testSqlInjectionInAddressIsStoredAsPlainText(): void
    {
        $evil = "x'); DROP TABLE orders;--";
        $this->orders->place($this->anna, 'led', 1, ['street' => $evil] + $this->address);

        $this->assertSame($evil, $this->orders->listForUser($this->anna->id)[0]['street']);
    }

    public function testUsersCannotSeeOrCancelForeignOrders(): void
    {
        $id = $this->orders->place($this->anna, 'led', 1, $this->address);

        $this->assertSame([], $this->orders->listForUser($this->ben->id));
        $this->assertFalse($this->orders->cancelOpen($this->ben->id, $id));
        $this->assertSame('offen', $this->orders->listForUser($this->anna->id)[0]['status']);
        $this->assertCount(1, $this->bookings->listForUser($this->anna->id));
    }

    public function testAdminListShowsCustomerAndAddressOldestFirst(): void
    {
        $first = $this->orders->place($this->anna, 'led', 1, $this->address);
        $second = $this->orders->place($this->ben, 'steckdose', 2, ['name' => 'Ben'] + $this->address);

        $open = $this->orders->listByStatus(OrderRepository::STATUS_OPEN);

        $this->assertSame([$first, $second], array_column($open, 'id'));
        $this->assertSame('Anna', $open[0]['customerName']);
        $this->assertSame('ben@example.de', $open[1]['customerEmail']);
        $this->assertSame('Hamburg', $open[1]['city']);
    }

    public function testCountsAndPackingListOnlyCountOpenOrders(): void
    {
        $this->orders->place($this->anna, 'led', 2, $this->address);
        $this->orders->place($this->ben, 'led', 1, $this->address);
        $done = $this->orders->place($this->ben, 'steckdose', 1, $this->address);
        $cancelled = $this->orders->place($this->anna, 'thermostat', 1, $this->address);
        $this->orders->markDelivered($done);
        $this->orders->cancelOpen($this->anna->id, $cancelled);

        $this->assertSame(['offen' => 2, 'geliefert' => 1, 'storniert' => 1], $this->orders->countByStatus());
        $this->assertSame(['led' => 3], $this->orders->packingList());
    }

    public function testDeliveredOrdersAreFinal(): void
    {
        $id = $this->orders->place($this->anna, 'led', 1, $this->address);

        $this->assertTrue($this->orders->markDelivered($id));
        $this->assertFalse($this->orders->markDelivered($id));
        $this->assertFalse($this->orders->markDelivered(9999));

        $order = $this->orders->listForUser($this->anna->id)[0];
        $this->assertSame('geliefert', $order['status']);
        $this->assertNotNull($order['deliveredAt']);

        $this->assertFalse($this->orders->cancelOpen($this->anna->id, $id));
        $this->assertCount(1, $this->bookings->listForUser($this->anna->id));
    }

    public function testCancelRemovesBookingsAndFee(): void
    {
        $id = $this->orders->place($this->anna, 'led', 2, $this->address);

        $this->assertTrue($this->orders->cancelOpen($this->anna->id, $id));

        $this->assertSame([], $this->bookings->listForUser($this->anna->id));
        $this->assertSame('storniert', $this->orders->listForUser($this->anna->id)[0]['status']);
    }

    public function testUsersAreNoAdminByDefaultAndAdminFlagCanBeSetAndRemoved(): void
    {
        $this->assertFalse($this->users->findById($this->anna->id)->isAdmin);

        $this->assertTrue($this->users->setAdmin('ANNA@example.de'));
        $this->assertTrue($this->users->findById($this->anna->id)->isAdmin);
        $this->assertFalse($this->users->findById($this->ben->id)->isAdmin);

        $this->assertTrue($this->users->setAdmin('anna@example.de', false));
        $this->assertFalse($this->users->findById($this->anna->id)->isAdmin);
    }

    public function testBaseSetIsOrderedOnceAndCannotBeCancelled(): void
    {
        $id = $this->orders->placeBase($this->anna, $this->address);

        $order = $this->orders->listForUser($this->anna->id)[0];
        $this->assertSame($id, $order['id']);
        $this->assertSame('basis', $order['product']);
        $this->assertSame(1, $order['quantity']);
        $this->assertSame('offen', $order['status']);
        $this->assertSame(5.99, $order['monthlyPrice']);
        $this->assertSame([], $this->bookings->listForUser($this->anna->id));
        $this->assertTrue($this->orders->hasBaseOrder($this->anna->id));
        $this->assertFalse($this->orders->hasBaseOrder($this->ben->id));

        $this->assertAllRejected(['zweites Basis-Set' => []], fn () => $this->orders->placeBase($this->anna, $this->address));
        $this->assertCount(1, $this->orders->listForUser($this->anna->id));

        $this->assertFalse($this->orders->cancelOpen($this->anna->id, $id));
        $this->assertTrue($this->orders->markDelivered($id));
    }

    public function testBaseSetShowsUpForAdminsAndInThePackingList(): void
    {
        $this->orders->placeBase($this->anna, $this->address);
        $this->orders->placeBase($this->ben, ['name' => 'Ben'] + $this->address);
        $this->orders->place($this->anna, 'led', 2, $this->address);

        $this->assertSame(['basis' => 2, 'led' => 2], $this->orders->packingList());
        $open = $this->orders->listByStatus(OrderRepository::STATUS_OPEN);
        $this->assertSame(['basis', 'basis', 'led'], array_column($open, 'product'));
        $this->assertSame(3, $this->orders->countByStatus()['offen']);
    }

    public function testNormalizeAddressTrimsAndRejectsBadInput(): void
    {
        $this->assertSame(
            ['name' => 'Anna', 'street' => 'Weg 2', 'zip' => '20095', 'city' => 'Hamburg'],
            $this->orders->normalizeAddress(['name' => ' Anna ', 'street' => ' Weg 2 ', 'zip' => '20095', 'city' => 'Hamburg '])
        );

        $this->expectException(ValidationException::class);
        $this->orders->normalizeAddress(['name' => 'Anna']);
    }

    public function testComfortModelOrdersBaseSetAndExtrasToTheSameAddress(): void
    {
        $ids = $this->orders->placeModel($this->anna, 'komfort', $this->address);

        $this->assertCount(4, $ids);
        $this->assertSame(['basis' => 1, 'bewegung' => 1, 'led' => 2, 'temperatur' => 1], $this->orders->packingList());
        $this->assertCount(4, $this->bookings->listForUser($this->anna->id));
        foreach ($this->orders->listForUser($this->anna->id) as $order) {
            $this->assertSame('offen', $order['status']);
            $this->assertSame('Hamburg', $order['city']);
        }
    }

    public function testStarterModelOrdersOnlyTheBaseSetAndCanBeChosenOnce(): void
    {
        $ids = $this->orders->placeModel($this->anna, 'starter', $this->address);

        $this->assertCount(1, $ids);
        $this->assertSame(['basis' => 1], $this->orders->packingList());
        $this->assertSame([], $this->bookings->listForUser($this->anna->id));

        $this->assertAllRejected(['zweites Modell' => ['komfort']], fn (string $model) => $this->orders->placeModel($this->anna, $model, $this->address));
        $this->assertCount(1, $this->orders->listForUser($this->anna->id));
    }

    public function testInvalidModelOrAddressOrdersNothing(): void
    {
        $this->assertAllRejected(self::invalidModels(), fn (string $model) => $this->orders->placeModel($this->anna, $model, $this->address));
        $this->assertAllRejected(
            ['ungueltige PLZ beim Komfort-Modell' => ['komfort', ['zip' => 'x'] + $this->address]],
            fn (string $model, array $address) => $this->orders->placeModel($this->anna, $model, $address)
        );

        $this->assertSame([], $this->orders->listForUser($this->anna->id));
        $this->assertSame([], $this->bookings->listForUser($this->anna->id));
    }

    /** @return array<string, array{string}> */
    public static function invalidModels(): array
    {
        return ['leer' => [''], 'unbekannt' => ['premium'], 'Basis-ID' => ['basis'], 'Gross' => ['KOMFORT']];
    }
}
