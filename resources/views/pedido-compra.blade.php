<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido de Compra - {{ $order->order_number }}</title>
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
            font-family: 'Courier New', monospace;
            font-size: 9pt;
            margin: 0;
            padding: 0;
            color: #000;
            padding-top: 95px;
            padding-bottom: 150px;
        }

        /* Topo fixo em todas as páginas (Dompdf repete position:fixed a cada página) */
        .print-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 90px;
            box-sizing: border-box;
            background: #fff;
            z-index: 9999;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 10px 14px 10px;
            border-bottom: 1px solid #ccc;
        }
        
        .print-header .logo {
            width: 110px;
            height: 55px;
            margin-bottom: 0;
        }
        
        .print-header .header-center {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
        }
        
        .print-header .header-right {
            text-align: right;
            font-size: 8pt;
            line-height: 1.35;
            padding-bottom: 4px;
            margin-bottom: 0;
        }
        
        .print-header .header-right > div {
            margin-bottom: 2px;
        }
        
        .print-header .header-center h1 {
            font-size: 14pt;
        }
        
        .print-header .page-info::after {
            content: "Página " counter(page);
        }

        /* Fundo fixo em todas as páginas */
        .print-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 32px;
            box-sizing: border-box;
            background: #fff;
            z-index: 9999;
            border-top: 1px solid #ccc;
            font-size: 8pt;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 10px;
        }
        
        .print-footer .footer-order::before {
            content: "Pedido: {{ $order->order_number }}";
        }
        
        .print-footer .footer-page::after {
            content: "Página " counter(page);
        }

        /* Assinaturas fixas em todas as páginas */
        .print-signatures {
            position: fixed;
            bottom: 32px;
            left: 0;
            width: 100%;
            box-sizing: border-box;
            background: #fff;
            z-index: 9998;
            border-top: 1px solid #ccc;
            padding: 8px 5px 5px 5px;
        }
        
        .print-signatures .signatures {
            margin-top: 0;
            padding-top: 0;
        }
        
        .print-signatures .signature-line {
            min-height: 40px;
            padding-top: 2px;
            margin-bottom: 2px;
        }
        
        .print-signatures .signature-image {
            max-height: 38px;
        }
        
        .print-signatures .signature-name {
            font-size: 8pt;
            margin-top: 2px;
        }
        
        .print-signatures .signature-name-line {
            min-height: 14px;
            margin-top: 2px;
            padding-bottom: 2px;
        }
        
        .print-signatures .text-small {
            font-size: 6pt;
        }

        /* Conteúdo em fluxo normal: tabela pode ocupar várias páginas, totais/observações vêm depois */
        .top {
            margin-bottom: 0;
        }

        .bottom {
            margin-top: 20px;
            padding-top: 10px;
            page-break-before: avoid;
            page-break-inside: avoid;
        }
        
        img {
            max-width: 100%;
            height: auto;
        }
        
        /* Header Styles (usado dentro do print-header) */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
            position: relative;
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
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .header-center h1 {
            font-size: 16pt;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }
        
        .header-right {
            text-align: right;
            font-size: 9pt;
        }
        
        /* Info Blocks Styles */
        .info-blocks {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .info-block {
            border: 1px solid #000;
            padding: 5px;
            font-size: 8pt;
            display: table-cell;
            vertical-align: top;
        }
        
        .info-block-left {
            width: 48%;
        }
        
        .info-block-right {
            width: 48%;
        }
        
        .info-block-title {
            font-weight: bold;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
        
        .info-block-content {
            font-size: 8pt;
            line-height: 1.3;
        }
        
        /* Delivery Block Styles */
        .delivery-block {
            border: 1px solid #000;
            padding: 5px;
            margin-bottom: 10px;
            font-size: 8pt;
        }
        
        .delivery-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        
        .delivery-row-item {
            flex: 1;
        }
        
        .dates-row {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 8pt;
        }
        
        /* Table Styles - permite quebra de página na tabela; cabeçalho repete em cada página */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 8pt;
            page-break-inside: auto;
        }
        
        .items-table thead {
            display: table-header-group;
        }
        
        .items-table thead th {
            border: 1px solid #000;
            padding: 4px 3px;
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
            vertical-align: bottom;
            line-height: 1.2;
        }
        
        .items-table tbody {
            display: table-row-group;
        }
        
        .items-table tbody td {
            padding: 4px 3px;
            vertical-align: top;
            line-height: 1.25;
        }
        
        .items-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        
        .items-table td {
            border: 1px solid #000;
            text-align: left;
            word-wrap: break-word;
            word-break: break-word;
            white-space: normal;
            overflow-wrap: break-word;
        }
        
        .items-table td.number {
            text-align: right;
            white-space: nowrap;
        }
        
        .items-table td.center {
            text-align: center;
            white-space: nowrap;
        }
        
        /* Totals Section Styles - evita quebrar no meio; linhas com altura mínima para não sobrepor */
        .totals-section {
            border: 1px solid #000;
            padding: 8px;
            margin-top: 15px;
            font-size: 8pt;
            line-height: 1.4;
            page-break-inside: avoid;
        }
        
        .totals-line {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 4px 0;
            min-height: 1.4em;
            border-bottom: 1px dotted #000;
        }
        .totals-line .totals-value-right {
            text-align: right;
            margin-left: auto;
            min-width: 80px;
        }
        
        .totals-line-values {
            display: table;
            width: 100%;
            padding: 4px 0;
            font-size: 8pt;
            line-height: 1.4;
        }
        
        .totals-line-values > div {
            display: table-cell;
            text-align: left;
            width: 16.66%;
            padding: 2px 5px 2px 0;
            vertical-align: top;
        }
        
        .total-final {
            font-weight: bold;
            font-size: 10pt;
            border-top: 2px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }
        
        /* Quebra de página após cada bloco de itens (máx. 17 por página) */
        .page-break-after {
            page-break-after: always;
        }
        
        .items-page {
            page-break-inside: avoid;
        }
        
        /* Observations Styles */
        .observations {
            border: 1px solid #000;
            padding: 5px;
            margin-top: 10px;
            min-height: 60px;
            font-size: 8pt;
            page-break-inside: avoid;
        }
        
        .observations-title {
            font-weight: bold;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
        
        /* Signatures Styles - evita quebrar no meio do bloco de assinaturas */
        .signatures {
            display: table;
            width: 100%;
            margin-top: 30px;
            padding-top: 20px;
            page-break-inside: avoid;
        }
        
        .signature-box {
            display: table-cell;
            text-align: center;
            width: 16.66%; /* 6 assinaturas = 100% / 6 */
            vertical-align: top;
            padding: 0 5px;
        }
        
        .signature-line {
            padding-top: 5px;
            margin-bottom: 5px;
            min-height: 60px;
            text-align: center;
        }
        
        .signature-image {
            max-width: 90%;
            max-height: 50px;
            height: auto;
            width: auto;
            display: inline-block;
            vertical-align: middle;
        }
        
        .signature-name {
            font-size: 9pt;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .signature-name-line {
            border-bottom: 1px solid #000;
            margin-top: 5px;
            padding-bottom: 5px;
            min-height: 20px;
        }
        
        /* Text Styles */
        .text-small {
            font-size: 7pt;
            margin-top: 2px;
        }
        
        .text-bold {
            font-weight: bold;
        }
        
        .buyer-name {
            font-size: 8pt;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <!-- Topo fixo: repetido em todas as páginas -->
    <div class="print-header">
        <div class="logo">
            <img src="https://www.gruporialma.com.br/assets/logo_sem_fundo-Dbkuj9iO.png" alt="Logo Rialma" />
        </div>
        <div class="header-center">
            <h1>PEDIDO DE COMPRA</h1>
        </div>
        <div class="header-right">
            <div class="page-info"></div>
            <div>Dt Emissão: {{ $order->order_date->format('d/m/Y') }}</div>
            <div>No. Pedido: {{ $order->order_number }}</div>
        </div>
    </div>

    <!-- Fundo fixo: repetido em todas as páginas -->
    <div class="print-footer">
        <span class="footer-order"></span>
        <span class="footer-page"></span>
    </div>

    <!-- Assinaturas fixas: repetidas em todas as páginas -->
    <div class="print-signatures">
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-line">
                    @if(isset($signatures['COMPRADOR']) && $signatures['COMPRADOR'] && !empty($signatures['COMPRADOR']['signature_base64']))
                        <img src="{!! $signatures['COMPRADOR']['signature_base64'] !!}" alt="Assinatura Comprador" class="signature-image" />
                    @elseif($buyer && $buyer->signature_path)
                        @php
                            $buyerSigPath = storage_path('app/public/' . $buyer->signature_path);
                            if (file_exists($buyerSigPath)) {
                                $buyerImageData = file_get_contents($buyerSigPath);
                                $extension = strtolower(pathinfo($buyerSigPath, PATHINFO_EXTENSION));
                                $mimeType = $extension === 'jpg' || $extension === 'jpeg' ? 'image/jpeg' : ($extension === 'png' ? 'image/png' : 'image/png');
                                $buyerBase64 = base64_encode($buyerImageData);
                                $buyerBase64 = str_replace(["\r", "\n"], '', $buyerBase64);
                                $buyerBase64Url = 'data:' . $mimeType . ';base64,' . $buyerBase64;
                            }
                        @endphp
                        @if(isset($buyerBase64Url))
                            <img src="{!! $buyerBase64Url !!}" alt="Assinatura Comprador" class="signature-image" />
                        @endif
                    @endif
                </div>
                <div class="signature-name-line"></div>
                <div class="signature-name">COMPRADOR</div>
                <div class="text-small">
                    @if($buyer)
                        {{ strtoupper($buyer->nome_completo ?? ($order->quote ? $order->quote->buyer_name : null) ?? '') }}
                    @elseif(isset($signatures['COMPRADOR']) && $signatures['COMPRADOR'])
                        {{ strtoupper($signatures['COMPRADOR']['user_name']) }}
                    @endif
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-line">
                    @if(isset($signatures['GERENTE LOCAL']) && $signatures['GERENTE LOCAL'] && !empty($signatures['GERENTE LOCAL']['signature_base64']))
                        <img src="{!! $signatures['GERENTE LOCAL']['signature_base64'] !!}" alt="Assinatura Gerente Local Compras" class="signature-image" />
                    @endif
                </div>
                <div class="signature-name-line"></div>
                <div class="signature-name">Gerente Local Compras</div>
                <div class="text-small">
                    @if(isset($signatures['GERENTE LOCAL']) && $signatures['GERENTE LOCAL'])
                        {{ strtoupper($signatures['GERENTE LOCAL']['user_name']) }}
                    @endif
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-line">
                    @if(isset($signatures['ENGENHEIRO']) && $signatures['ENGENHEIRO'] && !empty($signatures['ENGENHEIRO']['signature_base64']))
                        <img src="{!! $signatures['ENGENHEIRO']['signature_base64'] !!}" alt="Assinatura Engenheiro" class="signature-image" />
                    @endif
                </div>
                <div class="signature-name-line"></div>
                <div class="signature-name">ENGENHEIRO</div>
                <div class="text-small">
                    @if(isset($signatures['ENGENHEIRO']) && $signatures['ENGENHEIRO'])
                        {{ strtoupper($signatures['ENGENHEIRO']['user_name']) }}
                    @endif
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-line">
                    @if(isset($signatures['GERENTE GERAL']) && $signatures['GERENTE GERAL'] && !empty($signatures['GERENTE GERAL']['signature_base64']))
                        <img src="{!! $signatures['GERENTE GERAL']['signature_base64'] !!}" alt="Assinatura Gerente Geral Compras" class="signature-image" />
                    @endif
                </div>
                <div class="signature-name-line"></div>
                <div class="signature-name">Gerente Geral Compras</div>
                <div class="text-small">
                    @if(isset($signatures['GERENTE GERAL']) && $signatures['GERENTE GERAL'])
                        {{ strtoupper($signatures['GERENTE GERAL']['user_name']) }}
                    @endif
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-line">
                    @if(isset($signatures['DIRETOR']) && $signatures['DIRETOR'] && !empty($signatures['DIRETOR']['signature_base64']))
                        <img src="{!! $signatures['DIRETOR']['signature_base64'] !!}" alt="Assinatura Diretor" class="signature-image" />
                    @endif
                </div>
                <div class="signature-name-line"></div>
                <div class="signature-name">DIRETOR</div>
                <div class="text-small">
                    @if(isset($signatures['DIRETOR']) && $signatures['DIRETOR'])
                        {{ strtoupper($signatures['DIRETOR']['user_name']) }}
                    @endif
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-line">
                    @if(isset($signatures['PRESIDENTE']) && $signatures['PRESIDENTE'] && !empty($signatures['PRESIDENTE']['signature_base64']))
                        <img src="{!! $signatures['PRESIDENTE']['signature_base64'] !!}" alt="Assinatura Presidente" class="signature-image" />
                    @endif
                </div>
                <div class="signature-name-line"></div>
                <div class="signature-name">PRESIDENTE</div>
                <div class="text-small">
                    @if(isset($signatures['PRESIDENTE']) && $signatures['PRESIDENTE'])
                        {{ strtoupper($signatures['PRESIDENTE']['user_name']) }}
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Conteúdo Principal (fluxo normal; itens podem ocupar várias páginas) -->
    <div class="top">
        <!-- Blocos de Informação: Fornecedor e Faturar A -->
        <div class="info-blocks">
            <div class="info-block info-block-left">
                <div class="info-block-title">FORNECEDOR</div>
                <div class="info-block-content">
                    <div class="text-bold">{{ strtoupper($order->supplier_name ?? '') }}</div>
                    @php
                        $quoteSupplier = $order->quoteSupplier;
                    @endphp
                    @if($quoteSupplier && $quoteSupplier->municipality)
                        <div><strong>END:</strong> {{ strtoupper($quoteSupplier->municipality) }}</div>
                    @endif
                    @if($quoteSupplier && $quoteSupplier->state)
                        <div><strong>CIDADE:</strong> {{ strtoupper($quoteSupplier->municipality ?? '') }} - {{ strtoupper($quoteSupplier->state) }}</div>
                    @endif
                    @if($order->supplier_document)
                        <div><strong>CNPJ:</strong> {{ preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $order->supplier_document) }}</div>
                    @endif
                    @if($order->vendor_phone)
                        <div><strong>FONE:</strong> {{ $order->vendor_phone }}</div>
                    @endif
                </div>
            </div>
            
            <div class="info-block info-block-right">
                <div class="info-block-title">FATURAR A</div>
                <div class="info-block-content">
                    <div class="text-bold">{{ strtoupper($company->razao_social ?? $company->company ?? '') }}</div>
                    @if($company->endereco)
                        <div><strong>ENDERECO:</strong> {{ strtoupper($company->endereco) }}@if($company->endereco_numero){{ '-' . $company->endereco_numero }}@endif</div>
                    @endif
                    @if($company->bairro)
                        <div><strong>BAIRRO:</strong> {{ strtoupper($company->bairro) }}</div>
                    @endif
                    @if($company->cidade)
                        <div><strong>CIDADE:</strong> {{ strtoupper($company->cidade) }}@if($company->uf){{ ' - ' . strtoupper($company->uf) }}@endif</div>
                    @endif
                    @if($company->cep)
                        <div><strong>CEP:</strong> {{ preg_replace('/(\d{5})(\d{3})/', '$1-$2', $company->cep) }}</div>
                    @endif
                    @if($company->cnpj)
                        <div><strong>CNPJ:</strong> {{ preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $company->cnpj) }}</div>
                    @endif
                    @if($company->inscricao_estadual)
                        <div><strong>INSC.EST.:</strong> {{ $company->inscricao_estadual }}</div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Endereço de Entrega -->
        <div class="delivery-block">
            <div class="info-block-title">ENDEREÇO DE ENTREGA</div>
            <div class="delivery-row">
                <div class="delivery-row-item">
                    {{ strtoupper($company->company ?? '') }}
                    @if($company->endereco)
                        {{ strtoupper($company->endereco) }}@if($company->endereco_numero){{ '-' . $company->endereco_numero }}@endif
                    @endif
                    @if($company->bairro)
                        {{ strtoupper($company->bairro) }}
                    @endif
                    @if($company->cidade)
                        {{ strtoupper($company->cidade) }}@if($company->uf){{ ' ' . strtoupper($company->uf) }}@endif
                    @endif
                </div>
                <div class="delivery-row-item" style="text-align: right;">
                    <strong>TRANSPORTADORA:</strong> {{ $order->quote && $order->quote->freight_type ? ($order->quote->freight_type == 'F' ? 'FOB' : ($order->quote->freight_type == 'C' ? 'CIF' : '')) : '' }}
                </div>
            </div>
            <div class="dates-row">
                <div><strong>PRAZO DE ENTREGA:</strong> {{ $order->expected_delivery_date ? $order->expected_delivery_date->format('d/m/Y') : '' }}</div>
                <div><strong>DATA DE PAGAMENTO:</strong> {{ $order->quote && $order->quote->payment_condition_description ? $order->quote->payment_condition_description : '' }}</div>
            </div>
        </div>

        <!-- Tabela de Itens: no máximo 17 itens por página -->
        @foreach($itemChunks as $chunkIndex => $chunk)
        <div class="items-page">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 4%;">Item</th>
                        <th style="width: 8%;">Cod.</th>
                        <th style="width: 25%;">Descrição do Material / Serviço</th>
                        <th style="width: 15%;">Aplicação</th>
                        <th style="width: 10%;">Marca</th>
                        <th style="width: 5%;">Unid.</th>
                        <th style="width: 7%;">Qtd.</th>
                        <th style="width: 12%;">Vlr Unit.</th>
                        <th style="width: 14%;">Vlr Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($chunk as $indexInChunk => $item)
                        @php
                            $globalIndex = $chunkIndex * 17 + $indexInChunk + 1;
                            $quoteItem = $item->quoteItem;
                            $description = $item->product_description ?? '';
                            $application = $quoteItem ? ($quoteItem->application ?? '') : '';
                            $brand = '';
                            if ($quoteItem && $quoteItem->tag) {
                                $brand = $quoteItem->tag;
                            }
                        @endphp
                        <tr>
                            <td class="center">{{ str_pad($globalIndex, 4, '0', STR_PAD_LEFT) }}</td>
                            <td>{{ $item->product_code ?? '' }}</td>
                            <td>{{ strtoupper($description) }}</td>
                            <td>{{ strtoupper($application) }}</td>
                            <td>{{ strtoupper($brand) }}</td>
                            <td class="center">{{ strtoupper($item->unit ?? '') }}</td>
                            <td class="number">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                            <td class="number">{{ number_format($item->unit_price, 2, ',', '.') }}</td>
                            <td class="number">{{ number_format($item->total_price, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(!$loop->last)
        <div class="page-break-after"></div>
        @endif
        @endforeach
    </div>

    <!-- Totais e Observações (em fluxo normal, após a tabela; assinaturas ficam no rodapé fixo em todas as páginas) -->
    <div class="bottom">
        <!-- Totais -->
        <div class="totals-section">
            <div class="totals-line">
                <div><strong>COND. PGTO:</strong> {{ $order->quote && $order->quote->payment_condition_description ? $order->quote->payment_condition_description : '' }}</div>
                <div class="totals-value-right"><strong>VALOR BRUTO:</strong> {{ number_format($totalIten, 2, ',', '.') }}</div>
            </div>
            <div class="totals-line">
                <div><strong>TIPO FRETE:</strong> {{ $order->quote && $order->quote->freight_type ? ($order->quote->freight_type == 'F' ? 'FOB' : ($order->quote->freight_type == 'C' ? 'CIF' : 'SEM FRETE')) : 'SEM FRETE' }}@if($order->quote && $order->quote->requester_name) - <strong>SOLICITANTE:</strong> {{ strtoupper($order->quote->requester_name) }}@endif</div>
            </div>
            <div class="totals-line-values">
                <div>IPI: {{ number_format($totalIPI, 2, ',', '.') }}</div>
                <div>ICMS RETIDO: {{ number_format($totalICM, 2, ',', '.') }}</div>
                <div>FRETE: {{ number_format($totalFRE, 2, ',', '.') }}</div>
                <div>DESPESAS: {{ number_format($totalDES, 2, ',', '.') }}</div>
                <div>SEGURO: {{ number_format($totalSEG, 2, ',', '.') }}</div>
                <div>DESCONTO: {{ number_format($totalDEC, 2, ',', '.') }}</div>
            </div>
            <div class="totals-line total-final">
                <div><strong>COMPRADOR:</strong> @if($buyer){{ strtolower($buyer->login ?? $buyer->nome_completo ?? '') }}@endif</div>
                <div class="totals-value-right"><strong>VALOR TOTAL:</strong> {{ number_format($valorTotal, 2, ',', '.') }}</div>
            </div>
        </div>
        
        <!-- Observações -->
        @if($order->observation)
        <div class="observations">
            <div class="observations-title">OBSERVACOES</div>
            <div class="text-small">{!! nl2br(e(strtoupper($order->observation))) !!}</div>
        </div>
        @endif
    </div>
</body>
</html>
