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
        return $this->belongsTo(User::class, 'from_responsible_id');
    }

    public function toResponsible()
    {
        return $this->belongsTo(User::class, 'to_responsible_id');
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

