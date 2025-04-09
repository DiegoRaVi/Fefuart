<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'quantity',
        'category',
        'subcategory',
        'delivery_type',
        'delivery_time',
        'image_url',
        'stock',
        'order_id',
    ];

    public function order(){
        return $this->belongsTo(Order::class);
}
}
