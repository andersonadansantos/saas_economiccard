<?php
// Teste temporário apenas de conexão com o banco de dados.
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'economicacard';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Falha na conexão: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

echo 'Conexão com o banco "' . $db . '" estabelecida com sucesso.';