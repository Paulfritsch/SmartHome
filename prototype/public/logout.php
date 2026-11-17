<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $_SESSION = [];
    session_destroy();
}

redirect('login.php');
