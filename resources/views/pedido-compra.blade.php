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
            position: relative;
            min-height: 100vh;
        }

        .top {
            padding-bottom: 120px;
        }

        .bottom {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            margin-top: auto;
        }
        
        img {
            max-width: 100%;
            height: auto;
        }
        
        /* Header Styles */
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
        
        /* Table Styles */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 8pt;
        }
        
        .items-table th {
            border: 1px solid #000;
            padding: 3px;
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        
        .items-table td {
            border: 1px solid #000;
            padding: 3px;
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
        
        /* Totals Section Styles */
        .totals-section {
            border: 1px solid #000;
            padding: 5px;
            margin-top: 10px;
            font-size: 8pt;
        }
        
        .totals-line {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
            border-bottom: 1px dotted #000;
        }
        
        .totals-line-values {
            display: table;
            width: 100%;
            padding: 2px 0;
            font-size: 8pt;
        }
        
        .totals-line-values > div {
            display: table-cell;
            text-align: left;
            width: 16.66%;
            padding-right: 5px;
        }
        
        .total-final {
            font-weight: bold;
            font-size: 10pt;
            border-top: 2px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }
        
        /* Observations Styles */
        .observations {
            border: 1px solid #000;
            padding: 5px;
            margin-top: 10px;
            min-height: 60px;
            font-size: 8pt;
        }
        
        .observations-title {
            font-weight: bold;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
        
        /* Signatures Styles */
        .signatures {
            display: table;
            width: 100%;
            margin-top: 30px;
            padding-top: 20px;
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
    <!-- Conteúdo Principal -->
    <div class="top">
        <!-- Cabeçalho -->
        <div class="header">
            <div class="logo">
                <img src="https://www.gruporialma.com.br/assets/logo_sem_fundo-Dbkuj9iO.png" alt="Logo Rialma" />
            </div>
            <div class="header-center">
                <h1>PEDIDO DE COMPRA</h1>
            </div>
            <div class="header-right">
                <div>Página : {{ str_pad($pageNumber, 2, '0', STR_PAD_LEFT) }}</div>
                <div>Dt Emissão: {{ $order->order_date->format('d/m/Y') }}</div>
                <div>No. Pedido: {{ $order->order_number }}</div>
            </div>
        </div>

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

        <!-- Tabela de Itens -->
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
                @foreach($items as $index => $item)
                    @php
                        $quoteItem = $item->quoteItem;
                        $description = $item->product_description ?? '';
                        $application = $quoteItem ? ($quoteItem->application ?? '') : '';
                        $brand = '';
                        if ($quoteItem && $quoteItem->tag) {
                            $brand = $quoteItem->tag;
                        }
                    @endphp
                    <tr>
                        <td class="center">{{ str_pad($index + 1, 4, '0', STR_PAD_LEFT) }}</td>
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

    <!-- Rodapé Fixo (Totais, Observações e Assinaturas) -->
    <div class="bottom">
        <!-- Totais -->
        <div class="totals-section">
            <div class="totals-line">
                <div><strong>COND. PGTO:</strong> {{ $order->quote && $order->quote->payment_condition_description ? $order->quote->payment_condition_description : '' }}</div>
                <div><strong>VALOR BRUTO:</strong> {{ number_format($totalIten, 2, ',', '.') }}</div>
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
                <div><strong>VALOR TOTAL:</strong></div>
                <div><strong>{{ number_format($valorTotal, 2, ',', '.') }}</strong></div>
            </div>
            @if($buyer)
            <div class="totals-line" style="margin-top: 5px;">
                <div><strong>COMPRADOR:</strong> {{ strtolower($buyer->login ?? $buyer->nome_completo ?? '') }}</div>
            </div>
            @endif
        </div>
        
        <!-- Observações -->
        @if($order->observation)
        <div class="observations">
            <div class="observations-title">OBSERVACOES</div>
            <div class="text-small">{!! nl2br(e(strtoupper($order->observation))) !!}</div>
        </div>
        @endif

        <!-- Assinaturas -->
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
                    @if(isset($signatures['COMPRADOR']) && $signatures['COMPRADOR'])
                        {{ strtoupper($signatures['COMPRADOR']['user_name']) }}
                    @elseif($buyer)
                        {{ strtoupper($buyer->nome_completo) }}
                    @endif
                </div>
            </div>
            
            <div class="signature-box">
                <div class="signature-line">
                    @if(isset($signatures['GERENTE LOCAL']) && $signatures['GERENTE LOCAL'] && !empty($signatures['GERENTE LOCAL']['signature_base64']))
                        <img src="{!! $signatures['GERENTE LOCAL']['signature_base64'] !!}" alt="Assinatura Gerente Local" class="signature-image" />
                    @endif
                </div>
                <div class="signature-name-line"></div>
                <div class="signature-name">GERENTE LOCAL</div>
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
                        <img src="{!! $signatures['GERENTE GERAL']['signature_base64'] !!}" alt="Assinatura Gerente Geral" class="signature-image" />
                    @endif
                </div>
                <div class="signature-name-line"></div>
                <div class="signature-name">GERENTE GERAL</div>
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
</body>
</html>
