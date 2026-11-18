<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SmartSave\Schedule;
use SmartSave\ValidationException;

final class ScheduleTest extends TestCase
{
    use RejectsInvalidInput;

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

    public function testWithoutOnTimeEightHoursAreAssumed(): void
    {
        $schedule = new Schedule('22:00');

        $this->assertSame(8.0, $schedule->offHoursPerDay());
        $this->assertTrue($schedule->isOffAt(new DateTimeImmutable('2026-11-17 23:59')));
        $this->assertTrue($schedule->isOffAt(new DateTimeImmutable('2026-11-17 05:59')));
        $this->assertFalse($schedule->isOffAt(new DateTimeImmutable('2026-11-17 06:00')));
        $this->assertFalse($schedule->isOffAt(new DateTimeImmutable('2026-11-17 21:59')));
    }

    public function testSavingReasonDependsOnTimeOfDay(): void
    {
        foreach (self::savingReasons() as $label => [$off, $reason]) {
            $this->assertSame($reason, (new Schedule($off, null))->savingReason(), $label);
        }
    }

    public static function savingReasons(): array
    {
        return [
            '18:00 abends' => ['18:00', 'Schlaf'],
            '22:30 abends' => ['22:30', 'Schlaf'],
            '00:30 nachts' => ['00:30', 'Schlaf'],
            '03:59 nachts' => ['03:59', 'Schlaf'],
            '04:00 morgens' => ['04:00', 'Arbeit'],
            '07:15 morgens' => ['07:15', 'Arbeit'],
            '10:59 morgens' => ['10:59', 'Arbeit'],
            '11:00 tagsueber' => ['11:00', null],
            '14:00 tagsueber' => ['14:00', null],
            '17:59 tagsueber' => ['17:59', null],
        ];
    }

    public function testCreditedHoursAreCappedAtEight(): void
    {
        $this->assertSame(8.0, (new Schedule('22:00', '06:00'))->creditedHoursPerDay());
        $this->assertSame(8.0, (new Schedule('21:00', '08:00'))->creditedHoursPerDay()); // 11 h Pause, 8 h zaehlen
        $this->assertSame(8.0, (new Schedule('07:00'))->creditedHoursPerDay());
    }

    public function testCreditedHoursAreNeverMoreThanTheOffWindow(): void
    {
        $this->assertSame(6.0, (new Schedule('23:00', '05:00'))->creditedHoursPerDay());
        $this->assertSame(2.0, (new Schedule('22:00', '00:00'))->creditedHoursPerDay());
    }

    public function testNoCreditDuringTheDay(): void
    {
        $this->assertSame(0.0, (new Schedule('12:00', '20:00'))->creditedHoursPerDay());
        $this->assertSame(0.0, (new Schedule('14:00'))->creditedHoursPerDay());
    }

    public function testInvalidTimesAreRejected(): void
    {
        $this->assertAllRejected(self::invalidTimes(), fn (string $off, ?string $on) => new Schedule($off, $on));
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
}
