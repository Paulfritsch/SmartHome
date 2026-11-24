<?php

declare(strict_types=1);

// Kasse für Zusatztechnik: Anzahl, Lieferadresse und Monatspreis für die Restlaufzeit.
// Bezahlt wird bei der Lieferung, die Bestellung bleibt "offen", bis das Team sie als geliefert markiert.

require __DIR__ . '/_bootstrap.php';

use SmartSave\Catalog;
use SmartSave\OrderRepository;
use SmartSave\ValidationException;

// Diese Objekte legt _bootstrap.php an (die Angaben helfen der statischen Analyse mit PHPStan):
/** @var \SmartSave\UserRepository $users */
/** @var \SmartSave\BookingRepository $bookings */
/** @var \SmartSave\OrderRepository $orders */

$user = requireCustomer($users);

$productId = $_POST['product'] ?? $_GET['product'] ?? '';
$productId = is_string($productId) ? $productId : '';
if (!Catalog::exists($productId)) {
    flash('fehler', ['Dieses Produkt gibt es nicht. Bitte wähle eines aus der Liste.']);
    redirect('dashboard.php');
}

$product = Catalog::PRODUCTS[$productId];
$remaining = Catalog::remainingMonths($user->monthsElapsed);
$form = ['quantity' => '1', 'name' => $user->name, 'street' => '', 'zip' => '', 'city' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    foreach (array_keys($form) as $key) {
        $form[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    }
    $payment = is_string($_POST['payment'] ?? null) ? $_POST['payment'] : '';

    try {
        $orders->place($user, $productId, $form['quantity'], $form, $payment);
        flash('ok', ['Bestellung aufgegeben. Wir liefern an ' . trim($form['street']) . ', ' . trim($form['zip']) . ' ' . trim($form['city']) . ' und du bezahlst bei der Lieferung.']);
        redirect('dashboard.php');
    } catch (ValidationException $e) {
        flash('fehler', $e->getErrors());
    }
}

$canBook = Catalog::canBook($user->monthsElapsed);
$unit = Catalog::monthlyPrice($productId, $user->monthsElapsed);
$quantity = max(1, min(OrderRepository::MAX_QUANTITY, (int) $form['quantity']));
$owned = array_count_values(array_column($bookings->listForUser($user->id), 'product'))[$productId] ?? 0;
$saving = Catalog::savingForUnits($productId, $owned, $quantity, $user->oldMonthlyCost);

pageStart('Kasse', $user);
?>
<h1>Kasse</h1>
<p><a href="dashboard.php">← Zurück zu meiner Wohnung</a></p>

<?php if (!$canBook): ?>
    <p class="hinweis fehler">Dazubuchen geht nur, solange noch mindestens <?= e(Catalog::MIN_REMAINING_MONTHS) ?> Monate Laufzeit übrig sind.</p>
<?php else: ?>
<div class="checkout">
    <form method="post" class="karte">
        <?= csrfField() ?>
        <input type="hidden" name="product" value="<?= e($productId) ?>">

        <fieldset>
            <legend>Bestellung</legend>
            <p><strong><?= e($product['name']) ?></strong><br><span class="klein"><?= e($product['description']) ?></span></p>
            <label for="quantity">Anzahl</label>
            <select id="quantity" name="quantity">
                <?php for ($n = 1; $n <= OrderRepository::MAX_QUANTITY; $n++): ?>
                    <option value="<?= e($n) ?>"<?= $n === $quantity ? ' selected' : '' ?>><?= e($n) ?></option>
                <?php endfor; ?>
            </select>
        </fieldset>

        <fieldset>
            <legend>Lieferadresse</legend>
            <label for="name">Name</label>
            <input id="name" name="name" value="<?= e($form['name']) ?>" maxlength="60" autocomplete="name" required>
            <label for="street">Straße und Hausnummer</label>
            <input id="street" name="street" value="<?= e($form['street']) ?>" maxlength="80" autocomplete="street-address" required>
            <div class="zeile">
                <div>
                    <label for="zip">PLZ</label>
                    <input id="zip" name="zip" value="<?= e($form['zip']) ?>" inputmode="numeric" pattern="[0-9]{4,5}" maxlength="5" autocomplete="postal-code" required>
                </div>
                <div>
                    <label for="city">Ort</label>
                    <input id="city" name="city" value="<?= e($form['city']) ?>" maxlength="60" autocomplete="address-level2" required>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Zahlungsart</legend>
            <?php foreach (OrderRepository::PAYMENT_METHODS as $key => $label): ?>
                <label class="zahlart"><input type="radio" name="payment" value="<?= e($key) ?>" checked> <?= e($label) ?></label>
            <?php endforeach; ?>
        </fieldset>

        <button type="submit">Verbindlich bestellen</button>
    </form>

    <aside class="karte" aria-labelledby="zusammenfassung">
        <h2 id="zusammenfassung">Dein Preis</h2>
        <ul class="kasse">
            <li><span>Preis pro Gerät und Monat</span><span><?= e(euro($unit)) ?></span></li>
            <li><span>Restlaufzeit</span><span><?= e($remaining) ?> Monate</span></li>
            <li><span>Gesamt pro Gerät</span><span><?= e(euro($unit * $remaining)) ?></span></li>
            <li class="summe"><span>Bei <?= e($quantity) ?>× mehr pro Monat</span><span>+<?= e(euro($unit * $quantity)) ?></span></li>
            <li><span>Geschätzte Ersparnis pro Monat</span><span><?= e(euro($saving)) ?></span></li>
            <li class="summe"><span>Unterm Strich pro Monat</span><span><?= e(signedEuro($saving - $unit * $quantity)) ?></span></li>
        </ul>
        <p class="klein"><?= e(nettoText($saving, $unit * $quantity)) ?> Die Ersparnis ist eine Schätzung aus deinen Stromkosten (<?= e(euro($user->oldMonthlyCost)) ?> pro Monat).</p>
        <p class="klein">Der Betrag wird mit der Abo-Gebühr abgerechnet, nicht sofort. Nach 24 Monaten gehört dir die Technik, ohne weitere Kosten.</p>
    </aside>
</div>
<?php endif; ?>
<?php pageEnd();
