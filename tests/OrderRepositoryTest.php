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

    public function testOrderCreatesOneBookingPerUnit(): void
    {
        $id = $this->orders->place($this->anna, 'led', 3, $this->address);

        $bookings = $this->bookings->listForUser($this->anna->id);
        $this->assertCount(3, $bookings);
        $this->assertSame(3, $this->orders->listForUser($this->anna->id)[0]['quantity']);
        $this->assertGreaterThan(0, $id);
    }

    public function testQuantityGivenAsStringFromTheFormIsAccepted(): void
    {
        $this->orders->place($this->anna, 'led', '2', $this->address);

        $this->assertCount(2, $this->bookings->listForUser($this->anna->id));
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

    public function testUnknownProductIsRejectedAndStoresNothing(): void
    {
        try {
            $this->orders->place($this->anna, 'rakete', 1, $this->address);
            $this->fail('ValidationException erwartet');
        } catch (ValidationException) {
            $this->assertSame([], $this->orders->listForUser($this->anna->id));
        }
    }

    public function testOrderIsRejectedWhenTheTermIsNearlyOver(): void
    {
        $this->users->setMonthsElapsed($this->anna->id, 20);

        $this->expectException(ValidationException::class);

        $this->orders->place($this->users->findById($this->anna->id), 'led', 1, $this->address);
    }

    public function testInvalidAddressesAreRejected(): void
    {
        $this->assertAllRejected(
            self::invalidAddresses(),
            fn (string $field, string $value) => $this->orders->place($this->anna, 'led', 1, [$field => $value] + $this->address)
        );
        $this->assertSame([], $this->orders->listForUser($this->anna->id));
    }

    /** @return array<string, array{string, string}> */
    public static function invalidAddresses(): array
    {
        return [
            'Name leer' => ['name', '   '],
            'Strasse leer' => ['street', ''],
            'Strasse zu lang' => ['street', str_repeat('a', 81)],
            'PLZ Buchstaben' => ['zip', 'ABCDE'],
            'PLZ zu kurz' => ['zip', '123'],
            'PLZ zu lang' => ['zip', '123456'],
            'Ort leer' => ['city', ''],
        ];
    }

    public function testUnknownPaymentMethodIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->orders->place($this->anna, 'led', 1, $this->address, 'bitcoin');
    }

    public function testSqlInjectionInAddressIsStoredAsPlainText(): void
    {
        $evil = "x'); DROP TABLE orders;--";
        $this->orders->place($this->anna, 'led', 1, ['street' => $evil] + $this->address);

        $this->assertSame($evil, $this->orders->listForUser($this->anna->id)[0]['street']);
    }

    public function testUsersOnlySeeTheirOwnOrders(): void
    {
        $this->orders->place($this->anna, 'led', 1, $this->address);

        $this->assertSame([], $this->orders->listForUser($this->ben->id));
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

    public function testMarkDeliveredSetsStatusAndTimestampOnce(): void
    {
        $id = $this->orders->place($this->anna, 'led', 1, $this->address);

        $this->assertTrue($this->orders->markDelivered($id));
        $this->assertFalse($this->orders->markDelivered($id));

        $order = $this->orders->listForUser($this->anna->id)[0];
        $this->assertSame('geliefert', $order['status']);
        $this->assertNotNull($order['deliveredAt']);
        $this->assertFalse($this->orders->markDelivered(9999));
    }

    public function testCancelRemovesBookingsAndFee(): void
    {
        $id = $this->orders->place($this->anna, 'led', 2, $this->address);

        $this->assertTrue($this->orders->cancelOpen($this->anna->id, $id));

        $this->assertSame([], $this->bookings->listForUser($this->anna->id));
        $this->assertSame('storniert', $this->orders->listForUser($this->anna->id)[0]['status']);
    }

    public function testCannotCancelSomeoneElsesOrder(): void
    {
        $id = $this->orders->place($this->anna, 'led', 1, $this->address);

        $this->assertFalse($this->orders->cancelOpen($this->ben->id, $id));
        $this->assertSame('offen', $this->orders->listForUser($this->anna->id)[0]['status']);
        $this->assertCount(1, $this->bookings->listForUser($this->anna->id));
    }

    public function testCannotCancelDeliveredOrder(): void
    {
        $id = $this->orders->place($this->anna, 'led', 1, $this->address);
        $this->orders->markDelivered($id);

        $this->assertFalse($this->orders->cancelOpen($this->anna->id, $id));
        $this->assertCount(1, $this->bookings->listForUser($this->anna->id));
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

    public function testBaseSetOrderIsOpenOnceAndHasNoBookings(): void
    {
        $id = $this->orders->placeBase($this->anna, $this->address);

        $order = $this->orders->listForUser($this->anna->id)[0];
        $this->assertSame($id, $order['id']);
        $this->assertSame('basis', $order['product']);
        $this->assertSame(1, $order['quantity']);
        $this->assertSame('offen', $order['status']);
        $this->assertSame(5.99, $order['monthlyPrice']);
        $this->assertSame('Hauptstraße 1', $order['street']);
        $this->assertSame([], $this->bookings->listForUser($this->anna->id));
        $this->assertTrue($this->orders->hasBaseOrder($this->anna->id));
        $this->assertFalse($this->orders->hasBaseOrder($this->ben->id));
    }

    public function testBaseSetCanOnlyBeOrderedOncePerAccount(): void
    {
        $this->orders->placeBase($this->anna, $this->address);

        try {
            $this->orders->placeBase($this->anna, $this->address);
            $this->fail('ValidationException erwartet');
        } catch (ValidationException) {
            $this->assertCount(1, $this->orders->listForUser($this->anna->id));
        }
    }

    public function testBaseSetShowsUpForAdminsAndInThePackingList(): void
    {
        $this->orders->placeBase($this->anna, $this->address);
        $this->orders->placeBase($this->ben, ['name' => 'Ben'] + $this->address);
        $this->orders->place($this->anna, 'led', 2, $this->address);

        $this->assertSame(['basis' => 2, 'led' => 2], $this->orders->packingList());
        $open = $this->orders->listByStatus(OrderRepository::STATUS_OPEN);
        $this->assertSame(['basis', 'basis', 'led'], array_column($open, 'product'));
        $this->assertSame('Hamburg', $open[0]['city']);
        $this->assertSame(3, $this->orders->countByStatus()['offen']);
    }

    public function testBaseSetCannotBeCancelledButCanBeDelivered(): void
    {
        $id = $this->orders->placeBase($this->anna, $this->address);

        $this->assertFalse($this->orders->cancelOpen($this->anna->id, $id));
        $this->assertSame('offen', $this->orders->listForUser($this->anna->id)[0]['status']);
        $this->assertTrue($this->orders->markDelivered($id));
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

    public function testStarterModelOrdersOnlyTheBaseSet(): void
    {
        $ids = $this->orders->placeModel($this->anna, 'starter', $this->address);

        $this->assertCount(1, $ids);
        $this->assertSame(['basis' => 1], $this->orders->packingList());
        $this->assertSame([], $this->bookings->listForUser($this->anna->id));
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

    public function testUnknownOrEmptyModelOrdersNothing(): void
    {
        $this->assertAllRejected(self::invalidModels(), fn (string $model) => $this->orders->placeModel($this->anna, $model, $this->address));
        $this->assertSame([], $this->orders->listForUser($this->anna->id));
    }

    /** @return array<string, array{string}> */
    public static function invalidModels(): array
    {
        return ['leer' => [''], 'unbekannt' => ['premium'], 'Basis-ID' => ['basis'], 'Gross' => ['KOMFORT']];
    }

    public function testInvalidAddressOrdersNothingEvenForTheComfortModel(): void
    {
        try {
            $this->orders->placeModel($this->anna, 'komfort', ['zip' => 'x'] + $this->address);
            $this->fail('ValidationException erwartet');
        } catch (ValidationException) {
            $this->assertSame([], $this->orders->listForUser($this->anna->id));
            $this->assertSame([], $this->bookings->listForUser($this->anna->id));
        }
    }

    public function testModelCanOnlyBeChosenOnce(): void
    {
        $this->orders->placeModel($this->anna, 'starter', $this->address);

        try {
            $this->orders->placeModel($this->anna, 'komfort', $this->address);
            $this->fail('ValidationException erwartet');
        } catch (ValidationException) {
            $this->assertCount(1, $this->orders->listForUser($this->anna->id));
        }
    }
}
