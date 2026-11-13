<?php

namespace SmartSave;

use SmartSave\Observer\ObserverInterface;
use SmartSave\Observer\SubjectInterface;

// Subjekt im Observer Pattern: benachrichtigt Beobachter bei Zustandsaenderungen
class Device implements SubjectInterface
{
    public const DAYS_PER_MONTH = 30;

    /** @var ObserverInterface[] */
    private array $observers = [];

    private bool $isOn = false;

    public function __construct(
        private readonly string $name,
        private readonly float $wattage,
        // geschaetzte taegliche Nutzungsdauer in Stunden, z.B. 6.0 = 6h/Tag
        private readonly float $hoursPerDay = 0.0
    ) {
    }

    public function attach(ObserverInterface $observer): void
    {
        $this->observers[] = $observer;
    }

    public function detach(ObserverInterface $observer): void
    {
        $this->observers = array_filter(
            $this->observers,
            static fn (ObserverInterface $o) => $o !== $observer
        );
    }

    public function notify(): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }

    public function turnOn(): void
    {
        $this->isOn = true;
        $this->notify();
    }

    public function turnOff(): void
    {
        $this->isOn = false;
        $this->notify();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isOn(): bool
    {
        return $this->isOn;
    }

    public function getWattage(): float
    {
        return $this->wattage;
    }

    public function getHoursPerDay(): float
    {
        return $this->hoursPerDay;
    }

    // aktuelle Leistung in kW (0, wenn das Geraet aus ist) - nicht zu verwechseln mit dem Monatsverbrauch
    public function getPowerKw(): float
    {
        return $this->isOn ? $this->wattage / 1000 : 0.0;
    }

    // tatsaechlicher Verbrauch pro Monat in kWh, basierend auf Nutzungsdauer/Tag
    public function getMonthlyConsumptionKwh(int $daysPerMonth = self::DAYS_PER_MONTH): float
    {
        if (!$this->isOn) {
            return 0.0;
        }

        return ($this->wattage / 1000) * $this->hoursPerDay * $daysPerMonth;
    }
}
