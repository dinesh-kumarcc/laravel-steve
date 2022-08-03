<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'shopify_order_id','shopify_order_name','email','subtotal_price','total_price','checkout_method','due_date'
    ];

    /**
     * A order can have many line_items
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
    */
    public function line_items()
    {
      return $this->hasMany(LineItem::class);
    }

    public function customer()
    {
      return $this->hasOne(Customer::class);
    }
}
