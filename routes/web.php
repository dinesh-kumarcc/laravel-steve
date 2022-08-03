<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });

// Auth::routes();

// Route::get('/home', 'HomeController@index')->name('home');

Auth::routes(['register' => false]);

Route::get('/', 'OrderController@index');
Route::post('/orders', 'OrderController@fetchOrders');
Route::post('/items', 'OrderController@fetchItems');
Route::get('/items/methods', 'OrderController@ordersWithMethods');
Route::get('/sync', 'OrderController@sync');
Route::post('/order/update', 'OrderController@orderUpdate');
Route::get('/orders/remove', 'OrderController@deleteOrdersFromWebapp');

Route::group(['prefix' => 'webhook', 'as' => 'webhook.', 'namespace' => 'Shopify', 'middleware' => []], function (){
	Route::post('/order-create', 'ShopifyWebhookController@orderCreate')->name('order.create');
	Route::post('/order-update', 'ShopifyWebhookController@orderUpdate')->name('order.update');
	Route::post('/order-cancelled', 'ShopifyWebhookController@orderCancel')->name('order.cancel');
	Route::post('/product-create', 'ShopifyWebhookController@productCreate')->name('product.create');
	Route::post('/product-update', 'ShopifyWebhookController@productUpdate')->name('product.update');
});

Route::get('{any}', function () {
    return view('order');
})->where('any','.*');