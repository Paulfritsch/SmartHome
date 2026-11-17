<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SmartSave\Schedule;
use SmartSave\ValidationException;

final class ScheduleTest extends TestCase
{
    public function testOffWindowWithinOneDay(): void
    {
        $this->assertSame(8.0, (new Schedule('09:00', '17:00'))->offHoursPerDay());
    }

    public function testOffWindowOverMidnight(): void
    {
        $schedule = new Schedule('22:00', '06:00');

        $this->assertSame(8.0, $schedule->offHoursPerDay());
        $this->assertTrue($schedule->isOffAt(new DateTimeImmutable('2026-11-17 23:30')));
        $this->assertTrue($schedule->isOffAt(new DateTimeImmutable('2026-11-17 05:59')));
        $this->assertFalse($schedule->isOffAt(new DateTimeImmutable('2026-11-17 06:00')));
        $this->assertFalse($schedule->isOffAt(new DateTimeImmutable('2026-11-17 12:00')));
        $this->assertTrue($schedule->isOffAt(new DateTimeImmutable('2026-11-17 22:00')));
    }

    public function testWithoutOnTimeDeviceStaysOffUntilMidnight(): void
    {
        $schedule = new Schedule('18:00');

        $this->assertSame(6.0, $schedule->offHoursPerDay());
        $this->assertTrue($schedule->isOffAt(new DateTimeImmutable('2026-11-17 23:59')));
        $this->assertFalse($schedule->isOffAt(new DateTimeImmutable('2026-11-17 00:00')));
        $this->assertFalse($schedule->isOffAt(new DateTimeImmutable('2026-11-17 17:59')));
    }

    public function testTimesWithSecondsAreAccepted(): void
    {
        $schedule = new Schedule('22:00:00', '06:30:00');

        $this->assertSame('22:00', $schedule->offTime);
        $this->assertSame('06:30', $schedule->onTime);
    }

    public function testEmptyOnTimeMeansNoOnTime(): void
    {
        $this->assertNull((new Schedule('22:00', ''))->onTime);
    }

    /** @dataProvider invalidTimes */
    public function testInvalidTimesAreRejected(string $off, ?string $on): void
    {
        $this->expectException(ValidationException::class);
        new Schedule($off, $on);
    }

    public static function invalidTimes(): array
    {
        return [
            'keine Uhrzeit' => ['abend', null],
            'Stunde 24' => ['24:00', null],
            'Minute 60' => ['12:60', null],
            'Einschaltzeit ungueltig' => ['22:00', 'frueh'],
            'gleiche Zeiten' => ['07:00', '07:00'],
            'leere Ausschaltzeit' => ['', null],
        ];
    }

    public function testDescribe(): void
    {
        $this->assertSame('aus 22:00 bis 06:00', (new Schedule('22:00', '06:00'))->describe());
        $this->assertSame('aus ab 22:00', (new Schedule('22:00'))->describe());
    }
}
