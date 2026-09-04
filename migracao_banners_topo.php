<?php
require_once __DIR__ . '/app/config.php';

$conn->query("CREATE TABLE IF NOT EXISTS banners_topo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL DEFAULT '',
    imagem VARCHAR(500) NOT NULL DEFAULT '',
    link_externo VARCHAR(500) NOT NULL DEFAULT '',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo "Tabela banners_topo criada com sucesso!\n";
