<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use SmartSave\Device;
use SmartSave\EnergyMonitor;
use SmartSave\SavingsCalculator;

$oldMonthlyCost = isset($_POST['old_cost']) ? (float) $_POST['old_cost'] : 150.0;
$pricePerKwh = isset($_POST['price_per_kwh']) ? (float) $_POST['price_per_kwh'] : 0.35;

// Standardkonfiguration fuer einen 5-Personen-Haushalt (2 Eltern, 3 Kinder):
// Watt = typische Leistung, hoursPerDay = geschaetzte taegliche Nutzungsdauer
$deviceDefaults = [
    'Heizungsthermostat' => ['watt' => 1800.0, 'hours' => 6.0],
    'Beleuchtung Wohnzimmer' => ['watt' => 90.0, 'hours' => 4.0],
    'Steckdose Kueche' => ['watt' => 1500.0, 'hours' => 1.5],
];

$devices = [];

foreach ($deviceDefaults as $name => $default) {
    $wattageInput = $_POST['device_watt'][$name] ?? null;
    $wattage = $wattageInput !== null && $wattageInput !== ''
        ? (float) $wattageInput
        : $default['watt'];

    $hoursInput = $_POST['device_hours'][$name] ?? null;
    $hoursPerDay = $hoursInput !== null && $hoursInput !== ''
        ? (float) $hoursInput
        : $default['hours'];

    // beim ersten Laden (kein POST) sind alle Geraete an, damit man sofort
    // ein sinnvolles Ergebnis sieht; danach entscheidet die Checkbox
    $isOn = $_SERVER['REQUEST_METHOD'] !== 'POST' || isset($_POST['device_on'][$name]);

    $devices[] = [
        'device' => new Device($name, $wattage, $hoursPerDay),
        'isOn' => $isOn,
    ];
}

$monitor = new EnergyMonitor();
$calculator = new SavingsCalculator();

foreach ($devices as $entry) {
    $device = $entry['device'];
    $device->attach($monitor);
    $device->attach($calculator);

    if ($entry['isOn']) {
        $device->turnOn();
    } else {
        $device->turnOff();
    }
}

$savings = $calculator->calculateSavings($oldMonthlyCost, $pricePerKwh);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>SmartSave Prototyp</title>
</head>
<body>
    <h1>SmartSave – Dashboard (Prototyp)</h1>

    <form method="post">
        <fieldset>
            <legend>Allgemeine Angaben</legend>

            <label for="old_cost">Bisherige monatliche Energiekosten (EUR):</label>
            <input type="number" step="0.01" id="old_cost" name="old_cost"
                   value="<?= htmlspecialchars((string) $oldMonthlyCost) ?>">

            <br><br>

            <label for="price_per_kwh">Strompreis (EUR/kWh):</label>
            <input type="number" step="0.01" id="price_per_kwh" name="price_per_kwh"
                   value="<?= htmlspecialchars((string) $pricePerKwh) ?>">
        </fieldset>

        <fieldset>
            <legend>Geraete</legend>

            <?php foreach ($devices as $entry): ?>
                <?php $device = $entry['device']; ?>
                <div style="margin-bottom: 0.75rem;">
                    <label>
                        <input type="checkbox"
                               name="device_on[<?= htmlspecialchars($device->getName()) ?>]"
                               value="1"
                               <?= $entry['isOn'] ? 'checked' : '' ?>>
                        <?= htmlspecialchars($device->getName()) ?>
                    </label>

                    <label style="margin-left: 1rem;">
                        Leistung (Watt):
                        <input type="number" step="1"
                               name="device_watt[<?= htmlspecialchars($device->getName()) ?>]"
                               value="<?= htmlspecialchars((string) $device->getWattage()) ?>"
                               style="width: 6rem;">
                    </label>

                    <label style="margin-left: 1rem;">
                        Nutzung (Std./Tag):
                        <input type="number" step="0.5"
                               name="device_hours[<?= htmlspecialchars($device->getName()) ?>]"
                               value="<?= htmlspecialchars((string) $device->getHoursPerDay()) ?>"
                               style="width: 5rem;">
                    </label>
                </div>
            <?php endforeach; ?>
        </fieldset>

        <button type="submit">Berechnen</button>
    </form>

    <h2>Aktive Geraete</h2>
    <ul>
        <?php foreach ($devices as $entry): ?>
            <?php $device = $entry['device']; ?>
            <li>
                <?= htmlspecialchars($device->getName()) ?> –
                <?= $device->isOn() ? 'an' : 'aus' ?>,
                <?= $device->getHoursPerDay() ?> Std./Tag
                (<?= $device->getCurrentConsumptionKwh() ?> kWh/h Momentanleistung)
            </li>
        <?php endforeach; ?>
    </ul>

    <h2>Verbrauch &amp; Ersparnis</h2>
    <p>Gesamtverbrauch: <?= number_format($calculator->getTotalMonthlyConsumptionKwh(), 2) ?> kWh/Monat</p>
    <p>Neue geschaetzte monatliche Kosten: <?= number_format($calculator->getTotalMonthlyConsumptionKwh() * $pricePerKwh, 2) ?> EUR</p>
    <p>Geschaetzte monatliche Ersparnis: <?= number_format($savings, 2) ?> EUR</p>
</body>
</html>
