<?php

namespace App\Models;

use App\Modules\QuestionBank\Models\QuestionBank;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configurable Taxonomy Category for Academic Content Library (ACL).
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $test_type
 * @property string|null $section_code
 * @property int $target_questions
 * @property string|null $description
 * @property string $icon
 * @property bool $is_active
 */
class AclCategory extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'acl_categories';

    protected $fillable = [
        'name',
        'slug',
        'test_type',
        'section_code',
        'target_questions',
        'description',
        'icon',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target_questions' => 'integer',
            'is_active'        => 'boolean',
        ];
    }

    /**
     * Get question banks assigned to this ACL category.
     *
     * @return HasMany<QuestionBank, $this>
     */
    public function questionBanks(): HasMany
    {
        return $this->hasMany(QuestionBank::class, 'acl_category_id');
    }
}
