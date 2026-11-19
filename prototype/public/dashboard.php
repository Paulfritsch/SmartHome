<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use SmartSave\Catalog;
use SmartSave\Dashboard;
use SmartSave\DeviceCategory;
use SmartSave\DeviceRepository;
use SmartSave\ExampleApartment;
use SmartSave\RoomRepository;
use SmartSave\Subscription;
use SmartSave\ValidationException;

$user = requireCustomer($users);

function postString(string $key): string
{
    return is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
}

function postInt(string $key): int
{
    return (int) filter_var($_POST[$key] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    try {
        switch (postString('action')) {
            case 'add_room':
                $rooms->add($user->id, postString('name'), postInt('width'));
                flash('ok', ['Raum angelegt.']);
                break;
            case 'update_room':
                $rooms->update($user->id, postInt('room_id'), postString('name'), postInt('width'))
                    ?: flash('fehler', ['Raum nicht gefunden.']);
                break;
            case 'delete_room':
                $rooms->delete($user->id, postInt('room_id'));
                flash('ok', ['Raum samt Geräten gelöscht.']);
                break;
            case 'add_device':
                $devices->add(
                    $user->id,
                    postInt('room_id'),
                    postString('name'),
                    postString('watt'),
                    postString('hours'),
                    true,
                    postString('category'),
                    postString('off_time'),
                    postString('on_time'),
                    postString('pairing_method'),
                    postString('pairing_code')
                );
                flash('ok', ['Gerät hinzugefügt und verknüpft.']);
                break;
            case 'update_device':
                $devices->update(
                    $user->id,
                    postInt('device_id'),
                    postString('name'),
                    postString('watt'),
                    postString('hours'),
                    postString('category'),
                    postString('off_time'),
                    postString('on_time')
                )
                    ?: flash('fehler', ['Gerät nicht gefunden.']);
                break;
            case 'toggle_device':
                $devices->setOn($user->id, postInt('device_id'), postString('on') === '1');
                break;
            case 'delete_device':
                $devices->delete($user->id, postInt('device_id'));
                break;
            case 'update_settings':
                $users->updateSettings($user->id, postString('price'), postString('old_cost'));
                flash('ok', ['Einstellungen gespeichert.']);
                break;
            case 'cancel_order':
                if ($orders->cancelOpen($user->id, postInt('order_id'))) {
                    flash('ok', ['Bestellung storniert.']);
                } else {
                    flash('fehler', ['Diese Bestellung lässt sich nicht mehr stornieren.']);
                }
                break;
            case 'set_month':
                $users->setMonthsElapsed($user->id, postInt('months'));
                break;
            case 'add_examples':
                ExampleApartment::create($user->id, $rooms, $devices);
                flash('ok', ['Beispielwohnung angelegt.']);
                break;
        }
    } catch (ValidationException $e) {
        flash('fehler', $e->getErrors());
    }

    redirect('dashboard.php');
}

// --- Daten fuer die Anzeige ---
$roomList = $rooms->listForUser($user->id);
$deviceRows = $devices->listForUser($user->id);
$dashboard = Dashboard::forUser(
    $user->oldMonthlyCost,
    $user->pricePerKwh,
    array_map(static fn (array $row) => $row['device'], $deviceRows)
);

$devicesByRoom = [];
foreach ($deviceRows as $row) {
    $devicesByRoom[$row['roomId']][] = $row;
}

function categoryOptions(DeviceCategory $selected): string
{
    $html = '';
    foreach (DeviceCategory::cases() as $category) {
        $html .= '<option value="' . e($category->value) . '"' . ($category === $selected ? ' selected' : '') . '>'
            . e($category->label()) . '</option>';
    }

    return $html;
}

$now = new DateTimeImmutable('now');

$bookingList = Catalog::withSavings($bookings->listForUser($user->id), $user->oldMonthlyCost);
$ownedUnits = array_count_values(array_column($bookingList, 'product'));
$orderList = $orders->listForUser($user->id);
// Geschaetzte Ersparnis: Verbrauchsanzeige (Hub, App) plus eigene Zeitplaene; Zusatztechnik rechnet das Abo dazu
$subscription = new Subscription(
    Catalog::baseSaving($user->oldMonthlyCost) + $dashboard->getScheduleSavings(),
    $bookingList,
    $user->monthsElapsed,
    Catalog::maxSavings($user->oldMonthlyCost)
);
$month = $subscription->monthsElapsed();

pageStart('Meine Wohnung', $user);
?>
<h1>Meine Wohnung</h1>

<?php if (!$orders->hasBaseOrder($user->id)): ?>
    <p class="hinweis">Du hast noch kein Paket gewählt. <a href="basis-set.php">Paket wählen und Lieferadresse angeben</a></p>
<?php endif; ?>

<section class="werte" aria-label="Zusammenfassung">
    <div><span class="zahl"><?= e(number_format($dashboard->getMonthlyConsumptionKwh(), 1, ',', '.')) ?> kWh</span><span class="bez">Verbrauch pro Monat</span></div>
    <div><span class="zahl"><?= e(euro($dashboard->getNewMonthlyCost())) ?></span><span class="bez">Stromkosten bei <?= e(number_format($user->pricePerKwh, 2, ',', '.')) ?> €/kWh</span></div>
    <div class="gewinn"><span class="zahl"><?= e(euro($subscription->currentSavings())) ?></span><span class="bez">geschätzte Ersparnis pro Monat (Stromkosten heute <?= e(euro($user->oldMonthlyCost)) ?>)</span></div>
    <div><span class="zahl"><?= e(euro($dashboard->getScheduleSavings())) ?></span><span class="bez">davon durch Zeitpläne pro Monat (<?= e(number_format($dashboard->getScheduleSavingsKwh(), 1, ',', '.')) ?> kWh)</span></div>
    <div><span class="zahl"><?= e(number_format($dashboard->getCurrentPowerKwAt($now) * 1000, 0, ',', '.')) ?> W</span><span class="bez">Leistung jetzt (<?= e($now->format('H:i')) ?> Uhr)</span></div>
</section>

<section class="abo" aria-labelledby="abo-titel">
    <h2 id="abo-titel">Mein Abo</h2>
    <div class="abo-grid">
        <div>
            <p class="klein">Pro Monat</p>
            <dl class="rechnung">
                <div><dt>Ersparnis durch Verbrauchsanzeige und Zeitpläne</dt><dd><?= e(signedEuro($subscription->monthlySavings())) ?></dd></div>
                <?php if ($subscription->currentExtraSavings() > 0): ?>
                    <div><dt>Ersparnis durch Zusatztechnik</dt><dd><?= e(signedEuro($subscription->currentExtraSavings())) ?></dd></div>
                <?php endif; ?>
                <?php if ($subscription->currentCapReduction() > 0): ?>
                    <div><dt>Abzug: höchstens <?= e(number_format(Catalog::MAX_SAVINGS_PERCENT, 0)) ?> % der Stromkosten</dt><dd><?= e(signedEuro(-$subscription->currentCapReduction())) ?></dd></div>
                <?php endif; ?>
                <div><dt>Abo-Grundgebühr</dt><dd><?= e(signedEuro($subscription->isFinished() ? 0.0 : -Catalog::BASE_MONTHLY_FEE)) ?></dd></div>
                <div><dt>Zusatztechnik</dt><dd><?= e(signedEuro(-$subscription->currentExtras())) ?></dd></div>
                <div class="ergebnis <?= $subscription->effectiveMonthly() < 0 ? 'minus' : 'plus' ?>">
                    <dt>Effektiv gespart pro Monat</dt><dd><?= e(signedEuro($subscription->effectiveMonthly())) ?></dd>
                </div>
            </dl>
            <?php if (!$subscription->isFinished()): ?>
                <?php $deckung = $subscription->coveragePercent(); ?>
                <label for="deckung" class="laufzeit-label">Dein Abo finanziert sich zu <?= e($deckung) ?> % selbst</label>
                <progress id="deckung" max="100" value="<?= e(min(100, $deckung)) ?>"><?= e($deckung) ?> %</progress>
                <?php if ($subscription->effectiveMonthly() >= 0): ?>
                    <p class="klein">Die Ersparnis deckt das ganze Abo. Der Komfort kostet dich nichts, es bleiben sogar <?= e(euro($subscription->effectiveMonthly())) ?> pro Monat übrig.</p>
                <?php else: ?>
                    <p class="klein">Für den Komfort zahlst du <?= e(euro(-$subscription->effectiveMonthly())) ?> pro Monat drauf. Mit Zeitplänen (abends und morgens ausschalten) oder einem Heizkörperthermostat sinkt das.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div>
            <label for="laufzeit" class="laufzeit-label">
                <?= $subscription->isFinished()
                    ? 'Die 24 Monate sind vorbei: Die Technik gehört dir.'
                    : 'Laufzeit: ' . e($month) . ' von ' . e(Catalog::TERM_MONTHS) . ' Monaten vorbei' ?>
            </label>
            <progress id="laufzeit" max="<?= e(Catalog::TERM_MONTHS) ?>" value="<?= e($month) ?>"><?= e($month) ?> von <?= e(Catalog::TERM_MONTHS) ?></progress>
            <p class="bisher">Bisher effektiv gespart: <strong><?= e(signedEuro($subscription->effectiveSoFar())) ?></strong></p>
            <p class="klein">
                Über alle 24 Monate (bei deinen heutigen Einstellungen): Ersparnis <?= e(euro($subscription->totalSavings24())) ?>,
                Abo und Technik <?= e(euro($subscription->totalFees24())) ?>, effektiv <strong><?= e(signedEuro($subscription->totalEffective24())) ?></strong>.
                Danach entfällt die Gebühr, alle Geräte gehören dir, und du sparst weiter ca. <?= e(euro($subscription->monthlyAfterTerm())) ?> pro Monat.
            </p>
        </div>
    </div>

    <form method="post" class="zeitraffer">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="set_month">
        <span class="klein">Zeitraffer: so tun, als wäre schon Zeit vergangen.</span>
        <button type="submit" name="months" value="<?= e(min(Catalog::TERM_MONTHS, $month + 1)) ?>" class="leise">+1 Monat</button>
        <button type="submit" name="months" value="<?= e(min(Catalog::TERM_MONTHS, $month + 6)) ?>" class="leise">+6 Monate</button>
        <button type="submit" name="months" value="<?= e(Catalog::TERM_MONTHS) ?>" class="leise">Ende der Laufzeit</button>
        <button type="submit" name="months" value="0" class="leise">Zurück auf Start</button>
    </form>
</section>

<?php if ($roomList === []): ?>
    <section class="karte leer">
        <h2>Noch keine Räume</h2>
        <p>Lege unten deinen ersten Raum an, oder starte mit einer Beispielwohnung.</p>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_examples">
            <button type="submit">Beispielwohnung anlegen</button>
        </form>
    </section>
<?php else: ?>
    <section class="wohnung" aria-label="Grundriss deiner Wohnung">
        <?php foreach ($roomList as $room):
            $inRoom = $devicesByRoom[$room['id']] ?? [];
            $roomKwh = 0.0;
            $lit = false;
            foreach ($inRoom as $row) {
                $roomKwh += $row['device']->getMonthlyConsumptionKwh();
                $lit = $lit || $row['device']->getPowerKwAt($now) > 0.0;
            }
            ?>
            <article class="raum breite<?= e($room['width']) ?><?= $lit ? ' hell' : '' ?>">
                <header>
                    <h2><?= e($room['name']) ?></h2>
                    <span class="klein"><?= e(number_format($roomKwh, 1, ',', '.')) ?> kWh/Monat</span>
                </header>

                <div class="geraete">
                    <?php foreach ($inRoom as $row):
                        $device = $row['device'];
                        $schedule = $device->getSchedule();
                        $runningNow = $device->getPowerKwAt($now) > 0.0;
                        $scheduleOffNow = $device->isOn() && !$runningNow && $schedule !== null && $device->getWattage() > 0.0;
                        ?>
                        <div class="geraet<?= $runningNow ? ' an' : '' ?>">
                            <form method="post" class="schalter">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle_device">
                                <input type="hidden" name="device_id" value="<?= e($row['id']) ?>">
                                <input type="hidden" name="on" value="<?= $device->isOn() ? '0' : '1' ?>">
                                <button type="submit" class="lampe" aria-pressed="<?= $device->isOn() ? 'true' : 'false' ?>"
                                        aria-label="<?= e($device->getName()) ?> <?= $device->isOn() ? 'ausschalten' : 'einschalten' ?>">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-4 12.7V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.3A7 7 0 0 0 12 2Zm-2 18h4v1a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1v-1Z"/></svg>
                                </button>
                            </form>
                            <div class="geraet-text">
                                <strong><?= e($device->getName()) ?></strong>
                                <span class="klein"><?= $scheduleOffNow ? 'per Zeitplan aus' : ($device->isOn() ? 'an' : 'aus') ?> · <?= e(number_format($device->getWattage(), 0, ',', '.')) ?> W · <?= e(number_format($device->getHoursPerDay(), 1, ',', '.')) ?> h/Tag · <?= e($device->getCategory()->label()) ?></span>
                                <?php if ($row['pairingCode'] !== null): ?>
                                    <span class="klein">Verknüpft per <?= e(DeviceRepository::PAIRING_METHODS[$row['pairingMethod']] ?? 'Code') ?>: <?= e($row['pairingCode']) ?></span>
                                <?php endif; ?>
                                <?php if ($schedule !== null):
                                    $savedKwh = $device->getScheduleSavingsKwh(); ?>
                                    <span class="klein zeitplan">Zeitplan: <?= e($schedule->describe()) ?> ·
                                        <?php if ($device->getCategory()->savingsFactor() === 0.0): ?>
                                            spart nichts (<?= e($device->getCategory()->label()) ?>)
                                        <?php elseif ($schedule->savingReason() === null): ?>
                                            spart nichts (nur abends oder morgens ausschalten zählt)
                                        <?php elseif (!$device->isOn()): ?>
                                            gerade ausgeschaltet, keine Ersparnis
                                        <?php else: ?>
                                            spart ca. <?= e(euro($savedKwh * $user->pricePerKwh)) ?> pro Monat
                                            (<?= e(number_format($device->getScheduleSavedHoursPerDay(), 1, ',', '.')) ?> Std. <?= e($schedule->savingReason()) ?> pro Tag)
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <details class="bearbeiten">
                                <summary>Bearbeiten</summary>
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update_device">
                                    <input type="hidden" name="device_id" value="<?= e($row['id']) ?>">
                                    <label>Name <input name="name" value="<?= e($device->getName()) ?>" maxlength="40" required></label>
                                    <label>Leistung (Watt) <input name="watt" inputmode="decimal" value="<?= e($device->getWattage()) ?>" required></label>
                                    <label>Nutzung (Std./Tag) <input name="hours" inputmode="decimal" value="<?= e($device->getHoursPerDay()) ?>" required></label>
                                    <label>Art
                                        <select name="category"><?= categoryOptions($device->getCategory()) ?></select>
                                    </label>
                                    <label>Ausschalten um <input type="time" name="off_time" value="<?= e($schedule?->offTime ?? '') ?>"></label>
                                    <label>Wieder einschalten um <span class="klein">(optional)</span> <input type="time" name="on_time" value="<?= e($schedule?->onTime ?? '') ?>"></label>
                                    <p class="klein"><?= e($device->getCategory()->savingsNote()) ?> Ohne Einschaltzeit rechnen wir mit 8 Stunden Pause.</p>
                                    <button type="submit">Speichern</button>
                                </form>
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_device">
                                    <input type="hidden" name="device_id" value="<?= e($row['id']) ?>">
                                    <button type="submit" class="gefahr">Gerät löschen</button>
                                </form>
                            </details>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($inRoom === []): ?>
                        <p class="klein leerraum">Noch kein Gerät in diesem Raum.</p>
                    <?php endif; ?>
                </div>

                <details class="neu">
                    <summary>Gerät hinzufügen</summary>
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add_device">
                        <input type="hidden" name="room_id" value="<?= e($room['id']) ?>">
                        <label>Name <input name="name" placeholder="z. B. Stehlampe" maxlength="40" required></label>
                        <label>Leistung (Watt) <input name="watt" inputmode="decimal" placeholder="60" required></label>
                        <label>Nutzung (Std./Tag) <input name="hours" inputmode="decimal" placeholder="4" required></label>
                        <label>Art
                            <select name="category"><?= categoryOptions(DeviceCategory::Light) ?></select>
                        </label>
                        <label>Ausschalten um <span class="klein">(optional)</span> <input type="time" name="off_time"></label>
                        <label>Wieder einschalten um <span class="klein">(optional)</span> <input type="time" name="on_time"></label>
                        <label>Verknüpfen per
                            <select name="pairing_method">
                                <?php foreach (DeviceRepository::PAIRING_METHODS as $key => $label): ?>
                                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Seriennummer oder QR-Code-Inhalt <input name="pairing_code" placeholder="steht auf dem Gerät oder der Verpackung" maxlength="<?= e(DeviceRepository::MAX_PAIRING_CODE_LENGTH) ?>" required></label>
                        <button type="submit">Hinzufügen</button>
                    </form>
                </details>

                <details class="neu">
                    <summary>Raum bearbeiten</summary>
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_room">
                        <input type="hidden" name="room_id" value="<?= e($room['id']) ?>">
                        <label>Name <input name="name" value="<?= e($room['name']) ?>" maxlength="<?= e(RoomRepository::MAX_NAME_LENGTH) ?>" required></label>
                        <label>Größe
                            <select name="width">
                                <option value="1"<?= $room['width'] === 1 ? ' selected' : '' ?>>klein</option>
                                <option value="2"<?= $room['width'] === 2 ? ' selected' : '' ?>>groß</option>
                            </select>
                        </label>
                        <button type="submit">Speichern</button>
                    </form>
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_room">
                        <input type="hidden" name="room_id" value="<?= e($room['id']) ?>">
                        <button type="submit" class="gefahr">Raum samt Geräten löschen</button>
                    </form>
                </details>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<section class="karte shop" aria-labelledby="shop-titel">
    <h2 id="shop-titel">Technik dazubuchen</h2>
    <p class="klein">
        Im Basis-Abo (<?= e(euro(Catalog::BASE_MONTHLY_FEE)) ?> pro Monat) sind Hub, App und Support enthalten, dazu
        <?= e(implode(', ', array_map(
            static fn (string $id, int $count): string => $count . '× ' . Catalog::PRODUCTS[$id]['name'],
            array_keys(Catalog::BASE_INCLUDED),
            Catalog::BASE_INCLUDED
        ))) ?>.
        Alles Weitere buchst du einfach dazu. Der Preis verteilt sich auf die Restlaufzeit (<?= e($subscription->remainingMonths()) ?> Monate), und nach 24 Monaten gehört alles dir.
    </p>

    <?php if (!Catalog::canBook($month)): ?>
        <p class="hinweis fehler">Dazubuchen geht nur, solange noch mindestens <?= e(Catalog::MIN_REMAINING_MONTHS) ?> Monate Laufzeit übrig sind.</p>
    <?php else: ?>
        <ul class="produkte">
            <?php foreach (Catalog::PRODUCTS as $productId => $product):
                $price = Catalog::monthlyPrice($productId, $month);
                $saving = Catalog::savingForUnits($productId, $ownedUnits[$productId] ?? 0, 1, $user->oldMonthlyCost); ?>
                <li>
                    <div>
                        <strong><?= e($product['name']) ?></strong>
                        <span class="klein"><?= e($product['description']) ?></span>
                        <span class="klein netto"><?= e(nettoText($saving, $price)) ?></span>
                    </div>
                    <div class="kaufen">
                        <span class="preis"><?= e(euro($price)) ?> <span class="klein">pro Monat</span></span>
                        <a class="knopf" href="checkout.php?product=<?= e($productId) ?>">Dazubuchen</a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($orderList !== []): ?>
        <h3>Deine Bestellungen</h3>
        <ul class="gebucht">
            <?php foreach ($orderList as $order): ?>
                <li>
                    <span>
                        <?= e($order['quantity']) ?>× <?= e(Catalog::productName($order['product'])) ?>
                        <span class="status <?= e($order['status']) ?>"><?= e($order['status']) ?></span>
                        <span class="klein"><?= $order['product'] === Catalog::BASE_PRODUCT_ID
                                ? e(Catalog::baseSetContents()) . ' · Teil deines Abos'
                                : e(euro($order['monthlyPrice'] * $order['quantity'])) . ' pro Monat ab Monat ' . e($order['startMonth']) ?>
                            · Lieferung an <?= e($order['name']) ?>, <?= e($order['street']) ?>, <?= e($order['zip']) ?> <?= e($order['city']) ?></span>
                    </span>
                    <?php if ($order['status'] === 'offen' && $order['product'] !== Catalog::BASE_PRODUCT_ID): ?>
                        <form method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="cancel_order">
                            <input type="hidden" name="order_id" value="<?= e($order['id']) ?>">
                            <button type="submit" class="gefahr">Stornieren</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<div class="unten">
    <section class="karte">
        <h2>Raum hinzufügen</h2>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_room">
            <label for="room-name">Name</label>
            <input id="room-name" name="name" placeholder="z. B. Kinderzimmer" maxlength="<?= e(RoomRepository::MAX_NAME_LENGTH) ?>" required>
            <label for="room-width">Größe</label>
            <select id="room-width" name="width">
                <option value="1">klein</option>
                <option value="2">groß</option>
            </select>
            <button type="submit">Raum anlegen</button>
        </form>
    </section>

    <section class="karte">
        <h2>Strompreis und Kosten</h2>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_settings">
            <label for="price">Strompreis in € pro kWh</label>
            <input id="price" name="price" inputmode="decimal" value="<?= e(number_format($user->pricePerKwh, 2, ',', '')) ?>" required>
            <label for="old_cost">Energiekosten heute in € pro Monat</label>
            <input id="old_cost" name="old_cost" inputmode="decimal" value="<?= e(number_format($user->oldMonthlyCost, 2, ',', '')) ?>" required>
            <button type="submit">Speichern</button>
        </form>
        <p class="klein">Geschätzte Ersparnis = Verbrauchsanzeige durch Hub und App (<?= e(number_format(Catalog::BASE_SAVING_PERCENT, 1, ',', '')) ?> % der Energiekosten, nach dem Feldversuch Intelliekon von Fraunhofer ISE/ISI) plus Zeitpläne plus Zusatztechnik, höchstens <?= e(number_format(Catalog::MAX_SAVINGS_PERCENT, 0)) ?> %. Der Verbrauch der angelegten Geräte dient der Anzeige und fließt nicht in die Ersparnis ein. Zeitpläne zählen nur, wenn abends (ab 18 Uhr, Schlaf) oder morgens (4 bis 11 Uhr, Arbeit) ausgeschaltet wird, mit höchstens 8 Stunden pro Tag und nie mehr, als das Gerät täglich läuft. Bei Steckdosen zählt nichts, bei sonstigen Geräten nur der Standby-Anteil (10 %). Alles ist eine Simulation mit angenommenen Werten.</p>
    </section>
</div>
<?php
pageEnd();
