<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use PHPUnit\Framework\TestCase;
use SmartSave\Device;
use SmartSave\Observer\ObserverInterface;

final class DeviceTest extends TestCase
{
    public function testDeviceIsOffByDefault(): void
    {
        $device = new Device('Lampe', 60);

        $this->assertFalse($device->isOn());
        $this->assertSame(0.0, $device->getCurrentConsumptionKwh());
    }

    public function testTurnOnSetsStateAndConsumption(): void
    {
        $device = new Device('Heizung', 1500);

        $device->turnOn();

        $this->assertTrue($device->isOn());
        $this->assertSame(1.5, $device->getCurrentConsumptionKwh());
    }

    public function testTurnOffResetsConsumptionToZero(): void
    {
        $device = new Device('Heizung', 1500);
        $device->turnOn();

        $device->turnOff();

        $this->assertFalse($device->isOn());
        $this->assertSame(0.0, $device->getCurrentConsumptionKwh());
    }

    public function testGettersReturnConstructorValues(): void
    {
        $device = new Device('Steckdose Kueche', 200);

        $this->assertSame('Steckdose Kueche', $device->getName());
        $this->assertSame(200.0, $device->getWattage());
    }

    public function testTurnOnNotifiesAttachedObservers(): void
    {
        $device = new Device('Lampe', 60);
        $observer = $this->createMock(ObserverInterface::class);
        $observer->expects($this->once())
            ->method('update')
            ->with($device);

        $device->attach($observer);
        $device->turnOn();
    }

    public function testTurnOnAndOffEachTriggerOneNotification(): void
    {
        $device = new Device('Lampe', 60);
        $observer = $this->createMock(ObserverInterface::class);
        $observer->expects($this->exactly(2))
            ->method('update')
            ->with($device);

        $device->attach($observer);
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

    public function testMultipleObserversAreAllNotified(): void
    {
        $device = new Device('Lampe', 60);
        $observerA = $this->createMock(ObserverInterface::class);
        $observerB = $this->createMock(ObserverInterface::class);
        $observerA->expects($this->once())->method('update')->with($device);
        $observerB->expects($this->once())->method('update')->with($device);

        $device->attach($observerA);
        $device->attach($observerB);
        $device->turnOn();
    }
}
