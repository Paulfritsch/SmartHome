<?php

declare(strict_types=1);

// Router fuer den eingebauten PHP-Server: Website und Prototyp laufen so unter einer Adresse.
//
//   php -S localhost:8000 prototype/router.php
//
// "/" und alle Website-Seiten (smarthome/) kommen aus dem Website-Ordner,
// login.php, dashboard.php usw. aus prototype/public/. Nur Dateien aus diesen beiden
// Ordnern werden ausgeliefert; die Datenbank (prototype/data/) ist nicht erreichbar.

$roots = [
    realpath(__DIR__ . '/../smarthome'),
    realpath(__DIR__ . '/public'),
];

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if ($path === '/' || $path === '') {
    $path = '/index.html';
}

$mimeTypes = [
    'html' => 'text/html; charset=UTF-8',
    'css' => 'text/css; charset=UTF-8',
    'js' => 'text/javascript; charset=UTF-8',
    'woff2' => 'font/woff2',
    'txt' => 'text/plain; charset=UTF-8',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'ico' => 'image/x-icon',
];

foreach ($roots as $root) {
    if ($root === false) {
        continue;
    }
    $file = realpath($root . $path);
    // realpath loest ".." auf; liegt das Ergebnis ausserhalb des Ordners, wird nichts ausgeliefert
    if ($file === false || !is_file($file) || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
        continue;
    }

    $name = basename($file);
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if ($extension === 'php') {
        // Dateien mit Unterstrich (z. B. _bootstrap.php) sind nur zum Einbinden gedacht
        if (str_starts_with($name, '_') || $root !== $roots[1]) {
            continue;
        }
        $_SERVER['SCRIPT_NAME'] = '/' . $name;
        $_SERVER['SCRIPT_FILENAME'] = $file;
        require $file;
        return true;
    }

    if (!isset($mimeTypes[$extension])) {
        continue;
    }
    header('Content-Type: ' . $mimeTypes[$extension]);
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    return true;
}

http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html lang="de"><meta charset="UTF-8"><title>Nicht gefunden</title><p>Diese Seite gibt es nicht. <a href="/">Zur Startseite</a></p></html>';
return true;
