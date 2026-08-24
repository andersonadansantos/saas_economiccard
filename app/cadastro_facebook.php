<?php
require_once 'config.php';

function validarCpfFacebook($cpf) {
    return cpf_valido($cpf);
}

$pending = $_SESSION['fb_oauth_pending'] ?? null;
if (!$pending || empty($pending['facebook_id'])) {
    header('Location: login.php');
    exit;
}

$pers = $conn->query("SELECT * FROM personalizacao WHERE id = 1")->fetch_assoc();
$logoLoginUser = $pers['logo_login_user'] ?? '';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? $pending['nome']);
    $email = strtolower(trim($_POST['email'] ?? $pending['email']));
    $cpf = preg_replace('/\D/', '', trim($_POST['cpf'] ?? ''));
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $rg = trim($_POST['rg'] ?? '');
    $nascimento = trim($_POST['nascimento'] ?? '');
    $cep = trim($_POST['cep'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');

    $cpfMascarado = '';
    if (strlen($cpf) === 11) {
        $cpfMascarado = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    } else {
        $cpfMascarado = $cpf;
    }

    if ($nome === '' || strlen($cpf) !== 11 || $nascimento === '') {
        $erro = 'Preencha o nome completo, o CPF e a data de nascimento.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (!validarCpfFacebook($cpf)) {
        $erro = 'CPF inválido. Verifique os números digitados.';
    } else {
        $check = $conn->prepare("SELECT id FROM usuarios WHERE cpf = ?");
        $check->bind_param('s', $cpfMascarado);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $erro = 'CPF já cadastrado. Faça o login.';
        } else {
            $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, cpf, whatsapp, rg, nascimento, cep, cidade, endereco, avatar, facebook_id, afiliado_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssssssssss', $nome, $email, $cpfMascarado, $whatsapp, $rg, $nascimento, $cep, $cidade, $endereco, $pending['avatar'], $pending['facebook_id'], $pending['afiliado']);
            if ($stmt->execute()) {
                registrar_aceite_contrato($conn, 'usuarios', $conn->insert_id);
                $_SESSION['usuario_id'] = $conn->insert_id;
                if ($email !== '') {
                    require_once 'email_sender.php';
                    enviar_template_geral('bemvindo', ['nome' => $nome, 'email' => $email]);
                }
                $origem = $pending['origem'] ?? 'app';
                unset($_SESSION['fb_oauth_pending']);
                $destino = ($origem === 'web') ? 'web/dashboard.php' : 'dashboard.php';
                header('Location: ' . $destino);
                exit;
            } else {
                $erro = 'Erro ao salvar o cadastro. Tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Completar cadastro - Economic Card</title>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<style>
    body { font-family: 'Manrope', sans-serif; background-color: #51036d; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .glass-card { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.1); }
</style>
</head>
<body class="flex items-center justify-center min-h-screen text-white p-6">
<main class="w-full max-w-md">
<div class="glass-card rounded-2xl p-8">
<div class="text-center mb-6">
<?php if ($logoLoginUser): ?>
<img class="w-24 h-auto object-contain mx-auto mb-3" src="<?php echo htmlspecialchars($logoLoginUser); ?>" alt="Logo"/>
<?php endif; ?>
<h1 class="text-xl font-extrabold">Complete seu cadastro</h1>
<p class="text-sm text-white/60 mt-1">Faltam poucos dados para ativar seu cartão.</p>
</div>
<?php if ($erro): ?>
<div class="mb-4 p-3 rounded-lg bg-red-500/20 border border-red-400/40 text-sm text-red-100"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>
<form method="POST" action="cadastro_facebook.php" class="space-y-4">
<div>
<label class="block text-xs font-bold text-white/70 uppercase mb-1">Nome Completo *</label>
<input name="nome" value="<?php echo htmlspecialchars(trim($_POST['nome'] ?? $pending['nome'])); ?>" required class="w-full h-12 px-4 bg-white/10 border border-white/15 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#b6f570]" placeholder="Seu nome completo"/>
</div>
<div>
<label class="block text-xs font-bold text-white/70 uppercase mb-1">E-mail</label>
<input name="email" type="email" value="<?php echo htmlspecialchars(trim($_POST['email'] ?? $pending['email'])); ?>" required class="w-full h-12 px-4 bg-white/10 border border-white/15 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#b6f570]"/>
</div>
<div>
<label class="block text-xs font-bold text-white/70 uppercase mb-1">CPF *</label>
<input id="cpf" name="cpf" value="<?php echo htmlspecialchars($_POST['cpf'] ?? ''); ?>" required class="w-full h-12 px-4 bg-white/10 border border-white/15 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#b6f570]" placeholder="000.000.000-00" inputmode="numeric"/>
</div>
<div class="grid grid-cols-2 gap-3">
<div>
<label class="block text-xs font-bold text-white/70 uppercase mb-1">WhatsApp</label>
<input id="whatsapp" name="whatsapp" value="<?php echo htmlspecialchars($_POST['whatsapp'] ?? ''); ?>" class="w-full h-12 px-4 bg-white/10 border border-white/15 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#b6f570]" placeholder="(00) 00000-0000"/>
</div>
<div>
<label class="block text-xs font-bold text-white/70 uppercase mb-1">Nascimento</label>
<input name="nascimento" type="date" required value="<?php echo htmlspecialchars($_POST['nascimento'] ?? ''); ?>" class="w-full h-12 px-4 bg-white/10 border border-white/15 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#b6f570]"/>
</div>
</div>
<div>
<label class="block text-xs font-bold text-white/70 uppercase mb-1">CEP</label>
<input id="cep" name="cep" value="<?php echo htmlspecialchars($_POST['cep'] ?? ''); ?>" class="w-full h-12 px-4 bg-white/10 border border-white/15 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#b6f570]" placeholder="00000-000"/>
</div>
<button type="submit" class="w-full h-13 py-4 bg-[#b6f570] text-[#0f2000] font-extrabold rounded-xl hover:brightness-95 transition">CONCLUIR CADASTRO</button>
</form>
<p class="text-center mt-4 text-sm text-white/50">Já tem conta? <a href="login.php" class="text-white font-bold hover:underline">Entrar</a></p>
</div>
</main>
<script>
function maskCpf(v){v=v.replace(/\D/g,'').slice(0,11);if(v.length>9)return v.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/,'$1.$2.$3-$4');if(v.length>6)return v.replace(/(\d{3})(\d{3})(\d{3})/,'$1.$2.$3');if(v.length>3)return v.replace(/(\d{3})(\d{3})/,'$1.$2');return v;}
document.getElementById('cpf').addEventListener('input',e=>e.target.value=maskCpf(e.target.value));
document.getElementById('whatsapp').addEventListener('input',e=>{let v=e.target.value.replace(/\D/g,'').slice(0,11);if(v.length>10)v=v.replace(/(\d{2})(\d{5})(\d{4})/,'($1) $2-$3');else if(v.length>6)v=v.replace(/(\d{2})(\d{4})(\d{4})/,'($1) $2-$3');else if(v.length>2)v=v.replace(/(\d{2})(\d{1,4})/,'($1) $2');e.target.value=v;});
document.getElementById('cep').addEventListener('input',e=>{let v=e.target.value.replace(/\D/g,'').slice(0,8);if(v.length>5)v=v.replace(/(\d{5})(\d{3})/,'$1-$2');e.target.value=v;});
</script>
</body>
</html>
