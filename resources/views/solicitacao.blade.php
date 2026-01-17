<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitação - {{ $quote->quote_number }}</title>
    <style>
        @page {
            margin: 0.5cm;
            size: A4 portrait;
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
            position: relative;
        }
        
        .logo-box {
            width: 120px;
            height: 50px;
            background-color: #0066CC;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12pt;
            padding: 5px;
        }
        
        .header-center {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
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
            font-size: 8pt;
            line-height: 1.4;
        }
        
        .header-right div {
            margin-bottom: 2px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 15px;
            font-size: 8pt;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-weight: bold;
            margin-bottom: 2px;
            color: #333;
        }
        
        .detail-value {
            color: #000;
            padding: 2px 0;
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
            padding: 4px;
            text-align: left;
        }
        
        .items-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        
        .items-table td {
            background-color: #fff;
        }
        
        .items-table td.number {
            text-align: right;
        }
        
        .observations {
            margin-top: 15px;
            font-size: 8pt;
        }
        
        .observations-label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .summary {
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            font-size: 9pt;
            font-weight: bold;
            border-top: 2px solid #000;
            padding-top: 5px;
        }
        
        .summary-item {
            margin-right: 20px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo-box">
            {{ $company->company ?? $company->name ?? 'Rialma S.A' }}
        </div>
        
        <div class="header-center">
            <h1>Solicitação</h1>
        </div>
        
        <div class="header-right">
            <div><strong>Empresa:</strong> {{ $company->company ?? $company->name ?? '-' }}</div>
            <div><strong>Usuário:</strong> {{ auth()->user()->nome_completo ?? auth()->user()->name ?? 'SOFTCOM' }}</div>
            <div><strong>Emissão:</strong> {{ now()->format('d/m/Y H:i:s') }}</div>
            <div><strong>Página:</strong> 1 de 1</div>
            <div><strong>N°:</strong> {{ $quote->id ?? '-' }}</div>
        </div>
    </div>

    <!-- Details Grid -->
    <div class="details-grid">
        <div class="detail-item">
            <span class="detail-label">Data da Solicitação:</span>
            <span class="detail-value">{{ $quote->requested_at ? \Carbon\Carbon::parse($quote->requested_at)->format('d/m/Y') : '-' }}</span>
        </div>
        
        <div class="detail-item">
            <span class="detail-label">Data Aprovação:</span>
            <span class="detail-value">{{ $quote->approved_at ? \Carbon\Carbon::parse($quote->approved_at)->format('d/m/Y') : '-' }}</span>
        </div>
        
        <div class="detail-item">
            <span class="detail-label">Previsão Entrega:</span>
            <span class="detail-value">{{ isset($quote->expected_delivery_date) && $quote->expected_delivery_date ? \Carbon\Carbon::parse($quote->expected_delivery_date)->format('d/m/Y') : '-' }}</span>
        </div>
        
        <div class="detail-item">
            <span class="detail-label">Solicitante:</span>
            <span class="detail-value">{{ $quote->requester_name ?? '-' }}</span>
        </div>
        
        <div class="detail-item">
            <span class="detail-label">Data da Cotação:</span>
            <span class="detail-value">
                @if($quote->current_status_slug === 'cotacao' || $quote->current_status_slug === 'finalizada')
                    {{ $quote->updated_at ? \Carbon\Carbon::parse($quote->updated_at)->format('d/m/Y') : '-' }}
                @else
                    -
                @endif
            </span>
        </div>
        
        <div class="detail-item">
            <span class="detail-label">Frente de Obra:</span>
            <span class="detail-value">{{ $quote->work_front ?? '-' }}</span>
        </div>
        
        <div class="detail-item">
            <span class="detail-label">Comprador:</span>
            <span class="detail-value">
                @if(isset($buyer))
                    {{ $buyer->nome_completo ?? $buyer->name ?? '-' }}
                @elseif($quote->buyer)
                    {{ $quote->buyer->nome_completo ?? $quote->buyer->name ?? '-' }}
                @else
                    -
                @endif
            </span>
        </div>
        
        <div class="detail-item">
            <span class="detail-label">Empresa:</span>
            <span class="detail-value">{{ $quote->company_name ?? ($company->company ?? $company->name ?? '-') }}</span>
        </div>
        
        <div class="detail-item">
            <span class="detail-label">Status Pedido:</span>
            <span class="detail-value">
                @if($quote->orders_count > 0)
                    SOLICITADO
                @else
                    -
                @endif
            </span>
        </div>
        
        <div class="detail-item">
            <span class="detail-label">Status Cotação:</span>
            <span class="detail-value">{{ strtoupper($quote->current_status_label ?? 'AGUARDANDO') }}</span>
        </div>
    </div>

    <!-- Observations -->
    @if($quote->observation)
    <div class="observations">
        <div class="observations-label">Observações:</div>
        <div class="detail-value">{{ $quote->observation }}</div>
    </div>
    @endif

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 12%;">Referência</th>
                <th style="width: 30%;">Mercadoria</th>
                <th style="width: 18%;">Aplicação</th>
                <th style="width: 6%;">UND</th>
                <th style="width: 6%;">QTD</th>
                <th style="width: 8%;">Dias</th>
                <th style="width: 20%;">Centro de Custo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td>{{ $item->reference ?? '-' }}</td>
                <td>{{ $item->description ?? '-' }}</td>
                <td>{{ $item->application ?? '-' }}</td>
                <td>{{ $item->unit ?? '-' }}</td>
                <td class="number">{{ number_format($item->quantity ?? 0, 0, ',', '.') }}</td>
                <td class="number">{{ $item->priority_days ?? '-' }}</td>
                <td>
                    @if($item->cost_center_code || $item->cost_center_description)
                        {{ trim(($item->cost_center_code ?? '') . ' ' . ($item->cost_center_description ?? '')) ?: '-' }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Summary -->
    <div class="summary">
        <div class="summary-item">
            <strong>Itens:</strong> {{ count($items) }}
        </div>
        <div class="summary-item">
            <strong>Volumes:</strong> {{ collect($items)->sum('quantity') ?? 0 }}
        </div>
    </div>
</body>
</html>

