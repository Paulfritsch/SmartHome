<?php

namespace SmartSave;

use SmartSave\Observer\ObserverInterface;

// Beobachter im Observer Pattern: protokolliert Verbrauch je Geraet
class EnergyMonitor implements ObserverInterface
{
    /** @var array<string, float> */
    private array $consumptionLog = [];

    public function update(Device $device): void
    {
        $this->consumptionLog[$device->getName()] = $device->getCurrentConsumptionKwh();
    }

    public function getTotalConsumption(): float
    {
        return array_sum($this->consumptionLog);
    }

    /** @return array<string, float> */
    public function getConsumptionLog(): array
    {
        return $this->consumptionLog;
    }
}
