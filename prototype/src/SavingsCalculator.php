<?php

namespace SmartSave;

use SmartSave\Observer\ObserverInterface;

// Beobachter im Observer Pattern: berechnet die monatliche Ersparnis gegenueber den alten Energiekosten
class SavingsCalculator implements ObserverInterface
{
    private float $totalConsumptionKwh = 0.0;

    public function update(Device $device): void
    {
        // wichtig: Monatsverbrauch (Watt * Stunden/Tag * Tage), nicht nur Momentanleistung
        $this->totalConsumptionKwh += $device->getMonthlyConsumptionKwh();
    }

    // pricePerKwh in Euro, oldMonthlyCost in Euro
    public function calculateSavings(float $oldMonthlyCost, float $pricePerKwh = 0.35): float
    {
        $newMonthlyCost = $this->totalConsumptionKwh * $pricePerKwh;

        return max(0.0, $oldMonthlyCost - $newMonthlyCost);
    }

    public function getTotalMonthlyConsumptionKwh(): float
    {
        return $this->totalConsumptionKwh;
    }
}
