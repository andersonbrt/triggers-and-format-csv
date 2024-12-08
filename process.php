<?php

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
        $inputFile = 'data/input/upload_input.csv';

        // Caminho do arquivo de saída
        $outputFile = "data/output/output_{$action}.csv";

        if (file_exists($inputFile)) {

            // Verifica e ajusta a codificação do arquivo para UTF-8
            $fileContent = file_get_contents($inputFile);
            if (!mb_detect_encoding($fileContent, 'UTF-8', true)) {
                $fileContent = mb_convert_encoding($fileContent, 'UTF-8');
                file_put_contents($inputFile, $fileContent);
            }

            // Detectar delimitador
            $delimiter = detectDelimiter($inputFile);

            // Verificar se o BOM já existe, caso contrário, adicioná-lo
            $bom = "\xEF\xBB\xBF"; // BOM UTF-8
            $originalContent = file_get_contents($inputFile);

            if (substr($originalContent, 0, 3) !== $bom) {
                // Prepend BOM no início do conteúdo do arquivo
                $originalContent = $bom . $originalContent;
                file_put_contents($inputFile, $originalContent);
            }

            // Ler o arquivo CSV com o delimitador identificado
            $rows = array_map(fn($line) => str_getcsv($line, $delimiter), file($inputFile));
            $header = array_shift($rows); // Remove e obtém o cabeçalho
            $data = array_map(fn($row) => array_combine($header, $row), $rows);

            // Aplicar a formatação em todos os telefones
            foreach ($data as &$item) {
                $item['telefone'] = formatarTelefone($item['telefone']);
            }

            // Escreve os dados no arquivo de saída
            $file = fopen($outputFile, 'w');

            // Adicionar o cabeçalho ao arquivo CSV
            fputcsv($file, $header);

            // Guardar os dados no arquivo CSV
            foreach ($data as $row) {
                fputcsv($file, $row);
            }

            fclose($file);

            // Retorna uma resposta JSON válida
            echo json_encode([
                'message' => "Arquivo processado para $action com sucesso!",
                'file_url' => $outputFile // URL para download
            ]);
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

// Função para detectar delimitador do CSV
function detectDelimiter($filePath)
{
    $file = fopen($filePath, 'r');
    $line = fgets($file);
    fclose($file);

    $delimiters = [',', ';', '\t', '|'];
    $counts = [];

    foreach ($delimiters as $delimiter) {
        $counts[$delimiter] = substr_count($line, $delimiter);
    }

    return array_search(max($counts), $counts);
}

function detectCSVDelimiter($filePath)
{
    $file = fopen($filePath, 'r');

    if (!$file) {
        throw new Exception("Erro ao abrir o arquivo.");
    }

    // Delimitadores comuns
    $delimiters = [",", ";", "\t", "|"];

    $firstLine = fgets($file);
    fclose($file);

    if (!$firstLine) {
        throw new Exception("O arquivo está vazio ou não pode ser lido.");
    }

    $maxCount = 0;
    $detectedDelimiter = null;

    // Testando cada delimitador
    foreach ($delimiters as $delimiter) {
        $count = count(explode($delimiter, $firstLine));

        if ($count > $maxCount) {
            $maxCount = $count;
            $detectedDelimiter = $delimiter;
        }
    }

    return $detectedDelimiter;
}
