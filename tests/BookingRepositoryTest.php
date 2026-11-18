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

    public function testBookingStoresThePriceOfTheBookingMonth(): void
    {
        $this->bookings->book($this->anna, 'thermostat', 12);

        $list = $this->bookings->listForUser($this->anna);

        $this->assertCount(1, $list);
        $this->assertSame('thermostat', $list[0]['product']);
        $this->assertSame(12, $list[0]['startMonth']);
        $this->assertSame(4.0, $list[0]['monthlyPrice']);
    }

    public function testPriceStaysFixedWhenTimeMovesOn(): void
    {
        $this->bookings->book($this->anna, 'led', 0);
        $this->users->setMonthsElapsed($this->anna, 20);

        $this->assertSame(0.5, $this->bookings->listForUser($this->anna)[0]['monthlyPrice']);
    }

    public function testUnknownProductIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->bookings->book($this->anna, 'raumschiff', 0);
    }

    public function testNoBookingInTheLastMonths(): void
    {
        $this->expectException(ValidationException::class);
        $this->bookings->book($this->anna, 'led', 19);
    }

    public function testBookingLimit(): void
    {
        for ($i = 0; $i < Catalog::MAX_BOOKINGS_PER_USER; $i++) {
            $this->bookings->book($this->anna, 'led', 0);
        }

        $this->expectException(ValidationException::class);
        $this->bookings->book($this->anna, 'led', 0);
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

    public static function invalidMonths(): array
    {
        return ['negativ' => [-1], 'ueber Laufzeit' => [25]];
    }
}
