<?php
// ============================================================================
// MIGRACAO UNICA - CORRECAO DE TEXTO UTF-8 LEGADO
// ============================================================================
// Corrige textos antigos gravados com "+?" (e "-?") no lugar de caracteres
// acentuados. O restante do conteudo e preservado integralmente.
//
// Uso:
//   php migracao_utf8.php            (somente simulacao)
//   php migracao_utf8.php --apply    (aplica as alteracoes)
//   ou acesse pelo navegador com ?apply=1 (somente admin logado).
//
// E idempotente: quando nao existe mais "+?" nos textos, nada e alterado.
require_once __DIR__ . '/app/config.php';

$cli = (PHP_SAPI === 'cli');
$aplicar = $cli
    ? in_array('--apply', $argv ?? [], true)
    : (isset($_GET['apply']) && (string)$_GET['apply'] === '1');

// Pelo navegador, somente o administrador logado pode executar a migracao.
if (!$cli && !($_SESSION['admin_logado'] ?? false)) {
    http_response_code(403);
    exit('Acesso restrito ao administrador.');
}

echo "Migracao: correcao de textos UTF-8 legados (+? e -?)\n";
echo $aplicar ? "MODO: APLICAR ALTERACOES\n" : "MODO: SIMULACAO (use --apply para gravar)\n";
echo str_repeat('-', 72) . "\n";

// Dicionario exato. A substituicao e feita em PHP (strtr), portanto diferencia
// maiusculas/minusculas, nao depende da collation do MySQL e nao reprocessa o
// texto ja substituido.
$mapa = [
    // Frases completas primeiro (mais especificas).
    '. +? r+?pido e f+?cil' => '. É rápido e fácil',
    'Ativa+?+?o'            => 'Ativação',
    'Assist+?ncia'          => 'Assistência',
    'benef+?cios'           => 'benefícios',
    'Lulug+?s'              => 'Lulugás',
    'A+?a+?'                => 'Açaí',
    'S+?o Pedro'            => 'São Pedro',
    'Ant+?nio'              => 'Antônio',
    'F+?tima'               => 'Fátima',
    // Palavras e formas comuns.
    'Cart+?o'               => 'Cartão',
    'cart+?o'               => 'cartão',
    'Ol+?'                  => 'Olá',
    'ol+?'                  => 'olá',
    'd+?vida'               => 'dúvida',
    'n+?o'                  => 'não',
    'est+?'                 => 'está',
    'ser+?'                 => 'será',
    'r+?pido'               => 'rápido',
    'f+?cil'                => 'fácil',
    'Sa+?de'                => 'Saúde',
    'Bel+?m'                => 'Belém',
    'Jo+?o'                 => 'João',
    'n-?'                   => 'nº',
];

// Colunas de texto existentes no banco atual.
$sqlCols = "SELECT TABLE_NAME, COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND DATA_TYPE IN ('char','varchar','text','tinytext','mediumtext','longtext')
            ORDER BY TABLE_NAME, ORDINAL_POSITION";
$resCols = $conn->query($sqlCols);
if (!$resCols) {
    echo "[erro] nao foi possivel listar as colunas: {$conn->error}\n";
    exit(1);
}

// Chave primaria de cada tabela (necessaria para atualizar a linha certa).
$primarias = [];
$resPk = $conn->query("SELECT TABLE_NAME, COLUMN_NAME
                       FROM information_schema.KEY_COLUMN_USAGE
                       WHERE TABLE_SCHEMA = DATABASE()
                         AND CONSTRAINT_NAME = 'PRIMARY'
                       ORDER BY TABLE_NAME, ORDINAL_POSITION");
if ($resPk) {
    while ($pk = $resPk->fetch_row()) {
        $primarias[$pk[0]][] = $pk[1];
    }
}

$totalLinhas = 0;
$totalAlteradas = 0;
$colunasComProblema = 0;
$ignoradasSemPk = [];
$acao = $aplicar ? 'corrigidas' : 'a corrigir';

while ($c = $resCols->fetch_row()) {
    list($tabela, $coluna) = $c;
    $pk = isset($primarias[$tabela]) ? $primarias[$tabela] : [];
    if (!$pk) {
        $ignoradasSemPk[] = "{$tabela}.{$coluna}";
        continue;
    }

    $selectPk = implode(', ', array_map(function ($p) { return "`{$p}`"; }, $pk));
    $sql = "SELECT {$selectPk}, `{$coluna}` AS valor
            FROM `{$tabela}`
            WHERE `{$coluna}` LIKE '%+?%' OR `{$coluna}` LIKE '%-?%'";
    $res = $conn->query($sql);
    if (!$res) {
        echo "[erro] {$tabela}.{$coluna}: {$conn->error}\n";
        continue;
    }

    $linhas = 0;
    $alteradas = 0;
    while ($row = $res->fetch_assoc()) {
        $linhas++;
        $valor = (string)$row['valor'];
        $novo = strtr($valor, $mapa);
        if ($novo === $valor) {
            continue; // nenhum token conhecido nesta linha
        }

        $alteradas++;
        if (!$aplicar) {
            continue;
        }

        $where = [];
        $tipos = 's'; // valor corrigido
        $params = [$novo];
        foreach ($pk as $p) {
            $where[] = "`{$p}` = ?";
            $tipos .= 's';
            $params[] = $row[$p];
        }

        $sqlUp = "UPDATE `{$tabela}` SET `{$coluna}` = ? WHERE " . implode(' AND ', $where);
        $up = $conn->prepare($sqlUp);
        if (!$up) {
            echo "[erro] prepare {$tabela}.{$coluna}: {$conn->error}\n";
            continue;
        }
        $up->bind_param($tipos, ...$params);
        if (!$up->execute()) {
            echo "[erro] update {$tabela}.{$coluna}: {$up->error}\n";
        }
        $up->close();
    }
    $res->free();

    if ($linhas > 0) {
        $colunasComProblema++;
        $totalLinhas += $linhas;
        $totalAlteradas += $alteradas;
        echo sprintf("%-45s %3d linha(s) com token, %3d %s\n",
            "{$tabela}.{$coluna}", $linhas, $alteradas, $acao);
    }
}

echo str_repeat('-', 72) . "\n";
echo "Colunas com ocorrencias: {$colunasComProblema}\n";
echo "Linhas com token conhecido: {$totalLinhas} | linhas {$acao}: {$totalAlteradas}\n";
if ($ignoradasSemPk) {
    echo "[aviso] colunas sem chave primaria ignoradas: " . implode(', ', $ignoradasSemPk) . "\n";
}

// Verificacao final: procura qualquer "+?" ou "-?" restante.
$restantes = [];
$resCols2 = $conn->query($sqlCols);
while ($c = $resCols2->fetch_row()) {
    list($tabela, $coluna) = $c;
    $r = $conn->query("SELECT COUNT(*) FROM `{$tabela}` WHERE `{$coluna}` LIKE '%+?%' OR `{$coluna}` LIKE '%-?%'");
    if ($r) {
        $linha = $r->fetch_row();
        $n = (int)$linha[0];
        if ($n > 0) {
            $restantes[] = "{$tabela}.{$coluna}={$n}";
        }
        $r->free();
    }
}
if ($restantes) {
    echo "[pendente] ainda existem ocorrencias: " . implode(', ', $restantes) . "\n";
} else {
    echo "Verificacao final: nenhuma ocorrencia de '+?' ou '-?' restante.\n";
}

if (!$aplicar) {
    echo "Nenhuma alteracao foi gravada. Execute com --apply para aplicar.\n";
}
