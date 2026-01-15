<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookCobranca extends Model
{
    protected $table = 'webhook_cobranca';

    protected $fillable = [
        'payload',
        'processado',
        'identificador',
        'qt_identificadores',
        'valor'
    ];

    protected $casts = [
        'payload' => 'array',
        'processado' => 'boolean',
    ];
}
