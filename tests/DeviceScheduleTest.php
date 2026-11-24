<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SmartSave\Dashboard;
use SmartSave\Database;
use SmartSave\Device;
use SmartSave\DeviceCategory;
use SmartSave\DeviceRepository;
use SmartSave\RoomRepository;
use SmartSave\Schedule;
use SmartSave\UserRepository;
use SmartSave\ValidationException;

final class DeviceScheduleTest extends TestCase
{
    private function device(DeviceCategory $category, ?Schedule $schedule, float $watt = 1000.0, float $hours = 6.0): Device
    {
        $device = new Device('Gerät', $watt, $hours, $category, $schedule);
        $device->turnOn();

        return $device;
    }

    public function testHeatingSavesEightHoursPerDayWhenSwitchedOffAtNight(): void
    {
        // 1 kW * 12 h/Tag * 30 Tage = 360 kWh; 8 Stunden Schlaf fallen weg: 1 kW * 8 h * 30 = 240 kWh
        $device = $this->device(DeviceCategory::Heating, new Schedule('22:00', '06:00'), 1000.0, 12.0);

        $this->assertEqualsWithDelta(360.0, $device->getMonthlyConsumptionWithoutScheduleKwh(), 0.0001);
        $this->assertEqualsWithDelta(240.0, $device->getScheduleSavingsKwh(), 0.0001);
        $this->assertEqualsWithDelta(120.0, $device->getMonthlyConsumptionKwh(), 0.0001);
    }

    public function testSavingsAreCappedByDailyRuntime(): void
    {
        // laeuft nur 6 h am Tag: mehr als 6 h koennen nicht wegfallen
        $device = $this->device(DeviceCategory::Heating, new Schedule('22:00', '06:00'), 1000.0, 6.0);

        $this->assertEqualsWithDelta(180.0, $device->getScheduleSavingsKwh(), 0.0001);
        $this->assertEqualsWithDelta(0.0, $device->getMonthlyConsumptionKwh(), 0.0001);
    }

    public function testSocketSavesNothing(): void
    {
        $device = $this->device(DeviceCategory::Socket, new Schedule('22:00', '06:00'), 1000.0, 12.0);

        $this->assertSame(0.0, $device->getScheduleSavingsKwh());
        $this->assertEqualsWithDelta(360.0, $device->getMonthlyConsumptionKwh(), 0.0001);
    }

    public function testScheduleSwitchesPowerOffAtNight(): void
    {
        $device = $this->device(DeviceCategory::Light, new Schedule('22:00', '06:00'), 100.0);

        $this->assertSame(0.0, $device->getPowerKwAt(new DateTimeImmutable('2026-11-17 23:00')));
        $this->assertSame(0.1, $device->getPowerKwAt(new DateTimeImmutable('2026-11-17 12:00')));
    }

    public function testDashboardSumsScheduleSavingsAndLowersCost(): void
    {
        $heizung = $this->device(DeviceCategory::Heating, new Schedule('22:00', '06:00'), 1000.0, 10.0); // 300 kWh, spart 240
        $steckdose = $this->device(DeviceCategory::Socket, new Schedule('22:00', '06:00'), 1000.0, 10.0); // 300 kWh, spart 0

        $dashboard = Dashboard::forUser(150.0, 0.30, [$heizung, $steckdose]);

        $this->assertEqualsWithDelta(240.0, $dashboard->getScheduleSavingsKwh(), 0.0001);
        $this->assertEqualsWithDelta(72.0, $dashboard->getScheduleSavings(), 0.0001);
        // Verbrauch 60 + 300 = 360 kWh -> 108 EUR statt 180 EUR ohne Zeitplaene
        $this->assertEqualsWithDelta(108.0, $dashboard->getNewMonthlyCost(), 0.0001);
    }

    public function testDevicesWithSameNameDoNotOverwriteEachOther(): void
    {
        $a = new Device('Lampe', 100.0, 10.0);
        $b = new Device('Lampe', 100.0, 10.0);
        $a->turnOn();
        $b->turnOn();

        $dashboard = Dashboard::forUser(150.0, 0.30, [$a, $b]);

        $this->assertEqualsWithDelta(60.0, $dashboard->getMonthlyConsumptionKwh(), 0.0001);
        $this->assertEqualsWithDelta(0.2, $dashboard->getCurrentPowerKw(), 0.0001);
    }

    public function testScheduleAndCategoryArePersisted(): void
    {
        $pdo = Database::connect(':memory:');
        $user = (new UserRepository($pdo))->register('Anna', 'anna@example.de', 'geheim123', 0.3)->id;
        $room = (new RoomRepository($pdo))->add($user, 'Bad');
        $devices = new DeviceRepository($pdo);

        $id = $devices->add($user, $room, 'Heizung', 1500, 5, true, 'heizung', '22:00', '06:00');
        $device = $devices->listForUser($user)[0]['device'];

        $this->assertSame(DeviceCategory::Heating, $device->getCategory());
        $this->assertSame('aus 22:00 bis 06:00', $device->getSchedule()?->describe());

        // Zeitplan entfernen
        $devices->update($user, $id, 'Heizung', 1500, 5, 'heizung', '', '');
        $this->assertNull($devices->listForUser($user)[0]['device']->getSchedule());
    }

    public function testInvalidCategoryOrScheduleIsRejected(): void
    {
        $pdo = Database::connect(':memory:');
        $user = (new UserRepository($pdo))->register('Anna', 'anna@example.de', 'geheim123', 0.3)->id;
        $room = (new RoomRepository($pdo))->add($user, 'Bad');
        $devices = new DeviceRepository($pdo);

        foreach ([['raumschiff', null, null], ['licht', null, '06:00'], ['licht', '25:00', null]] as [$category, $off, $on]) {
            try {
                $devices->add($user, $room, 'Lampe', 10, 1, true, $category, $off, $on);
                $this->fail("Eingabe haette abgelehnt werden muessen: $category/$off/$on");
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->getErrors());
            }
        }
    }

    public function testOldDatabaseWithoutNewColumnsIsMigrated(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, password_hash TEXT, price_per_kwh REAL, old_monthly_cost REAL, created_at TEXT)');
        $pdo->exec('CREATE TABLE rooms (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, name TEXT, width INTEGER)');
        $pdo->exec('CREATE TABLE devices (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, room_id INTEGER, name TEXT, wattage REAL, hours_per_day REAL, is_on INTEGER DEFAULT 1)');

        Database::createSchema($pdo);

        $columns = array_column($pdo->query('PRAGMA table_info(devices)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
        $this->assertContains('category', $columns);
        $this->assertContains('off_time', $columns);
        $this->assertContains('on_time', $columns);
    }
}
