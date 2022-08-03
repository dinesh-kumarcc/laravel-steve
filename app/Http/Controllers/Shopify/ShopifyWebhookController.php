<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Order;
use App\LineItem;
use App\Customer;
use App\Product;
use App\Events\ItemCompleted;
use App\Events\ItemAdded;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

class ShopifyWebhookController extends Controller
{
    public function orderCreate(Request $request)
    {
        logger('order create webhook working');

        $data = $request->all();
        $data_string = request()->getContent();
        $hmac_header = request()->header('x-shopify-hmac-sha256') ?: '';
        $shop_domain = request()->header('x-shopify-shop-domain');

        logger(json_encode($data));
        if(!empty($data)){
            $verified = $this->verifyWebhook($data_string,$hmac_header);
            if($verified){
                $checkout_method = null; $due_date = null;
                foreach($data['note_attributes'] as $key => $atttribute){
                    if($atttribute['name'] == "Checkout-Method"){
                        $checkout_method = $atttribute['value'];
                    }
                    if($atttribute['name'] == "Pickup-Date" || $atttribute['name'] == "Shipping-Date" || $atttribute['name'] == "Delivery-Date"){
                        $due_date = $atttribute['value'];
                    }
                    if($atttribute['name'] == "Pickup-Time"){
                        $due_time = $atttribute['value'];
                    }
                }

                if(isset($checkout_method) && $checkout_method != null && isset($due_date) && $due_date != null){
                    $order_exist = Order::where('shopify_order_id', (string) $data['id'])->count();
                    if($order_exist == 0){
                        $new_order = Order::firstOrCreate(
                            ['shopify_order_id' => (string) $data['id']],
                            ['shopify_order_name' => $data['name'], 'email' => $data['email'], 'subtotal_price' => $data['subtotal_price'], 'total_price' => $data['total_price'], 'checkout_method' => $checkout_method, 'due_date' => $due_date, 'source' => $data['source_name'] ]
                        );
                        // $new_order->gift_message = $data['note'];
                        if(isset($due_time)){
                            $new_order->due_time = $due_time;
                        }
                        $new_order->save();

                        foreach ($data['line_items'] as $key => $LineItem) {
                            for ($i=1; $i <= $LineItem['quantity']; $i++) { 
                                $updated_item = LineItem::create(
                                    ['line_item_id' => (string) $LineItem['id'], 'order_id' => $new_order->id, 'name' => $LineItem['name'], 'product_id' => $LineItem['product_id'], 'variant_id' => $LineItem['variant_id'], 'quantity' => 1, 'status' => 0]
                                );
                            }
                        }
                        $today = Carbon::today()->toDateString();
                        logger('today='.$today.', due_date='.$new_order->due_date);
                        if($new_order->due_date == $today){
                            logger('ItemAdded event broadcast');
                            broadcast(new ItemAdded($updated_item))->toOthers();
                        }else{
                            broadcast(new ItemCompleted($updated_item))->toOthers();
                        }

                        $customer = Customer::firstOrCreate(
                            ['shopify_customer_id' => (string) $data['customer']['id']],
                            ['shopify_customer_id' => $data['customer']['id'], 'order_id' => $new_order->id, 'name' => $data['customer']['first_name'].' '.$data['customer']['last_name'] ]
                        );
                    }
                }
            }
        }
    }

    public function orderUpdate(Request $request)
    {
        // echo 'update webhook';
        logger('order update webhook working');
        // die();
        $data = $request->all();
        $data_string = request()->getContent();
        $hmac_header = request()->header('x-shopify-hmac-sha256') ?: '';
        $shop_domain = request()->header('x-shopify-shop-domain');

        logger(json_encode($data));
        if(!empty($data)){
            $verified = $this->verifyWebhook($data_string,$hmac_header);
            if($verified){
                if($data['cancelled_at'] == null || $data['cancelled_at'] == ''){
                    $checkout_method = null; $due_date = null;
                    foreach($data['note_attributes'] as $key => $atttribute){
                        if($atttribute['name'] == "Checkout-Method"){
                            $checkout_method = $atttribute['value'];
                        }
                        if($atttribute['name'] == "Pickup-Date" || $atttribute['name'] == "Shipping-Date" || $atttribute['name'] == "Delivery-Date"){
                            $due_date = $atttribute['value'];
                        }
                        if($atttribute['name'] == "Pickup-Time"){
                            $due_time = $atttribute['value'];
                        }
                    }

                    if(isset($checkout_method) && $checkout_method != null && isset($due_date) && $due_date != null){
                        Order::updateOrCreate(
                            ['shopify_order_id' => (string) $data['id']],
                            ['shopify_order_name' => $data['name'], 'email' => $data['email'], 'subtotal_price' => $data['subtotal_price'], 'total_price' => $data['total_price'], 'checkout_method' => $checkout_method, 'due_date' => $due_date, 'source' => $data['source_name'] ]
                        );
                        $new_order = Order::where('shopify_order_id', $data['id'])->first();
                        // $new_order->gift_message = $data['note'];
                        if(isset($due_time)){
                            $new_order->due_time = $due_time;
                        }
                        $new_order->save();

                        $its_new_order = 0;
                        foreach ($data['line_items'] as $key => $LineItem) {
                            // if($LineItem->fulfillment_status == 'fulfilled'){
                            //     $line_item_found = LineItem::where('line_item_id', $LineItem['id'])->first();
                            //     if($line_item_found){
                            //         $line_item_found->delete();
                            //     }
                            // }

                            $line_item_exists = LineItem::where('line_item_id', $LineItem['id'])->count();
                            if($line_item_exists == 0){
                                $its_new_order = 1;
                                for ($i=1; $i <= $LineItem['quantity']; $i++) { 
                                    $updated_item = LineItem::create(
                                        ['line_item_id' => (string) $LineItem['id'], 'order_id' => $new_order->id, 'name' => $LineItem['name'], 'product_id' => $LineItem['product_id'], 'variant_id' => $LineItem['variant_id'], 'quantity' => 1, 'status' => 0]
                                    );
                                }
                            }
                        }
                        // if($new_order->fulfillment_status == 'fulfilled'){
                        //     $new_order->delete();
                        // }

                        $updated_item = LineItem::first();

                        $today = Carbon::today()->toDateString();
                        logger('today='.$today.', due_date='.$new_order->due_date);
                        if($its_new_order && ($new_order->due_date == $today)){
                            logger('ItemAdded event broadcast');
                            broadcast(new ItemAdded($updated_item))->toOthers();
                        }else{
                            broadcast(new ItemCompleted($updated_item))->toOthers();
                        }

                        $customer = Customer::updateOrCreate(
                            ['shopify_customer_id' => (string) $data['customer']['id']],
                            ['shopify_customer_id' => (string) $data['customer']['id'], 'order_id' => $new_order->id, 'name' => $data['customer']['first_name'].' '.$data['customer']['last_name'] ]
                        );
                    }
                }
            }
        }
    }

    public function orderCancel(Request $request)
    {
        logger('order cancel webhook working');

        $data = $request->all();
        $data_string = request()->getContent();
        $hmac_header = request()->header('x-shopify-hmac-sha256') ?: '';
        $shop_domain = request()->header('x-shopify-shop-domain');

        logger(json_encode($data));
        if(!empty($data)){
            $verified = $this->verifyWebhook($data_string,$hmac_header);
            if($verified){
                logger('cancel webhook verified');
                $order = Order::where('shopify_order_id', (string) $data['id'])->first();
                if($order){
                    logger('order found on cancel webhook');
                    $line_items = LineItem::where('order_id', $order->id)->get();
                    foreach($line_items as $line_item){
                        $line_item->delete();
                    }
                    $order->delete();

                    $customer = Customer::where('order_id', $order->id)->first();
                    if($customer){
                        $customer->delete();
                    }
                    logger($data['id'].' order deleted on order cancel webhook');
                }
            }
        }
    }

    public function productCreate(Request $request)
    {
        $data = $request->all();
        $data_string = request()->getContent();
        $hmac_header = request()->header('x-shopify-hmac-sha256') ?: '';
        $shop_domain = request()->header('x-shopify-shop-domain');
        logger('product webhook triggered');
        logger(json_encode($data));
        if(!empty($data)){
            $verified = $this->verifyWebhook($data_string,$hmac_header);
            if($verified){
                Product::firstOrCreate(
                    ['product_id' => (string) $data['id']],
                    ['title' => $data['title'], 'handle' => $data['handle'], 'vendor' => $data['vendor'], 'product_type' => $data['product_type'], 'tags' => $data['tags']]
                );
                $updated_item = LineItem::first();
                broadcast(new ItemCompleted($updated_item))->toOthers();
            }
        }
    }

    public function productUpdate(Request $request)
    {
        $data = $request->all();
        $data_string = request()->getContent();
        $hmac_header = request()->header('x-shopify-hmac-sha256') ?: '';
        $shop_domain = request()->header('x-shopify-shop-domain');
        logger('product update triggered');
        logger(json_encode($data));
        if(!empty($data)){
            $verified = $this->verifyWebhook($data_string,$hmac_header);
            if($verified){
                Product::where('product_id', (string) $data['id'])->update(['title' => $data['title'], 'handle' => $data['handle'], 'vendor' => $data['vendor'], 'product_type' => $data['product_type'], 'tags' => $data['tags']]);
                $updated_item = LineItem::first();
                broadcast(new ItemCompleted($updated_item))->toOthers();
            }
        }
    }

    private function verifyWebhook($data, $hmac_header)
    {
        $calculated_hmac = base64_encode(hash_hmac('sha256', $data, env('SHOPIFY_SHARED_SECRET'), true));
        return hash_equals($hmac_header, $calculated_hmac);
    }
}
