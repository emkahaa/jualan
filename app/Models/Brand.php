<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'description',
        'status',
        'meta_title',
        'meta_description',
    ];

    // --- Relasi ---
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
