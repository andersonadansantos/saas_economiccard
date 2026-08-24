<?php
// Cloudflare Turnstile — configuração e helpers centralizados.
// Incluído automaticamente via config.php.

// Valores definidos em secrets_local.php (fora do git) ou via variáveis de ambiente.
if (!defined('TURNSTILE_SITE_KEY')) {
    define('TURNSTILE_SITE_KEY', getenv('TURNSTILE_SITE_KEY') ?: '');
}
if (!defined('TURNSTILE_SECRET')) {
    define('TURNSTILE_SECRET', getenv('TURNSTILE_SECRET') ?: '');
}

// Turnstile só fica ativo quando site key E secret estão configurados.
// Sem chaves, os widgets não são renderizados e a validação é ignorada
// (evita bloquear todos os logins em ambientes sem configuração).
function turnstile_ativo() {
    return trim((string)TURNSTILE_SITE_KEY) !== '' && trim((string)TURNSTILE_SECRET) !== '';
}

// Script do widget (colocar no <head>)
function turnstile_script() {
    echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

// Widget (colocar dentro do <form>)
function turnstile_widget($theme = 'light') {
    if (!turnstile_ativo()) return;
    $theme = in_array($theme, ['light', 'dark', 'auto'], true) ? $theme : 'light';
    echo '<div class="cf-turnstile" data-sitekey="' . TURNSTILE_SITE_KEY . '" data-theme="' . $theme . '"></div>';
}

// Valida o token enviado no POST; retorna true se passou.
function verificar_turnstile($token = null) {
    if (!turnstile_ativo()) return true;
    if ($token === null) {
        $token = $_POST['cf-turnstile-response'] ?? '';
    }
    if (empty($token)) return false;

    $dados = http_build_query([
        'secret'   => TURNSTILE_SECRET,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    $resposta = false;
    if (function_exists('curl_init')) {
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dados);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $raw = curl_exec($ch);
        curl_close($ch);
        $resposta = json_decode($raw, true);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $dados,
            'timeout' => 10
        ]]);
        $raw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
        $resposta = json_decode($raw, true);
    }

    return is_array($resposta) && !empty($resposta['success']);
}

// Usado nos handlers de login: bloqueia a requisição quando a verificação falha.
// Define a mensagem de erro (por referência) e retorna true se deve bloquear.
function turnstile_bloqueado(&$erro) {
    if (verificar_turnstile()) {
        return false;
    }
    $erro = 'Falha na verificação de segurança. Tente novamente.';
    return true;
}