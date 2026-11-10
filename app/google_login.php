<?php
require_once 'config.php';

// ====== LOGIN COM GOOGLE (início do fluxo OAuth 2.0) =====
// Credenciais vêm de secrets_local.php ou variáveis de ambiente (ver config.php).
// Sem credenciais não adianta enviar o usuário ao Google: o Google devolveria
// "invalid_request" na tela dele. Redireciona de volta com mensagem amigável.
if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '') {
    app_log('google', 'GOOGLE_CLIENT_ID/GOOGLE_CLIENT_SECRET ausentes nesta instalação — fluxo bloqueado.');
    $_SESSION['google_login_erro'] = 'Login com Google indisponível no momento. Entre com seu CPF.';
    $destino = (($_GET['origem'] ?? 'app') === 'web') ? 'web/login.php' : 'login.php';
    header('Location: ' . $destino);
    exit;
}

$origem = (($_GET['origem'] ?? 'app') === 'web') ? 'web' : 'app';

// Token de afiliado (indicação): o Google só devolve code+state no retorno,
// qualquer parâmetro extra se perde. Por isso o token viaja na sessão.
$afiliado = '';
if (!empty($_GET['afiliado'])) {
    $tk = trim((string)$_GET['afiliado']);
    $st = $conn->prepare("SELECT id FROM afiliados WHERE token = ?");
    $st->bind_param('s', $tk);
    $st->execute();
    if ($st->get_result()->num_rows > 0) {
        $afiliado = $tk;
    }
}

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state']     = $state;
$_SESSION['google_oauth_origem']    = $origem;
$_SESSION['google_oauth_afiliado']  = $afiliado;

$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => google_redirect_uri(),
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
];

app_log('google', "inicio: origem={$origem} redirect_uri=" . google_redirect_uri());

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit;
