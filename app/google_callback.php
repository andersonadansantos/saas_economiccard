<?php
require_once 'config.php';

// ====== LOGIN COM GOOGLE (retorno do OAuth 2.0) =====
// O Google devolve ?code=...&state=... (sucesso) ou ?error=... (falha/cancelamento).
// O state prova que o pedido partiu desta instalação (proteção CSRF do OAuth).

$origem       = (($_SESSION['google_oauth_origem'] ?? 'app') === 'web') ? 'web' : 'app';
$destinoLogin = ($origem === 'web') ? 'web/login.php' : 'login.php';
$redirectUri  = google_redirect_uri();

// HTTP POST/GET para as APIs do Google, com verificação SSL ativada,
// timeouts e captura de falhas de rede (curl_errno) para diagnóstico.
function googleHttpPost($url, $data) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST            => true,
        CURLOPT_POSTFIELDS      => http_build_query($data),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT         => 30,
        CURLOPT_SSL_VERIFYPEER  => true,
        CURLOPT_SSL_VERIFYHOST  => 2,
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        app_log('google', 'curl POST falhou em ' . $url . ': ' . curl_error($ch) . ' (errno ' . curl_errno($ch) . ')');
    }
    curl_close($ch);
    return $res;
}

function googleHttpGetJson($url, $bearer) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $bearer, 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT         => 30,
        CURLOPT_SSL_VERIFYPEER  => true,
        CURLOPT_SSL_VERIFYHOST  => 2,
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        app_log('google', 'curl GET falhou em ' . $url . ': ' . curl_error($ch) . ' (errno ' . curl_errno($ch) . ')');
    }
    curl_close($ch);
    return $res;
}

$erro = '';
$g    = null;

if (isset($_GET['error'])) {
    $detalhe = (string)$_GET['error'];
    app_log('google', 'autorização recusada pelo Google: ' . $detalhe);
    $erro = ($detalhe === 'access_denied')
        ? 'Você cancelou o login com o Google. Tente novamente ou entre com seu CPF.'
        : 'Não foi possível concluir o login com o Google (' . $detalhe . '). Tente novamente.';
} elseif (empty($_GET['code'])) {
    $erro = 'Resposta inválida do Google (código ausente). Tente novamente.';
} elseif (empty($_GET['state']) || empty($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], (string)$_GET['state'])) {
    // Sessão expirou (o cookie dura 7 dias, mas o state só vale uma tentativa).
    app_log('google', 'state inválido/expirado (sessão perdida ou reuso do link).');
    $erro = 'Sua sessão de login com o Google expirou. Clique novamente em "Entrar com Google".';
} else {
    // 1) Troca o code pelo token de acesso.
    $tokenJson = googleHttpPost('https://oauth2.googleapis.com/token', [
        'code'          => $_GET['code'],
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => $redirectUri,
        'grant_type'    => 'authorization_code',
    ]);

    $tokenData = $tokenJson !== false ? json_decode($tokenJson, true) : null;
    if (!is_array($tokenData)) {
        app_log('google', 'resposta sem JSON do endpoint token.');
        $erro = 'Falha na comunicação com o Google. Tente novamente em instantes.';
    } elseif (isset($tokenData['error'])) {
        $code = (string)$tokenData['error'];
        app_log('google', 'endpoint token erro: ' . $code . ' — ' . (string)($tokenData['error_description'] ?? ''));
        if ($code === 'invalid_client') {
            $erro = 'Login com Google indisponível no momento (credenciais inválidas). Entre com seu CPF.';
        } elseif ($code === 'invalid_grant') {
            $erro = 'O código de autorização expirou ou já foi usado. Clique novamente em "Entrar com Google".';
        } elseif ($code === 'redirect_uri_mismatch') {
            app_log('google', 'redirect_uri NÃO registrado no Google Cloud Console: ' . $redirectUri);
            $erro = 'Configuração do Google incompleta (URI de retorno não autorizada). Entre com seu CPF e avise o suporte.';
        } else {
            $erro = 'Não foi possível concluir o login com o Google. Tente novamente.';
        }
    } elseif (empty($tokenData['access_token'])) {
        app_log('google', 'token de acesso ausente na resposta.');
        $erro = 'Falha ao obter o token de acesso do Google. Tente novamente.';
    } else {
        // 2) Busca o perfil do usuário (e-mail, nome, avatar, google_id).
        $profileJson = googleHttpGetJson('https://openidconnect.googleapis.com/v1/userinfo', $tokenData['access_token']);
        $g = $profileJson !== false ? json_decode($profileJson, true) : null;

        if (!is_array($g)) {
            app_log('google', 'resposta sem JSON do userinfo.');
            $erro = 'Não foi possível ler seus dados do Google. Tente novamente.';
        } elseif (empty($g['email'])) {
            $erro = 'Não foi possível obter o e-mail da sua conta Google.';
        } elseif (empty($g['email_verified'])) {
            $erro = 'Seu e-mail Google não é verificado. Verifique o e-mail na sua conta do Google e tente novamente.';
        }
    }
}

if ($erro === '' && is_array($g)) {
    $gEmail  = strtolower(trim((string)$g['email']));
    $gName   = trim((string)($g['name'] ?? ''));
    $gAvatar = trim((string)($g['picture'] ?? ''));
    $gId     = trim((string)($g['sub'] ?? ''));

    // 3) Procura o usuário por google_id (login repetido) ou por e-mail
    //    (conta criada por outro método com o mesmo e-mail → vincula o Google).
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE (google_id = ? AND ? <> '') OR email = ? ORDER BY (google_id = ?) DESC LIMIT 1");
    $stmt->bind_param('ssss', $gId, $gId, $gEmail, $gId);
    $stmt->execute();
    $userLogin = $stmt->get_result()->fetch_assoc();

    if ($userLogin) {
        if (($userLogin['status'] ?? 'ativo') === 'desativado') {
            app_log('google', 'login google bloqueado: conta desativada (id ' . $userLogin['id'] . ').');
            $erro = 'Esta conta foi encerrada. Entre em contato com o suporte para mais informações.';
        } else {
            // Vincula o google_id (e avatar, se o usuário não tiver) sem perder dados.
            if (empty($userLogin['google_id']) && $gId !== '') {
                if (empty($userLogin['avatar']) && $gAvatar !== '') {
                    $upd = $conn->prepare("UPDATE usuarios SET google_id = ?, avatar = ? WHERE id = ?");
                    $upd->bind_param('ssi', $gId, $gAvatar, $userLogin['id']);
                } else {
                    $upd = $conn->prepare("UPDATE usuarios SET google_id = ? WHERE id = ?");
                    $upd->bind_param('si', $gId, $userLogin['id']);
                }
                $upd->execute();
            } elseif (empty($userLogin['avatar']) && $gAvatar !== '') {
                $upd = $conn->prepare("UPDATE usuarios SET avatar = ? WHERE id = ?");
                $upd->bind_param('si', $gAvatar, $userLogin['id']);
                $upd->execute();
            }

            unset($_SESSION['tipo_conta'], $_SESSION['dependente_id'], $_SESSION['dependente_nome']);
            $_SESSION['usuario_id'] = (int)$userLogin['id'];
            unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_origem'], $_SESSION['google_oauth_afiliado']);
            app_log('google', 'login ok: usuario id ' . $userLogin['id'] . ' (origem ' . $origem . ')');

            $destino = ($origem === 'web') ? 'web/dashboard.php' : 'dashboard.php';
            if (!$userLogin['cartao_ativo']) {
                $destino = ($origem === 'web') ? 'web/ativar.php' : 'ativar.php';
            }
            header('Location: ' . $destino);
            exit;
        }
    } else {
        // 4) Novo usuário: completa o cadastro (CPF obrigatório).
        //    O token de afiliado veio pela sessão (google_login.php validou).
        $afiliadoToken = '';
        $afiliadoSessao = $_SESSION['google_oauth_afiliado'] ?? '';
        $afiliadoGet    = trim((string)($_GET['afiliado'] ?? ''));
        foreach ([$afiliadoSessao, $afiliadoGet] as $tk) {
            if ($tk === '') continue;
            $st = $conn->prepare("SELECT id FROM afiliados WHERE token = ?");
            $st->bind_param('s', $tk);
            $st->execute();
            if ($st->get_result()->num_rows > 0) {
                $afiliadoToken = $tk;
                break;
            }
        }

        $_SESSION['google_oauth_pending'] = [
            'google_id' => $gId,
            'nome'      => $gName,
            'email'     => $gEmail,
            'avatar'    => $gAvatar,
            'afiliado'  => $afiliadoToken,
            'origem'    => $origem,
        ];
        unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_origem'], $_SESSION['google_oauth_afiliado']);
        header('Location: cadastro_google.php');
        exit;
    }
}

// Em caso de erro, volta para a tela de login correta com mensagem amigável.
if ($erro !== '') {
    $_SESSION['google_login_erro'] = $erro;
    header('Location: ' . $destinoLogin);
    exit;
}
