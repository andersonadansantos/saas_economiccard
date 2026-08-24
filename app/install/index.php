<?php
// ============================================================
// INSTALADOR - Economic Card
// Configura a conexao com o banco de dados e grava o arquivo
// app/secrets_local.php (fora do git). Funciona sem o banco:
// primeiro mostra o formulario; so conecta ao enviar.
// ============================================================
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

$installDir = __DIR__;
$appDir     = dirname($installDir);
$secretsFile = $appDir . '/secrets_local.php';

// Tabelas esperadas pelo sistema (aviso das faltantes)
$expectedTables = [
    'admin', 'afiliados', 'api_pagamento', 'banners', 'categorias',
    'config_atendimento', 'config_smtp', 'config_whatsapp',
    'envios_email', 'envios_whatsapp', 'mensagens', 'mensagens_lidas',
    'pagamentos_pix', 'parceiros', 'pedidos_cartao', 'personalizacao',
    'planos', 'template_email', 'template_email_geral', 'usuarios',
];

$valores = [
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_user' => 'root',
    'db_pass' => '',
    'db_name' => 'economicacard',
];

$jaInstalado = false;
if (file_exists($secretsFile)) {
    $conteudo = @file_get_contents($secretsFile);
    if ($conteudo !== false && preg_match("/define\(\s*'DB_NAME'/", $conteudo)) {
        $jaInstalado = true;
        if (preg_match("/define\(\s*'DB_HOST',\s*'([^']*)'\s*\)/", $conteudo, $m)) $valores['db_host'] = $m[1];
        if (preg_match("/define\(\s*'DB_PORT',\s*'?\d+'?\s*\)/", $conteudo)) {
            preg_match("/define\(\s*'DB_PORT',\s*'(\d+)'\s*\)/", $conteudo, $m) and $valores['db_port'] = $m[1];
        }
        if (preg_match("/define\(\s*'DB_USER',\s*'([^']*)'\s*\)/", $conteudo, $m)) $valores['db_user'] = $m[1];
        if (preg_match("/define\(\s*'DB_NAME',\s*'([^']*)'\s*\)/", $conteudo, $m)) $valores['db_name'] = $m[1];
    }
}

$resultado = null;   // ['ok'=>bool, 'passos'=>[[titulo, ok, detalhe]], 'faltantes'=>[]]
$erroFatal = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores['db_host'] = trim($_POST['db_host'] ?? 'localhost');
    $valores['db_port'] = trim($_POST['db_port'] ?? '3306');
    $valores['db_user'] = trim($_POST['db_user'] ?? 'root');
    $valores['db_pass'] = (string)($_POST['db_pass'] ?? '');
    $valores['db_name'] = trim($_POST['db_name'] ?? 'economicacard');
    $confirmou = isset($_POST['confirmar']);

    $port = (int)($valores['db_port'] ?: 3306);
    $passos = [];
    $okGeral = true;

    if ($jaInstalado && !$confirmou) {
        $erroFatal = 'Já existe uma configuração salva. Marque a opção "Quero substituir a configuração existente" para reinstalar.';
    } else {
        // 1. Conecta ao SERVIDOR (sem banco)
        $conn = null;
        try {
            $conn = new mysqli($valores['db_host'], $valores['db_user'], $valores['db_pass'], '', $port);
            $conn->set_charset('utf8mb4');
            $passos[] = ['Conexão com o servidor MySQL', true, $valores['db_host'] . ':' . $port];
        } catch (mysqli_sql_exception $e) {
            $okGeral = false;
            $dica = '';
            if (strpos($e->getMessage(), 'Access denied') !== false) {
                $dica = 'Usuário ou senha incorretos.';
            } elseif (strpos($e->getMessage(), 'Connection refused') !== false || strpos($e->getMessage(), 'Connection timed out') !== false) {
                $dica = 'Servidor MySQL não encontrado em ' . $valores['db_host'] . ':' . $port . '. Verifique se o serviço está rodando e se "db_host" está acessível de dentro do container (ex.: nome do serviço no EasyPanel, não localhost).';
            }
            $passos[] = ['Conexão com o servidor MySQL', false, $e->getMessage() . ($dica ? ' — ' . $dica : '')];
        }

        // 2. Cria o banco se nao existir
        if ($conn) {
            try {
                $dbname = $conn->real_escape_string($valores['db_name']);
                $conn->query("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $conn->select_db($valores['db_name']);
                $passos[] = ['Banco de dados "' . $valores['db_name'] . '"', true, 'Pronto para uso (criado se não existia).'];
            } catch (mysqli_sql_exception $e) {
                $okGeral = false;
                $passos[] = ['Banco de dados "' . $valores['db_name'] . '"', false, $e->getMessage()];
            }

            // 3. Verifica tabelas existentes
            $faltantes = $expectedTables;
            if ($conn) {
                try {
                    $res = $conn->query('SHOW TABLES');
                    $existentes = [];
                    while ($r = $res->fetch_row()) { $existentes[] = $r[0]; }
                    $faltantes = array_values(array_diff($expectedTables, $existentes));
                    $encontradas = count($expectedTables) - count($faltantes);
                    $passos[] = ['Verificação de tabelas', true, "$encontradas de " . count($expectedTables) . ' tabelas encontradas.' . (count($faltantes) ? ' Faltam: ' . implode(', ', $faltantes) . '.' : '')];
                } catch (mysqli_sql_exception $e) {
                    $passos[] = ['Verificação de tabelas', false, $e->getMessage()];
                }
            }

            // 4. Grava secrets_local.php
            if ($okGeral || $conn) {
                $php = "<?php\n"
                     . "// ============================================================\n"
                     . "// Gerado pelo instalador em " . date('Y-m-d H:i:s') . "\n"
                     . "// NAO versionar este arquivo (ja esta no .gitignore).\n"
                     . "// ============================================================\n"
                     . "define('DB_HOST', " . var_export($valores['db_host'], true) . ");\n"
                     . "define('DB_PORT', " . var_export($valores['db_port'], true) . ");\n"
                     . "define('DB_USER', " . var_export($valores['db_user'], true) . ");\n"
                     . "define('DB_PASS', " . var_export($valores['db_pass'], true) . ");\n"
                     . "define('DB_NAME', " . var_export($valores['db_name'], true) . ");\n";
                $gravou = @file_put_contents($secretsFile, $php);
                if ($gravou !== false) {
                    @chmod($secretsFile, 0640);
                    $passos[] = ['Gravação da configuração (secrets_local.php)', true, htmlspecialchars(basename($secretsFile)) . ' salvo em ' . htmlspecialchars($appDir)];
                } else {
                    $okGeral = false;
                    $passos[] = ['Gravação da configuração (secrets_local.php)', false, 'Sem permissão de escrita em ' . htmlspecialchars($appDir) . '. Ajuste as permissões ou crie o arquivo manualmente.'];
                }
            }

            if ($conn) { $conn->close(); }
        }

        $resultado = [
            'ok' => $okGeral,
            'passos' => $passos,
            'faltantes' => isset($faltantes) ? $faltantes : [],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Instalador | Economic Card</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }
    body { min-height: 100vh; background: linear-gradient(135deg, #350040 0%, #621872 55%, #8a3f98 100%); display: flex; align-items: center; justify-content: center; padding: 24px; }
    .card { width: 100%; max-width: 560px; background: #fff; border-radius: 18px; box-shadow: 0 24px 60px rgba(20,0,30,.45); overflow: hidden; }
    .head { background: #621872; color: #fff; padding: 26px 32px; }
    .head h1 { font-size: 22px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
    .head p { opacity: .85; font-size: 13px; margin-top: 4px; }
    .body { padding: 28px 32px 32px; }
    label { display: block; font-size: 13px; font-weight: 600; color: #3c3340; margin: 14px 0 5px; }
    input[type=text], input[type=password], input[type=number] {
        width: 100%; padding: 11px 14px; border: 1.5px solid #ddd2dc; border-radius: 10px;
        font-size: 15px; color: #221a26; background: #faf7fa; transition: border .15s; }
    input:focus { outline: none; border-color: #621872; background: #fff; }
    .grid2 { display: grid; grid-template-columns: 1fr 130px; gap: 12px; }
    .check { display: flex; gap: 9px; align-items: flex-start; margin-top: 16px; font-size: 13px; color: #5b4b60; }
    .check input { margin-top: 2px; accent-color: #621872; }
    button { width: 100%; margin-top: 22px; padding: 14px; border: none; border-radius: 999px;
        background: linear-gradient(90deg, #621872, #8a3f98); color: #fff; font-size: 15px; font-weight: 700;
        cursor: pointer; transition: filter .15s; }
    button:hover { filter: brightness(1.12); }
    .notice { background: #fff7e6; border: 1px solid #f0d9a8; color: #7a5b12; font-size: 13px;
        border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; line-height: 1.45; }
    .resultado { margin-bottom: 20px; border-radius: 12px; overflow: hidden; border: 1px solid #e4dae3; }
    .resultado .tit { padding: 12px 16px; font-weight: 700; font-size: 14px; color: #fff; background: #2e7d32; }
    .resultado.falha .tit { background: #b23b3b; }
    .passo { display: flex; gap: 10px; padding: 11px 16px; border-top: 1px solid #f0eaf0; font-size: 13.5px; }
    .passo:first-of-type { border-top: none; }
    .passo b { min-width: 18px; text-align: center; }
    .passo.ok b { color: #2e7d32; } .passo.fail b { color: #b23b3b; }
    .passo small { display: block; color: #7d7080; margin-top: 2px; word-break: break-word; }
    .foot { text-align: center; font-size: 12px; color: rgba(255,255,255,.75); margin-top: 18px; }
</style>
</head>
<body>
<div>
    <div class="card">
        <div class="head">
            <h1>&#128190; Instalador &mdash; Economic Card</h1>
            <p>Configure a conex&atilde;o com o banco de dados MySQL/MariaDB.</p>
        </div>
        <div class="body">

            <?php if ($jaInstalado && !$_POST): ?>
                <div class="notice">&#9888;&#65039; <strong>J&aacute; existe uma configura&ccedil;&atilde;o instalada</strong> (banco: <?= htmlspecialchars($valores['db_name']) ?> @ <?= htmlspecialchars($valores['db_host']) ?>).<br>Preencha novamente apenas se quiser substitu&iacute;-la.</div>
            <?php endif; ?>

            <?php if ($erroFatal): ?>
                <div class="notice" style="background:#fdeaea;border-color:#eec3c3;color:#8f2f2f;">&#10060; <?= htmlspecialchars($erroFatal) ?></div>
            <?php endif; ?>

            <?php if ($resultado): ?>
                <div class="resultado <?= $resultado['ok'] ? '' : 'falha' ?>">
                    <div class="tit"><?= $resultado['ok'] ? '&#9989; Instalação concluída com sucesso!' : '&#9888;&#65039; Instalação concluída com pendências' ?></div>
                    <?php foreach ($resultado['passos'] as $p): ?>
                        <div class="passo <?= $p[1] ? 'ok' : 'fail' ?>"><b><?= $p[1] ? '&#10004;' : '&#10008;' ?></b><span><?= htmlspecialchars($p[0]) ?><small><?= htmlspecialchars($p[2]) ?></small></span></div>
                    <?php endforeach; ?>
                </div>
                <?php if ($resultado['ok']): ?>
                    <div class="notice">&#128273; Por seguran&ccedil;a, <strong>apague a pasta <code>/app/install/</code> do servidor</strong> ap&oacute;s concluir a instala&ccedil;&atilde;o.</div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <label>Host do banco</label>
                <input type="text" name="db_host" value="<?= htmlspecialchars($valores['db_host']) ?>" required placeholder="ex.: mariadb, db, 127.0.0.1">

                <label>Porta</label>
                <input type="number" name="db_port" value="<?= htmlspecialchars($valores['db_port']) ?>" placeholder="3306">

                <label>Usu&aacute;rio</label>
                <input type="text" name="db_user" value="<?= htmlspecialchars($valores['db_user']) ?>" required>

                <label>Senha</label>
                <input type="password" name="db_pass" value="" autocomplete="new-password" placeholder="deixe vazio se n&atilde;o houver senha">

                <label>Nome do banco</label>
                <input type="text" name="db_name" value="<?= htmlspecialchars($valores['db_name']) ?>" required>

                <?php if ($jaInstalado): ?>
                    <div class="check"><input type="checkbox" name="confirmar" id="confirmar"><label for="confirmar" style="margin:0">Quero substituir a configura&ccedil;&atilde;o existente</label></div>
                <?php endif; ?>

                <button type="submit">Testar conex&atilde;o e instalar</button>
            </form>
        </div>
    </div>
    <p class="foot">Economic Card &copy; <?= date('Y') ?> &mdash; Instalador v1</p>
</div>
</body>
</html>
