<?php
require_once 'config.php';

// ====== CREDENCIAIS FACEBOOK OAuth ======
// Preencha com os dados do seu app em https://developers.facebook.com/apps
// (Menu: Configurações > Básico) e em "Login com Facebook > Configurações"
// Valores definidos em secrets_local.php (fora do git) ou via variáveis de ambiente.
if (!defined('FB_APP_ID')) {
    define('FB_APP_ID', getenv('FB_APP_ID') ?: '');
}
if (!defined('FB_APP_SECRET')) {
    define('FB_APP_SECRET', getenv('FB_APP_SECRET') ?: '');
}
define('FB_API_VERSION', 'v19.0');
define('FB_REDIRECT_URI', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/fb_callback.php');

$origem = $_GET['origem'] ?? 'app';
$state = bin2hex(random_bytes(16));
$_SESSION['fb_oauth_state'] = $state;
$_SESSION['fb_oauth_origem'] = $origem;

$params = [
    'client_id'    => FB_APP_ID,
    'redirect_uri' => FB_REDIRECT_URI,
    'response_type' => 'code',
    'scope'        => 'email',
    'state'        => $state,
];

header('Location: https://www.facebook.com/' . FB_API_VERSION . '/dialog/oauth?' . http_build_query($params));
exit;
