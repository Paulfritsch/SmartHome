<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use PHPUnit\Framework\TestCase;
use SmartSave\Device;
use SmartSave\SavingsCalculator;

final class SavingsCalculatorTest extends TestCase
{
    public function testWithoutRunningDevicesTheWholeOldCostIsSaved(): void
    {
        $calculator = new SavingsCalculator();
        $this->assertSame(60.0, $calculator->calculateSavings(60.0), 'kein Geraet angemeldet');

        $device = new Device('Heizung', 1500, 6.0);
        $device->attach($calculator);
        $device->turnOn();
        $device->turnOff();

        $this->assertSame(0.0, $calculator->getTotalMonthlyConsumptionKwh());
        $this->assertSame(60.0, $calculator->calculateSavings(60.0), 'Geraet wieder ausgeschaltet');
    }

    public function testSavingsSubtractTheCostAtDefaultAndCustomPrice(): void
    {
        // 1000 W, 3 Std./Tag => 90 kWh/Monat; Standardpreis 0,35 EUR => 31,50 EUR
        $calculator = new SavingsCalculator();
        $device = new Device('Heizung', 1000, 3.0);
        $device->attach($calculator);
        $device->turnOn();

        $this->assertEqualsWithDelta(90.0, $calculator->getTotalMonthlyConsumptionKwh(), 0.0001);
        $this->assertEqualsWithDelta(28.5, $calculator->calculateSavings(60.0), 0.0001);
        // eigener Preis: 90 kWh * 0,50 EUR = 45 EUR
        $this->assertEqualsWithDelta(15.0, $calculator->calculateSavings(60.0, 0.50), 0.0001);
    }

    public function testSavingsNeverGoBelowZero(): void
    {
        $calculator = new SavingsCalculator();
        $device = new Device('Grossverbraucher', 100000, 5.0);
        $device->attach($calculator);
        $device->turnOn();

        $this->assertSame(0.0, $calculator->calculateSavings(10.0));
    }

    public function testConsumptionAddsUpAcrossDevicesAndCountsEachDeviceOnce(): void
    {
        // Heizung 1,5 kW * 6 h * 30 = 270 kWh, Lampe 0,06 kW * 4 h * 30 = 7,2 kWh
        $calculator = new SavingsCalculator();
        $heizung = new Device('Heizung', 1500, 6.0);
        $lampe = new Device('Lampe', 60, 4.0);
        $heizung->attach($calculator);
        $lampe->attach($calculator);

        $heizung->turnOn();
        $heizung->turnOn(); // doppeltes Einschalten darf nicht doppelt zaehlen
        $lampe->turnOn();

        $this->assertEqualsWithDelta(277.2, $calculator->getTotalMonthlyConsumptionKwh(), 0.0001);
        $this->assertEqualsWithDelta(102.98, $calculator->calculateSavings(200.0), 0.0001);
    }
}
