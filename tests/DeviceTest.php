<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use PHPUnit\Framework\TestCase;
use SmartSave\Device;
use SmartSave\Observer\ObserverInterface;

final class DeviceTest extends TestCase
{
    public function testTurnOnAndOffSwitchStateAndPower(): void
    {
        $device = new Device('Heizung', 1500);
        $this->assertFalse($device->isOn());
        $this->assertSame(0.0, $device->getPowerKw());

        $device->turnOn();
        $this->assertTrue($device->isOn());
        $this->assertSame(1.5, $device->getPowerKw());

        $device->turnOff();
        $this->assertFalse($device->isOn());
        $this->assertSame(0.0, $device->getPowerKw());
    }

    public function testAllObserversAreNotifiedOnTurnOnAndOff(): void
    {
        $device = new Device('Lampe', 60);
        $observerA = $this->createMock(ObserverInterface::class);
        $observerB = $this->createMock(ObserverInterface::class);
        $observerA->expects($this->exactly(2))->method('update')->with($device);
        $observerB->expects($this->exactly(2))->method('update')->with($device);

        $device->attach($observerA);
        $device->attach($observerB);
        $device->turnOn();
        $device->turnOff();
    }

    public function testDetachStopsFurtherNotifications(): void
    {
        $device = new Device('Lampe', 60);
        $observer = $this->createMock(ObserverInterface::class);
        $observer->expects($this->never())->method('update');

        $device->attach($observer);
        $device->detach($observer);
        $device->turnOn();
    }

    public function testMonthlyConsumptionFromWattageHoursAndState(): void
    {
        // 1000 W = 1 kWh/h, 2 Std./Tag, 30 Tage => 60 kWh; bei 31 Tagen 62 kWh
        $device = new Device('Heizung', 1000, 2.0);
        $this->assertSame(0.0, $device->getMonthlyConsumptionKwh(), 'ausgeschaltet verbraucht nichts');

        $device->turnOn();
        $this->assertSame(60.0, $device->getMonthlyConsumptionKwh());
        $this->assertSame(62.0, $device->getMonthlyConsumptionKwh(31));
    }
}
