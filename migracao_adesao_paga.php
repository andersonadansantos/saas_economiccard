<?php
// Migração idempotente: tabela usuarios com a coluna adesao_paga.
// Indica se o usuário já pagou a taxa de adesão na contratação do plano Mensal.
// Enquanto nao paga (0), a primeira contratação do plano Mensal cobra a taxa
// de adesão (personalizacao.valor_adesao) e exibe o modal de aviso. Depois de
// paga (1), cobra apenas a mensalidade e não exibe mais o modal.
// Uso: php migracao_adesao_paga.php   (ou acesse pelo navegador uma única vez)
require_once __DIR__ . '/app/config.php';

function coluna_existe($conn, $tabela, $coluna) {
    $t = $conn->real_escape_string($tabela);
    $c = $conn->real_escape_string($coluna);
    $r = $conn->query("SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c'");
    return $r ? ((int)$r->fetch_assoc()['n'] > 0) : false;
}

$tabela = 'usuarios';

if (!coluna_existe($conn, $tabela, 'adesao_paga')) {
    if (!$conn->query("ALTER TABLE `$tabela` ADD COLUMN `adesao_paga` TINYINT(1) NOT NULL DEFAULT 0")) {
        echo "[erro] usuarios.adesao_paga: " . $conn->error . "\n";
        exit(1);
    }
    echo "[novo] usuarios.adesao_paga criada\n";
} else {
    echo "[ok] usuarios.adesao_paga já existe\n";
}

echo "Migração concluída com sucesso.\n";
