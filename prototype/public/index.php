<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use SmartSave\Device;
use SmartSave\EnergyMonitor;
use SmartSave\SavingsCalculator;

$oldMonthlyCost = isset($_POST['old_cost']) ? (float) $_POST['old_cost'] : 60.0;

$devices = [
    new Device('Heizungsthermostat', 1500),
    new Device('Beleuchtung Wohnzimmer', 60),
    new Device('Steckdose Kueche', 200),
];

$monitor = new EnergyMonitor();
$calculator = new SavingsCalculator();

foreach ($devices as $device) {
    $device->attach($monitor);
    $device->attach($calculator);
    // Simulation: optimierter Betrieb schaltet Geraete bedarfsgerecht statt dauerhaft
    $device->turnOn();
}

$savings = $calculator->calculateSavings($oldMonthlyCost);
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
        <label for="old_cost">Bisherige monatliche Energiekosten (EUR):</label>
        <input type="number" step="0.01" id="old_cost" name="old_cost" value="<?= htmlspecialchars((string) $oldMonthlyCost) ?>">
        <button type="submit">Berechnen</button>
    </form>

    <h2>Aktive Geraete</h2>
    <ul>
        <?php foreach ($devices as $device): ?>
            <li><?= htmlspecialchars($device->getName()) ?> – <?= $device->isOn() ? 'an' : 'aus' ?> (<?= $device->getCurrentConsumptionKwh() ?> kWh/h)</li>
        <?php endforeach; ?>
    </ul>

    <h2>Verbrauch &amp; Ersparnis</h2>
    <p>Gesamtverbrauch: <?= number_format($monitor->getTotalConsumption(), 3) ?> kWh/h</p>
    <p>Geschaetzte monatliche Ersparnis: <?= number_format($savings, 2) ?> EUR</p>
</body>
</html>
