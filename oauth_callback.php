<?php
require_once 'config.php';

$code = trim($_GET['code'] ?? '');
$state = trim($_GET['state'] ?? '');
$erro = '';
$sucesso = false;
$salvo = '';
$dados = [];

$cfg = $conn->query("SELECT * FROM api_pagamento WHERE id = 1")->fetch_assoc();

if ($code === '') {
    $erro = 'Nenhum código de autorização recebido. Clique em "Autorizar" no Mercado Pago para ser redirecionado até aqui com o código.';
} else {
    $client_id = trim($cfg['client_id'] ?? '');
    $client_secret = trim($cfg['client_secret'] ?? '');
    $redirect_uri = trim($cfg['redirect_uri'] ?? '');
    if ($client_id === '' || $client_secret === '' || $redirect_uri === '') {
        $erro = 'Credenciais incompletas. Preencha Client ID, Client Secret e Redirect URI na página "API Pagamento" do admin e salve.';
    } else {
        $post = [
            'grant_type' => 'authorization_code',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'code' => $code,
            'redirect_uri' => $redirect_uri,
        ];
        $ch = curl_init('https://api.mercadopago.com/oauth/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $resp = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErro = curl_error($ch);
        curl_close($ch);

        if ($curlErro !== '') {
            $erro = 'Falha na conexão com o Mercado Pago: ' . $curlErro;
        } else {
            $dados = json_decode($resp, true);
            if ($httpCode >= 200 && $httpCode < 300 && !empty($dados['access_token'])) {
                if ($state === 'parceiro') {
                    // Autorização do PARCEIRO (sócio): token vai para api_pagamento e
                    // o User ID dele é preenchido automaticamente — sem digitação manual.
                    $stmt = $conn->prepare("UPDATE api_pagamento SET parceiro_user_id = ?, parceiro_access_token = ?, parceiro_refresh_token = ?, parceiro_token_em = NOW() WHERE id = 1");
                    $stmt->bind_param('sss', (string)$dados['user_id'], $dados['access_token'], $dados['refresh_token'] ?? '');
                    $stmt->execute();
                    if ($stmt->affected_rows >= 0) {
                        $salvo = 'Conta do PARCEIRO conectada (User ID ' . $dados['user_id'] . '). O Split já está habilitado para os próximos pagamentos.';
                    }
                } elseif ($state !== '' && ctype_digit($state)) {
                    $stmt = $conn->prepare("UPDATE afiliados SET mp_user_id = ?, mp_access_token = ?, mp_refresh_token = ?, mp_token_em = NOW() WHERE id = ?");
                    $stmt->bind_param('sssi', (string)$dados['user_id'], $dados['access_token'], $dados['refresh_token'] ?? '', (int)$state);
                    $stmt->execute();
                    if ($stmt->affected_rows > 0) {
                        $salvo = 'Conta conectada ao afiliado #' . (int)$state . '.';
                    }
                }
                $sucesso = true;
            } else {
                $msg = is_array($dados) ? ($dados['message'] ?? 'resposta inesperada') : $resp;
                $erro = 'Erro ao trocar o código pelo token (HTTP ' . $httpCode . '): ' . $msg;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OAuth Mercado Pago - Economic Card</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Manrope', sans-serif; }
</style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">
<div class="w-full max-w-lg bg-white rounded-2xl shadow-sm p-8">
<div class="flex items-center gap-4 mb-6">
<div class="w-14 h-14 rounded-2xl bg-[#51036d]/10 text-[#51036d] flex items-center justify-center">
<span class="material-symbols-outlined text-3xl">account_balance_wallet</span>
</div>
<div>
<h1 class="text-xl font-extrabold text-gray-800">Integração Mercado Pago</h1>
<p class="text-sm text-gray-500">Autorização OAuth do vendedor/afiliado</p>
</div>
</div>
<?php if ($sucesso): ?>
<div class="mb-4 p-4 rounded-xl bg-green-50 border border-green-200">
<p class="font-bold text-green-700 mb-1">Conta Mercado Pago conectada com sucesso!</p>
<?php if ($salvo): ?>
<p class="text-sm text-green-700"><?php echo htmlspecialchars($salvo); ?></p>
<?php else: ?>
<p class="text-sm text-green-700">A autorização foi concluída, mas nenhum afiliado foi informado (faltou o parâmetro state).</p>
<?php endif; ?>
</div>
<div class="space-y-2 text-sm">
<div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
<p class="text-xs font-bold text-gray-500 uppercase">User ID do vendedor</p>
<p class="font-semibold text-gray-800 mt-1"><?php echo htmlspecialchars((string)($dados['user_id'] ?? '-')); ?></p>
</div>
<div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
<p class="text-xs font-bold text-gray-500 uppercase">Access Token</p>
<p class="font-semibold text-gray-800 mt-1 break-all"><?php echo htmlspecialchars((string)($dados['access_token'] ?? '-')); ?></p>
</div>
</div>
<?php else: ?>
<div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>
<a href="admin_api_pagamento.php" class="mt-6 inline-flex w-full justify-center bg-[#51036d] hover:bg-[#3a024d] text-white font-bold px-6 py-3 rounded-xl transition">Voltar ao painel</a>
</div>
</body>
</html>
