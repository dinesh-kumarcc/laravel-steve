@extends('layouts.app')

@section('content')
<div class="container orders-app">
    <vue-confirm-dialog class="counter-confirm-popup"></vue-confirm-dialog>
    <div class="order-app-loader"></div>
    <div class="row header-panel">
        <div class="logo-section">
            <a href="{{ url('/') }}">
                <img src="{{ asset('images/logo.png') }}" alt="Orchardfruit Logo" />
            </a>
        </div>

        <order-header :types="products" :methods="methods" :producttype="product_type" :checkoutmethod="checkout_method" @tabchange="changeCurrent" @typechange="changeProductType" @methodchange="changeCheckoutMethod"></order-header>  
    </div>
    <div class="row">
        <div class="col-md-12">
            <router-view :orders="orders"></router-view>
        </div>
    </div>
</div>
@endsection