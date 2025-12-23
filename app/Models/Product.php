<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'price',
        'stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    /**
     * Get product by SKU
     */
    public static function findBySku(string $sku): ?self
    {
        return self::where('sku', $sku)->first();
    }

    /**
     * Update or create product
     */
    public static function updateOrCreateBySku(array $data): self
    {
        return self::updateOrCreate(
            ['sku' => $data['sku']],
            $data
        );
    }
}