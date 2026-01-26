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

        .header-emission-info {
            margin-top: 10px;
            font-size: 9pt;
            text-align: right;
            color: #666;
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

        .statement {
            margin-top: 30px;
            margin-bottom: 40px;
            font-size: 10pt;
            text-align: justify;
            line-height: 1.6;
        }

        .signature-section {
            margin-top: 60px;
            width: 100%;
        }

        .signature-row {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }

        .signature-box {
            display: table-cell;
            text-align: left;
            width: 50%;
            vertical-align: top;
            padding-right: 20px;
        }

        .signature-box:last-child {
            padding-right: 0;
        }

        .signature-label {
            font-size: 9pt;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            min-height: 30px;
            width: 100%;
            margin-top: 5px;
        }

        .signature-line.date {
            width: 200px;
            text-align: center;
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
            <div class="header-emission-info">
                @if(isset($local_emissao) && $local_emissao)
                    <strong>Local:</strong> {{ $local_emissao }}<br>
                @endif
                @if(isset($data_emissao) && $data_emissao)
                    <strong>Data:</strong> {{ $data_emissao }}
                @endif
                @if(isset($hora_emissao) && $hora_emissao)
                    <strong>Hora:</strong> {{ $hora_emissao }}
                @endif
            </div>
        </div>
    </div>

    <div class="company-info">
        <strong>{{ $company->id ?? '' }}</strong> - {{ strtoupper($company->company ?? '') }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">Item</th>
                <th style="width: 15%;">Número</th>
                <th style="width: 30%;">Descrição Completa</th>
                <th style="width: 10%;">Filial</th>
                <th style="width: 10%;">Local</th>
                <th style="width: 15%;">Situação do Patrimônio</th>
                <th style="width: 15%;">Valor</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalValue = 0;
            @endphp
            @foreach($assets as $index => $asset)
            @php
                $value = floatval($asset->value_brl ?? 0);
                $totalValue += $value;
            @endphp
            <tr>
                <td class="number">{{ $index + 1 }}</td>
                <td>{{ $asset->asset_number ?? '' }}</td>
                <td>{{ strtoupper($asset->description ?? '') }}</td>
                <td>{{ $asset->branch->name ?? '-' }}</td>
                <td>{{ $asset->location->name ?? '-' }}</td>
                <td>{{ strtoupper($asset->status ?? 'incluido') }}</td>
                <td class="number">R$ {{ number_format($value, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <strong>Quantidade total:</strong> {{ count($assets) }}<br>
        <strong>Valor total:</strong> R$ {{ number_format($totalValue, 2, ',', '.') }}
    </div>

    <div class="statement">
        <strong>TERMO DE RESPONSABILIDADE</strong><br><br>
        Eu, {{ strtoupper($responsible->name ?? '') }}, responsabilizo-me pela conservação e controle dos bens patrimoniais constantes da relação anexa, relatório de Inventário de Patrimônio emitido em ________________ através de processamento de dados, comprometendo-me, ao mesmo tempo a prestar esclarecimentos ao setor de patrimônio, sobre possíveis mudanças, desaparecimentos ou quaisquer danos que venham ocorrer sobre esses bens.
    </div>

    <div style="margin-top: 20px; margin-bottom: 10px; width: 100%; border-bottom: 1px solid #000; min-height: 30px;"></div>

    <div class="signature-section">
        <div class="signature-row">
            <div class="signature-box">
                <div class="signature-label">Local</div>
                <div class="signature-line"></div>
            </div>
            <div class="signature-box">
                <div class="signature-label">Data</div>
                <div class="signature-line date"></div>
            </div>
        </div>
        <div class="signature-row">
            <div class="signature-box">
                <div class="signature-label">Assinatura do Responsável</div>
                <div class="signature-line"></div>
            </div>
            <div class="signature-box">
                <div class="signature-label">Depto./Setor Patrimônio</div>
                <div class="signature-line"></div>
            </div>
        </div>
    </div>
</body>
</html>

