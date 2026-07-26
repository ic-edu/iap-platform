<?php

namespace App\Modules\Commerce\Domain\Models;

use App\Modules\Academic\Models\Course;
use App\Modules\Assessment\Models\Test;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}
