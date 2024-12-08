<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Carrega o arquivo .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$APP_URL = getenv('APP_URL');

// Agora as variáveis estão disponíveis no ambiente $_ENV ou getenv()
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtém o caminho do arquivo CSV enviado
    $filePath = 'data/output/output_send-botconversa.csv';

    // Verifica se o arquivo existe
    if (!file_exists($filePath)) {
        // Lê o conteúdo do arquivo CSV
        $rows = array_map('str_getcsv', file($filePath));
        $header = array_shift($rows); // Remove o cabeçalho
        $data = array_map(fn($row) => array_combine($header, $row), $rows);

        // Adicionar o accountId no array $data
        $accountId = $_POST['accountId'];
        //$accountId = $_GET['accountId'];

        switch ($accountId) {
            case 'conta0':
                $accountName = "Conta Matriz";
                break;
            case 'conta1':
                $accountName = "Conta 1";
                break;
            case 'conta2':
                $accountName = "Conta 2";
                break;

            case 'conta3':
                $accountName = "Conta 3";
                break;
            default:
                $accountName = "Undefined";
                break;
        }

        // Converte os dados para JSON
        $jsonData = json_encode($_POST);

        // Envia os dados para o Webhook (aqui você define o seu endpoint de Webhook)
        $webhookUrl = "{$_ENV['APP_URL']}?account_id={$accountId}"; // Substitua pelo seu URL de Webhook

        // Inicializa o cURL para enviar os dados
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ]);
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Verifica a resposta
        if ($statusCode == 200) {
            echo json_encode(['message' => 'Dados enviados com sucesso para (' . $accountName . ').']);
        } else {
            echo json_encode(['message' => 'Erro ao enviar os dados para o Webhook.']);
        }
    } else {
        echo json_encode(['message' => 'Arquivo não encontrado.']);
    }
} else {
    echo json_encode(['message' => 'Requisição inválida.']);
}
