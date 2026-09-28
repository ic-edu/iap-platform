<?php

namespace App\Modules\QuestionEngine\Models;

use App\Modules\QuestionBank\Models\Question;
use App\Modules\QuestionEngine\Enums\AssessmentFamily;
use App\Modules\QuestionEngine\Enums\StandardStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property AssessmentFamily $assessment_family
 * @property string $standard_code
 * @property string $version
 * @property string $title
 * @property string $provider
 * @property StandardStatus $status
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_until
 * @property string|null $source_name
 * @property string|null $source_url
 * @property Carbon|null $source_checked_at
 * @property array $structure_definition
 * @property array|null $blueprint_definition
 * @property array|null $validation_definition
 * @property array|null $scoring_definition
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class AssessmentStandard extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'assessment_standards';

    protected $fillable = [
        'assessment_family',
        'standard_code',
        'version',
        'title',
        'provider',
        'status',
        'effective_from',
        'effective_until',
        'source_name',
        'source_url',
        'source_checked_at',
        'structure_definition',
        'blueprint_definition',
        'validation_definition',
        'scoring_definition',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'assessment_family' => AssessmentFamily::class,
            'status' => StandardStatus::class,
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'source_checked_at' => 'datetime',
            'structure_definition' => 'array',
            'blueprint_definition' => 'array',
            'validation_definition' => 'array',
            'scoring_definition' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Check if this standard version is active.
     */
    public function isActive(): bool
    {
        return $this->status === StandardStatus::Active;
    }

    /**
     * Check if this standard version is superseded.
     */
    public function isSuperseded(): bool
    {
        return $this->status === StandardStatus::Superseded;
    }

    /**
     * Check if this standard version is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === StandardStatus::Archived;
    }

    /**
     * Get questions generated or bound under this standard version.
     *
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'assessment_standard_id');
    }
}
