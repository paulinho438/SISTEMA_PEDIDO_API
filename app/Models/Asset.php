<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_number',
        'increment',
        'acquisition_date',
        'status',
        'movement_number',
        'standard_description_id',
        'sub_type_1_id',
        'sub_type_2_id',
        'description',
        'brand',
        'model',
        'serial_number',
        'tag',
        'dimension',
        'capacity',
        'use_condition_id',
        'motor',
        'rpm',
        'manufacture_year',
        'old_number',
        'item_quantity',
        'auxiliary',
        'complement',
        'supplier_id',
        'document_number',
        'document_issue_date',
        'nfe_key',
        'observation',
        'asset_url',
        'image_path',
        'branch_id',
        'account_id',
        'cost_center_id',
        'cost_center_code',
        'location_id',
        'responsible_id',
        'project_id',
        'business_unit_id',
        'grouping_id',
        'value_brl',
        'value_usd',
        'purchase_reference_type',
        'purchase_reference_id',
        'purchase_reference_number',
        'purchase_quote_item_id',
        'company_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'document_issue_date' => 'date',
        'item_quantity' => 'decimal:2',
        'value_brl' => 'decimal:2',
        'value_usd' => 'decimal:2',
        'increment' => 'integer',
        'manufacture_year' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $now = now()->format('Y-m-d H:i:s');
            $model->attributes['created_at'] = $model->attributes['created_at'] instanceof \Carbon\Carbon
                ? $model->attributes['created_at']->format('Y-m-d H:i:s')
                : $now;
            $model->attributes['updated_at'] = $model->attributes['updated_at'] instanceof \Carbon\Carbon
                ? $model->attributes['updated_at']->format('Y-m-d H:i:s')
                : $now;
        });

        static::updating(function (self $model): void {
            $model->attributes['updated_at'] = $model->attributes['updated_at'] instanceof \Carbon\Carbon
                ? $model->attributes['updated_at']->format('Y-m-d H:i:s')
                : now()->format('Y-m-d H:i:s');
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function standardDescription()
    {
        return $this->belongsTo(AssetStandardDescription::class, 'standard_description_id');
    }

    public function subType1()
    {
        return $this->belongsTo(AssetSubType1::class, 'sub_type_1_id');
    }

    public function subType2()
    {
        return $this->belongsTo(AssetSubType2::class, 'sub_type_2_id');
    }

    public function useCondition()
    {
        return $this->belongsTo(AssetUseCondition::class, 'use_condition_id');
    }

    public function branch()
    {
        return $this->belongsTo(AssetBranch::class, 'branch_id');
    }

    public function account()
    {
        return $this->belongsTo(AssetAccount::class, 'account_id');
    }

    public function costCenter()
    {
        return $this->belongsTo(Costcenter::class, 'cost_center_id');
    }

    public function location()
    {
        return $this->belongsTo(AssetLocation::class, 'location_id');
    }

    public function responsible()
    {
        return $this->belongsTo(AssetResponsible::class, 'responsible_id');
    }

    public function project()
    {
        return $this->belongsTo(AssetProject::class, 'project_id');
    }

    public function businessUnit()
    {
        return $this->belongsTo(AssetBusinessUnit::class, 'business_unit_id');
    }

    public function grouping()
    {
        return $this->belongsTo(AssetGrouping::class, 'grouping_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Fornecedor::class, 'supplier_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function movements()
    {
        return $this->hasMany(AssetMovement::class);
    }

    public function images()
    {
        return $this->hasMany(AssetImage::class);
    }
}

