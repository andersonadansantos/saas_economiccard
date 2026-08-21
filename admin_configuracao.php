<?php
require_once 'config.php';
require_once 'email_sender.php';
if (!($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin_login.php');
    exit;
}

$aba = $_GET['aba'] ?? 'smtp';
if (!in_array($aba, ['smtp', 'template', 'mkt', 'atendimento'])) { $aba = 'smtp'; }

$erro = '';
$sucesso = '';
$smtp = getSmtpConfig();
$template = $conn->query("SELECT * FROM template_email WHERE id = 1")->fetch_assoc();
if (!$template) {
    $conn->query("INSERT IGNORE INTO template_email (id) VALUES (1)");
    $template = $conn->query("SELECT * FROM template_email WHERE id = 1")->fetch_assoc();
}
$atendimento = $conn->query("SELECT * FROM config_atendimento WHERE id = 1")->fetch_assoc();
if (!$atendimento) {
    $conn->query("INSERT IGNORE INTO config_atendimento (id) VALUES (1)");
    $atendimento = $conn->query("SELECT * FROM config_atendimento WHERE id = 1")->fetch_assoc();
}

// Conteúdo padrão para o editor visual (apenas a região editável, sem shell)
$templateConteudoPadrao = '<p>Olá, <strong>{nome}</strong>!</p>
<p>Conteúdo da sua mensagem aqui.</p>';

// Conteúdo editável (após extrair do shell completo, se o corpo salvo for HTML completo)
$templateConteudoEditor = extrairConteudoTemplate($template['corpo'] ?? '');
if (trim($templateConteudoEditor) === '') { $templateConteudoEditor = $templateConteudoPadrao; }

// ========== ABAS DE PROCESSAMENTO ==========

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // ---------- SMTP ----------
    if ($acao === 'salvar_smtp') {
        $host    = trim($_POST['host'] ?? '');
        $porta   = (int)($_POST['porta'] ?? 587);
        $usuario = trim($_POST['usuario'] ?? '');
        $senha   = $_POST['senha'] ?? '';
        $remNome = trim($_POST['remetente_nome'] ?? '');
        $remMail = trim($_POST['remetente_email'] ?? '');
        $cripto  = trim($_POST['criptografia'] ?? 'tls');
        if ($senha === '') { $senha = $smtp['senha'] ?? ''; }
        $stmt = $conn->prepare("UPDATE config_smtp SET host=?, porta=?, usuario=?, senha=?, remetente_nome=?, remetente_email=?, criptografia=? WHERE id=1");
        $stmt->bind_param('sisssss', $host, $porta, $usuario, $senha, $remNome, $remMail, $cripto);
        $stmt->execute();
        $smtp = getSmtpConfig();
        $sucesso = 'Configuração SMTP salva com sucesso!';
    }

    if ($acao === 'testar_smtp') {
        $smtp['host']    = trim($_POST['host'] ?? $smtp['host'] ?? '');
        $smtp['porta']   = (int)($_POST['porta'] ?? $smtp['porta'] ?? 587);
        $smtp['usuario'] = trim($_POST['usuario'] ?? $smtp['usuario'] ?? '');
        $smtp['senha']   = $_POST['senha'] !== '' ? $_POST['senha'] : ($smtp['senha'] ?? '');
        $smtp['remetente_nome']  = trim($_POST['remetente_nome'] ?? $smtp['remetente_nome'] ?? '');
        $smtp['remetente_email'] = trim($_POST['remetente_email'] ?? $smtp['remetente_email'] ?? '');
        $smtp['criptografia']    = trim($_POST['criptografia'] ?? $smtp['criptografia'] ?? 'tls');
        $paraTeste = trim($_POST['email_teste'] ?? $smtp['remetente_email'] ?? '');
        if ($paraTeste === '' || !filter_var($paraTeste, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail de destino válido para o teste.';
        } else {
            $r = enviar_email_smtp($paraTeste, 'Teste de configuração SMTP - Economic Card', '<p>Este é um e-mail de teste enviado pelo painel administrativo do Economic Card.</p>', $smtp);
            if ($r['ok']) {
                $sucesso = 'E-mail de teste enviado com sucesso para ' . htmlspecialchars($paraTeste) . '!';
            } else {
                $erro = 'Falha no teste: ' . htmlspecialchars($r['msg']);
            }
        }
    }

    // ---------- TEMPLATE ----------
    if ($acao === 'salvar_template') {
        $nome    = trim($_POST['nome'] ?? '');
        $assunto = trim($_POST['assunto'] ?? '');
        $corpo   = $_POST['corpo'] ?? '';
        $stmt = $conn->prepare("UPDATE template_email SET nome=?, assunto=?, corpo=? WHERE id=1");
        $stmt->bind_param('sss', $nome, $assunto, $corpo);
        $stmt->execute();
        $template = $conn->query("SELECT * FROM template_email WHERE id = 1")->fetch_assoc();
        $sucesso = 'Template de e-mail salvo com sucesso!';
    }

    // ---------- CENTRAL DE ATENDIMENTO ----------
    if ($acao === 'salvar_atendimento') {
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail de atendimento válido (ou deixe em branco).';
        } else {
            $stmt = $conn->prepare("UPDATE config_atendimento SET whatsapp=?, email=? WHERE id=1");
            $stmt->bind_param('ss', $whatsapp, $email);
            $stmt->execute();
            $atendimento = $conn->query("SELECT * FROM config_atendimento WHERE id = 1")->fetch_assoc();
            $sucesso = 'Central de atendimento salva com sucesso!';
        }
    }

    // ---------- E-MAIL MKT ----------
    if ($acao === 'preparar_envio') {
        $assunto = trim($_POST['assunto'] ?? '');
        $corpo   = $_POST['corpo'] ?? '';
        if ($assunto === '' || $corpo === '') {
            $erro = 'Preencha o assunto e o corpo da campanha.';
        } else {
            // Monta a lista de destinatários (usuários com e-mail)
            $destinos = [];
            $r = $conn->query("SELECT id, nome, email FROM usuarios WHERE email IS NOT NULL AND email <> '' ORDER BY id");
            while ($row = $r->fetch_assoc()) { $destinos[] = $row; }

            // Conta envios de hoje
            $hoje = date('Y-m-d');
            $totalHoje = (int)$conn->query("SELECT COUNT(*) AS t FROM envios_email WHERE DATE(criado_em) = '$hoje'")->fetch_assoc()['t'];
            $restante = max(300 - $totalHoje, 0);

            if (count($destinos) === 0) {
                $erro = 'Nenhum usuário cadastrado possui e-mail.';
            } elseif ($restante <= 0) {
                $erro = 'Limite diário de 300 envios já foi atingido hoje. Tente novamente amanhã.';
            } else {
                $_SESSION['mkt_assunto'] = $assunto;
                $_SESSION['mkt_corpo']   = $corpo;
                $_SESSION['mkt_destinos'] = $destinos;
                $_SESSION['mkt_total_restante'] = $restante;
                $mensagemLimite = count($destinos) > $restante
                    ? ' A lista tem ' . count($destinos) . ' destinatários, mas o limite diário permite apenas <b>' . $restante . ' envios hoje</b> — o restante será enviado nos próximos dias ao reenviar esta campanha.'
                    : '';
                $sucesso = 'Campanha preparada! Total de destinatários: <b>' . count($destinos) . '</b>.' . $mensagemLimite;
            }
        }
    }

    if ($acao === 'processar_lote') {
        header('Content-Type: application/json; charset=utf-8');
        $destinos = $_SESSION['mkt_destinos'] ?? [];
        $assunto  = $_SESSION['mkt_assunto'] ?? '';
        $corpo    = $_SESSION['mkt_corpo'] ?? '';
        $restante = $_SESSION['mkt_total_restante'] ?? 0;
        $lote = 25;
        $enviados = 0;
        $erros = 0;
        $finalizado = false;

        set_time_limit(120);
        while ($lote > 0 && count($destinos) > 0 && $restante > 0) {
            $dest = array_shift($destinos);
            $corpoCompleto = templateShell(quillParaInline(extrairConteudoTemplate($corpo)));
            $corpoRender = renderTemplateEmail($corpoCompleto, $assunto, ['nome' => $dest['nome'], 'email' => $dest['email']]);
            $r = enviar_email_smtp($dest['email'], $corpoRender[1], $corpoRender[0]);
            $stmt = $conn->prepare("INSERT INTO envios_email (destinatario, assunto, status) VALUES (?, ?, ?)");
            $status = $r['ok'] ? 'enviado' : 'erro';
            $stmt->bind_param('sss', $dest['email'], $assunto, $status);
            $stmt->execute();
            if ($r['ok']) { $enviados++; } else { $erros++; }
            $restante--;
            $lote--;
        }
        $_SESSION['mkt_destinos'] = $destinos;
        $_SESSION['mkt_total_restante'] = $restante;
        if (count($destinos) === 0 || $restante <= 0) {
            $finalizado = true;
            unset($_SESSION['mkt_destinos'], $_SESSION['mkt_assunto'], $_SESSION['mkt_corpo'], $_SESSION['mkt_total_restante']);
        }
        echo json_encode([
            'ok' => true,
            'enviados_lote' => $enviados,
            'erros_lote' => $erros,
            'fila' => count($destinos),
            'finalizado' => $finalizado
        ]);
        exit;
    }
}

// Contagem de envios de hoje (para a aba MKT)
$hoje = date('Y-m-d');
$totalHoje = (int)$conn->query("SELECT COUNT(*) AS t FROM envios_email WHERE DATE(criado_em) = '$hoje'")->fetch_assoc()['t'];
$totalComEmail = (int)$conn->query("SELECT COUNT(*) AS t FROM usuarios WHERE email IS NOT NULL AND email <> ''")->fetch_assoc()['t'];
$restanteHoje = max(300 - $totalHoje, 0);

$ultimosEnvios = $conn->query("SELECT * FROM envios_email ORDER BY id DESC LIMIT 15");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configuração - Admin Economic Card</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
    body { font-family: 'Manrope', sans-serif; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .editor-wrapper .ql-editor { min-height: 300px; font-family: 'Manrope', sans-serif; font-size: 15px; }
    .editor-wrapper .ql-editor p, .editor-wrapper .ql-editor li { line-height: 1.6; }
</style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php require 'admin_menu.php'; ?>
<main class="md:ml-60 min-h-screen">
<header class="bg-white shadow-sm sticky top-0 z-30">
<div class="px-6 py-4 flex items-center justify-between">
<div>
<h1 class="text-xl font-extrabold text-gray-800">Configuração</h1>
<p class="text-sm text-gray-500">E-mails, SMTP e campanhas de marketing</p>
</div>
<a href="logout.php?admin=1" class="bg-[#51036d] hover:bg-[#3a024d] text-white rounded-lg px-4 py-2 text-sm font-semibold transition">Sair</a>
</div>
</header>
<div class="p-6">

<!-- Tabs -->
<div class="flex gap-2 mb-6 flex-wrap">
<a href="admin_configuracao.php?aba=smtp" class="px-4 py-2.5 rounded-lg font-semibold text-sm transition <?php echo $aba === 'smtp' ? 'bg-[#51036d] text-white' : 'bg-white text-gray-700 hover:bg-gray-200'; ?>">SMTP</a>
<a href="admin_configuracao.php?aba=template" class="px-4 py-2.5 rounded-lg font-semibold text-sm transition <?php echo $aba === 'template' ? 'bg-[#51036d] text-white' : 'bg-white text-gray-700 hover:bg-gray-200'; ?>">Template de E-mail</a>
<a href="admin_configuracao.php?aba=mkt" class="px-4 py-2.5 rounded-lg font-semibold text-sm transition <?php echo $aba === 'mkt' ? 'bg-[#51036d] text-white' : 'bg-white text-gray-700 hover:bg-gray-200'; ?>">E-mail MKT</a>
<a href="admin_configuracao.php?aba=atendimento" class="px-4 py-2.5 rounded-lg font-semibold text-sm transition <?php echo $aba === 'atendimento' ? 'bg-[#51036d] text-white' : 'bg-white text-gray-700 hover:bg-gray-200'; ?>">Central de Atendimento</a>
</div>

<?php if ($sucesso): ?>
<div class="mb-6 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm"><?php echo $sucesso; ?></div>
<?php endif; ?>
<?php if ($erro): ?>
<div class="mb-6 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo $erro; ?></div>
<?php endif; ?>

<!-- ================= ABAS ================= -->
<?php if ($aba === 'smtp'): ?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Servidor SMTP</h2>
<p class="text-sm text-gray-500 mb-4">Configure seu servidor de e-mail (Gmail, Outlook, Hostinger, etc.) para envio de mensagens.</p>
<form method="POST" action="admin_configuracao.php?aba=smtp" class="grid grid-cols-1 md:grid-cols-2 gap-4">
<input type="hidden" name="acao" value="salvar_smtp"/>
<div class="md:col-span-2">
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Servidor SMTP (host) *</label>
<input type="text" name="host" value="<?php echo htmlspecialchars($smtp['host'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="smtp.seusite.com.br">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Porta</label>
<input type="number" name="porta" value="<?php echo htmlspecialchars($smtp['porta'] ?? '587'); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="587">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Criptografia</label>
<select name="criptografia" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<option value="tls" <?php echo ($smtp['criptografia'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS (recomendado - porta 587)</option>
<option value="ssl" <?php echo ($smtp['criptografia'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL (porta 465)</option>
<option value="none" <?php echo ($smtp['criptografia'] ?? '') === 'none' ? 'selected' : ''; ?>>Sem criptografia</option>
</select>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Usuário (e-mail de login)</label>
<input type="text" name="usuario" value="<?php echo htmlspecialchars($smtp['usuario'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="contato@seusite.com.br">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Senha</label>
<input type="password" name="senha" value="" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="<?php echo !empty($smtp['senha']) ? '•••••••• (deixe em branco para manter)' : 'Senha do e-mail'; ?>">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nome do remetente *</label>
<input type="text" name="remetente_nome" value="<?php echo htmlspecialchars($smtp['remetente_nome'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Economic Card">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">E-mail do remetente *</label>
<input type="email" name="remetente_email" value="<?php echo htmlspecialchars($smtp['remetente_email'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="contato@seusite.com.br">
</div>
<div class="md:col-span-2 flex items-center gap-3">
<button type="submit" class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition">SALVAR SMTP</button>
</div>
</form>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Testar envio</h2>
<p class="text-sm text-gray-500 mb-4">Envie um e-mail de teste para validar as configurações acima (use os mesmos campos preenchidos).</p>
<form method="POST" action="admin_configuracao.php?aba=smtp" class="grid grid-cols-1 md:grid-cols-2 gap-4">
<input type="hidden" name="acao" value="testar_smtp"/>
<input type="hidden" name="host" value="<?php echo htmlspecialchars($smtp['host'] ?? ''); ?>"/>
<input type="hidden" name="porta" value="<?php echo htmlspecialchars($smtp['porta'] ?? '587'); ?>"/>
<input type="hidden" name="usuario" value="<?php echo htmlspecialchars($smtp['usuario'] ?? ''); ?>"/>
<input type="hidden" name="senha" value=""/>
<input type="hidden" name="remetente_nome" value="<?php echo htmlspecialchars($smtp['remetente_nome'] ?? ''); ?>"/>
<input type="hidden" name="remetente_email" value="<?php echo htmlspecialchars($smtp['remetente_email'] ?? ''); ?>"/>
<input type="hidden" name="criptografia" value="<?php echo htmlspecialchars($smtp['criptografia'] ?? 'tls'); ?>"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">E-mail de destino</label>
<input type="email" name="email_teste" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="seuemail@exemplo.com" required>
</div>
<div class="flex items-end">
<button type="submit" class="bg-[#51036d] hover:bg-[#3a024d] text-white font-bold px-6 py-2.5 rounded-lg transition">ENVIAR TESTE</button>
</div>
</form>
<p class="text-xs text-gray-500 mt-3">Dica: para Gmail ative "Permitir apps menos seguros" ou crie uma senha de app. Para Hostinger, use a porta 465/SSL ou 587/TLS com as credenciais do seu e-mail profissional.</p>
</div>
<?php endif; ?>

<?php if ($aba === 'template'): ?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Template de E-mail</h2>
<p class="text-sm text-gray-500 mb-4">Edite o conteúdo do e-mail visualmente. O cabeçalho e o rodapé são adicionados automaticamente e os estilos são convertidos para inline (funciona em todos os servidores de e-mail). Placeholders: <code class="bg-gray-100 px-1 rounded">{nome}</code>, <code class="bg-gray-100 px-1 rounded">{email}</code>, <code class="bg-gray-100 px-1 rounded">{ano}</code>, <code class="bg-gray-100 px-1 rounded">{assunto}</code>.</p>
<form method="POST" action="admin_configuracao.php?aba=template" class="grid grid-cols-1 gap-4">
<input type="hidden" name="acao" value="salvar_template"/>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nome do template</label>
<input type="text" name="nome" value="<?php echo htmlspecialchars($template['nome'] ?? 'Padrão'); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Assunto padrão *</label>
<input type="text" name="assunto" value="<?php echo htmlspecialchars($template['assunto'] ?? 'Novidades Economic Card'); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-2">Conteúdo do e-mail (editor visual) *</label>
<div id="editorTemplate" class="editor-wrapper bg-white border border-gray-300 rounded-lg"></div>
<input type="hidden" name="corpo" id="corpoInput">
<p class="text-xs text-gray-500 mt-2">Use os placeholders <code class="bg-gray-100 px-1 rounded">{nome}</code>, <code class="bg-gray-100 px-1 rounded">{email}</code>, <code class="bg-gray-100 px-1 rounded">{ano}</code> e <code class="bg-gray-100 px-1 rounded">{assunto}</code>. O cabeçalho "ECONOMIC CARD" e o rodapé são adicionados automaticamente em todos os e-mails.</p>
</div>
<div class="flex items-center justify-between">
<div class="flex gap-3">
<button type="button" onclick="previewTemplate()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-6 py-3 rounded-lg transition">PRÉ-VISUALIZAR</button>
</div>
<button type="submit" class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition">SALVAR TEMPLATE</button>
</div>
</form>
</div>
<div id="previewBox" class="hidden bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-4">Pré-visualização</h2>
<div class="border border-gray-200 rounded-xl overflow-hidden" id="previewContent"></div>
</div>
<?php endif; ?>

<?php if ($aba === 'mkt'): ?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Enviar campanha (E-mail MKT)</h2>
<p class="text-sm text-gray-500 mb-4">Envie e-mails para todos os usuários cadastrados. <b>Limite: 300 envios por dia.</b> Quando a lista tiver mais de 300 destinatários, o restante deve ser enviado nos próximos dias (reabra esta página e clique novamente em enviar).</p>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
<div class="bg-[#51036d]/5 border border-[#51036d]/20 rounded-xl p-4">
<p class="text-xs font-bold uppercase text-[#51036d] mb-1">Usuários com e-mail</p>
<p class="text-3xl font-extrabold text-[#51036d]"><?php echo $totalComEmail; ?></p>
</div>
<div class="bg-[#3e6a00]/5 border border-[#3e6a00]/20 rounded-xl p-4">
<p class="text-xs font-bold uppercase text-[#3e6a00] mb-1">Enviados hoje</p>
<p class="text-3xl font-extrabold text-[#3e6a00]"><?php echo $totalHoje; ?> / 300</p>
</div>
<div class="bg-gray-100 border border-gray-200 rounded-xl p-4">
<p class="text-xs font-bold uppercase text-gray-500 mb-1">Restantes hoje</p>
<p class="text-3xl font-extrabold text-gray-600"><?php echo $restanteHoje; ?></p>
</div>
</div>
<form method="POST" action="admin_configuracao.php?aba=mkt" class="grid grid-cols-1 gap-4">
<input type="hidden" name="acao" value="preparar_envio"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Assunto *</label>
<input type="text" name="assunto" value="<?php echo htmlspecialchars($template['assunto'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" required>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-2">Conteúdo da campanha (editor visual) *</label>
<div id="editorMkt" class="editor-wrapper bg-white border border-gray-300 rounded-lg"></div>
<input type="hidden" name="corpo" id="corpoInputMkt">
</div>
<div class="flex items-center gap-3">
<button type="submit" class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition">PREPARAR ENVIO</button>
<span class="text-sm text-gray-500">Após preparar, clique em "INICIAR ENVIO" para começar.</span>
</div>
</form>
</div>

<?php if (isset($_SESSION['mkt_destinos'])): $filaMkt = count($_SESSION['mkt_destinos']); ?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Campanha pronta para envio</h2>
<p class="text-sm text-gray-500 mb-4"><b><?php echo $filaMkt; ?></b> e-mail(s) na fila (respeitando o limite diário de 300). O envio acontece em lotes para não travar o servidor.</p>
<div class="mb-4">
<div class="w-full bg-gray-200 rounded-full h-3"><div id="barraProgresso" class="bg-[#3e6a00] h-3 rounded-full transition-all duration-300" style="width:0%"></div></div>
</div>
<p class="text-sm text-gray-600 mb-4" id="statusEnvio">Clique em iniciar para começar.</p>
<button id="btnIniciarEnvio" onclick="iniciarEnvio()" class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition">INICIAR ENVIO</button>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
<h2 class="px-6 py-4 text-lg font-extrabold text-gray-800 border-b border-gray-100">Últimos envios</h2>
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead>
<tr class="bg-gray-50 text-gray-600 text-left uppercase text-xs tracking-wider">
<th class="px-4 py-3">Destinatário</th>
<th class="px-4 py-3">Assunto</th>
<th class="px-4 py-3">Status</th>
<th class="px-4 py-3">Data</th>
</tr>
</thead>
<tbody>
<?php if ($ultimosEnvios->num_rows === 0): ?>
<tr><td colspan="4" class="px-4 py-10 text-center text-gray-500">Nenhum envio realizado.</td></tr>
<?php endif; ?>
<?php while ($e = $ultimosEnvios->fetch_assoc()): ?>
<tr class="border-t border-gray-100 hover:bg-gray-50">
<td class="px-4 py-3"><?php echo htmlspecialchars($e['destinatario']); ?></td>
<td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars(mb_strimwidth($e['assunto'], 0, 50, '...')); ?></td>
<td class="px-4 py-3"><?php echo $e['status'] === 'enviado' ? '<span class="px-2 py-1 rounded-full bg-[#b6f570]/40 text-[#3e6a00] text-[10px] font-bold uppercase">Enviado</span>' : '<span class="px-2 py-1 rounded-full bg-red-50 text-red-600 text-[10px] font-bold uppercase">Erro</span>'; ?></td>
<td class="px-4 py-3 text-gray-500"><?php echo date('d/m/Y H:i', strtotime($e['criado_em'])); ?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>

<?php if ($aba === 'atendimento'): ?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-1">Central de Atendimento</h2>
<p class="text-sm text-gray-500 mb-4">Cadastre os canais de contato (WhatsApp e E-mail) que serão exibidos aos usuários no menu <b>"Central de atendimento"</b> do aplicativo.</p>
<form method="POST" action="admin_configuracao.php?aba=atendimento" class="grid grid-cols-1 md:grid-cols-2 gap-4">
<input type="hidden" name="acao" value="salvar_atendimento"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">WhatsApp</label>
<input type="text" name="whatsapp" value="<?php echo htmlspecialchars($atendimento['whatsapp'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="5511999999999">
<p class="text-xs text-gray-500 mt-1">Somente números, com DDI + DDD. Ex.: <code class="bg-gray-100 px-1 rounded">5511999999999</code>. Deixe em branco para não exibir.</p>
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">E-mail</label>
<input type="email" name="email" value="<?php echo htmlspecialchars($atendimento['email'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="atendimento@economiccard.com.br">
<p class="text-xs text-gray-500 mt-1">E-mail de atendimento. Deixe em branco para não exibir.</p>
</div>
<div class="md:col-span-2 flex items-center gap-3">
<button type="submit" class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition">SALVAR ATENDIMENTO</button>
</div>
</form>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-4">Pré-visualização no menu do usuário</h2>
<div class="bg-gray-100 border border-gray-200 rounded-xl p-6 flex flex-col items-center gap-4">
<div class="w-full max-w-xs bg-white rounded-2xl shadow-lg p-4">
<p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3">Central de atendimento</p>
<div class="space-y-2">
<?php if (!empty($atendimento['whatsapp'])): ?>
<a href="https://wa.me/<?php echo htmlspecialchars(preg_replace('/\D/', '', $atendimento['whatsapp'])); ?>" target="_blank" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-[#25D366]/10 text-[#128C7E] font-semibold text-sm">
<span class="material-symbols-outlined">chat</span> WhatsApp
</a>
<?php else: ?>
<div class="px-4 py-3 rounded-xl bg-gray-50 text-gray-400 font-semibold text-sm"><span class="material-symbols-outlined align-middle mr-2">chat</span> WhatsApp (não configurado)</div>
<?php endif; ?>
<?php if (!empty($atendimento['email'])): ?>
<a href="mailto:<?php echo htmlspecialchars($atendimento['email']); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-[#51036d]/10 text-[#51036d] font-semibold text-sm">
<span class="material-symbols-outlined">mail</span> E-mail
</a>
<?php else: ?>
<div class="px-4 py-3 rounded-xl bg-gray-50 text-gray-400 font-semibold text-sm"><span class="material-symbols-outlined align-middle mr-2">mail</span> E-mail (não configurado)</div>
<?php endif; ?>
</div>
</div>
</div>
</div>
<?php endif; ?>

</div>
</main>
<script src="https://cdn.quilljs.com/1.3.7/quill.js"></script>
<script>
// ====== EDITORES VISUAIS (Quill) ======
const toolbarOpcoes = [
    [{ header: [1, 2, 3, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ color: [] }, { background: [] }],
    [{ list: 'ordered' }, { list: 'bullet' }],
    [{ align: [] }],
    ['link', 'blockquote', 'code-block'],
    ['clean']
];
const conteudoInicialEditor = <?php echo json_encode($templateConteudoEditor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

let editorTemplate = null, editorMkt = null;

document.addEventListener('DOMContentLoaded', function () {
    const elTemplate = document.getElementById('editorTemplate');
    if (elTemplate) {
        editorTemplate = new Quill(elTemplate, { theme: 'snow', modules: { toolbar: toolbarOpcoes }, placeholder: 'Escreva aqui o conteúdo do e-mail...' });
        editorTemplate.root.innerHTML = conteudoInicialEditor;
    }
    const elMkt = document.getElementById('editorMkt');
    if (elMkt) {
        editorMkt = new Quill(elMkt, { theme: 'snow', modules: { toolbar: toolbarOpcoes }, placeholder: 'Escreva aqui o conteúdo da campanha...' });
        editorMkt.root.innerHTML = conteudoInicialEditor;
    }
});

// Sincroniza o conteúdo dos editores para os campos hidden antes do submit
function sincronizarEditores() {
    if (editorTemplate) document.getElementById('corpoInput').value = editorTemplate.root.innerHTML;
    if (editorMkt) document.getElementById('corpoInputMkt').value = editorMkt.root.innerHTML;
}
document.querySelectorAll('form').forEach(f => f.addEventListener('submit', sincronizarEditores));

// Shell de e-mail para a pré-visualização (espelha templateShell do PHP)
const shellPreview = '<div style="background-color:#f4f5f7;padding:32px 16px;font-family:Arial,Helvetica,sans-serif;">' +
    '<div style="max-width:600px;margin:0 auto;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(81,3,109,0.08);">' +
    '<div style="background-color:#51036d;padding:32px 24px;text-align:center;"><span style="font-size:24px;font-weight:800;color:#ffffff;">ECONOMIC CARD</span></div>' +
    '<div style="padding:32px 24px;font-family:Arial,Helvetica,sans-serif;color:#191c1d;font-size:15px;line-height:1.6;">__CONTEUDO__</div>' +
    '<div style="padding:24px;background-color:#f4f5f7;font-size:12px;color:#7f7381;text-align:center;">&copy; __ANO__ Economic Card. Todos os direitos reservados.</div>' +
    '</div></div>';

function previewTemplate() {
    sincronizarEditores();
    let corpo = document.getElementById('corpoInput').value;
    if (!corpo) corpo = document.getElementById('corpoInputMkt') ? document.getElementById('corpoInputMkt').value : corpo;
    const content = document.getElementById('previewContent');
    const assunto = document.querySelector('input[name="assunto"]');
    content.innerHTML = shellPreview
        .replace('__CONTEUDO__', corpo
            .replace(/\{nome\}/g, 'João da Silva')
            .replace(/\{email\}/g, 'joao@exemplo.com')
            .replace(/\{assunto\}/g, (assunto ? assunto.value : '') || 'Assunto'))
        .replace(/\{ano\}/g, new Date().getFullYear())
        .replace(/\{assunto\}/g, (assunto ? assunto.value : '') || 'Assunto');
    document.getElementById('previewBox').classList.remove('hidden');
    content.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function iniciarEnvio() {
    const btn = document.getElementById('btnIniciarEnvio');
    const status = document.getElementById('statusEnvio');
    const barra = document.getElementById('barraProgresso');
    btn.disabled = true;
    const totalFila = parseInt(status.dataset.total || '0', 10) || 0;
    let total = totalFila, enviados = 0;
    const form = new FormData();
    form.append('acao', 'processar_lote');
    function etapa() {
        fetch('admin_configuracao.php?aba=mkt', { method: 'POST', body: form })
            .then(r => r.json())
            .then(d => {
                enviados += d.enviados_lote;
                const processados = d.fila + enviados;
                const totalGeral = total > 0 ? total : (d.fila + enviados);
                if (total === 0) total = totalGeral;
                const pct = total > 0 ? Math.round(processados / total * 100) : 0;
                if (barra) barra.style.width = Math.min(pct, 100) + '%';
                status.textContent = 'Enviados: ' + enviados + ' | Na fila: ' + d.fila + (d.erros_lote > 0 ? ' | Erros: ' + d.erros_lote : '');
                if (d.finalizado) {
                    status.textContent = 'Envio concluído! Total enviado nesta sessão: ' + enviados + '.';
                    btn.textContent = 'CONCLUÍDO';
                    setTimeout(() => location.reload(), 2000);
                } else {
                    etapa();
                }
            })
            .catch(() => {
                status.textContent = 'Erro na conexão. Recarregue a página e verifique os últimos envios.';
                btn.disabled = false;
                btn.textContent = 'TENTAR NOVAMENTE';
            });
    }
    etapa();
}
<?php if (isset($_SESSION['mkt_destinos'])): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('statusEnvio').dataset.total = <?php echo (int)$filaMkt; ?>;
});
<?php endif; ?>
</script>
</body>
</html>
