<?php
// Migração idempotente: regra de Split de Pagamentos configurável por tipo de
// cobrança (Adesão / 1ª mensalidade / Recorrência) na integração Asaas.
// Nova aba "Split" em Admin > API Pagamento define quanto o PARCEIRO e o
// AFILIADO recebem em cada tipo de pagamento; o restante fica com a empresa.
// Uso: php migracao_split_asaas.php   (ou acesse pelo navegador uma única vez)
require_once __DIR__ . '/app/config.php';

function coluna_existe($conn, $tabela, $coluna) {
    $t = $conn->real_escape_string($tabela);
    $c = $conn->real_escape_string($coluna);
    $r = $conn->query("SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c'");
    return $r ? ((int)$r->fetch_assoc()['n'] > 0) : false;
}

$tabela = 'api_pagamento';
$colunas = [
    // [coluna, definicao]
    ['split_parceiro_adesao', "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER valor_fixo_parceiro"],
    ['split_parceiro_recorrencia', "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER split_parceiro_adesao"],
    ['split_afiliado_adesao', "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER split_parceiro_recorrencia"],
    ['split_afiliado_1a_mensal', "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER split_afiliado_adesao"],
];

$falhas = 0;
foreach ($colunas as [$coluna, $definicao]) {
    if (coluna_existe($conn, $tabela, $coluna)) {
        echo "[ok] $tabela.$coluna já existe\n";
        continue;
    }
    if ($conn->query("ALTER TABLE `$tabela` ADD COLUMN `$coluna` $definicao")) {
        echo "[novo] $tabela.$coluna criada\n";
    } else {
        $falhas++;
        echo "[erro] $tabela.$coluna: " . $conn->error . "\n";
    }
}

echo $falhas === 0 ? "Migração concluída com sucesso.\n" : "Migração concluída com $falhas erro(s).\n";
exit($falhas === 0 ? 0 : 1);