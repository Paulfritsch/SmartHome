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

    public function testOffWindowWithinOneDayOverMidnightAndWithoutOnTime(): void
    {
        $this->assertSame(8.0, (new Schedule('09:00', '17:00'))->offHoursPerDay());

        $overMidnight = new Schedule('22:00', '06:00');
        $this->assertSame(8.0, $overMidnight->offHoursPerDay());
        $this->assertTrue($overMidnight->isOffAt(new DateTimeImmutable('2026-11-17 23:30')));
        $this->assertTrue($overMidnight->isOffAt(new DateTimeImmutable('2026-11-17 05:59')));
        $this->assertFalse($overMidnight->isOffAt(new DateTimeImmutable('2026-11-17 06:00')));
        $this->assertFalse($overMidnight->isOffAt(new DateTimeImmutable('2026-11-17 12:00')));

        // ohne Einschaltzeit werden 8 Stunden Pause angenommen
        $withoutOn = new Schedule('22:00');
        $this->assertSame(8.0, $withoutOn->offHoursPerDay());
        $this->assertTrue($withoutOn->isOffAt(new DateTimeImmutable('2026-11-17 05:59')));
        $this->assertFalse($withoutOn->isOffAt(new DateTimeImmutable('2026-11-17 06:00')));
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

    public function testCreditedHoursAreCappedAtEightAndLimitedToTheOffWindow(): void
    {
        $this->assertSame(8.0, (new Schedule('21:00', '08:00'))->creditedHoursPerDay()); // 11 h Pause, 8 h zaehlen
        $this->assertSame(8.0, (new Schedule('07:00'))->creditedHoursPerDay());
        $this->assertSame(6.0, (new Schedule('23:00', '05:00'))->creditedHoursPerDay());
        $this->assertSame(2.0, (new Schedule('22:00', '00:00'))->creditedHoursPerDay());
        $this->assertSame(0.0, (new Schedule('12:00', '20:00'))->creditedHoursPerDay(), 'tagsueber zaehlt nicht');
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
