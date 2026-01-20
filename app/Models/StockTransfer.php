<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_number',
        'origin_location_id',
        'destination_location_id',
        'driver_name',
        'license_plate',
        'status',
        'observation',
        'user_id',
        'company_id',
    ];

    protected $casts = [
        'origin_location_id' => 'integer',
        'destination_location_id' => 'integer',
        'user_id' => 'integer',
        'company_id' => 'integer',
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

            // Gerar número da transferência se não fornecido
            if (empty($model->transfer_number)) {
                $model->transfer_number = self::generateTransferNumber($model->company_id);
            }

            // Status padrão
            if (empty($model->status)) {
                $model->status = 'pendente';
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

    /**
     * Gerar número único de transferência
     */
    protected static function generateTransferNumber($companyId)
    {
        $year = date('Y');
        $lastTransfer = self::where('company_id', $companyId)
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastTransfer ? (int) substr($lastTransfer->transfer_number, -6) + 1 : 1;
        
        return sprintf('TRF-%s-%06d', $year, $sequence);
    }

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'origin_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'destination_location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function isPendente(): bool
    {
        return $this->status === 'pendente';
    }

    public function isRecebido(): bool
    {
        return $this->status === 'recebido';
    }
}
