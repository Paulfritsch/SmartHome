<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use PHPUnit\Framework\TestCase;
use SmartSave\Device;
use SmartSave\SavingsCalculator;

final class SavingsCalculatorTest extends TestCase
{
    public function testSavingsEqualsOldCostWhenNoDeviceAttached(): void
    {
        $calculator = new SavingsCalculator();

        $savings = $calculator->calculateSavings(60.0);

        $this->assertSame(60.0, $savings);
        $this->assertSame(0.0, $calculator->getTotalMonthlyConsumptionKwh());
    }

    public function testSavingsSubtractsMonthlyCostAtDefaultPrice(): void
    {
        // 1000 W, 3 Std./Tag => 1 * 3 * 30 = 90 kWh/Monat
        // Kosten = 90 * 0.35 EUR = 31.50 EUR
        $calculator = new SavingsCalculator();
        $device = new Device('Heizung', 1000, 3.0);
        $device->attach($calculator);
        $device->turnOn();

        $savings = $calculator->calculateSavings(60.0);

        $this->assertEqualsWithDelta(90.0, $calculator->getTotalMonthlyConsumptionKwh(), 0.0001);
        $this->assertEqualsWithDelta(28.5, $savings, 0.0001);
    }

    public function testSavingsUsesCustomPricePerKwh(): void
    {
        // 500 W, 2 Std./Tag => 0.5 * 2 * 30 = 30 kWh/Monat
        // Kosten = 30 * 0.50 EUR = 15 EUR
        $calculator = new SavingsCalculator();
        $device = new Device('Waschmaschine', 500, 2.0);
        $device->attach($calculator);
        $device->turnOn();

        $savings = $calculator->calculateSavings(20.0, 0.50);

        $this->assertEqualsWithDelta(5.0, $savings, 0.0001);
    }

    public function testSavingsNeverGoesBelowZero(): void
    {
        // Sehr hoher Verbrauch => neue Kosten uebersteigen die alten Kosten deutlich
        $calculator = new SavingsCalculator();
        $device = new Device('Grossverbraucher', 100000, 5.0);
        $device->attach($calculator);
        $device->turnOn();

        $savings = $calculator->calculateSavings(10.0);

        $this->assertSame(0.0, $savings);
    }

    public function testConsumptionAccumulatesAcrossMultipleDevices(): void
    {
        // Heizung: 1500 W, 6 Std./Tag => 1.5 * 6 * 30 = 270 kWh
        // Lampe:   60 W,   4 Std./Tag => 0.06 * 4 * 30 = 7.2 kWh
        // Gesamt: 277.2 kWh, Kosten = 277.2 * 0.35 = 97.02 EUR
        $calculator = new SavingsCalculator();
        $heizung = new Device('Heizung', 1500, 6.0);
        $lampe = new Device('Lampe', 60, 4.0);
        $heizung->attach($calculator);
        $lampe->attach($calculator);

        $heizung->turnOn();
        $lampe->turnOn();

        $savings = $calculator->calculateSavings(200.0);

        $this->assertEqualsWithDelta(277.2, $calculator->getTotalMonthlyConsumptionKwh(), 0.0001);
        $this->assertEqualsWithDelta(102.98, $savings, 0.0001);
    }

    public function testDeviceThatStaysOffContributesNoConsumption(): void
    {
        $calculator = new SavingsCalculator();
        $device = new Device('Heizung', 1500, 6.0);
        $device->attach($calculator);

        // explizit ausschalten, damit notify() ausgeloest wird,
        // Geraet war aber ohnehin nie eingeschaltet
        $device->turnOff();

        $savings = $calculator->calculateSavings(60.0);

        $this->assertSame(0.0, $calculator->getTotalMonthlyConsumptionKwh());
        $this->assertSame(60.0, $savings);
    }
        public function testTurningDeviceOffAfterOnRemovesItsConsumption(): void
    {
        $calculator = new SavingsCalculator();
        $device = new Device('Heizung', 1500, 6.0);
        $device->attach($calculator);

        $device->turnOn();
        $device->turnOff();

        $this->assertSame(0.0, $calculator->getTotalMonthlyConsumptionKwh());
        $this->assertSame(60.0, $calculator->calculateSavings(60.0));
    }

    public function testTurningDeviceOnTwiceDoesNotCountItTwice(): void
    {
        $calculator = new SavingsCalculator();
        $device = new Device('Heizung', 1500, 6.0);
        $device->attach($calculator);

        $device->turnOn();
        $device->turnOn();

        // 1.5 kW * 6 h * 30 Tage = 270 kWh, nicht 540
        $this->assertEqualsWithDelta(270.0, $calculator->getTotalMonthlyConsumptionKwh(), 0.0001);
    }
}
