<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$current = currentUser($users);
redirect($current !== null ? homePage($current) : 'login.php');
