<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;

    public $table = 'banks';

    /**
     * Os atributos que podem ser atribuídos em massa.
     *
     * @var array
     */
    protected $fillable = [
        'ispb',
        'short_name',
        'code_number',
        'participation_in_compe',
        'main_access',
        'full_name',
        'start_date',
    ];

    /**
     * Os atributos que devem ser tratados como data.
     *
     * @var array
     */
    protected $dates = [
        'start_date',
    ];
}
