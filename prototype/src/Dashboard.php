<?php

declare(strict_types=1);

namespace SmartSave;

// Fachlogik des Dashboards: liest und prueft die Formulareingaben, baut die Geraete auf,
// verbindet die Beobachter und stellt die Ergebnisse bereit. index.php zeigt sie nur noch an.
final class Dashboard
{
    public const DEFAULT_OLD_MONTHLY_COST = 150.0;
    public const MAX_WATTAGE = 10000.0;
    public const MAX_HOURS_PER_DAY = 24.0;

    // Standardkonfiguration fuer einen 5-Personen-Haushalt (2 Eltern, 3 Kinder):
    // watt = typische Leistung, hours = geschaetzte taegliche Nutzungsdauer
    public const DEVICE_DEFAULTS = [
        'Heizungsthermostat' => ['watt' => 1800.0, 'hours' => 6.0],
        'Beleuchtung Schlafzimmer' => ['watt' => 90.0, 'hours' => 4.0],
        'Steckdose Kueche' => ['watt' => 1500.0, 'hours' => 1.5],
    ];

    /** @var Device[] */
    private array $devices = [];

    /** @var string[] */
    private array $errors = [];

    private float $oldMonthlyCost;
    private float $pricePerKwh;
    private EnergyMonitor $monitor;
    private SavingsCalculator $calculator;

    /**
     * @param array<string, mixed> $post Formulardaten ($_POST)
     * @param bool $isPost true, wenn das Formular abgeschickt wurde
     */
    public static function fromRequest(array $post, bool $isPost): self
    {
        return new self($post, $isPost);
    }

    /** @param array<string, mixed> $post */
    private function __construct(array $post, bool $isPost)
    {
        $this->monitor = new EnergyMonitor();
        $this->calculator = new SavingsCalculator();

        $this->oldMonthlyCost = $this->readNumber(
            $post['old_cost'] ?? null,
            'Bisherige Energiekosten',
            self::DEFAULT_OLD_MONTHLY_COST,
            0.0,
            PHP_FLOAT_MAX
        );
        $this->pricePerKwh = $this->readNumber(
            $post['price_per_kwh'] ?? null,
            'Strompreis',
            SavingsCalculator::DEFAULT_PRICE_PER_KWH,
            0.0,
            PHP_FLOAT_MAX
        );

        foreach (self::DEVICE_DEFAULTS as $name => $default) {
            $wattage = $this->readNumber(
                $post['device_watt'][$name] ?? null,
                "$name: Leistung (Watt)",
                $default['watt'],
                0.0,
                self::MAX_WATTAGE
            );
            $hours = $this->readNumber(
                $post['device_hours'][$name] ?? null,
                "$name: Nutzung (Std./Tag)",
                $default['hours'],
                0.0,
                self::MAX_HOURS_PER_DAY
            );
            // beim ersten Laden sind alle Geraete an, damit sofort ein Ergebnis sichtbar ist;
            // nach dem Absenden entscheidet die Checkbox
            $isOn = !$isPost || isset($post['device_on'][$name]);

            $this->addDevice(new Device($name, $wattage, $hours), $isOn);
        }
    }

    /** @return Device[] */
    public function getDevices(): array
    {
        return $this->devices;
    }

    /** @return string[] */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getOldMonthlyCost(): float
    {
        return $this->oldMonthlyCost;
    }

    public function getPricePerKwh(): float
    {
        return $this->pricePerKwh;
    }

    public function getMonthlyConsumptionKwh(): float
    {
        return $this->calculator->getTotalMonthlyConsumptionKwh();
    }

    public function getNewMonthlyCost(): float
    {
        return $this->getMonthlyConsumptionKwh() * $this->pricePerKwh;
    }

    public function getSavings(): float
    {
        return $this->calculator->calculateSavings($this->oldMonthlyCost, $this->pricePerKwh);
    }

    // Summe der aktuellen Leistung aller eingeschalteten Geraete in kW
    public function getCurrentPowerKw(): float
    {
        return $this->monitor->getTotalConsumption();
    }

    private function addDevice(Device $device, bool $isOn): void
    {
        $device->attach($this->monitor);
        $device->attach($this->calculator);

        // turnOn()/turnOff() loesen notify() aus: beide Beobachter erfassen auch den Zustand "aus"
        $isOn ? $device->turnOn() : $device->turnOff();

        $this->devices[] = $device;
    }

    /**
     * Liest einen Zahlenwert aus dem Formular. Leer = Standardwert (kein Fehler),
     * ungueltig oder ausserhalb des Bereichs = Standardwert und Fehlermeldung.
     */
    private function readNumber(mixed $raw, string $label, float $default, float $min, float $max): float
    {
        if ($raw === null || $raw === '') {
            return $default;
        }

        if (!is_numeric($raw)) {
            $shown = is_scalar($raw) ? " \"$raw\"" : '';
            $this->errors[] = "$label:$shown ist keine Zahl.";
            return $default;
        }

        $value = (float) $raw;
        if ($value < $min || $value > $max) {
            $this->errors[] = $max === PHP_FLOAT_MAX
                ? "$label: Wert darf nicht kleiner als $min sein."
                : "$label: Wert muss zwischen $min und $max liegen.";
            return $default;
        }

        return $value;
    }
}
