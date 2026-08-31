<?php
// Migração idempotente: tabela planos com preço de 1º mês (adesão) e valor mensal (2º+),
// suportando até 4 casas decimais nos valores.
// Uso: php migracao_planos_adesao.php   (ou acesse pelo navegador uma única vez)
require_once __DIR__ . '/app/config.php';

function coluna_existe($conn, $tabela, $coluna) {
    $t = $conn->real_escape_string($tabela);
    $c = $conn->real_escape_string($coluna);
    $r = $conn->query("SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c'");
    return $r ? ((int)$r->fetch_assoc()['n'] > 0) : false;
}

function coluna_tipo($conn, $tabela, $coluna) {
    $t = $conn->real_escape_string($tabela);
    $c = $conn->real_escape_string($coluna);
    $r = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c'");
    return $r ? $r->fetch_assoc()['COLUMN_TYPE'] : '';
}

$tabela = 'planos';

// valor_adesao = valor do 1º mês (cobrado na ativação)
if (!coluna_existe($conn, $tabela, 'valor_adesao')) {
    if (!$conn->query("ALTER TABLE `$tabela` ADD COLUMN `valor_adesao` DECIMAL(12,4) NOT NULL DEFAULT 0.0000 AFTER `valor`")) {
        echo "[erro] planos.valor_adesao: " . $conn->error . "\n";
        exit(1);
    }
    $conn->query("UPDATE `$tabela` SET `valor_adesao` = `valor` WHERE `valor_adesao` = 0");
    echo "[novo] planos.valor_adesao criada e preenchida\n";
} else {
    // Garante precisão de 4 casas decimais
    if (coluna_tipo($conn, $tabela, 'valor_adesao') !== 'decimal(12,4)') {
        if ($conn->query("ALTER TABLE `$tabela` MODIFY `valor_adesao` DECIMAL(12,4) NOT NULL DEFAULT 0.0000")) {
            echo "[ajuste] planos.valor_adesao -> DECIMAL(12,4)\n";
        } else {
            echo "[erro-ajuste] planos.valor_adesao: " . $conn->error . "\n";
        }
    } else {
        echo "[ok] planos.valor_adesao já é DECIMAL(12,4)\n";
    }
}

// valor_mensal = valor da 2ª cobrança em diante (recorrente/mensal)
if (!coluna_existe($conn, $tabela, 'valor_mensal')) {
    if (!$conn->query("ALTER TABLE `$tabela` ADD COLUMN `valor_mensal` DECIMAL(12,4) NOT NULL DEFAULT 0.0000 AFTER `valor_adesao`")) {
        echo "[erro] planos.valor_mensal: " . $conn->error . "\n";
        exit(1);
    }
    echo "[novo] planos.valor_mensal criada\n";
} else {
    if (coluna_tipo($conn, $tabela, 'valor_mensal') !== 'decimal(12,4)') {
        if ($conn->query("ALTER TABLE `$tabela` MODIFY `valor_mensal` DECIMAL(12,4) NOT NULL DEFAULT 0.0000")) {
            echo "[ajuste] planos.valor_mensal -> DECIMAL(12,4)\n";
        } else {
            echo "[erro-ajuste] planos.valor_mensal: " . $conn->error . "\n";
        }
    } else {
        echo "[ok] planos.valor_mensal já é DECIMAL(12,4)\n";
    }
}

// coluna valor (legada) também para 4 casas
$tValor = coluna_tipo($conn, $tabela, 'valor');
if ($tValor !== 'decimal(12,4)') {
    if ($conn->query("ALTER TABLE `$tabela` MODIFY `valor` DECIMAL(12,4) NOT NULL")) {
        echo "[ajuste] planos.valor -> DECIMAL(12,4)\n";
    }
}

echo "Migração concluída com sucesso.\n";
