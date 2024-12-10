<?php
require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

use Dotenv\Dotenv;

// Carrega o arquivo .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Tokens seguros armazenados no servidor
$apiTokens = [
    'conta0' => $_ENV['TOKEN_CONTA0'],
    'conta1' => $_ENV['TOKEN_CONTA1'],
    'conta2' => $_ENV['TOKEN_CONTA2'],
    'conta3' => $_ENV['TOKEN_CONTA3'],
];

// Recebendo a conta selecionada do frontend
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!isset($input['accountId']) || empty($input['accountId'])) {
    echo json_encode([]);
    exit;
}

$accountId = $input['accountId'];

if (!isset($apiTokens[$accountId])) {
    echo json_encode([]);
    exit;
}

// Token associado à conta
$token = $apiTokens[$accountId];
$apiResponse = fetchExternalData($token);

echo json_encode($apiResponse);

/**
 * Função para buscar dados externos da API com o token correto.
 */
function fetchExternalData($token)
{
    $url = 'https://backend.botconversa.com.br/api/v1/webhook/tags/'; // Substitua com sua URL externa real
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        'API-KEY: ' . $token
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return [];
    }

    curl_close($ch);

    $data = json_decode($response, true);

    return is_array($data) ? $data : [];
}
