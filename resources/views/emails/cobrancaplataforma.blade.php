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

        .table th,
        .table td {
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

        .p15px {
            margin-top: -15px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Cobrança da Plataforma</h1>
        </div>
        <div class="content">
            <h2>Código para pagamento Pix:</h2>
            <div style="display:flex; justify-content: center; align-itens: center;">
                <img src="data:image/png;base64, {!! base64_encode($qrCode) !!}" alt="QR Code">
            </div>
            <h2>Detalhes</h2>
            <p>Seu plano contratado: <b>{{ $locacao->type }}</b>.</p>
            <p>O custo total para esses empréstimos foi de <b>{{ 'R$ ' . number_format($locacao->valor, 2, ',', '.') }}</b>.</p>
            <h2>Atenção</h2>
            <p>A partir de agora a renovação da sua licença acontecerá de forma automatica e no mesmo momento em que
                voce fizer o
                pagamento, portando para que isso aconteça você precisará efetuar o pagamento da sua fatura através do
                código pix
                gerado (Código para pagamento Pix) ou através da leitura do QRcode gerado.</p>

            <h2>O que você precisa saber!</h2>
            <p>Após pagamento de fatura pela chave Pix O SISTEMA RENOVARÁ SUA LICENÇA DE FORMA
                AUTOMATICA!</p>

            <h2>Resumo {{ count($emprestimosData) }} Empréstimos</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Data de lançamento</th>
                        <th>Nome do Cliente</th>
                        <th>Valor do Empréstimo</th>
                        <th>Lucro</th>
                        <th>Juros</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($emprestimosData as $emprestimo)
                        <tr>
                            <td>{{ $emprestimo[0] }}</td>
                            <td>{{ $emprestimo[1] }}</td>
                            <td>{{ $emprestimo[2] }}</td>
                            <td>{{ $emprestimo[3] }}</td>
                            <td>{{ $emprestimo[4] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <h4>Total Valor Emprestado</h2>
            <p class="p15px">R$ {{ number_format($totalValorEmprestado, 2, ',', '.') }}</p>
            <h4>Total Lucro</h2>
            <p class="p15px">R$ {{ number_format($totalLucro, 2, ',', '.') }}</p>
            <h4>Média de Juros</h2>
            <p class="p15px">R$ {{ number_format($mediaJuros, 2, ',', '.') }}</p>


        </div>
        <div class="footer">
            <p>&copy; 2024 RJ EMPRÉSTIMOS. Todos os direitos reservados.</p>
        </div>
    </div>
</body>

</html>
