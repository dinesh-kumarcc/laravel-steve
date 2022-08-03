<?php

namespace App\Http\Controllers;

use App\Order;
use App\LineItem;
use App\Customer;
use App\Product;
use App\Events\ItemCompleted;
use App\Events\ItemAdded;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Osiset\BasicShopifyAPI\BasicShopifyAPI;
use Osiset\BasicShopifyAPI\Options;
use Osiset\BasicShopifyAPI\Session;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use DB;
use Illuminate\Support\Str;
// use Illuminate\Support\Facades\Session;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $api = $this->apiConnect();
        $webhook = array(
            'webhook' => array(
                "topic"     => 'orders/create',
                "address"   => 'https://orchardfruitnyc.com/webhook/order-create',
                "format"    => "json"
            )
        );
        $webhooks = $api->rest('GET', '/admin/api/'.env('SHOPIFY_API_VERSION').'/webhooks.json', [])['body'];
        // echo "<pre>";
        // print_r($webhooks);
        // echo "</pre>";

        return view('order');
    }

    public function fetchOrders(Request $request)
    {
        $types = Product::whereNotNull('product_type')->where('product_type', '!=', '')->groupBy('product_type')->pluck('product_type');
        $types[] = 'all';

        $methods = Order::whereNotNull('checkout_method')->where('checkout_method', '!=', '')->groupBy('checkout_method')->pluck('checkout_method');
        $methods[] = 'all';

        $current = $request->input('day');

        $orders = Order::with(['line_items','customer'])->withCount('line_items')
        ->where(function($query) use ($request){
            if($request->input('method') != 'all'){
                $query->where('orders.checkout_method', $request->input('method'));
            }
        })
        ->where('orders.due_date', $current)
        ->get();

        // $orders = Order::leftJoin('line_items', 'orders.id', '=', 'line_items.order_id')
        // ->leftJoin('products', 'line_items.product_id', '=', 'products.product_id')
        // ->where(function($query) use ($request){
        //     if($request->input('type') != 'all'){
        //         $query->where('products.product_type', $request->input('type'));
        //     }
        // })
        // ->where(function($query) use ($request){
        //     if($request->input('method') != 'all'){
        //         $query->where('orders.checkout_method', $request->input('method'));
        //     }
        // })
        // ->where('orders.due_date', $current)
        // ->select('line_items.*','orders.shopify_order_name','orders.checkout_method','orders.due_date','orders.due_time','orders.gift_message','products.product_type')
        // ->get();

        return response()->json([ 'product_types' => $types, 'orders' => $orders,'methods' => $methods ]);
    }

    public function fetchItems(Request $request)
    {
        $order = Order::find($request->input('order_id'));
        $items = LineItem::leftJoin('orders', 'line_items.order_id', '=', 'orders.id')
        ->where('line_items.order_id', $request->input('order_id'))
        ->select('line_items.*','orders.checkout_method','orders.due_date','orders.due_time','orders.gift_message')
        ->get();

        $filtered_items = array();
        foreach($items as $key => $item){
            $already_exist = 0;
            
            foreach($filtered_items as $k => $filtered_item){
                if($item->gift_message == '' || $item->gift_message == null){
                    if($item->checkout_method == $filtered_item['checkout_method'] && $item->due_date == $filtered_item['due_date'] && $item->variant_id == $filtered_item['variant_id']){
                        if(($item->checkout_method == 'pickup' && $item->due_time == $filtered_item['due_time']) || $item->checkout_method != 'pickup'){
                            $need_left = $filtered_item['need']+$item->quantity;
                            if($item->status){
                                $need_left = $need_left-1;
                            }
                            $already_exist = 1;
                            $filtered_items[$k]['total'] = $filtered_item['total']+$item->quantity;
                            $filtered_items[$k]['need'] = $need_left;
                            $filtered_items[$k]['counter'] = $filtered_item['total']-$filtered_item['need'];
                        }else{
                            continue;
                        }
                        break;
                    }
                }else{
                    if($item->checkout_method == $filtered_item['checkout_method'] && $item->due_date == $filtered_item['due_date'] && $item->variant_id == $filtered_item['variant_id'] && $item->order_id == $filtered_item['order_id']){
                        if(($item->checkout_method == 'pickup' && $item->due_time == $filtered_item['due_time']) || $item->checkout_method != 'pickup'){
                            $need_left = $filtered_item['need']+$item->quantity;
                            if($item->status){
                                $need_left = $need_left-1;
                            }
                            $already_exist = 1;
                            $filtered_items[$k]['total'] = $filtered_item['total']+$item->quantity;
                            $filtered_items[$k]['need'] = $need_left;
                            $filtered_items[$k]['counter'] = $filtered_item['total']-$filtered_item['need'];
                        }else{
                            continue;
                        }
                        break;
                    }
                }
                
            }
            
            
            if(!$already_exist){
                $item->total = $item->quantity;
                $need_left = $item->quantity;
                if($item->status){
                    $need_left = $need_left-1;
                }
                $item->need = $need_left;
                $item->counter = $item->total-$need_left;

                $filtered_items[] = $item;
            }
        }

        return response()->json([ 'order' => $order, 'items' => $filtered_items ]);
    }
	

    public function itemStatusUpdate(Request $request)
    {
        $user = Auth::user();
        // DB::enableQueryLog();
        $orders = Order::where('due_date', $request->input('due_date'))
                ->where('checkout_method', $request->input('checkout_method'))
                ->where(function($query) use ($request){
                    if($request->input('checkout_method') == 'pickup'){
                        $query->where('due_time', $request->input('due_time'));
                    }
                })
                ->where(function($query) use ($request){
                    if($request->input('gift_message') != '' && $request->input('gift_message') != null){
                        $query->where('gift_message', $request->input('gift_message'));
                    }
                })
                ->get();

        // logger(DB::getQueryLog());

        foreach($orders as $order){
            if($request->input('action') == 'plus'){
                $updated_item = LineItem::where('variant_id',$request->input('variant_id'))->where('order_id', $order->id)->where('status', 0)->first();
                if($updated_item){
                    $updated_item->status = 1;
                    $updated_item->save();
                    broadcast(new ItemCompleted($updated_item))->toOthers();
                    return ['status' => 'Status updated!', 'order' => $updated_item ];
                }
            }else{
                $updated_item = LineItem::where('variant_id',$request->input('variant_id'))->where('order_id', $order->id)->where('status', 1)->first();
                if($updated_item){
                    $updated_item->status = 0;
                    $updated_item->save();
                    broadcast(new ItemCompleted($updated_item))->toOthers();
                    return ['status' => 'Status updated!', 'order' => $updated_item ];
                }
            }
        }
        return ['status' => 'Status updated!' ];
    }

    public function orderUpdate(Request $request)
    {
        $user = Auth::user();
        $order = Order::find($request->input('id'));
        if($order){
            $order->phase = 2;
            $order->save();
        }
    }

    public function ordersWithMethods(Request $request){
        $current = Carbon::today()->toDateString();

        $items = LineItem::leftJoin('orders', 'line_items.order_id', '=', 'orders.id')
        ->where('orders.due_date', $current)
        ->select('line_items.*', 'orders.checkout_method', 'orders.due_date')
        ->get();

        $checkoutMethods = Order::whereNotNull('checkout_method')->where('checkout_method', '!=', '')->groupBy('checkout_method')->pluck('checkout_method');

        $filtered_items = [];
        
        foreach ($items as $key => $item) {
            $name_slug = Str::slug($item->name, '-');
            $oldRecord = ((isset($filtered_items[$name_slug][$item->checkout_method])) ? $filtered_items[$name_slug][$item->checkout_method] : 0 );

            $filtered_items[$name_slug]['name'] = $item->name;
            $filtered_items[$name_slug][$item->checkout_method] = $oldRecord + 1;
        }

        foreach ($filtered_items as $key => $item) {
            foreach ($checkoutMethods as $methodKey => $method) {
                if (!isset($item[$method])) {
                    $filtered_items[$key][$method] = 0;
                }
            }
        }

        return $filtered_items;
    }

    private function apiConnect(){
        $options = new Options();
        $options->setType(true); // Makes it private
        $options->setVersion(env('SHOPIFY_API_VERSION'));
        $options->setApiKey(env('SHOPIFY_API_KEY'));
        $options->setApiPassword(env('SHOPIFY_API_PASSWORD'));

        // Create the client and session
        $api = new BasicShopifyAPI($options);
        $api->setSession(new Session(env('SHOPIFY_DOMAIN')));
        return $api;
    }

    public function fetchProducts()
    {
        $products = $this->getProducts(null, []);

        foreach($products as $key => $product){
            $p = Product::firstOrCreate(
                ['product_id' => (string) $product['id']],
                ['title' => $product['title'], 'handle' => $product['handle'], 'vendor' => $product['vendor'], 'product_type' => $product['product_type'], 'tags' => $product['tags']]
            );
        }

        $db_products = Product::all();
    }

    protected function getProducts($next, $previuosData){
        $api = $this->apiConnect();
        $response = $api->rest('GET', '/admin/api/'.env('SHOPIFY_API_VERSION').'/products.json', ['limit' => 50, 'page_info' => $next ]);
        if($response['link'] && $response['link']['next']){
            $merge_prev = [
                new Collection($previuosData),
                new Collection($response['body']['products'])
            ];

            return $this->getProducts($response['link']['next'], Arr::collapse($merge_prev));
        }

        $all_products = [
                new Collection($previuosData),
                new Collection($response['body']['products'])
            ];
        return Arr::collapse($all_products);
    }

    protected function getOrders($next, $previuosData){
        $api = $this->apiConnect();
        $response = $api->rest('GET', '/admin/api/'.env('SHOPIFY_API_VERSION').'/orders.json', ['limit' => 50, 'page_info' => $next ]);

        if($response['link'] && $response['link']['next']){
            $merge_prev = [
                new Collection($previuosData),
                new Collection($response['body']['orders'])
            ];

            return $this->getOrders($response['link']['next'], Arr::collapse($merge_prev));
        }

        $all_orders = [
                new Collection($previuosData),
                new Collection($response['body']['orders'])
            ];
        return Arr::collapse($all_orders);
    }

    public function deleteOrdersFromWebapp()
    {
        /* Remove orders from webapp */

        // $shopify_response = $api->rest('GET', '/admin/api/'.env('SHOPIFY_API_VERSION').'/orders/4169399631917.json', [])['body']['order'];
        // echo '<pre>';
        // print_r($shopify_response);
        // echo '</pre>';

        // if($shopify_response['cancelled_at'] != null){
        //     $order = Order::where('shopify_order_id', $shopify_response['id'])->first();
        //     if($order){
        //         $line_items = LineItem::where('order_id', $order->id)->get();
        //         foreach($line_items as $line_item){
        //             $line_item->delete();
        //         }
        //         $order->delete();
        //     }
        // }
        // echo 'order removed';
        
        /* Remove orders from webapp */
    }

    public function sync()
    {
        // $order = Order::where('shopify_order_name', '#12063')->first();
        // if($order){
        //     $line_items = LineItem::where('order_id', $order->id)->get();
        //     foreach($line_items as $line_item){
        //         $line_item->delete();
        //     }
        //     $order->delete();
        // }

        // die();

        $api = $this->apiConnect();
        // $orders = Order::with('line_items')->get()->toArray();
        // $current = Carbon::today()->toDateString();

        // $items = LineItem::leftJoin('orders', 'line_items.order_id', '=', 'orders.id')
        // ->leftJoin('products', 'line_items.product_id', '=', 'products.product_id')
        // ->select('line_items.*','orders.shopify_order_name','orders.checkout_method','orders.due_date','orders.due_time','orders.gift_message','products.product_type')
        // ->get()->toArray();

        // echo '<pre>';
        // print_r($items);
        // echo '</pre>';
        // die();

        $response = $this->getOrders(null, []);
        // die();
        
        echo 'start adding in db';

        foreach($response as $key => $order){
            $checkout_method = null; $due_date = null;
            foreach($order['note_attributes'] as $key => $atttribute){
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
                // if($order['fulfillment_status'] != 'fulfilled' && $order['cancelled_at'] == null && $order['cancelled_at'] == ''){
                echo 'source=='.$order['source_name'];
                    $new_order = Order::updateOrCreate(
                        ['shopify_order_id' => (string) $order['id']],
                        ['shopify_order_name' => $order['name'], 'email' => $order['contact_email'], 'subtotal_price' => $order['subtotal_price'], 'total_price' => $order['total_price'], 'source' => $order['source_name'], 'checkout_method' => $checkout_method, 'due_date' => $due_date ]
                    );
                    $new_order->source = $order['source_name'];
                    if(isset($due_time)){
                        $new_order->due_time = $due_time;
                    }
                    $new_order->save();

                    foreach($order['line_items'] as $LineItem){
                        $line_item_exists = LineItem::where('line_item_id', $LineItem['id'])->count();
                        if($line_item_exists == 0){
                            for ($i=1; $i <= $LineItem['quantity']; $i++) { 
                                $line_item = LineItem::create(
                                    ['line_item_id' => (string) $LineItem['id'], 'order_id' => $new_order->id, 'name' => $LineItem['name'], 'product_id' => $LineItem['product_id'], 'variant_id' => $LineItem['variant_id'], 'quantity' => 1, 'status' => 0]
                                );
                            }
                        }
                        
                    }
                // }

                    $customer = Customer::updateOrCreate(
                            ['shopify_customer_id' => (string) $order['customer']['id']],
                            ['shopify_customer_id' => $order['customer']['id'], 'order_id' => $new_order->id, 'name' => $order['customer']['first_name'].' '.$order['customer']['last_name'] ]
                        );
            }
        }

        echo 'saved all orders';
        // die();
        // foreach($orders as $order){
        //     echo '<pre>';
        //     print_r($order);
        //     echo '</pre>';
        //     if($order['cancelled_at'] != null){
                
        //         $order = Order::where('shopify_order_id', $order['id'])->first();
        //         if($order){
        //             $line_items = LineItem::where('order_id', $order['id'])->get();
        //             foreach($line_items as $line_item){
        //                 $line_item->delete();
        //             }
        //             $order->delete();
        //         }
        //     }
        // }

        // LineItem::query()->truncate();
        // Order::query()->delete();
        
    }
}
