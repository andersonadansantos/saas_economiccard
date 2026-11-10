<?php
require_once 'config.php';
require_once 'afiliado_creditos.php';
if (!($_SESSION['admin_logado'] ?? false)) {
    header('Location: admin_login.php');
    exit;
}

$erro = '';
$sucesso = '';

// Lê um campo de valor de comissão enviado pelo formulário:
// vazio -> null (usar o padrão global); número -> valor arredondado >= 0.
function afiliado_campo_override($valorCampo) {
    $v = trim((string)$valorCampo);
    if ($v === '') return null;
    $n = round((float)str_replace(',', '.', $v), 2);
    if ($n < 0) $n = 0.0;
    return $n;
}

// Lê um valor de comissão global: número >= 0 (vazio/zereado -> 0).
function afiliado_campo_global($valorCampo) {
    $n = round((float)str_replace(',', '.', (string)$valorCampo), 2);
    if ($n < 0) $n = 0.0;
    return $n;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'config_remuneracao') {
        // Configuração global da remuneração dos afiliados (vendedores):
        // valores padrão por parcela + ativar/desativar a remuneração automática.
        $valorAdesao = afiliado_campo_global($_POST['valor_adesao'] ?? '0');
        $valorP2 = afiliado_campo_global($_POST['valor_parcela2'] ?? '0');
        $valorP3 = afiliado_campo_global($_POST['valor_parcela3'] ?? '0');
        $valorP4 = afiliado_campo_global($_POST['valor_parcela4'] ?? '0');
        $remuneracaoAtiva = isset($_POST['remuneracao_ativa']) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE afiliado_config SET valor_adesao = ?, valor_parcela2 = ?, valor_parcela3 = ?, valor_parcela4 = ?, remuneracao_ativa = ? WHERE id = 1");
        $stmt->bind_param('ddddi', $valorAdesao, $valorP2, $valorP3, $valorP4, $remuneracaoAtiva);
        $stmt->execute();
        if ($stmt->affected_rows >= 0) {
            $sucesso = 'Configuração de remuneração salva.' . ($remuneracaoAtiva === 1 ? '' : ' A remuneração automática está DESATIVADA — nenhuma nova comissão será gerada.');
        } else {
            $erro = 'Não foi possível salvar a configuração de remuneração.';
        }
    } elseif ($acao === 'cadastrar') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $cpf = trim($_POST['cpf'] ?? '');
        $senha = $_POST['senha'] ?? '';
        // Valores individuais por parcela (null = usar o padrão global).
        $ovAdesao = afiliado_campo_override($_POST['valor_adesao'] ?? '');
        $ovP2 = afiliado_campo_override($_POST['valor_parcela2'] ?? '');
        $ovP3 = afiliado_campo_override($_POST['valor_parcela3'] ?? '');
        $ovP4 = afiliado_campo_override($_POST['valor_parcela4'] ?? '');
        $wallet = trim($_POST['wallet_afiliado'] ?? '');
        if ($nome === '' || $email === '' || $senha === '') {
            $erro = 'Preencha nome, e-mail e senha do vendedor.';
        } else {
            $check = $conn->prepare("SELECT id FROM afiliados WHERE email = ?");
            $check->bind_param('s', $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $erro = 'Já existe um afiliado com este e-mail.';
            } else {
                $token = bin2hex(random_bytes(16));
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                do {
                    $codigo = (string)random_int(1000, 9999);
                    $ck = $conn->prepare("SELECT id FROM afiliados WHERE codigo = ?");
                    $ck->bind_param('s', $codigo);
                    $ck->execute();
                } while ($ck->get_result()->num_rows > 0);
                $stmt = $conn->prepare("INSERT INTO afiliados (codigo, nome, email, telefone, cpf, senha, valor_fixo_mensalidade, valor_adesao, valor_parcela2, valor_parcela3, valor_parcela4, wallet_afiliado, token) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssssddddss', $codigo, $nome, $email, $telefone, $cpf, $hash, $ovAdesao, $ovP2, $ovP3, $ovP4, $wallet, $token);
                $stmt->execute();
                registrar_aceite_contrato($conn, 'afiliados', $conn->insert_id);
                $sucesso = 'Afiliado cadastrado! Código colaborador: ' . $codigo . '. Ele já pode acessar o painel em /card/afiliado.';
            }
        }
    } elseif ($acao === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $cpf = trim($_POST['cpf'] ?? '');
        $nascimento = trim($_POST['nascimento'] ?? '');
        if ($nascimento === '') { $nascimento = null; }
        // Valores individuais por parcela (vazio = voltar ao padrão global).
        $ovAdesao = afiliado_campo_override($_POST['valor_adesao'] ?? '');
        $ovP2 = afiliado_campo_override($_POST['valor_parcela2'] ?? '');
        $ovP3 = afiliado_campo_override($_POST['valor_parcela3'] ?? '');
        $ovP4 = afiliado_campo_override($_POST['valor_parcela4'] ?? '');
        $wallet = trim($_POST['wallet_afiliado'] ?? '');
        $senha = $_POST['senha'] ?? '';
        if ($nome === '' || $email === '') {
            $erro = 'Preencha nome e e-mail do afiliado.';
        } else {
            $check = $conn->prepare("SELECT id FROM afiliados WHERE email = ? AND id <> ?");
            $check->bind_param('si', $email, $id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $erro = 'Já existe outro afiliado com este e-mail.';
            } else {
                if ($senha !== '') {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE afiliados SET nome = ?, email = ?, telefone = ?, cpf = ?, nascimento = ?, valor_adesao = ?, valor_parcela2 = ?, valor_parcela3 = ?, valor_parcela4 = ?, wallet_afiliado = ?, senha = ? WHERE id = ?");
                    $stmt->bind_param('sssssddddssi', $nome, $email, $telefone, $cpf, $nascimento, $ovAdesao, $ovP2, $ovP3, $ovP4, $wallet, $hash, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE afiliados SET nome = ?, email = ?, telefone = ?, cpf = ?, nascimento = ?, valor_adesao = ?, valor_parcela2 = ?, valor_parcela3 = ?, valor_parcela4 = ?, wallet_afiliado = ? WHERE id = ?");
                    $stmt->bind_param('sssssddddsi', $nome, $email, $telefone, $cpf, $nascimento, $ovAdesao, $ovP2, $ovP3, $ovP4, $wallet, $id);
                }
                $stmt->execute();
                $sucesso = 'Afiliado atualizado com sucesso!';
            }
        }
    } elseif ($acao === 'alternar') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE afiliados SET ativo = 1 - ativo WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $sucesso = 'Status do afiliado alterado.';
    } elseif ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM afiliados WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $sucesso = 'Afiliado excluído.';
    } elseif ($acao === 'vincular_usuario') {
        // Vínculo manual: identifica um usuário já cadastrado como indicado
        // deste afiliado. A regra de remuneração por parcela passa a valer
        // para ele, inclusive retroativamente para as parcelas já pagas.
        $afiliadoId = (int)($_POST['afiliado_id'] ?? 0);
        $usuarioId = (int)($_POST['usuario_id'] ?? 0);
        $stA = $conn->prepare("SELECT id, nome, token, ativo FROM afiliados WHERE id = ? LIMIT 1");
        $stA->bind_param('i', $afiliadoId);
        $stA->execute();
        $afV = $stA->get_result()->fetch_assoc();
        $stU = $conn->prepare("SELECT id, nome, afiliado_token FROM usuarios WHERE id = ? LIMIT 1");
        $stU->bind_param('i', $usuarioId);
        $stU->execute();
        $usV = $stU->get_result()->fetch_assoc();
        if (!$afV) {
            $erro = 'Afiliado não encontrado.';
        } elseif (!$usV) {
            $erro = 'Usuário não encontrado.';
        } else {
            // Regra: o mesmo usuário nunca pode ficar com dois afiliados.
            // (Token de afiliado que não existe mais não conta como vínculo.)
            $vinculoAtual = null;
            if (!empty($usV['afiliado_token'])) {
                $stT = $conn->prepare("SELECT nome FROM afiliados WHERE token = ? LIMIT 1");
                $stT->bind_param('s', $usV['afiliado_token']);
                $stT->execute();
                $vinculoAtual = $stT->get_result()->fetch_assoc();
            }
            if ($vinculoAtual) {
                $erro = 'Este usuário já está vinculado ao afiliado ' . $vinculoAtual['nome'] . '. Desvincule-o antes, se for o caso.';
            } else {
                $stmt = $conn->prepare("UPDATE usuarios SET afiliado_token = ? WHERE id = ? AND (afiliado_token IS NULL OR afiliado_token = '' OR afiliado_token NOT IN (SELECT token FROM afiliados))");
                $stmt->bind_param('si', $afV['token'], $usuarioId);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $sucesso = 'Usuário ' . $usV['nome'] . ' vinculado ao afiliado ' . $afV['nome'] . '.';
                    if ((int)$afV['ativo'] === 1) {
                        $retro = afiliado_registrar_creditos_retroativos($conn, $usuarioId);
                        if ($retro > 0) {
                            $sucesso .= ' Foram gerados ' . $retro . ' crédito(s) retroativo(s) referente(s) às parcelas já pagas.';
                        }
                    } else {
                        $sucesso .= ' Atenção: o afiliado está inativo — parcelas pagas só geram comissão quando ele estiver ativo.';
                    }
                } else {
                    $erro = 'Não foi possível vincular o usuário (ele pode ter acabado de ser vinculado a outro afiliado).';
                }
            }
        }
    } elseif ($acao === 'desvincular_usuario') {
        // Correção administrativa: remove o vínculo. Os créditos já gerados
        // permanecem no histórico; novas parcelas deixam de gerar comissão.
        $afiliadoId = (int)($_POST['afiliado_id'] ?? 0);
        $usuarioId = (int)($_POST['usuario_id'] ?? 0);
        $stA = $conn->prepare("SELECT id, nome, token FROM afiliados WHERE id = ? LIMIT 1");
        $stA->bind_param('i', $afiliadoId);
        $stA->execute();
        $afD = $stA->get_result()->fetch_assoc();
        if (!$afD) {
            $erro = 'Afiliado não encontrado.';
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET afiliado_token = NULL WHERE id = ? AND afiliado_token = ?");
            $stmt->bind_param('is', $usuarioId, $afD['token']);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $sucesso = 'Usuário desvinculado do afiliado ' . $afD['nome'] . '. Os créditos já gerados permanecem no histórico; novas parcelas deixarão de gerar comissão.';
            } else {
                $erro = 'Este usuário não estava vinculado a este afiliado.';
            }
        }
    } elseif ($acao === 'marcar_pago') {
        // Registro de valores JÁ PAGOS ao vendedor: marca todos os créditos
        // ativos dele como pagos (status='pago' + data). O histórico é
        // preservado; o saldo disponível passa a considerar apenas os novos
        // créditos gerados depois disso.
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("SELECT id, nome FROM afiliados WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $afPagoAcao = $stmt->get_result()->fetch_assoc();
        if (!$afPagoAcao) {
            $erro = 'Afiliado não encontrado.';
        } else {
            $qtd = afiliado_marcar_creditos_pagos($conn, $id);
            if ($qtd > 0) {
                $sucesso = $qtd . ' crédito(s) de ' . $afPagoAcao['nome'] . ' marcado(s) como pago(s) ao vendedor.';
            } else {
                $erro = 'Não há créditos ativos para marcar como pagos para ' . $afPagoAcao['nome'] . '.';
            }
        }
    } elseif ($acao === 'estornar_credito') {
        // Estorno MANUAL de um crédito individual, com motivo obrigatório
        // (registrado junto com a data). O histórico original é preservido.
        $creditoId = (int)($_POST['credito_id'] ?? 0);
        $motivo = trim((string)($_POST['motivo'] ?? ''));
        if ($creditoId <= 0 || $motivo === '') {
            $erro = 'Informe o motivo do estorno do crédito.';
        } else {
            if (afiliado_estornar_credito_manual($conn, $creditoId, $motivo)) {
                $sucesso = 'Crédito #' . $creditoId . ' estornado. Motivo registrado: ' . htmlspecialchars($motivo);
            } else {
                $erro = 'Não foi possível estornar o crédito #' . $creditoId . ' (ele pode já estar estornado).';
            }
        }
    } elseif ($acao === 'logar') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("SELECT id, nome FROM afiliados WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $afLogar = $stmt->get_result()->fetch_assoc();
        if ($afLogar) {
            $_SESSION['afiliado_id'] = (int)$afLogar['id'];
            $_SESSION['afiliado_nome'] = $afLogar['nome'];
            header('Location: afiliado/painel.php');
            exit;
        }
    }
}

$afiliados = $conn->query("SELECT a.*,
        (SELECT COUNT(*) FROM usuarios u WHERE u.afiliado_token = a.token) AS indicados,
        (SELECT COUNT(*) FROM usuarios u WHERE u.afiliado_token = a.token AND u.cartao_ativo = 1) AS ativos,
        (SELECT COUNT(*) FROM creditos_afiliado c WHERE c.afiliado_id = a.id AND c.status IN ('ativo','pago')) AS creditos_validos,
        (SELECT COUNT(*) FROM creditos_afiliado c WHERE c.afiliado_id = a.id AND c.status IN ('ativo','pago') AND (c.parcela = 1 OR (c.parcela = 0 AND c.tipo_cobranca = 'adesao'))) AS adesoes,
        (SELECT COUNT(*) FROM creditos_afiliado c WHERE c.afiliado_id = a.id AND c.status IN ('ativo','pago') AND ((c.parcela BETWEEN 2 AND 4) OR (c.parcela = 0 AND c.tipo_cobranca IN ('1a_mensalidade','recorrencia')))) AS parcelas,
        (SELECT COALESCE(SUM(c.valor), 0) FROM creditos_afiliado c WHERE c.afiliado_id = a.id AND c.status IN ('ativo','pago')) AS total_gerado,
        (SELECT COALESCE(SUM(c.valor), 0) FROM creditos_afiliado c WHERE c.afiliado_id = a.id AND c.status = 'ativo') AS saldo,
        (SELECT COALESCE(SUM(c.valor), 0) FROM creditos_afiliado c WHERE c.afiliado_id = a.id AND c.status = 'pago') AS pago_total,
        (SELECT COUNT(*) FROM creditos_afiliado c2 WHERE c2.afiliado_id = a.id) AS creditos_total
    FROM afiliados a ORDER BY a.id DESC");
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . site_base() . '/';

// Configuração global de remuneração (padrões por parcela + liga/desliga).
$afiliadoCfg = afiliado_config_global($conn);

// Usuários disponíveis para vínculo manual: sem afiliado atribuído (token
// vazio ou de afiliado que não existe mais) — garante que nenhum usuário
// fique com dois afiliados ao mesmo tempo. Alimenta o modal "Vincular
// usuário"; a busca (nome/e-mail/CPF) é feita no navegador.
$usuariosSemAfiliado = $conn->query("SELECT u.id, u.nome, u.email, u.cpf,
        (SELECT COUNT(*) FROM pagamentos_pix p WHERE p.usuario_id = u.id AND p.tipo = 'plano' AND p.status = 'approved') AS mensalidades
    FROM usuarios u
    WHERE u.afiliado_token IS NULL OR u.afiliado_token = '' OR NOT EXISTS (SELECT 1 FROM afiliados af WHERE af.token = u.afiliado_token)
    ORDER BY u.nome ASC
    LIMIT 500");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Afiliados - Admin Economic Card</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Manrope', sans-serif; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    summary { list-style: none; }
    summary::-webkit-details-marker { display: none; }
</style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php require 'admin_menu.php'; ?>
<main class="md:ml-60 min-h-screen">
<header class="bg-white shadow-sm sticky top-0 z-30">
<div class="px-6 py-4 flex items-center justify-between">
<div>
<h1 class="text-xl font-extrabold text-gray-800">Afiliados</h1>
<p class="text-sm text-gray-500">Vendedores remunerados por parcela paga (adesão + 2ª/3ª/4ª) dos usuários indicados pelo link exclusivo</p>
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

<!-- ====== Remuneração dos afiliados (vendedores): padrões globais ====== -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<div class="flex flex-col sm:flex-row sm:items-start gap-4 justify-between mb-4">
<div>
<h2 class="text-lg font-extrabold text-gray-800 flex items-center gap-2"><span class="material-symbols-outlined text-[#51036d]">tune</span> Remuneração dos vendedores</h2>
<p class="text-sm text-gray-500 mt-1">Valores <b>padrão</b> da comissão por parcela paga de usuário indicado. Cada afiliado pode ter valores próprios no cadastro dele (abaixo). A partir da 5ª parcela o vendedor não recebe mais.</p>
</div>
<span class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold <?php echo (int)$afiliadoCfg['remuneracao_ativa'] === 1 ? 'bg-[#b6f570]/40 text-[#3e6a00]' : 'bg-gray-200 text-gray-600'; ?>">
<span class="w-1.5 h-1.5 rounded-full <?php echo (int)$afiliadoCfg['remuneracao_ativa'] === 1 ? 'bg-[#3e6a00]' : 'bg-gray-400'; ?>"></span>
<?php echo (int)$afiliadoCfg['remuneracao_ativa'] === 1 ? 'Remuneração automática ATIVA' : 'Remuneração automática DESATIVADA'; ?>
</span>
</div>
<form method="POST" action="admin_afiliados.php" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
<input type="hidden" name="acao" value="config_remuneracao"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Comissão por adesão — 1ª parcela (R$)</label>
<input name="valor_adesao" value="<?php echo number_format((float)$afiliadoCfg['valor_adesao'], 2, ',', '.'); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Ex.: 20,00">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Comissão 2ª parcela (R$)</label>
<input name="valor_parcela2" value="<?php echo number_format((float)$afiliadoCfg['valor_parcela2'], 2, ',', '.'); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Ex.: 5,00">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Comissão 3ª parcela (R$)</label>
<input name="valor_parcela3" value="<?php echo number_format((float)$afiliadoCfg['valor_parcela3'], 2, ',', '.'); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Ex.: 5,00">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Comissão 4ª parcela (R$)</label>
<input name="valor_parcela4" value="<?php echo number_format((float)$afiliadoCfg['valor_parcela4'], 2, ',', '.'); ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Ex.: 5,00">
</div>
<div class="flex flex-col gap-2">
<label class="flex items-center gap-2 text-xs font-bold text-gray-600 uppercase cursor-pointer select-none">
<input type="checkbox" name="remuneracao_ativa" <?php echo (int)$afiliadoCfg['remuneracao_ativa'] === 1 ? 'checked' : ''; ?> class="w-4 h-4 accent-[#51036d]">
Remuneração automática
</label>
<button class="bg-[#51036d] hover:bg-[#3a024d] text-white font-bold px-6 py-2.5 rounded-lg transition" type="submit">SALVAR CONFIGURAÇÕES</button>
</div>
</form>
<div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-1 text-xs text-gray-500">
<span>Modelo atual: <b class="text-gray-700">R$ <?php echo number_format((float)$afiliadoCfg['valor_adesao'], 2, ',', '.'); ?></b> na adesão + <b class="text-gray-700">R$ <?php echo number_format((float)$afiliadoCfg['valor_parcela2'], 2, ',', '.'); ?>/R$ <?php echo number_format((float)$afiliadoCfg['valor_parcela3'], 2, ',', '.'); ?>/R$ <?php echo number_format((float)$afiliadoCfg['valor_parcela4'], 2, ',', '.'); ?></b> nas parcelas 2ª/3ª/4ª</span>
<span>Total por usuário indicado com as 4 parcelas pagas: <b class="text-[#3e6a00]">R$ <?php echo number_format((float)$afiliadoCfg['valor_adesao'] + (float)$afiliadoCfg['valor_parcela2'] + (float)$afiliadoCfg['valor_parcela3'] + (float)$afiliadoCfg['valor_parcela4'], 2, ',', '.'); ?></b></span>
</div>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<div class="flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
<div>
<h2 class="text-lg font-extrabold text-gray-800">Cadastro de afiliados</h2>
<p class="text-sm text-gray-500 mt-1">Compartilhe este link para que vendedores façam o próprio cadastro (Nome, E-mail, WhatsApp, CPF e Senha do painel).</p>
</div>
<a href="cadastro_afiliado.php" target="_blank" class="shrink-0 inline-flex items-center gap-2 text-sm font-bold text-[#51036d] hover:text-[#3a024d] bg-[#51036d]/10 hover:bg-[#51036d]/20 rounded-lg px-4 py-2.5 transition">
<span class="material-symbols-outlined text-[18px]">open_in_new</span> Abrir página
</a>
</div>
<div class="mt-4 flex flex-col sm:flex-row gap-2">
<input id="linkCadastroAfiliado" type="text" readonly value="<?php echo htmlspecialchars($baseUrl . 'cadastro_afiliado.php'); ?>" class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-[#51036d]">
<button type="button" onclick="copiarLink('<?php echo htmlspecialchars($baseUrl . 'cadastro_afiliado.php'); ?>', this)" class="shrink-0 bg-[#51036d] hover:bg-[#3a024d] text-white font-bold px-5 py-2.5 rounded-lg transition text-sm">Copiar link</button>
</div>
</div>

<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
<h2 class="text-lg font-extrabold text-gray-800 mb-4">Cadastrar novo vendedor</h2>
<form method="POST" action="admin_afiliados.php" class="grid grid-cols-1 md:grid-cols-2 gap-4">
<input type="hidden" name="acao" value="cadastrar"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nome completo *</label>
<input name="nome" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">E-mail (login) *</label>
<input name="email" type="email" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Senha *</label>
<input name="senha" type="text" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Defina a senha de acesso ao painel">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Comissão adesão — 1ª parcela (R$)</label>
<input name="valor_adesao" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Vazio = padrão global (R$ <?php echo number_format((float)$afiliadoCfg['valor_adesao'], 2, ',', '.'); ?>)">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Comissão 2ª parcela (R$)</label>
<input name="valor_parcela2" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Vazio = padrão global (R$ <?php echo number_format((float)$afiliadoCfg['valor_parcela2'], 2, ',', '.'); ?>)">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Comissão 3ª parcela (R$)</label>
<input name="valor_parcela3" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Vazio = padrão global (R$ <?php echo number_format((float)$afiliadoCfg['valor_parcela3'], 2, ',', '.'); ?>)">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Comissão 4ª parcela (R$)</label>
<input name="valor_parcela4" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Vazio = padrão global (R$ <?php echo number_format((float)$afiliadoCfg['valor_parcela4'], 2, ',', '.'); ?>)">
</div>
<p class="md:col-span-2 -mt-2 text-[11px] text-gray-400">Valores individualizados para este vendedor — deixe em branco para usar os padrões globais definidos no card "Remuneração dos vendedores". Use 0,00 para não pagar comissão nesta parcela para ele.</p>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Telefone / WhatsApp</label>
<input name="telefone" id="telefone" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">CPF</label>
<input name="cpf" id="cpf" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Wallet ID Afiliado</label>
<input name="wallet_afiliado" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Carteira Asaas para receber os repasses (UUID)">
</div>
<div class="md:col-span-2">
<button class="bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-6 py-3 rounded-lg transition" type="submit">CADASTRAR AFILIADO</button>
</div>
</form>
</div>

<!-- ====== Afiliados cadastrados ====== -->
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
<div>
<h2 class="text-lg font-extrabold text-gray-800">Afiliados cadastrados</h2>
<p class="text-sm text-gray-500 mt-0.5">Remuneração, indicações e histórico de cada afiliado.</p>
</div>
<span class="shrink-0 text-xs font-bold text-[#51036d] bg-[#51036d]/10 rounded-full px-3 py-1.5"><?php echo (int)$afiliados->num_rows; ?> no total</span>
</div>

<?php if ($afiliados->num_rows === 0): ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
<span class="material-symbols-outlined text-gray-200" style="font-size:56px">group_add</span>
<p class="mt-3 text-sm font-bold text-gray-600">Nenhum afiliado cadastrado ainda</p>
<p class="mt-1 text-xs text-gray-400">Use o formulário acima ou compartilhe o link de cadastro para começar.</p>
</div>
<?php endif; ?>

<div class="space-y-5">
<?php while ($a = $afiliados->fetch_assoc()): ?>
<?php
    // Iniciais do nome para o avatar do card.
    $partesNome = preg_split('/\s+/', trim((string)$a['nome']));
    $iniciais = mb_substr($partesNome[0] ?? 'A', 0, 1);
    if (count($partesNome) > 1) { $iniciais .= mb_substr($partesNome[count($partesNome) - 1], 0, 1); }
    $linkIndicacao = $baseUrl . 'indicacao/' . $a['token'];
    // Valores resolvidos deste afiliado (override individual ou padrão global).
    $valorAdesaoAf = afiliado_valor_parcela($a, 1, $afiliadoCfg);
    $valorP2Af = afiliado_valor_parcela($a, 2, $afiliadoCfg);
    $valorP3Af = afiliado_valor_parcela($a, 3, $afiliadoCfg);
    $valorP4Af = afiliado_valor_parcela($a, 4, $afiliadoCfg);
    $temValoresProprios = ($a['valor_adesao'] !== null && (string)$a['valor_adesao'] !== '')
        || ($a['valor_parcela2'] !== null && (string)$a['valor_parcela2'] !== '')
        || ($a['valor_parcela3'] !== null && (string)$a['valor_parcela3'] !== '')
        || ($a['valor_parcela4'] !== null && (string)$a['valor_parcela4'] !== '');
    $saldoAfiliado = (float)$a['saldo'];
    $totalGeradoAf = (float)$a['total_gerado'];
    $pagoTotalAf = (float)$a['pago_total'];
    $temWallet = trim((string)($a['wallet_afiliado'] ?? '')) !== '';
    $dataCadastro = !empty($a['criado_em']) ? date('d/m/Y', strtotime($a['criado_em'])) : '—';

    // Usuários indicados deste afiliado: nome, mensalidades pagas por cada um
    // e créditos gerados (a remuneração só existe para usuário vinculado).
    $afiliadoIdAtual = (int)$a['id'];
    $stmtUsu = $conn->prepare("SELECT u.id, u.nome, u.email, u.cartao_ativo, u.criado_em,
            (SELECT COUNT(*) FROM pagamentos_pix p WHERE p.usuario_id = u.id AND p.tipo = 'plano' AND p.status = 'approved') AS mensalidades,
            (SELECT COALESCE(SUM(c.valor), 0) FROM creditos_afiliado c WHERE c.usuario_id = u.id AND c.afiliado_id = ? AND c.status = 'ativo') AS creditos
        FROM usuarios u
        WHERE u.afiliado_token = ?
        ORDER BY u.nome ASC");
    $stmtUsu->bind_param('is', $afiliadoIdAtual, $a['token']);
    $stmtUsu->execute();
    $usuariosAfiliado = $stmtUsu->get_result();

    // Histórico detalhado dos créditos gerados (livro-caixa do afiliado).
    $stmtHist = $conn->prepare("SELECT c.id AS credito_id, c.usuario_id, c.valor, c.tipo_cobranca, c.parcela, c.status, c.criado_em, c.estornado_em, c.motivo_estorno, c.pago_em, u.nome AS usuario_nome
        FROM creditos_afiliado c
        LEFT JOIN usuarios u ON u.id = c.usuario_id
        WHERE c.afiliado_id = ?
        ORDER BY c.criado_em DESC, c.id DESC
        LIMIT 100");
    $stmtHist->bind_param('i', $afiliadoIdAtual);
    $stmtHist->execute();
    $historicoAfiliado = $stmtHist->get_result();
?>
<article class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

    <!-- Identidade -->
    <div class="p-5 sm:p-6 pb-4 flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
        <div class="flex items-center gap-3.5 min-w-0">
            <div class="w-12 h-12 rounded-2xl bg-[#51036d]/10 text-[#51036d] flex items-center justify-center shrink-0 font-extrabold select-none" title="Afiliado #<?php echo (int)$a['id']; ?>"><?php echo htmlspecialchars($iniciais); ?></div>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <p class="font-extrabold text-gray-800 leading-snug truncate"><?php echo htmlspecialchars($a['nome']); ?></p>
                    <span class="text-[10px] font-bold text-gray-300 shrink-0">#<?php echo (int)$a['id']; ?></span>
                </div>
                <p class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($a['email']); ?></p>
                <?php if (!empty($a['telefone'])): ?>
                <p class="mt-0.5 text-xs text-gray-400 truncate flex items-center gap-1"><span class="material-symbols-outlined text-[13px] text-gray-300">call</span><?php echo htmlspecialchars($a['telefone']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <span class="shrink-0 self-start sm:self-center inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide <?php echo $a['ativo'] ? 'bg-[#b6f570]/40 text-[#3e6a00]' : 'bg-gray-200 text-gray-600'; ?>">
            <span class="w-1.5 h-1.5 rounded-full <?php echo $a['ativo'] ? 'bg-[#3e6a00]' : 'bg-gray-400'; ?>"></span>
            <?php echo $a['ativo'] ? 'Ativo' : 'Inativo'; ?>
        </span>
    </div>

    <!-- Números -->
    <div class="px-5 sm:px-6 pb-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3">
            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1"><span class="material-symbols-outlined text-[15px] text-gray-400">payments</span> Remuneração por parcela</p>
            <p class="text-sm font-extrabold text-gray-800 leading-tight">Adesão: R$ <?php echo number_format($valorAdesaoAf, 2, ',', '.'); ?></p>
            <p class="text-[11px] text-gray-500 leading-tight mt-0.5">2ª/3ª/4ª: R$ <?php echo number_format($valorP2Af, 2, ',', '.'); ?> cada<?php echo $temValoresProprios ? ' <span class="font-bold text-[#51036d]">(valores próprios)</span>' : ' (padrão global)'; ?></p>
        </div>
        <div class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3">
            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1"><span class="material-symbols-outlined text-[15px] text-gray-400">group</span> Usuários indicados</p>
            <p class="text-lg font-extrabold text-gray-800 leading-tight"><?php echo (int)$a['indicados']; ?></p>
            <p class="text-[11px] text-gray-400 leading-tight mt-0.5"><?php echo (int)$a['ativos']; ?> ativo<?php echo (int)$a['ativos'] === 1 ? '' : 's'; ?></p>
        </div>
        <div class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3">
            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1"><span class="material-symbols-outlined text-[15px] text-gray-400">event_repeat</span> Comissões geradas</p>
            <p class="text-lg font-extrabold text-gray-800 leading-tight <?php echo (int)$a['creditos_validos'] === 0 ? 'text-gray-300' : ''; ?>"><?php echo (int)$a['creditos_validos']; ?></p>
            <p class="text-[11px] text-gray-400 leading-tight mt-0.5"><?php echo (int)$a['adesoes']; ?> <?php echo (int)$a['adesoes'] === 1 ? 'adesão' : 'adesões'; ?> • <?php echo (int)$a['parcelas']; ?> parcela<?php echo (int)$a['parcelas'] === 1 ? '' : 's'; ?> seguinte<?php echo (int)$a['parcelas'] === 1 ? '' : 's'; ?></p>
        </div>
        <div class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3">
            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1"><span class="material-symbols-outlined text-[15px] text-gray-400">account_balance_wallet</span> Total acumulado</p>
            <?php if ($totalGeradoAf > 0): ?>
            <p class="text-lg font-extrabold text-[#3e6a00] leading-tight">R$ <?php echo number_format($totalGeradoAf, 2, ',', '.'); ?></p>
            <?php else: ?>
            <p class="text-lg font-extrabold text-gray-300 leading-tight">R$ 0,00</p>
            <?php endif; ?>
            <p class="text-[11px] text-gray-400 leading-tight mt-0.5">disponível R$ <?php echo number_format($saldoAfiliado, 2, ',', '.'); ?> • pago R$ <?php echo number_format($pagoTotalAf, 2, ',', '.'); ?></p>
        </div>
    </div>

    <!-- Identificação -->
    <div class="px-5 sm:px-6 pb-5 grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Código colaborador</p>
            <div class="flex items-center gap-2 rounded-xl bg-gray-50 border border-gray-100 px-3.5 py-2.5">
                <span class="text-sm font-extrabold tracking-[0.25em] text-[#51036d]"><?php echo htmlspecialchars($a['codigo']); ?></span>
                <button type="button" onclick="copiarLink('<?php echo htmlspecialchars($a['codigo']); ?>', this)" class="ml-auto shrink-0 inline-flex items-center gap-1 text-[11px] font-bold text-[#51036d] hover:text-[#3a024d]">
                    <span class="material-symbols-outlined text-[14px]">content_copy</span><span class="rotulo-copia">Copiar</span>
                </button>
            </div>
        </div>
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Link de indicação</p>
            <div class="flex items-center gap-2 rounded-xl bg-gray-50 border border-gray-100 px-3.5 py-2.5 min-w-0">
                <span class="text-xs text-gray-500 truncate flex-1 min-w-0" title="<?php echo htmlspecialchars($linkIndicacao); ?>"><?php echo htmlspecialchars($linkIndicacao); ?></span>
                <button type="button" onclick="copiarLink('<?php echo htmlspecialchars($linkIndicacao); ?>', this)" class="shrink-0 inline-flex items-center gap-1 text-[11px] font-bold text-[#51036d] hover:text-[#3a024d]">
                    <span class="material-symbols-outlined text-[14px]">content_copy</span><span class="rotulo-copia">Copiar</span>
                </button>
            </div>
        </div>
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Wallet ID Asaas</p>
            <div class="flex items-center gap-2 rounded-xl bg-gray-50 border border-gray-100 px-3.5 py-2.5 min-w-0">
                <?php if ($temWallet): ?>
                <span class="text-xs text-gray-600 truncate flex-1 min-w-0 font-mono"><?php echo htmlspecialchars($a['wallet_afiliado']); ?></span>
                <?php else: ?>
                <span class="text-xs font-semibold text-yellow-700 flex-1 min-w-0">Pendente — sem repasse automático no split</span>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Cadastrado em</p>
            <div class="flex items-center gap-2 rounded-xl bg-gray-50 border border-gray-100 px-3.5 py-2.5">
                <span class="material-symbols-outlined text-[15px] text-gray-400">calendar_month</span>
                <span class="text-sm text-gray-600 font-semibold"><?php echo $dataCadastro; ?></span>
            </div>
        </div>
    </div>

    <!-- Indicações e histórico (expansíveis) -->
    <div class="px-5 sm:px-6 pb-5 space-y-3">

        <!-- Usuários indicados -->
        <details class="group rounded-xl border border-gray-100 bg-gray-50/60 open:bg-white open:border-gray-200">
            <summary class="cursor-pointer select-none px-4 py-3 flex items-center justify-between gap-3">
                <span class="flex items-center gap-2 text-sm font-bold text-gray-700 min-w-0">
                    <span class="material-symbols-outlined text-[18px] text-[#51036d]">group</span>
                    Usuários indicados
                    <span class="shrink-0 text-[10px] font-bold text-[#51036d] bg-[#51036d]/10 rounded-full px-2 py-0.5"><?php echo (int)$a['indicados']; ?></span>
                </span>
                <span class="material-symbols-outlined text-[20px] text-gray-400 transition-transform group-open:rotate-180 shrink-0">expand_more</span>
            </summary>
            <div class="border-t border-gray-100 px-4 py-3">
                <?php if ($usuariosAfiliado->num_rows === 0): ?>
                <div class="rounded-xl border border-dashed border-gray-200 px-4 py-6 text-center">
                    <p class="text-xs font-bold text-gray-500">Nenhum usuário indicado ainda</p>
                    <p class="mt-1 text-[11px] text-gray-400">Usuários que se cadastrarem pelo link de indicação — ou forem vinculados manualmente — aparecem aqui.</p>
                    <button type="button" data-afiliado-id="<?php echo (int)$a['id']; ?>" data-afiliado-nome="<?php echo htmlspecialchars($a['nome'], ENT_QUOTES); ?>" onclick="abrirVincularUsuario(this)" class="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-[#3e6a00] hover:text-[#2e5000] bg-[#3e6a00]/10 hover:bg-[#3e6a00]/20 rounded-lg px-3.5 py-2 transition">
                        <span class="material-symbols-outlined text-[16px]">person_add</span> Vincular usuário
                    </button>
                </div>
                <?php else: ?>
                <p class="mb-2 text-xs text-gray-500">As parcelas 1ª (adesão) a 4ª pagas por estes usuários geram comissão conforme a remuneração do afiliado; a partir da 5ª não gera mais.</p>
                <div class="rounded-xl border border-gray-100 bg-white divide-y divide-gray-100">
                    <?php while ($u = $usuariosAfiliado->fetch_assoc()): ?>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-bold text-gray-800 truncate"><?php echo htmlspecialchars($u['nome']); ?></p>
                                <?php if ((int)$u['cartao_ativo'] === 1): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wide bg-[#b6f570]/40 text-[#3e6a00]">Cartão ativo</span>
                                <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wide bg-gray-200 text-gray-600">Cartão inativo</span>
                                <?php endif; ?>
                            </div>
                            <p class="mt-0.5 text-xs text-gray-400 truncate"><?php echo htmlspecialchars($u['email'] ?: 'sem e-mail'); ?> • desde <?php echo !empty($u['criado_em']) ? date('d/m/Y', strtotime($u['criado_em'])) : '—'; ?></p>
                        </div>
                        <div class="flex items-center gap-4 shrink-0 sm:justify-end">
                            <div class="text-left sm:text-right">
                                <p class="text-xs font-bold text-gray-600 leading-tight"><?php echo (int)$u['mensalidades']; ?> mensalidade<?php echo (int)$u['mensalidades'] === 1 ? '' : 's'; ?> paga<?php echo (int)$u['mensalidades'] === 1 ? '' : 's'; ?></p>
                                <p class="text-xs font-extrabold <?php echo (float)$u['creditos'] > 0 ? 'text-[#3e6a00]' : 'text-gray-300'; ?> leading-tight">R$ <?php echo number_format((float)$u['creditos'], 2, ',', '.'); ?> gerados</p>
                            </div>
                            <form method="POST" action="admin_afiliados.php" class="inline" data-msg="<?php echo htmlspecialchars('Desvincular ' . $u['nome'] . ' deste afiliado? Os créditos já gerados permanecem no histórico; novas mensalidades deixarão de gerar crédito.', ENT_QUOTES); ?>" onsubmit="confirmarExclusao(event, this, this.dataset.msg)">
                                <input type="hidden" name="acao" value="desvincular_usuario"/>
                                <input type="hidden" name="afiliado_id" value="<?php echo (int)$a['id']; ?>"/>
                                <input type="hidden" name="usuario_id" value="<?php echo (int)$u['id']; ?>"/>
                                <button type="submit" title="Desvincular usuário deste afiliado" class="text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg p-1.5 transition">
                                    <span class="material-symbols-outlined text-[16px]">link_off</span>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>
            </div>
        </details>

        <!-- Histórico de créditos -->
        <details class="group rounded-xl border border-gray-100 bg-gray-50/60 open:bg-white open:border-gray-200">
            <summary class="cursor-pointer select-none px-4 py-3 flex items-center justify-between gap-3">
                <span class="flex items-center gap-2 text-sm font-bold text-gray-700 min-w-0">
                    <span class="material-symbols-outlined text-[18px] text-[#51036d]">receipt_long</span>
                    Histórico de créditos
                    <span class="shrink-0 text-[10px] font-bold text-[#51036d] bg-[#51036d]/10 rounded-full px-2 py-0.5"><?php echo (int)$a['creditos_total']; ?></span>
                </span>
                <span class="material-symbols-outlined text-[20px] text-gray-400 transition-transform group-open:rotate-180 shrink-0">expand_more</span>
            </summary>
            <div class="border-t border-gray-100 px-4 py-3">
                <?php if ($historicoAfiliado->num_rows === 0): ?>
                <div class="rounded-xl border border-dashed border-gray-200 px-4 py-6 text-center">
                    <p class="text-xs font-bold text-gray-500">Nenhum crédito gerado ainda</p>
                    <p class="mt-1 text-[11px] text-gray-400">Os créditos aparecem conforme os usuários indicados pagam as parcelas (adesão + 2ª/3ª/4ª).</p>
                </div>
                <?php else: ?>
                <p class="mb-2 text-[11px] text-gray-500">Disponível: <b>R$ <?php echo number_format($saldoAfiliado, 2, ',', '.'); ?></b> • Já pago ao vendedor: <b>R$ <?php echo number_format($pagoTotalAf, 2, ',', '.'); ?></b></p>
                <div class="rounded-xl border border-gray-100 bg-white divide-y divide-gray-100">
                    <?php while ($c = $historicoAfiliado->fetch_assoc()): ?>
                    <?php
                        $rotuloCobranca = afiliado_rotulo_parcela($c['parcela'], $c['tipo_cobranca']);
                        $dataCredito = !empty($c['criado_em']) ? date('d/m/Y H:i', strtotime($c['criado_em'])) : '—';
                    ?>
                    <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-700 truncate"><?php echo htmlspecialchars($c['usuario_nome'] ?? ('Usuário removido #' . (int)$c['usuario_id'])); ?></p>
                            <p class="text-[11px] text-gray-400 truncate"><?php echo $dataCredito; ?> • <?php echo htmlspecialchars($rotuloCobranca); ?></p>
                            <?php if ($c['status'] === 'estornado' && !empty($c['motivo_estorno'])): ?>
                            <p class="text-[10px] text-red-500 truncate">Estorno: <?php echo htmlspecialchars(mb_strimwidth($c['motivo_estorno'], 0, 90, '…')); ?><?php echo !empty($c['estornado_em']) ? ' (' . date('d/m/Y H:i', strtotime($c['estornado_em'])) . ')' : ''; ?></p>
                            <?php elseif ($c['status'] === 'pago' && !empty($c['pago_em'])): ?>
                            <p class="text-[10px] text-gray-400 truncate">Pago ao vendedor em <?php echo date('d/m/Y H:i', strtotime($c['pago_em'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <div class="text-right">
                                <?php if ($c['status'] === 'ativo'): ?>
                                <p class="text-sm font-extrabold text-[#3e6a00] leading-tight">+ R$ <?php echo number_format((float)$c['valor'], 2, ',', '.'); ?></p>
                                <?php elseif ($c['status'] === 'pago'): ?>
                                <p class="text-sm font-extrabold text-[#51036d] leading-tight">R$ <?php echo number_format((float)$c['valor'], 2, ',', '.'); ?> <span class="text-[9px] uppercase tracking-wide">pago</span></p>
                                <?php else: ?>
                                <p class="text-sm font-extrabold text-gray-400 leading-tight line-through">R$ <?php echo number_format((float)$c['valor'], 2, ',', '.'); ?></p>
                                <p class="text-[9px] font-bold uppercase tracking-wide text-red-500 leading-tight">estornado<?php echo !empty($c['estornado_em']) ? ' em ' . date('d/m/Y', strtotime($c['estornado_em'])) : ''; ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if ($c['status'] !== 'estornado'): ?>
                            <button type="button"
                            data-credito-id="<?php echo (int)$c['credito_id']; ?>"
                            onclick="abrirEstornarCredito(this)"
                            title="Estornar este crédito (registro de motivo)"
                            class="text-gray-300 hover:text-red-600 hover:bg-red-50 rounded-lg p-1.5 transition shrink-0">
                                <span class="material-symbols-outlined text-[16px]">undo</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php if ((int)$a['creditos_total'] > 100): ?>
                <p class="mt-2 text-[11px] text-gray-400 text-right">Exibindo os 100 créditos mais recentes de <?php echo (int)$a['creditos_total']; ?>.</p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </details>
    </div>

    <!-- Ações -->
    <div class="px-5 sm:px-6 py-4 bg-gray-50/70 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center gap-2.5 justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button"
            data-id="<?php echo (int)$a['id']; ?>"
            data-nome="<?php echo htmlspecialchars($a['nome'], ENT_QUOTES); ?>"
            data-email="<?php echo htmlspecialchars($a['email'], ENT_QUOTES); ?>"
            data-telefone="<?php echo htmlspecialchars($a['telefone'] ?? '', ENT_QUOTES); ?>"
            data-cpf="<?php echo htmlspecialchars($a['cpf'] ?? '', ENT_QUOTES); ?>"
            data-nascimento="<?php echo htmlspecialchars($a['nascimento'] ?? '', ENT_QUOTES); ?>"
            data-wallet="<?php echo htmlspecialchars($a['wallet_afiliado'] ?? '', ENT_QUOTES); ?>"
            data-valor-adesao="<?php echo $a['valor_adesao'] !== null ? number_format((float)$a['valor_adesao'], 2, ',', '.') : ''; ?>"
            data-valor-parcela2="<?php echo $a['valor_parcela2'] !== null ? number_format((float)$a['valor_parcela2'], 2, ',', '.') : ''; ?>"
            data-valor-parcela3="<?php echo $a['valor_parcela3'] !== null ? number_format((float)$a['valor_parcela3'], 2, ',', '.') : ''; ?>"
            data-valor-parcela4="<?php echo $a['valor_parcela4'] !== null ? number_format((float)$a['valor_parcela4'], 2, ',', '.') : ''; ?>"
            onclick="abrirEditarAfiliado(this)"
            class="inline-flex items-center gap-1.5 text-xs font-bold text-[#51036d] hover:text-[#3a024d] bg-[#51036d]/10 hover:bg-[#51036d]/20 rounded-lg px-3 py-2 transition" title="Editar informações do afiliado">
                <span class="material-symbols-outlined text-[16px]">edit</span> Editar
            </button>
            <form method="POST" action="admin_afiliados.php" class="inline">
                <input type="hidden" name="acao" value="logar"/>
                <input type="hidden" name="id" value="<?php echo $a['id']; ?>"/>
                <button class="inline-flex items-center gap-1.5 text-xs font-bold text-[#51036d] hover:text-[#3a024d] bg-[#51036d]/10 hover:bg-[#51036d]/20 rounded-lg px-3 py-2 transition" type="submit" title="Logar como afiliado e abrir o painel dele">
                    <span class="material-symbols-outlined text-[16px]">login</span> Logar
                </button>
            </form>
            <button type="button"
            data-afiliado-id="<?php echo (int)$a['id']; ?>"
            data-afiliado-nome="<?php echo htmlspecialchars($a['nome'], ENT_QUOTES); ?>"
            onclick="abrirVincularUsuario(this)"
            class="inline-flex items-center gap-1.5 text-xs font-bold text-[#3e6a00] hover:text-[#2e5000] bg-[#3e6a00]/10 hover:bg-[#3e6a00]/20 rounded-lg px-3 py-2 transition"
            title="Vincular um usuário já cadastrado como indicado deste afiliado">
                <span class="material-symbols-outlined text-[16px]">person_add</span> Vincular usuário
            </button>
            <?php if ((float)$a['saldo'] > 0): ?>
            <form method="POST" action="admin_afiliados.php" class="inline" data-msg="<?php echo htmlspecialchars('Registrar o pagamento de R$ ' . number_format($saldoAfiliado, 2, ',', '.') . ' ao vendedor ' . $a['nome'] . '? Todos os créditos ativos dele ficarão marcados como pagos.', ENT_QUOTES); ?>" onsubmit="confirmarExclusao(event, this, this.dataset.msg)">
                <input type="hidden" name="acao" value="marcar_pago"/>
                <input type="hidden" name="id" value="<?php echo $a['id']; ?>"/>
                <button class="inline-flex items-center gap-1.5 text-xs font-bold text-[#3e6a00] hover:text-[#2e5000] bg-[#b6f570]/40 hover:bg-[#b6f570]/60 rounded-lg px-3 py-2 transition" type="submit" title="Marcar todos os créditos ativos como pagos ao vendedor">
                    <span class="material-symbols-outlined text-[16px]">paid</span> Registrar pagamento (R$ <?php echo number_format($saldoAfiliado, 2, ',', '.'); ?>)
                </button>
            </form>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            <form method="POST" action="admin_afiliados.php" class="inline">
                <input type="hidden" name="acao" value="alternar"/>
                <input type="hidden" name="id" value="<?php echo $a['id']; ?>"/>
                <button class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-600 hover:text-gray-800 bg-gray-200/70 hover:bg-gray-200 rounded-lg px-3 py-2 transition" type="submit">
                    <span class="material-symbols-outlined text-[16px]"><?php echo $a['ativo'] ? 'pause_circle' : 'play_circle'; ?></span>
                    <?php echo $a['ativo'] ? 'Desativar' : 'Ativar'; ?>
                </button>
            </form>
            <form method="POST" action="admin_afiliados.php" class="inline" onsubmit="confirmarExclusao(event, this, 'Excluir este afiliado? Esta ação não pode ser desfeita.');">
                <input type="hidden" name="acao" value="excluir"/>
                <input type="hidden" name="id" value="<?php echo $a['id']; ?>"/>
                <button class="inline-flex items-center gap-1.5 text-xs font-bold text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 border border-red-100 rounded-lg px-3 py-2 transition" type="submit">
                    <span class="material-symbols-outlined text-[16px]">delete</span> Excluir
                </button>
            </form>
        </div>
    </div>
</article>
<?php endwhile; ?>
</div>
</div>
</main>

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

<div id="modalEdicaoAfiliado" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-black/50" onclick="fecharModalEdicaoAfiliado()"></div>
<div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden max-h-[92vh] flex flex-col">
<div class="bg-[#51036d] px-6 py-4 flex items-center justify-between">
<h2 class="text-white font-bold flex items-center gap-2"><span class="material-symbols-outlined">edit</span> Editar afiliado</h2>
<button type="button" onclick="fecharModalEdicaoAfiliado()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<form method="POST" action="admin_afiliados.php" class="overflow-y-auto p-6 space-y-4">
<input type="hidden" name="acao" value="editar"/>
<input type="hidden" name="id" id="edit_id"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nome completo *</label>
<input id="edit_nome" name="nome" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">E-mail (login) *</label>
<input id="edit_email" name="email" type="email" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Telefone / WhatsApp</label>
<input id="edit_telefone" name="telefone" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">CPF</label>
<input id="edit_cpf" name="cpf" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Data de nascimento</label>
<input id="edit_nascimento" name="nascimento" type="date" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Comissão adesão — 1ª parcela (R$)</label>
<input id="edit_valor_adesao" name="valor_adesao" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Vazio = padrão global (R$ <?php echo number_format((float)$afiliadoCfg['valor_adesao'], 2, ',', '.'); ?>)">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Comissão 2ª parcela (R$)</label>
<input id="edit_valor_parcela2" name="valor_parcela2" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Vazio = padrão global (R$ <?php echo number_format((float)$afiliadoCfg['valor_parcela2'], 2, ',', '.'); ?>)">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Comissão 3ª parcela (R$)</label>
<input id="edit_valor_parcela3" name="valor_parcela3" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Vazio = padrão global (R$ <?php echo number_format((float)$afiliadoCfg['valor_parcela3'], 2, ',', '.'); ?>)">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Comissão 4ª parcela (R$)</label>
<input id="edit_valor_parcela4" name="valor_parcela4" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Vazio = padrão global (R$ <?php echo number_format((float)$afiliadoCfg['valor_parcela4'], 2, ',', '.'); ?>)">
</div>
</div>
<p class="-mt-1 text-[11px] text-gray-400">Valores individualizados deste vendedor — deixe em branco para voltar aos padrões globais. Use 0,00 para não pagar comissão nesta parcela.</p>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Wallet ID Afiliado</label>
<input id="edit_wallet" name="wallet_afiliado" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]" placeholder="Carteira Asaas para receber os repasses (UUID)">
</div>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nova senha (opcional)</label>
<input id="edit_senha" name="senha" type="text" placeholder="Deixe em branco para manter a atual" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d]">
</div>
<div class="flex gap-3 pt-2">
<button type="button" onclick="fecharModalEdicaoAfiliado()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-4 py-2.5 rounded-lg transition">Cancelar</button>
<button class="flex-1 bg-[#3e6a00] hover:bg-[#2e5000] text-white font-bold px-4 py-2.5 rounded-lg transition" type="submit">SALVAR ALTERAÇÕES</button>
</div>
</form>
</div>
</div>

<div id="modalVincularUsuario" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-black/50" onclick="fecharModalVincularUsuario()"></div>
<div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden max-h-[92vh] flex flex-col">
<div class="bg-[#51036d] px-6 py-4 flex items-center justify-between">
<h2 class="text-white font-bold flex items-center gap-2"><span class="material-symbols-outlined">person_add</span> Vincular usuário</h2>
<button type="button" onclick="fecharModalVincularUsuario()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<form id="formVincularUsuario" method="POST" action="admin_afiliados.php" class="overflow-y-auto p-6 pb-4">
<input type="hidden" name="acao" value="vincular_usuario"/>
<input type="hidden" name="afiliado_id" id="vinc_afiliado_id"/>
<input type="hidden" name="usuario_id" id="vinc_usuario_id"/>
<p class="text-sm text-gray-600 mb-4">Vincule um usuário já cadastrado como indicado de <b id="vinc_nome_afiliado"></b>. As parcelas já pagas por ele geram <b>créditos retroativos</b> conforme o modelo por parcela (1ª/adesão + 2ª/3ª/4ª) da remuneração configurada.</p>
<div class="relative mb-3">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
<input id="buscaUsuarioVincular" type="text" oninput="filtrarUsuariosVincular()" placeholder="Buscar por nome, e-mail ou CPF..." class="w-full border border-gray-300 rounded-lg pl-10 pr-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#51036d] text-sm">
</div>
<div id="listaUsuariosVincular" class="max-h-72 overflow-y-auto rounded-xl border border-gray-100 divide-y divide-gray-100">
<?php if ($usuariosSemAfiliado->num_rows === 0): ?>
<p class="p-6 text-center text-sm text-gray-500">Nenhum usuário disponível — todos os cadastrados já estão vinculados a um afiliado.</p>
<?php endif; ?>
<?php while ($u = $usuariosSemAfiliado->fetch_assoc()): ?>
<div class="busca-item flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50">
<div class="min-w-0">
<p class="text-sm font-bold text-gray-800 truncate"><?php echo htmlspecialchars($u['nome']); ?></p>
<p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars(($u['email'] ?: 'sem e-mail') . ' • ' . ($u['cpf'] ?: 'CPF não informado')); ?></p>
</div>
<div class="flex flex-col items-end gap-1 shrink-0">
<span class="text-[10px] font-bold text-gray-400"><?php echo (int)$u['mensalidades']; ?> mensalidade<?php echo (int)$u['mensalidades'] === 1 ? '' : 's'; ?> paga<?php echo (int)$u['mensalidades'] === 1 ? '' : 's'; ?></span>
<button type="button" onclick="selecionarUsuarioVincular(<?php echo (int)$u['id']; ?>)" class="text-xs font-bold text-[#51036d] hover:text-[#3a024d] bg-[#51036d]/10 hover:bg-[#51036d]/20 rounded-lg px-3 py-1.5 transition">Vincular</button>
</div>
</div>
<?php endwhile; ?>
</div>
</form>
<div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end">
<button type="button" onclick="fecharModalVincularUsuario()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-4 py-2.5 rounded-lg transition">Cancelar</button>
</div>
</div>
</div>

<script>
function abrirEditarAfiliado(btn) {
    document.getElementById('edit_id').value = btn.dataset.id;
    document.getElementById('edit_nome').value = btn.dataset.nome;
    document.getElementById('edit_email').value = btn.dataset.email;
    document.getElementById('edit_telefone').value = btn.dataset.telefone;
    document.getElementById('edit_cpf').value = btn.dataset.cpf;
    document.getElementById('edit_nascimento').value = btn.dataset.nascimento || '';
    document.getElementById('edit_wallet').value = btn.dataset.wallet;
    document.getElementById('edit_valor_adesao').value = btn.dataset.valorAdesao || '';
    document.getElementById('edit_valor_parcela2').value = btn.dataset.valorParcela2 || '';
    document.getElementById('edit_valor_parcela3').value = btn.dataset.valorParcela3 || '';
    document.getElementById('edit_valor_parcela4').value = btn.dataset.valorParcela4 || '';
    document.getElementById('edit_senha').value = '';
    const m = document.getElementById('modalEdicaoAfiliado');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function fecharModalEdicaoAfiliado() {
    const m = document.getElementById('modalEdicaoAfiliado');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
</script>

<!-- Modal: estorno manual de um crédito do afiliado (com motivo) -->
<div id="modalEstornarCredito" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
<div class="absolute inset-0 bg-black/50" onclick="fecharModalEstornarCredito()"></div>
<div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
<div class="bg-red-600 px-6 py-4 flex items-center justify-between">
<h2 class="text-white font-bold flex items-center gap-2"><span class="material-symbols-outlined">undo</span> Estornar crédito</h2>
<button type="button" onclick="fecharModalEstornarCredito()" class="text-white/70 hover:text-white"><span class="material-symbols-outlined">close</span></button>
</div>
<form id="formEstornarCredito" method="POST" action="admin_afiliados.php" class="p-6 space-y-4">
<input type="hidden" name="acao" value="estornar_credito"/>
<input type="hidden" name="credito_id" id="estorno_credito_id"/>
<div>
<label class="block text-xs font-bold text-gray-600 uppercase mb-1">Motivo do estorno *</label>
<textarea id="estorno_motivo" name="motivo" required rows="3" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Ex.: reembolso acordado com o cliente; cobrança indevida; ajuste administrativo..."></textarea>
<p class="mt-1 text-[11px] text-gray-400">O motivo e a data ficam registrados no histórico; a comissão original não é apagada.</p>
</div>
<div class="flex gap-3 pt-1">
<button type="button" onclick="fecharModalEstornarCredito()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-4 py-2.5 rounded-lg transition">Cancelar</button>
<button class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold px-4 py-2.5 rounded-lg transition" type="submit">ESTORNAR CRÉDITO</button>
</div>
</form>
</div>
</div>

<script>
function abrirEstornarCredito(btn) {
    document.getElementById('estorno_credito_id').value = btn.dataset.creditoId;
    document.getElementById('estorno_motivo').value = '';
    const m = document.getElementById('modalEstornarCredito');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function fecharModalEstornarCredito() {
    const m = document.getElementById('modalEstornarCredito');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
</script>

<script>
const tel = document.getElementById('telefone');
if (tel) tel.addEventListener('input', (e) => { e.target.value = e.target.value.replace(/\D/g, '').slice(0, 15); });
const cpf = document.getElementById('cpf');
if (cpf) cpf.addEventListener('input', (e) => {
    let v = e.target.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
    else if (v.length > 3) v = v.replace(/(\d{3})(\d{3})/, '$1.$2');
    e.target.value = v;
});
const editTel = document.getElementById('edit_telefone');
if (editTel) editTel.addEventListener('input', (e) => { e.target.value = e.target.value.replace(/\D/g, '').slice(0, 15); });
const editCpf = document.getElementById('edit_cpf');
if (editCpf) editCpf.addEventListener('input', (e) => {
    let v = e.target.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
    else if (v.length > 3) v = v.replace(/(\d{3})(\d{3})/, '$1.$2');
    e.target.value = v;
});
function copiarLink(texto, btn) {
    // O rótulo pode estar num span próprio (botões com ícone) para o ícone
    // não ser apagado pelo feedback "Copiado!".
    const rotulo = btn.querySelector('.rotulo-copia');
    const alvo = rotulo || btn;
    const original = alvo.textContent;
    const finalizar = () => {
        alvo.textContent = 'Copiado!';
        setTimeout(() => { alvo.textContent = original; }, 2000);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto).then(finalizar);
    } else {
        const ta = document.createElement('textarea');
        ta.value = texto;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        finalizar();
    }
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
function confirmarModal() {
    if (formPendente) formPendente.submit();
    fecharModalConfirmacao();
}
function fecharModalConfirmacao() {
    formPendente = null;
    const m = document.getElementById('modalConfirmacao');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
function abrirVincularUsuario(btn) {
    document.getElementById('vinc_afiliado_id').value = btn.dataset.afiliadoId;
    document.getElementById('vinc_nome_afiliado').textContent = btn.dataset.afiliadoNome;
    document.getElementById('vinc_usuario_id').value = '';
    const busca = document.getElementById('buscaUsuarioVincular');
    busca.value = '';
    filtrarUsuariosVincular();
    const m = document.getElementById('modalVincularUsuario');
    m.classList.remove('hidden');
    m.classList.add('flex');
    busca.focus();
}
function fecharModalVincularUsuario() {
    const m = document.getElementById('modalVincularUsuario');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
function normalizarBusca(s) {
    return (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}
// Índice de busca do modal de vínculo (nome + e-mail + CPF, sem acentos).
document.querySelectorAll('#listaUsuariosVincular .busca-item').forEach(function (el) {
    el.dataset.busca = normalizarBusca(el.textContent);
});
function filtrarUsuariosVincular() {
    const q = normalizarBusca(document.getElementById('buscaUsuarioVincular').value.trim());
    document.querySelectorAll('#listaUsuariosVincular .busca-item').forEach(function (el) {
        el.style.display = (!q || (el.dataset.busca || '').indexOf(q) !== -1) ? '' : 'none';
    });
}
function selecionarUsuarioVincular(id) {
    document.getElementById('vinc_usuario_id').value = id;
    document.getElementById('formVincularUsuario').submit();
}
</script>
</body>
</html>
