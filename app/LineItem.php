<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LineItem extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'line_item_id','order_id','name','product_id','variant_id','quantity','counter'
    ];

    /**
     * A line_item belong to a order
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function orders()
    {
      return $this->belongsTo(Order::class);
    }
}
