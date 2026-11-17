<?php

declare(strict_types=1);

// Gemeinsamer Start fuer alle Seiten: Autoloader, Sitzung, Datenbank, kleine Helfer.
// Der Unterstrich im Namen kennzeichnet: keine eigene Seite, wird nur eingebunden.

require_once __DIR__ . '/../../vendor/autoload.php';

use SmartSave\BookingRepository;
use SmartSave\Database;
use SmartSave\RoomRepository;
use SmartSave\DeviceRepository;
use SmartSave\OrderRepository;
use SmartSave\User;
use SmartSave\UserRepository;

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; form-action 'self'; frame-ancestors 'none'");

ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']),
]);
session_start();
date_default_timezone_set('Europe/Berlin'); // Zeitplaene gelten in deutscher Uhrzeit

$pdo = Database::connect(getenv('SMARTSAVE_DB') ?: Database::defaultPath());
$users = new UserRepository($pdo);
$rooms = new RoomRepository($pdo);
$devices = new DeviceRepository($pdo);
$bookings = new BookingRepository($pdo);
$orders = new OrderRepository($pdo, $bookings);

function e(string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $target): never
{
    header('Location: ' . $target);
    exit;
}

function currentUser(UserRepository $users): ?User
{
    $id = $_SESSION['user_id'] ?? null;

    return is_int($id) ? $users->findById($id) : null;
}

function requireUser(UserRepository $users): User
{
    $user = currentUser($users);
    if ($user === null) {
        redirect('login.php');
    }

    return $user;
}

// Nur fuer Admins (siehe bin/make-admin.php). Alle anderen sehen einen 403-Fehler, nicht die Seite.
function requireAdmin(UserRepository $users): User
{
    $user = requireUser($users);
    if (!$user->isAdmin) {
        http_response_code(403);
        pageStart('Kein Zugriff', $user);
        echo '<h1>Kein Zugriff</h1><p>Dieser Bereich ist nur für das SmartSave-Team.</p><p><a href="dashboard.php">Zurück zu meiner Wohnung</a></p>';
        pageEnd();
        exit;
    }

    return $user;
}

function euro(float $value): string
{
    return number_format($value, 2, ',', '.') . ' €';
}

function signedEuro(float $value): string
{
    return ($value < 0 ? '−' : '+') . euro(abs($value));
}

/** Zeitstempel aus SQLite (UTC) als deutsche Ortszeit. */
function berlinTime(string $utc): string
{
    $time = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $utc, new DateTimeZone('UTC'));

    return $time === false ? $utc : $time->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i');
}

function csrfToken(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

// Bricht bei fehlendem oder falschem Token ab: schuetzt vor Formularen, die eine fremde Seite abschickt.
function requireCsrf(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrfToken(), $sent)) {
        http_response_code(400);
        exit('Ungültige Anfrage. Bitte lade die Seite neu und versuche es noch einmal.');
    }
}

/** @param string[] $messages */
function flash(string $type, array $messages): void
{
    $_SESSION['flash'][] = ['type' => $type, 'messages' => $messages];
}

/** @return array<int, array{type: string, messages: string[]}> */
function takeFlash(): array
{
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $all;
}

function pageStart(string $title, ?User $user = null): void
{
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> – SmartSave</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<header class="kopf">
    <a class="marke" href="/">SmartSave</a>
    <?php if ($user !== null): ?>
        <form method="post" action="logout.php" class="abmelden">
            <?php if ($user->isAdmin): ?>
                <a href="admin.php">Bestellungen (Admin)</a>
            <?php endif; ?>
            <?= csrfField() ?>
            <span><?= e($user->name) ?></span>
            <button type="submit" class="leise">Abmelden</button>
        </form>
    <?php endif; ?>
</header>
<main>
    <?php foreach (takeFlash() as $note): ?>
        <div class="hinweis <?= e($note['type']) ?>" role="<?= $note['type'] === 'fehler' ? 'alert' : 'status' ?>">
            <ul>
                <?php foreach ($note['messages'] as $message): ?>
                    <li><?= e($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
<?php
}

function pageEnd(): void
{
    ?>
</main>
<footer class="fuss">SmartSave ist ein Schulprojekt (Berufsschule, Lernfeld 10). Die Geräte sind simuliert.</footer>
</body>
</html>
<?php
}
