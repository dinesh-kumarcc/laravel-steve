<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'order_id','shopify_customer_id','name'
    ];

    /**
     * A order can have many line_items
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
    */
    public function orders()
    {
      return $this->belongsTo(Order::class);
    }
}
