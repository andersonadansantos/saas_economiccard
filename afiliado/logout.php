<?php
require_once dirname(__DIR__) . '/config.php';
$_SESSION['afiliado_id'] = null;
unset($_SESSION['afiliado_id'], $_SESSION['afiliado_nome']);
header('Location: index.php');
exit;
