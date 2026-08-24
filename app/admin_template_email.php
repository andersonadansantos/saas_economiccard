<?php
require_once 'config.php';
require_once 'email_sender.php';
if (!($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin_login.php');
    exit;
}

$subaba = $_GET['subaba'] ?? 'bemvindo';
if (!in_array($subaba, ['bemvindo', 'cartao_ativado'])) { $subaba = 'bemvindo'; }

function carregarTemplateGeral($conn, $chave) {
    $st = $conn->prepare("SELECT * FROM template_email_geral WHERE chave = ?");
    $st->bind_param('s', $chave);
    $st->execute();
    $t = $st->get_result()->fetch_assoc();
    if (!$t) {
        $conn->prepare("INSERT INTO template_email_geral (chave) VALUES (?)")->execute([$chave]);
        $st->execute();
        $t = $st->get_result()->fetch_assoc();
    }
    return $t;
}

$templateBemvindo = carregarTemplateGeral($conn, 'bemvindo');
$templateAtivado  = carregarTemplateGeral($conn, 'cartao_ativado');

// Conteúdo padrão de cada template (apenas região editável, sem shell)
$padraoBemvindo = '<p>Olá, <strong>{nome}</strong>!</p>
<p>Seja bem-vindo ao <strong>Economic Card</strong>! Seu cadastro foi realizado com sucesso.</p>
<p>Para acessar o aplicativo, entre com o seu <strong>CPF</strong> no campo de login. É rápido e fácil.</p>
<p>Fique atento: assim que o pagamento da sua assinatura for confirmado, seu cartão digital será ativado automaticamente.</p>
<p>Qualquer dúvida, fale com a nossa Central de Atendimento.</p>';

$padraoAtivado = '<p>Olá, <strong>{nome}</strong>!</p>
<p>Seu cartão <strong>Economic Card</strong> foi <strong>ativado com sucesso</strong>! Confira os descontos e benefícios nas lojas parceiras.</p>
<p>Aproveite todas as vantagens do seu cartão.</p>
<p>Qualquer dúvida, fale com a nossa Central de Atendimento.</p>';

$conteudoEditorBemvindo = extrairConteudoTemplate($templateBemvindo['corpo'] ?? '');
if (trim($conteudoEditorBemvindo) === '') { $conteudoEditorBemvindo = $padraoBemvindo; }
$conteudoEditorAtivado = extrairConteudoTemplate($templateAtivado['corpo'] ?? '');
if (trim($conteudoEditorAtivado) === '') { $conteudoEditorAtivado = $padraoAtivado; }

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'salvar_template_geral') {
        $chave   = trim($_POST['chave'] ?? '');
        if (!in_array($chave, ['bemvindo', 'cartao_ativado'])) { $chave = 'bemvindo'; }
        $nome    = trim($_POST['nome'] ?? '');
        $assunto = trim($_POST['assunto'] ?? '');
        $corpo   = $_POST['corpo'] ?? '';
        $stmt = $conn->prepare("UPDATE template_email_geral SET nome=?, assunto=?, corpo=? WHERE chave=?");
        $stmt->bind_param('ssss', $nome, $assunto, $corpo, $chave);
        $stmt->execute();
        $templateBemvindo = carregarTemplateGeral($conn, 'bemvindo');
        $templateAtivado  = carregarTemplateGeral($conn, 'cartao_ativado');
        $sucesso = 'Template de e-mail salvo com sucesso!';
    } elseif ($acao === 'enviar_teste_geral') {
        $chave   = trim($_POST['chave'] ?? '');
        if (!in_array($chave, ['bemvindo', 'cartao_ativado'])) { $chave = 'bemvindo'; }
        $t = carregarTemplateGeral($conn, $chave);
        $assunto = trim($_POST['assunto'] ?? $t['assunto'] ?? '');
        $corpo   = $_POST['corpo'] ?? '';
        $paraTeste = trim($_POST['email_teste'] ?? '');
        if ($paraTeste === '' || !filter_var($paraTeste, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail de destino válido para o teste.';
        } elseif ($assunto === '' || trim($corpo) === '') {
            $erro = 'Preencha o assunto e o conteúdo antes de testar.';
        } else {
            $corpoCompleto = templateShell(quillParaInline(extrairConteudoTemplate($corpo)));
            $render = renderTemplateEmail($corpoCompleto, $assunto, ['nome' => 'Cliente', 'email' => $paraTeste]);
            $r = enviar_email_smtp($paraTeste, $render[1], $render[0]);
            if ($r['ok']) {
                $sucesso = 'E-mail de teste enviado com sucesso para ' . htmlspecialchars($paraTeste) . '!';
            } else {
                $erro = 'Falha no teste: ' . htmlspecialchars($r['msg']);
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
<title>Template de Email - Admin Economic Card</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.js"></script>
<style>
    body { font-family: 'Manrope', sans-serif; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .ql-toolbar { border-radius: 12px 12px 0 0; border-color: #e5e7eb; }
    .ql-container { border-radius: 0 0 12px 12px; border-color: #e5e7eb; min-height: 260px; font-size: 15px; }
    .ql-editor { min-height: 260px; font-family: 'Manrope', sans-serif; }
</style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php require 'admin_menu.php'; ?>
<main class="md:ml-60 min-h-screen">
<header class="bg-white shadow-sm sticky top-0 z-30">
<div class="px-6 py-4 flex items-center justify-between">
<div>
<h1 class="text-xl font-extrabold text-gray-800">Template de Email</h1>
<p class="text-sm text-gray-500">Edite os e-mails automáticos enviados pelo sistema</p>
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

<div class="flex flex-wrap gap-2 mb-6">
<a href="admin_template_email.php?subaba=bemvindo" class="px-4 py-2.5 rounded-lg font-semibold text-sm transition <?php echo $subaba === 'bemvindo' ? 'bg-[#51036d] text-white' : 'bg-white text-gray-700 hover:bg-gray-200'; ?>">Bem vindo</a>
<a href="admin_template_email.php?subaba=cartao_ativado" class="px-4 py-2.5 rounded-lg font-semibold text-sm transition <?php echo $subaba === 'cartao_ativado' ? 'bg-[#51036d] text-white' : 'bg-white text-gray-700 hover:bg-gray-200'; ?>">Cartão Ativado</a>
</div>

<?php if ($subaba === 'bemvindo'): $tpl = $templateBemvindo; $chave = 'bemvindo'; ?>
<form method="POST" action="admin_template_email.php?subaba=bemvindo" class="grid grid-cols-1 gap-4">
<input type="hidden" name="acao" value="salvar_template_geral"/>
<input type="hidden" name="chave" value="bemvindo"/>
<div class="bg-white rounded-xl shadow-sm p-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-4">Bem vindo ao Economic Card</h2>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Assunto do e-mail</label>
<input type="text" name="assunto" value="<?php echo htmlspecialchars($tpl['assunto'] ?? 'Bem-vindo ao Economic Card!'); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div class="mt-4">
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Conteúdo do e-mail</label>
<div id="editorBemvindo" class="editor-wrapper bg-white border border-gray-300 rounded-lg"></div>
<input type="hidden" name="corpo" id="corpoInputBemvindo"/>
</div>
<div class="flex items-center justify-between mt-4 gap-3 flex-wrap">
<button type="submit" class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition">SALVAR TEMPLATE</button>
<div class="flex items-center gap-3">
<input type="email" name="email_teste" id="emailTesteBemvindo" placeholder="email@teste.com" class="w-56 border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d] text-sm">
<button type="button" onclick="enviarTeste('bemvindo')" class="bg-[#51036d] hover:bg-[#3a024d] text-white font-bold px-5 py-3 rounded-lg transition">ENVIAR TESTE</button>
</div>
</div>
</div>
</form>
<?php endif; ?>

<?php if ($subaba === 'cartao_ativado'): $tpl = $templateAtivado; $chave = 'cartao_ativado'; ?>
<form method="POST" action="admin_template_email.php?subaba=cartao_ativado" class="grid grid-cols-1 gap-4">
<input type="hidden" name="acao" value="salvar_template_geral"/>
<input type="hidden" name="chave" value="cartao_ativado"/>
<div class="bg-white rounded-xl shadow-sm p-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-4">Cartão Ativado</h2>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Assunto do e-mail</label>
<input type="text" name="assunto" value="<?php echo htmlspecialchars($tpl['assunto'] ?? 'Seu cartão Economic Card foi ativado!'); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div class="mt-4">
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Conteúdo do e-mail</label>
<div id="editorAtivado" class="editor-wrapper bg-white border border-gray-300 rounded-lg"></div>
<input type="hidden" name="corpo" id="corpoInputAtivado"/>
</div>
<div class="flex items-center justify-between mt-4 gap-3 flex-wrap">
<button type="submit" class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition">SALVAR TEMPLATE</button>
<div class="flex items-center gap-3">
<input type="email" name="email_teste" id="emailTesteAtivado" placeholder="email@teste.com" class="w-56 border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d] text-sm">
<button type="button" onclick="enviarTeste('cartao_ativado')" class="bg-[#51036d] hover:bg-[#3a024d] text-white font-bold px-5 py-3 rounded-lg transition">ENVIAR TESTE</button>
</div>
</div>
</div>
</form>
<?php endif; ?>

</div>
</main>
<script>
const toolbarOpcoes = [
    [{ 'header': [1, 2, 3, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ 'color': [] }, { 'background': [] }],
    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
    [{ 'align': [] }],
    ['blockquote', 'code-block'],
    ['link'],
    ['clean']
];

const conteudoBemvindo = <?php echo json_encode($conteudoEditorBemvindo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const conteudoAtivado = <?php echo json_encode($conteudoEditorAtivado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

let editorBemvindo = null, editorAtivado = null;

document.addEventListener('DOMContentLoaded', () => {
    const elBemvindo = document.getElementById('editorBemvindo');
    if (elBemvindo) {
        editorBemvindo = new Quill(elBemvindo, { theme: 'snow', modules: { toolbar: toolbarOpcoes }, placeholder: 'Escreva aqui o conteúdo do e-mail...' });
        editorBemvindo.root.innerHTML = conteudoBemvindo;
    }
    const elAtivado = document.getElementById('editorAtivado');
    if (elAtivado) {
        editorAtivado = new Quill(elAtivado, { theme: 'snow', modules: { toolbar: toolbarOpcoes }, placeholder: 'Escreva aqui o conteúdo do e-mail...' });
        editorAtivado.root.innerHTML = conteudoAtivado;
    }
    document.querySelectorAll('form[action*="admin_template_email.php"]').forEach(f => {
        f.addEventListener('submit', () => {
            if (editorBemvindo && document.getElementById('corpoInputBemvindo')) document.getElementById('corpoInputBemvindo').value = editorBemvindo.root.innerHTML;
            if (editorAtivado && document.getElementById('corpoInputAtivado')) document.getElementById('corpoInputAtivado').value = editorAtivado.root.innerHTML;
        });
    });
});

function enviarTeste(chave) {
    const email = chave === 'bemvindo' ? document.getElementById('emailTesteBemvindo') : document.getElementById('emailTesteAtivado');
    const editor = chave === 'bemvindo' ? editorBemvindo : editorAtivado;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'admin_template_email.php?subaba=' + chave;
    form.style.display = 'none';
    const campos = {
        'acao': 'enviar_teste_geral',
        'chave': chave,
        'corpo': editor.root.innerHTML,
        'assunto': document.querySelector('input[name=assunto]').value,
        'email_teste': email.value
    };
    for (const [nome, valor] of Object.entries(campos)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = nome;
        input.value = valor;
        form.appendChild(input);
    }
    document.body.appendChild(form);
    form.submit();
}
</script>
</body>
</html>
