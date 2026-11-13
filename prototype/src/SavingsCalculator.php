<?php

namespace SmartSave;

use SmartSave\Observer\ObserverInterface;

// Beobachter im Observer Pattern: berechnet die monatliche Ersparnis gegenueber den alten Energiekosten
class SavingsCalculator implements ObserverInterface
{
    /** @var array<string, float> Monatsverbrauch in kWh je Geraetename */
    private array $consumptionByDevice = [];

    public function update(Device $device): void
    {
        // ueberschreiben statt addieren: jedes Geraet zaehlt nur mit seinem aktuellen Zustand
        $this->consumptionByDevice[$device->getName()] = $device->getMonthlyConsumptionKwh();
    }

    // pricePerKwh in Euro, oldMonthlyCost in Euro
    public function calculateSavings(float $oldMonthlyCost, float $pricePerKwh = 0.35): float
    {
        $newMonthlyCost = $this->getTotalMonthlyConsumptionKwh() * $pricePerKwh;

        return max(0.0, $oldMonthlyCost - $newMonthlyCost);
    }

    public function getTotalMonthlyConsumptionKwh(): float
    {
        return (float) array_sum($this->consumptionByDevice);
    }
}