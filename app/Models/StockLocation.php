<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StockLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'address',
        'active',
        'company_id',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function almoxarifes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'stock_almoxarife_locations', 'stock_location_id', 'user_id')
            ->withPivot('company_id')
            ->withTimestamps();
    }
}

