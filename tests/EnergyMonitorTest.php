<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use PHPUnit\Framework\TestCase;
use SmartSave\Device;
use SmartSave\EnergyMonitor;

final class EnergyMonitorTest extends TestCase
{
    public function testMonitorFollowsTheDeviceState(): void
    {
        $device = new Device('Testgeraet', 1000);
        $monitor = new EnergyMonitor();
        $device->attach($monitor);

        $device->turnOn();
        $this->assertSame(1.0, $monitor->getTotalConsumption());

        $device->turnOff();
        $this->assertSame(0.0, $monitor->getTotalConsumption());
    }
}
