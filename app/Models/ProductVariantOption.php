<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductVariantOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'option_name',
        'option_value',
    ];

    // --- Relasi ---
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
