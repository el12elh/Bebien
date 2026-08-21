<?php
require_once __DIR__ . '/../includes/auth.php';
signout();
header('Location: /');
exit;
