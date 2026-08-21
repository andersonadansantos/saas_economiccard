<?php
require_once 'config.php';

if (($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bloqueado = turnstile_bloqueado($erro);
    if (!$bloqueado) {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = $_POST['senha'] ?? '';
    $hash    = hash('sha256', $senha);

    $stmt = $conn->prepare("SELECT * FROM admin WHERE usuario = ? AND senha = ?");
    $stmt->bind_param('ss', $usuario, $hash);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $_SESSION['admin_logado'] = true;
        $_SESSION['admin_usuario'] = $usuario;
        header('Location: admin.php');
        exit;
    } else {
        $erro = 'Usuário ou senha inválidos.';
    }
    }
}
$pers = $conn->query("SELECT * FROM personalizacao WHERE id = 1")->fetch_assoc();
$logoLoginAdmin = $pers['logo_app'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel Administrativo - Economic Card</title>
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
<div class="text-center mb-8 flex items-center justify-center">
    <?php if ($logoLoginAdmin): ?>
    <img class="max-w-[250px] max-h-[250px] h-auto object-contain" src="<?php echo htmlspecialchars($logoLoginAdmin); ?>" alt="Logo Economic Card"/>
    <?php else: ?>
    <span class="material-symbols-outlined text-[#51036d] text-6xl">shield_person</span>
    <?php endif; ?>
</div>
<?php if ($erro): ?>
<div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>
<form method="POST" action="admin" class="space-y-4">
    <div>
        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Usuário</label>
        <input name="usuario" type="text" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Senha</label>
        <input name="senha" type="password" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
    </div>
    <?php turnstile_widget('light'); ?>
<button type="submit" class="w-full bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold py-2.5 rounded-lg transition">ENTRAR</button>
</form>
<div class="mt-6 text-center">
<a href="usuario" class="text-sm text-[#51036d] hover:underline">Acessar app</a>
</div>
</div>
</body>
</html>