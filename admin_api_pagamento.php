<?php
require_once 'config.php';
if (!($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin_login.php');
    exit;
}

// Senha da área protegida — definida em secrets_local.php (fora do git) ou via env ADMIN_API_SENHA.
if (!defined('ADMIN_API_SENHA')) {
    define('ADMIN_API_SENHA', getenv('ADMIN_API_SENHA') ?: '');
}
$senhaApi = ADMIN_API_SENHA;
$erro = '';
$desbloqueado = !empty($_SESSION['api_pagamento_desbloqueado']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['desbloquear'])) {
    $senhaDigitada = preg_replace('/\D/', '', trim($_POST['senha'] ?? ''));
    if ($senhaDigitada === $senhaApi) {
        $_SESSION['api_pagamento_desbloqueado'] = true;
        header('Location: admin_api_pagamento.php');
        exit;
    } else {
        $erro = 'Senha incorreta. Tente novamente.';
    }
}

if (!$desbloqueado) {
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API Pagamento - Admin Economic Card</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Manrope', sans-serif; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
</style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php require 'admin_menu.php'; ?>
<main class="md:ml-60 min-h-screen">
<header class="bg-white shadow-sm sticky top-0 z-30">
<div class="px-6 py-4 flex items-center justify-between">
<div>
<h1 class="text-xl font-extrabold text-gray-800">API Pagamento</h1>
<p class="text-sm text-gray-500">Área protegida por senha</p>
</div>
<a href="logout.php?admin=1" class="bg-[#51036d] hover:bg-[#3a024d] text-white rounded-lg px-4 py-2 text-sm font-semibold transition">Sair</a>
</div>
</header>
<div class="p-6">
<div class="max-w-md mx-auto bg-white rounded-xl shadow-sm p-6">
<div class="flex items-center gap-3 mb-4">
<div class="w-12 h-12 rounded-xl bg-[#51036d]/10 text-[#51036d] flex items-center justify-center"><span class="material-symbols-outlined">lock</span></div>
<div>
<h2 class="text-lg font-extrabold text-gray-800">Acesso restrito</h2>
<p class="text-sm text-gray-500">Digite a senha de 6 dígitos para acessar as credenciais da API de pagamento.</p>
</div>
</div>
<?php if ($erro): ?>
<div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>
<form method="POST" action="admin_api_pagamento.php">
<input type="hidden" name="desbloquear" value="1"/>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Senha de 6 dígitos</label>
<input type="password" name="senha" maxlength="6" inputmode="numeric" autocomplete="off" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d] tracking-[0.5em] text-center" placeholder="••••••" required/>
<button type="submit" class="mt-4 w-full bg-[#51036d] hover:bg-[#3a024d] text-white font-bold px-6 py-3 rounded-lg transition">ACESSAR</button>
</form>
</div>
</div>
</main>
</body>
</html>
<?php
    exit;
}

$sucesso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $access_token = trim($_POST['access_token'] ?? '');
    $public_key = trim($_POST['public_key'] ?? '');
    $client_id = trim($_POST['client_id'] ?? '');
    $client_secret = trim($_POST['client_secret'] ?? '');
    $marketplace_user_id = trim($_POST['marketplace_user_id'] ?? '');
    $parceiro_user_id = trim($_POST['parceiro_user_id'] ?? '');
    $parceiro_public_key = trim($_POST['parceiro_public_key'] ?? '');
    $porcentagem_parceiro = (float)str_replace(',', '.', $_POST['porcentagem_parceiro'] ?? '0');
    if ($porcentagem_parceiro < 0 || $porcentagem_parceiro > 100) { $porcentagem_parceiro = 0; }
    $webhook_url = trim($_POST['webhook_url'] ?? '');
    $redirect_uri = trim($_POST['redirect_uri'] ?? '');
    $sandbox = isset($_POST['sandbox']) ? 1 : 0;
    $stmt = $conn->prepare("UPDATE api_pagamento SET access_token=?, public_key=?, client_id=?, client_secret=?, marketplace_user_id=?, parceiro_user_id=?, parceiro_public_key=?, porcentagem_parceiro=?, webhook_url=?, redirect_uri=?, sandbox=? WHERE id=1");
    $stmt->bind_param('sssssssdssi', $access_token, $public_key, $client_id, $client_secret, $marketplace_user_id, $parceiro_user_id, $parceiro_public_key, $porcentagem_parceiro, $webhook_url, $redirect_uri, $sandbox);
    $stmt->execute();
    $sucesso = 'Configuração da API de Pagamento salva com sucesso!';
}

$cfg = $conn->query("SELECT * FROM api_pagamento WHERE id = 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API Pagamento - Admin Economic Card</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Manrope', sans-serif; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
</style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php require 'admin_menu.php'; ?>
<main class="md:ml-60 min-h-screen">
<header class="bg-white shadow-sm sticky top-0 z-30">
<div class="px-6 py-4 flex items-center justify-between">
<div>
<h1 class="text-xl font-extrabold text-gray-800">API Pagamento</h1>
<p class="text-sm text-gray-500">Split de pagamento Mercado Pago</p>
</div>
<a href="logout.php?admin=1" class="bg-[#51036d] hover:bg-[#3a024d] text-white rounded-lg px-4 py-2 text-sm font-semibold transition">Sair</a>
</div>
</header>
<div class="p-6">
<?php if ($sucesso): ?>
<div class="mb-6 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm"><?php echo htmlspecialchars($sucesso); ?></div>
<?php endif; ?>
<?php if ($erro): ?>
<div class="mb-6 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Credenciais Mercado Pago</h2>
<p class="text-sm text-gray-500 mb-4">Preencha as credenciais da sua aplicação para habilitar o Split de pagamento.</p>
<form method="POST" action="admin_api_pagamento.php" class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Access Token (produção)</label>
<input type="text" name="access_token" value="<?php echo htmlspecialchars($cfg['access_token'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="TEST-xxx-...">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Public Key</label>
<input type="text" name="public_key" value="<?php echo htmlspecialchars($cfg['public_key'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="APP_USR-xxx-...">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Client ID</label>
<input type="text" name="client_id" value="<?php echo htmlspecialchars($cfg['client_id'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Client Secret</label>
<input type="text" name="client_secret" value="<?php echo htmlspecialchars($cfg['client_secret'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">User ID (conta marketplace)</label>
<input type="text" name="marketplace_user_id" value="<?php echo htmlspecialchars($cfg['marketplace_user_id'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="ID numérico da sua conta">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">User ID do parceiro (recebedor do split)</label>
<input type="text" name="parceiro_user_id" value="<?php echo htmlspecialchars($cfg['parceiro_user_id'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Preenchido automaticamente pelo OAuth">
<p class="text-xs text-gray-500 mt-1">Preenchido automaticamente quando o parceiro conclui a autorização OAuth abaixo. Afiliados não recebem split.</p>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Public Key do parceiro (cartão no split)</label>
<input type="text" name="parceiro_public_key" value="<?php echo htmlspecialchars($cfg['parceiro_public_key'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="APP_USR-xxx-...">
<p class="text-xs text-gray-500 mt-1">Obrigatória para pagamento por CARTÃO com split: o token do cartão é gerado com a Public Key do coletor. O parceiro copia a dela em mercadopago.com.br/developers → "Suas integrações" → esta aplicação → Credenciais de produção.</p>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Porcentagem do parceiro (%)</label>
<input type="number" name="porcentagem_parceiro" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars(number_format((float)($cfg['porcentagem_parceiro'] ?? 0), 2, '.', '')); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Ex.: 30">
<p class="text-xs text-gray-500 mt-1">Quanto o parceiro ganha (% do valor do pagamento). O restante fica com a conta marketplace.</p>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">URL do Webhook</label>
<input type="text" name="webhook_url" value="<?php echo htmlspecialchars($cfg['webhook_url'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="https://seusite.com.br/webhook.php">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Redirect URI (OAuth)</label>
<input type="text" name="redirect_uri" value="<?php echo htmlspecialchars($cfg['redirect_uri'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="https://seusite.com.br/oauth_callback.php">
</div>
<div class="md:col-span-2 flex items-center gap-4">
<label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
<input type="checkbox" name="sandbox" class="w-4 h-4 accent-[#3e6a00]" <?php echo !empty($cfg['sandbox']) ? 'checked' : ''; ?>> Modo sandbox (testes)
</label>
<button type="submit" class="ml-auto bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition">SALVAR CREDENCIAIS</button>
</div>
</form>
</div>

<?php
$oauthBase = '';
$oauthRedirect = '';
if (!empty($cfg['client_id'])) {
    $oauthBase = 'https://auth.mercadopago.com.br/authorization?client_id=' . urlencode($cfg['client_id']) . '&response_type=code&platform_id=mp';
    if (!empty($cfg['redirect_uri'])) {
        $oauthRedirect = '&redirect_uri=' . urlencode($cfg['redirect_uri']);
    }
}
$urlParceiro = $oauthBase . $oauthRedirect . ($oauthBase !== '' ? '&state=parceiro' : '');
$parceiroConectado = !empty($cfg['parceiro_access_token']);
?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Autorização OAuth do PARCEIRO (Split)</h2>
<p class="text-sm text-gray-500 mb-4">Somente o <b>parceiro/sócio</b> precisa autorizar — afiliados não recebem split. O parceiro deve estar logado na conta Mercado Pago dele ao abrir a URL abaixo e clicar em <b>Autorizar</b>. Ao concluir, o sistema salva o token e preenche o <b>User ID do parceiro</b> automaticamente.</p>
<?php if ($oauthBase === ''): ?>
<div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg p-3 text-sm">Preencha o <b>Client ID</b> (e a <b>Redirect URI</b>) no formulário acima e clique em SALVAR CREDENCIAIS para gerar a URL de autorização do parceiro.</div>
<?php else: ?>
<div class="space-y-3">
<div class="<?php echo $parceiroConectado ? 'bg-green-50 border-green-200 text-green-800' : 'bg-yellow-50 border-yellow-200 text-yellow-800'; ?> border rounded-lg p-3 text-sm">
<?php if ($parceiroConectado): ?>
<b>Parceiro conectado.</b> User ID: <code><?php echo htmlspecialchars((string)$cfg['parceiro_user_id']); ?></code> — autorizado em <?php echo htmlspecialchars((string)($cfg['parceiro_token_em'] ?? '-')); ?>. Os tokens valem <b>6 meses</b>; depois disso refaça esta autorização.
<?php else: ?>
<b>Parceiro ainda NÃO conectado.</b> Enquanto ele não autorizar, os pagamentos seguem 100% para a empresa (sem split).
<?php endif; ?>
</div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">URL de autorização do parceiro</label>
<div class="flex items-center gap-2">
<code id="oauthUrl" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-700 break-all"><?php echo htmlspecialchars($urlParceiro); ?></code>
<button type="button" onclick="copiarOAuth(document.getElementById('oauthUrl').textContent.trim(), this)" class="bg-[#51036d] hover:bg-[#3a024d] text-white text-sm font-semibold rounded-lg px-4 py-2 shrink-0">Copiar</button>
<a href="<?php echo htmlspecialchars($urlParceiro); ?>" target="_blank" rel="noopener" class="bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-semibold rounded-lg px-4 py-2 shrink-0">Abrir</a>
</div>
<p class="text-sm text-gray-600">Após autorizar, o parceiro será redirecionado para <code><?php echo htmlspecialchars($cfg['redirect_uri']); ?></code> e o token será salvo automaticamente nesta página.</p>
</div>
<?php endif; ?>
<p class="text-xs text-gray-500 mt-3">Importante: a Redirect URI precisa estar cadastrada exatamente igual na aplicação do Mercado Pago e ser uma URL pública (o localhost só funciona para testes neste computador).</p>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Passo a passo: como conseguir as chaves e configurar o Split</h2>
<p class="text-sm text-gray-500 mb-4">Siga as etapas na ordem. Não pule nenhuma — cada uma depende da anterior para o Split funcionar de ponta a ponta.</p>

<div class="space-y-4">
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold">1</div>
<div>
<p class="font-bold text-gray-800 text-sm">Crie a conta Mercado Pago da sua empresa</p>
<p class="text-sm text-gray-600">Acesse <b>https://www.mercadopago.com.br</b> e clique em "Criar conta". Escolha o tipo <b>Pessoa Jurídica</b> (CNPJ) se a empresa tem CNPJ, ou <b>Pessoa Física</b> (CPF) para começar. Informe e-mail, senha e confirme o e-mail pelo link enviado. Essa será a conta <b>marketplace</b> (a conta principal que recebe e distribui o dinheiro).</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold">2</div>
<div>
<p class="font-bold text-gray-800 text-sm">Complete a verificação da conta</p>
<p class="text-sm text-gray-600">No painel, vá em <b>Configurações → Dados da conta → Verificação</b>. Envie os documentos exigidos (CNPJ, documentos dos sócios) e aguarde a aprovação. Cadastre também uma <b>chave Pix</b> em <b>Configurações → Carteira → Chave Pix</b>. Conta sem verificação e sem chave Pix não consegue receber nem repassar valores.</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold">3</div>
<div>
<p class="font-bold text-gray-800 text-sm">Crie a aplicação no Mercado Pago Developers</p>
<p class="text-sm text-gray-600">Acesse <b>https://www.mercadopago.com.br/developers/panel</b> e faça login com a conta criada no passo 1. Em <b>"Suas integrações"</b>, clique em <b>"Criar aplicação"</b>. Dê um nome (ex.: "Economic Card"), uma descrição e selecione a solução <b>Pagamentos online → Checkout API (Checkout Transparente)</b> (é ela que cobre PIX e cartão). Ao criar, você entra no painel da aplicação, onde estão as credenciais. Em <b>"Redirect URI"</b> da seção OAuth, cadastre a mesma URL que você vai informar no campo <b>Redirect URI (OAuth)</b> do formulário abaixo.</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold">4</div>
<div>
<p class="font-bold text-gray-800 text-sm">Copie cada chave no lugar certo da aplicação</p>
<p class="text-sm text-gray-600">No painel da aplicação existem duas abas: <b>Credenciais de produção</b> e <b>Credenciais de teste</b>. É na aba de <b>produção</b> que você vai copiar as chaves finais. Cada chave fica assim:</p>
<div class="mt-2 space-y-2">
<div class="bg-gray-50 border border-gray-200 rounded-lg p-3"><p class="font-bold text-gray-800 text-xs uppercase">Access Token (produção)</p><p class="text-sm text-gray-600 mt-1">Fica no início da aba <b>Credenciais de produção</b>. É um texto longo que começa com <code>APP_USR-</code> (produção) ou <code>TEST-</code> (teste). Copie exatamente o que está na aba de produção. Cuidado: a chave de teste começa com <code>TEST-</code> e só funciona no sandbox.</p></div>
<div class="bg-gray-50 border border-gray-200 rounded-lg p-3"><p class="font-bold text-gray-800 text-xs uppercase">Public Key</p><p class="text-sm text-gray-600 mt-1">Logo abaixo do Access Token, na mesma aba de credenciais. É a chave pública usada no formulário de pagamento do app. Em produção começa com <code>APP_USR-</code>.</p></div>
<div class="bg-gray-50 border border-gray-200 rounded-lg p-3"><p class="font-bold text-gray-800 text-xs uppercase">Client ID</p><p class="text-sm text-gray-600 mt-1">Fica nas <b>configurações da aplicação</b> (menu "Configurações" ou "Detalhes"). É um número simples (ex.: <code>123456789</code>).</p></div>
<div class="bg-gray-50 border border-gray-200 rounded-lg p-3"><p class="font-bold text-gray-800 text-xs uppercase">Client Secret</p><p class="text-sm text-gray-600 mt-1">Fica ao lado do Client ID, nas mesmas configurações da aplicação. É uma sequência longa de letras e números. Funciona como a senha da aplicação — nunca compartilhe.</p></div>
</div>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold">5</div>
<div>
<p class="font-bold text-gray-800 text-sm">Preencha os campos "Credenciais Mercado Pago" acima</p>
<p class="text-sm text-gray-600">Cole cada chave copiada no passo 4 no campo correspondente do formulário acima: Access Token (produção), Public Key, Client ID, Client Secret. No campo <b>User ID (conta marketplace)</b> informe o número da conta Mercado Pago principal — ele aparece na URL do seu painel (<code>https://www.mercadopago.com.br/home?ref=</code>) ou em <b>Configurações → Dados da conta</b>. O campo <b>User ID do parceiro</b> é preenchido <b>automaticamente</b> quando o parceiro conclui a autorização OAuth (passo 7) — não precisa digitar. Deixe o campo <b>URL do Webhook</b> para o passo 8. Clique em <b>SALVAR CREDENCIAIS</b>.</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold">6</div>
<div>
<p class="font-bold text-gray-800 text-sm">Habilite a conta como marketplace</p>
<p class="text-sm text-gray-600">O Split só funciona se a conta principal estiver habilitada como <b>marketplace</b>. Acesse <b>Configurações → Vender → (ou "Integrações")</b> e procure a opção <b>"Operar como marketplace / split de pagamentos"</b>. Em muitos casos a habilitação precisa ser solicitada pelo suporte do Mercado Pago. Abra um ticket em <b>https://www.mercadopago.com.br/ajuda</b> pedindo "habilitação de conta marketplace para Split de Pagamentos" e informe o e-mail da conta. Enquanto a conta não for marketplace, a divisão do valor falha.</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold">7</div>
<div>
<p class="font-bold text-gray-800 text-sm">Peça a autorização (OAuth) do PARCEIRO</p>
<p class="text-sm text-gray-600">Somente o <b>parceiro/sócio</b> precisa autorizar — <b>afiliados não recebem split</b> e não precisam fazer nada. Use a seção <b>"Autorização OAuth do PARCEIRO"</b> desta página: copie a URL e envie para o parceiro. Ele deve estar logado na conta Mercado Pago dele e clicar em <b>Autorizar</b>. O Mercado Pago redireciona para a sua <code>redirect_uri</code> com o parâmetro <code>code</code>, e o sistema troca automaticamente esse código pelo access token do parceiro (POST em <code>https://api.mercadopago.com/oauth/token</code>), salvando o <code>user_id</code> e os tokens no banco — nada precisa ser feito à mão.</p>
<div class="mt-2 bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800">Os tokens OAuth <b>valem 6 meses</b>. Depois desse prazo é preciso refazer a autorização (ou renovar via refresh_token), senão o pagamento volta a seguir sem split.</div>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold">8</div>
<div>
<p class="font-bold text-gray-800 text-sm">Configure o Webhook</p>
<p class="text-sm text-gray-600">No painel da aplicação, menu <b>Webhooks</b>, cadastre a URL do seu servidor (ex.: <code>https://seusite.com.br/webhook.php</code>) e habilite os eventos de <b>payment</b> (payment.created, payment.approved, payment.rejected). Copie essa mesma URL no campo <b>URL do Webhook</b> do formulário acima e salve. No webhook, valide o cabeçalho <code>X-Signature</code> antes de processar qualquer notificação — sem essa validação, qualquer um pode forjar notificações.</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold">9</div>
<div>
<p class="font-bold text-gray-800 text-sm">Entenda como o Split é enviado (já implementado neste sistema)</p>
<p class="text-sm text-gray-600">No modelo oficial <b>Split Payments 1:1 (marketplace)</b> do Mercado Pago, o pagamento é criado na API <code>/v1/payments</code> usando o <b>access token OAuth do vendedor/parceiro</b> no cabeçalho <code>Authorization</code> (ele é o coletor que recebe) e a parte da plataforma vai no campo <b><code>application_fee</code></b>, em reais:</p>
<pre class="mt-2 bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-700 overflow-x-auto">POST https://api.mercadopago.com/v1/payments
Authorization: Bearer TOKEN_OAUTH_DO_PARCEIRO

{
  "transaction_amount": 100.00,
  "description": "Ativação Economic Card",
  "payment_method_id": "pix",
  "payer": { "email": "cliente@email.com" },
  "application_fee": 30.00
}</pre>
<p class="text-sm text-gray-600 mt-2">No exemplo, o cliente paga R$ 100: <b>R$ 70 caem na conta do parceiro</b> e <b>R$ 30 ficam com a empresa</b> (application_fee). A taxa do Mercado Pago é descontada primeiro da parte do parceiro, e depois a application_fee sai do restante. Em caso de reembolso, o valor é devolvido de forma <b>proporcional</b> entre as duas contas.</p>
<div class="mt-2 bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-800"><b>Já está ativo no código:</b> os endpoints de PIX (<code>criar_pix.php</code>) e cartão (<code>processa_pagamento_cartao.php</code>) aplicam o split automaticamente quando os campos abaixo estão preenchidos e o parceiro tem autorização OAuth válida. A porcentagem configurada no formulário é convertida em <code>application_fee</code> sobre o valor do plano. Sem configuração completa, o pagamento segue normal (100% para a empresa).</div>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold">10</div>
<div>
<p class="font-bold text-gray-800 text-sm">Teste no sandbox e depois vá para produção</p>
<p class="text-sm text-gray-600">Marque o campo <b>Modo sandbox (testes)</b> do formulário e use as <b>credenciais de teste</b> (que começam com <code>TEST-</code>) junto com os cartões de teste do Mercado Pago (ex.: <code>5031 4332 1540 6351</code>). Faça um pagamento com a divisão e confira se cada destinatário recebeu a parte certa. Só depois de tudo funcionando: tire a marcação do sandbox, troque para as credenciais de produção e salve novamente.</p>
</div>
</div>
</div>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Exigências para o Split de Pagamento funcionar</h2>
<p class="text-sm text-gray-500 mb-4">Checklist completo para ativar o Split do Mercado Pago nesta aplicação.</p>
<div class="space-y-4">
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[18px]">person</span></div>
<div>
<p class="font-bold text-gray-800 text-sm">1. Conta Mercado Pago configurada como marketplace</p>
<p class="text-sm text-gray-600">A conta principal (sua empresa) deve estar habilitada como <b>marketplace</b>. No painel do Mercado Pago, ative a opção de "marketplace" para que o Split seja liberado para sua aplicação.</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[18px]">apps</span></div>
<div>
<p class="font-bold text-gray-800 text-sm">2. Aplicação criada no Mercado Pago Developers</p>
<p class="text-sm text-gray-600">Crie uma aplicação em <b>https://www.mercadopago.com.br/developers</b>. Copie as credenciais: Access Token, Public Key, Client ID e Client Secret. Cole nas credenciais acima. Cada vendedor precisa autorizar sua conta (OAuth) para receber a parte da split.</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[18px]">group_add</span></div>
<div>
<p class="font-bold text-gray-800 text-sm">3. Parceiro autorizado via OAuth</p>
<p class="text-sm text-gray-600">O <b>parceiro/sócio</b> (único recebedor do split — afiliados não participam) precisa ter uma conta Mercado Pago e autorizar sua aplicação pelo fluxo OAuth, usando a URL da seção "Autorização OAuth do PARCEIRO". Sem essa autorização, o Split não consegue dividir o pagamento.</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[18px]">split</span></div>
<div>
<p class="font-bold text-gray-800 text-sm">4. Pagamento criado com o token do vendedor + application_fee</p>
<p class="text-sm text-gray-600">No modelo Split 1:1, a cobrança é enviada com o <b>access token OAuth do vendedor</b> no cabeçalho <code>Authorization</code> e a comissão da plataforma no campo <b><code>application_fee</code></b> (valor fixo em R$). O restante cai automaticamente na conta do vendedor. Exemplo:</p>
<pre class="mt-2 bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-700 overflow-x-auto">Authorization: Bearer TOKEN_OAUTH_DO_VENDEDOR

{ "transaction_amount": 25.00, "application_fee": 10.00, ... }</pre>
<p class="text-sm text-gray-600 mt-2">Neste sistema isso é automático: informe a porcentagem do parceiro e o valor da empresa é calculado sobre cada plano.</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[18px]">sync</span></div>
<div>
<p class="font-bold text-gray-800 text-sm">5. Webhook configurado para receber notificações</p>
<p class="text-sm text-gray-600">Configure o webhook para os eventos de <b>payment</b> (pagamento criado/aprovado/recusado). Use a URL cadastrada acima e valide o <code>X-Signature</code> para segurança.</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[18px]">verified</span></div>
<div>
<p class="font-bold text-gray-800 text-sm">6. Validação e liberação da conta</p>
<p class="text-sm text-gray-600">A conta da empresa e as contas dos vendedores precisam estar <b>verificadas</b> (documentos, dados bancários e chave Pix cadastrada) para que os valores do Split sejam repassados.</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[18px]">science</span></div>
<div>
<p class="font-bold text-gray-800 text-sm">7. Teste no sandbox antes de ir para produção</p>
<p class="text-sm text-gray-600">Use o modo sandbox com os cartões de teste do Mercado Pago (ex.: 5031 4332 1540 6351). Após validar o Split completo, troque para as credenciais de produção e marque o modo sandbox como desativado.</p>
</div>
</div>
</div>
</div>

<div class="bg-[#51036d] text-white rounded-xl shadow-sm p-6">
<div class="flex items-start gap-4">
<div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center shrink-0"><span class="material-symbols-outlined">link</span></div>
<div>
<h3 class="font-extrabold mb-1">Documentação oficial</h3>
<p class="text-sm text-white/80">Consulte a documentação oficial do Mercado Pago sobre o modelo usado neste sistema: <a href="https://www.mercadopago.com.br/developers/pt/docs/split-payments/split-1-1/overview" target="_blank" rel="noopener" class="underline text-white hover:text-white/70">Split Payments 1:1 (visão geral)</a> e <a href="https://www.mercadopago.com.br/developers/pt/docs/split-payments/split-1-1/integration-configuration/integrate-marketplace" target="_blank" rel="noopener" class="underline text-white hover:text-white/70">Integrar checkout ao marketplace</a>.</p>
</div>
</div>
</div>
</div>
</main>
<script>
function copiarOAuth(texto, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto).then(() => {
            const original = btn.textContent;
            btn.textContent = 'Copiado!';
            setTimeout(() => btn.textContent = original, 2000);
        });
    } else {
        const ta = document.createElement('textarea');
        ta.value = texto;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        const original = btn.textContent;
        btn.textContent = 'Copiado!';
        setTimeout(() => btn.textContent = original, 2000);
    }
}
</script>
</body>
</html>
