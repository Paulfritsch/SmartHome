<?php

namespace SmartSave;

use SmartSave\Observer\ObserverInterface;
use SmartSave\Observer\SubjectInterface;

// Subjekt im Observer Pattern: benachrichtigt Beobachter bei Zustandsaenderungen
class Device implements SubjectInterface
{
    /** @var ObserverInterface[] */
    private array $observers = [];

    private bool $isOn = false;

    public function __construct(
        private readonly string $name,
        private readonly float $wattage
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

    // aktueller Verbrauch in kWh je aufgerufener Stunde (vereinfachte Simulation)
    public function getCurrentConsumptionKwh(): float
    {
        return $this->isOn ? $this->wattage / 1000 : 0.0;
    }
}
