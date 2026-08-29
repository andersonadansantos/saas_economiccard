<?php
// Migração idempotente: coluna "tipo" em pagamentos_pix (plano | cartao_fisico).
// Uso: php migracao_pix_tipo.php   (ou acesse pelo navegador uma única vez)
require_once __DIR__ . '/app/config.php';

function coluna_existe($conn, $tabela, $coluna) {
    $t = $conn->real_escape_string($tabela);
    $c = $conn->real_escape_string($coluna);
    $r = $conn->query("SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c'");
    return $r ? ((int)$r->fetch_assoc()['n'] > 0) : false;
}

$tabela = 'pagamentos_pix';
$coluna = 'tipo';
$definicao = "VARCHAR(20) NOT NULL DEFAULT 'plano' AFTER provedor";

if (coluna_existe($conn, $tabela, $coluna)) {
    echo "[ok] $tabela.$coluna já existe\n";
} elseif ($conn->query("ALTER TABLE `$tabela` ADD COLUMN `$coluna` $definicao")) {
    echo "[novo] $tabela.$coluna criada\n";
} else {
    echo "[erro] $tabela.$coluna: " . $conn->error . "\n";
    exit(1);
}

echo "Migração concluída com sucesso.\n";