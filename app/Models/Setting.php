<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One admin-editable application setting. Read and written through SettingsService,
 * which defines the allowed keys — never store secrets or credentials here.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property int|null $updated_by
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by'];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
