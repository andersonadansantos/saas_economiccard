<?php
require_once 'config.php';
require_once 'email_sender.php';

function validarCpf($cpf) {
    return cpf_valido($cpf);
}

function validarCep($cep) {
    $cep = preg_replace('/\D/', '', $cep);
    if (strlen($cep) !== 8) {
        return false;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $resp = @file_get_contents('https://viacep.com.br/ws/' . $cep . '/json/', false, $ctx);
    if ($resp === false) {
        return true;
    }
    $dados = json_decode($resp, true);
    return is_array($dados) && empty($dados['erro']);
}

$pers = $conn->query("SELECT * FROM personalizacao WHERE id = 1")->fetch_assoc();
$logoApp = $pers['logo_app'] ?? '';

$afiliadoToken = '';
if (!empty($_GET['afiliado']) || !empty($_POST['afiliado'])) {
    $token = trim($_GET['afiliado'] ?? $_POST['afiliado']);
    $st = $conn->prepare("SELECT id FROM afiliados WHERE token = ?");
    $st->bind_param('s', $token);
    $st->execute();
    if ($st->get_result()->num_rows > 0) {
        $afiliadoToken = $token;
    }
}

$erro = '';
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome       = trim($_POST['nome'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $cpf        = preg_replace('/\D/', '', trim($_POST['cpf'] ?? ''));
    $whatsapp   = trim($_POST['whatsapp'] ?? '');
    $rg         = trim($_POST['rg'] ?? '');
    $nascimento = trim($_POST['nascimento'] ?? '');
    $cep        = trim($_POST['cep'] ?? '');
    $cidade     = trim($_POST['cidade'] ?? '');
    $endereco   = trim($_POST['endereco'] ?? '');
    $codigoColab = preg_replace('/\D/', '', trim($_POST['codigo_colaborador'] ?? ''));

    if ($codigoColab !== '') {
        $st = $conn->prepare("SELECT token FROM afiliados WHERE codigo = LPAD(?, 4, '0') AND ativo = 1 LIMIT 1");
        $st->bind_param('s', $codigoColab);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if ($row) {
            $afiliadoToken = $row['token'];
        }
    }

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
    } elseif (!validarCpf($cpf)) {
        $erro = 'CPF inválido. Verifique os números digitados.';
    } elseif (!validarCep($cep)) {
        $erro = 'CEP inválido. Verifique o CEP digitado.';
    } elseif (empty($_POST['aceite_contrato'])) {
        $erro = 'Você precisa aceitar o Contrato de Adesão para concluir o cadastro.';
    } else {
        $check = $conn->prepare("SELECT id FROM usuarios WHERE cpf = ?");
        $check->bind_param('s', $cpfMascarado);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $erro = 'CPF já cadastrado. Faça o login.';
        } else {
            $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, cpf, whatsapp, rg, nascimento, cep, cidade, endereco, afiliado_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssssssss', $nome, $email, $cpfMascarado, $whatsapp, $rg, $nascimento, $cep, $cidade, $endereco, $afiliadoToken);
            if ($stmt->execute()) {
                registrar_aceite_contrato($conn, 'usuarios', $conn->insert_id);
                $_SESSION['usuario_id'] = $conn->insert_id;
                if ($email !== '') {
                    enviar_template_geral('bemvindo', ['nome' => $nome, 'email' => $email]);
                }
                header('Location: dashboard.php');
                exit;
            } else {
                $erro = 'Erro ao salvar o cadastro. Tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>

<html lang="pt-BR"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Economic Card - Criar Conta</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Hanken+Grotesk:wght@600;700&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@100..900&family=Manrope:wght@100..900&display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "secondary-container": "#b6f570",
                        "surface": "#f8f9fa",
                        "error-container": "#ffdad6",
                        "secondary-fixed": "#b6f570",
                        "on-tertiary-fixed": "#2e1600",
                        "on-secondary-fixed-variant": "#2e5000",
                        "inverse-on-surface": "#f0f1f2",
                        "background": "#f8f9fa",
                        "surface-bright": "#f8f9fa",
                        "on-tertiary-fixed-variant": "#6c3b00",
                        "primary-fixed-dim": "#edb1ff",
                        "on-primary-container": "#e397fd",
                        "error": "#ba1a1a",
                        "tertiary-fixed": "#ffdcc0",
                        "on-secondary-fixed": "#0f2000",
                        "surface-container-high": "#e7e8e9",
                        "surface-container-lowest": "#ffffff",
                        "outline-variant": "#d1c2d1",
                        "tertiary-fixed-dim": "#ffb877",
                        "inverse-surface": "#2e3132",
                        "on-surface-variant": "#4e434f",
                        "tertiary-container": "#6a3a00",
                        "primary-fixed": "#f9d8ff",
                        "tertiary": "#4a2700",
                        "surface-container": "#edeeef",
                        "on-primary-fixed": "#320046",
                        "inverse-primary": "#edb1ff",
                        "on-error": "#ffffff",
                        "on-tertiary": "#ffffff",
                        "on-secondary-container": "#427000",
                        "primary-container": "#6a2585",
                        "primary": "#51036d",
                        "on-background": "#191c1d",
                        "on-secondary": "#ffffff",
                        "surface-tint": "#8540a0",
                        "on-surface": "#191c1d",
                        "secondary-fixed-dim": "#9cd857",
                        "on-primary-fixed-variant": "#6b2686",
                        "on-error-container": "#93000a",
                        "secondary": "#3e6a00",
                        "surface-container-low": "#f3f4f5",
                        "on-primary": "#ffffff",
                        "on-tertiary-container": "#ff9d35",
                        "surface-variant": "#e1e3e4",
                        "outline": "#7f7381",
                        "surface-dim": "#d9dadb",
                        "surface-container-highest": "#e1e3e4"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "xs": "4px",
                        "lg": "32px",
                        "base": "8px",
                        "sm": "12px",
                        "container-padding": "20px",
                        "xl": "48px",
                        "card-gutter": "16px",
                        "md": "24px"
                    },
                    "fontFamily": {
                        "headline-sm": ["Manrope"],
                        "body-lg": ["Manrope"],
                        "display-lg": ["Manrope"],
                        "body-md": ["Manrope"],
                        "headline-md": ["Manrope"],
                        "label-caps": ["Hanken Grotesk"],
                        "label-bold": ["Hanken Grotesk"]
                    },
                    "fontSize": {
                        "headline-sm": ["20px", {"lineHeight": "1.4", "fontWeight": "600"}],
                        "headline-sm-mobile": ["18px", {"lineHeight": "1.4", "fontWeight": "600"}],
                        "body-lg": ["16px", {"lineHeight": "1.6", "fontWeight": "400"}],
                        "display-lg": ["32px", {"lineHeight": "1.2", "fontWeight": "800"}],
                        "display-lg-mobile": ["28px", {"lineHeight": "1.2", "fontWeight": "800"}],
                        "body-md": ["14px", {"lineHeight": "1.5", "fontWeight": "400"}],
                        "headline-md": ["24px", {"lineHeight": "1.3", "fontWeight": "700"}],
                        "label-caps": ["10px", {"lineHeight": "1", "fontWeight": "600"}],
                        "label-bold": ["12px", {"lineHeight": "1", "letterSpacing": "0.05em", "fontWeight": "700"}]
                    }
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .glass-header {
            background: rgba(248, 249, 250, 0.8);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        .input-focus-ring:focus-within {
            box-shadow: 0 0 0 2px rgba(81, 3, 109, 0.1);
        }
    </style>
</head>
<body class="bg-surface text-on-surface min-h-screen flex flex-col font-body-md">
<header class="fixed top-0 left-0 w-full z-50 glass-header flex items-center px-container-padding h-16">
<a class="text-primary hover:opacity-80 transition-opacity active:scale-95 transition-transform mr-4" href="login.php">
<span class="material-symbols-outlined">arrow_back</span>
</a>
<h1 class="font-headline-sm text-headline-sm-mobile md:text-headline-sm text-primary">Criar Conta</h1>
</header>
<main class="flex-grow pt-24 pb-32 px-container-padding max-w-md mx-auto w-full">
<div class="flex flex-col items-center mb-lg">
<?php if ($logoApp): ?>
<img class="w-[150px] h-auto object-contain mb-xs" src="<?php echo htmlspecialchars($logoApp); ?>" alt="Logo Economic Card"/>
<?php endif; ?>
<p class="font-body-md text-on-surface-variant text-center px-4">
                Faça seu cadastro e tenha descontos e benefícios exclusivos.
            </p>
</div>
<?php if ($sucesso): ?>
<div class="mb-md p-md rounded-xl bg-secondary-container/30 border border-secondary/30 text-center">
<p class="font-headline-sm text-on-secondary-container mb-base">Cadastro realizado com sucesso!</p>
<a class="inline-block w-full bg-primary text-on-primary font-headline-sm py-4 rounded-full text-center" href="login.php">Ir para o Login</a>
</div>
<?php else: ?>
<?php if ($erro): ?>
<div class="mb-md p-md rounded-xl bg-error-container border border-error/30 text-on-error-container font-body-md">
<?php echo htmlspecialchars($erro); ?>
</div>
<?php endif; ?>
<form class="space-y-card-gutter" id="registrationForm" method="POST" action="cadastro.php">
<?php if ($afiliadoToken): ?>
<input type="hidden" name="afiliado" value="<?php echo htmlspecialchars($afiliadoToken); ?>"/>
<?php endif; ?>
<div class="relative group">
<label class="block font-label-caps text-label-caps text-outline mb-1 ml-1 uppercase">Nome Completo</label>
<div class="relative flex items-center bg-surface-container-low rounded-xl border border-outline-variant focus-within:border-primary focus-within:bg-white transition-all input-focus-ring overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant ml-4">person</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-4 px-3 font-body-md text-on-surface placeholder:text-outline/60" name="nome" placeholder="Seu nome completo" type="text" required/>
</div>
</div>
<div class="relative group">
<label class="block font-label-caps text-label-caps text-outline mb-1 ml-1 uppercase">E-mail</label>
<div class="relative flex items-center bg-surface-container-low rounded-xl border border-outline-variant focus-within:border-primary focus-within:bg-white transition-all input-focus-ring overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant ml-4">mail</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-4 px-3 font-body-md text-on-surface placeholder:text-outline/60" name="email" placeholder="seuemail@exemplo.com" type="email" required/>
</div>
</div>
<div class="grid grid-cols-2 gap-card-gutter">
<div class="relative group">
<label class="block font-label-caps text-label-caps text-outline mb-1 ml-1 uppercase">CPF</label>
<div class="relative flex items-center bg-surface-container-low rounded-xl border border-outline-variant focus-within:border-primary focus-within:bg-white transition-all input-focus-ring overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant ml-4">badge</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-4 px-3 font-body-md text-on-surface placeholder:text-outline/60" id="cpf" name="cpf" placeholder="000.000.000-00" type="text" required/>
</div>
</div>
<div class="relative group">
<label class="block font-label-caps text-label-caps text-outline mb-1 ml-1 uppercase">WhatsApp</label>
<div class="relative flex items-center bg-surface-container-low rounded-xl border border-outline-variant focus-within:border-primary focus-within:bg-white transition-all input-focus-ring overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant ml-4">chat</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-4 px-3 font-body-md text-on-surface placeholder:text-outline/60" id="whatsapp" name="whatsapp" placeholder="(00) 00000-0000" type="tel"/>
</div>
</div>
</div>
<div class="grid grid-cols-2 gap-card-gutter">
<div class="relative group">
<label class="block font-label-caps text-label-caps text-outline mb-1 ml-1 uppercase">RG</label>
<div class="relative flex items-center bg-surface-container-low rounded-xl border border-outline-variant focus-within:border-primary focus-within:bg-white transition-all input-focus-ring overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant ml-4">fingerprint</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-4 px-3 font-body-md text-on-surface placeholder:text-outline/60" id="rg" name="rg" placeholder="00.000.000-0" type="text"/>
</div>
</div>
<div class="relative group">
<label class="block font-label-caps text-label-caps text-outline mb-1 ml-1 uppercase">Nascimento</label>
<div class="relative flex items-center bg-surface-container-low rounded-xl border border-outline-variant focus-within:border-primary focus-within:bg-white transition-all input-focus-ring overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant ml-4">calendar_today</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-4 px-3 font-body-md text-on-surface placeholder:text-outline/60" name="nascimento" type="date" required/>
</div>
</div>
</div>
<div class="relative group">
<label class="block font-label-caps text-label-caps text-outline mb-1 ml-1 uppercase">CEP</label>
<div class="relative flex items-center bg-surface-container-low rounded-xl border border-outline-variant focus-within:border-primary focus-within:bg-white transition-all input-focus-ring overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant ml-4">location_on</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-4 px-3 font-body-md text-on-surface placeholder:text-outline/60" id="cep" name="cep" placeholder="00000-000" type="text"/>
</div>
</div>
<div class="relative group">
<label class="block font-label-caps text-label-caps text-outline mb-1 ml-1 uppercase">Cidade</label>
<div class="relative flex items-center bg-surface-container-low rounded-xl border border-outline-variant focus-within:border-primary focus-within:bg-white transition-all input-focus-ring overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant ml-4">apartment</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-4 px-3 font-body-md text-on-surface placeholder:text-outline/60" name="cidade" placeholder="Sua cidade" type="text"/>
</div>
</div>
<div class="relative group">
<label class="block font-label-caps text-label-caps text-outline mb-1 ml-1 uppercase">Endereço Completo</label>
<div class="relative flex items-center bg-surface-container-low rounded-xl border border-outline-variant focus-within:border-primary focus-within:bg-white transition-all input-focus-ring overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant ml-4">home</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-4 px-3 font-body-md text-on-surface placeholder:text-outline/60" name="endereco" placeholder="Rua, número e complemento" type="text"/>
</div>
</div>
<div class="relative group">
<label class="block font-label-caps text-label-caps text-outline mb-1 ml-1 uppercase">Código Colaborador (opcional)</label>
<div class="relative flex items-center bg-surface-container-low rounded-xl border border-outline-variant focus-within:border-primary focus-within:bg-white transition-all input-focus-ring overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant ml-4">group</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-4 px-3 font-body-md text-on-surface placeholder:text-outline/60" id="codigoColaborador" name="codigo_colaborador" placeholder="0000" type="text" maxlength="4" inputmode="numeric"/>
</div>
</div>
<div class="pt-base">
<label class="flex items-start gap-3 cursor-pointer select-none">
<input type="checkbox" id="aceiteContrato" name="aceite_contrato" value="1" class="mt-1 w-5 h-5 accent-[#3e6a00]" required/>
<span class="font-body-md text-on-surface-variant">
Li e aceito o
<button type="button" onclick="abrirContrato()" class="text-primary font-bold underline ml-1">Contrato de Adesão Economic Card</button>
</span>
</label>
</div>
<div class="pt-base">
<button class="w-full bg-secondary text-white font-headline-sm py-4 rounded-full shadow-[0_8px_30px_rgba(62,106,0,0.15)] active:scale-95 transition-all duration-200 uppercase flex items-center justify-center gap-2" id="submitBtn" type="submit">
                    CADASTRAR
                    <span class="material-symbols-outlined">how_to_reg</span>
</button>
</div>
</form>
<?php endif; ?>
<div class="text-center mt-lg">
<p class="font-body-md text-on-surface-variant">
                Já tem uma conta? 
                <a class="text-primary font-bold hover:underline" href="login.php">Entrar</a>
</p>
</div>
<a href="#" onclick="event.preventDefault(); abrirContrato(); return false;" class="flex items-center justify-center mt-md text-outline hover:text-primary transition-colors cursor-pointer group">
<span class="material-symbols-outlined mr-2">download</span>
<span class="font-label-bold uppercase">Baixar contrato de uso do cartão</span>
</a>
</main>
<div id="modalContrato" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-black/60" onclick="fecharContrato()"></div>
<div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl h-[85vh] overflow-hidden flex flex-col">
<div class="bg-[#51036d] px-6 py-4 flex items-center justify-between shrink-0">
<div class="flex items-center gap-3">
<div class="w-9 h-9 rounded-xl bg-white/15 flex items-center justify-center"><span class="material-symbols-outlined text-white">description</span></div>
<h2 class="text-white font-bold">Contrato de Adesão Economic Card</h2>
</div>
<button onclick="fecharContrato()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<div class="shrink-0 border-b border-gray-200 bg-gray-50 px-6 py-3 grid grid-cols-2 gap-x-4 gap-y-1 text-[13px]">
<div><span class="text-gray-500 font-semibold">Nome completo:</span> <span id="ctrNome" class="text-gray-800 font-medium">—</span></div>
<div><span class="text-gray-500 font-semibold">CPF:</span> <span id="ctrCpf" class="text-gray-800 font-medium">—</span></div>
<div><span class="text-gray-500 font-semibold">Telefone/WhatsApp:</span> <span id="ctrWhats" class="text-gray-800 font-medium">—</span></div>
<div><span class="text-gray-500 font-semibold">E-mail:</span> <span id="ctrEmail" class="text-gray-800 font-medium">—</span></div>
<div class="col-span-2"><span class="text-gray-500 font-semibold">Endereço:</span> <span id="ctrEnd" class="text-gray-800 font-medium">—</span></div>
</div>
<iframe id="modalContratoFrame" src="Contrato_de_Adesao_Economic_Card.pdf" class="flex-1 w-full bg-gray-100" type="application/pdf"></iframe>
<div class="shrink-0 border-t border-gray-200 p-4 flex items-center justify-between gap-4 bg-white">
<div class="flex items-center gap-2 text-sm text-gray-700">
<span class="material-symbols-outlined text-[#3e6a00]">verified_user</span>
<span>Clique em <b>Aceitar</b> para concordar com os termos.</span>
</div>
<div class="flex items-center gap-2">
<a id="ctrBaixar" href="gerar_contrato.php" target="_blank" class="shrink-0 border border-[#51036d] text-[#51036d] font-bold px-4 py-2.5 rounded-lg transition hover:bg-[#51036d]/5 flex items-center gap-2">
<span class="material-symbols-outlined text-[18px]">download</span> PDF
</a>
<button onclick="aceitarContrato()" class="shrink-0 bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-2.5 rounded-lg transition flex items-center gap-2">
<span class="material-symbols-outlined text-[18px]">check</span> ACEITAR CONTRATO
</button>
</div>
</div>
</div>
</div>
<footer class="w-full px-container-padding py-8 flex flex-col items-center gap-4 bg-surface-container-low mt-auto">
<p class="text-outline text-[10px] font-label-caps opacity-60">
            © 2024 ECONOMIC CARD. TODOS OS DIREITOS RESERVADOS.
        </p>
</footer>
<script>
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

        const whatsappInput = document.getElementById('whatsapp');
        whatsappInput.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            if (value.length > 10) {
                value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            } else if (value.length > 6) {
                value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
            } else if (value.length > 2) {
                value = value.replace(/(\d{2})(\d{1,4})/, '($1) $2');
            }
            e.target.value = value;
        });

        const cepInput = document.getElementById('cep');
        cepInput.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 8) value = value.slice(0, 8);
            if (value.length > 5) {
                value = value.replace(/(\d{5})(\d{3})/, '$1-$2');
            }
            e.target.value = value;
        });

        const rgInput = document.getElementById('rg');
        rgInput.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 9) value = value.slice(0, 9);
            e.target.value = value;
        });

        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('focus', () => {
                input.parentElement.classList.add('shadow-md');
            });
            input.addEventListener('blur', () => {
                input.parentElement.classList.remove('shadow-md');
            });
        });

        const aceiteContrato = document.getElementById('aceiteContrato');
        function dadosContrato() {
            const f = document.getElementById('registrationForm');
            const v = (n) => f.querySelector(`[name="${n}"]`)?.value ?? '';
            const cpf = v('cpf').replace(/\D/g, '');
            const cpfFmt = cpf.length === 11 ? cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : v('cpf');
            const end = [v('endereco'), v('cidade'), v('cep') ? 'CEP ' + v('cep') : ''].filter(Boolean).join(' — ');
            return {
                nome: v('nome'),
                cpf: cpfFmt,
                whats: v('whatsapp'),
                email: v('email'),
                end: end,
                query: new URLSearchParams({
                    preview: '1',
                    nome: v('nome'),
                    cpf: v('cpf'),
                    email: v('email'),
                    whatsapp: v('whatsapp'),
                    endereco: v('endereco'),
                    cidade: v('cidade'),
                    cep: v('cep')
                }).toString()
            };
        }
        function abrirContrato() {
            const d = dadosContrato();
            document.getElementById('ctrNome').textContent = d.nome || '—';
            document.getElementById('ctrCpf').textContent = d.cpf || '—';
            document.getElementById('ctrWhats').textContent = d.whats || '—';
            document.getElementById('ctrEmail').textContent = d.email || '—';
            document.getElementById('ctrEnd').textContent = d.end || '—';
            document.getElementById('ctrBaixar').href = 'gerar_contrato.php?' + d.query + '&download=1';
            document.getElementById('modalContratoFrame').src = 'gerar_contrato.php?' + d.query;
            document.getElementById('modalContrato').classList.remove('hidden');
            document.getElementById('modalContrato').classList.add('flex');
        }
        function fecharContrato() {
            document.getElementById('modalContrato').classList.add('hidden');
            document.getElementById('modalContrato').classList.remove('flex');
        }
        function aceitarContrato() {
            aceiteContrato.checked = true;
            fecharContrato();
        }
    </script>
</body></html>
