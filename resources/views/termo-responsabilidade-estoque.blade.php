<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TERMO DE RESPONSABILIDADE</title>
    <style>
        @page {
            margin: 1cm;
            size: A4 portrait;
        }
        
        * {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            margin: 0;
            padding: 0;
            color: #000;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .logo {
            width: 120px;
            height: 60px;
            background-color: rgb(30, 58, 138);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            border-radius: 8px;
            overflow: hidden;
        }

        .logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .header-title {
            flex: 1;
            text-align: center;
            margin: 0 20px;
        }

        .header-title h1 {
            font-size: 16pt;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }

        .header-right {
            text-align: right;
            font-size: 9pt;
        }

        .company-info {
            margin-bottom: 20px;
            font-size: 10pt;
        }

        .company-info strong {
            display: inline-block;
            min-width: 100px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 9pt;
        }

        table th,
        table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }

        table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }

        table td {
            text-align: left;
        }

        .number {
            text-align: right;
        }

        .summary {
            margin-top: 20px;
            margin-bottom: 30px;
            font-size: 10pt;
        }

        .summary strong {
            display: inline-block;
            min-width: 150px;
        }

        .statement {
            margin-top: 30px;
            margin-bottom: 40px;
            font-size: 10pt;
            text-align: justify;
            line-height: 1.6;
        }

        .signature-section {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .signature-box {
            flex: 1;
            text-align: center;
            padding-top: 60px;
            border-top: 1px solid #000;
        }

        .signature-box label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 9pt;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="https://www.gruporialma.com.br/assets/logo_sem_fundo-Dbkuj9iO.png" alt="Logo Rialma" />
        </div>
        <div class="header-title">
            <h1>TERMO DE RESPONSABILIDADE</h1>
        </div>
        <div class="header-right">
            Página 1 de 1
        </div>
    </div>

    <div class="company-info">
        <strong>{{ $company->id ?? '' }}</strong> - {{ strtoupper($company->company ?? '') }}<br>
        @if(isset($items[0]['location']) && $items[0]['location'])
            <strong>{{ $items[0]['location']->code ?? '' }}</strong> - {{ strtoupper($items[0]['location']->name ?? '') }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">Item</th>
                <th style="width: 25%;">Descrição Completa</th>
                <th style="width: 10%;">Marca</th>
                <th style="width: 10%;">Modelo</th>
                <th style="width: 8%;">Tag</th>
                <th style="width: 8%;">Dimensão</th>
                <th style="width: 12%;">Centro de Custo</th>
                <th style="width: 15%;">Responsável</th>
                <th style="width: 7%;">Número de Série</th>
                <th style="width: 5%;">Capacidade</th>
                <th style="width: 10%;">Situação do Patrimônio</th>
                <th style="width: 10%;">Valor</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
            <tr>
                <td class="number">{{ $index + 1 }}</td>
                <td>{{ strtoupper($item['product']->description ?? '') }}</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>{{ strtoupper($solicitante->nome_completo ?? $solicitante->name ?? '') }}</td>
                <td>-</td>
                <td>-</td>
                <td>I - INCLUIDO</td>
                <td class="number">R$ 0,00</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <strong>Quantidade total:</strong> {{ count($items) }}<br>
        <strong>Valor total:</strong> R$ 0,00
    </div>

    <div class="statement">
        <strong>TERMO DE RESPONSABILIDADE</strong><br><br>
        Eu, {{ strtoupper($solicitante->nome_completo ?? $solicitante->name ?? '') }}, responsabilizo-me pela conservação e controle dos bens patrimoniais constantes da relação anexa, relatório de Inventário de Patrimônio emitido em ____________________.
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <label>Local</label>
        </div>
        <div class="signature-box">
            <label>Data</label>
        </div>
        <div class="signature-box">
            <label>Assinatura do Responsável</label>
        </div>
        <div class="signature-box">
            <label>Depto./Setor Patrimônio</label>
        </div>
    </div>
</body>
</html>

