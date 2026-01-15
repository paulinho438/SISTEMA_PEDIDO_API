<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseQuoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_quote_id',
        'product_code',
        'reference',
        'description',
        'quantity',
        'unit',
        'application',
        'priority_days',
        'tag',
        'cost_center_code',
        'cost_center_description',
        'selected_supplier_id',
        'selected_unit_cost',
        'selected_total_cost',
        'selection_reason',
        'tes_code',
        'tes_description',
        'cfop_code',
    ];

    protected $casts = [
        'quantity' => 'float',
        'priority_days' => 'integer',
        'selected_unit_cost' => 'float',
        'selected_total_cost' => 'float',
    ];

    /**
     * Boot do model - eventos
     */
    protected static function boot()
    {
        parent::boot();

        // Garantir que created_at e updated_at sejam sempre strings antes de salvar
        static::creating(function ($model) {
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

    public function quote(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuote::class, 'purchase_quote_id');
    }

    public function selectedSupplier(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuoteSupplier::class, 'selected_supplier_id');
    }
}
