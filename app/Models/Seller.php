<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_name',
        'store_slug',
        'store_description',
        'store_logo_path',
        'status',
    ];

    // --- Relasi ---
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // Relasi polimorfik untuk alamat (sebagai addressable)
    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable');
    }
}
