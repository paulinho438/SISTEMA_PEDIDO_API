<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TERMO DE RESPONSABILIDADE - FERRAMENTAS</title>
    <style>
        @page { margin: 1.5cm; size: A4 portrait; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { font-family: Arial, sans-serif; font-size: 10pt; margin: 0; padding: 15px; color: #000; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h1 { font-size: 14pt; margin: 0; text-transform: uppercase; }
        .header .numero { font-size: 11pt; font-weight: bold; margin-top: 5px; }
        .info-block { margin-bottom: 18px; font-size: 10pt; }
        .info-block strong { display: inline-block; min-width: 140px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 9pt; }
        table th, table td { border: 1px solid #000; padding: 6px 8px; text-align: left; }
        table th { background-color: #e8e8e8; font-weight: bold; text-align: center; }
        table td.number { text-align: right; }
        .statement { margin-top: 25px; margin-bottom: 30px; font-size: 10pt; text-align: justify; line-height: 1.6; }
        .signature-section { margin-top: 50px; }
        .signature-box { margin-bottom: 25px; }
        .signature-label { font-size: 9pt; font-weight: bold; margin-bottom: 4px; }
        .signature-line { border-bottom: 1px solid #000; min-height: 28px; width: 100%; }
        .footer-info { margin-top: 30px; font-size: 9pt; color: #444; }
    </style>
</head>
<body>
    <div class="header">
        <h1>TERMO DE RESPONSABILIDADE</h1>
        <p style="margin: 5px 0 0 0;">FERRAMENTAS / EQUIPAMENTOS</p>
        <div class="numero">Nº {{ $termo->numero ?? '' }}</div>
    </div>

    <div class="info-block">
        <strong>Responsável:</strong> {{ strtoupper($termo->responsible_name ?? '') }}<br>
        @if(!empty($termo->cpf))
        <strong>CPF:</strong> {{ $termo->cpf }}<br>
        @endif
        @if(!empty($termo->project))
        <strong>Obra/Projeto:</strong> {{ $termo->project }}<br>
        @endif
        <strong>Local de retirada:</strong> {{ $location->name ?? $location->code ?? '-' }}<br>
        <strong>Data de emissão:</strong> {{ $data_emissao ?? '' }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 6%;">Item</th>
                <th style="width: 55%;">Descrição (Ferramenta/Equipamento)</th>
                <th style="width: 15%;">Quantidade</th>
                <th style="width: 12%;">Unidade</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
            <tr>
                <td class="number">{{ $index + 1 }}</td>
                <td>{{ $item['description'] ?? '-' }}</td>
                <td class="number">{{ $item['quantity'] ?? '0' }}</td>
                <td>{{ $item['unit'] ?? 'UN' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="statement">
        <strong>DECLARAÇÃO</strong><br><br>
        O responsável acima declara ter recebido os itens (ferramentas/equipamentos) relacionados neste termo, comprometendo-se a zelar por sua conservação e utilização adequada até o término da obra/projeto. Ao final, os itens deverão ser devolvidos ao estoque no mesmo local de retirada, para que sejam registradas as entradas correspondentes e o termo seja encerrado.
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-label">Assinatura do Responsável</div>
            <div class="signature-line"></div>
        </div>
        <div class="signature-box">
            <div class="signature-label">Data</div>
            <div class="signature-line" style="width: 180px;"></div>
        </div>
    </div>

    <div class="footer-info">
        Emitido em {{ $data_emissao ?? '' }}. Ao devolver os itens, o termo será encerrado e as quantidades retornarão ao estoque.
    </div>
</body>
</html>
