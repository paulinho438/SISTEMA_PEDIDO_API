<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotaoCobranca extends Model
{
    public $table = 'button_clicks_cobranca';

    protected $fillable = [
        'is_active',
        'click_count',
        'company_id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

}
