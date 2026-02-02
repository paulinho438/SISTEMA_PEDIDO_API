<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResponsibilityTerm extends Model
{
    protected $table = 'responsibility_terms';

    protected $fillable = [
        'numero',
        'responsible_name',
        'cpf',
        'project',
        'stock_location_id',
        'status',
        'company_id',
        'created_by',
        'returned_by',
        'returned_at',
        'observation',
    ];

    protected $casts = [
        'returned_at' => 'datetime',
    ];

    public const STATUS_ABERTO = 'aberto';
    public const STATUS_DEVOLVIDO = 'devolvido';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function returnedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ResponsibilityTermItem::class, 'responsibility_term_id');
    }

    public function isDevolvido(): bool
    {
        return $this->status === self::STATUS_DEVOLVIDO;
    }
}
