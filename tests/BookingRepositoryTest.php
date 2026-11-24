<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use PHPUnit\Framework\TestCase;
use SmartSave\BookingRepository;
use SmartSave\Catalog;
use SmartSave\Database;
use SmartSave\UserRepository;
use SmartSave\ValidationException;

final class BookingRepositoryTest extends TestCase
{
    use RejectsInvalidInput;

    private BookingRepository $bookings;
    private UserRepository $users;
    private int $anna;
    private int $ben;

    protected function setUp(): void
    {
        $pdo = Database::connect(':memory:');
        $this->users = new UserRepository($pdo);
        $this->bookings = new BookingRepository($pdo);
        $this->anna = $this->users->register('Anna', 'anna@example.de', 'geheim123', 0.3)->id;
        $this->ben = $this->users->register('Ben', 'ben@example.de', 'geheim456', 0.3)->id;
    }

    public function testBookingStoresThePriceOfTheBookingMonthAndKeepsItFixed(): void
    {
        $this->bookings->book($this->anna, 'thermostat', 12);
        $this->bookings->book($this->anna, 'led', 0);

        $list = $this->bookings->listForUser($this->anna);
        $this->assertCount(2, $list);
        $this->assertSame('thermostat', $list[0]['product']);
        $this->assertSame(12, $list[0]['startMonth']);
        $this->assertSame(4.0, $list[0]['monthlyPrice']);

        // der Preis bleibt gleich, auch wenn die Zeit weiterlaeuft
        $this->users->setMonthsElapsed($this->anna, 20);
        $this->assertSame(0.5, $this->bookings->listForUser($this->anna)[1]['monthlyPrice']);
    }

    public function testInvalidBookingsAreRejected(): void
    {
        $this->assertAllRejected(
            ['unbekanntes Produkt' => ['raumschiff', 0], 'in den letzten Monaten' => ['led', 19]],
            fn (string $product, int $month) => $this->bookings->book($this->anna, $product, $month)
        );

        for ($i = 0; $i < Catalog::MAX_BOOKINGS_PER_USER; $i++) {
            $this->bookings->book($this->anna, 'led', 0);
        }
        $this->assertAllRejected(['Buchungslimit' => []], fn () => $this->bookings->book($this->anna, 'led', 0));
    }

    public function testUsersSeeAndCancelOnlyTheirOwnBookings(): void
    {
        $id = $this->bookings->book($this->anna, 'led', 0);

        $this->assertSame([], $this->bookings->listForUser($this->ben));
        $this->assertFalse($this->bookings->cancel($this->ben, $id));
        $this->assertCount(1, $this->bookings->listForUser($this->anna));

        $this->assertTrue($this->bookings->cancel($this->anna, $id));
        $this->assertSame([], $this->bookings->listForUser($this->anna));
    }

    public function testMonthsElapsedMustStayWithinTheTerm(): void
    {
        foreach ([12, 24] as $months) {
            $this->users->setMonthsElapsed($this->anna, $months);
            $this->assertSame($months, $this->users->findById($this->anna)->monthsElapsed);
        }

        $this->assertAllRejected(self::invalidMonths(), fn (int $months) => $this->users->setMonthsElapsed($this->anna, $months));
    }

    /** @return array<string, array{int}> */
    public static function invalidMonths(): array
    {
        return ['negativ' => [-1], 'ueber Laufzeit' => [25]];
    }
}
