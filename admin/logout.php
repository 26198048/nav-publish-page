<?php
require_once __DIR__ . '/../includes/security.php';
admin_logout();
header('Location: index.php');
exit;
