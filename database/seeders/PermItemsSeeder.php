<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermItemsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table("permitems")->insert(
            [
                "name"             => "Criar Empresas",
                "slug"             => "criar_empresas",
                "group"            => "empresa"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Criar Usuarios",
                "slug"             => "criar_usuarios",
                "group"            => "usuario"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Dashboard",
                "slug"             => "view_dashboard",
                "group"            => "dashboard"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Permissões",
                "slug"             => "view_permissions",
                "group"            => "permissoes"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Criar Permissões",
                "slug"             => "view_permissions_create",
                "group"            => "permissoes"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Editar Permissões",
                "slug"             => "view_permissions_edit",
                "group"            => "permissoes"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Excluir Permissões",
                "slug"             => "view_permissions_delete",
                "group"            => "permissoes"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Menu Cadastro",
                "slug"             => "view_menu_cadastro",
                "group"            => "cadastro"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Categorias",
                "slug"             => "view_categories",
                "group"            => "categorias"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Criar Categoria",
                "slug"             => "view_categories_create",
                "group"            => "categorias"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Editar Categoria",
                "slug"             => "view_categories_edit",
                "group"            => "categorias"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Excluir Categoria",
                "slug"             => "view_categories_delete",
                "group"            => "categorias"
            ]
        );


        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Centro de Custo",
                "slug"             => "view_costcenter",
                "group"            => "centrodecusto"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Criar Centro de Custo",
                "slug"             => "view_costcenter_create",
                "group"            => "centrodecusto"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Editar Centro de Custo",
                "slug"             => "view_costcenter_edit",
                "group"            => "centrodecusto"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Excluir Centro de Custo",
                "slug"             => "view_costcenter_delete",
                "group"            => "centrodecusto"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Bancos",
                "slug"             => "view_bancos",
                "group"            => "bancos"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Criar Bancos",
                "slug"             => "view_bancos_create",
                "group"            => "bancos"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Editar Bancos",
                "slug"             => "view_bancos_edit",
                "group"            => "bancos"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Excluir Bancos",
                "slug"             => "view_bancos_delete",
                "group"            => "bancos"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Clientes",
                "slug"             => "view_clientes",
                "group"            => "clientes"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Dados Sensiveis Cliente",
                "slug"             => "view_clientes_sensitive",
                "group"            => "clientes"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Criar Cliente",
                "slug"             => "view_clientes_create",
                "group"            => "clientes"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Editar Cliente",
                "slug"             => "view_clientes_edit",
                "group"            => "clientes"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Excluir Cliente",
                "slug"             => "view_clientes_delete",
                "group"            => "clientes"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Emprestimos",
                "slug"             => "view_emprestimos",
                "group"            => "emprestimos"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Criar Emprestimos",
                "slug"             => "view_emprestimos_create",
                "group"            => "emprestimos"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Editar Emprestimos",
                "slug"             => "view_emprestimos_edit",
                "group"            => "emprestimos"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Excluir Emprestimos",
                "slug"             => "view_emprestimos_delete",
                "group"            => "emprestimos"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Autorizar Pagamentos Empréstimos",
                "slug"             => "view_emprestimos_autorizar_pagamentos",
                "group"            => "emprestimos"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Fornecedores",
                "slug"             => "view_fornecedores",
                "group"            => "fornecedores"
            ]
        );



        DB::table("permitems")->insert(
            [
                "name"             => "Criar Fornecedor",
                "slug"             => "view_fornecedores_create",
                "group"            => "fornecedores"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Editar Fornecedor",
                "slug"             => "view_fornecedores_edit",
                "group"            => "fornecedores"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Excluir Fornecedor",
                "slug"             => "view_fornecedores_delete",
                "group"            => "fornecedores"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Contas a Pagar",
                "slug"             => "view_contaspagar",
                "group"            => "contaspagar"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Criar Contas a Pagar",
                "slug"             => "view_contaspagar_create",
                "group"            => "contaspagar"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Baixa Contas a Pagar",
                "slug"             => "view_contaspagar_baixa",
                "group"            => "contaspagar"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Excluir Contas a Pagar",
                "slug"             => "view_contaspagar_delete",
                "group"            => "contaspagar"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Contas a Receber",
                "slug"             => "view_contasreceber",
                "group"            => "contasreceber"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Criar Contas a Receber",
                "slug"             => "view_contasreceber_create",
                "group"            => "contasreceber"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Baixa Contas a Receber",
                "slug"             => "view_contasreceber_baixa",
                "group"            => "contasreceber"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Excluir Contas a Receber",
                "slug"             => "view_contasreceber_delete",
                "group"            => "contasreceber"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Movimentacao Financeira",
                "slug"             => "view_movimentacaofinanceira",
                "group"            => "movimentacaofinanceira"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Alteração de Parâmetros da Empresa",
                "slug"             => "edit_empresa",
                "group"            => "alteracaoempresa"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Fechamento de caixa",
                "slug"             => "view_fechamentocaixa",
                "group"            => "fechamentocaixa"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Efetuar Saque no Fechamento de Caixa",
                "slug"             => "view_sacarfechamentocaixa",
                "group"            => "fechamentocaixa"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Gerar Deposito Fechamento de Caixa",
                "slug"             => "view_depositarfechamentocaixa",
                "group"            => "fechamentocaixa"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Encerrar Fechamento de caixa",
                "slug"             => "view_encerrarfechamentocaixa",
                "group"            => "fechamentocaixa"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Alterar Fechamento de caixa",
                "slug"             => "view_alterarfechamentocaixa",
                "group"            => "fechamentocaixa"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Tela de Baixas pelo Aplicativo",
                "slug"             => "aplicativo_baixas",
                "group"            => "aplicativo"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Criação de empresas",
                "slug"             => "view_criacao_empresas",
                "group"            => "companies"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Editar empresas",
                "slug"             => "view_editar_empresas",
                "group"            => "companies"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Permissões MASTERGERAL",
                "slug"             => "view_mastergeral",
                "group"            => "geral"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Resumo Financeiro APP",
                "slug"             => "resumo_financeiro_aplicativo",
                "group"            => "aplicativo"
            ]
        );

        // Permissões de Cotações - Grupo: cotacoes (Visualização e Listagem)
        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Cotações",
                "slug"             => "view_cotacoes",
                "group"            => "cotacoes"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Visualizar Detalhes de Cotação",
                "slug"             => "view_cotacoes_detail",
                "group"            => "cotacoes"
            ]
        );

        // Permissões de Cotações - Grupo: cotacoes_solicitacao (Criação)
        DB::table("permitems")->insert(
            [
                "name"             => "Criar Cotação",
                "slug"             => "create_cotacoes",
                "group"            => "cotacoes_solicitacao"
            ]
        );

        // Permissões de Cotações - Grupo: cotacoes_edicao (Edição/Detalhes)
        DB::table("permitems")->insert(
            [
                "name"             => "Editar Cotações",
                "slug"             => "edit_cotacoes",
                "group"            => "cotacoes_edicao"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Editar Detalhes de Cotação",
                "slug"             => "edit_cotacoes_detalhes",
                "group"            => "cotacoes_edicao"
            ]
        );

        // Permissões de Cotações - Grupo: cotacoes_comprador (Ações do Comprador)
        DB::table("permitems")->insert(
            [
                "name"             => "Atribuir Comprador à Cotação",
                "slug"             => "cotacoes_assign_buyer",
                "group"            => "cotacoes_comprador"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Finalizar Cotação",
                "slug"             => "cotacoes_finalizar",
                "group"            => "cotacoes_comprador"
            ]
        );

        // Permissões de Cotações - Grupo: cotacoes_autorizacao (Autorização Inicial)
        DB::table("permitems")->insert(
            [
                "name"             => "Autorizar Solicitação de Cotação",
                "slug"             => "cotacoes_autorizar",
                "group"            => "cotacoes_autorizacao"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Rejeitar Solicitação de Cotação",
                "slug"             => "cotacoes_rejeitar",
                "group"            => "cotacoes_autorizacao"
            ]
        );

        // Permissões de Cotações - Grupo: cotacoes_analise (Análise Supervisor)
        DB::table("permitems")->insert(
            [
                "name"             => "Analisar Cotação",
                "slug"             => "cotacoes_analisar",
                "group"            => "cotacoes_analise"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Aprovar Análise Supervisor",
                "slug"             => "cotacoes_aprovar_supervisor",
                "group"            => "cotacoes_analise"
            ]
        );

        // Permissões de Cotações - Grupo: cotacoes_gerencia (Aprovação Gerência)
        DB::table("permitems")->insert(
            [
                "name"             => "Aprovar Cotação na Gerência",
                "slug"             => "cotacoes_aprovar_gerencia",
                "group"            => "cotacoes_gerencia"
            ]
        );

        // Permissões de Cotações - Grupo: cotacoes_reprovar (Reprovação)
        DB::table("permitems")->insert(
            [
                "name"             => "Reprovar Cotação",
                "slug"             => "cotacoes_reprovar",
                "group"            => "cotacoes_reprovar"
            ]
        );

        // Permissões de Cotações - Grupo: cotacoes_impressao (Relatórios)
        DB::table("permitems")->insert(
            [
                "name"             => "Imprimir Cotação",
                "slug"             => "cotacoes_imprimir",
                "group"            => "cotacoes_impressao"
            ]
        );

        // Permissões de Cotações - Grupo: cotacoes_admin (Administração)
        DB::table("permitems")->insert(
            [
                "name"             => "Excluir Cotações",
                "slug"             => "cotacoes_delete",
                "group"            => "cotacoes_admin"
            ]
        );

        // Permissões de Cotações - Grupo: cotacoes_selecao (Seleção de Níveis)
        DB::table("permitems")->insert(
            [
                "name"             => "Selecionar Níveis de Aprovação",
                "slug"             => "cotacoes_analisar_selecionar",
                "group"            => "cotacoes_selecao"
            ]
        );

        // Permissões de Cotações - Grupo: cotacoes_aprovacao_nivel (Aprovação por Nível)
        DB::table("permitems")->insert(
            [
                "name"             => "Aprovar como Comprador",
                "slug"             => "cotacoes_aprovar_comprador",
                "group"            => "cotacoes_aprovacao_nivel"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Aprovar como Gerente Local",
                "slug"             => "cotacoes_aprovar_gerente_local",
                "group"            => "cotacoes_aprovacao_nivel"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Aprovar como Engenheiro",
                "slug"             => "cotacoes_aprovar_engenheiro",
                "group"            => "cotacoes_aprovacao_nivel"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Aprovar como Gerente Geral",
                "slug"             => "cotacoes_aprovar_gerente_geral",
                "group"            => "cotacoes_aprovacao_nivel"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Aprovar como Diretor",
                "slug"             => "cotacoes_aprovar_diretor",
                "group"            => "cotacoes_aprovacao_nivel"
            ]
        );

        DB::table("permitems")->insert(
            [
                "name"             => "Aprovar como Presidente",
                "slug"             => "cotacoes_aprovar_presidente",
                "group"            => "cotacoes_aprovacao_nivel"
            ]
        );

    }
}
