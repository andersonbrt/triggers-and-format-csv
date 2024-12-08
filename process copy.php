<?php
// Gerar o arquivo CSV com codificação UTF-8
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="arquivo.csv"');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_FILES['csvFile']) && !isset($_POST['action'])) {

        $uploadDir = 'data/input/';
        $filePath = $uploadDir . 'upload_input.csv'; // Nome fixo para o arquivo

        // Move o arquivo para o diretório de entrada
        if (move_uploaded_file($_FILES['csvFile']['tmp_name'], $filePath)) {
            echo json_encode(['message' => 'Arquivo carregado com sucesso!']);
        } else {
            echo json_encode(['message' => 'Erro ao carregar o arquivo.']);
        }
    } elseif (isset($_POST['action'])) {

        $action = $_POST['action'];

        // Usa o caminho do arquivo carregado, não o arquivo fixo
        $inputFile = 'data/input/upload_input.csv'; // Agora o arquivo carregado é usado

        // Caminha do arquivo de saida
        $outputFile = "data/output/output_{$action}.csv";

        if (file_exists($inputFile)) {
            $rows = array_map('str_getcsv', file($inputFile));
            $header = array_shift($rows); // Remove e obtém o cabeçalho
            $data = array_map(fn($row) => array_combine($header, $row), $rows);

            // Aplicar a formatação em todos os telefones
            foreach ($data as &$item) {
                $item['telefone'] = formatarTelefone($item['telefone']);
            }

            // Escreve os dados no arquivo de saída
            $file = fopen($outputFile, 'w');

            // Verifica action para formatar em UTF-8
            if ($action !== 'send-botconversa') {

                // Escrever a BOM (Byte Order Mark) UTF-8 para garantir a codificação correta no Excel
                fwrite($file, "\xEF\xBB\xBF"); // BOM para UTF-8
            }

            // Adicionar o cabeçalho ao arquivo CSV
            fputcsv($file, $header);

            // Guardar os dados no arquivo CSV
            foreach ($data as $row) {
                fputcsv($file, $row);
            }

            fclose($file);

            // Retorna uma resposta JSON válida
            echo json_encode(
                [
                    'message' => "Arquivo processado para $action com sucesso!",
                    'file_url' => $outputFile // URL para download
                ]
            );
        } else {
            echo json_encode(['message' => 'Arquivo de entrada não encontrado!']);
        }
    }
}

// Função para formatar o telefone
function formatarTelefone($telefone)
{
    // Remover caracteres não numéricos
    $telefone = preg_replace('/\D/', '', $telefone);

    // Verificar o tamanho do telefone
    $tamanho = strlen($telefone);

    // Verificar a quantidade de dígitos e aplicar a formatação conforme necessário
    switch ($tamanho) {
        case 10: // 10 dígitos (sem DDI e sem nono dígito)
            $ddd = substr($telefone, 0, 2);
            $resto = substr($telefone, 2);

            if ($ddd < 28) {
                $telefone = "55" . $ddd . "9" . $resto; // Adiciona o DDI e o nono dígito
            } else {
                $telefone = "55" . $telefone; // Apenas adiciona o DDI
            }
            break;

        case 11: // 11 dígitos (sem DDI e com nono dígito)
            $ddd = substr($telefone, 0, 2);
            $resto = substr($telefone, 3); // Pula o nono dígito

            if ($ddd < 28) {
                $telefone = "55" . $telefone; // Adiciona o DDI e mantém o nono dígito
            } else {
                $telefone = "55" . $ddd . $resto; // Adiciona o DDI e remove o nono dígito
            }
            break;

        case 12: // 12 dígitos (com DDI e sem nono dígito)
            $ddi = substr($telefone, 0, 2);
            $ddd = substr($telefone, 2, 2);
            $resto = substr($telefone, 4);

            if ($ddi === "55" && $ddd < 28) {
                $telefone = $ddi . $ddd . "9" . $resto; // Adiciona o nono dígito
            }
            break;

        case 13: // 13 dígitos (com DDI e com nono dígito)
            $ddi = substr($telefone, 0, 2);
            $ddd = substr($telefone, 2, 2);
            $resto = substr($telefone, 5); // Pula o nono dígito

            if ($ddi === "55" && $ddd >= 28) {
                $telefone = $ddi . $ddd . $resto; // Remove o nono dígito
            }
            break;
    }

    return $telefone;
}
