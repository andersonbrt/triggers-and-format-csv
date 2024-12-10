<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Configuração de diretórios
    $uploadDir = 'data/input/';
    $inputFile = $uploadDir . 'upload_input.csv';

    if (isset($_FILES['csvFile']) && !isset($_POST['action'])) {
        // Upload do arquivo
        $response = move_uploaded_file($_FILES['csvFile']['tmp_name'], $inputFile)
            ? ['message' => 'Arquivo carregado com sucesso!']
            : ['message' => 'Erro ao carregar o arquivo.'];

        echo json_encode($response);
        exit;
    }

    if (isset($_POST['action'])) {

        $action = $_POST['action'] ?? 'default';
        $etiquetas = (string)$_POST['etiquetas'] ?? '';
        $etiquetas = str_replace(',', ', ', $etiquetas);
        $outputFile = "data/output/output_{$action}.csv";

        if (!file_exists($inputFile)) {
            die(json_encode(['message' => 'Arquivo de entrada não encontrado!']));
        }

        // Verificar e corrigir codificação UTF-8 e BOM
        ensureUTF8WithBOM($inputFile);

        // Detectar delimitador e ler o arquivo
        $delimiter = detectDelimiter($inputFile);
        $rows = array_map(fn($line) => str_getcsv($line, $delimiter), file($inputFile));

        $header = array_map('trim', array_shift($rows)); // Limpa espaços do cabeçalho
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header); // Remove BOM
        $data = array_map(fn($row) => array_combine($header, $row), $rows);

        // Formatar telefones
        array_walk($data, fn(&$row) => $row['telefone'] = formatarTelefone($row['telefone']));

        // Criar arquivo de saída
        $file = fopen($outputFile, 'w');

        if ($action === 'botconversa' || $action === 'send-botconversa') {
            $newHeader = ['telefone', 'nome', 'etiquetas'];
            fputcsv($file, $newHeader);
            foreach ($data as $row) {
                fputcsv($file, [
                    'telefone' => $row['telefone'],
                    'nome' => $row['nome'] ?? '',
                    'etiquetas' => $etiquetas
                ]);
            }
        } else {
            $newHeader = ['NOME', 'DDI', 'DDD', 'NUMERO', 'PRIORIDADE', 'EXTRA1', 'EXTRA2', 'COD_CLI', 'DATA'];
            fputcsv($file, $newHeader);
            foreach ($data as $row) {
                fputcsv($file, [
                    'NOME' => $row['nome'] ?? '',
                    'DDI' => '',
                    'DDD' => '',
                    'NUMERO' => $row['telefone'],
                    'PRIORIDADE' => '',
                    'EXTRA1' => '',
                    'EXTRA2' => '',
                    'COD_CLI' => '',
                    'DATA' => ''
                ]);
            }
        }

        fclose($file);

        echo json_encode([
            'message' => "Arquivo processado para $action com sucesso!",
            'file_url' => $outputFile
        ]);
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

// Função para detectar delimitador de CSV
function detectDelimiter($filePath)
{
    $line = fgets(fopen($filePath, 'r'));
    $delimiters = [',', ';', '\t', '|'];

    $counts = array_map(fn($delim) => substr_count($line, $delim), $delimiters);
    return $delimiters[array_search(max($counts), $counts)];
}

// Função para garantir UTF-8 com BOM
function ensureUTF8WithBOM($filePath)
{
    $content = file_get_contents($filePath);
    if (!mb_detect_encoding($content, 'UTF-8', true)) {
        $content = mb_convert_encoding($content, 'UTF-8');
    }
    if (substr($content, 0, 3) !== "\xEF\xBB\xBF") {
        $content = "\xEF\xBB\xBF" . $content;
    }
    file_put_contents($filePath, $content);
}
