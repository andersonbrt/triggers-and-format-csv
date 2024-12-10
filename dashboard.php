<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// Pastas a serem limpas
$foldersToClean = [
    'data/input',
    'data/output'
];

// Deletar arquivos das pastas
deleteFilesFromFolders($foldersToClean);

function deleteFilesFromFolders(array $folders)
{
    foreach ($folders as $folder) {
        if (!is_dir($folder)) {
            continue;
        }

        // Listar todos os arquivos dentro da pasta (exceto '.' e '..')
        $files = glob($folder . '/*'); // Use * para pegar todos os arquivos e subpastas

        foreach ($files as $file) {
            if (is_file($file)) {
                // Deleta o arquivo
                if (unlink($file)) {
                }
            } elseif (is_dir($file)) {
                // Remove subpastas recursivamente
                deleteFolder($file);
            }
        }
    }
}

function deleteFolder($folder)
{
    $files = array_diff(scandir($folder), ['.', '..']);
    foreach ($files as $file) {
        $path = $folder . DIRECTORY_SEPARATOR . $file;
        is_dir($path) ? deleteFolder($path) : unlink($path);
    }
    return rmdir($folder);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- CSS do Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- jQuery deve ser carregado primeiro -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- CSS do Select2 (versão 4.0.13) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet">
    <!-- JS do Select2 (versão 4.0.13) -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>

    <!-- JS do Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">

</head>


<body>
    <div class="container mt-5 dashboard">

        <h2>Bem-vindo, <?php echo $_SESSION['user']; ?>
            <!-- Botão de Logout -->
            <a href="logout.php" class="btn btn-danger">Sair</a>
            <!-- Botão de Logout -->
            <button class="btn btn-secondary" onclick="atualizarPagina()">Reeniciar</button>
        </h2>
        <form id="uploadForm" enctype="multipart/form-data" action="process.php" method="POST">
            <div class="mb-3">
                <label for="csvFile" class="form-label">Carregar CSV</label>
                <input type="file" name="csvFile" id="csvFile" class="form-control" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-primary btn-send">Enviar</button>
        </form>
        <hr>
        <div id="actions" style="display: none;">
            <button class="btn btn-dark" onclick="showDropdown('send-botconversa')">Enviar para Bot Conversa</button>
            <button class="btn btn-info" onclick="showDropdown('botconversa')">Formatar para BotConversa</button>
            <button class="btn btn-warning" onclick="formatCSV('velip')">Formatar para Velip</button>

            <!-- Área para exibir mensagem de preparação de formatação de dados -->
            <div id="responseMessageFormat" style="display: none;margin-top:10px;">
                <p id="messageTextFormat"></p>
            </div>

            <div id="dropdown" class="mt-2" style="display: none;">
                <select id="accountDropdown" class="form-select">
                    <option selected="selected" disabled>-</option>
                    <option value="conta0">Conta Matriz</option>
                    <option value="conta1">Conta 1</option>
                    <option value="conta2">Conta 2</option>
                    <option value="conta3">Conta 3</option>
                </select>
            </div>
            <div id="multiSelectContainer" style="display: none;margin-top:10px;">
                <label for="multiSelect" class="form-label">Selecione as Etiquetas:</label>
                <select id="multiSelect" class="form-select select2" multiple="multiple" style="width: 100%;">
                    <!-- As opções serão preenchidas dinamicamente -->
                </select>
            </div>

        </div>
        <hr>
        <!-- Modais -->
        <div class="modal fade" id="responseModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="responseModalTitle">Mensagem</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p id="responseModalMessage"></p>
                        <a id="downloadButton" class="btn btn-success" style="display: none;" href="#" download>Baixar Arquivo</a>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Área para exibir mensagem e botão de webhook -->
        <div id="responseMessageWebhook" style="display: none;">
            <a id="webhookButton" class="btn btn-success" style="display: none;" onclick="enviarParaWebhook()">Enviar Dados</a>
            <a id="formatButton" class="btn btn-success" style="display: none;">Formatar</a>
        </div>

        <!-- Barra de Progresso -->
        <div id="progress-container" style="display: none;">
            <div id="progress-bar" class="progress" style="height: 30px;margin-top:10px;">
                <div id="progress" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                    0%
                </div>
            </div>
        </div>

    </div>

    <script>
        async function fetchAccountData(accountId) {
            try {
                const response = await fetch('etiquetas.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        accountId
                    }),
                });

                if (!response.ok) {
                    throw new Error('Erro ao buscar dados!');
                }

                const options = await response.json();
                if (options.error) {
                    console.error('Erro da API externa:', options.error);
                    return;
                }

                renderSelectOptions(options);
            } catch (error) {
                console.error('Erro ao buscar dados:', error);
            }
        }

        function renderSelectOptions(data) {
            const $multiSelect = $('#multiSelect');
            //const selectedIds = $(this).val();
            $multiSelect.empty();

            // Popular o Select2 com as opções da API
            data.forEach(option => {
                const newOption = new Option(option.name, option.id);
                $multiSelect.append(newOption);
            });

            $multiSelect.trigger('change');
        }

        $(document).ready(function() {
            // Inicializa o Select2
            $('.select2').select2({
                placeholder: "Digite o nome da Etiqueta",
                allowClear: true,
            });

            // Exibe e preenche a lista de múltiplas escolhas com base na conta selecionada
            $('#accountDropdown').on('change', function() {
                const selectedAccount = $(this).val();
                if (selectedAccount) {
                    // Chama a função para buscar dados no servidor
                    fetchAccountData(selectedAccount);
                    $('#multiSelectContainer').show(); // Exibe o container da lista de múltiplas escolhas
                } else {
                    $('#multiSelectContainer').hide(); // Oculta caso nenhuma conta esteja selecionada
                }
            });
        });


        $(document).ready(function() {
            // Sempre que houver uma mudança no campo de arquivo, exiba o botão de envio
            $('#csvFile').on('change', function() {
                $('.btn-send').text('Enviar');
                $('.btn-send').show(); // Exibe o botão de envio
                $('#actions').hide(); // Esconde a área de ações
                $('#webhookButton').hide(); // Esconde botao de envio de webhook
                //$('.btn-dark').removeClass('disabled');
            });
        });

        // Função para exibir o modal com uma mensagem e opcionalmente um botão de download
        function showModal(title, message, downloadUrl = null) {
            $('#responseModalTitle').text(title);
            $('#responseModalMessage').text(message);

            if (downloadUrl) {
                // Adiciona o botão de download ao modal se houver uma URL
                $('#responseModalDownload').show();
                $('#downloadButton').attr('href', downloadUrl);
                $('#downloadButton').show();
            } else {
                // Esconde o botão de download se não houver uma URL
                $('#responseModalDownload').hide();
                $('#downloadButton').hide();
            }

            var modal = new bootstrap.Modal(document.getElementById('responseModal'));
            modal.show();

            // Recarregar a página ao fechar o modal clicando fora ou no botão de fechamento
            $('#responseModal').on('hidden.bs.modal', function() {
                atualizarPagina();
            });
        }

        $('#uploadForm').on('submit', function(e) {
            e.preventDefault(); // Impede o envio padrão do formulário
            $('.btn-send').text('Enviando...');
            $('#webhookButton').show();
            var formData = new FormData(this);

            $.ajax({
                url: 'process.php', // A URL que processa o upload
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    var data = JSON.parse(response);
                    // Se o upload for bem-sucedido, esconde o botão de envio e mostra as actions
                    if (data.message === 'Arquivo carregado com sucesso!') {
                        $('.btn-send').hide(); // Esconde o botão de enviar
                        $('#actions').show(); // Torna as actions visíveis
                    }
                }
            });
        });

        function showDropdown(action) {

            $(document).ready(function() {
                // Sempre que houver uma mudança no campo de arquivo, exiba o botão de envio
                $('#csvFile').on('change', function() {
                    $('.btn-send').text('Enviar');
                    $('.btn-send').show(); // Exibe o botão de envio
                    $('#actions').hide(); // Esconde a área de ações
                    $('#webhookButton').hide(); // Esconde botao de envio de webhook

                });
            });

            // Verifica se action é botconversa
            if (action === 'botconversa') {
                $('.btn-info').addClass('disabled');
                //$('.btn-info').text('Formatando dados para BotConversa...');
                $('.btn-dark').hide();
                $('.btn-warning').hide();
                $('#formatButton').show();
                $('#webhookButton').hide();
            }

            // Verifica se action é send-botconversa
            if (action === 'send-botconversa') {
                $('.btn-dark').addClass('disabled');
                $('.btn-info').hide();
                $('.btn-warning').hide();
                $('#webhookButton').show();
                $('#formatButton').hide();
            }

            // Desabilita o botão sem ação ao carregá-lo
            $('#responseMessageFormat').show();
            $('#messageTextFormat').text('Preparando dados...');
            $('#messageTextFormat').show();

            // Exibe a mensagem e o botão para webhook
            $('#responseMessageWebhook').show();
            $('#messageTextFormat').text('Selecione a conta:');
            $('#dropdown').toggle();

            formatButton.addEventListener('click', function() {
                formatCSV(action);
            });

            webhookButton.addEventListener('click', function() {
                formatCSV(action);
            });
        }

        // Função para formataCSV
        function formatCSV(type) {
            var selectedOptions = $('#multiSelect').select2('data'); // Obter dados do Select2
            var selectedNames = selectedOptions.map(option => option.text); // Extrair apenas os nomes

            var formData = new FormData();
            formData.append('action', type);
            formData.append('etiquetas', selectedNames); // Enviar os nomes como JSON
            formData.append('csvFile', $('#csvFile')[0].files[0]);

            $.ajax({
                url: 'process.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,

                success: function(response) {
                    var data = JSON.parse(response);

                    if (data.file_url) {

                        // Verifica se action é velip ou botconversa
                        if (formData.get('action') === 'botconversa' || formData.get('action') === 'velip') {

                            // Exibe o modal com o botão de download dentro
                            showModal('Sucesso', data.message, data.file_url);
                        } else {

                            // Exibe a mensagem e o botão para webhook
                            $('#responseMessageWebhook').show();
                            $('#messageTextFormat').text('Selecione a conta:');
                            $('#dropdown').toggle();
                            $('#webhookButton').show(); // Exibe o botão de webhook
                        }
                    } else {
                        // Exibe o modal sem botão de download em caso de erro
                        showModal('Erro', 'Ocorreu um problema ao processar o arquivo.');
                    }
                },
                error: function() {
                    // Exibe o modal em caso de erro na requisição
                    showModal('Erro', 'Não foi possível processar a solicitação.');
                }
            });
        }

        // Função para atualizar a página
        function atualizarPagina() {
            location.reload();
        }

        /// Função para enviar dados para webhook
        function enviarParaWebhook() {
            // Exibe a barra de progresso
            $('#progress-container').show();
            $('#webhookButton').text('Enviando...');
            $('#progress').css('width', '0%');
            $('#progress').text('0%');

            // Define o accountId selecionado
            var accountId = $('#accountDropdown').val();
            // Obter IDs selecionados no campo multiSelect
            var selectedIds = $('#multiSelect').val() || [];

            var progress = 0;
            var interval;

            function atualizarProgresso() {
                if (progress < 25) {
                    progress += 1;
                    atualizarBarra(progress);
                    setTimeout(atualizarProgresso, 500);
                } else if (progress < 50) {
                    progress += 1;
                    atualizarBarra(progress);
                    setTimeout(atualizarProgresso, 1000);
                } else if (progress < 75) {
                    progress += 1;
                    atualizarBarra(progress);
                    setTimeout(atualizarProgresso, 5000);
                } else if (progress < 85) {
                    progress += 1;
                    atualizarBarra(progress);
                    setTimeout(atualizarProgresso, 10000);
                } else if (progress < 100) {
                    progress += 1;
                    atualizarBarra(progress);
                    setTimeout(atualizarProgresso, 20000);
                } else {
                    clearTimeout(interval);
                }
            }

            function atualizarBarra(progress) {
                $('#progress').css('width', progress + '%');
                $('#progress').text(progress + '%');
            }

            atualizarProgresso();

            $.ajax({
                url: 'webhook.php',
                type: 'POST',
                data: {
                    accountId: accountId,
                    selectedIds: selectedIds,
                },
                beforeSend: function() {
                    // A barra começa com 0%
                    $('#progress').css('width', '0%');
                    $('#progress').text('0%');
                },
                success: function(response) {
                    var data = JSON.parse(response);
                    progress = 100;
                    atualizarBarra(progress);

                    // Exibe o modal com a mensagem de sucesso
                    showModal('Sucesso', data.message);
                },
                error: function() {
                    progress = 100;
                    atualizarBarra(progress);
                    $('#progress').text('Erro');

                    // Exibe o modal com a mensagem de erro
                    showModal('Erro', 'Ocorreu um erro ao enviar os dados para o webhook!');
                },
                complete: function() {
                    // Opcional: esconder a barra de progresso após a execução
                    setTimeout(function() {
                        $('#progress-container').hide();
                        $('#webhookButton').hide();
                        $('#dropdown').hide();
                        $('.btn-dark').hide();
                    }, 100); // Esconde após 100ms
                }
            });
        }
    </script>
</body>

</html>