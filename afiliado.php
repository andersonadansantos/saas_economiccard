<?php
require_once 'config.php';

$erro = '';
if (isset($_GET['sair'])) {
    unset($_SESSION['afiliado_id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'login') {
    $bloqueado = turnstile_bloqueado($erro);
    if (!$bloqueado) {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $stmt = $conn->prepare("SELECT * FROM afiliados WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $af = $stmt->get_result()->fetch_assoc();
    if ($af && password_verify($senha, $af['senha'])) {
        $_SESSION['afiliado_id'] = $af['id'];
        header('Location: afiliado.php');
        exit;
    } else {
        $erro = 'E-mail ou senha inválidos.';
    }
    }
}

$af = null;
if (!empty($_SESSION['afiliado_id'])) {
    $stmt = $conn->prepare("SELECT * FROM afiliados WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['afiliado_id']);
    $stmt->execute();
    $af = $stmt->get_result()->fetch_assoc();
    if (!$af) {
        unset($_SESSION['afiliado_id']);
    }
}

$usuarios = null;
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/card/';
$oauthUrl = '';
if ($af) {
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE afiliado_token = ? ORDER BY id DESC");
    $stmt->bind_param('s', $af['token']);
    $stmt->execute();
    $usuarios = $stmt->get_result();

    $mpCfg = $conn->query("SELECT client_id, redirect_uri FROM api_pagamento WHERE id = 1")->fetch_assoc();
    if (!empty($mpCfg['client_id'])) {
        $oauthUrl = 'https://auth.mercadopago.com.br/authorization?client_id=' . urlencode($mpCfg['client_id']) . '&response_type=code&platform_id=mp';
        if (!empty($mpCfg['redirect_uri'])) {
            $oauthUrl .= '&redirect_uri=' . urlencode($mpCfg['redirect_uri']);
        }
        $oauthUrl .= '&state=' . (int)$af['id'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel do Afiliado - Economic Card</title>
<script src="https://cdn.tailwindcss.com"></script>
<?php turnstile_script(); ?>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Manrope', sans-serif; background-color: #f3f4f5; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
</style>
</head>
<body class="min-h-screen flex flex-col">
<?php if (!$af): ?>
<main class="flex-1 flex flex-col items-center justify-center px-6 py-10">
<div class="w-full max-w-md">
<div class="text-center mb-8">
<div class="w-16 h-16 mx-auto rounded-2xl bg-[#51036d] flex items-center justify-center mb-4">
<span class="material-symbols-outlined text-white text-[32px]">support_agent</span>
</div>
<h1 class="text-2xl font-extrabold text-[#191c1d]">Painel do Afiliado</h1>
<p class="text-sm text-gray-500 mt-1">Acesse com seu e-mail e senha</p>
</div>
<?php if ($erro): ?>
<div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
<form method="POST" action="afiliado.php" class="space-y-4">
<input type="hidden" name="acao" value="login"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">E-mail</label>
<input name="email" type="email" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Senha</label>
<input name="senha" type="password" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<?php turnstile_widget('light'); ?>
<button class="w-full bg-[#51036d] hover:bg-[#3a024d] text-white font-bold py-3 rounded-lg transition" type="submit">ENTRAR</button>
</form>
</div>
</div>
</main>
<?php else: ?>
<header class="bg-[#51036d] text-white px-6 py-5">
<div class="max-w-3xl mx-auto flex items-center justify-between gap-3">
<div class="flex items-center gap-3 min-w-0">
<div class="w-11 h-11 rounded-full bg-white/15 flex items-center justify-center shrink-0">
<span class="material-symbols-outlined">support_agent</span>
</div>
<div class="min-w-0">
<h1 class="font-extrabold truncate"><?php echo htmlspecialchars($af['nome']); ?></h1>
<p class="text-xs text-white/70">Painel do Afiliado</p>
</div>
</div>
<div class="flex items-center gap-2 shrink-0">
<a href="gerar_contrato_afiliado.php" download="Contrato_Economic_Card_Vendas_por_Comissao.pdf" class="text-white/80 hover:text-white text-sm font-semibold inline-flex items-center gap-1">
<span class="material-symbols-outlined text-[16px]">download</span> Baixar Contrato
</a>
<a href="afiliado.php?sair=1" class="text-white/80 hover:text-white text-sm font-semibold">Sair</a>
</div>
</div>
</header>
<main class="flex-1 max-w-3xl mx-auto w-full px-6 py-6 space-y-6">
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
<h2 class="text-sm font-extrabold text-gray-800 uppercase mb-3">Seu link de indicação</h2>
<p class="text-xs text-gray-500 mb-3">Compartilhe este link. Os cadastros feitos por ele aparecem abaixo.</p>
<div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5">
<code class="text-xs text-gray-600 break-all flex-1"><?php echo htmlspecialchars($baseUrl . 'cadastro.php?afiliado=' . $af['token']); ?></code>
<button type="button" class="text-[#51036d] hover:underline text-xs font-bold shrink-0" onclick="copiarLink(this)">Copiar</button>
</div>
</div>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
<h2 class="text-sm font-extrabold text-gray-800 uppercase mb-3">Mercado Pago (recebimento de comissão)</h2>
<?php if (empty($oauthUrl)): ?>
<p class="text-xs text-gray-500">A conexão com o Mercado Pago ainda não foi configurada pelo administrador. Volte em breve.</p>
<?php else: ?>
<?php if (!empty($af['mp_user_id'])): ?>
<div class="flex items-center gap-2 mb-3">
<span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase bg-[#3e6a00] text-white">Conectado</span>
<?php if (!empty($af['mp_token_em'])): ?>
<span class="text-xs text-gray-500">Desde <?php echo date('d/m/Y H:i', strtotime($af['mp_token_em'])); ?></span>
<?php endif; ?>
</div>
<div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-3">
<p class="text-xs font-bold text-gray-500 uppercase">User ID do vendedor</p>
<p class="font-semibold text-gray-800 mt-1"><?php echo htmlspecialchars($af['mp_user_id']); ?></p>
</div>
<a href="<?php echo htmlspecialchars($oauthUrl); ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 bg-[#51036d] hover:bg-[#3a024d] text-white text-sm font-bold px-4 py-2.5 rounded-lg transition">Reconectar conta</a>
<?php else: ?>
<p class="text-xs text-gray-500 mb-3">Conecte sua conta do Mercado Pago para receber sua comissão pelo Split de pagamentos. Após autorizar, você será redirecionado de volta.</p>
<a href="<?php echo htmlspecialchars($oauthUrl); ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 bg-[#3e6a00] hover:bg-[#2e5000] text-white text-sm font-bold px-4 py-2.5 rounded-lg transition">
<span class="material-symbols-outlined text-[18px]">account_balance_wallet</span> Conectar Mercado Pago
</a>
<?php endif; ?>
<?php endif; ?>
</div>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
<div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
<h2 class="font-extrabold text-gray-800">Usuários cadastrados</h2>
<span class="text-xs font-bold text-[#3e6a00] bg-[#b6f570]/30 px-2 py-1 rounded-full"><?php echo $usuarios->num_rows; ?> total</span>
</div>
<?php if ($usuarios->num_rows === 0): ?>
<div class="px-5 py-12 text-center">
<p class="text-gray-500 text-sm">Nenhum usuário cadastrado pelo seu link ainda.</p>
</div>
<?php endif; ?>
<?php while ($u = $usuarios->fetch_assoc()): ?>
<div class="px-5 py-4 border-b border-gray-100 last:border-0 flex items-center justify-between gap-3">
<div class="min-w-0">
<p class="font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($u['nome']); ?></p>
<p class="text-xs text-gray-500"><?php echo htmlspecialchars($u['cpf']); ?> · <?php echo date('d/m/Y', strtotime($u['criado_em'])); ?></p>
</div>
<span class="shrink-0 px-2 py-1 rounded-full text-[10px] font-bold uppercase text-white <?php echo $u['cartao_ativo'] ? 'bg-[#3e6a00]' : 'bg-[#ba1a1a]'; ?>"><?php echo $u['cartao_ativo'] ? 'ATIVADO' : 'DESATIVADO'; ?></span>
</div>
<?php endwhile; ?>
</div>
</main>
<?php endif; ?>
<footer class="py-5 text-center">
<p class="text-xs text-gray-400">© 2024 ECONOMIC CARD. TODOS OS DIREITOS RESERVADOS.</p>
</footer>
<script>
function copiarLink(btn) {
    const code = btn.previousElementSibling;
    const texto = code.textContent.trim();
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto).then(() => {
            btn.textContent = 'Copiado!';
            setTimeout(() => btn.textContent = 'Copiar', 2000);
        });
    } else {
        const ta = document.createElement('textarea');
        ta.value = texto;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        btn.textContent = 'Copiado!';
        setTimeout(() => btn.textContent = 'Copiar', 2000);
    }
}
</script>
</body>
</html>
