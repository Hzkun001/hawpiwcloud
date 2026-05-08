<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

authLogout();

header('Location: login.php?status=logged_out');
exit;
