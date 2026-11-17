<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use SmartSave\ExampleApartment;
use SmartSave\ValidationException;

if (currentUser($users) !== null) {
    redirect('dashboard.php');
}

$form = ['name' => '', 'email' => '', 'price' => '', 'old_cost' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    foreach (array_keys($form) as $field) {
        $form[$field] = is_string($_POST[$field] ?? null) ? $_POST[$field] : '';
    }
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    try {
        $user = $users->register($form['name'], $form['email'], $password, $form['price'], $form['old_cost']);
        if (isset($_POST['examples'])) {
            ExampleApartment::create($user->id, $rooms, $devices);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user->id;
        flash('ok', ['Dein Konto ist angelegt. Lege jetzt Räume und Geräte an.']);
        redirect('dashboard.php');
    } catch (ValidationException $e) {
        flash('fehler', $e->getErrors());
    }
}

pageStart('Konto anlegen');
?>
<section class="karte schmal">
    <h1>Konto anlegen</h1>
    <form method="post">
        <?= csrfField() ?>
        <label for="name">Name</label>
        <input id="name" name="name" value="<?= e($form['name']) ?>" maxlength="60" autocomplete="name" required>

        <label for="email">E-Mail</label>
        <input id="email" name="email" type="email" value="<?= e($form['email']) ?>" autocomplete="username" required>

        <label for="password">Passwort <span class="klein">(mindestens 8 Zeichen)</span></label>
        <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>

        <label for="price">Dein Strompreis in € pro kWh</label>
        <input id="price" name="price" inputmode="decimal" placeholder="0,35" value="<?= e($form['price']) ?>" required>

        <label for="old_cost">Deine Energiekosten heute in € pro Monat <span class="klein">(optional, sonst 150 €)</span></label>
        <input id="old_cost" name="old_cost" inputmode="decimal" placeholder="150" value="<?= e($form['old_cost']) ?>">

        <label class="check"><input type="checkbox" name="examples" value="1" checked> Beispielwohnung anlegen (3 Räume mit Geräten)</label>

        <button type="submit">Konto anlegen</button>
    </form>
    <p class="klein">Mit dem Konto startet dein Demo-Abo: <?= e(number_format(\SmartSave\Catalog::BASE_MONTHLY_FEE, 2, ',', '.')) ?> € pro Monat, <?= e(\SmartSave\Catalog::TERM_MONTHS) ?> Monate Laufzeit, danach gehört dir die Technik. Es wird nichts abgebucht.</p>
    <p class="klein">Schulprojekt: Bitte keine echten Zugangsdaten verwenden, die du anderswo benutzt.</p>
    <p class="klein">Schon ein Konto? <a href="login.php">Anmelden</a></p>
</section>
<?php
pageEnd();
