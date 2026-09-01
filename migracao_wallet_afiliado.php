<?php
// Migração idempotente: coluna afiliados.wallet_afiliado.
// Guarda o Wallet ID (carteira de recebimento de comissões) do afiliado,
// usado no repasse de comissões (ex.: wallet Asaas para split de pagamentos).
// Uso: php migracao_wallet_afiliado.php   (ou acesse pelo navegador uma única vez)
require_once __DIR__ . '/app/config.php';

function coluna_existe($conn, $tabela, $coluna) {
    $t = $conn->real_escape_string($tabela);
    $c = $conn->real_escape_string($coluna);
    $r = $conn->query("SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c'");
    return $r ? ((int)$r->fetch_assoc()['n'] > 0) : false;
}

$tabela = 'afiliados';

if (!coluna_existe($conn, $tabela, 'wallet_afiliado')) {
    if (!$conn->query("ALTER TABLE `$tabela` ADD COLUMN `wallet_afiliado` VARCHAR(64) NULL DEFAULT NULL AFTER `comissao`")) {
        echo "[erro] afiliados.wallet_afiliado: " . $conn->error . "\n";
        exit(1);
    }
    echo "[novo] afiliados.wallet_afiliado criada\n";
} else {
    echo "[ok] afiliados.wallet_afiliado já existe\n";
}

echo "Migração concluída com sucesso.\n";