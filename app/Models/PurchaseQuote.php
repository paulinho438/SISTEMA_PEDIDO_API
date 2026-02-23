<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class PurchaseQuote extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'quote_number',
        'requested_at',
        'requester_id',
        'requester_name',
        'company_id',
        'company_name',
        'location',
        'work_front',
        'observation',
        'requires_response',
        'protheus_export_status',
        'protheus_exported_at',
        'current_status_id',
        'current_status_slug',
        'current_status_label',
        'main_cost_center_code',
        'main_cost_center_description',
        'buyer_id',
        'buyer_name',
        'engineer_id',
        'engineer_name',
        'created_by',
        'updated_by',
        'payment_condition_code',
        'payment_condition_description',
        'freight_type',
        'nature_operation_code',
        'nature_operation_description',
        'nature_operation_cfop',
        'reset_reason',
        'reset_at',
        'reset_by',
    ];

    protected $casts = [
        // 'requested_at' => 'date', // Removido - o mutator cuida da conversão
        'protheus_exported_at' => 'datetime',
        'reset_at' => 'datetime',
    ];

    /**
     * Boot do model - eventos
     */
    protected static function boot()
    {
        parent::boot();

        // Garantir que requested_at, created_at e updated_at sejam sempre strings antes de salvar
        static::creating(function ($model) {
            // Converter requested_at para string Y-m-d
            if (isset($model->attributes['requested_at']) && $model->attributes['requested_at'] !== null) {
                $value = $model->attributes['requested_at'];
                if ($value instanceof \Carbon\Carbon) {
                    $model->attributes['requested_at'] = $value->format('Y-m-d');
                } elseif (is_string($value)) {
                    try {
                        $parsed = \Carbon\Carbon::parse($value);
                        $model->attributes['requested_at'] = $parsed->format('Y-m-d');
                    } catch (\Exception $e) {
                        $model->attributes['requested_at'] = $value;
                    }
                } else {
                    $model->attributes['requested_at'] = (string) $value;
                }
            }
            
            // Converter created_at e updated_at para string Y-m-d H:i:s
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
        
        // Garantir que updated_at seja sempre string ao atualizar
        static::updating(function ($model) {
            if (!isset($model->attributes['updated_at']) || $model->attributes['updated_at'] === null) {
                $model->attributes['updated_at'] = now()->format('Y-m-d H:i:s');
            } elseif ($model->attributes['updated_at'] instanceof \Carbon\Carbon) {
                $model->attributes['updated_at'] = $model->attributes['updated_at']->format('Y-m-d H:i:s');
            }
        });
    }

    /**
     * Mutator para garantir que requested_at seja salvo como string Y-m-d
     * para compatibilidade com SQL Server DATE type (sem hora)
     * IMPORTANTE: Sempre salva como string literal para SQL Server
     */
    public function setRequestedAtAttribute($value)
    {
        if ($value instanceof \Carbon\Carbon) {
            // Converter Carbon para string Y-m-d (sem hora)
            $this->attributes['requested_at'] = $value->format('Y-m-d');
        } elseif (is_string($value) && !empty($value)) {
            // Se já é string, garantir formato Y-m-d e garantir que seja tratada como string
            try {
                $parsed = \Carbon\Carbon::parse($value);
                $formatted = $parsed->format('Y-m-d');
                // Forçar como string explicitamente
                $this->attributes['requested_at'] = (string) $formatted;
            } catch (\Exception $e) {
                // Se não conseguir parsear, usar como está mas garantir que seja string
                $this->attributes['requested_at'] = (string) $value;
            }
        } elseif ($value === null) {
            $this->attributes['requested_at'] = null;
        } else {
            // Qualquer outro tipo, converter para string
            $this->attributes['requested_at'] = (string) $value;
        }
    }

    /**
     * Accessor para converter string Y-m-d de volta para Carbon quando ler do banco.
     * A data é apenas dia (sem hora); deve ser interpretada no timezone da aplicação
     * sem conversão, para não exibir dia anterior (ex: 11/02 virar 09/02).
     */
    public function getRequestedAtAttribute($value)
    {
        if ($value) {
            try {
                if ($value instanceof \Carbon\Carbon) {
                    return $value->copy()->startOfDay();
                }
                $tz = config('app.timezone', 'America/Sao_Paulo');
                // Criar a data já no timezone da aplicação (3º parâmetro) para não converter e mudar o dia
                if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    return \Carbon\Carbon::createFromFormat('Y-m-d', $value, $tz)->startOfDay();
                }
                // Valor com hora (ex: retorno do SQL Server): tratar como data apenas no timezone da app
                if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                    $dateOnly = substr($value, 0, 10);
                    return \Carbon\Carbon::createFromFormat('Y-m-d', $dateOnly, $tz)->startOfDay();
                }
                $carbon = \Carbon\Carbon::parse($value, $tz);
                return $carbon->startOfDay();
            } catch (\Exception $e) {
                return $value;
            }
        }
        return $value;
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseQuoteItem::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(PurchaseQuoteSupplier::class, 'purchase_quote_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PurchaseQuoteMessage::class, 'purchase_quote_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuoteStatus::class, 'current_status_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(PurchaseQuoteStatusHistory::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_id')->withDefault();
    }

    public function resetByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reset_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id')->withDefault();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'purchase_quote_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PurchaseQuoteApproval::class);
    }

    public function getRequiredApprovals()
    {
        return $this->approvals()->required()->ordered()->get();
    }

    public function getPendingApprovals()
    {
        return $this->approvals()->pending()->ordered()->get();
    }

    public function getNextApprovalLevel()
    {
        $pending = $this->getPendingApprovals();
        return $pending->first();
    }

    public function isAllApproved(): bool
    {
        $required = $this->approvals()->required()->count();
        $approved = $this->approvals()->required()->approved()->count();
        
        return $required > 0 && $required === $approved;
    }

    /**
     * Gera o próximo número de solicitação (ex.: SOL-0343).
     * Usa o maior número já existente em quote_number (parte numérica) + 1,
     * para evitar saltos na numeração quando o ID da tabela (auto-increment) pula.
     */
    public static function generateNextNumber(): string
    {
        $driver = (new static)->getConnection()->getDriverName();
        $maxNum = 0;

        if ($driver === 'sqlsrv') {
            $raw = self::whereNotNull('quote_number')
                ->where('quote_number', 'like', 'SOL-%')
                ->selectRaw("MAX(TRY_CAST(SUBSTRING(quote_number, 5, 50) AS INT)) as max_num")
                ->value('max_num');
            $maxNum = (int) $raw;
        } else {
            $raw = self::whereNotNull('quote_number')
                ->where('quote_number', 'like', 'SOL-%')
                ->selectRaw("MAX(CAST(SUBSTRING(quote_number, 5, 50) AS UNSIGNED)) as max_num")
                ->value('max_num');
            $maxNum = (int) $raw;
        }

        $next = $maxNum + 1;

        return 'SOL-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
