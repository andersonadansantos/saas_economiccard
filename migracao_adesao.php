<?php
// Migração idempotente: tabela personalizacao com o valor da taxa de Adesão
// cobrada no momento do cadastro (coluna valor_adesao, até 4 casas decimais).
// Uso: php migracao_adesao.php   (ou acesse pelo navegador uma única vez)
require_once __DIR__ . '/app/config.php';

function coluna_existe($conn, $tabela, $coluna) {
    $t = $conn->real_escape_string($tabela);
    $c = $conn->real_escape_string($coluna);
    $r = $conn->query("SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c'");
    return $r ? ((int)$r->fetch_assoc()['n'] > 0) : false;
}

$tabela = 'personalizacao';

if (!coluna_existe($conn, $tabela, 'valor_adesao')) {
    if (!$conn->query("ALTER TABLE `$tabela` ADD COLUMN `valor_adesao` DECIMAL(12,4) NOT NULL DEFAULT 0.0000")) {
        echo "[erro] personalizacao.valor_adesao: " . $conn->error . "\n";
        exit(1);
    }
    echo "[novo] personalizacao.valor_adesao criada\n";
} else {
    echo "[ok] personalizacao.valor_adesao já existe\n";
}

echo "Migração concluída com sucesso.\n";
