<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Order;
use App\LineItem;
use Osiset\BasicShopifyAPI\BasicShopifyAPI;
use Osiset\BasicShopifyAPI\Options;
use Osiset\BasicShopifyAPI\Session;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class CancelledOrderCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancelledorder:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cron to delete cancelled orders';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        logger('cancelled order cron');

        $orders = $this->getOrders(null, []);

        foreach($orders as $order){
            if($order['cancelled_at'] != null){
                $order = Order::where('shopify_order_id', $order['id'])->first();
                if($order){
                    $line_items = LineItem::where('order_id', $order['id'])->get();
                    foreach($line_items as $line_item){
                        $line_item->delete();
                    }
                    $order->delete();
                }
            }
        }
        logger('cancelled order cron worked fine');
    }

    protected function getOrders($next, $previuosData){
        $api = $this->apiConnect();
        $response = $api->rest('GET', '/admin/api/'.env('SHOPIFY_API_VERSION').'/orders.json', [ 'status' => 'cancelled', 'limit' => 250 ]);

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
        logger('cancelled orders array returned from shopify');
        return Arr::collapse($all_orders);
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
}
