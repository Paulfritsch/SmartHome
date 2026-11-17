<?php

namespace SmartSave;

use SmartSave\Observer\ObserverInterface;

// Beobachter im Observer Pattern: berechnet die monatliche Ersparnis gegenueber den alten Energiekosten
class SavingsCalculator implements ObserverInterface
{
    public const DEFAULT_PRICE_PER_KWH = 0.35;

    /** @var array<int, float> Monatsverbrauch in kWh je Geraet (Schluessel = Objekt-ID, damit gleiche Namen sich nicht ueberschreiben) */
    private array $consumptionByDevice = [];

    public function update(Device $device): void
    {
        // ueberschreiben statt addieren: jedes Geraet zaehlt nur mit seinem aktuellen Zustand
        $this->consumptionByDevice[spl_object_id($device)] = $device->getMonthlyConsumptionKwh();
    }

    // pricePerKwh in Euro, oldMonthlyCost in Euro
    public function calculateSavings(float $oldMonthlyCost, float $pricePerKwh = self::DEFAULT_PRICE_PER_KWH): float
    {
        $newMonthlyCost = $this->getTotalMonthlyConsumptionKwh() * $pricePerKwh;

        return max(0.0, $oldMonthlyCost - $newMonthlyCost);
    }

    public function getTotalMonthlyConsumptionKwh(): float
    {
        return (float) array_sum($this->consumptionByDevice);
    }
}