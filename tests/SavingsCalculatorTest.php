<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use PHPUnit\Framework\TestCase;
use SmartSave\Device;
use SmartSave\SavingsCalculator;

final class SavingsCalculatorTest extends TestCase
{
    public function testSavingsEqualsOldCostWhenNoConsumption(): void
    {
        $calculator = new SavingsCalculator();

        $savings = $calculator->calculateSavings(60.0);

        $this->assertSame(60.0, $savings);
    }

    public function testSavingsSubtractsNewCostAtDefaultPrice(): void
    {
        $calculator = new SavingsCalculator();
        $device = new Device('Heizung', 1500);
        $device->turnOn();
        $calculator->update($device);

        // 1.5 kWh * 0.35 EUR = 0.525 EUR neue Kosten
        $savings = $calculator->calculateSavings(60.0);

        $this->assertEqualsWithDelta(59.475, $savings, 0.0001);
    }

    public function testSavingsUsesCustomPricePerKwh(): void
    {
        $calculator = new SavingsCalculator();
        $device = new Device('Heizung', 1000);
        $device->turnOn();
        $calculator->update($device);

        // 1.0 kWh * 0.50 EUR = 0.50 EUR neue Kosten
        $savings = $calculator->calculateSavings(10.0, 0.50);

        $this->assertEqualsWithDelta(9.5, $savings, 0.0001);
    }

    public function testSavingsNeverGoesBelowZero(): void
    {
        $calculator = new SavingsCalculator();
        $device = new Device('Grossverbraucher', 100000);
        $device->turnOn();
        $calculator->update($device);

        $savings = $calculator->calculateSavings(10.0);

        $this->assertSame(0.0, $savings);
    }

    public function testConsumptionAccumulatesAcrossMultipleUpdates(): void
    {
        $calculator = new SavingsCalculator();
        $heizung = new Device('Heizung', 1500);
        $lampe = new Device('Lampe', 60);
        $heizung->turnOn();
        $lampe->turnOn();

        $calculator->update($heizung);
        $calculator->update($lampe);

        // Gesamt 1.56 kWh * 0.35 EUR = 0.546 EUR neue Kosten
        $savings = $calculator->calculateSavings(60.0);

        $this->assertEqualsWithDelta(59.454, $savings, 0.0001);
    }
}
