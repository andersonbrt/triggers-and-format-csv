<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Carrega o arquivo .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Obtém URL da aplicação
$app_url = $_ENV['APP_URL'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filePath = 'data/output/output_send-botconversa.csv';

    if (file_exists($filePath)) {
        // Verifica se o arquivo é UTF-8
        if (!isUtf8File($filePath)) {
            // Converte o arquivo para UTF-8
            convertToUtf8($filePath);
        }

        $firstLine = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)[0] ?? '';
        $delimiter = detectDelimiter($firstLine);

        if ($delimiter !== ',') {
            echo json_encode(['message' => 'O arquivo não é um CSV delimitado por vírgulas.']);
            exit;
        }

        $rows = array_map(
            fn($line) => str_getcsv($line, $delimiter),
            file($filePath)
        );

        if (!$rows || count($rows) < 2) {
            echo json_encode(['message' => 'Erro ao processar dados com base no delimitador detectado']);
            exit;
        }

        $header = array_shift($rows);
        if (!$header) {
            echo json_encode(['message' => 'Erro: cabeçalho inválido']);
            exit;
        }

        // Remove o BOM dos cabeçalhos e dos dados
        $header = array_map('removeBOM', $header);
        $data = array_map(
            fn($row) => array_map(fn($value) => removeBOM(sanitizeString($value)), array_combine($header, $row)),
            $rows
        );

        // Converter para JSON sem BOM e caracteres unicode escapados
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

        if (!isset($_POST['accountId'])) {
            echo json_encode(['message' => 'accountId não foi fornecido.']);
            exit;
        }

        $accountId = $_POST['accountId'];
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
        }

        $webhookUrl = "{$app_url}?account_id={$accountId}";
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData),
        ]);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode == 200) {
            echo json_encode(['message' => 'Dados enviados com sucesso para (' . $accountName . ').']);
        } else {
            echo json_encode(['message' => 'Erro ao enviar os dados para o Webhook.']);
        }
    } else {
        echo json_encode(['message' => 'Arquivo CSV não encontrado no caminho especificado.']);
    }
} else {
    echo json_encode(['message' => 'Requisição inválida.']);
}

/**
 * Detecta o delimitador usado no arquivo CSV
 *
 * @param string $line Primeira linha do arquivo CSV
 * @return string|null Retorna o delimitador detectado ou null se não encontrar.
 */
function detectDelimiter(string $line): ?string
{
    $delimiters = [',', ';', "\t", '|'];
    $counts = [];

    foreach ($delimiters as $delimiter) {
        $counts[$delimiter] = substr_count($line, $delimiter);
    }

    arsort($counts);

    return $counts ? key($counts) : null;
}

/**
 * Verifica se o arquivo está codificado como UTF-8.
 *
 * @param string $filePath
 * @return bool
 */
function isUtf8File(string $filePath): bool
{
    $content = file_get_contents($filePath);
    return mb_check_encoding($content, 'UTF-8');
}

/**
 * Converte o conteúdo de um arquivo para UTF-8.
 *
 * @param string $filePath
 * @return void
 */
function convertToUtf8(string $filePath): void
{
    $content = file_get_contents($filePath);
    $utf8Content = mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content, 'UTF-8, ISO-8859-1, ISO-8859-15', true));
    file_put_contents($filePath, $utf8Content);
}

/**
 * Remove ou limpa qualquer caractere inválido e ajusta a codificação UTF-8.
 *
 * @param string $string
 * @return string
 */
function sanitizeString(string $string): string
{
    $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    $string = preg_replace('/[\x00-\x1F\x7F]/', '', $string);
    $string = trim($string);

    return $string;
}

/**
 * Remove o BOM (Byte Order Mark) de uma string.
 *
 * @param string $string
 * @return string
 */
function removeBOM($string)
{
    $bom = "\xEF\xBB\xBF"; // Representação do BOM UTF-8
    if (substr($string, 0, 3) === $bom) {
        $string = substr($string, 3);
    }
    return $string;
}
