<?php

namespace App\Modules\Commerce\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 */
class ProductCategory extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'product_categories';

    protected $fillable = ['name', 'slug', 'description'];

    /**
     * Get products in category.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
