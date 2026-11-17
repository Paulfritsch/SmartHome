<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use PHPUnit\Framework\TestCase;
use SmartSave\Dashboard;
use SmartSave\Database;
use SmartSave\DeviceRepository;
use SmartSave\ExampleApartment;
use SmartSave\RoomRepository;
use SmartSave\UserRepository;
use SmartSave\ValidationException;

final class DeviceRepositoryTest extends TestCase
{
    private DeviceRepository $devices;
    private RoomRepository $rooms;
    private int $anna;
    private int $ben;
    private int $annaRoom;
    private int $benRoom;

    protected function setUp(): void
    {
        $pdo = Database::connect(':memory:');
        $users = new UserRepository($pdo);
        $this->devices = new DeviceRepository($pdo);
        $this->rooms = new RoomRepository($pdo);
        $this->anna = $users->register('Anna', 'anna@example.de', 'geheim123', 0.35)->id;
        $this->ben = $users->register('Ben', 'ben@example.de', 'geheim456', 0.30)->id;
        $this->annaRoom = $this->rooms->add($this->anna, 'Wohnzimmer');
        $this->benRoom = $this->rooms->add($this->ben, 'Flur');
    }

    public function testAddedDeviceIsListedWithValues(): void
    {
        $this->devices->add($this->anna, $this->annaRoom, 'Lampe', '60', '5,5');

        $list = $this->devices->listForUser($this->anna);

        $this->assertCount(1, $list);
        $this->assertSame('Lampe', $list[0]['device']->getName());
        $this->assertSame(60.0, $list[0]['device']->getWattage());
        $this->assertSame(5.5, $list[0]['device']->getHoursPerDay());
        $this->assertTrue($list[0]['device']->isOn());
    }

    public function testUserSeesOnlyOwnDevices(): void
    {
        $this->devices->add($this->anna, $this->annaRoom, 'Lampe', 60, 5);
        $this->devices->add($this->ben, $this->benRoom, 'Fernseher', 120, 3);

        $this->assertSame('Lampe', $this->devices->listForUser($this->anna)[0]['device']->getName());
        $this->assertCount(1, $this->devices->listForUser($this->ben));
    }

    public function testUserCannotChangeOrDeleteForeignDevices(): void
    {
        $id = $this->devices->add($this->anna, $this->annaRoom, 'Lampe', 60, 5);

        $this->assertFalse($this->devices->setOn($this->ben, $id, false));
        $this->assertFalse($this->devices->update($this->ben, $id, 'Gehackt', 1, 1));
        $this->assertFalse($this->devices->delete($this->ben, $id));

        $device = $this->devices->listForUser($this->anna)[0]['device'];
        $this->assertSame('Lampe', $device->getName());
        $this->assertTrue($device->isOn());
    }

    public function testToggleUpdateAndDeleteOwnDevice(): void
    {
        $id = $this->devices->add($this->anna, $this->annaRoom, 'Lampe', 60, 5);

        $this->assertTrue($this->devices->setOn($this->anna, $id, false));
        $this->assertFalse($this->devices->listForUser($this->anna)[0]['device']->isOn());

        $this->assertTrue($this->devices->update($this->anna, $id, 'Stehlampe', 40, 2));
        $this->assertSame('Stehlampe', $this->devices->listForUser($this->anna)[0]['device']->getName());

        $this->assertTrue($this->devices->delete($this->anna, $id));
        $this->assertSame([], $this->devices->listForUser($this->anna));
    }

    /** @dataProvider invalidDevices */
    public function testInvalidDeviceIsRejected(string $name, mixed $watt, mixed $hours): void
    {
        $this->expectException(ValidationException::class);
        $this->devices->add($this->anna, $this->annaRoom, $name, $watt, $hours);
    }

    public static function invalidDevices(): array
    {
        return [
            'leerer Name' => ['', 60, 5],
            'Watt keine Zahl' => ['Lampe', 'abc', 5],
            'Watt negativ' => ['Lampe', -5, 5],
            'Watt zu hoch' => ['Lampe', Dashboard::MAX_WATTAGE + 1, 5],
            'mehr als 24 Stunden' => ['Lampe', 60, 25],
        ];
    }

    public function testExampleDevicesMatchDashboardDefaults(): void
    {
        ExampleApartment::create($this->anna, $this->rooms, $this->devices);

        $this->assertCount(count(Dashboard::DEVICE_DEFAULTS), $this->devices->listForUser($this->anna));
        $this->assertCount(1 + count(ExampleApartment::ROOMS), $this->rooms->listForUser($this->anna));
    }

    public function testDeviceCannotBePlacedInForeignRoom(): void
    {
        $this->expectException(ValidationException::class);
        $this->devices->add($this->anna, $this->benRoom, 'Lampe', 60, 5);
    }

    public function testDeviceLimitPerUser(): void
    {
        for ($i = 0; $i < DeviceRepository::MAX_DEVICES_PER_USER; $i++) {
            $this->devices->add($this->anna, $this->annaRoom, "Lampe $i", 10, 1);
        }

        $this->expectException(ValidationException::class);
        $this->devices->add($this->anna, $this->annaRoom, 'Eine zu viel', 10, 1);
    }

    public function testDashboardForUserUsesOwnPriceAndDeviceStates(): void
    {
        $lampe = $this->devices->add($this->anna, $this->annaRoom, 'Lampe', 100, 10);
        $this->devices->add($this->anna, $this->annaRoom, 'Heizung', 1000, 5);
        $this->devices->setOn($this->anna, $lampe, false);

        $devices = array_map(static fn (array $row) => $row['device'], $this->devices->listForUser($this->anna));
        $dashboard = Dashboard::forUser(150.0, 0.20, $devices);

        // nur die Heizung ist an: 1 kW * 5 h * 30 Tage = 150 kWh -> 30 EUR bei 0,20 EUR/kWh
        $this->assertEqualsWithDelta(150.0, $dashboard->getMonthlyConsumptionKwh(), 0.0001);
        $this->assertEqualsWithDelta(30.0, $dashboard->getNewMonthlyCost(), 0.0001);
        $this->assertEqualsWithDelta(120.0, $dashboard->getSavings(), 0.0001);
    }
}
