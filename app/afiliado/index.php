<?php
require_once dirname(__DIR__) . '/config.php';

if (!empty($_GET['ref'])) {
    $ref = trim($_GET['ref']);
    $st = $conn->prepare("SELECT id FROM afiliados WHERE token = ?");
    $st->bind_param('s', $ref);
    $st->execute();
    if ($st->get_result()->num_rows > 0) {
        header('Location: ../cadastro.php?afiliado=' . urlencode($ref));
        exit;
    }
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bloqueado = turnstile_bloqueado($erro);
    if (!$bloqueado) {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $stmt = $conn->prepare("SELECT * FROM afiliados WHERE email = ? AND ativo = 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $af = $res->fetch_assoc();
        if (password_verify($senha, $af['senha'])) {
            $_SESSION['afiliado_id'] = (int)$af['id'];
            $_SESSION['afiliado_nome'] = $af['nome'];
            header('Location: painel.php');
            exit;
        } else {
            $erro = 'Senha incorreta.';
        }
    } else {
        $erro = 'Afiliado não encontrado ou inativo.';
    }
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
    body { font-family: 'Manrope', sans-serif; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
</style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-sm bg-white rounded-2xl shadow-lg p-8">
<div class="text-center mb-8">
<div class="w-14 h-14 mx-auto rounded-2xl bg-[#51036d] flex items-center justify-center mb-4">
<span class="material-symbols-outlined text-white text-3xl">handshake</span>
</div>
<h1 class="text-xl font-extrabold text-gray-800">Painel do Afiliado</h1>
<p class="text-sm text-gray-500">Economic Card</p>
</div>
<?php if ($erro): ?>
<div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>
<form method="POST" action="index.php" class="space-y-4">
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">E-mail</label>
<input name="email" type="email" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Senha</label>
<input name="senha" type="password" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<?php turnstile_widget('light'); ?>
<button type="submit" class="w-full bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold py-2.5 rounded-lg transition">ENTRAR</button>
</form>
<div class="mt-6 text-center">
<a href="../login.php" class="text-sm text-[#51036d] hover:underline">Acessar app</a>
</div>
</div>
</body>
</html>
