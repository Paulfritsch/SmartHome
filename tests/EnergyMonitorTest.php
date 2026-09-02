<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use PHPUnit\Framework\TestCase;
use SmartSave\Device;
use SmartSave\EnergyMonitor;
use SmartSave\SavingsCalculator;

final class EnergyMonitorTest extends TestCase
{
    public function testMonitorReceivesUpdateWhenDeviceTurnsOn(): void
    {
        $device = new Device('Testgeraet', 1000);
        $monitor = new EnergyMonitor();
        $device->attach($monitor);

        $device->turnOn();

        $this->assertSame(1.0, $monitor->getTotalConsumption());
    }

    public function testMonitorConsumptionDropsToZeroWhenDeviceTurnsOff(): void
    {
        $device = new Device('Testgeraet', 1000);
        $monitor = new EnergyMonitor();
        $device->attach($monitor);

        $device->turnOn();
        $device->turnOff();

        $this->assertSame(0.0, $monitor->getTotalConsumption());
    }

    public function testDetachedObserverIsNoLongerNotified(): void
    {
        $device = new Device('Testgeraet', 1000);
        $monitor = new EnergyMonitor();
        $device->attach($monitor);
        $device->detach($monitor);

        $device->turnOn();

        $this->assertSame(0.0, $monitor->getTotalConsumption());
    }

    public function testSavingsCalculatorReturnsPositiveSavingsForLowConsumption(): void
    {
        $device = new Device('Sparlampe', 60);
        $calculator = new SavingsCalculator();
        $device->attach($calculator);

        $device->turnOn();
        $savings = $calculator->calculateSavings(60.0);

        $this->assertGreaterThan(0.0, $savings);
    }
}
