<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Empréstimos Finalizados</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding: 10px 0;
            background-color: #007bff;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 20px;
        }
        .content h2 {
            color: #333333;
        }
        .content p {
            color: #666666;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .table th, .table td {
            border: 1px solid #dddddd;
            padding: 8px;
            text-align: left;
        }
        .table th {
            background-color: #007bff;
            color: #ffffff;
        }
        .footer {
            text-align: center;
            padding: 10px 0;
            background-color: #f4f4f4;
            color: #666666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Relatório de Empréstimos Finalizados</h1>
        </div>
        <div class="content">
            <h2>Resumo</h2>
            <p>Os seguintes clientes finalizaram seus empréstimos:</p>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome do Cliente</th>
                        <th>Data de Início</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clientData as $client)
                        <tr>
                            <td>{{ $client[0] }}</td>
                            <td>{{ $client[1] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <h2>Custo Total</h2>
            <p>Estamos lhe enviando este email para informar que acabamos de confirmar o seu pagamento.</p>
                E para sua maior comodidade e segurança estamos disponibilizando também via Excel, um backup de todos os seus clientes e empréstimos em andamento.
                Mais uma vez agradecemos por utilizar a nossa plataforma.</p>
        </div>
        <div class="footer">
            <p>&copy; 2024 RJ EMPRESTIMOS. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
