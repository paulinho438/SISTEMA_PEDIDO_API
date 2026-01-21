<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_product_id',
        'stock_location_id',
        'quantity_available',
        'quantity_reserved',
        'quantity_total',
        'min_stock',
        'max_stock',
        'last_movement_at',
        'company_id',
    ];

    protected $casts = [
        'quantity_available' => 'decimal:4',
        'quantity_reserved' => 'decimal:4',
        'quantity_total' => 'decimal:4',
        'min_stock' => 'decimal:4',
        'max_stock' => 'decimal:4',
        'last_movement_at' => 'datetime',
    ];

    // Removido boot() que formatava datas como string
    // O Eloquent gerencia timestamps automaticamente
    // Para inserções diretas no SQL Server, use insertStockWithStringTimestamps() nos serviços

    public function product(): BelongsTo
    {
        return $this->belongsTo(StockProduct::class, 'stock_product_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}

