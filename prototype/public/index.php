<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use SmartSave\Dashboard;

// Die gesamte Logik steckt in SmartSave\Dashboard - diese Datei zeigt nur die Ergebnisse an.
$dashboard = Dashboard::fromRequest($_POST, $_SERVER['REQUEST_METHOD'] === 'POST');

function e(string|float $value): string
{
    return htmlspecialchars((string) $value);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>SmartSave Prototyp</title>
</head>
<body>
    <h1>SmartSave – Dashboard (Prototyp)</h1>

    <?php if ($dashboard->getErrors() !== []): ?>
        <div role="alert" style="border: 1px solid #c00; padding: 0.5rem; margin-bottom: 1rem;">
            <strong>Bitte pruefe deine Eingaben (es wurden Standardwerte verwendet):</strong>
            <ul>
                <?php foreach ($dashboard->getErrors() as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post">
        <fieldset>
            <legend>Allgemeine Angaben</legend>

            <label for="old_cost">Bisherige monatliche Energiekosten (EUR):</label>
            <input type="number" step="0.01" min="0" id="old_cost" name="old_cost"
                   value="<?= e($dashboard->getOldMonthlyCost()) ?>">

            <br><br>

            <label for="price_per_kwh">Strompreis (EUR/kWh):</label>
            <input type="number" step="0.01" min="0" id="price_per_kwh" name="price_per_kwh"
                   value="<?= e($dashboard->getPricePerKwh()) ?>">
        </fieldset>

        <fieldset>
            <legend>Geraete</legend>

            <?php foreach ($dashboard->getDevices() as $device): ?>
                <div style="margin-bottom: 0.75rem;">
                    <label>
                        <input type="checkbox"
                               name="device_on[<?= e($device->getName()) ?>]"
                               value="1"
                               <?= $device->isOn() ? 'checked' : '' ?>>
                        <?= e($device->getName()) ?>
                    </label>

                    <label style="margin-left: 1rem;">
                        Leistung (Watt):
                        <input type="number" step="1" min="0" max="<?= e(Dashboard::MAX_WATTAGE) ?>"
                               name="device_watt[<?= e($device->getName()) ?>]"
                               value="<?= e($device->getWattage()) ?>"
                               style="width: 6rem;">
                    </label>

                    <label style="margin-left: 1rem;">
                        Nutzung (Std./Tag):
                        <input type="number" step="0.5" min="0" max="<?= e(Dashboard::MAX_HOURS_PER_DAY) ?>"
                               name="device_hours[<?= e($device->getName()) ?>]"
                               value="<?= e($device->getHoursPerDay()) ?>"
                               style="width: 5rem;">
                    </label>
                </div>
            <?php endforeach; ?>
        </fieldset>

        <button type="submit">Berechnen</button>
    </form>

    <h2>Aktive Geraete</h2>
    <ul>
        <?php foreach ($dashboard->getDevices() as $device): ?>
            <li>
                <?= e($device->getName()) ?> –
                <?= $device->isOn() ? 'an' : 'aus' ?>,
                <?= e($device->getHoursPerDay()) ?> Std./Tag
                (<?= e($device->getPowerKw()) ?> kW aktuelle Leistung)
            </li>
        <?php endforeach; ?>
    </ul>

    <h2>Verbrauch &amp; Ersparnis</h2>
    <p>Aktuelle Gesamtleistung: <?= number_format($dashboard->getCurrentPowerKw(), 2) ?> kW</p>
    <p>Gesamtverbrauch: <?= number_format($dashboard->getMonthlyConsumptionKwh(), 2) ?> kWh/Monat</p>
    <p>Neue geschaetzte monatliche Kosten: <?= number_format($dashboard->getNewMonthlyCost(), 2) ?> EUR</p>
    <p>Geschaetzte monatliche Ersparnis: <?= number_format($dashboard->getSavings(), 2) ?> EUR</p>
</body>
</html>
