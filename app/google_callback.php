<?php
require_once 'config.php';

// ====== CREDENCIAIS GOOGLE OAuth ======
// Valores definidos em secrets_local.php (fora do git) ou via variáveis de ambiente.
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
}
define('GOOGLE_REDIRECT_URI', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/google_callback.php');

function googleHttpPost($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

$origem = $_SESSION['google_oauth_origem'] ?? 'app';

if (isset($_GET['error'])) {
    $erro = 'Você cancelou o login ou ocorreu um erro na autenticação com o Google.';
} elseif (empty($_GET['code'])) {
    $erro = 'Resposta inválida do Google (código ausente).';
} elseif (empty($_GET['state']) || empty($_SESSION['google_oauth_state']) || $_GET['state'] !== $_SESSION['google_oauth_state']) {
    $erro = 'Falha de segurança: estado inválido. Tente novamente.';
} else {
    // Troca o code por token de acesso
    $token = googleHttpPost('https://oauth2.googleapis.com/token', [
        'code'          => $_GET['code'],
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]);
    $tokenData = json_decode($token, true);

    if (empty($tokenData['access_token'])) {
        $erro = 'Falha ao obter o token de acesso do Google. Tente novamente.';
    } else {
        // Busca o perfil do usuário (e-mail, nome, avatar)
        $ch = curl_init('https://openidconnect.googleapis.com/v1/userinfo');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenData['access_token']]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $profile = curl_exec($ch);
        curl_close($ch);
        $g = json_decode($profile, true);

        if (empty($g['email'])) {
            $erro = 'Não foi possível obter o e-mail da sua conta Google.';
        } else {
            $gEmail   = strtolower(trim($g['email']));
            $gName    = trim($g['name'] ?? '');
            $gAvatar  = trim($g['picture'] ?? '');
            $gId      = trim($g['sub'] ?? '');

            // Procura por google_id ou e-mail
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE google_id = ? OR email = ? LIMIT 1");
            $stmt->bind_param('ss', $gId, $gEmail);
            $stmt->execute();
            $userLogin = $stmt->get_result()->fetch_assoc();

            if ($userLogin) {
                // Vincula o google_id se ainda não estiver salvo
                if (empty($userLogin['google_id'])) {
                    $upd = $conn->prepare("UPDATE usuarios SET google_id = ? WHERE id = ?");
                    $upd->bind_param('si', $gId, $userLogin['id']);
                    $upd->execute();
                }
                $_SESSION['usuario_id'] = $userLogin['id'];
                unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_origem']);

                $destino = 'dashboard.php';
                if ($origem === 'web') {
                    $destino = 'web/dashboard.php';
                }
                if (!$userLogin['cartao_ativo']) {
                    $destino = ($origem === 'web' ? 'web/' : '') . 'ativar.php';
                }
                header('Location: ' . $destino);
                exit;
            } else {
                // Novo usuário: completa o cadastro com CPF (email/nome já preenchidos)
                $afiliadoToken = '';
                if (!empty($_GET['afiliado'])) {
                    $token = trim($_GET['afiliado']);
                    $st = $conn->prepare("SELECT id FROM afiliados WHERE token = ?");
                    $st->bind_param('s', $token);
                    $st->execute();
                    if ($st->get_result()->num_rows > 0) { $afiliadoToken = $token; }
                }
                $_SESSION['google_oauth_pending'] = [
                    'google_id' => $gId,
                    'nome'      => $gName,
                    'email'     => $gEmail,
                    'avatar'    => $gAvatar,
                    'afiliado'  => $afiliadoToken,
                    'origem'    => $origem,
                ];
                unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_origem']);
                header('Location: cadastro_google.php');
                exit;
            }
        }
    }
}

// Em caso de erro, redireciona de volta ao login correto
if (isset($erro)) {
    $_SESSION['google_login_erro'] = $erro;
    $destinoLogin = ($origem === 'web') ? 'web/login.php' : 'login.php';
    header('Location: ' . $destinoLogin);
    exit;
}
