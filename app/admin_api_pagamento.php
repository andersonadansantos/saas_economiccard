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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chaveAsaas = trim($_POST['asaas_api_key'] ?? '');
    $ambienteAsaas = (($_POST['asaas_ambiente'] ?? 'producao') === 'sandbox') ? 'sandbox' : 'producao';
    $walletParceiro = trim($_POST['asaas_wallet_parceiro'] ?? '');
    $valorFixoParceiro = round((float)str_replace(',', '.', $_POST['valor_fixo_parceiro'] ?? '0'), 2);
    if ($valorFixoParceiro < 0) { $valorFixoParceiro = 0; }
    // A tela gerencia exclusivamente as credenciais Asaas.
    $stmt = $conn->prepare("UPDATE api_pagamento SET asaas_api_key=?, asaas_ambiente=?, asaas_wallet_parceiro=?, valor_fixo_parceiro=? WHERE id=1");
    $stmt->bind_param('sssd', $chaveAsaas, $ambienteAsaas, $walletParceiro, $valorFixoParceiro);
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

$cfg = $conn->query("SELECT * FROM api_pagamento WHERE id = 1")->fetch_assoc();
$asaasAtivo = trim((string)($cfg['asaas_api_key'] ?? '')) !== '';
$walletPreenchida = trim((string)($cfg['asaas_wallet_parceiro'] ?? '')) !== '';
$vfParceiro = (float)($cfg['valor_fixo_parceiro'] ?? 0);
$splitAtivoAsaas = $asaasAtivo && $walletPreenchida && $vfParceiro > 0;
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
<p class="text-sm text-gray-500">Split de pagamento Asaas</p>
</div>
<a href="logout.php?admin=1" class="bg-[#51036d] hover:bg-[#3a024d] text-white rounded-lg px-4 py-2 text-sm font-semibold transition">Sair</a>
</div>
</header>
<div class="p-6">
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
<p class="text-sm text-gray-500 mb-4">O PIX de ativação usa o <b>Split de Pagamentos nativo do Asaas</b>: a cobrança é emitida pela conta da EMPRESA e a parte do parceiro cai automaticamente na conta dele.</p>
<div class="flex flex-wrap gap-2 text-xs font-bold">
<span class="px-3 py-1.5 rounded-full <?php echo $asaasAtivo ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-yellow-100 text-yellow-800 border border-yellow-200'; ?>">
<?php echo $asaasAtivo ? 'Chave Asaas configurada (' . ($cfg['asaas_ambiente'] === 'sandbox' ? 'SANDBOX' : 'PRODUÇÃO') . ')' : 'Chave Asaas NÃO configurada'; ?>
</span>
<span class="px-3 py-1.5 rounded-full <?php echo $walletPreenchida ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-yellow-100 text-yellow-800 border border-yellow-200'; ?>">
<?php echo $walletPreenchida ? 'Wallet do parceiro cadastrada' : 'Wallet do parceiro pendente'; ?>
</span>
<span class="px-3 py-1.5 rounded-full <?php echo $splitAtivoAsaas ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-gray-100 text-gray-600 border border-gray-200'; ?>">
<?php echo $splitAtivoAsaas ? 'SPLIT ATIVO: parceiro recebe R$ ' . number_format($vfParceiro, 2, ',', '.') . ' por pagamento' : 'Sem split (100% empresa)'; ?>
</span>
</div>
<?php if (!$asaasAtivo): ?>
<div class="mt-4 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg p-3 text-sm"><b>Atenção:</b> sem a chave Asaas, nenhum pagamento (PIX ou cartão) pode ser processado. Configure a chave para habilitar as cobranças.</div>
<?php endif; ?>
</div>

<!-- Formulário Asaas -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Credenciais Asaas</h2>
<p class="text-sm text-gray-500 mb-4">Ao salvar, o sistema testa automaticamente a conexão com a API (consulta de saldo da conta).</p>
<form method="POST" action="admin_api_pagamento.php" class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Valor fixo do parceiro por pagamento (R$)</label>
<input type="number" name="valor_fixo_parceiro" min="0" step="0.01" value="<?php echo htmlspecialchars(number_format($vfParceiro, 2, '.', '')); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Ex.: 5.00">
<p class="text-xs text-gray-500 mt-1">Valor fixo em reais que o parceiro recebe de cada pagamento. O restante fica com a empresa. Com 0, não há split.</p>
</div>
<div class="md:col-span-2">
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Wallet ID do parceiro (recebedor do split)</label>
<input type="text" name="asaas_wallet_parceiro" value="<?php echo htmlspecialchars((string)($cfg['asaas_wallet_parceiro'] ?? '')); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="UUID da carteira Asaas do parceiro">
<p class="text-xs text-gray-500 mt-1">Identificador único da carteira Asaas do PARCEIRO. Veja como obter no passo 3 abaixo. Afiliados não participam do split.</p>
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
<p class="text-sm text-gray-600">A cobrança PIX é criada via <code>POST /v3/payments</code> com <code>billingType: "PIX"</code> e o array <b><code>split</code></b> apontando a carteira do parceiro:</p>
<pre class="mt-2 bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-700 overflow-x-auto">{
  "customer": "cus_000005162240",
  "billingType": "PIX",
  "value": 25.00,
  "dueDate": "2026-08-23",
  "split": [ { "walletId": "WALLET_ID_DO_PARCEIRO", "value": 5.00 } ]
}</pre>
<p class="text-sm text-gray-600 mt-2">Exemplo: cliente paga R$ 25 com valor fixo de R$ 5 para o parceiro → <b>R$ 5 caem na conta do parceiro</b> e <b>R$ 20 ficam na conta da empresa</b>. Em caso de estorno, o valor é devolvido proporcionalmente das duas contas. A confirmação chega pelo webhook (PAYMENT_RECEIVED/CONFIRMED) e o app também consulta o status diretamente.</p>
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
</main>
<script>
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
