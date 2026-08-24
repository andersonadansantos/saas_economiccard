<?php
require_once __DIR__ . '/../config.php';
unset($_SESSION['usuario_id']);
unset($_SESSION['google_oauth_pending']);
unset($_SESSION['fb_oauth_pending']);
header('Location: login.php');
exit;