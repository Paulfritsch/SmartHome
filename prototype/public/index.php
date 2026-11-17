<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

redirect(currentUser($users) !== null ? 'dashboard.php' : 'login.php');
