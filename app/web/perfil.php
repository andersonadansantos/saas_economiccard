<?php
$paginaAtiva = 'perfil';
$tituloPagina = 'Perfil';
require_once __DIR__ . '/_head.php';

$salvo = false;
$avErro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'encerrar_conta') {
    $uid = (int)$_SESSION['usuario_id'];
    $stmt = $conn->prepare("UPDATE usuarios SET status = 'desativado', cartao_ativo = 0 WHERE id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    session_unset();
    session_destroy();
    header('Location: login.php?conta_encerrada=1');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cpf  = trim($_POST['cpf'] ?? '');
    $rg   = trim($_POST['rg'] ?? '');
    $nasc = trim($_POST['nascimento'] ?? '');
    $end  = trim($_POST['endereco'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $cep     = trim($_POST['cep'] ?? '');
    $whats   = trim($_POST['whatsapp'] ?? '');
    $avatar  = trim($_POST['avatar_atual'] ?? '');
    if (isset($_FILES['avatar']) && is_array($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $tmp  = $_FILES['avatar']['tmp_name'];
        $info = @getimagesize($tmp);
        $exts = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if ($info !== false && isset($exts[$info['mime']])) {
            $dirUpload = dirname(__DIR__) . '/uploads';
            if (!is_dir($dirUpload)) { @mkdir($dirUpload, 0777, true); }
            $nomeArq = 'avatar_' . $uid . '_' . time() . '.' . $exts[$info['mime']];
            if (move_uploaded_file($tmp, $dirUpload . '/' . $nomeArq)) {
                $avatar = 'uploads/' . $nomeArq;
            } else {
                $avErro = 'Não foi possível salvar a foto. Verifique a permissão da pasta uploads.';
            }
        } else {
            $avErro = 'Formato de imagem inválido. Envie uma foto PNG, JPG, WEBP ou GIF.';
        }
    } elseif (isset($_FILES['avatar']) && is_array($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $avErro = 'Falha no envio da foto (código ' . (int)$_FILES['avatar']['error'] . '). Tente novamente com uma imagem menor.';
    }
    if (isset($_POST['remover_avatar'])) { $avatar = ''; }
    if ($nome !== '') {
        $stmt = $conn->prepare("UPDATE usuarios SET nome=?, email=?, cpf=?, rg=?, nascimento=?, endereco=?, cidade=?, cep=?, whatsapp=?, avatar=? WHERE id=?");
        $stmt->bind_param('ssssssssssi', $nome, $email, $cpf, $rg, $nasc, $end, $cidade, $cep, $whats, $avatar, $uid);
        $stmt->execute();
        $salvo = true;
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
    }
}
?>
<div class="max-w-3xl">
<section class="bg-white rounded-2xl card-shadow p-8 flex flex-col sm:flex-row items-center gap-6 mb-8">
<div class="relative group">
<div class="w-28 h-28 rounded-full overflow-hidden border-4 border-white card-shadow bg-surface-variant/50 flex items-center justify-center">
<?php if ($u['avatar']): ?>
<img class="w-full h-full object-cover" src="<?php echo webUrl($u['avatar']); ?>" alt="Foto de perfil"/>
<?php else: ?>
<span class="material-symbols-outlined text-primary text-[48px]" style="font-variation-settings: 'FILL' 1;">person</span>
<?php endif; ?>
</div>
<button type="button" class="absolute bottom-1 right-1 bg-primary text-white w-10 h-10 rounded-full flex items-center justify-center shadow-lg border-2 border-white hover:scale-110 transition-transform" onclick="document.getElementById('avatarInput').click()">
<span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">edit</span>
</button>
</div>
<div class="text-center sm:text-left">
<h3 class="text-xl font-extrabold text-on-surface"><?php echo htmlspecialchars($u['nome']); ?></h3>
<p class="text-xs font-bold text-on-surface-variant tracking-wider uppercase mt-0.5">Usuário do Cartão</p>
<p class="text-sm text-on-surface-variant mt-2">Mantenha seus dados atualizados para liberar todos os benefícios.</p>
</div>
</section>

<?php if ($salvo): ?>
<div class="mb-6 p-4 rounded-xl bg-secondary-container/30 border border-secondary/30 text-secondary font-bold text-center">Dados salvos com sucesso!</div>
<?php endif; ?>
<?php if ($avErro !== ''): ?>
<div class="mb-6 p-4 rounded-xl bg-error-container/30 border border-error/30 text-[#93000a] font-bold text-center"><?php echo htmlspecialchars($avErro); ?></div>
<?php endif; ?>

<form class="bg-white rounded-2xl card-shadow p-8 space-y-5" method="POST" action="perfil.php" enctype="multipart/form-data">
<input type="hidden" name="avatar_atual" value="<?php echo htmlspecialchars($u['avatar'] ?? ''); ?>"/>
<input type="file" id="avatarInput" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden"/>
<?php if ($u['avatar']): ?>
<label class="flex items-center gap-2 justify-center text-sm font-semibold text-[#ba1a1a] cursor-pointer">
<input type="checkbox" name="remover_avatar" class="w-4 h-4 accent-[#ba1a1a]"> Remover foto de perfil
</label>
<?php endif; ?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">NOME COMPLETO</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/50 text-[20px]">person</span>
<input class="w-full bg-surface-variant/50 border border-outline-variant rounded-xl py-3.5 pl-12 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-primary transition-all" name="nome" type="text" value="<?php echo htmlspecialchars($u['nome']); ?>" required/>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">E-MAIL</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/50 text-[20px]">mail</span>
<input class="w-full bg-surface-variant/50 border border-outline-variant rounded-xl py-3.5 pl-12 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-primary transition-all" name="email" type="email" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>"/>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">CPF</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/50 text-[20px]">badge</span>
<input class="w-full bg-surface-variant/50 border border-outline-variant rounded-xl py-3.5 pl-12 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-primary transition-all" id="cpf" name="cpf" type="text" value="<?php echo htmlspecialchars($u['cpf']); ?>" required/>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">RG</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/50 text-[20px]">fingerprint</span>
<input class="w-full bg-surface-variant/50 border border-outline-variant rounded-xl py-3.5 pl-12 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-primary transition-all" id="rg" name="rg" type="text" value="<?php echo htmlspecialchars($u['rg'] ?? ''); ?>"/>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">DATA DE NASC.</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/50 text-[20px]">calendar_month</span>
<input class="w-full bg-surface-variant/50 border border-outline-variant rounded-xl py-3.5 pl-12 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-primary transition-all" name="nascimento" type="date" value="<?php echo htmlspecialchars($u['nascimento'] ?? ''); ?>"/>
</div>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">ENDEREÇO COMPLETO</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/50 text-[20px]">location_on</span>
<input class="w-full bg-surface-variant/50 border border-outline-variant rounded-xl py-3.5 pl-12 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-primary transition-all" name="endereco" type="text" value="<?php echo htmlspecialchars($u['endereco'] ?? ''); ?>"/>
</div>
</div>
<div class="grid grid-cols-2 gap-5">
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">CIDADE</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/50 text-[20px]">map</span>
<input class="w-full bg-surface-variant/50 border border-outline-variant rounded-xl py-3.5 pl-12 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-primary transition-all" name="cidade" type="text" value="<?php echo htmlspecialchars($u['cidade'] ?? ''); ?>"/>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">CEP</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/50 text-[20px]">explore</span>
<input class="w-full bg-surface-variant/50 border border-outline-variant rounded-xl py-3.5 pl-12 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-primary transition-all" id="cep" name="cep" type="text" value="<?php echo htmlspecialchars($u['cep'] ?? ''); ?>"/>
</div>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">WHATSAPP</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/50 text-[20px]">call</span>
<input class="w-full bg-surface-variant/50 border border-outline-variant rounded-xl py-3.5 pl-12 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-primary transition-all" id="whatsapp" name="whatsapp" type="tel" value="<?php echo htmlspecialchars($u['whatsapp'] ?? ''); ?>"/>
</div>
</div>
<div class="flex flex-col sm:flex-row gap-4 pt-2">
<button class="flex-1 bg-secondary hover:brightness-110 text-white font-bold py-4 rounded-xl shadow-lg transition flex items-center justify-center gap-2" type="submit">
SALVAR <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">save</span>
</button>
<button class="flex-1 flex items-center justify-center gap-2 font-bold text-primary py-4 rounded-xl border border-outline-variant hover:bg-surface-variant/50 transition" type="button" onclick="window.open('<?php echo htmlspecialchars(webUrl('gerar_contrato.php')); ?>','_blank')">
<span class="material-symbols-outlined text-[18px]">download</span> BAIXAR CONTRATO DE USO DO CARTÃO
</button>
</div>
</form>

<section class="bg-white rounded-2xl card-shadow p-6 mt-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border border-error/20">
<div class="flex items-start gap-3">
<div class="w-10 h-10 rounded-full bg-error/10 text-error flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[20px]">delete_forever</span></div>
<div>
<h3 class="font-bold text-error text-sm">Encerrar minha conta</h3>
<p class="text-sm text-on-surface-variant mt-1">Desativa seu acesso ao cartão e ao Economic Card. Você pode ser reativado pela administração caso necessário.</p>
</div>
</div>
<button type="button" onclick="abrirEncerrarConta()" class="shrink-0 inline-flex items-center justify-center gap-2 font-bold text-error py-3 px-5 rounded-xl border border-error/40 hover:bg-error/5 transition text-sm">
<span class="material-symbols-outlined text-[18px]">logout</span> Encerrar conta
</button>
</section>
</div>
<script>
const cpfInput = document.getElementById('cpf');
if (cpfInput) cpfInput.addEventListener('input', (e) => {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 11) value = value.slice(0, 11);
    if (value.length > 9) value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    else if (value.length > 6) value = value.replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
    else if (value.length > 3) value = value.replace(/(\d{3})(\d{3})/, '$1.$2');
    e.target.value = value;
});
const cepInput = document.getElementById('cep');
if (cepInput) cepInput.addEventListener('input', (e) => { let value = e.target.value.replace(/\D/g, ''); if (value.length > 8) value = value.slice(0,8); if (value.length > 5) value = value.replace(/(\d{5})(\d{3})/, '$1-$2'); e.target.value = value; });
const avatarInput = document.getElementById('avatarInput');
if (avatarInput) avatarInput.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const img = new Image();
    img.onload = function() { img.classList.add('w-full', 'h-full', 'object-cover'); };
    img.src = URL.createObjectURL(file);
const wrapper = document.querySelector('.w-28.h-28');
if (wrapper) { wrapper.innerHTML = ''; wrapper.appendChild(img); }
});
function abrirEncerrarConta() {
    const el = document.getElementById('encerrarContaModal');
    el.classList.remove('hidden'); el.classList.add('flex');
}
function fecharEncerrarConta() {
    const el = document.getElementById('encerrarContaModal');
    el.classList.add('hidden'); el.classList.remove('flex');
}
document.addEventListener('click', (e) => {
    const el = document.getElementById('encerrarContaModal');
    if (el && e.target === el) fecharEncerrarConta();
});
</script>
<!-- Encerrar conta modal -->
<div class="hidden fixed inset-0 z-[95] bg-black/60 backdrop-blur-sm items-center justify-center p-6" id="encerrarContaModal">
<div class="w-full max-w-md bg-white rounded-2xl shadow-2xl border border-outline-variant overflow-hidden">
<div class="px-5 py-4 border-b border-outline-variant flex items-center justify-between gap-3">
<div class="flex items-center gap-2 min-w-0">
<span class="material-symbols-outlined text-error text-[20px] shrink-0" style="font-variation-settings: 'FILL' 1;">delete_forever</span>
<span class="font-bold text-sm text-on-surface uppercase truncate">Encerrar conta</span>
</div>
<button class="material-symbols-outlined text-on-surface-variant hover:bg-surface-variant/50 p-1.5 rounded-full shrink-0" onclick="fecharEncerrarConta()">close</button>
</div>
<div class="px-5 py-5">
<p class="text-sm text-on-surface-variant">Tem certeza que deseja <b class="text-error">encerrar sua conta</b> no Economic Card?</p>
<p class="text-sm text-on-surface-variant mt-2">Seu acesso ao cartão e aos benefícios será <b>desativado</b>. Esta ação pode ser revertida pela administração.</p>
</div>
<div class="px-5 py-4 border-t border-outline-variant flex items-center justify-end gap-3">
<button class="text-sm font-bold text-on-surface-variant hover:opacity-70 transition px-4 py-2" onclick="fecharEncerrarConta()">Cancelar</button>
<form method="POST" action="perfil.php">
<input type="hidden" name="acao" value="encerrar_conta"/>
<button class="inline-flex items-center gap-2 text-sm font-bold text-white bg-error hover:bg-red-700 px-5 py-2.5 rounded-xl transition" type="submit">
<span class="material-symbols-outlined text-[18px]">logout</span> Sim, encerrar
</button>
</form>
</div>
</div>
</div>
<?php require_once __DIR__ . '/_foot.php'; ?>
