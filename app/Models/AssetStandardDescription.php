<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetStandardDescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'active',
        'company_id',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Boot do model - eventos
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Sempre forçar formato ISO para SQL Server (evita conversão nvarchar → datetime fora do intervalo)
            $now = now()->format('Y-m-d H:i:s');
            $createdAt = $model->attributes['created_at'] ?? null;
            $updatedAt = $model->attributes['updated_at'] ?? null;
            $model->attributes['created_at'] = $createdAt instanceof \Carbon\Carbon
                ? $createdAt->format('Y-m-d H:i:s')
                : $now;
            $model->attributes['updated_at'] = $updatedAt instanceof \Carbon\Carbon
                ? $updatedAt->format('Y-m-d H:i:s')
                : $now;
        });

        static::updating(function ($model) {
            $updatedAt = $model->attributes['updated_at'] ?? null;
            $model->attributes['updated_at'] = $updatedAt instanceof \Carbon\Carbon
                ? $updatedAt->format('Y-m-d H:i:s')
                : now()->format('Y-m-d H:i:s');
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class, 'standard_description_id');
    }
}

