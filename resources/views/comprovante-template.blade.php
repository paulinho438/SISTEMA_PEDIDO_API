<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante de Transferência</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #000;
            line-height: 1.5;
        }

        .comprovante {
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 10px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 30px;
            margin: 0;
            font-weight: bold;
        }

        .header p {
            font-size: 12px;
            color: #666;
        }

        .section {
            margin-bottom: 15px;
            display:flex;
            justify-content: start;
            align-items:center;
            justify-content: space-between;

        }

        .section2 {
            margin-bottom: 15px;


        }

        .section h2 {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .section p {
            margin: 0;
            font-size: 14px;


        }

        .section-divider {
            margin: 15px 0;
            border-bottom: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="comprovante">
        <div class="header">
            <h1>Comprovante de transferência</h1>
            <p>{{ $data_hora }}</p>
        </div>

        <div class="section">
            <h2>Valor</h2>
            <p>R$ {{ number_format($valor, 2, ',', '.') }}</p>
        </div>

        <div class="section">
            <h2>Tipo de transferência</h2>
            <p>{{ $tipo_transferencia }}</p>
        </div>

        <div class="section">
            <h2>Descrição</h2>
            <p>{{ $descricao ?? 'Informação/Descrição' }}</p>
        </div>

        <div class="section-divider"></div>

        <h2>Destino</h2>
        <div class="section2">
            <p><strong>Nome:</strong> {{ $destino_nome }}</p>
            <p><strong>CPF:</strong> {{ $destino_cpf }}</p>
            <p><strong>Chave Pix:</strong> {{ $destino_chave_pix }}</p>
            <p><strong>Instituição:</strong> {{ $destino_instituicao }}</p>
            <p><strong>Banco:</strong> {{ $destino_banco }}</p>
            <p><strong>Agencia:</strong> {{ $destino_agencia }}</p>
            <p><strong>Conta:</strong> {{ $destino_conta }}</p>
            <p><strong>Id da transação:</strong> {{ $id_transacao }}</p>
        </div>

        <div class="section-divider"></div>

        <h2>Origem</h2>
        <div class="section2">
            <p><strong>Nome:</strong> {{ $origem_nome }}</p>
            <p><strong>CNPJ:</strong> {{ $origem_cnpj }}</p>
            <p><strong>Instituição:</strong> {{ $origem_instituicao }}</p>
        </div>
    </div>
</body>
</html>
