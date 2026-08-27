<?php
require_once 'config.php';

$erro = '';
if (isset($_SESSION['google_login_erro'])) {
    $erro = $_SESSION['google_login_erro'];
    unset($_SESSION['google_login_erro']);
}
if (isset($_GET['conta_encerrada'])) {
    $erro = 'Sua conta foi encerrada. Entre em contato com o suporte para mais informações.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bloqueado = turnstile_bloqueado($erro);
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
?>
<!DOCTYPE html>

<html class="light" lang="pt-BR"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
<title>Economic Card - Login</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<?php turnstile_script(); ?>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Hanken+Grotesk:wght@600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "outline": "#7f7381",
                        "on-tertiary-container": "#ff9d35",
                        "inverse-on-surface": "#f0f1f2",
                        "tertiary-fixed": "#ffdcc0",
                        "tertiary": "#4a2700",
                        "surface-container-highest": "#e1e3e4",
                        "surface-variant": "#e1e3e4",
                        "secondary-container": "#b6f570",
                        "inverse-surface": "#2e3132",
                        "error": "#ba1a1a",
                        "surface-container-lowest": "#ffffff",
                        "surface-dim": "#d9dadb",
                        "outline-variant": "#d1c2d1",
                        "surface-container-high": "#e7e8e9",
                        "on-primary-fixed-variant": "#6b2686",
                        "primary-fixed-dim": "#edb1ff",
                        "surface-container-low": "#f3f4f5",
                        "on-tertiary": "#ffffff",
                        "on-background": "#191c1d",
                        "on-tertiary-fixed": "#2e1600",
                        "on-secondary-fixed-variant": "#2e5000",
                        "surface-bright": "#f8f9fa",
                        "primary-container": "#6a2585",
                        "on-surface-variant": "#4e434f",
                        "surface": "#f8f9fa",
                        "on-tertiary-fixed-variant": "#6c3b00",
                        "background": "#f8f9fa",
                        "on-primary": "#ffffff",
                        "on-error": "#ffffff",
                        "tertiary-container": "#6a3a00",
                        "on-primary-container": "#e397fd",
                        "secondary": "#3e6a00",
                        "primary": "#51036d",
                        "on-error-container": "#93000a",
                        "on-primary-fixed": "#320046",
                        "secondary-fixed-dim": "#9cd857",
                        "on-surface": "#191c1d",
                        "error-container": "#ffdad6",
                        "secondary-fixed": "#b6f570",
                        "tertiary-fixed-dim": "#ffb877",
                        "surface-container": "#edeeef",
                        "on-secondary": "#ffffff",
                        "on-secondary-container": "#427000",
                        "inverse-primary": "#edb1ff",
                        "primary-fixed": "#f9d8ff",
                        "surface-tint": "#8540a0",
                        "on-secondary-fixed": "#0f2000"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "xl": "48px",
                        "sm": "12px",
                        "md": "24px",
                        "base": "8px",
                        "lg": "32px",
                        "xs": "4px",
                        "container-padding": "20px",
                        "card-gutter": "16px"
                    },
                    "fontFamily": {
                        "body-md": ["Manrope"],
                        "body-lg": ["Manrope"],
                        "label-bold": ["Hanken Grotesk"],
                        "display-lg": ["Manrope"],
                        "label-caps": ["Hanken Grotesk"],
                        "headline-sm": ["Manrope"],
                        "headline-md": ["Manrope"]
                    },
                    "fontSize": {
                        "body-md": ["14px", {"lineHeight": "1.5", "fontWeight": "400"}],
                        "body-lg": ["16px", {"lineHeight": "1.6", "fontWeight": "400"}],
                        "label-bold": ["12px", {"lineHeight": "1", "letterSpacing": "0.05em", "fontWeight": "700"}],
                        "display-lg": ["32px", {"lineHeight": "1.2", "fontWeight": "800"}],
                        "label-caps": ["10px", {"lineHeight": "1", "fontWeight": "600"}],
                        "headline-sm": ["20px", {"lineHeight": "1.4", "fontWeight": "600"}],
                        "headline-md": ["24px", {"lineHeight": "1.3", "fontWeight": "700"}]
                    }
                },
            },
        }
    </script>
<style>
        html, body {
            height: 100%;
        }
        body {
            background-color: #51036d;
            overflow-x: hidden;
            overflow-y: auto;
        }
        body.min-h-screen {
            min-height: 100vh;
            min-height: -webkit-fill-available;
            min-height: 100dvh;
        }
        main.min-h-screen {
            min-height: 100vh;
            min-height: -webkit-fill-available;
            min-height: 100dvh;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .input-gradient {
            background: linear-gradient(90deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);
        }

        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        .float-anim {
            animation: floating 4s ease-in-out infinite;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen text-on-primary selection:bg-secondary-fixed selection:text-on-secondary-fixed">
<main class="relative z-10 w-full max-w-md px-container-padding flex flex-col min-h-screen py-xl">
<header class="flex flex-col items-center mt-base md:mt-xl pt-[200px]">
<div class="float-anim mb-[30px] relative">
<?php if ($logoLoginUser): ?>
<img class="w-[200px] h-auto object-contain" src="<?php echo htmlspecialchars($logoLoginUser); ?>" alt="Logo Economic Card"/>
<?php else: ?>
<div class="w-24 h-24 glass-card rounded-[2rem] flex items-center justify-center p-md shadow-2xl overflow-hidden group">
<div class="absolute inset-0 bg-primary-container opacity-20 group-hover:opacity-40 transition-opacity"></div>
<span class="material-symbols-outlined text-[64px] text-on-primary-container" style="font-variation-settings: 'FILL' 1;">credit_card</span>
</div>
<?php endif; ?>
</div>
</header>
<section class="space-y-lg">
<div class="space-y-md">
<div class="text-center mb-base">
<span class="font-label-caps text-label-caps text-on-primary/60">CONECTE-SE</span>
<h2 class="font-headline-sm text-headline-sm mt-xs">Acessar com seu CPF</h2>
</div>
<?php if ($erro): ?>
<div class="bg-error-container/20 border border-error/30 text-on-error-container rounded-xl px-4 py-3 font-body-md text-body-md mb-md">
<?php echo htmlspecialchars($erro); ?>
</div>
<?php endif; ?>
<form method="POST" action="<?php echo htmlspecialchars($_SERVER['SCRIPT_NAME']); ?>">
<input type="hidden" name="origem" value="app"/>
<div class="relative group">
<label class="absolute -top-2.5 left-4 px-2 bg-primary text-[10px] font-bold text-on-primary/80 uppercase tracking-widest z-20" for="cpf">CPF</label>
<div class="relative flex items-center">
<span class="material-symbols-outlined absolute left-4 text-on-primary/40">fingerprint</span>
<input class="w-full h-14 pl-12 pr-4 bg-white/5 border border-white/10 rounded-xl font-body-lg text-body-lg text-on-primary placeholder:text-on-primary/20 focus:outline-none focus:ring-2 focus:ring-secondary focus:border-transparent transition-all duration-300" id="cpf" name="cpf" placeholder="000.000.000-00" type="tel" inputmode="numeric" autocomplete="off" required/>
</div>
</div>
<div class="my-md flex justify-center rounded-xl overflow-hidden">
<?php turnstile_widget('light'); ?>
</div>
<div class="space-y-base pt-base">
<button class="w-full h-14 bg-secondary text-on-secondary font-headline-sm text-headline-sm rounded-xl shadow-[0_8px_30px_rgba(62,106,0,0.3)] hover:scale-[1.02] active:scale-[0.98] transition-all duration-200 flex items-center justify-center gap-base" id="loginBtn" type="submit">
                        ACESSAR
                        <span class="material-symbols-outlined text-on-secondary">arrow_forward</span>
</button>
</div>
</form>
<div class="flex items-center gap-3 my-5">
<div class="flex-1 h-px bg-white/15"></div>
<span class="text-[11px] font-bold text-white/40 uppercase tracking-widest">ou</span>
<div class="flex-1 h-px bg-white/15"></div>
</div>
<a href="google_login.php" class="w-full h-14 bg-white text-on-surface font-headline-sm text-headline-sm rounded-xl hover:scale-[1.02] active:scale-[0.98] transition-all duration-200 flex items-center justify-center gap-base">
<svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.1c-.22-.66-.35-1.36-.35-2.1s.13-1.44.35-2.1V7.06H2.18A11 11 0 0 0 1 12c0 1.77.43 3.45 1.18 4.94l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
Entrar com Google
</a>
</div>
<div class="text-center">
<p class="font-body-md text-body-md text-on-primary/70">
                    Ainda não tem cadastro? 
                    <a class="text-secondary-fixed font-bold hover:underline ml-1" href="cadastro.php">Cadastrar-se</a>
</p>
</div>
</section>
<footer class="mt-auto pt-lg">
<div class="flex justify-center items-center px-sm">
<a class="font-label-bold text-label-bold text-on-primary/50 hover:text-on-primary transition-colors" href="#">Desenvolvido por WD Soluções Digitais LTDA. v.0.1</a>
</div>
</footer>
</main>
<script>
        document.addEventListener('DOMContentLoaded', () => {
            const cpfInput = document.getElementById('cpf');

            cpfInput.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 11) value = value.slice(0, 11);

                if (value.length > 9) {
                    value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                } else if (value.length > 6) {
                    value = value.replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
                } else if (value.length > 3) {
                    value = value.replace(/(\d{3})(\d{3})/, '$1.$2');
                }
                e.target.value = value;
            });
        });
    </script>
</body></html>
