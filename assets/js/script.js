$(document).ready(function () {
    // Mostra/oculta o dropdown de seleção
    $('#actions .btn-success').click(function () {
        $('#dropdown').toggle();
    });

    // Formatação para Bot Conversa ou Velip
    function formatCSV(actionType) {
        $.ajax({
            url: 'process.php',
            type: 'POST',
            data: { action: actionType },
            dataType: 'json',
            success: function (response) {
                alert(response.message);
            },
            error: function () {
                alert('Ocorreu um erro durante o processamento.');
            }
        });
    }

    // Ações para os botões
    $('#actions .btn-secondary').click(function () {
        formatCSV('botconversa');
    });

    $('#actions .btn-info').click(function () {
        formatCSV('velip');
    });

    // Evento para seleção no dropdown
    $('#accountDropdown').change(function () {
        alert('Conta selecionada: ' + $(this).val());
    });
});
