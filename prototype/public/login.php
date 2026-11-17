<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$current = currentUser($users);
if ($current !== null) {
    redirect(homePage($current));
}

$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    $user = $users->authenticate($email, $password);
    if ($user !== null) {
        session_regenerate_id(true); // neue Sitzungs-ID nach dem Login (gegen Session Fixation)
        $_SESSION['user_id'] = $user->id;
        redirect(homePage($user));
    }

    // bewusst eine Meldung fuer beide Faelle: man erfaehrt nicht, ob die E-Mail existiert
    flash('fehler', ['E-Mail oder Passwort stimmen nicht.']);
}

pageStart('Anmelden');
?>
<section class="karte schmal">
    <h1>Anmelden</h1>
    <form method="post">
        <?= csrfField() ?>
        <label for="email">E-Mail</label>
        <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>

        <label for="password">Passwort</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <button type="submit">Anmelden</button>
    </form>
    <p class="klein">Noch kein Konto? <a href="register.php">Konto anlegen</a></p>
</section>
<?php
pageEnd();
