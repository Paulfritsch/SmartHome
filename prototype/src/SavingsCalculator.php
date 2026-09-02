<?php

namespace SmartSave;

use SmartSave\Observer\ObserverInterface;

// Beobachter im Observer Pattern: berechnet die monatliche Ersparnis gegenueber den alten Energiekosten
class SavingsCalculator implements ObserverInterface
{
    private float $totalConsumptionKwh = 0.0;

    public function update(Device $device): void
    {
        $this->totalConsumptionKwh += $device->getCurrentConsumptionKwh();
    }

    // pricePerKwh in Euro, oldMonthlyCost in Euro
    public function calculateSavings(float $oldMonthlyCost, float $pricePerKwh = 0.35): float
    {
        $newMonthlyCost = $this->totalConsumptionKwh * $pricePerKwh;

        return max(0.0, $oldMonthlyCost - $newMonthlyCost);
    }
}
