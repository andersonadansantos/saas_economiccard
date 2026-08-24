<?php
$paginaAtiva = 'cartao_fisico';
$tituloPagina = 'Cartão Físico';
require_once __DIR__ . '/_head.php';

$pedido = false;
$cartaoImg = $pers['cartao_fisico'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'pedir') {
    $check = $conn->prepare("SELECT id FROM pedidos_cartao WHERE usuario_id = ?");
    $check->bind_param('i', $uid);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO pedidos_cartao (usuario_id) VALUES (?)");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
    }
    $pedido = true;
}
?>
<div class="max-w-3xl">
<section class="bg-white rounded-2xl card-shadow overflow-hidden">
<div class="px-6 py-4 border-b border-outline-variant flex items-center justify-between">
<h3 class="font-extrabold text-on-surface">Cartão Físico</h3>
<span class="px-2 py-0.5 bg-secondary-container text-secondary text-[9px] font-bold rounded">OPCIONAL</span>
</div>
<div class="p-6">
<div class="premium-gradient rounded-xl p-6 shadow-lg relative overflow-hidden aspect-[1.58/1] flex flex-col justify-between mb-8">
<div class="absolute -top-10 -right-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
<?php if ($cartaoImg): ?>
<img class="absolute inset-0 w-full h-full object-contain" src="<?php echo webUrl($cartaoImg); ?>" alt="Cartão Físico Economic Card"/>
<?php endif; ?>
<div class="flex justify-between items-start z-10">
<div class="space-y-1"></div>
<span class="px-3 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 bg-white/15 backdrop-blur-md text-white"><span class="w-1.5 h-1.5 rounded-full bg-secondary-container"></span> <?php echo $u['cartao_ativo'] ? 'ATIVO' : 'INATIVO'; ?></span>
</div>
<div class="z-10 mt-auto">
<p class="text-white text-xl font-extrabold"><?php echo htmlspecialchars(strtoupper($u['nome'])); ?></p>
</div>
</div>

<?php if ($pedido): ?>
<div class="mb-6 p-4 rounded-xl bg-secondary-container/30 border border-secondary/30 text-center">
<p class="font-bold text-secondary">Pedido do cartão físico realizado com sucesso!</p>
<p class="text-sm text-secondary mt-1">Você receberá seu cartão em breve.</p>
</div>
<?php endif; ?>

<form method="POST" action="cartao_fisico.php" class="space-y-5">
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">NOME COMPLETO</label>
<div class="relative flex items-center bg-surface-variant/50 rounded-xl border border-outline-variant overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant/50 ml-4">person</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-3.5 px-3 text-sm" value="<?php echo htmlspecialchars($u['nome']); ?>" readonly/>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">CPF</label>
<div class="relative flex items-center bg-surface-variant/50 rounded-xl border border-outline-variant overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant/50 ml-4">badge</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-3.5 px-3 text-sm" value="<?php echo htmlspecialchars($u['cpf']); ?>" readonly/>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">WHATSAPP</label>
<div class="relative flex items-center bg-surface-variant/50 rounded-xl border border-outline-variant overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant/50 ml-4">chat</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-3.5 px-3 text-sm" value="<?php echo htmlspecialchars($u['whatsapp'] ?: ''); ?>" readonly/>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">ENDEREÇO COMPLETO</label>
<div class="relative flex items-center bg-surface-variant/50 rounded-xl border border-outline-variant overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant/50 ml-4">home</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-3.5 px-3 text-sm" value="<?php echo htmlspecialchars($u['endereco'] ?: ''); ?>" readonly/>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">CIDADE</label>
<div class="relative flex items-center bg-surface-variant/50 rounded-xl border border-outline-variant overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant/50 ml-4">apartment</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-3.5 px-3 text-sm" value="<?php echo htmlspecialchars($u['cidade'] ?: ''); ?>" readonly/>
</div>
</div>
<div class="relative">
<label class="font-bold text-xs text-on-surface-variant mb-1.5 block">CEP</label>
<div class="relative flex items-center bg-surface-variant/50 rounded-xl border border-outline-variant overflow-hidden">
<span class="material-symbols-outlined text-on-surface-variant/50 ml-4">location_on</span>
<input class="w-full bg-transparent border-none focus:ring-0 py-3.5 px-3 text-sm" value="<?php echo htmlspecialchars($u['cep'] ?: ''); ?>" readonly/>
</div>
</div>
</div>
<div class="pt-2">
<input type="hidden" name="acao" value="pedir"/>
<button class="w-full bg-secondary hover:brightness-110 text-white font-bold py-4 rounded-xl shadow-lg transition uppercase flex items-center justify-center gap-2" type="submit">
Pedir Cartão Físico <span class="material-symbols-outlined">contactless</span>
</button>
</div>
</form>
<p class="text-center text-sm text-on-surface-variant mt-5">O Cartão físico é opcional.</p>
</div>
</section>
</div>
<?php require_once __DIR__ . '/_foot.php'; ?>
