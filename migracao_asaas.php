<?php
// Migração idempotente da integração Asaas (Split de Pagamentos nativo).
// Uso: php migracao_asaas.php   (ou acesse pelo navegador uma única vez)
// Cria as colunas necessárias em api_pagamento e pagamentos_pix.
// As colunas antigas do Mercado Pago permanecem no banco apenas por histórico
// (nenhum código as utiliza mais).
require_once __DIR__ . '/app/config.php';

function coluna_existe($conn, $tabela, $coluna) {
    $t = $conn->real_escape_string($tabela);
    $c = $conn->real_escape_string($coluna);
    $r = $conn->query("SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c'");
    return $r ? ((int)$r->fetch_assoc()['n'] > 0) : false;
}

$migracoes = [
    // [tabela, coluna, definicao]
    ['api_pagamento', 'asaas_api_key', "TEXT NULL AFTER access_token"],
    ['api_pagamento', 'asaas_ambiente', "VARCHAR(10) NOT NULL DEFAULT 'producao' AFTER asaas_api_key"],
    ['api_pagamento', 'asaas_wallet_parceiro', "VARCHAR(64) NULL AFTER asaas_ambiente"],
    ['api_pagamento', 'valor_fixo_parceiro', "DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER asaas_wallet_parceiro"],
    ['pagamentos_pix', 'provedor', "VARCHAR(10) NOT NULL DEFAULT 'mp' AFTER mp_payment_id"],
    ['pagamentos_pix', 'asaas_payment_id', "VARCHAR(64) NULL AFTER provedor"],
];

$falhas = 0;
foreach ($migracoes as [$tabela, $coluna, $definicao]) {
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
