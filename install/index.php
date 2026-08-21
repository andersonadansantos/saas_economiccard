<?php
// Teste temporário apenas de conexão com o banco de dados.
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'economicacard';

try {
    $conn = new mysqli($host, $user, $pass, $db);
} catch (mysqli_sql_exception $e) {
    http_response_code(503);
    die('Falha na conexao: o MySQL nao esta acessivel. Inicie o MySQL no painel do XAMPP e tente novamente.');
}
$conn->set_charset('utf8mb4');

echo 'Conexão com o banco "' . $db . '" estabelecida com sucesso.';