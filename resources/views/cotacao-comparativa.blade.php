<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quadro Comparativo de Preços - {{ $quote->quote_number }}</title>
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
            font-size: 8pt;
            margin: 0;
            padding: 0;
            color: #000;
        }
        
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
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
            margin-right: 20px;
        }
        
        .logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .header-title {
            flex: 1;
            text-align: left;
        }
        
        .header-title h1 {
            font-size: 14pt;
            font-weight: bold;
            margin: 0;
            text-decoration: underline;
            text-transform: uppercase;
        }
        
        .quote-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 7pt;
        }
        
        .quote-table th,
        .quote-table td {
            border: 1px solid #000;
            padding: 3px;
            text-align: left;
        }
        
        .quote-table th {
            background-color: #d3d3d3;
            font-weight: bold;
            text-align: center;
        }
        
        .quote-table td.number {
            text-align: right;
        }
        
        .quote-table td.center {
            text-align: center;
        }
        
        .quote-table td[style*="text-align: right"] {
            text-align: right !important;
        }
        
        .supplier-block {
            border: 1px solid #000;
            padding: 5px;
            vertical-align: top;
        }
        
        .supplier-header {
            background-color: #c6e0b4;
            font-weight: bold;
            text-align: center;
            padding: 5px;
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
        }
        
        .supplier-info {
            font-size: 7pt;
            margin-bottom: 5px;
        }
        
        .supplier-info table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        
        .supplier-info td {
            border: none;
            padding: 2px;
        }
        
        .supplier-info td:first-child {
            font-weight: bold;
            width: 30%;
        }
        
        .cost-table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
            font-size: 7pt;
        }
        
        .cost-table th,
        .cost-table td {
            border: 1px solid #000;
            padding: 3px;
            text-align: center;
        }
        
        .cost-table th {
            background-color: #d3d3d3;
            font-weight: bold;
            font-size: 6pt;
        }
        
        .cost-table td {
            text-align: right;
        }
        
        .cost-table td.center {
            text-align: center;
        }
        
        .highlight-yellow {
            background-color: #ffff00 !important;
        }
        
        .highlight-red {
            background-color: #ffcccc !important;
        }
        
        .footer-totals {
            margin-top: 10px;
        }
        
        .footer-totals div {
            background-color: #ffff00;
            padding: 3px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        
        .page-break {
            page-break-after: always;
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 8pt;
        }
        
        .summary-table th,
        .summary-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }
        
        .summary-table th {
            background-color: #d3d3d3;
            font-weight: bold;
            text-align: center;
        }
        
        .summary-table td.number {
            text-align: right;
        }
        
        .observations {
            margin-top: 10px;
            margin-bottom: 10px;
            font-size: 8pt;
            padding: 5px;
            border: 1px solid #000;
            background-color: #f9f9f9;
        }
        
        .observations-label {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .observations-content {
            color: #000;
        }
    </style>
</head>
<body>
    @php
        $totalSuppliers = count($suppliers);
        $firstPageSuppliers = 1; // Primeira página: 1 cotação
        $suppliersPerPage = 2; // Páginas seguintes: 2 cotações por página
        
        // Calcular menor preço e fornecedor selecionado para cada item
        $itemHighlights = [];
        foreach ($items as $item) {
            $menorPrecoSemDifal = null;
            $menorPrecoSemDifalSupplierId = null;
            $menorPrecoComDifal = null;
            $menorPrecoComDifalSupplierId = null;
            $selectedSupplierId = $item->selected_supplier_id;
            
            foreach ($suppliers as $supplier) {
                $supplierItem = $supplier->items->firstWhere('purchase_quote_item_id', $item->id);
                if ($supplierItem) {
                    $precoSemDifal = $supplierItem->unit_cost ?? 0;
                    $precoComDifal = ($supplierItem->final_cost ?? ($supplierItem->unit_cost_with_ipi ?? $supplierItem->unit_cost ?? 0)) * $item->quantity;
                    
                    if (!$menorPrecoSemDifal || ($precoSemDifal > 0 && $precoSemDifal < $menorPrecoSemDifal)) {
                        $menorPrecoSemDifal = $precoSemDifal;
                        $menorPrecoSemDifalSupplierId = $supplier->id;
                    }
                    
                    if (!$menorPrecoComDifal || ($precoComDifal > 0 && $precoComDifal < $menorPrecoComDifal)) {
                        $menorPrecoComDifal = $precoComDifal;
                        $menorPrecoComDifalSupplierId = $supplier->id;
                    }
                }
            }
            
            $itemHighlights[$item->id] = [
                'menor_preco_sem_difal_supplier_id' => $menorPrecoSemDifalSupplierId,
                'menor_preco_com_difal_supplier_id' => $menorPrecoComDifalSupplierId,
                'selected_supplier_id' => $selectedSupplierId,
            ];
        }
    @endphp

    <!-- PRIMEIRA PÁGINA: Quadro Comparativo + 1 Cotação -->
    <div class="header">
        <div class="logo">
            <img src="https://www.gruporialma.com.br/assets/logo_sem_fundo-Dbkuj9iO.png" alt="Logo Rialma" />
        </div>
        <div class="header-title">
            <h1>QUADRO COMPARATIVO DE PREÇOS</h1>
        </div>
    </div>

    <!-- Observações -->
    @if($quote->observation)
    <div class="observations">
        <div class="observations-label">Observações:</div>
        <div class="observations-content">{{ $quote->observation }}</div>
    </div>
    @endif

    <!-- Tabela Principal com Itens e Cotações -->
    <table class="quote-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 3%;">Item</th>
                <th rowspan="2" style="width: 5%;">Qtd</th>
                <th rowspan="2" style="width: 5%;">Medida</th>
                <th rowspan="2" style="width: 20%;">DESCRIÇÃO DE PRODUTO</th>
                <th rowspan="2" style="width: 15%;">FINALIDADE</th>
                <th rowspan="2" style="width: 8%;">FILIAL</th>
                <th rowspan="2" style="width: 8%;">N° RM</th>
                @if($totalSuppliers > 0)
                    @for($i = 0; $i < min(1, $totalSuppliers); $i++)
                        @if(isset($suppliers[$i]))
                            @php
                                $supplier = $suppliers[$i];
                            @endphp
                            <th colspan="7" style="width: 50%;" class="supplier-header">
                                <div style="font-weight: bold; margin-bottom: 5px;">COTAÇÃO {{ $i + 1 }}</div>
                                <div style="font-size: 6pt; text-align: left;">
                                    <div><strong>FORNECEDOR:</strong> {{ strtoupper($supplier->supplier_name ?? '') }}</div>
                                    <div><strong>VENDEDOR:</strong> {{ strtoupper($supplier->vendor_name ?? '') }}</div>
                                    <div><strong>TELEFONE:</strong> {{ $supplier->vendor_phone ?? '' }}</div>
                                    <div><strong>EMAIL:</strong> {{ $supplier->vendor_email ?? '' }}</div>
                                    <div><strong>N° PROPOSTA:</strong> {{ $supplier->proposal_number ?? '' }}</div>
                                </div>
                            </th>
                        @else
                            <th colspan="7" style="width: 50%;" class="supplier-header">
                                COTAÇÃO {{ $i + 1 }}
                            </th>
                        @endif
                    @endfor
                @endif
            </tr>
            <tr>
                @if($totalSuppliers > 0)
                    @for($i = 0; $i < min(1, $totalSuppliers); $i++)
                        <th>Custo Unit. S/IPI</th>
                        <th>IPI %</th>
                        <th>Valor Total S/Difal C/IPI</th>
                        <th>ICMS %</th>
                        <th>ICMS Custo Total</th>
                        <th>Custo Total C/DIFAL C/IPI</th>
                        <th>Marca</th>
                    @endfor
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($items as $itemIndex => $item)
            <tr>
                <td class="center">{{ $itemIndex + 1 }}</td>
                <td class="center">{{ number_format($item->quantity, 0, ',', '.') }}</td>
                <td class="center">{{ strtoupper($item->unit ?? 'UND') }}</td>
                <td>{{ strtoupper($item->description ?? '') }}</td>
                <td>{{ strtoupper($item->application ?? '') }}</td>
                <td>{{ strtoupper($quote->location ?? '') }}</td>
                <td></td>
                
                @for($i = 0; $i < min(1, $totalSuppliers); $i++)
                    @if(isset($suppliers[$i]))
                        @php
                            $supplier = $suppliers[$i];
                            $supplierItem = $supplier->items->firstWhere('purchase_quote_item_id', $item->id);
                            $unitCost = $supplierItem ? ($supplierItem->unit_cost ?? 0) : 0;
                            $ipiPercent = $supplierItem ? ($supplierItem->ipi ?? 0) : 0;
                            $totalWithIpi = $supplierItem ? (($supplierItem->unit_cost_with_ipi ?? $supplierItem->unit_cost ?? 0) * $item->quantity) : 0;
                            $icmsPercent = $supplierItem ? ($supplierItem->icms ?? 0) : 0;
                            $icmsTotal = $supplierItem ? ($supplierItem->icms_total ?? 0) : 0;
                            $totalWithDifal = $supplierItem ? (($supplierItem->final_cost ?? ($supplierItem->unit_cost_with_ipi ?? $supplierItem->unit_cost ?? 0)) * $item->quantity) : 0;
                            
                            // Verificar se deve destacar (menor preço ou selecionado)
                            $highlight = false;
                            $highlightClass = '';
                            if (isset($itemHighlights[$item->id]) && $itemHighlights[$item->id]['selected_supplier_id'] == $supplier->id) {
                                $highlight = true;
                                $highlightClass = 'highlight-yellow';
                            } elseif (isset($itemHighlights[$item->id]) && $itemHighlights[$item->id]['menor_preco_com_difal_supplier_id'] == $supplier->id) {
                                $highlight = true;
                                $highlightClass = 'highlight-yellow';
                            }
                        @endphp
                        <!-- Células diretas de custo (sem tabela aninhada) -->
                        <td class="{{ $highlight ? $highlightClass : '' }}" style="text-align: right;">R$ {{ number_format($unitCost, 2, ',', '.') }}</td>
                        <td style="text-align: right;">{{ $ipiPercent > 0 ? number_format($ipiPercent, 2, ',', '.') . '%' : '' }}</td>
                        <td class="{{ $highlight ? $highlightClass : '' }}" style="text-align: right;">R$ {{ number_format($totalWithIpi, 2, ',', '.') }}</td>
                        <td style="text-align: right;">{{ $icmsPercent > 0 ? number_format($icmsPercent, 2, ',', '.') . '%' : '' }}</td>
                        <td style="text-align: right;">R$ {{ number_format($icmsTotal, 2, ',', '.') }}</td>
                        <td class="{{ $highlight ? $highlightClass : '' }}" style="text-align: right;">R$ {{ number_format($totalWithDifal, 2, ',', '.') }}</td>
                        <td class="center">{{ strtoupper($item->tag ?? '') }}</td>
                    @else
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    @endif
                @endfor
            </tr>
            @endforeach
            
            @if($totalSuppliers > 0)
                @for($i = 0; $i < min(1, $totalSuppliers); $i++)
                    @if(isset($suppliers[$i]))
                        @php
                            $supplier = $suppliers[$i];
                        @endphp
                        <!-- Linha final com informações de frete -->
                        <tr>
                            <td colspan="7"></td>
                            <td colspan="7" style="padding: 5px; font-size: 7pt;">
                                <div class="supplier-info">
                                    <table style="width: 100%; border-collapse: collapse; font-size: 7pt;">
                                        <tr>
                                            <td style="padding: 2px; font-weight: bold; width: 40%;">Tipo de frete:</td>
                                            <td style="padding: 2px;">{{ $supplier->freight_type == 'F' ? 'FOB' : ($supplier->freight_type == 'C' ? 'CIF' : '') }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 2px; font-weight: bold;">Valor do Frete:</td>
                                            <td style="padding: 2px; text-align: right;">0,00</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 2px; font-weight: bold;">Total Comprado S/ Difal C/IPI + Frete:</td>
                                            <td style="padding: 2px; text-align: right;">
                                                @php
                                                    $totalSemDifal = 0;
                                                    foreach ($items as $it) {
                                                        $si = $supplier->items->firstWhere('purchase_quote_item_id', $it->id);
                                                        if ($si) {
                                                            $totalSemDifal += ($si->unit_cost_with_ipi ?? $si->unit_cost ?? 0) * $it->quantity;
                                                        }
                                                    }
                                                @endphp
                                                {{ number_format($totalSemDifal, 2, ',', '.') }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 2px; font-weight: bold;">Total Comprado C/ Difal C/IPI + Frete:</td>
                                            <td style="padding: 2px; text-align: right;">
                                                @php
                                                    $totalComDifal = 0;
                                                    foreach ($items as $it) {
                                                        $si = $supplier->items->firstWhere('purchase_quote_item_id', $it->id);
                                                        if ($si) {
                                                            $totalComDifal += ($si->final_cost ?? ($si->unit_cost_with_ipi ?? $si->unit_cost ?? 0)) * $it->quantity;
                                                        }
                                                    }
                                                @endphp
                                                {{ number_format($totalComDifal, 2, ',', '.') }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 2px; font-weight: bold;">Condição de Pgto:</td>
                                            <td style="padding: 2px;">{{ $supplier->payment_condition_description ?? '' }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 2px; font-weight: bold;">Prazo de Entrega:</td>
                                            <td style="padding: 2px;">{{ $supplier->payment_condition_description ?? '' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @else
                        <tr>
                            <td colspan="14"></td>
                        </tr>
                    @endif
                @endfor
            @endif
        </tbody>
    </table>

    <!-- Rodapé Primeira Página -->
    <div class="footer-totals">
        @php
            $totalSemDifalGeral = 0;
            $totalComDifalGeral = 0;
            foreach ($items as $item) {
                $selectedSupplier = $suppliers->firstWhere('id', $item->selected_supplier_id);
                if ($selectedSupplier) {
                    $supplierItem = $selectedSupplier->items->firstWhere('purchase_quote_item_id', $item->id);
                    if ($supplierItem) {
                        $totalSemDifalGeral += ($supplierItem->unit_cost_with_ipi ?? $supplierItem->unit_cost ?? 0) * $item->quantity;
                        $totalComDifalGeral += ($supplierItem->final_cost ?? ($supplierItem->unit_cost_with_ipi ?? $supplierItem->unit_cost ?? 0)) * $item->quantity;
                    }
                }
            }
        @endphp
    </div>

    @if($totalSuppliers > 1)
        <!-- PÁGINAS SEGUINTES: 2 cotações por página -->
        @for($pageStart = 1; $pageStart < $totalSuppliers; $pageStart += $suppliersPerPage)
            <div class="page-break"></div>
            
            <div class="header">
                <div class="logo">
                    <img src="https://www.gruporialma.com.br/assets/logo_sem_fundo-Dbkuj9iO.png" alt="Logo Rialma" />
                </div>
                <div class="header-title">
                    <h1>QUADRO COMPARATIVO DE PREÇOS</h1>
                </div>
            </div>
            
            <!-- Container para as cotações lado a lado -->
            @php
                $suppliersOnThisPage = min($pageStart + $suppliersPerPage, $totalSuppliers) - $pageStart;
                $cellWidth = $suppliersOnThisPage == 1 ? 50 : (100 / $suppliersOnThisPage); // Se só 1 cotação, ocupa 50% da tela
                $containerWidth = $suppliersOnThisPage == 1 ? 50 : 100; // Container também limita a largura quando só 1 cotação
            @endphp
            <div style="display: table; width: {{ $containerWidth }}%;">
                @for($i = $pageStart; $i < min($pageStart + $suppliersPerPage, $totalSuppliers); $i++)
                    @php
                        $supplier = $suppliers[$i];
                        $supplierIndex = $i + 1;
                    @endphp
                    <div style="display: table-cell; width: {{ $cellWidth }}%; vertical-align: top; padding: 0 5px; border: 1px solid #000;">
                        <div class="supplier-header">
                            COTAÇÃO {{ $supplierIndex }}
                        </div>
                        <div style="padding: 5px;">
                            <!-- Informações do Fornecedor -->
                            <div class="supplier-info">
                                <table>
                                    <tr>
                                        <td>FORNECEDOR:</td>
                                        <td style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ strtoupper($supplier->supplier_name ?? '') }}</td>
                                    </tr>
                                    <tr>
                                        <td>VENDEDOR:</td>
                                        <td>{{ strtoupper($supplier->vendor_name ?? '') }}</td>
                                    </tr>
                                    <tr>
                                        <td>TELEFONE:</td>
                                        <td>{{ $supplier->vendor_phone ?? '' }}</td>
                                    </tr>
                                    <tr>
                                        <td>EMAIL:</td>
                                        <td>{{ $supplier->vendor_email ?? '' }}</td>
                                    </tr>
                                    <tr>
                                        <td>N° PROPOSTA:</td>
                                        <td>{{ $supplier->proposal_number ?? '' }}</td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Tabela com Item, Qtd, Medida, Descrição e Custos na mesma linha -->
                            <table class="quote-table" style="font-size: 7pt;">
                                <thead>
                                    <tr>
                                        <th style="width: 8%;">Item</th>
                                        <th style="width: 8%;">Qtd</th>
                                        <th style="width: 8%;">Medida</th>
                                        <th style="width: 25%;">DESCRIÇÃO DE PRODUTO</th>
                                        <th style="width: 6%;">Custo Unit. S/IPI</th>
                                        <th style="width: 5%;">IPI %</th>
                                        <th style="width: 8%;">Valor Total S/Difal C/IPI</th>
                                        <th style="width: 5%;">ICMS %</th>
                                        <th style="width: 7%;">ICMS Custo Total</th>
                                        <th style="width: 8%;">Custo Total C/DIFAL C/IPI</th>
                                        <th style="width: 6%;">Marca</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $itemIndex => $item)
                                        @php
                                            $supplierItem = $supplier->items->firstWhere('purchase_quote_item_id', $item->id);
                                            $unitCost = $supplierItem ? ($supplierItem->unit_cost ?? 0) : 0;
                                            $ipiPercent = $supplierItem ? ($supplierItem->ipi ?? 0) : 0;
                                            $totalWithIpi = $supplierItem ? (($supplierItem->unit_cost_with_ipi ?? $supplierItem->unit_cost ?? 0) * $item->quantity) : 0;
                                            $icmsPercent = $supplierItem ? ($supplierItem->icms ?? 0) : 0;
                                            $icmsTotal = $supplierItem ? ($supplierItem->icms_total ?? 0) : 0;
                                            $totalWithDifal = $supplierItem ? (($supplierItem->final_cost ?? ($supplierItem->unit_cost_with_ipi ?? $supplierItem->unit_cost ?? 0)) * $item->quantity) : 0;
                                            
                                            $highlight = false;
                                            $highlightClass = '';
                                            if (isset($itemHighlights[$item->id])) {
                                                if ($itemHighlights[$item->id]['selected_supplier_id'] == $supplier->id) {
                                                    $highlight = true;
                                                    $highlightClass = 'highlight-yellow';
                                                } elseif ($itemHighlights[$item->id]['menor_preco_com_difal_supplier_id'] == $supplier->id) {
                                                    $highlight = true;
                                                    $highlightClass = 'highlight-yellow';
                                                }
                                            }
                                        @endphp
                                        <tr>
                                            <td class="center">{{ $itemIndex + 1 }}</td>
                                            <td class="center">{{ number_format($item->quantity, 0, ',', '.') }}</td>
                                            <td class="center">{{ strtoupper($item->unit ?? 'UND') }}</td>
                                            <td>{{ strtoupper($item->description ?? '') }}</td>
                                            <td class="{{ $highlight ? $highlightClass : '' }}" style="text-align: right;">R$ {{ number_format($unitCost, 2, ',', '.') }}</td>
                                            <td style="text-align: right;">{{ $ipiPercent > 0 ? number_format($ipiPercent, 2, ',', '.') . '%' : '' }}</td>
                                            <td class="{{ $highlight ? $highlightClass : '' }}" style="text-align: right;">R$ {{ number_format($totalWithIpi, 2, ',', '.') }}</td>
                                            <td style="text-align: right;">{{ $icmsPercent > 0 ? number_format($icmsPercent, 2, ',', '.') . '%' : '' }}</td>
                                            <td style="text-align: right;">R$ {{ number_format($icmsTotal, 2, ',', '.') }}</td>
                                            <td class="{{ $highlight ? $highlightClass : '' }}" style="text-align: right;">R$ {{ number_format($totalWithDifal, 2, ',', '.') }}</td>
                                            <td class="center">{{ strtoupper($item->tag ?? '') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <!-- Informações de Frete e Entrega -->
                            <div class="supplier-info">
                                <table>
                                    <tr>
                                        <td>Tipo de frete:</td>
                                        <td>{{ $supplier->freight_type == 'F' ? 'FOB' : ($supplier->freight_type == 'C' ? 'CIF' : '') }}</td>
                                    </tr>
                                    <tr>
                                        <td>Valor do Frete:</td>
                                        <td style="text-align: right;">0,00</td>
                                    </tr>
                                    <tr>
                                        <td>Total Comprado S/ Difal C/IPI + Frete:</td>
                                        <td style="text-align: right;">
                                            @php
                                                $totalSemDifal = 0;
                                                foreach ($items as $item) {
                                                    $si = $supplier->items->firstWhere('purchase_quote_item_id', $item->id);
                                                    if ($si) {
                                                        $totalSemDifal += ($si->unit_cost_with_ipi ?? $si->unit_cost ?? 0) * $item->quantity;
                                                    }
                                                }
                                            @endphp
                                            {{ number_format($totalSemDifal, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Total Comprado C/ Difal C/IPI + Frete:</td>
                                        <td style="text-align: right;">
                                            @php
                                                $totalComDifal = 0;
                                                foreach ($items as $item) {
                                                    $si = $supplier->items->firstWhere('purchase_quote_item_id', $item->id);
                                                    if ($si) {
                                                        $totalComDifal += ($si->final_cost ?? ($si->unit_cost_with_ipi ?? $si->unit_cost ?? 0)) * $item->quantity;
                                                    }
                                                }
                                            @endphp
                                            {{ number_format($totalComDifal, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Condição de Pgto:</td>
                                        <td>{{ $supplier->payment_condition_description ?? '' }}</td>
                                    </tr>
                                    <tr>
                                        <td>Prazo de Entrega:</td>
                                        <td>{{ $supplier->payment_condition_description ?? '' }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                @endfor
            </div>

        @endfor
    @endif

    <!-- ÚLTIMA PÁGINA: Quadro Resumo da Cotação e Compra -->
    <div class="page-break"></div>
    
    <div class="header">
        <div class="logo">
            <img src="https://www.gruporialma.com.br/assets/logo_sem_fundo-Dbkuj9iO.png" alt="Logo Rialma" />
        </div>
        <div class="header-title">
            <h1>QUADRO RESUMO DA COTAÇÃO E COMPRA</h1>
        </div>
    </div>

    <!-- Tabela de Resumo -->
    <table class="summary-table">
        <thead>
            <tr>
                <th style="width: 3%;">Item</th>
                <th style="width: 5%;">Qtd</th>
                <th style="width: 5%;">Medida</th>
                <th style="width: 25%;">Descrição do Produto</th>
                <th style="width: 8%;">Marca</th>
                <th style="width: 15%;">Finalidade</th>
                <th style="width: 8%;">FILIAL</th>
                <th style="width: 8%;">N° RM</th>
                <th colspan="3" style="width: 17%;">FORNECEDOR MENOR PREÇO S/DIFAL</th>
                <th colspan="3" style="width: 17%;">FORNECEDOR MENOR PREÇO C/DIFAL</th>
                <th colspan="3" style="width: 17%;">FORNECEDOR DECISÃO COMPRA</th>
                <th style="width: 10%;">JUSTIFICATIVA</th>
            </tr>
            <tr>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th>FORNECEDOR</th>
                <th>UNIT. MINIMO S/DIFAL</th>
                <th>TOTAL MINIMO S/DIFAL</th>
                <th>FORNECEDOR</th>
                <th>UNIT. MINIMO C/DIFAL</th>
                <th>TOTAL MINIMO C/DIFAL</th>
                <th>FORNECEDOR</th>
                <th>UNIT. MINIMO C/DIFAL</th>
                <th>TOTAL MINIMO C/DIFAL</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalSemDifal = 0;
                $totalComDifal = 0;
                $totalDecisao = 0;
            @endphp
            @foreach($items as $itemIndex => $item)
                @php
                    // Encontrar menor preço sem DIFAL
                    $menorPrecoSemDifal = null;
                    $menorPrecoSemDifalSupplier = null;
                    $menorPrecoComDifal = null;
                    $menorPrecoComDifalSupplier = null;
                    $decisaoCompra = null;
                    $decisaoCompraSupplier = null;
                    
                    foreach ($suppliers as $supplier) {
                        $supplierItem = $supplier->items->firstWhere('purchase_quote_item_id', $item->id);
                        if ($supplierItem) {
                            $precoSemDifal = $supplierItem->unit_cost ?? 0;
                            $precoComDifal = ($supplierItem->final_cost ?? ($supplierItem->unit_cost_with_ipi ?? $supplierItem->unit_cost ?? 0)) * $item->quantity;
                            
                            // Menor preço sem DIFAL
                            if (!$menorPrecoSemDifal || ($precoSemDifal > 0 && $precoSemDifal < $menorPrecoSemDifal)) {
                                $menorPrecoSemDifal = $precoSemDifal;
                                $menorPrecoSemDifalSupplier = $supplier;
                            }
                            
                            // Menor preço com DIFAL
                            if (!$menorPrecoComDifal || ($precoComDifal > 0 && $precoComDifal < $menorPrecoComDifal)) {
                                $menorPrecoComDifal = $precoComDifal;
                                $menorPrecoComDifalSupplier = $supplier;
                            }
                            
                            // Decisão de compra (usar item selecionado se houver)
                            if ($item->selected_supplier_id == $supplier->id) {
                                $decisaoCompra = $precoComDifal;
                                $decisaoCompraSupplier = $supplier;
                            }
                        }
                    }
                    
                    // Se não houver decisão, usar menor preço com DIFAL
                    if (!$decisaoCompraSupplier && $menorPrecoComDifalSupplier) {
                        $decisaoCompra = $menorPrecoComDifal;
                        $decisaoCompraSupplier = $menorPrecoComDifalSupplier;
                    }
                    
                    // Acumular totais
                    $totalSemDifal += ($menorPrecoSemDifal ?? 0) * $item->quantity;
                    $totalComDifal += $menorPrecoComDifal ?? 0;
                    $totalDecisao += $decisaoCompra ?? 0;
                    
                    // Buscar marca do fornecedor selecionado
                    $marca = '';
                    if ($decisaoCompraSupplier) {
                        $supplierItemSelecionado = $decisaoCompraSupplier->items->firstWhere('purchase_quote_item_id', $item->id);
                        $marca = $supplierItemSelecionado->brand ?? '';
                    }
                @endphp
            <tr>
                <td class="center">{{ $itemIndex + 1 }}</td>
                <td class="center">{{ number_format($item->quantity, 0, ',', '.') }}</td>
                <td class="center">{{ strtoupper($item->unit ?? 'UND') }}</td>
                <td>{{ strtoupper($item->description ?? '') }}</td>
                <td>{{ strtoupper($marca) }}</td>
                <td>{{ strtoupper($item->application ?? '') }}</td>
                <td>{{ strtoupper($quote->location ?? '') }}</td>
                <td></td>
                <!-- Menor Preço S/DIFAL -->
                <td>{{ $menorPrecoSemDifalSupplier ? strtoupper($menorPrecoSemDifalSupplier->supplier_name ?? '') : '' }}</td>
                <td class="number">{{ $menorPrecoSemDifal ? number_format($menorPrecoSemDifal, 2, ',', '.') : '0,00' }}</td>
                <td class="number">{{ $menorPrecoSemDifal ? number_format($menorPrecoSemDifal * $item->quantity, 2, ',', '.') : '0,00' }}</td>
                <!-- Menor Preço C/DIFAL -->
                <td>{{ $menorPrecoComDifalSupplier ? strtoupper($menorPrecoComDifalSupplier->supplier_name ?? '') : '' }}</td>
                <td class="number">{{ ($menorPrecoComDifal && $item->quantity > 0) ? number_format($menorPrecoComDifal / $item->quantity, 2, ',', '.') : '0,00' }}</td>
                <td class="number">{{ $menorPrecoComDifal ? number_format($menorPrecoComDifal, 2, ',', '.') : '0,00' }}</td>
                <!-- Decisão Compra -->
                <td>{{ $decisaoCompraSupplier ? strtoupper($decisaoCompraSupplier->supplier_name ?? '') : '' }}</td>
                <td class="number">{{ ($decisaoCompra && $item->quantity > 0) ? number_format($decisaoCompra / $item->quantity, 2, ',', '.') : '0,00' }}</td>
                <td class="number">{{ $decisaoCompra ? number_format($decisaoCompra, 2, ',', '.') : '0,00' }}</td>
                <td>{{ strtoupper($item->selection_reason ?? '') }}</td>
            </tr>
            @endforeach
            <!-- Totais -->
            <tr style="font-weight: bold;">
                <td colspan="9" style="text-align: right;">TOTAL:</td>
                <td class="number">{{ number_format($totalSemDifal, 2, ',', '.') }}</td>
                <td></td>
                <td class="number">{{ number_format($totalComDifal, 2, ',', '.') }}</td>
                <td></td>
                <td class="number">{{ number_format($totalDecisao, 2, ',', '.') }}</td>
                <td class="number">{{ number_format($totalComDifal - $totalDecisao, 2, ',', '.') }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <!-- Rodapé Última Página -->
    <div class="footer-totals">
        <div>COMPRADOR: {{ strtoupper($buyer->nome_completo ?? $buyer->name ?? '') }}</div>
        <div>TOTAL S/ DIFAL: R$ {{ number_format($totalSemDifal, 2, ',', '.') }}</div>
        <div>TOTAL C/ DIFAL: R$ {{ number_format($totalDecisao, 2, ',', '.') }}</div>
    </div>
</body>
</html>
