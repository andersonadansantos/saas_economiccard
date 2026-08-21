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

function fbHttpGet($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

$origem = $_SESSION['fb_oauth_origem'] ?? 'app';

if (isset($_GET['error'])) {
    $erro = 'Você cancelou o login ou ocorreu um erro na autenticação com o Facebook.';
} elseif (empty($_GET['code'])) {
    $erro = 'Resposta inválida do Facebook (código ausente).';
} elseif (empty($_GET['state']) || empty($_SESSION['fb_oauth_state']) || $_GET['state'] !== $_SESSION['fb_oauth_state']) {
    $erro = 'Falha de segurança: estado inválido. Tente novamente.';
} else {
    // Troca o code por token de acesso
    $tokenUrl = 'https://graph.facebook.com/' . FB_API_VERSION . '/oauth/access_token?' . http_build_query([
        'client_id'     => FB_APP_ID,
        'client_secret' => FB_APP_SECRET,
        'redirect_uri'  => FB_REDIRECT_URI,
        'code'          => $_GET['code'],
    ]);
    $tokenData = json_decode(fbHttpGet($tokenUrl), true);

    if (empty($tokenData['access_token'])) {
        $erro = 'Falha ao obter o token de acesso do Facebook. Tente novamente.';
    } else {
        // Busca o perfil do usuário (id, nome, e-mail, avatar)
        $profileUrl = 'https://graph.facebook.com/' . FB_API_VERSION . '/me?' . http_build_query([
            'fields'       => 'id,name,email,picture.width(400)',
            'access_token' => $tokenData['access_token'],
        ]);
        $f = json_decode(fbHttpGet($profileUrl), true);

        if (empty($f['id'])) {
            $erro = 'Não foi possível obter os dados da sua conta Facebook.';
        } else {
            $fId     = trim($f['id']);
            $fName   = trim($f['name'] ?? '');
            $fEmail  = strtolower(trim($f['email'] ?? ''));
            $fAvatar = trim($f['picture']['data']['url'] ?? '');

            // Procura por facebook_id ou e-mail
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE facebook_id = ? OR email = ? LIMIT 1");
            $stmt->bind_param('ss', $fId, $fEmail);
            $stmt->execute();
            $userLogin = $stmt->get_result()->fetch_assoc();

            if ($userLogin) {
                // Vincula o facebook_id se ainda não estiver salvo
                if (empty($userLogin['facebook_id'])) {
                    $upd = $conn->prepare("UPDATE usuarios SET facebook_id = ? WHERE id = ?");
                    $upd->bind_param('si', $fId, $userLogin['id']);
                    $upd->execute();
                }
                $_SESSION['usuario_id'] = $userLogin['id'];
                unset($_SESSION['fb_oauth_state'], $_SESSION['fb_oauth_origem']);

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
                $_SESSION['fb_oauth_pending'] = [
                    'facebook_id' => $fId,
                    'nome'        => $fName,
                    'email'       => $fEmail,
                    'avatar'      => $fAvatar,
                    'afiliado'    => $afiliadoToken,
                    'origem'      => $origem,
                ];
                unset($_SESSION['fb_oauth_state'], $_SESSION['fb_oauth_origem']);
                header('Location: cadastro_facebook.php');
                exit;
            }
        }
    }
}

// Em caso de erro, redireciona de volta ao login correto
if (isset($erro)) {
    $_SESSION['fb_login_erro'] = $erro;
    $destinoLogin = ($origem === 'web') ? 'web/login.php' : 'login.php';
    header('Location: ' . $destinoLogin);
    exit;
}
