<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomLog extends Model
{
    use HasFactory;

    public $fillable = [
        'user_id',
        'content',
        'operation',
    ];

    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = now()->format('Y-m-d H:i:s');
            $model->updated_at = now()->format('Y-m-d H:i:s');
        });
    }
}
