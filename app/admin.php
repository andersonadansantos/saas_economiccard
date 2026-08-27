<?php
require_once 'config.php';
if (!($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $cpf = trim($_POST['cpf'] ?? '');
        $rg = trim($_POST['rg'] ?? '');
        $nasc = trim($_POST['nascimento'] ?? '');
        $end = trim($_POST['endereco'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $cep = trim($_POST['cep'] ?? '');
        $whats = trim($_POST['whatsapp'] ?? '');
        $stmt = $conn->prepare("UPDATE usuarios SET nome=?, email=?, cpf=?, rg=?, nascimento=?, endereco=?, cidade=?, cep=?, whatsapp=? WHERE id=?");
        $stmt->bind_param('sssssssssi', $nome, $email, $cpf, $rg, $nasc, $end, $cidade, $cep, $whats, $id);
        $stmt->execute();
        header('Location: admin.php?editado=1');
        exit;
    } elseif ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        header('Location: admin.php?excluido=1');
        exit;
    } elseif ($acao === 'reativar') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE usuarios SET status = 'ativo' WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        header('Location: admin.php?reativado=1');
        exit;
    } elseif ($acao === 'alternar_cartao') {
        $id = (int)($_POST['id'] ?? 0);
        $novo = (int)($_POST['novo'] ?? 0);
        $validade = null;
        if ($novo === 1) {
            $validade = date('Y-m-d', strtotime('+60 days'));
        } else {
            $validade = date('Y-m-d', strtotime('-1 day'));
        }
        $stmt = $conn->prepare("UPDATE usuarios SET cartao_ativo = ?, cartao_validade = ? WHERE id = ?");
        $stmt->bind_param('isi', $novo, $validade, $id);
        $stmt->execute();
        if ($novo === 1) {
            $u = $conn->query("SELECT nome, email FROM usuarios WHERE id = $id")->fetch_assoc();
            if ($u && !empty($u['email'])) {
                require_once 'email_sender.php';
                enviar_template_geral('cartao_ativado', ['nome' => $u['nome'], 'email' => $u['email']]);
            }
        }
        header('Location: admin.php?cartao=' . ($novo ? '1' : '0'));
        exit;
    }
}

$editado = isset($_GET['editado']);
$excluido = isset($_GET['excluido']);
$reativado = isset($_GET['reativado']);
$cartaoMsg = '';
if (isset($_GET['cartao'])) {
    $cartaoMsg = $_GET['cartao'] === '1' ? 'Cartão ativado com sucesso!' : 'Cartão expirado/desativado com sucesso!';
}

$busca = trim($_GET['busca'] ?? '');
if ($busca !== '') {
    $like = '%' . $busca . '%';
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE nome LIKE ? OR cpf LIKE ? OR cidade LIKE ? ORDER BY id DESC");
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $usuarios = $stmt->get_result();
} else {
    $usuarios = $conn->query("SELECT * FROM usuarios ORDER BY id DESC");
}
$total = $usuarios->num_rows;
$statsAtivos = (int)$conn->query("SELECT COUNT(*) AS total FROM usuarios WHERE cartao_ativo = 1")->fetch_assoc()['total'];
$statsInativos = (int)$conn->query("SELECT COUNT(*) AS total FROM usuarios WHERE cartao_ativo = 0")->fetch_assoc()['total'];
$statsPedidos = (int)$conn->query("SELECT COUNT(*) AS total FROM pedidos_cartao")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin - Economic Card</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    body { font-family: 'Manrope', sans-serif; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .cartaoToggle { display: none; }
    .toggleSwitch {
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        width: 50px;
        height: 30px;
        background-color: rgb(82, 82, 82);
        border-radius: 20px;
        cursor: pointer;
        transition-duration: .2s;
    }
    .toggleSwitch::after {
        content: "";
        position: absolute;
        height: 10px;
        width: 10px;
        left: 5px;
        background-color: transparent;
        border-radius: 50%;
        transition-duration: .2s;
        box-shadow: 5px 2px 7px rgba(8, 8, 8, 0.26);
        border: 5px solid white;
    }
    .cartaoToggle:checked + .toggleSwitch::after {
        transform: translateX(100%);
        transition-duration: .2s;
        background-color: white;
    }
    .cartaoToggle:checked + .toggleSwitch {
        background-color: rgb(148, 118, 255);
        transition-duration: .2s;
    }
</style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php require 'admin_menu.php'; ?>
<main class="md:ml-60 min-h-screen">
<header class="bg-white shadow-sm sticky top-0 z-30">
<div class="px-6 py-4 flex items-center justify-between">
<div>
<h1 class="text-xl font-extrabold text-gray-800">Dashboard</h1>
<p class="text-sm text-gray-500">Lista de cadastros do cartão</p>
</div>
<div class="flex items-center gap-4">
<span class="text-sm text-gray-600 hidden sm:block">Olá, <?php echo htmlspecialchars($_SESSION['admin_usuario'] ?? 'admin'); ?></span>
<a href="logout.php?admin=1" class="bg-[#51036d] hover:bg-[#3a024d] text-white rounded-lg px-4 py-2 text-sm font-semibold transition">Sair</a>
</div>
</div>
</header>
<div class="p-6">
<?php if ($editado): ?>
<div class="mb-6 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm">Cadastro atualizado com sucesso!</div>
<?php endif; ?>
<?php if ($excluido): ?>
<div class="mb-6 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">Cadastro excluído com sucesso!</div>
<?php endif; ?>
<?php if ($reativado): ?>
<div class="mb-6 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm">Conta reativada com sucesso!</div>
<?php endif; ?>
<?php if ($cartaoMsg): ?>
<div class="mb-6 p-3 rounded-lg bg-blue-50 border border-blue-200 text-blue-700 text-sm"><?php echo htmlspecialchars($cartaoMsg); ?></div>
<?php endif; ?>
<div class="bg-white rounded-xl shadow-sm p-4 mb-6 flex flex-col sm:flex-row items-start sm:items-center gap-4">
<div class="flex items-center gap-3">
<div class="w-12 h-12 rounded-xl bg-[#b6f570]/30 flex flex-col items-center justify-center">
<span class="text-xl font-extrabold text-[#3e6a00]"><?php echo $total; ?></span>
</div>
<div>
<p class="font-bold text-gray-800">Cadastros</p>
<p class="text-xs text-gray-500">Total de usuários no sistema</p>
</div>
</div>
<form method="GET" action="admin.php" class="flex-1 flex gap-2 w-full">
<input name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Buscar por nome, CPF ou cidade..." class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<button type="submit" class="bg-[#51036d] hover:bg-[#3a024d] text-white px-5 py-2.5 rounded-lg font-semibold transition">Buscar</button>
<?php if ($busca !== ''): ?>
<a href="admin.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2.5 rounded-lg font-semibold transition">Limpar</a>
<?php endif; ?>
</form>
</div>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
<div class="lg:col-span-1 flex flex-col items-center justify-center">
<h3 class="text-lg font-extrabold text-gray-800 self-start mb-4">Estatísticas de Cartões</h3>
<div class="relative w-48 h-48">
<canvas id="graficoCartoes"></canvas>
</div>
<div class="flex items-center justify-center gap-6 mt-4 text-sm">
<div class="flex items-center gap-2">
<span class="w-3 h-3 rounded-full bg-[#3e6a00]"></span>
<span class="font-semibold text-gray-700">Ativos: <span class="text-[#3e6a00]"><?php echo $statsAtivos; ?></span></span>
</div>
<div class="flex items-center gap-2">
<span class="w-3 h-3 rounded-full bg-gray-300"></span>
<span class="font-semibold text-gray-700">Inativos: <span class="text-gray-500"><?php echo $statsInativos; ?></span></span>
</div>
</div>
</div>
<div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
<div class="bg-[#b6f570]/20 border border-[#3e6a00]/20 rounded-xl p-5">
<p class="text-xs font-bold uppercase text-[#3e6a00] mb-1">Cartões Ativos</p>
<p class="text-3xl font-extrabold text-[#3e6a00]"><?php echo $statsAtivos; ?></p>
<p class="text-xs text-gray-500 mt-1"><?php echo $total > 0 ? round($statsAtivos / $total * 100) : 0; ?>% do total de cadastros</p>
</div>
<div class="bg-gray-100 border border-gray-200 rounded-xl p-5">
<p class="text-xs font-bold uppercase text-gray-500 mb-1">Cartões Inativos</p>
<p class="text-3xl font-extrabold text-gray-600"><?php echo $statsInativos; ?></p>
<p class="text-xs text-gray-500 mt-1"><?php echo $total > 0 ? round($statsInativos / $total * 100) : 0; ?>% do total de cadastros</p>
</div>
<div class="bg-[#51036d]/5 border border-[#51036d]/20 rounded-xl p-5">
<p class="text-xs font-bold uppercase text-[#51036d] mb-1">Pedidos/Cartões</p>
<p class="text-3xl font-extrabold text-[#51036d]"><?php echo $statsPedidos; ?></p>
<p class="text-xs text-gray-500 mt-1">Pedidos de cartão físico</p>
</div>
<div class="bg-[#51036d]/5 border border-[#51036d]/20 rounded-xl p-5">
<p class="text-xs font-bold uppercase text-[#51036d] mb-1">Total de cadastros</p>
<p class="text-3xl font-extrabold text-[#51036d]"><?php echo $total; ?></p>
</div>
</div>
</div>
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead>
<tr class="bg-gray-50 text-gray-600 text-left uppercase text-xs tracking-wider">
<th class="px-4 py-3">#</th>
<th class="px-4 py-3">Nome</th>
<th class="px-4 py-3">E-mail</th>
<th class="px-4 py-3">CPF</th>
<th class="px-4 py-3">Cidade</th>
<th class="px-4 py-3">WhatsApp</th>
<th class="px-4 py-3">Cartão</th>
<th class="px-4 py-3">Cadastrado em</th>
</tr>
</thead>
<tbody>
<?php if ($total === 0): ?>
<tr><td colspan="8" class="px-4 py-10 text-center text-gray-500">Nenhum cadastro encontrado.</td></tr>
<?php endif; ?>
<?php while ($u = $usuarios->fetch_assoc()): ?>
<tr class="border-t border-gray-100 hover:bg-gray-50 <?php echo (($u['status'] ?? 'ativo') === 'desativado') ? 'opacity-70' : ''; ?>">
<td class="px-4 py-3 text-gray-500"><?php echo $u['id']; ?></td>
<td class="px-4 py-3">
<div class="flex items-center gap-3">
<?php if ($u['avatar']): ?>
<img class="w-9 h-9 rounded-full object-cover border border-gray-200 shrink-0" src="<?php echo htmlspecialchars($u['avatar']); ?>" alt="Avatar de <?php echo htmlspecialchars($u['nome']); ?>"/>
<?php else: ?>
<div class="w-9 h-9 rounded-full bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-[18px]">person</span>
</div>
<?php endif; ?>
<div class="flex flex-wrap items-center gap-2">
<button type="button" class="text-[#51036d] font-bold hover:underline text-left" onclick="abrirModal(<?php echo $u['id']; ?>)">
<?php echo htmlspecialchars($u['nome']); ?>
</button>
<?php if (($u['status'] ?? 'ativo') === 'desativado'): ?>
<span class="inline-flex items-center gap-1 text-[10px] font-bold text-gray-500 bg-gray-200 rounded-lg px-2 py-1" title="Usuário encerrou a conta">
<span class="material-symbols-outlined text-[12px]">person_off</span> Desativada
</span>
<?php endif; ?>
<?php if (!empty($u['google_id'])): ?>
<span class="inline-flex items-center gap-1 text-[10px] font-bold text-[#4285F4] bg-[#4285F4]/10 rounded-lg px-2 py-1" title="Cadastro feito pelo Google">
<svg class="w-3 h-3" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.1c-.22-.66-.35-1.36-.35-2.1s.13-1.44.35-2.1V7.06H2.18A11 11 0 0 0 1 12c0 1.77.43 3.45 1.18 4.94l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
Google
</span>
<?php endif; ?>
<?php if (!empty($u['facebook_id'])): ?>
<span class="inline-flex items-center gap-1 text-[10px] font-bold text-[#1877F2] bg-[#1877F2]/10 rounded-lg px-2 py-1" title="Cadastro feito pelo Facebook">
<svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047v-2.66c0-3.026 1.792-4.697 4.533-4.697 1.312 0 2.686.235 2.686.235v2.971h-1.513c-1.491 0-1.956.93-1.956 1.886v2.265h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
Facebook
</span>
<?php endif; ?>
<button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-[#3e6a00] hover:text-[#2e5000] bg-[#b6f570]/20 hover:bg-[#b6f570]/40 rounded-lg px-2 py-1 transition" onclick="abrirEditar(<?php echo $u['id']; ?>)" title="Editar cadastro">
<span class="material-symbols-outlined text-[14px]">edit</span> Editar
</button>
<form method="POST" action="admin.php" onsubmit="confirmarExclusao(event, this, 'Excluir este cadastro e todos os dados dele? Esta ação não pode ser desfeita.');" class="inline">
<input type="hidden" name="acao" value="excluir"/>
<input type="hidden" name="id" value="<?php echo $u['id']; ?>"/>
<button type="submit" class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 rounded-lg px-2 py-1 transition" title="Excluir cadastro">
<span class="material-symbols-outlined text-[14px]">delete</span> Excluir
</button>
</form>
<?php if (($u['status'] ?? 'ativo') === 'desativado'): ?>
<form method="POST" action="admin.php" class="inline">
<input type="hidden" name="acao" value="reativar"/>
<input type="hidden" name="id" value="<?php echo $u['id']; ?>"/>
<button type="submit" class="inline-flex items-center gap-1 text-xs font-semibold text-[#3e6a00] hover:text-[#2e5000] bg-[#b6f570]/20 hover:bg-[#b6f570]/40 rounded-lg px-2 py-1 transition" title="Reativar conta do usuário">
<span class="material-symbols-outlined text-[14px]">undo</span> Reativar
</button>
</form>
<?php endif; ?>
</div>
</div>
</td>
<td class="px-4 py-3"><?php echo htmlspecialchars($u['email'] ?: '-'); ?></td><td class="px-4 py-3"><?php echo htmlspecialchars($u['cpf']); ?></td><td class="px-4 py-3"><?php echo htmlspecialchars($u['cidade'] ?: '-'); ?></td>
<td class="px-4 py-3">
<?php if ($u['whatsapp']): ?>
<a class="inline-flex items-center gap-1.5 text-[#128C7E] hover:opacity-80 transition-opacity" href="https://wa.me/<?php echo htmlspecialchars(preg_replace('/\D/', '', $u['whatsapp'])); ?>" target="_blank" rel="noopener" title="Falar com <?php echo htmlspecialchars($u['nome']); ?> no WhatsApp">
<span class="material-symbols-outlined text-[18px]">chat</span>
<?php echo htmlspecialchars($u['whatsapp']); ?>
</a>
<?php else: ?>
-
<?php endif; ?>
</td>
<td class="px-4 py-3">
<div class="flex items-center gap-2">
<input type="checkbox" id="cartaoToggle<?php echo (int)$u['id']; ?>" class="cartaoToggle" <?php echo $u['cartao_ativo'] ? 'checked' : ''; ?> onchange="alternarCartao(<?php echo (int)$u['id']; ?>, this.checked ? 1 : 0, this)">
<label for="cartaoToggle<?php echo (int)$u['id']; ?>" class="toggleSwitch" title="<?php echo $u['cartao_ativo'] ? 'Expirar cartão' : 'Ativar cartão'; ?>"></label>
</div>
</td>
<td class="px-4 py-3 text-gray-500"><?php echo date('d/m/Y H:i', strtotime($u['criado_em'])); ?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
</div>
</main>

<div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-black/50" onclick="fecharModal()"></div>
<div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
<div class="bg-[#51036d] px-6 py-5 flex items-center justify-between">
<h2 class="text-white font-bold">Detalhes do Cadastro</h2>
<button onclick="fecharModal()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<div class="p-6 space-y-3">
<div class="text-center mb-2">
<div class="w-16 h-16 mx-auto rounded-full bg-[#b6f570]/40 flex items-center justify-center text-[#3e6a00] overflow-hidden">
<span id="m-avatar-icon" class="material-symbols-outlined text-3xl" style="font-variation-settings:'FILL' 1;">person</span>
<img id="m-avatar" class="w-full h-full object-cover hidden" alt="Avatar do usuário"/>
</div>
<p id="m-nome" class="text-lg font-extrabold text-gray-800 mt-2"></p>
</div>
<div class="grid grid-cols-2 gap-3">
<div class="bg-gray-50 rounded-lg p-3">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">CPF</p>
<p id="m-cpf" class="text-sm font-semibold text-gray-800 mt-1"></p>
</div>
<div class="bg-gray-50 rounded-lg p-3">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">E-mail</p>
<p id="m-email" class="text-sm font-semibold text-gray-800 mt-1 break-all"></p>
</div>
<div class="bg-gray-50 rounded-lg p-3">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">RG</p>
<p id="m-rg" class="text-sm font-semibold text-gray-800 mt-1"></p>
</div>
<div class="bg-gray-50 rounded-lg p-3">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">Nascimento</p>
<p id="m-nasc" class="text-sm font-semibold text-gray-800 mt-1"></p>
</div>
<div class="bg-gray-50 rounded-lg p-3">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">WhatsApp</p>
<p id="m-whats" class="text-sm font-semibold text-gray-800 mt-1"></p>
</div>
<div class="bg-gray-50 rounded-lg p-3 col-span-2">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">Endereço</p>
<p id="m-end" class="text-sm font-semibold text-gray-800 mt-1"></p>
</div>
<div class="bg-gray-50 rounded-lg p-3">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">Cidade</p>
<p id="m-cidade" class="text-sm font-semibold text-gray-800 mt-1"></p>
</div>
<div class="bg-gray-50 rounded-lg p-3">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">CEP</p>
<p id="m-cep" class="text-sm font-semibold text-gray-800 mt-1"></p>
</div>
<div class="bg-gray-50 rounded-lg p-3 col-span-2">
<p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">Cadastrado em</p>
<p id="m-criado" class="text-sm font-semibold text-gray-800 mt-1"></p>
</div>
</div>
<div class="mt-4">
<a id="m-contrato" href="#" target="_blank" class="flex items-center justify-center gap-2 text-[#3e6a00] font-bold border border-[#3e6a00]/30 bg-[#3e6a00]/5 hover:bg-[#3e6a00]/10 rounded-lg px-4 py-2.5 transition">
<span class="material-symbols-outlined text-[18px]">description</span> Baixar Contrato de Adesão
</a>
</div>
</div>
</div>
</div>

<div id="modalEditar" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-black/50" onclick="fecharEditar()"></div>
<div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden max-h-[90vh] overflow-y-auto">
<div class="bg-[#3e6a00] px-6 py-5 flex items-center justify-between">
<h2 class="text-white font-bold">Editar Cadastro</h2>
<button onclick="fecharEditar()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<div class="p-6">
<form method="POST" action="admin.php" class="grid grid-cols-2 gap-3">
<input type="hidden" name="acao" value="editar"/>
<input type="hidden" name="id" id="e-id"/>
<div class="col-span-2">
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nome completo</label>
<input id="e-nome" name="nome" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#3e6a00]">
</div>
<div class="col-span-2">
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">E-mail</label>
<input id="e-email" name="email" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#3e6a00]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">CPF</label>
<input id="e-cpf" name="cpf" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#3e6a00]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">RG</label>
<input id="e-rg" name="rg" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#3e6a00]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nascimento</label>
<input id="e-nasc" name="nascimento" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#3e6a00]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">WhatsApp</label>
<input id="e-whats" name="whatsapp" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#3e6a00]">
</div>
<div class="col-span-2">
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Endereço</label>
<input id="e-end" name="endereco" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#3e6a00]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Cidade</label>
<input id="e-cidade" name="cidade" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#3e6a00]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">CEP</label>
<input id="e-cep" name="cep" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#3e6a00]">
</div>
<div class="col-span-2 flex justify-end gap-3 mt-2">
<button type="button" onclick="fecharEditar()" class="px-5 py-2.5 rounded-lg font-semibold bg-gray-200 hover:bg-gray-300 text-gray-700 transition">Cancelar</button>
<button type="submit" class="px-5 py-2.5 rounded-lg font-bold bg-[#3e6a00] hover:bg-[#2e5000] text-white transition">SALVAR ALTERAÇÕES</button>
</div>
</form>
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
<button onclick="confirmarModal()" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold px-4 py-2.5 rounded-lg transition">Confirmar</button>
</div>
</div>
</div>
</div>

<script>
const dados = <?php
$dados = [];
$r = $conn->query("SELECT * FROM usuarios");
while ($linha = $r->fetch_assoc()) { $dados[] = $linha; }
echo json_encode($dados, JSON_UNESCAPED_UNICODE);
?>;

function abrirModal(id) {
    const u = dados.find(x => parseInt(x.id) === id);
    if (!u) return;
    const av = document.getElementById('m-avatar');
    const avIcon = document.getElementById('m-avatar-icon');
    if (u.avatar) {
        av.src = u.avatar;
        av.classList.remove('hidden');
        avIcon.classList.add('hidden');
    } else {
        av.classList.add('hidden');
        avIcon.classList.remove('hidden');
    }
    document.getElementById('m-nome').textContent = u.nome || '-';
    document.getElementById('m-cpf').textContent = u.cpf || '-';
    document.getElementById('m-email').textContent = u.email || '-';
    document.getElementById('m-rg').textContent = u.rg || '-';
    document.getElementById('m-nasc').textContent = u.nascimento ? new Date(u.nascimento + 'T00:00:00').toLocaleDateString('pt-BR') : '-';
    document.getElementById('m-whats').textContent = u.whatsapp || '-';
    document.getElementById('m-end').textContent = u.endereco || '-';
    document.getElementById('m-cidade').textContent = u.cidade || '-';
    document.getElementById('m-cep').textContent = u.cep || '-';
    document.getElementById('m-criado').textContent = u.criado_em ? new Date(u.criado_em).toLocaleString('pt-BR') : '-';

    const p = new URLSearchParams({ preview: '1', download: '1', uid: u.id || '', nome: u.nome || '', cpf: u.cpf || '', email: u.email || '', whatsapp: u.whatsapp || '', endereco: u.endereco || '', cidade: u.cidade || '', cep: u.cep || '' });
    document.getElementById('m-contrato').href = 'gerar_contrato.php?' + p.toString();

    const modal = document.getElementById('modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function fecharModal() {
    const modal = document.getElementById('modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function abrirEditar(id) {
    const u = dados.find(x => parseInt(x.id) === id);
    if (!u) return;
    document.getElementById('e-id').value = u.id;
    document.getElementById('e-nome').value = u.nome || '';
    document.getElementById('e-email').value = u.email || '';
    document.getElementById('e-cpf').value = u.cpf || '';
    document.getElementById('e-rg').value = u.rg || '';
    document.getElementById('e-nasc').value = u.nascimento || '';
    document.getElementById('e-whats').value = u.whatsapp || '';
    document.getElementById('e-end').value = u.endereco || '';
    document.getElementById('e-cidade').value = u.cidade || '';
    document.getElementById('e-cep').value = u.cep || '';
    const modal = document.getElementById('modalEditar');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function fecharEditar() {
    const modal = document.getElementById('modalEditar');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

let formPendente = null;
function confirmarExclusao(ev, form, msg) {
    ev.preventDefault();
    formPendente = form;
    document.getElementById('modalConfirmacaoMsg').textContent = msg;
    const m = document.getElementById('modalConfirmacao');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

let cartaoPendente = null;
function alternarCartao(id, novo, el) {
    cartaoPendente = { id: id, novo: novo, el: el, anterior: novo === 1 ? 0 : 1 };
    document.getElementById('modalConfirmacaoMsg').textContent = novo === 1 ? 'Ativar o cartão deste usuário?' : 'Expirar/desativar o cartão deste usuário?';
    const btnOk = document.querySelector('#modalConfirmacao button.bg-red-600');
    if (btnOk) {
        if (novo === 1) { btnOk.classList.remove('bg-red-600', 'hover:bg-red-700'); btnOk.classList.add('bg-[#3e6a00]', 'hover:bg-[#2e5000]'); }
        else { btnOk.classList.remove('bg-[#3e6a00]', 'hover:bg-[#2e5000]'); btnOk.classList.add('bg-red-600', 'hover:bg-red-700'); }
    }
    const m = document.getElementById('modalConfirmacao');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function confirmarModal() {
    if (formPendente) { formPendente.submit(); fecharModalConfirmacao(); return; }
    if (cartaoPendente) {
        const p = cartaoPendente;
        cartaoPendente = null;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'admin.php';
        form.innerHTML = '<input type="hidden" name="acao" value="alternar_cartao">' +
            '<input type="hidden" name="id" value="' + p.id + '">' +
            '<input type="hidden" name="novo" value="' + p.novo + '">';
        document.body.appendChild(form);
        form.submit();
        fecharModalConfirmacao();
    }
}
function fecharModalConfirmacao() {
    if (cartaoPendente && cartaoPendente.el) {
        cartaoPendente.el.checked = cartaoPendente.anterior === 1;
    }
    formPendente = null;
    cartaoPendente = null;
    const m = document.getElementById('modalConfirmacao');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

new Chart(document.getElementById('graficoCartoes'), {
    type: 'doughnut',
    data: {
        labels: ['Ativos', 'Inativos'],
        datasets: [{
            data: [<?php echo $statsAtivos; ?>, <?php echo $statsInativos; ?>],
            backgroundColor: ['#3e6a00', '#d1d5db'],
            borderWidth: 0,
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(ctx) { return ' ' + ctx.label + ': ' + ctx.raw; }
                }
            }
        }
    }
});
</script>
</body>
</html>