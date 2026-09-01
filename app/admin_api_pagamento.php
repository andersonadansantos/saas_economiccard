<?php
require_once 'config.php';
if (!($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin_login.php');
    exit;
}

// Senha da área protegida — definida em secrets_local.php (fora do git) ou via env ADMIN_API_SENHA.
// Padrao: 142536 (usado em producao quando nao ha env nem constantes locais).
if (!defined('ADMIN_API_SENHA')) {
    define('ADMIN_API_SENHA', getenv('ADMIN_API_SENHA') ?: '142536');
}
$senhaApi = ADMIN_API_SENHA;
$erro = '';
$desbloqueado = !empty($_SESSION['api_pagamento_desbloqueado']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['desbloquear'])) {
    $senhaDigitada = preg_replace('/\D/', '', trim($_POST['senha'] ?? ''));
    if ($senhaApi !== '' && $senhaDigitada === $senhaApi) {
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

require_once 'asaas_pix.php';

$sucesso = '';
$teste = null;
$erroSplit = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'salvar_split') {
        // Regra de distribuição (aba Split): valores em R$ por tipo de cobrança.
        $walletPrc = trim($_POST['split_wallet_parceiro'] ?? '');
        $adesaoPrc = round((float)str_replace(',', '.', $_POST['split_parceiro_adesao'] ?? '0'), 2);
        $recorPrc = round((float)str_replace(',', '.', $_POST['split_parceiro_recorrencia'] ?? '0'), 2);
        $afiliadoHabilitado = !empty($_POST['split_afiliado_habilitar']);
        if ($afiliadoHabilitado) {
            $adesaoAfi = round((float)str_replace(',', '.', $_POST['split_afiliado_adesao'] ?? '0'), 2);
            $afiliado1a = round((float)str_replace(',', '.', $_POST['split_afiliado_1a_mensal'] ?? '0'), 2);
        } else {
            $adesaoAfi = 0;
            $afiliado1a = 0;
        }
        if ($adesaoPrc < 0) $adesaoPrc = 0;
        if ($recorPrc < 0) $recorPrc = 0;
        if ($adesaoAfi < 0) $adesaoAfi = 0;
        if ($afiliado1a < 0) $afiliado1a = 0;
        $stmt = $conn->prepare("UPDATE api_pagamento SET asaas_wallet_parceiro=?, split_parceiro_adesao=?, split_parceiro_recorrencia=?, split_afiliado_adesao=?, split_afiliado_1a_mensal=? WHERE id=1");
        $stmt->bind_param('sdddd', $walletPrc, $adesaoPrc, $recorPrc, $adesaoAfi, $afiliado1a);
        $stmt->execute();
        $sucesso = 'Regra de split (aba Split) salva com sucesso!';
    } else {
        $chaveAsaas = trim($_POST['asaas_api_key'] ?? '');
        $ambienteAsaas = (($_POST['asaas_ambiente'] ?? 'producao') === 'sandbox') ? 'sandbox' : 'producao';
        // A tela gerencia exclusivamente as credenciais Asaas.
        $stmt = $conn->prepare("UPDATE api_pagamento SET asaas_api_key=?, asaas_ambiente=? WHERE id=1");
        $stmt->bind_param('ss', $chaveAsaas, $ambienteAsaas);
        $stmt->execute();
        $sucesso = 'Configuração salva com sucesso!';
        if ($chaveAsaas !== '') {
            $cfgNova = [
                'asaas_api_key' => $chaveAsaas,
                'asaas_ambiente' => $ambienteAsaas
            ];
            $teste = asaas_testar_conexao($cfgNova);
        } else {
            $teste = ['ok' => false, 'message' => 'Chave da API Asaas vazia — os pagamentos (PIX e cartão) ficarão indisponíveis até a configuração.'];
        }
    }
}

$cfg = $conn->query("SELECT * FROM api_pagamento WHERE id = 1")->fetch_assoc();
$asaasAtivo = trim((string)($cfg['asaas_api_key'] ?? '')) !== '';
$walletPreenchida = trim((string)($cfg['asaas_wallet_parceiro'] ?? '')) !== '';
$splitAdesaoPrc = (float)($cfg['split_parceiro_adesao'] ?? 0);
$splitRecorPrc = (float)($cfg['split_parceiro_recorrencia'] ?? 0);
$splitAdesaoAfi = (float)($cfg['split_afiliado_adesao'] ?? 0);
$splitAfiliado1a = (float)($cfg['split_afiliado_1a_mensal'] ?? 0);
$algumSplitPrc = $splitAdesaoPrc > 0 || $splitRecorPrc > 0;
$algumSplitAfi = $splitAdesaoAfi > 0 || $splitAfiliado1a > 0;
$splitAtivoAsaas = $asaasAtivo && $walletPreenchida && $algumSplitPrc;
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
<p class="text-sm text-gray-500">Integração Asaas + regra de split por tipo de cobrança</p>
</div>
<a href="logout.php?admin=1" class="bg-[#51036d] hover:bg-[#3a024d] text-white rounded-lg px-4 py-2 text-sm font-semibold transition">Sair</a>
</div>
<div class="px-6 pb-4 flex gap-2">
<button type="button" data-aba="credenciais" onclick="trocarAba('credenciais', this)" class="tab-btn px-4 py-2 text-sm font-bold rounded-lg transition bg-[#51036d] text-white">Credenciais Asaas</button>
<button type="button" data-aba="split" onclick="trocarAba('split', this)" class="tab-btn px-4 py-2 text-sm font-bold rounded-lg transition bg-gray-100 text-gray-600 hover:bg-gray-200">Split de Pagamentos</button>
</div>
</header>
<div id="aba-credenciais" class="p-6">
<?php if ($sucesso): ?>
<div class="mb-6 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm"><?php echo htmlspecialchars($sucesso); ?></div>
<?php endif; ?>
<?php if ($teste): ?>
<div class="mb-6 p-3 rounded-lg border text-sm <?php echo $teste['ok'] ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-700'; ?>">
<b><?php echo $teste['ok'] ? 'Teste de conexão aprovado:' : 'Atenção:'; ?></b> <?php echo htmlspecialchars($teste['message']); ?>
</div>
<?php endif; ?>

<!-- Status atual -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Status da integração</h2>
<p class="text-sm text-gray-500 mb-4">O PIX e o cartão usam o <b>Split de Pagamentos nativo do Asaas</b>: a cobrança é emitida pela conta da EMPRESA e as partes de parceiro/afiliado caem automaticamente nas contas deles.</p>
<div class="flex flex-wrap gap-2 text-xs font-bold">
<span class="px-3 py-1.5 rounded-full <?php echo $asaasAtivo ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-yellow-100 text-yellow-800 border border-yellow-200'; ?>">
<?php echo $asaasAtivo ? 'Chave Asaas configurada (' . ($cfg['asaas_ambiente'] === 'sandbox' ? 'SANDBOX' : 'PRODUÇÃO') . ')' : 'Chave Asaas NÃO configurada'; ?>
</span>
<span class="px-3 py-1.5 rounded-full <?php echo $walletPreenchida ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-yellow-100 text-yellow-800 border border-yellow-200'; ?>">
<?php echo $walletPreenchida ? 'Wallet do parceiro cadastrada' : 'Wallet do parceiro pendente'; ?>
</span>
<span class="px-3 py-1.5 rounded-full <?php echo $splitAtivoAsaas ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-gray-100 text-gray-600 border border-gray-200'; ?>">
<?php echo $splitAtivoAsaas ? 'SPLIT ATIVO (regra da aba Split)' : 'Sem split ou split pendente (100% empresa)'; ?>
</span>
</div>
<?php if (!$asaasAtivo): ?>
<div class="mt-4 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg p-3 text-sm"><b>Atenção:</b> sem a chave Asaas, nenhum pagamento (PIX ou cartão) pode ser processado. Configure a chave para habilitar as cobranças.</div>
<?php endif; ?>
</div>

<!-- Credenciais Asaas -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Credenciais Asaas</h2>
<p class="text-sm text-gray-500 mb-4">Ao salvar, o sistema testa automaticamente a conexão com a API (consulta de saldo da conta).</p>
<form method="POST" action="admin_api_pagamento.php" class="grid grid-cols-1 md:grid-cols-2 gap-4">
<input type="hidden" name="acao" value="credenciais"/>
<div class="md:col-span-2">
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Chave de API (conta da empresa)</label>
<input type="password" name="asaas_api_key" value="<?php echo htmlspecialchars((string)($cfg['asaas_api_key'] ?? '')); ?>" autocomplete="off" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="$aact_prod_...">
<p class="text-xs text-gray-500 mt-1">Gerada em <b>asaas.com → Configurações → Integrações → Chave de API</b>. É ela que emite as cobranças na conta da empresa.</p>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Ambiente</label>
<select name="asaas_ambiente" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<option value="producao" <?php echo (($cfg['asaas_ambiente'] ?? 'producao') === 'producao') ? 'selected' : ''; ?>>Produção (api.asaas.com)</option>
<option value="sandbox" <?php echo (($cfg['asaas_ambiente'] ?? '') === 'sandbox') ? 'selected' : ''; ?>>Sandbox (api-sandbox.asaas.com)</option>
</select>
<p class="text-xs text-gray-500 mt-1">No sandbox use a chave gerada dentro do ambiente de testes.</p>
</div>
<div class="md:col-span-2 flex items-center gap-3">
<button type="submit" class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition">SALVAR E TESTAR CONEXÃO</button>
<span class="text-xs text-gray-500">O teste consulta o saldo da conta informada na chave.</span>
</div>
</form>
</div>

<!-- Passo a passo Asaas -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Passo a passo: Split de Pagamentos no Asaas</h2>
<p class="text-sm text-gray-500 mb-4">Diferente do modelo antigo, aqui <b>não existe OAuth nem renovação de token a cada 6 meses</b> — basta a chave da empresa e o Wallet ID do parceiro.</p>

<div class="space-y-4">
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold">1</div>
<div>
<p class="font-bold text-gray-800 text-sm">Contas Asaas: empresa e parceiro</p>
<p class="text-sm text-gray-600">Crie a conta da <b>empresa</b> em <b>https://www.asaas.com</b> (tipo Pessoa Jurídica) e complete a validação cadastral. O <b>parceiro/sócio</b> também precisa ter uma conta Asaas própria, validada, com chave Pix e dados bancários cadastrados — é nela que ele vai receber a parte dele automaticamente.</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold">2</div>
<div>
<p class="font-bold text-gray-800 text-sm">Gere a Chave de API da empresa</p>
<p class="text-sm text-gray-600">Na conta da empresa: <b>Configurações → Integrações → Chave de API</b>. Gere a chave de produção (começa com <code>$aact_prod_</code>) e cole no campo <b>Chave de API</b> acima. Para testar antes, gere a chave do <b>sandbox</b> (<code>$aact_hmlg_</code>) e marque o ambiente Sandbox. Nunca compartilhe essa chave.</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold">3</div>
<div>
<p class="font-bold text-gray-800 text-sm">Obtenha o Wallet ID do parceiro</p>
<p class="text-sm text-gray-600">O Wallet ID <b>não aparece na interface web</b> do Asaas — o parceiro obtém pela própria API usando a chave DELE. Peça para ele rodar (ou rode junto com a chave dele):</p>
<pre class="mt-2 bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-700 overflow-x-auto">curl -G "https://api.asaas.com/v3/wallets" \
  -H "access_token: $CHAVE_DE_API_DO_PARCEIRO"</pre>
<p class="text-sm text-gray-600 mt-2">Na resposta, copie o campo <code>id</code> (um UUID) e cole no campo <b>Wallet ID do parceiro</b> acima. Em caso de dúvida, o parceiro pode solicitar o Wallet ID ao suporte do Asaas.</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold">4</div>
<div>
<p class="font-bold text-gray-800 text-sm">Configure o Webhook na conta da empresa</p>
<p class="text-sm text-gray-600">Na conta da empresa: <b>Configurações → Integrações → Webhooks</b>, versão <b>v3</b>. Cadastre a URL <code>https://economiccard.com.br/webhook.php</code> e habilite os eventos de pagamento: <b>PAYMENT_RECEIVED</b>, <b>PAYMENT_CONFIRMED</b>, <b>PAYMENT_REFUNDED</b> e <b>PAYMENT_REFUND_IN_PROGRESS</b>. Se quiser, informe esta mesma chave de API como token de autenticação do webhook — o sistema valida quando o token for enviado.</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold">5</div>
<div>
<p class="font-bold text-gray-800 text-sm">Como o split funciona (já implementado neste sistema)</p>
<p class="text-sm text-gray-600">A cobrança PIX é criada via <code>POST /v3/payments</code> com <code>billingType: "PIX"</code> e o array <b><code>splits</code></b> apontando a carteira do parceiro:</p>
<pre class="mt-2 bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-700 overflow-x-auto">{
  "customer": "cus_000005162240",
  "billingType": "PIX",
  "value": 25.00,
  "dueDate": "2026-08-23",
  "splits": [ { "walletId": "WALLET_ID_DO_PARCEIRO", "fixedValue": 5.00 } ]
}</pre>
<p class="text-sm text-gray-600 mt-2">Exemplo: cliente paga R$ 25 com valor fixo de R$ 5 para o parceiro → <b>R$ 5 caem na conta do parceiro</b> e <b>R$ 20 ficam na conta da empresa</b>. Atenção ao nome do campo: é <b>fixedValue</b> (o campo <i>value</i> é rejeitado pelo Asaas). Em caso de estorno, o valor é devolvido proporcionalmente das duas contas. A confirmação chega pelo webhook (PAYMENT_RECEIVED/CONFIRMED) e o app também consulta o status diretamente.</p>
</div>
</div>
<div class="border border-gray-200 rounded-xl p-4 flex gap-3">
<div class="w-8 h-8 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold">6</div>
<div>
<p class="font-bold text-gray-800 text-sm">Teste e vá para produção</p>
<p class="text-sm text-gray-600">Marque <b>Sandbox</b>, cole uma chave do ambiente de testes e gere um PIX de ativação no app. Confirme o recebimento no sandbox e verifique se o valor apareceu nas duas contas. Depois troque para a chave de produção, desmarque o sandbox e salve novamente.</p>
</div>
</div>
</div>
</div>

<div class="bg-[#51036d] text-white rounded-xl shadow-sm p-6">
<div class="flex items-start gap-4">
<div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center shrink-0"><span class="material-symbols-outlined">link</span></div>
<div>
<h3 class="font-extrabold mb-1">Documentação oficial Asaas</h3>
<p class="text-sm text-white/80">Consulte a documentação oficial sobre o modelo usado neste sistema: <a href="https://docs.asaas.com/docs/split-de-pagamentos" target="_blank" rel="noopener" class="underline text-white hover:text-white/70">Split de Pagamentos</a>, <a href="https://docs.asaas.com/reference/criar-nova-cobranca" target="_blank" rel="noopener" class="underline text-white hover:text-white/70">Criar cobrança (POST /payments)</a> e <a href="https://docs.asaas.com/docs/webhook-para-pagamentos" target="_blank" rel="noopener" class="underline text-white hover:text-white/70">Webhook de pagamentos</a>.</p>
</div>
</div>
</div>
</div>

<div id="aba-split" class="p-6 hidden">
<?php if ($erroSplit): ?>
<div class="mb-6 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo htmlspecialchars($erroSplit); ?></div>
<?php endif; ?>

<!-- Como funciona a distribuição -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Como funciona a distribuição</h2>
<p class="text-sm text-gray-500 mb-4">Defina, em reais (R$), a parte de cada tipo de cobrança. O restante de cada pagamento fica automaticamente na conta da empresa (SaaS).</p>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
<div class="border border-gray-200 rounded-xl p-4">
<div class="w-9 h-9 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center mb-2"><span class="material-symbols-outlined text-xl">add_card</span></div>
<h3 class="font-bold text-sm text-gray-800 mb-1">1º Pagamento (Adesão)</h3>
<p class="text-xs text-gray-500">Parceiro recebe <b class="text-gray-700">R$ <?php echo number_format($splitAdesaoPrc, 2, ',', '.'); ?></b><?php echo $algumSplitAfi ? ' e afiliado recebe <b class="text-gray-700">R$ ' . number_format($splitAdesaoAfi, 2, ',', '.') . '</b>' : ' (afiliado não configurado)'; ?>.</p>
</div>
<div class="border border-gray-200 rounded-xl p-4">
<div class="w-9 h-9 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center mb-2"><span class="material-symbols-outlined text-xl">calendar_month</span></div>
<h3 class="font-bold text-sm text-gray-800 mb-1">2º Pagamento (1ª mensalidade)</h3>
<p class="text-xs text-gray-500">Parceiro recebe <b class="text-gray-700">R$ <?php echo number_format($splitRecorPrc, 2, ',', '.'); ?></b><?php echo $algumSplitAfi ? ' e afiliado recebe <b class="text-gray-700">R$ ' . number_format($splitAfiliado1a, 2, ',', '.') . '</b>' : ' (afiliado não configurado)'; ?>.</p>
</div>
<div class="border border-gray-200 rounded-xl p-4">
<div class="w-9 h-9 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center mb-2"><span class="material-symbols-outlined text-xl">autorenew</span></div>
<h3 class="font-bold text-sm text-gray-800 mb-1">3º+ Pagamentos (Recorrência)</h3>
<p class="text-xs text-gray-500">Só o parceiro recebe <b class="text-gray-700">R$ <?php echo number_format($splitRecorPrc, 2, ',', '.'); ?></b>. Afiliado não recebe mais.</p>
</div>
</div>
<?php if (!$walletPreenchida): ?>
<div class="mt-4 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg p-3 text-sm"><b>Dica:</b> preencha o <b>Wallet ID do parceiro</b> abaixo para ativar o split. Sem ele, todas as cobranças vão 100% para a empresa.</div>
<?php endif; ?>
</div>

<!-- Form: Parceiro e Afiliado -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Regra de split por tipo de cobrança</h2>
<p class="text-sm text-gray-500 mb-4">Valores em R$ que saem da conta da empresa para as contas de parceiro/afiliado a cada pagamento.</p>
<form method="POST" action="admin_api_pagamento.php">
<input type="hidden" name="acao" value="salvar_split"/>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div class="md:col-span-2">
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Wallet ID do parceiro</label>
<input type="text" name="split_wallet_parceiro" value="<?php echo htmlspecialchars((string)($cfg['asaas_wallet_parceiro'] ?? '')); ?>" placeholder="8e3...-uuid" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" required/>
<p class="text-xs text-gray-500 mt-1">UUID da conta Asaas do parceiro (obtido pela API — ver passo a passo na aba Credenciais).</p>
</div>
<div class="md:col-span-2 border-b border-gray-100 pb-3">
<h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Sessão: Parceiro</h3>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Adesão (1º pagamento) — R$</label>
<input type="text" name="split_parceiro_adesao" value="<?php echo number_format($splitAdesaoPrc, 2, ',', '.'); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]"/>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Recorrência (2º+) — R$</label>
<input type="text" name="split_parceiro_recorrencia" value="<?php echo number_format($splitRecorPrc, 2, ',', '.'); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]"/>
</div>
<div class="md:col-span-2 border-b border-gray-100 pb-3 pt-2">
<h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Sessão: Afiliado</h3>
<label class="inline-flex items-center gap-2 mt-1 text-xs text-gray-500 cursor-pointer">
<input type="checkbox" name="split_afiliado_habilitar" value="1" id="split_afiliado_habilitar" <?php echo $algumSplitAfi ? 'checked' : ''; ?> onclick="document.getElementById('bloco-afiliado').classList.toggle('hidden', !this.checked)" class="rounded border-gray-300 text-[#51036d] focus:ring-[#51036d]"/>
Ativar participação de afiliados no split (ela só vale se o afiliado estiver <b>ativo</b> e tiver <b>Wallet ID</b> preenchido no cadastro).
</label>
</div>
<div id="bloco-afiliado" class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4 <?php echo $algumSplitAfi ? '' : 'hidden'; ?>">
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Adesão (1º pagamento) — R$</label>
<input type="text" name="split_afiliado_adesao" value="<?php echo number_format($splitAdesaoAfi, 2, ',', '.'); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]"/>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">1ª mensalidade (2º pagamento) — R$</label>
<input type="text" name="split_afiliado_1a_mensal" value="<?php echo number_format($splitAfiliado1a, 2, ',', '.'); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]"/>
</div>
</div>
<div class="md:col-span-2 flex items-center gap-3">
<button type="submit" class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition">SALVAR REGRA DE SPLIT</button>
<span class="text-xs text-gray-500">Vale para PIX e cartão.</span>
</div>
</div>
</form>
</div>
</div>

</main>
<script>
function trocarAba(nome, btn) {
    document.querySelectorAll('.tab-btn').forEach(function (b) {
        var ativo = b === btn;
        b.classList.toggle('bg-[#51036d]', ativo);
        b.classList.toggle('text-white', ativo);
        b.classList.toggle('bg-gray-100', !ativo);
        b.classList.toggle('text-gray-600', !ativo);
    });
    document.getElementById('aba-credenciais').classList.toggle('hidden', nome !== 'credenciais');
    document.getElementById('aba-split').classList.toggle('hidden', nome !== 'split');
}
function copiarTexto(texto, btn) {
    const finalizar = () => {
        const original = btn.textContent;
        btn.textContent = 'Copiado!';
        setTimeout(() => btn.textContent = original, 2000);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto).then(finalizar);
    } else {
        const ta = document.createElement('textarea');
        ta.value = texto;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        finalizar();
    }
}
</script>
</body>
</html>
