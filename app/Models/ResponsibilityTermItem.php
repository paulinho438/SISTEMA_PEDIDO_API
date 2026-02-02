<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponsibilityTermItem extends Model
{
    protected $table = 'responsibility_term_items';

    protected $fillable = [
        'responsibility_term_id',
        'stock_product_id',
        'stock_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public function responsibilityTerm(): BelongsTo
    {
        return $this->belongsTo(ResponsibilityTerm::class);
    }

    public function stockProduct(): BelongsTo
    {
        return $this->belongsTo(StockProduct::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
