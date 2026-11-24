<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

// Diese Objekte legt _bootstrap.php an (die Angaben helfen der statischen Analyse mit PHPStan):
/** @var \SmartSave\UserRepository $users */

$current = currentUser($users);
redirect($current !== null ? homePage($current) : 'login.php');
