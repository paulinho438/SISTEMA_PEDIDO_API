<?php

use App\Http\Controllers\{
    AuthController,
    CompanyController,
    UserController,
    PermgroupController,
    PermitemController,
    CategoryController,
    CostcenterController,
    BancoController,
    ClientController,
    EmprestimoController,
    JurosController,
    FornecedorController,
    ContaspagarController,
    ContasreceberController,
    MovimentacaofinanceiraController,
    ControleBcodexController,
    UsuarioController,
    FeriadoController,
    AddressController,
    BotaoCobrancaController,
    DashboardController,
    GestaoController,
    LogController,
    PlanosController,
    LocacaoController,
    AuthClienteController,
    WebhookTesteController,
    NotasController,
    ProtheusDataController,
    Integrations\ProtheusIntegrationController,
    PurchaseQuoteController,
    ReportController,
    StockProductController,
    StockLocationController,
    StockController,
    StockMovementController,
    StockAlmoxarifeController,
    StockTransferController,
    PurchaseInvoiceController,
    PurchaseOrderController,
    AssetController,
    AssetMovementController,
    AssetUseConditionController,
    AssetStandardDescriptionController,
    AssetBranchController,
    AssetSubType1Controller,
    AssetSubType2Controller,
    AssetGroupingController,
    AssetAccountController,
    AssetProjectController,
    AssetBusinessUnitController
};
use App\Models\BotaoCobranca;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Route;

use App\Mail\ExampleEmail;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;

use Illuminate\Support\Facades\Http;


Route::get('/401', [AuthController::class, 'unauthorized'])->name('login');

Route::get('/users', [UserController::class, 'index']);

Route::get('/users/{id}', [UserController::class, 'id']);
Route::get('/testarAutomacaoRenovacao', [CompanyController::class, 'testarAutomacaoRenovacao']);

Route::post('/informar_localizacao_app', [UsuarioController::class, 'informarLocalizacaoApp']);

Route::post('/webhook/teste', [WebhookTesteController::class, 'receber']);

Route::post('/wapi/envio_mensagem_teste', [EmprestimoController::class, 'enviarMensagemWAPITeste']);
Route::post('/wapi/envio_mensagem_teste_audio', [EmprestimoController::class, 'enviarMensagemAudioWAPITeste']);



Route::get('/setup-teste', function (Request $request) {
    $details = [
        'title' => 'Relatório de Emprestimos',
        'body' => 'This is a test email using MailerSend in Laravel.'
    ];

    Mail::to('paulo_henrique500@hotmail.com')->send(new ExampleEmail($details, []));

    return 'Email sent successfully!';
});

Route::post('/informar_localizacao', [UsuarioController::class, 'informarLocalizacao']);


Route::post('/webhook/retorno_cobranca', [EmprestimoController::class, 'webhookRetornoCobranca']);
Route::post('/webhook/retorno_pagamento', [EmprestimoController::class, 'webhookPagamento']);
Route::post('/webhook/corrigir_registros_webhook', [EmprestimoController::class, 'corrigirRegistrosWebhook']);
Route::post('/manutencao/corrigir_pix', [EmprestimoController::class, 'corrigirPix']);
Route::post('/manutencao/corrigir_pix_parcela', [EmprestimoController::class, 'corrigirValoresPix']);
Route::post('/manutencao/aplicar_multa_parcela/{id}', [EmprestimoController::class, 'aplicarMultaParcela']);
Route::post('/manutencao/buscar_clientes_cobrados/', [EmprestimoController::class, 'buscarClientesCobrados']);
Route::post('/manutencao/buscar_parcelas_pendentes/', [EmprestimoController::class, 'buscarParcelasPendentes']);
Route::post('/manutencao/emprestimos_aptos_a_protesto/', [EmprestimoController::class, 'emprestimosAptosAProtesto']);
Route::post('/manutencao/emprestimos_aptos_a_refinanciar/', [EmprestimoController::class, 'emprestimosAptosARefinanciar']);
Route::post('/manutencao/ajustar_data_lancamento_parcelas/', [EmprestimoController::class, 'ajustarDataLancamentoParcelas']);






Route::post('/rotina/locacao_data_corte/{id}', [LocacaoController::class, 'dataCorte']);





Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth_cliente/login', [AuthClienteController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/parcela/{id}/infoemprestimofront', [EmprestimoController::class, 'infoEmprestimoFront']);
Route::post('/parcela/{id}/infoClienteLocalizacao', [EmprestimoController::class, 'infoClienteLocalizacao']);
Route::post('/parcela/{id}/personalizarpagamento', [EmprestimoController::class, 'personalizarPagamento']);
Route::post('/parcela/{id}/gerarpixpagamentoparcela', [EmprestimoController::class, 'gerarPixPagamentoParcela']);
Route::post('/parcela/{id}/gerarpixpagamentoquitacao', [EmprestimoController::class, 'gerarPixPagamentoQuitacao']);
Route::post('/parcela/{id}/gerarpixpagamentosaldopendente', [EmprestimoController::class, 'gerarPixPagamentoSaldoPendente']);

Route::middleware('auth:clientes')->group(function () {
    Route::get('/clientes/app/emprestimos_andamento', [ClientController::class, 'buscarEmprestimosAndamento']);
});

Route::prefix('integrations/protheus')
    ->middleware('protheus.integration')
    ->group(function () {
        Route::post('/purchase-orders/{supplier}/processing', [ProtheusIntegrationController::class, 'processing']);
        Route::post('/purchase-orders/{supplier}/complete', [ProtheusIntegrationController::class, 'complete']);
        Route::post('/purchase-orders/{supplier}/fail', [ProtheusIntegrationController::class, 'fail']);
    });

Route::middleware('auth:api')->group(function () {
    Route::get('/dashboard/info-conta', [DashboardController::class, 'infoConta']);
    Route::get('/dashboard/purchase-metrics', [DashboardController::class, 'purchaseQuoteMetrics']);
    Route::get('/dashboard/stock-metrics', [DashboardController::class, 'stockMetrics']);
    Route::get('/relatorios/custos-centro-custo', [ReportController::class, 'costsByCostCenter']);
    Route::get('/relatorios/cotacoes', [ReportController::class, 'quotesSummary']);
    Route::get('/relatorios/custos-fornecedor', [ReportController::class, 'costsBySupplier']);
    Route::get('/relatorios/custos-solicitacao', [ReportController::class, 'costsByQuote']);
    Route::get('/relatorios/historico-periodo', [ReportController::class, 'historyByPeriod']);

    Route::post('/auth/validate', [AuthController::class, 'validateToken']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/permission_groups', [PermgroupController::class, 'index']);
    Route::get('/permission_groups/{id}', [PermgroupController::class, 'id']);
    Route::get('/permission_groups/{id}/delete', [PermgroupController::class, 'delete']);
    Route::get('/permission_groups/items/{id}', [PermgroupController::class, 'getItemsForGroup']);
    Route::get('/permission_groups/items/user/{id}', [PermgroupController::class, 'getItemsForGroupUser']);
    Route::put('/permission_groups/{id}', [PermgroupController::class, 'update']);
    Route::post('/permission_groups', [PermgroupController::class, 'insert']);

    Route::get('/permission_items', [PermitemController::class, 'index']);
    Route::get('/permission_items/{id}', [PermitemController::class, 'id']);

    Route::get('/categories', [CategoryController::class, 'all']);
    Route::get('/categories/{id}', [CategoryController::class, 'id']);
    Route::get('/categories/{id}/delete', [CategoryController::class, 'delete']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::post('/categories', [CategoryController::class, 'insert']);

    Route::get('/costcenter', [CostcenterController::class, 'all']);
    Route::get('/costcenter/{id}', [CostcenterController::class, 'id']);
    Route::get('/costcenter/{id}/delete', [CostcenterController::class, 'delete']);
    Route::put('/costcenter/{id}', [CostcenterController::class, 'update']);
    Route::post('/costcenter', [CostcenterController::class, 'insert']);

    Route::post('/notas', [NotasController::class, 'insert']);
    Route::get('/notas/{id}', [NotasController::class, 'all']);
    Route::get('/notas/{id}/delete', [NotasController::class, 'delete']);


    Route::get('/clientesdisponiveis', [ClientController::class, 'clientesDisponiveis']);
    Route::post('/enviarmensagemmassa', [ClientController::class, 'enviarMensagemMassa']);

    Route::get('/cliente', [ClientController::class, 'all']);
    Route::get('/cliente/{id}', [ClientController::class, 'id']);
    Route::get('/cliente/{id}/delete', [ClientController::class, 'delete']);
    Route::put('/cliente/{id}', [ClientController::class, 'update']);
    Route::post('/cliente', [ClientController::class, 'insert']);
    Route::get('/cliente/{id}/enviar_acesso_app', [ClientController::class, 'enviarAcessoApp']);


    Route::get('/gestao/usuariosempresa/{id}', [GestaoController::class, 'getAllUsuariosEmpresa']);

    Route::get('/usuariocompanies', [UsuarioController::class, 'allCompany']);
    Route::get('/usuario', [UsuarioController::class, 'all']);
    // Rotas específicas devem vir ANTES das rotas com parâmetros {id}
    Route::get('/usuario/assinaturas-por-perfil', [UsuarioController::class, 'getSignaturesByProfile']);
    Route::get('/usuario/{id}', [UsuarioController::class, 'id']);
    Route::get('/usuario/{id}/delete', [UsuarioController::class, 'delete']);
    Route::put('/usuario/{id}', [UsuarioController::class, 'update']);
    Route::post('/usuario/{id}', [UsuarioController::class, 'update']); // Para suportar _method=PUT
    Route::post('/usuario', [UsuarioController::class, 'insert']);
    Route::post('/usuario/{id}/upload-signature', [UsuarioController::class, 'uploadSignature']);
    Route::get('/cobranca/atrasadas', [ClientController::class, 'parcelasAtrasadas']);
    Route::get('/mapa/clientes', [ClientController::class, 'mapaClientes']);
    Route::get('/mapa/consultor', [ClientController::class, 'mapaConsultor']);
    Route::get('/mapa/localizacao_clientes', [ClientController::class, 'mapaLocalizacaoClientes']);
    Route::post('/mapa/cobraramanha', [ClientController::class, 'mapaCobrarAmanha']);
    Route::post('/mapa/rotaconsultor', [ClientController::class, 'mapaRotaConsultor']);

    Route::get('/cobranca/buttonpressed', [BotaoCobrancaController::class, 'pressed']);
    Route::get('/cobranca/getbuttonpressed', [BotaoCobrancaController::class, 'getButtonPressed']);


    Route::get('/empresas/{id}', [CompanyController::class, 'get']);
    Route::get('/empresa', [CompanyController::class, 'get']);

    Route::get('/empresas', [CompanyController::class, 'getAll']);
    Route::post('/empresas', [CompanyController::class, 'insert']);
    Route::put('/empresas/{id}', [CompanyController::class, 'update']);

    Route::get('/getenvioautomaticorenovacao', [CompanyController::class, 'getEnvioAutomaticoRenovacao']);
    Route::get('/getmensagemaudioautomatico', [CompanyController::class, 'getMensagemAudioAutomatico']);

    Route::post('/empresas/alterenvioautomaticorenovacao', [CompanyController::class, 'alterEnvioAutomaticoRenovacao']);
    Route::post('/empresas/altermensagemaudioautomatico', [CompanyController::class, 'alterMensagemAudioAutomatico']);




    Route::get('/planos/{id}', [PlanosController::class, 'get']);
    Route::get('/planos', [PlanosController::class, 'getAll']);
    Route::post('/planos', [PlanosController::class, 'insert']);
    Route::put('/planos/{id}', [PlanosController::class, 'update']);


    Route::get('/contaspagar', [ContaspagarController::class, 'all']);
    Route::get('/contaspagar/{id}', [ContaspagarController::class, 'id']);
    Route::get('/contaspagar/{id}/delete', [ContaspagarController::class, 'delete']);
    Route::post('/contaspagar', [ContaspagarController::class, 'insert']);

    Route::get('/contaspagar/pagamentos/pendentes', [ContaspagarController::class, 'pagamentoPendentes']);
    Route::get('/contaspagar/pagamentos/pendentesaplicativo', [ContaspagarController::class, 'pagamentoPendentesAplicativo']);

    Route::post('/contaspagar/pagamentos/transferenciaconsultar/{id}', [EmprestimoController::class, 'pagamentoTransferenciaConsultar']);
    Route::post('/contaspagar/pagamentos/transferencia/{id}', [EmprestimoController::class, 'pagamentoTransferencia']);

    Route::post('/contaspagar/pagamentos/transferenciatituloconsultar/{id}', [EmprestimoController::class, 'pagamentoTransferenciaTituloAPagarConsultar']);
    Route::post('/contaspagar/pagamentos/transferenciatitulo/{id}', [EmprestimoController::class, 'pagamentoTransferenciaTituloAPagar']);

    Route::post('/contaspagar/pagamentos/reprovaremprestimo/{id}', [EmprestimoController::class, 'reprovarEmprestimo']);
    Route::post('/contaspagar/pagamentos/reprovarcontasapagar/{id}', [EmprestimoController::class, 'reprovarContasAPagar']);

    Route::get('/contasreceber', [ContasreceberController::class, 'all']);
    Route::get('/contasreceber/{id}', [ContasreceberController::class, 'id']);
    Route::get('/contasreceber/{id}/delete', [ContasreceberController::class, 'delete']);
    Route::post('/contasreceber', [ContasreceberController::class, 'insert']);

    Route::get('/movimentacaofinanceira', [MovimentacaofinanceiraController::class, 'all']);
    Route::get('/movimentacaofinanceira/{id}', [MovimentacaofinanceiraController::class, 'id']);

    Route::get('/controlebcodex', [ControleBcodexController::class, 'all']);



    Route::get('/log', [LogController::class, 'all']);


    Route::get('/fornecedor', [FornecedorController::class, 'all']);
    Route::get('/fornecedor/protheus', [FornecedorController::class, 'listFromProtheus']);
    Route::get('/protheus/fornecedores', [ProtheusDataController::class, 'fornecedores']);
    Route::get('/protheus/produtos', [ProtheusDataController::class, 'produtos']);
    Route::get('/protheus/centros-custo', [ProtheusDataController::class, 'centrosDeCusto']);
    Route::get('/protheus/condicoes-pagamento', [ProtheusDataController::class, 'condicoesPagamento']);
    Route::get('/protheus/pedidos-compra', [ProtheusDataController::class, 'pedidosCompra']);
    Route::get('/protheus/pedidos-compra/itens', [ProtheusDataController::class, 'itensPedidoCompra']);
    Route::get('/protheus/compradores', [ProtheusDataController::class, 'compradores']);
    Route::get('/protheus/naturezas-operacao', [ProtheusDataController::class, 'naturezasOperacao']);
    Route::get('/protheus/tes', [ProtheusDataController::class, 'tes']);
    Route::get('/cotacoes', [PurchaseQuoteController::class, 'index']);
    Route::post('/cotacoes', [PurchaseQuoteController::class, 'store']);
    Route::put('/cotacoes/{quote}', [PurchaseQuoteController::class, 'update']);
    Route::delete('/cotacoes/{quote}', [PurchaseQuoteController::class, 'destroy']);
    Route::get('/cotacoes/acompanhamento', [PurchaseQuoteController::class, 'acompanhamento']);
    Route::get('/cotacoes/{quote}', [PurchaseQuoteController::class, 'show']);
    Route::post('/cotacoes/{quote}/detalhes', [PurchaseQuoteController::class, 'saveDetails']);
    Route::post('/cotacoes/{quote}/assign-buyer', [PurchaseQuoteController::class, 'assignBuyer']);
    Route::post('/cotacoes/{quote}/assign-engineer', [PurchaseQuoteController::class, 'assignEngineer']);
    Route::post('/cotacoes/{quote}/approve', [PurchaseQuoteController::class, 'approve']);
    Route::post('/cotacoes/{quote}/reject', [PurchaseQuoteController::class, 'reject']);
    Route::post('/cotacoes/{quote}/finalizar', [PurchaseQuoteController::class, 'finalizeQuote']);
    Route::post('/cotacoes/{quote}/analisar', [PurchaseQuoteController::class, 'analyzeQuote']);
    Route::post('/cotacoes/{quote}/analisar-aprovacoes', [PurchaseQuoteController::class, 'analyzeAndSelectApprovals']);
    Route::post('/cotacoes/{quote}/aprovar-nivel/{level}', [PurchaseQuoteController::class, 'approveByLevel']);
    Route::post('/cotacoes/{quote}/reprovar', [PurchaseQuoteController::class, 'reprove']);
    Route::get('/cotacoes/{quote}/imprimir', [PurchaseQuoteController::class, 'imprimir']);
    
    // Rotas de Estoque
    // Rotas de Notas Fiscais
    Route::prefix('notas-fiscais')->group(function () {
        Route::get('/', [PurchaseInvoiceController::class, 'index']);
        Route::get('/pedido/{orderId}', [PurchaseInvoiceController::class, 'buscarPedido']);
        Route::get('/{id}', [PurchaseInvoiceController::class, 'show']);
        Route::post('/', [PurchaseInvoiceController::class, 'store']);
    });
    
    // Rotas de Pedidos de Compra
    Route::prefix('pedidos-compra')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index']);
        Route::get('/cotacao/{quoteId}', [PurchaseOrderController::class, 'buscarPorCotacao']);
        Route::post('/cotacao/{quoteId}/gerar', [PurchaseOrderController::class, 'gerarPedidosCotacao']);
        Route::get('/{id}', [PurchaseOrderController::class, 'show']);
        Route::get('/{id}/imprimir', [PurchaseOrderController::class, 'imprimir']);
        Route::put('/{id}/status', [PurchaseOrderController::class, 'updateStatus']);
    });
    
    Route::prefix('estoque')->group(function () {
        // Almoxarifes (rotas específicas devem vir antes das genéricas)
        Route::get('/almoxarifes', [StockAlmoxarifeController::class, 'listAlmoxarifes']);
        Route::get('/almoxarifes/{user_id}/locais', [StockAlmoxarifeController::class, 'listByAlmoxarife']);
        Route::post('/almoxarifes/{user_id}/locais', [StockAlmoxarifeController::class, 'associateMultiple']);
        Route::delete('/almoxarifes/{user_id}/locais', [StockAlmoxarifeController::class, 'disassociateMultiple']);
        
        // Movimentações (rotas específicas devem vir antes das genéricas)
        Route::get('/movimentacoes', [StockMovementController::class, 'index']);
        Route::post('/movimentacoes/ajuste', [StockMovementController::class, 'ajuste']);
        Route::post('/movimentacoes/entrada', [StockMovementController::class, 'entrada']);
        Route::post('/movimentacoes/transferir', [StockMovementController::class, 'transferir']);
        Route::post('/movimentacoes/transferir-lote', [StockMovementController::class, 'transferirLote']);
        Route::post('/transferencias/gerar-documento', [StockMovementController::class, 'gerarDocumentoTransferencia']);
        
        // Transferências (controle)
        Route::get('/transferencias', [StockTransferController::class, 'index']);
        Route::get('/transferencias/{id}', [StockTransferController::class, 'show']);
        Route::post('/transferencias', [StockTransferController::class, 'store']);
        Route::post('/transferencias/{id}/receber', [StockTransferController::class, 'receber']);
        Route::delete('/transferencias/{id}', [StockTransferController::class, 'destroy']);
        Route::get('/transferencias/{id}/documento', [StockTransferController::class, 'gerarDocumento']);
        
        // Produtos
        Route::get('/produtos/buscar', [StockProductController::class, 'buscar']);
        Route::get('/produtos/buscar-combinado', [StockProductController::class, 'buscarCombinado']);
        Route::get('/produtos', [StockProductController::class, 'index']);
        Route::get('/produtos/{id}', [StockProductController::class, 'show']);
        Route::post('/produtos', [StockProductController::class, 'store']);
        Route::post('/produtos/cadastrar-com-protheus', [StockProductController::class, 'cadastrarComProtheus']);
        Route::put('/produtos/{id}', [StockProductController::class, 'update']);
        Route::patch('/produtos/{id}/toggle-active', [StockProductController::class, 'toggleActive']);
        Route::post('/produtos/{id}/upload-image', [StockProductController::class, 'uploadImage']);
        Route::delete('/produtos/{id}/remove-image', [StockProductController::class, 'removeImage']);
        
        // Locais
        Route::get('/locais/{location_id}/almoxarifes', [StockAlmoxarifeController::class, 'listByLocation']);
        Route::post('/locais/{location_id}/almoxarifes', [StockAlmoxarifeController::class, 'associate']);
        Route::delete('/locais/{location_id}/almoxarifes/{user_id}', [StockAlmoxarifeController::class, 'disassociate']);
        Route::get('/locais/all-active', [StockLocationController::class, 'listAllActive']); // Todos os locais ativos (sem filtro de acesso)
        Route::get('/locais', [StockLocationController::class, 'index']);
        Route::get('/locais/{id}', [StockLocationController::class, 'show']);
        Route::post('/locais', [StockLocationController::class, 'store']);
        Route::put('/locais/{id}', [StockLocationController::class, 'update']);
        Route::patch('/locais/{id}/toggle-active', [StockLocationController::class, 'toggleActive']);
        
        // Estoque (rotas genéricas por último)
        Route::get('/', [StockController::class, 'index']);
        Route::post('/{id}/reservar', [StockController::class, 'reservar']);
        Route::post('/{id}/liberar', [StockController::class, 'liberar']);
        Route::post('/{id}/cancelar-reserva', [StockController::class, 'cancelarReserva']);
        Route::post('/{id}/dar-saida', [StockController::class, 'darSaida']);
        Route::post('/{id}/dar-saida-criar-ativo', [StockController::class, 'darSaidaECriarAtivo']);
        Route::post('/{id}/transferir-e-sair', [StockController::class, 'transferirESair']);
        Route::get('/{id}', [StockController::class, 'show']);
    });
    
    // Rotas de Ativos
    Route::prefix('ativos')->group(function () {
        // Ativos - Rotas específicas primeiro
        Route::get('/', [AssetController::class, 'index']);
        Route::get('/buscar', [AssetController::class, 'buscar']);
        Route::post('/', [AssetController::class, 'store']);
        
        // Cadastros Auxiliares (devem vir ANTES de rotas genéricas como /{id})
        Route::prefix('condicoes-uso')->group(function () {
            Route::get('/', [AssetUseConditionController::class, 'index']);
            Route::get('/{id}', [AssetUseConditionController::class, 'show']);
            Route::post('/', [AssetUseConditionController::class, 'store']);
            Route::put('/{id}', [AssetUseConditionController::class, 'update']);
            Route::delete('/{id}', [AssetUseConditionController::class, 'destroy']);
        });
        Route::prefix('descricoes-padrao')->group(function () {
            Route::get('/', [AssetStandardDescriptionController::class, 'index']);
            Route::post('/', [AssetStandardDescriptionController::class, 'store']);
            Route::put('/{id}', [AssetStandardDescriptionController::class, 'update']);
            Route::delete('/{id}', [AssetStandardDescriptionController::class, 'destroy']);
        });
        Route::prefix('filiais')->group(function () {
            Route::get('/', [AssetBranchController::class, 'index']);
            Route::get('/{id}', [AssetBranchController::class, 'show']);
            Route::post('/', [AssetBranchController::class, 'store']);
            Route::put('/{id}', [AssetBranchController::class, 'update']);
            Route::delete('/{id}', [AssetBranchController::class, 'destroy']);
        });
        Route::prefix('sub-tipos-1')->group(function () {
            Route::get('/', [AssetSubType1Controller::class, 'index']);
            Route::post('/', [AssetSubType1Controller::class, 'store']);
            Route::put('/{id}', [AssetSubType1Controller::class, 'update']);
            Route::delete('/{id}', [AssetSubType1Controller::class, 'destroy']);
        });
        Route::prefix('sub-tipos-2')->group(function () {
            Route::get('/', [AssetSubType2Controller::class, 'index']);
            Route::post('/', [AssetSubType2Controller::class, 'store']);
            Route::put('/{id}', [AssetSubType2Controller::class, 'update']);
            Route::delete('/{id}', [AssetSubType2Controller::class, 'destroy']);
        });
        Route::prefix('agrupamentos')->group(function () {
            Route::get('/', [AssetGroupingController::class, 'index']);
            Route::post('/', [AssetGroupingController::class, 'store']);
            Route::put('/{id}', [AssetGroupingController::class, 'update']);
            Route::delete('/{id}', [AssetGroupingController::class, 'destroy']);
        });
        Route::prefix('contas')->group(function () {
            Route::get('/', [AssetAccountController::class, 'index']);
            Route::post('/', [AssetAccountController::class, 'store']);
            Route::put('/{id}', [AssetAccountController::class, 'update']);
            Route::delete('/{id}', [AssetAccountController::class, 'destroy']);
        });
        Route::prefix('projetos')->group(function () {
            Route::get('/', [AssetProjectController::class, 'index']);
            Route::post('/', [AssetProjectController::class, 'store']);
            Route::put('/{id}', [AssetProjectController::class, 'update']);
            Route::delete('/{id}', [AssetProjectController::class, 'destroy']);
        });
        Route::prefix('unidades-negocio')->group(function () {
            Route::get('/', [AssetBusinessUnitController::class, 'index']);
            Route::post('/', [AssetBusinessUnitController::class, 'store']);
            Route::put('/{id}', [AssetBusinessUnitController::class, 'update']);
            Route::delete('/{id}', [AssetBusinessUnitController::class, 'destroy']);
        });
        
        // Rotas genéricas de Ativos (devem vir DEPOIS das rotas específicas)
        Route::get('/{id}', [AssetController::class, 'show']);
        Route::put('/{id}', [AssetController::class, 'update']);
        Route::post('/{id}/baixar', [AssetController::class, 'baixar']);
        Route::post('/{id}/transferir', [AssetController::class, 'transferir']);
        Route::post('/{id}/alterar-responsavel', [AssetController::class, 'alterarResponsavel']);
        
        // Movimentações
        Route::get('/{id}/movimentacoes', [AssetMovementController::class, 'index']);
    });
    
    Route::get('/fornecedor/{id}', [FornecedorController::class, 'id']);
    Route::get('/fornecedor/{id}/delete', [FornecedorController::class, 'delete']);
    Route::put('/fornecedor/{id}', [FornecedorController::class, 'update']);
    Route::post('/fornecedor', [FornecedorController::class, 'insert']);

    
});
