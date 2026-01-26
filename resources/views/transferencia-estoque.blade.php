<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transferência - {{ $numero_documento }}</title>
    <style>
        @page {
            margin: 0.5cm;
            size: A4 landscape;
        }
        
        * {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            margin: 0;
            padding: 0;
            color: #000;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
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
        
        .header-center {
            flex: 1;
            text-align: center;
        }
        
        .header-center h1 {
            font-size: 18pt;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }
        
        .header-right {
            text-align: right;
            font-size: 9pt;
        }
        
        .info-section {
            margin-bottom: 15px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 5px;
            font-size: 9pt;
        }
        
        .info-label {
            font-weight: bold;
            width: 150px;
        }
        
        .info-value {
            flex: 1;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 8pt;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }
        
        .items-table th {
            background-color: #d3d3d3;
            font-weight: bold;
            text-align: center;
        }
        
        .items-table td {
            text-align: left;
        }
        
        .items-table td.numero {
            text-align: right;
        }
        
        .items-table td.centro {
            text-align: center;
        }
        
        .signature-section {
            display: table;
            width: 100%;
            margin-top: 30px;
            padding-top: 20px;
        }
        
        .signature-box {
            display: table-cell;
            text-align: center;
            width: 33.33%;
            vertical-align: top;
            padding: 0 5px;
        }
        
        .signature-line {
            padding-top: 5px;
            margin-bottom: 5px;
            min-height: 60px;
            text-align: center;
        }
        
        .signature-name-line {
            border-bottom: 1px solid #000;
            margin-top: 5px;
            padding-bottom: 5px;
            min-height: 20px;
        }
        
        .signature-label {
            font-size: 9pt;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="https://www.gruporialma.com.br/assets/logo_sem_fundo-Dbkuj9iO.png" alt="Logo Rialma" />
        </div>
        <div class="header-center">
            <h1>Transferência</h1>
        </div>
        <div class="header-right">
            <div><strong>{{ $company->name ?? 'EMPRESA' }}</strong></div>
            <div>Usuário: {{ $user->nome_completo ?? 'N/A' }}</div>
            <div>Emissão: {{ $data_transferencia }} {{ $hora_transferencia }}</div>
            <div>Página: <span id="pageNum"></span> de <span id="totalPages"></span></div>
            <div>N°: {{ $numero_documento }}</div>
        </div>
    </div>

    <div class="info-section">
        <div class="info-row">
            <span class="info-label">Data:</span>
            <span class="info-value">{{ $data_transferencia }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Operação:</span>
            <span class="info-value">SAÍDA</span>
        </div>
        <div class="info-row">
            <span class="info-label">Tipo:</span>
            <span class="info-value">TRANSFERÊNCIA</span>
        </div>
        <div class="info-row">
            <span class="info-label">Loja Origem:</span>
            <span class="info-value">{{ $local_origem->name ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Número Origem:</span>
            <span class="info-value">{{ $local_origem->code ?? '0' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Loja Destino:</span>
            <span class="info-value">{{ $local_destino->name ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Observações:</span>
            <span class="info-value">{{ $observacao ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">MOTORISTA:</span>
            <span class="info-value">{{ $driver_name ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">PLACA:</span>
            <span class="info-value">{{ $license_plate ?? 'N/A' }}</span>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 8%;">Código</th>
                <th style="width: 8%;">Referência</th>
                <th style="width: 40%;">Mercadoria</th>
                <th style="width: 8%;">Marca</th>
                <th style="width: 8%;">Modelo</th>
                <th style="width: 8%;">Observação</th>
                <th style="width: 10%;">Quantidade</th>
                <th style="width: 5%;">Preço Unitário</th>
                <th style="width: 5%;">Preço Total</th>
            </tr>
        </thead>
        <tbody>
            @php
                $itemNumero = 1;
            @endphp
            @foreach($itens as $item)
                <tr>
                    <td class="centro">{{ $item['codigo'] ?? '-' }}</td>
                    <td class="centro">{{ $item['referencia'] ?? $item['codigo'] ?? '-' }}</td>
                    <td>{{ $item['descricao'] ?? '-' }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="numero">{{ number_format((float)($item['quantidade'] ?? 0), 2, ',', '.') }}</td>
                    <td class="numero">{{ number_format((float)($item['preco_unitario'] ?? 0), 2, ',', '.') }}</td>
                    <td class="numero">{{ number_format((float)($item['preco_total'] ?? 0), 2, ',', '.') }}</td>
                </tr>
                @php
                    $itemNumero++;
                @endphp
            @endforeach
        </tbody>
    </table>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-name-line"></div>
            <div class="signature-label">ALMOXARIFADO ORIGEM</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-name-line"></div>
            <div class="signature-label">MOTORISTA / PLACA / CARRO</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-name-line"></div>
            <div class="signature-label">RECEBEDOR DO ALMOXARIFADO</div>
        </div>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $text = "Página {PAGE_NUM} de {PAGE_COUNT}";
            $size = 9;
            $font = $fontMetrics->getFont("Arial");
            $width = $fontMetrics->get_text_width($text, $font, $size) / 2;
            $x = ($pdf->get_width() - $width) / 2;
            $y = $pdf->get_height() - 35;
            $pdf->page_text($x, $y, $text, $font, $size);
        }
    </script>
</body>
</html>

