<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use SmartSave\Catalog;
use SmartSave\ValidationException;

// Fuer Konten ohne Paket-Bestellung (z. B. vor der Einfuehrung der Lieferadresse angelegt)
$user = requireUser($users);

if ($orders->hasBaseOrder($user->id)) {
    flash('ok', ['Dein Basis-Set ist schon bestellt.']);
    redirect('dashboard.php');
}

$form = ['name' => $user->name, 'model' => '', 'street' => '', 'zip' => '', 'city' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    foreach (array_keys($form) as $key) {
        $form[$key] = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    }
    try {
        $orders->placeModel($user, $form['model'], $form);
        flash('ok', ['Paket bestellt. Wir liefern an ' . trim($form['street']) . ', ' . trim($form['zip']) . ' ' . trim($form['city']) . ', bezahlt wird vor Ort.']);
        redirect('dashboard.php');
    } catch (ValidationException $e) {
        flash('fehler', $e->getErrors());
    }
}

pageStart('Paket wählen', $user);
?>
<section class="karte schmal">
    <h1>Paket wählen</h1>
    <p>Du hast noch kein Paket gewählt. Bezahlt wird bei der Lieferung vor Ort.</p>
    <form method="post">
        <?= csrfField() ?>
        <?php modelChoice($form['model']); ?>

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
        <button type="submit">Paket bestellen</button>
    </form>
    <p class="klein"><a href="dashboard.php">Zurück zu meiner Wohnung</a></p>
</section>
<?php pageEnd();
