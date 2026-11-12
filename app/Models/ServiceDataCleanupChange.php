<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Change log written by the data-cleanup backfill so it can be reversed.
 */
class ServiceDataCleanupChange extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'service_data_cleanup_changes';

    protected $fillable = [
        'migration',
        'table_name',
        'row_id',
        'column_name',
        'old_value',
        'new_value',
    ];

    /**
     * Record a single column change.
     */
    public static function record(
        string $migration,
        string $table,
        int|string $rowId,
        string $column,
        mixed $old,
        mixed $new,
    ): self {
        return static::create([
            'migration' => $migration,
            'table_name' => $table,
            'row_id' => (string) $rowId,
            'column_name' => $column,
            'old_value' => $old === null ? null : (string) $old,
            'new_value' => $new === null ? null : (string) $new,
        ]);
    }
}
