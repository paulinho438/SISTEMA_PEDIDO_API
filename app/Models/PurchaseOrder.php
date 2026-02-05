<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    // Constantes de Status
    const STATUS_PENDENTE = 'pendente';
    const STATUS_LINK = 'link';
    const STATUS_LINK_APROVADO = 'link_aprovado';
    const STATUS_LINK_REPROVADO = 'link_reprovado';
    const STATUS_COLETA = 'coleta';
    const STATUS_EM_TRANSITO = 'em_transito';
    const STATUS_ATENDIDO = 'atendido';
    const STATUS_ATENDIDO_PARCIAL = 'atendido_parcial';
    const STATUS_PAGAMENTO = 'pagamento';
    const STATUS_ENCERRADO = 'encerrado';

    protected $fillable = [
        'order_number',
        'order_date',
        'expected_delivery_date',
        'purchase_quote_id',
        'purchase_quote_supplier_id',
        'supplier_name',
        'supplier_document',
        'supplier_code',
        'vendor_name',
        'vendor_phone',
        'vendor_email',
        'proposal_number',
        'freight_type',
        'freight_value',
        'total_amount',
        'status',
        'observation',
        'protheus_order_number',
        'protheus_exported_at',
        'company_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'protheus_exported_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'freight_value' => 'decimal:2',
    ];

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

    public function quote(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuote::class, 'purchase_quote_id');
    }

    public function quoteSupplier(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuoteSupplier::class, 'purchase_quote_supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function generateNextNumber(): string
    {
        $nextId = (int) self::max('id') + 1;
        return 'PED-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Retorna os status válidos para transição a partir do status atual
     */
    public function getValidNextStatuses(): array
    {
        // Normalizar status: tratar vazio/null como 'pendente'
        $currentStatus = trim($this->status ?? '') ?: self::STATUS_PENDENTE;
        
        $transitions = [
            self::STATUS_PENDENTE => [self::STATUS_LINK], // Mantido para pedidos antigos ou migração
            self::STATUS_LINK => [self::STATUS_LINK_APROVADO, self::STATUS_LINK_REPROVADO],
            self::STATUS_LINK_APROVADO => [self::STATUS_COLETA],
            self::STATUS_COLETA => [self::STATUS_EM_TRANSITO],
            self::STATUS_EM_TRANSITO => [self::STATUS_ATENDIDO, self::STATUS_ATENDIDO_PARCIAL],
            self::STATUS_ATENDIDO => [self::STATUS_PAGAMENTO],
            self::STATUS_ATENDIDO_PARCIAL => [self::STATUS_PAGAMENTO],
            self::STATUS_PAGAMENTO => [self::STATUS_ENCERRADO],
        ];

        // Se o status atual está nas transições, retornar normalmente
        if (isset($transitions[$currentStatus])) {
            return $transitions[$currentStatus];
        }

        // Se é um status antigo, permitir migração para status novos
        $oldStatuses = ['recebido', 'parcial', 'parcialmente_recebido'];
        if (in_array($currentStatus, $oldStatuses)) {
            // Permitir migrar para status equivalentes ou próximos no fluxo
            return [
                self::STATUS_ATENDIDO,           // Se estava "recebido"
                self::STATUS_ATENDIDO_PARCIAL,   // Se estava "parcial"
                self::STATUS_PAGAMENTO,          // Se já foi atendido, pode ir para pagamento
                self::STATUS_ENCERRADO,          // Se já foi pago, pode encerrar
            ];
        }

        return [];
    }

    /**
     * Verifica se uma transição de status é válida
     */
    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, $this->getValidNextStatuses());
    }

    /**
     * Relacionamento com histórico de status
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(PurchaseOrderStatusHistory::class);
    }
}
