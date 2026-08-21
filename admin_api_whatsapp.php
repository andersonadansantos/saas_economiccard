<?php
require_once 'config.php';
if (!($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin_login.php');
    exit;
}

function limparNumeroWhatsapp($numero) {
    $n = preg_replace('/\D/', '', $numero ?? '');
    if (strlen($n) < 10 || strlen($n) > 13) { return ''; }
    if (strlen($n) === 13) { return $n; }
    if (strlen($n) === 12) { return '55' . $n; }
    if (strlen($n) === 11) { return '55' . $n; }
    if (strlen($n) === 10) { return '55' . $n; }
    return '';
}

function enviar_whatsapp_evolution($url_base, $instancia, $api_key, $numero, $texto) {
    $url = rtrim($url_base, '/') . '/message/sendText/' . rawurlencode($instancia);
    $payload = json_encode(['number' => $numero, 'text' => $texto], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . $api_key
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err !== '') { return ['ok' => false, 'msg' => 'Erro de conexão: ' . $err]; }
    if ($httpCode >= 200 && $httpCode < 300) { return ['ok' => true, 'msg' => 'Enviado (HTTP ' . $httpCode . ')']; }
    return ['ok' => false, 'msg' => 'HTTP ' . $httpCode . ': ' . substr($res, 0, 300)];
}

function renderizarMensagem($template, $dados) {
    $msg = $template ?? '';
    foreach ($dados as $k => $v) {
        $msg = str_replace('{' . $k . '}', $v, $msg);
    }
    return $msg;
}

$cfg = $conn->query("SELECT * FROM config_whatsapp WHERE id = 1")->fetch_assoc();

// Disparo automático: executa o envio de avisos 1x por dia ao abrir o painel
if (!empty($cfg['ativo']) && !empty($cfg['url_base']) && !empty($cfg['instancia']) && !empty($cfg['api_key']) && !empty($cfg['template'])) {
    $ultimo = $cfg['ultimo_cron'] ?? null;
    if ($ultimo === null || strtotime($ultimo) < strtotime('-20 hours')) {
        $q = $conn->query("SELECT id, nome, whatsapp, cartao_validade FROM usuarios WHERE cartao_ativo = 1 AND cartao_validade IS NOT NULL AND DATEDIFF(cartao_validade, CURDATE()) BETWEEN 1 AND 5 ORDER BY cartao_validade ASC");
        $n = 0;
        while ($u = $q->fetch_assoc()) {
            $numero = limparNumeroWhatsapp($u['whatsapp']);
            if ($numero === '') { continue; }
            $jaEnviado = $conn->query("SELECT id FROM envios_whatsapp WHERE usuario_id = " . (int)$u['id'] . " AND tipo = 'expira_5dias' AND criado_em > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->num_rows;
            if ($jaEnviado > 0) { continue; }
            $diasRestantes = max(1, (int)((strtotime($u['cartao_validade']) - strtotime(date('Y-m-d'))) / 86400));
            $msg = renderizarMensagem($cfg['template'], ['nome' => $u['nome'], 'dias' => $diasRestantes, 'validade' => date('d/m/Y', strtotime($u['cartao_validade']))]);
            $r = enviar_whatsapp_evolution($cfg['url_base'], $cfg['instancia'], $cfg['api_key'], $numero, $msg);
            $statusDb = $r['ok'] ? 'enviado' : 'erro';
            $stmt = $conn->prepare("INSERT INTO envios_whatsapp (usuario_id, tipo, numero, status, resposta) VALUES (?, 'expira_5dias', ?, ?, ?)");
            $stmt->bind_param('isss', $u['id'], $numero, $statusDb, $r['msg']);
            $stmt->execute();
            if ($r['ok']) { $n++; }
            sleep(1);
        }
        $conn->query("UPDATE config_whatsapp SET ultimo_cron = NOW() WHERE id = 1");
        $cfg = $conn->query("SELECT * FROM config_whatsapp WHERE id = 1")->fetch_assoc();
    }
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'salvar_config') {
        $url_base = trim($_POST['url_base'] ?? '');
        $instancia = trim($_POST['instancia'] ?? '');
        $api_key = trim($_POST['api_key'] ?? '');
        $template = $_POST['template'] ?? '';
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE config_whatsapp SET url_base=?, instancia=?, api_key=?, template=?, ativo=? WHERE id=1");
        $stmt->bind_param('ssssi', $url_base, $instancia, $api_key, $template, $ativo);
        $stmt->execute();
        $sucesso = 'Configuração da API WhatsApp salva com sucesso!';
        $cfg = $conn->query("SELECT * FROM config_whatsapp WHERE id = 1")->fetch_assoc();
    } elseif ($acao === 'testar_envio') {
        $numero = limparNumeroWhatsapp($_POST['numero'] ?? '');
        if ($numero === '') {
            $erro = 'Informe um número válido (com DDD).';
        } elseif (empty($cfg['url_base']) || empty($cfg['instancia']) || empty($cfg['api_key'])) {
            $erro = 'Preencha e salve as credenciais da API antes de testar.';
        } else {
            $msg = renderizarMensagem($cfg['template'], [
                'nome' => 'Teste',
                'dias' => 5,
                'validade' => date('d/m/Y', strtotime('+5 days'))
            ]);
            $r = enviar_whatsapp_evolution($cfg['url_base'], $cfg['instancia'], $cfg['api_key'], $numero, $msg);
            if ($r['ok']) {
                $sucesso = 'Mensagem de teste enviada para ' . $numero . '!';
            } else {
                $erro = 'Falha no teste: ' . $r['msg'];
            }
        }
    } elseif ($acao === 'enviar_avisos') {
        if (empty($cfg['url_base']) || empty($cfg['instancia']) || empty($cfg['api_key'])) {
            $erro = 'Preencha e salve as credenciais da API antes de enviar.';
        } elseif (empty($cfg['template'])) {
            $erro = 'Defina o texto da mensagem antes de enviar.';
        } else {
            $q = $conn->query("SELECT id, nome, whatsapp, cartao_validade FROM usuarios WHERE cartao_ativo = 1 AND cartao_validade IS NOT NULL AND DATEDIFF(cartao_validade, CURDATE()) BETWEEN 1 AND 5 ORDER BY cartao_validade ASC");
            $enviados = 0;
            $semNumero = 0;
            $falhas = 0;
            $resultados = [];
            while ($u = $q->fetch_assoc()) {
                $numero = limparNumeroWhatsapp($u['whatsapp']);
                if ($numero === '') { $semNumero++; $resultados[] = ['nome' => $u['nome'], 'status' => 'sem número', 'msg' => '']; continue; }
                $jaEnviado = $conn->query("SELECT id FROM envios_whatsapp WHERE usuario_id = " . (int)$u['id'] . " AND tipo = 'expira_5dias' AND criado_em > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->num_rows;
                if ($jaEnviado > 0) { $resultados[] = ['nome' => $u['nome'], 'status' => 'já enviado', 'msg' => '']; continue; }
                $diasRestantes = max(1, (int)((strtotime($u['cartao_validade']) - strtotime(date('Y-m-d'))) / 86400));
                $msg = renderizarMensagem($cfg['template'], [
                    'nome' => $u['nome'],
                    'dias' => $diasRestantes,
                    'validade' => date('d/m/Y', strtotime($u['cartao_validade']))
                ]);
                $r = enviar_whatsapp_evolution($cfg['url_base'], $cfg['instancia'], $cfg['api_key'], $numero, $msg);
                $stmt = $conn->prepare("INSERT INTO envios_whatsapp (usuario_id, tipo, numero, status, resposta) VALUES (?, 'expira_5dias', ?, ?, ?)");
                $statusDb = $r['ok'] ? 'enviado' : 'erro';
                $stmt->bind_param('isss', $u['id'], $numero, $statusDb, $r['msg']);
                $stmt->execute();
                if ($r['ok']) { $enviados++; $resultados[] = ['nome' => $u['nome'], 'status' => 'enviado', 'msg' => '']; }
                else { $falhas++; $resultados[] = ['nome' => $u['nome'], 'status' => 'erro: ' . $r['msg'], 'msg' => '']; }
            }
            $total = $enviados + $semNumero + $falhas;
            $sucesso = 'Processados ' . $total . ' usuário(s): ' . $enviados . ' enviado(s), ' . $falhas . ' falha(s), ' . $semNumero . ' sem número.';
            $resultadoEnvio = $resultados;
        }
    }
}

$usuariosExpirando = [];
if (!empty($cfg['ativo'])) {
    $q = $conn->query("SELECT id, nome, whatsapp, cartao_validade FROM usuarios WHERE cartao_ativo = 1 AND cartao_validade IS NOT NULL AND DATEDIFF(cartao_validade, CURDATE()) BETWEEN 1 AND 5 ORDER BY cartao_validade ASC");
    while ($u = $q->fetch_assoc()) {
        $u['numero_formatado'] = limparNumeroWhatsapp($u['whatsapp']);
        $u['dias_restantes'] = max(1, (int)((strtotime($u['cartao_validade']) - strtotime(date('Y-m-d'))) / 86400));
        $usuariosExpirando[] = $u;
    }
}

$ultimosEnvios = $conn->query("SELECT e.*, u.nome FROM envios_whatsapp e LEFT JOIN usuarios u ON u.id = e.usuario_id ORDER BY e.id DESC LIMIT 20");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API WhatsApp - Admin Economic Card</title>
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
<h1 class="text-xl font-extrabold text-gray-800">API WhatsApp</h1>
<p class="text-sm text-gray-500">Evolution API - aviso de expiração do cartão</p>
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
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Credenciais Evolution API</h2>
<p class="text-sm text-gray-500 mb-4">Preencha os dados da sua instância do Evolution API. A mensagem é enviada automaticamente para os usuários com cartão vencendo em até 5 dias.</p>
<form method="POST" action="admin_api_whatsapp.php" class="grid grid-cols-1 md:grid-cols-3 gap-4">
<input type="hidden" name="acao" value="salvar_config"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">URL base da API</label>
<input type="text" name="url_base" value="<?php echo htmlspecialchars($cfg['url_base'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="https://servidor.evolution.com.br">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nome da instância</label>
<input type="text" name="instancia" value="<?php echo htmlspecialchars($cfg['instancia'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="economiccard">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">API Key (token da instância)</label>
<input type="text" name="api_key" value="<?php echo htmlspecialchars($cfg['api_key'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="cole o token aqui">
</div>
<div class="md:col-span-3">
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Texto da mensagem (placeholders: {nome}, {dias}, {validade})</label>
<textarea name="template" rows="4" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Olá, {nome}! Seu cartão Economic Card vence em {dias} dias ({validade}). Renove agora..."><?php echo htmlspecialchars($cfg['template'] ?? ''); ?></textarea>
</div>
<div class="md:col-span-3 flex items-center gap-4">
<label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
<input type="checkbox" name="ativo" class="w-4 h-4 accent-[#3e6a00]" <?php echo !empty($cfg['ativo']) ? 'checked' : ''; ?>> Habilitar envio automático de avisos
</label>
<button type="submit" class="ml-auto bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition">SALVAR CONFIGURAÇÃO</button>
</div>
</form>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Conexão com o WhatsApp</h2>
<p class="text-sm text-gray-500 mb-4">Escaneie o QR Code com o WhatsApp (WhatsApp &gt; Dispositivos vinculados &gt; Vincular dispositivo). O QR expira em ~60s e é renovado automaticamente.</p>
<div class="flex flex-col md:flex-row items-start gap-6">
<div class="w-64 h-64 bg-gray-50 border-2 border-dashed border-gray-300 rounded-xl flex items-center justify-center" id="qrBoxWhats">
<span class="material-symbols-outlined text-[64px] text-gray-300" id="qrIconWhats">qr_code_2</span>
<img id="qrImgWhats" class="w-full h-full object-contain hidden" alt="QR Code WhatsApp"/>
</div>
<div class="flex-1">
<div id="wppStatusBox" class="mb-4">
<span id="wppStatusBadge" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-bold bg-gray-100 text-gray-600">
<span class="w-2.5 h-2.5 rounded-full bg-gray-400" id="wppStatusDot"></span> <span id="wppStatusText">Verificando conexão...</span>
</span>
</div>
<div class="flex items-center gap-3">
<button type="button" id="btnGerarQr" onclick="gerarQRWhats()" class="bg-[#25D366] hover:bg-[#1eb857] text-white font-bold px-6 py-3 rounded-lg transition flex items-center gap-2">
<span class="material-symbols-outlined text-[18px]">qr_code_scanner</span> GERAR QR CODE
</button>
<button type="button" id="btnDesconectar" onclick="desconectarWhats()" class="hidden bg-red-600 hover:bg-red-700 text-white font-bold px-6 py-3 rounded-lg transition flex items-center gap-2">
<span class="material-symbols-outlined text-[18px]">link_off</span> Desconectar
</button>
</div>
<p class="text-xs text-gray-500 mt-4">Se a instância ainda não existe, o botão acima também tenta criá-la automaticamente. Verifique se o URL base e a API key estão corretos e salve a configuração antes.</p>
</div>
</div>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Enviar avisos de expiração</h2>
<p class="text-sm text-gray-500 mb-4">Envia a mensagem para todos os usuários com cartão ativo vencendo em até 5 dias. Cada usuário só recebe uma vez a cada 24 horas.</p>
<div class="flex items-center gap-3">
<form method="POST" action="admin_api_whatsapp.php" class="flex items-center gap-3">
<input type="hidden" name="acao" value="enviar_avisos"/>
<button type="submit" class="bg-[#25D366] hover:bg-[#1eb857] text-white font-bold px-6 py-3 rounded-lg transition flex items-center gap-2">
<span class="material-symbols-outlined text-[18px]">send</span> ENVIAR AVISOS AGORA
</button>
</form>
<span class="text-sm text-gray-500"><?php echo count($usuariosExpirando); ?> usuário(s) encontrado(s) com cartão vencendo em até 5 dias.</span>
</div>
<?php if (!empty($resultadoEnvio)): ?>
<div class="mt-4 border border-gray-200 rounded-lg overflow-hidden">
<table class="w-full text-sm">
<thead class="bg-gray-50 text-left text-gray-600">
<tr><th class="px-4 py-2 font-bold">Usuário</th><th class="px-4 py-2 font-bold">Status</th></tr>
</thead>
<tbody>
<?php foreach ($resultadoEnvio as $res): ?>
<tr class="border-t border-gray-100">
<td class="px-4 py-2"><?php echo htmlspecialchars($res['nome']); ?></td>
<td class="px-4 py-2"><?php echo htmlspecialchars($res['status']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Testar envio</h2>
<p class="text-sm text-gray-500 mb-4">Envia a mensagem de teste para um número (com DDD, ex.: 91999999999).</p>
<form method="POST" action="admin_api_whatsapp.php" class="flex items-center gap-3">
<input type="hidden" name="acao" value="testar_envio"/>
<input type="text" name="numero" placeholder="91999999999" class="w-64 border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" required/>
<button type="submit" class="bg-[#51036d] hover:bg-[#3a024d] text-white text-sm font-bold px-6 py-3 rounded-lg transition">TESTAR ENVIO</button>
</form>
</div>

<?php if (count($usuariosExpirando) > 0): ?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Usuários com cartão vencendo em até 5 dias</h2>
<p class="text-sm text-gray-500 mb-4">Lista dos usuários que receberão o aviso.</p>
<div class="border border-gray-200 rounded-lg overflow-hidden">
<table class="w-full text-sm">
<thead class="bg-gray-50 text-left text-gray-600">
<tr><th class="px-4 py-2 font-bold">Nome</th><th class="px-4 py-2 font-bold">WhatsApp</th><th class="px-4 py-2 font-bold">Validade</th><th class="px-4 py-2 font-bold">Dias restantes</th></tr>
</thead>
<tbody>
<?php foreach ($usuariosExpirando as $u): ?>
<tr class="border-t border-gray-100">
<td class="px-4 py-2"><?php echo htmlspecialchars($u['nome']); ?></td>
<td class="px-4 py-2"><?php echo htmlspecialchars($u['whatsapp']); ?> <span class="text-xs text-gray-400">(<?php echo htmlspecialchars($u['numero_formatado']); ?>)</span></td>
<td class="px-4 py-2"><?php echo date('d/m/Y', strtotime($u['cartao_validade'])); ?></td>
<td class="px-4 py-2"><span class="px-2 py-1 rounded-full bg-yellow-100 text-yellow-800 text-xs font-bold"><?php echo (int)$u['dias_restantes']; ?> dia(s)</span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Últimos envios</h2>
<p class="text-sm text-gray-500 mb-4">Histórico das mensagens enviadas pela API WhatsApp.</p>
<div class="border border-gray-200 rounded-lg overflow-hidden">
<table class="w-full text-sm">
<thead class="bg-gray-50 text-left text-gray-600">
<tr><th class="px-4 py-2 font-bold">Data</th><th class="px-4 py-2 font-bold">Usuário</th><th class="px-4 py-2 font-bold">Número</th><th class="px-4 py-2 font-bold">Tipo</th><th class="px-4 py-2 font-bold">Status</th><th class="px-4 py-2 font-bold">Resposta</th></tr>
</thead>
<tbody>
<?php if ($ultimosEnvios->num_rows === 0): ?>
<tr class="border-t border-gray-100"><td colspan="6" class="px-4 py-2 text-gray-400">Nenhum envio registrado ainda.</td></tr>
<?php endif; ?>
<?php while ($e = $ultimosEnvios->fetch_assoc()): ?>
<tr class="border-t border-gray-100">
<td class="px-4 py-2 whitespace-nowrap"><?php echo date('d/m/Y H:i', strtotime($e['criado_em'])); ?></td>
<td class="px-4 py-2"><?php echo htmlspecialchars($e['nome'] ?? ('#' . (int)$e['usuario_id'])); ?></td>
<td class="px-4 py-2"><?php echo htmlspecialchars($e['numero']); ?></td>
<td class="px-4 py-2"><?php echo htmlspecialchars($e['tipo']); ?></td>
<td class="px-4 py-2"><span class="px-2 py-1 rounded-full text-xs font-bold <?php echo $e['status'] === 'enviado' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>"><?php echo htmlspecialchars($e['status']); ?></span></td>
<td class="px-4 py-2 text-xs text-gray-500 break-all"><?php echo htmlspecialchars(substr($e['resposta'] ?? '', 0, 100)); ?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Cron automático (cron-job.org)</h2>
<p class="text-sm text-gray-500 mb-4">Cadastre a URL abaixo no <b>cron-job.org</b> para enviar o aviso automaticamente todos os dias. O script verifica quem tem cartão vencendo em 1 a 5 dias e envia a mensagem.</p>
<div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-3">
<p class="text-xs font-bold text-gray-600 uppercase mb-1">URL do cron (copie e cole no cron-job.org)</p>
<code id="cronUrl" class="text-sm text-[#51036d] break-all font-mono"><?php
$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$hostAtual = $_SERVER['HTTP_HOST'] ?? 'localhost';
echo htmlspecialchars($esquema . '://' . $hostAtual . '/card/wpp_aviso_cron.php?token=' . ($cfg['cron_token'] ?? ''));
?></code>
</div>
<ol class="list-decimal list-inside text-sm text-gray-600 space-y-1 mb-3">
<li class="pl-1">Acesse <b>cron-job.org</b>, crie uma conta e clique em <b>"Create cron job"</b>.</li>
<li class="pl-1">Cole a URL acima no campo <b>URL</b>.</li>
<li class="pl-1">Em <b>Schedule</b> escolha <b>"Every day"</b> (ex.: 09:00) ou defina intervalo personalizado.</li>
<li class="pl-1">Salve. Pronto, o envio roda sozinho todos os dias.</li>
</ol>
<div class="flex items-center gap-3">
<button type="button" onclick="navigator.clipboard.writeText(document.getElementById('cronUrl').textContent.trim())" class="bg-[#51036d] hover:bg-[#3a024d] text-white text-sm font-bold px-6 py-3 rounded-lg transition">COPIAR URL DO CRON</button>
<a href="<?php echo htmlspecialchars($esquema . '://' . $hostAtual . '/card/wpp_aviso_cron.php?token=' . ($cfg['cron_token'] ?? '')); ?>" target="_blank" class="bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-bold px-6 py-3 rounded-lg transition">TESTAR AGORA</a>
</div>
<p class="text-xs text-gray-500 mt-3">O link acima exige o site publicado na internet (cron-job.org não acessa localhost). Enquanto estiver só na sua máquina, o teste via link abre no navegador normalmente.</p>
</div>

<div class="bg-[#51036d] text-white rounded-xl shadow-sm p-6">
<div class="flex items-start gap-4">
<div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center shrink-0"><span class="material-symbols-outlined">quiz</span></div>
<div>
<h3 class="font-extrabold mb-1">Como funciona o envio automático</h3>
<p class="text-sm text-white/80">O botão <b>ENVIAR AVISOS AGORA</b> verifica os usuários com cartão ativo vencendo em 1 a 5 dias e envia a mensagem via Evolution API (endpoint <code>/message/sendText/{instancia}</code>). Para automatizar diariamente, agende no cron o script <code>wpp_aviso_cron.php</code> deste projeto.</p>
</div>
</div>
</div>
</div>
<div id="modalConfirmacao" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-black/50" onclick="fecharModalConfirmacao()"></div>
<div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
<div class="bg-[#51036d] px-6 py-5 flex items-center justify-between">
<h2 class="text-white font-bold">Confirmar ação</h2>
<button onclick="fecharModalConfirmacao()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<div class="p-6">
<p id="modalConfirmacaoMsg" class="text-sm text-gray-600 mb-6">Tem certeza?</p>
<div class="flex gap-3">
<button onclick="fecharModalConfirmacao()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-4 py-2.5 rounded-lg transition">Cancelar</button>
<button id="btnConfirmarModal" onclick="confirmarModal()" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold px-4 py-2.5 rounded-lg transition">Confirmar</button>
</div>
</div>
</div>
</div>
</main>
<script>
let formPendente = null;
function confirmarExclusao(ev, form, msg) {
    ev.preventDefault();
    formPendente = form;
    document.getElementById('modalConfirmacaoMsg').textContent = msg;
    const m = document.getElementById('modalConfirmacao');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function confirmarModal() {
    if (window.confirmarDesconectar) { const f = window.confirmarDesconectar; window.confirmarDesconectar = null; f(); return; }
    if (formPendente) { formPendente.submit(); fecharModalConfirmacao(); }
}
function fecharModalConfirmacao() {
    window.confirmarDesconectar = null;
    formPendente = null;
    const m = document.getElementById('modalConfirmacao');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
let wppPolling = null;
const qrBoxWhats = document.getElementById('qrBoxWhats');
const qrImgWhats = document.getElementById('qrImgWhats');
const qrIconWhats = document.getElementById('qrIconWhats');
const wppStatusDot = document.getElementById('wppStatusDot');
const wppStatusText = document.getElementById('wppStatusText');
const btnGerarQr = document.getElementById('btnGerarQr');
const btnDesconectar = document.getElementById('btnDesconectar');

let qrVisivel = false;

function setWppStatus(texto, cor, estado) {
    wppStatusText.textContent = texto;
    wppStatusDot.className = 'w-2.5 h-2.5 rounded-full ' + cor;
    if (estado === 'open') {
        btnGerarQr.classList.add('hidden');
        btnDesconectar.classList.remove('hidden');
    } else {
        btnGerarQr.classList.remove('hidden');
        btnDesconectar.classList.add('hidden');
    }
}

function mostrarQr(base64) {
    qrImgWhats.src = base64;
    qrImgWhats.classList.remove('hidden');
    qrIconWhats.classList.add('hidden');
    qrVisivel = true;
}

function limparQr() {
    qrImgWhats.classList.add('hidden');
    qrIconWhats.classList.remove('hidden');
    qrVisivel = false;
}

async function verificarStatusWhats() {
    try {
        const r = await fetch('wpp_status.php?action=status&_=' + Date.now());
        const d = await r.json();
        if (d.ok && d.state === 'open') {
            setWppStatus('Conectado (' + d.instance + ')', 'bg-green-500', 'open');
            limparQr();
            return;
        }
        if (d.ok && d.state === 'connecting') {
            setWppStatus('Conectando... escaneie o QR abaixo', 'bg-yellow-500', 'connecting');
            return;
        }
        if (qrVisivel) {
            setWppStatus('Aguardando escaneamento do QR Code...', 'bg-yellow-500', 'connecting');
            return;
        }
        setWppStatus('Desconectado', 'bg-gray-400', 'close');
        limparQr();
    } catch (e) {
        setWppStatus('Erro ao verificar conexão', 'bg-red-500', 'close');
    }
}

async function gerarQRWhats() {
    btnGerarQr.disabled = true;
    btnGerarQr.innerHTML = '<span class="material-symbols-outlined text-[18px]">hourglass_top</span> GERANDO...';
    try {
        const r = await fetch('wpp_status.php?action=qrcode&_=' + Date.now());
        const d = await r.json();
        if (d.ok && d.base64) {
            mostrarQr(d.base64);
            setWppStatus('Escaneie o QR Code com seu WhatsApp', 'bg-yellow-500', 'connecting');
            if (wppPolling) clearInterval(wppPolling);
            wppPolling = setInterval(verificarStatusWhats, 5000);
            verificarStatusWhats();
        } else if (d.ok && d.state === 'open') {
            setWppStatus('Conectado', 'bg-green-500', 'open');
        } else {
            setWppStatus(d.msg || 'Falha ao gerar QR Code', 'bg-red-500', 'close');
            limparQr();
        }
    } catch (e) {
        setWppStatus('Erro ao gerar QR Code', 'bg-red-500', 'close');
    } finally {
        btnGerarQr.disabled = false;
        btnGerarQr.innerHTML = '<span class="material-symbols-outlined text-[18px]">qr_code_scanner</span> GERAR QR CODE';
    }
}

async function desconectarWhats() {
    document.getElementById('modalConfirmacaoMsg').textContent = 'Desconectar o WhatsApp desta instância?';
    const btnOk = document.querySelector('#modalConfirmacao button.bg-red-600');
    if (btnOk) { btnOk.classList.remove('bg-[#3e6a00]', 'hover:bg-[#2e5000]'); btnOk.classList.add('bg-red-600', 'hover:bg-red-700'); }
    window.confirmarDesconectar = async () => {
        fecharModalConfirmacao();
        try {
            await fetch('wpp_status.php?action=desconectar&_=' + Date.now());
            setWppStatus('Desconectado', 'bg-gray-400', 'close');
            limparQr();
        } catch (e) {}
    };
    const m = document.getElementById('modalConfirmacao');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

verificarStatusWhats();
if (wppPolling) clearInterval(wppPolling);
wppPolling = setInterval(verificarStatusWhats, 10000);
</script>
</body>
</html>
