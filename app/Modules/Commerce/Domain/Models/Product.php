<?php

namespace App\Modules\Commerce\Domain\Models;

use App\Modules\Academic\Models\Course;
use App\Modules\Assessment\Models\Test;
use App\Modules\Commerce\Domain\Enums\AssessmentFamily;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $category_id
 * @property string $title
 * @property string $slug
 * @property string $product_type
 * @property string|null $description
 * @property string|null $thumbnail_url
 * @property float $price
 * @property bool $is_active
 * @property bool $is_featured
 * @property string|null $course_id
 * @property string|null $test_id
 * @property string|null $assessment_family
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Course|null $course
 * @property Test|null $test
 */
class Product extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'product_type',
        'description',
        'thumbnail_url',
        'price',
        'is_active',
        'is_featured',
        'course_id',
        'test_id',
        'assessment_family',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * Get product category.
     *
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Get mapped course.
     *
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * Get mapped test.
     *
     * @return BelongsTo<Test, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class, 'test_id');
    }

    /**
     * Get all price change requests for this product.
     */
    public function priceChangeRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PriceChangeRequest::class, 'product_id');
    }

    /**
     * Get latest pending price change request for this product.
     */
    public function pendingPriceChangeRequest(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PriceChangeRequest::class, 'product_id')->where('status', 'pending')->latestOfMany();
    }

    /**
     * Determine if this product is an assessment type.
     */
    public function isAssessment(): bool
    {
        return $this->product_type === 'assessment';
    }

    /**
     * Determine if this product is an abstract assessment package (without specific test_id).
     */
    public function isAssessmentPackage(): bool
    {
        return $this->isAssessment() && empty($this->test_id);
    }

    /**
     * Determine if this product is tied to a specific Test instance.
     */
    public function hasSpecificTest(): bool
    {
        return $this->isAssessment() && !empty($this->test_id);
    }

    /**
     * Determine if this product has a declared assessment family.
     */
    public function hasAssessmentFamily(): bool
    {
        return !empty($this->getEffectiveFamily());
    }

    /**
     * Get the effective assessment family (either explicitly declared, derived from linked Test, or deterministically inferred for legacy abstract assessment packages).
     */
    public function getEffectiveFamily(): ?string
    {
        // Precedence A: Explicit assessment_family
        if (!empty($this->assessment_family)) {
            $family = is_object($this->assessment_family) ? $this->assessment_family->value : $this->assessment_family;
            return strtolower(trim((string) $family));
        }

        // Precedence B: Specific linked Test.test_type
        if ($this->test_id && $this->test) {
            $testType = $this->test->test_type;
            if (is_object($testType)) {
                return strtolower($testType->value);
            }
            if (is_string($testType)) {
                return strtolower(trim($testType));
            }
        }

        // Precedence C: Legacy abstract assessment-package inference, ONLY when:
        // - product_type === 'assessment'
        // - test_id is empty
        // - assessment_family is empty
        if ($this->product_type === 'assessment' && empty($this->test_id)) {
            return $this->inferLegacyAssessmentFamily();
        }

        return null;
    }

    /**
     * Deterministic, token-aware legacy family inference for abstract assessment packages.
     */
    protected function inferLegacyAssessmentFamily(): ?string
    {
        $allowedFamilies = AssessmentFamily::values();
        $matchedFamilies = [];

        // Extract alphanumeric tokens from slug and title
        $tokens = array_filter(array_merge(
            preg_split('/[^a-z0-9]+/i', (string) ($this->slug ?? '')) ?: [],
            preg_split('/[^a-z0-9]+/i', (string) ($this->title ?? '')) ?: []
        ));

        $normalizedTokens = array_map('strtolower', $tokens);

        foreach ($allowedFamilies as $family) {
            $familyLower = strtolower($family);
            if (in_array($familyLower, $normalizedTokens, true)) {
                $matchedFamilies[] = $familyLower;
            }
        }

        $uniqueMatches = array_values(array_unique($matchedFamilies));

        if (count($uniqueMatches) === 1) {
            return $uniqueMatches[0];
        }

        return null;
    }

    /**
     * Get product name attribute alias for title.
     */
    public function getNameAttribute(): string
    {
        return $this->title ?? '';
    }

    /**
     * Canonical validation rules for Product creation/updating.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(?string $productId = null): array
    {
        $allowedFamilies = implode(',', AssessmentFamily::values());

        return [
            'title'             => ['required', 'string', 'max:255'],
            'slug'              => ['nullable', 'string', 'max:255', 'unique:products,slug' . ($productId ? ",{$productId}" : '')],
            'product_type'      => ['required', 'string', 'in:assessment,course,membership,placement_test,corporate_training'],
            'assessment_family' => [
                'nullable',
                'required_if:product_type,assessment',
                'string',
                "in:{$allowedFamilies}",
            ],
            'test_id'           => ['nullable', 'exists:tests,id'],
            'course_id'         => ['nullable', 'exists:courses,id'],
            'price'             => ['required', 'numeric', 'min:0'],
            'is_active'         => ['boolean'],
            'is_featured'       => ['boolean'],
            'description'       => ['nullable', 'string'],
        ];
    }
}
