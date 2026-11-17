<?php

namespace SmartSave;

use SmartSave\Observer\ObserverInterface;

// Beobachter im Observer Pattern: protokolliert Verbrauch je Geraet
class EnergyMonitor implements ObserverInterface
{
    /** @var array<int, float> Schluessel = Objekt-ID des Geraets */
    private array $consumptionLog = [];

    public function update(Device $device): void
    {
        $this->consumptionLog[spl_object_id($device)] = $device->getPowerKw();
    }

    public function getTotalConsumption(): float
    {
        return array_sum($this->consumptionLog);
    }

    /** @return array<int, float> */
    public function getConsumptionLog(): array
    {
        return $this->consumptionLog;
    }
}
