<?php

namespace App\Models;

use App\Models\Company;
use App\Models\Permgroup;

use Illuminate\Database\Eloquent\SoftDeletes;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $dates = ['deleted_at'];

    /**
     * Eventos do modelo para formatar timestamps como strings
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            $now = now()->format('Y-m-d H:i:s');
            if (!isset($user->attributes['created_at']) || empty($user->attributes['created_at'])) {
                $user->attributes['created_at'] = $now;
            } else {
                $user->attributes['created_at'] = is_string($user->attributes['created_at']) 
                    ? $user->attributes['created_at'] 
                    : (is_object($user->attributes['created_at']) 
                        ? $user->attributes['created_at']->format('Y-m-d H:i:s') 
                        : $now);
            }
            if (!isset($user->attributes['updated_at']) || empty($user->attributes['updated_at'])) {
                $user->attributes['updated_at'] = $now;
            } else {
                $user->attributes['updated_at'] = is_string($user->attributes['updated_at']) 
                    ? $user->attributes['updated_at'] 
                    : (is_object($user->attributes['updated_at']) 
                        ? $user->attributes['updated_at']->format('Y-m-d H:i:s') 
                        : $now);
            }
        });

        static::updating(function ($user) {
            $now = now()->format('Y-m-d H:i:s');
            if (!isset($user->attributes['updated_at']) || empty($user->attributes['updated_at'])) {
                $user->attributes['updated_at'] = $now;
            } else {
                $user->attributes['updated_at'] = is_string($user->attributes['updated_at']) 
                    ? $user->attributes['updated_at'] 
                    : (is_object($user->attributes['updated_at']) 
                        ? $user->attributes['updated_at']->format('Y-m-d H:i:s') 
                        : $now);
            }
        });
    }

    protected $hidden = [
        'password'
    ];

    public $fillable = [
        'nome_completo',
        'rg',
        'cpf',
        'login',
        'data_nascimento',
        'sexo',
        'telefone_celular',
        'email',
        'signature_path',
        'status',
        'status_motivo',
        'tentativas',
        'password'
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function companies(){
        return $this->belongsToMany(Company::class);
    }



    public function groups() {
        return $this->belongsToMany(Permgroup::class);
    }

    public function getCompaniesAsString()
    {
        return $this->companies()->pluck('company')->implode(', ');
    }

    public function hasPermission($permission)
    {
        return $this->groups()->whereHas('items', function ($query) use ($permission) {
            $query->where('slug', $permission);
        })->exists();
    }

    // Método para obter o nome do grupo pelo ID da empresa
    public function getGroupNameByEmpresaId($empresaId)
    {
        $group = $this->groups()->where('company_id', $empresaId)->first();

        return $group ? $group->name : null;
    }

    public function getGroupByEmpresaId($empresaId)
    {
        $group = $this->groups()->where('company_id', $empresaId)->first();

        return $group ? $group : null;
    }
    public function emprestimos()
    {
        return $this->hasMany(Emprestimo::class, 'user_id', 'id');
    }

    public function locations()
    {
        return $this->hasMany(UserLocation::class);
    }
}

