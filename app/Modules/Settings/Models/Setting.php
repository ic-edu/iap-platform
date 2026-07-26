<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $key
 * @property string|null $value
 * @property string $group
 */
class Setting extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
        'group',
    ];
}
