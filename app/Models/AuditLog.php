<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Retorna o tipo auditable em formato curto (ex: 'asset', 'purchase_quote').
     */
    public static function auditableTypeFor(Model $model): string
    {
        $map = [
            Asset::class => 'asset',
            PurchaseQuote::class => 'purchase_quote',
            PurchaseOrder::class => 'purchase_order',
            PurchaseInvoice::class => 'purchase_invoice',
            AssetBranch::class => 'asset_branch',
            AssetLocation::class => 'asset_location',
            AssetStandardDescription::class => 'asset_standard_description',
        ];

        return $map[get_class($model)] ?? class_basename($model);
    }

    /**
     * Registra manualmente uma ação na auditoria (ex: quando o insert é feito via SQL bruto).
     */
    public static function record(string $auditableType, string $auditableId, string $action, ?array $oldValues = null, ?array $newValues = null): void
    {
        $payload = [
            'user_id' => \Illuminate\Support\Facades\Auth::id(),
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent() ? substr(request()->userAgent(), 0, 500) : null,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
        self::query()->insert($payload);
    }
}
