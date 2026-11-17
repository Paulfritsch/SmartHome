<?php

declare(strict_types=1);

// Macht ein bestehendes Konto zum Admin (Zugriff auf admin.php: offene Bestellungen und Lieferadressen).
//
//   php prototype/bin/make-admin.php anna@example.de          # Admin-Rechte geben
//   php prototype/bin/make-admin.php anna@example.de --remove  # Admin-Rechte entziehen
//
// Das Konto muss vorher ueber die Registrierung angelegt worden sein. Es gibt bewusst keinen
// Standard-Admin und keine Webseite, um Admin zu werden.

require_once __DIR__ . '/../../vendor/autoload.php';

use SmartSave\Database;
use SmartSave\UserRepository;

if (PHP_SAPI !== 'cli') {
    exit('Nur im Terminal ausführen.');
}

$email = $argv[1] ?? '';
if ($email === '') {
    fwrite(STDERR, "Aufruf: php prototype/bin/make-admin.php <E-Mail> [--remove]\n");
    exit(1);
}

$users = new UserRepository(Database::connect(getenv('SMARTSAVE_DB') ?: Database::defaultPath()));
$grant = !in_array('--remove', $argv, true);

if (!$users->setAdmin($email, $grant)) {
    fwrite(STDERR, "Kein Konto mit dieser E-Mail gefunden. Bitte zuerst registrieren.\n");
    exit(1);
}

echo $grant ? "$email ist jetzt Admin.\n" : "$email ist kein Admin mehr.\n";
