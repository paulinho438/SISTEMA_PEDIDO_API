<?php

namespace App\Models;
use App\Models\User;
use App\Models\Planos;
use App\Models\Locacao;
use App\Models\Emprestimo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    public $table = 'companies';

    protected $fillable = [
        'company',
        'juros',
        'caixa',
        'caixa_pix',
        'ativo',
        'email',
        'motivo_inativo',
        'plano_id',
        'login',
        'numero_contato',
        'envio_automatico_renovacao',
        'mensagem_audio',
        'token_api_wtz',
        'instance_id'

    ];

    use HasFactory;

    /**
     * Boot do model - eventos
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!isset($model->attributes['created_at']) || $model->attributes['created_at'] === null) {
                $model->attributes['created_at'] = now()->format('Y-m-d H:i:s');
            } elseif ($model->attributes['created_at'] instanceof \Carbon\Carbon) {
                $model->attributes['created_at'] = $model->attributes['created_at']->format('Y-m-d H:i:s');
            }
            if (!isset($model->attributes['updated_at']) || $model->attributes['updated_at'] === null) {
                $model->attributes['updated_at'] = now()->format('Y-m-d H:i:s');
            } elseif ($model->attributes['updated_at'] instanceof \Carbon\Carbon) {
                $model->attributes['updated_at'] = $model->attributes['updated_at']->format('Y-m-d H:i:s');
            }
        });
        
        static::updating(function ($model) {
            if (!isset($model->attributes['updated_at']) || $model->attributes['updated_at'] === null) {
                $model->attributes['updated_at'] = now()->format('Y-m-d H:i:s');
            } elseif ($model->attributes['updated_at'] instanceof \Carbon\Carbon) {
                $model->attributes['updated_at'] = $model->attributes['updated_at']->format('Y-m-d H:i:s');
            }
        });
    }

    public function users() {
        return $this->belongsToMany(User::class);
    }

    public function plano()
    {
        return $this->belongsTo(Planos::class);
    }

    public function locacoes()
    {
        return $this->hasMany(Locacao::class);
    }

    public function depositos()
    {
        return $this->hasMany(Deposito::class);
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function emprestimos()
    {
        return $this->hasMany(Emprestimo::class);
    }

    public function protheusAssociations()
    {
        return $this->hasMany(CompanyProtheusAssociation::class);
    }

    /**
     * Buscar o código da tabela Protheus associada a esta empresa
     * 
     * @param string $tabelaProtheus Código da tabela (ex: 'SA1010', 'SA2010')
     * @return CompanyProtheusAssociation|null
     */
    public function getProtheusAssociation($tabelaProtheus)
    {
        return $this->protheusAssociations()
            ->where('tabela_protheus', $tabelaProtheus)
            ->first();
    }

    /**
     * Buscar associação por descrição (tipo de tabela)
     * 
     * @param string $descricao Descrição da tabela (ex: 'Cliente', 'Fornecedor')
     * @return CompanyProtheusAssociation|null
     */
    public function getProtheusAssociationByDescricao($descricao)
    {
        return $this->protheusAssociations()
            ->where(function($query) use ($descricao) {
                $query->where('descricao', $descricao)
                      ->orWhere('descricao', 'LIKE', '%' . $descricao . '%');
            })
            ->first();
    }

    /**
     * Buscar código da tabela Protheus para clientes desta empresa
     * Busca dinamicamente na tabela de associação pela descrição "Cliente"
     * 
     * @return string|null
     */
    public function getTabelaCliente()
    {
        $association = $this->getProtheusAssociationByDescricao('Cliente');
        return $association ? $association->tabela_protheus : null;
    }

    /**
     * Buscar código da tabela Protheus para fornecedores desta empresa
     * Busca dinamicamente na tabela de associação pela descrição "Fornecedor"
     * 
     * @return string|null
     */
    public function getTabelaFornecedor()
    {
        $association = $this->getProtheusAssociationByDescricao('Fornecedor');
        return $association ? $association->tabela_protheus : null;
    }

    /**
     * Buscar fornecedores do Protheus usando a associação da empresa
     * Este método retorna a associação que pode ser usada para buscar na tabela do Protheus
     * Busca dinamicamente na tabela de associação pela descrição "Fornecedor"
     * 
     * @return CompanyProtheusAssociation|null
     */
    public function getFornecedoresProtheusAssociation()
    {
        return $this->getProtheusAssociationByDescricao('Fornecedor');
    }

    /**
     * Verificar se a empresa tem associação com tabela de fornecedores do Protheus
     * Busca dinamicamente na tabela de associação pela descrição "Fornecedor"
     * 
     * @return bool
     */
    public function hasFornecedoresProtheus()
    {
        return $this->getProtheusAssociationByDescricao('Fornecedor') !== null;
    }

    /**
     * Buscar todas as associações de fornecedores (caso haja múltiplas)
     * Busca dinamicamente na tabela de associação pela descrição "Fornecedor"
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllFornecedoresAssociations()
    {
        return $this->protheusAssociations()
            ->where(function($query) {
                $query->where('descricao', 'LIKE', '%fornecedor%')
                      ->orWhere('descricao', 'LIKE', '%Fornecedor%');
            })
            ->get();
    }

    /**
     * Buscar associação de clientes do Protheus
     * Busca dinamicamente na tabela de associação pela descrição "Cliente"
     * 
     * @return CompanyProtheusAssociation|null
     */
    public function getClientesProtheusAssociation()
    {
        return $this->getProtheusAssociationByDescricao('Cliente');
    }

    /**
     * Buscar associação de produtos do Protheus
     *
     * @return CompanyProtheusAssociation|null
     */
    public function getProdutosProtheusAssociation()
    {
        return $this->getProtheusAssociationByDescricao('Produto');
    }

    /**
     * Verificar se a empresa tem associação com tabela de produtos do Protheus
     *
     * @return bool
     */
    public function hasProdutosProtheus()
    {
        return $this->getProtheusAssociationByDescricao('Produto') !== null;
    }

    /**
     * Buscar associação de centro de custo do Protheus
     *
     * @return CompanyProtheusAssociation|null
     */
    public function getCentrosCustoProtheusAssociation()
    {
        return $this->getProtheusAssociationByDescricao('Centro de Custo');
    }

    /**
     * Verificar se a empresa tem associação com tabela de centros de custo do Protheus
     *
     * @return bool
     */
    public function hasCentrosCustoProtheus()
    {
        return $this->getProtheusAssociationByDescricao('Centro de Custo') !== null;
    }

    /**
     * Buscar associação de condição de pagamento do Protheus
     *
     * @return CompanyProtheusAssociation|null
     */
    public function getCondicoesPagamentoProtheusAssociation()
    {
        return $this->getProtheusAssociationByDescricao('Condição de Pagamento');
    }

    /**
     * Verificar se a empresa tem associação com tabela de condição de pagamento do Protheus
     *
     * @return bool
     */
    public function hasCondicoesPagamentoProtheus()
    {
        return $this->getProtheusAssociationByDescricao('Condição de Pagamento') !== null;
    }

    /**
     * Buscar associação de pedidos de compra do Protheus
     *
     * @return CompanyProtheusAssociation|null
     */
    public function getPedidosCompraProtheusAssociation()
    {
        return $this->getProtheusAssociationByDescricao('Pedido de Compra');
    }

    /**
     * Verificar se a empresa tem associação com tabela de pedidos de compra do Protheus
     *
     * @return bool
     */
    public function hasPedidosCompraProtheus()
    {
        return $this->getProtheusAssociationByDescricao('Pedido de Compra') !== null;
    }

    /**
     * Buscar associação de itens de pedido de compra do Protheus
     *
     * @return CompanyProtheusAssociation|null
     */
    public function getItensPedidoCompraProtheusAssociation()
    {
        return $this->getProtheusAssociationByDescricao('Item de Pedido de Compra');
    }

    /**
     * Verificar se a empresa tem associação com tabela de itens de pedido de compra do Protheus
     *
     * @return bool
     */
    public function hasItensPedidoCompraProtheus()
    {
        return $this->getProtheusAssociationByDescricao('Item de Pedido de Compra') !== null;
    }

    /**
     * Buscar associação de compradores do Protheus
     *
     * @return CompanyProtheusAssociation|null
     */
    public function getCompradoresProtheusAssociation()
    {
        return $this->getProtheusAssociationByDescricao('Comprador');
    }

    /**
     * Verificar se a empresa tem associação com tabela de compradores do Protheus
     *
     * @return bool
     */
    public function hasCompradoresProtheus()
    {
        return $this->getProtheusAssociationByDescricao('Comprador') !== null;
    }

    /**
     * Buscar associação de naturezas de operação do Protheus
     *
     * @return CompanyProtheusAssociation|null
     */
    public function getNaturezasOperacaoProtheusAssociation()
    {
        return $this->getProtheusAssociationByDescricao('Natureza de Operação');
    }

    /**
     * Verificar se a empresa tem associação com tabela de naturezas de operação do Protheus
     *
     * @return bool
     */
    public function hasNaturezasOperacaoProtheus()
    {
        return $this->getProtheusAssociationByDescricao('Natureza de Operação') !== null;
    }

    /**
     * Verificar se a empresa tem associação com tabela de clientes do Protheus
     * 
     * @return bool
     */
    public function hasClientesProtheus()
    {
        return $this->getProtheusAssociationByDescricao('Cliente') !== null;
    }
}
