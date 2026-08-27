<?php
require_once __DIR__ . '/../config.php';

if (isset($_SESSION['usuario_id'])) {
    $st = $conn->prepare("SELECT cartao_ativo FROM usuarios WHERE id = ?");
    $uidLogado = (int)$_SESSION['usuario_id'];
    $st->bind_param('i', $uidLogado);
    $st->execute();
    $rowLogado = $st->get_result()->fetch_assoc();
    if ($rowLogado && !$rowLogado['cartao_ativo']) {
        header('Location: ativar.php');
        exit;
    }
    header('Location: dashboard.php');
    exit;
}

$erro = '';
if (isset($_SESSION['google_login_erro'])) {
    $erro = $_SESSION['google_login_erro'];
    unset($_SESSION['google_login_erro']);
}
if (isset($_GET['conta_encerrada'])) {
    $erro = 'Sua conta foi encerrada. Entre em contato com o suporte para mais informações.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bloqueado = (($_POST['origem'] ?? '') === 'app') ? false : turnstile_bloqueado($erro);
    if (!$bloqueado) {
    $cpf = preg_replace('/\D/', '', trim($_POST['cpf'] ?? ''));
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE REPLACE(REPLACE(cpf,'.',''),'-','') = ?");
    $cpfBusca = preg_replace('/\.|-/', '', $cpf);
    $stmt->bind_param('s', $cpfBusca);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $userLogin = $res->fetch_assoc();
        if (($userLogin['status'] ?? 'ativo') === 'desativado') {
            $erro = 'Esta conta foi encerrada. Entre em contato com o suporte para mais informações.';
        } else {
        $_SESSION['usuario_id'] = $userLogin['id'];
        if (!$userLogin['cartao_ativo']) {
            header('Location: ativar.php');
            exit;
        }
        header('Location: dashboard.php');
        exit;
        }
    } else {
        $erro = 'CPF não encontrado. Verifique ou faça o cadastro.';
    }
    }
}
$pers = $conn->query("SELECT * FROM personalizacao WHERE id = 1")->fetch_assoc();
$logoLoginUser = $pers['logo_login_user'] ?? '';
function webUrl($src) {
    if (!$src) return '';
    if (preg_match('#^https?://#i', $src) || strpos($src, 'data:') === 0) return $src;
    return '../' . ltrim($src, '/');
}
?>
<!DOCTYPE html>
<html class="light" lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Economic Card - Login</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Hanken+Grotesk:wght@600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<?php turnstile_script(); ?>
<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#51036d", "primary-dark": "#3a024d", "primary-container": "#6a2585",
                    secondary: "#3e6a00", "secondary-container": "#b6f570",
                    surface: "#f4f5f7", "on-surface": "#191c1d", "on-surface-variant": "#4e434f",
                    "surface-container-lowest": "#ffffff", error: "#ba1a1a"
                },
                fontFamily: { sans: ["Manrope", "sans-serif"], display: ["Hanken Grotesk", "sans-serif"] }
            }
        }
    };
</script>
<style>
    body { font-family: 'Manrope', sans-serif; -webkit-tap-highlight-color: transparent; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    .premium-gradient { background: linear-gradient(135deg, #51036d 0%, #6a2585 100%); }
</style>
</head>
<body class="min-h-screen bg-[#f4f5f7] flex items-center justify-center p-6">
<div class="w-full max-w-4xl bg-white rounded-2xl shadow-2xl overflow-hidden grid grid-cols-1 md:grid-cols-2">
<!-- Left brand panel -->
<div class="premium-gradient text-white p-10 hidden md:flex items-center justify-center relative overflow-hidden">
<div class="absolute -top-16 -right-16 w-64 h-64 bg-white/10 rounded-full blur-2xl"></div>
<div class="absolute -bottom-20 -left-10 w-72 h-72 bg-white/10 rounded-full blur-2xl"></div>
<?php if ($logoLoginUser): ?>
<img class="relative z-10 w-64 h-auto object-contain" src="<?php echo webUrl($logoLoginUser); ?>" alt="Logo Economic Card"/>
<?php else: ?>
<div class="relative z-10 w-20 h-20 rounded-2xl bg-white/10 flex items-center justify-center"><span class="material-symbols-outlined text-4xl">credit_card</span></div>
<?php endif; ?>
</div>
<!-- Right form -->
<div class="p-8 md:p-12">
<div class="md:hidden flex justify-center mb-6">
<?php if ($logoLoginUser): ?>
<img class="w-24 h-auto object-contain" src="<?php echo webUrl($logoLoginUser); ?>" alt="Logo Economic Card"/>
<?php endif; ?>
</div>
<div class="mb-8">
<span class="text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-widest">CONECTE-SE</span>
<h3 class="text-2xl font-extrabold text-on-surface mt-1">Acessar com seu CPF</h3>
<p class="text-sm text-on-surface-variant mt-1">Digite o CPF cadastrado para entrar.</p>
</div>
<?php if ($erro): ?>
<div class="mb-6 bg-error/10 border border-error/30 text-[#93000a] rounded-xl px-4 py-3 text-sm">
<?php echo htmlspecialchars($erro); ?>
</div>
<?php endif; ?>
<form method="POST" action="<?php echo htmlspecialchars($_SERVER['SCRIPT_NAME']); ?>" class="space-y-5">
<div class="relative group">
<label class="absolute -top-2.5 left-4 px-2 bg-white text-[10px] font-bold text-primary uppercase tracking-widest z-20" for="cpf">CPF</label>
<div class="relative flex items-center">
<span class="material-symbols-outlined absolute left-4 text-on-surface-variant/40">fingerprint</span>
<input class="w-full h-14 pl-12 pr-4 bg-[#f4f5f7] border border-outline-variant rounded-xl text-base text-on-surface placeholder:text-on-surface-variant/30 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all" id="cpf" name="cpf" placeholder="000.000.000-00" type="tel" inputmode="numeric" autocomplete="off" required/>
</div>
</div>
<?php turnstile_widget('light'); ?>
<button class="w-full h-14 bg-primary hover:bg-primary-dark text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2" type="submit">
ACESSAR
<span class="material-symbols-outlined">arrow_forward</span>
</button>
</form>
<div class="flex items-center gap-3 my-5">
<div class="flex-1 h-px bg-outline-variant/60"></div>
<span class="text-[11px] font-bold text-on-surface-variant/50 uppercase tracking-widest">ou</span>
<div class="flex-1 h-px bg-outline-variant/60"></div>
</div>
<a href="../google_login.php?origem=web" class="w-full h-14 bg-white border border-outline-variant hover:bg-gray-50 text-on-surface font-bold rounded-xl shadow-sm hover:shadow transition-all flex items-center justify-center gap-3">
<svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.1c-.22-.66-.35-1.36-.35-2.1s.13-1.44.35-2.1V7.06H2.18A11 11 0 0 0 1 12c0 1.77.43 3.45 1.18 4.94l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
Entrar com Google
</a>
<div class="text-center mt-6">
<p class="text-sm text-on-surface-variant">Ainda não tem cadastro? <a class="text-primary font-bold hover:underline" href="../cadastro.php">Cadastrar-se</a></p>
</div>
<div class="md:hidden text-center mt-8 text-[10px] text-on-surface-variant/60">© 2024 ECONOMIC CARD. TODOS OS DIREITOS RESERVADOS.</div>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const cpfInput = document.getElementById('cpf');
    cpfInput.addEventListener('input', (e) => {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) value = value.slice(0, 11);
        if (value.length > 9) value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        else if (value.length > 6) value = value.replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
        else if (value.length > 3) value = value.replace(/(\d{3})(\d{3})/, '$1.$2');
        e.target.value = value;
    });
});
</script>
</body>
</html>
