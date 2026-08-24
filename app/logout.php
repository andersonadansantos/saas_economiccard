<?php
require_once 'config.php';
if (($_GET['admin'] ?? '') === '1') {
    unset($_SESSION['admin_logado'], $_SESSION['admin_usuario']);
    header('Location: admin_login.php');
    exit;
}
unset($_SESSION['usuario_id']);
unset($_SESSION['google_oauth_pending']);
unset($_SESSION['fb_oauth_pending']);
header('Location: login.php');
exit;