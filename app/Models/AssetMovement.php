<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'movement_type',
        'movement_date',
        'from_branch_id',
        'to_branch_id',
        'from_location_id',
        'to_location_id',
        'from_responsible_id',
        'to_responsible_id',
        'from_cost_center_id',
        'to_cost_center_id',
        'observation',
        'user_id',
        'reference_type',
        'reference_id',
        'reference_number',
    ];

    protected $casts = [
        'movement_date' => 'date',
    ];

    /**
     * Boot do model - eventos
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $now = now()->format('Y-m-d H:i:s');
            $createdAt = $model->attributes['created_at'] ?? null;
            $updatedAt = $model->attributes['updated_at'] ?? null;
            $model->attributes['created_at'] = $createdAt instanceof \Carbon\Carbon
                ? $createdAt->format('Y-m-d H:i:s')
                : $now;
            $model->attributes['updated_at'] = $updatedAt instanceof \Carbon\Carbon
                ? $updatedAt->format('Y-m-d H:i:s')
                : $now;
            // Coluna DATE no SQL Server: enviar só Y-m-d
            $movDate = $model->attributes['movement_date'] ?? null;
            if ($movDate !== null) {
                $model->attributes['movement_date'] = $movDate instanceof \Carbon\Carbon
                    ? $movDate->format('Y-m-d')
                    : \Carbon\Carbon::parse($movDate)->format('Y-m-d');
            }
        });

        static::updating(function ($model) {
            $updatedAt = $model->attributes['updated_at'] ?? null;
            $model->attributes['updated_at'] = $updatedAt instanceof \Carbon\Carbon
                ? $updatedAt->format('Y-m-d H:i:s')
                : now()->format('Y-m-d H:i:s');
            $movDate = $model->attributes['movement_date'] ?? null;
            if ($movDate !== null) {
                $model->attributes['movement_date'] = $movDate instanceof \Carbon\Carbon
                    ? $movDate->format('Y-m-d')
                    : \Carbon\Carbon::parse($movDate)->format('Y-m-d');
            }
        });
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromBranch()
    {
        return $this->belongsTo(AssetBranch::class, 'from_branch_id');
    }

    public function toBranch()
    {
        return $this->belongsTo(AssetBranch::class, 'to_branch_id');
    }

    public function fromLocation()
    {
        return $this->belongsTo(StockLocation::class, 'from_location_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(StockLocation::class, 'to_location_id');
    }

    public function fromResponsible()
    {
        return $this->belongsTo(AssetResponsible::class, 'from_responsible_id');
    }

    public function toResponsible()
    {
        return $this->belongsTo(AssetResponsible::class, 'to_responsible_id');
    }

    public function fromCostCenter()
    {
        return $this->belongsTo(Costcenter::class, 'from_cost_center_id');
    }

    public function toCostCenter()
    {
        return $this->belongsTo(Costcenter::class, 'to_cost_center_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

