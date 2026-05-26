<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'status',
        'amount',
        'description',
        'product_name',
        'price',
        'webhook_snapshot',
    ];

    protected $casts = [
        'webhook_snapshot' => 'array',
    ];
}
