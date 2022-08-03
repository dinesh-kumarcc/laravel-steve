/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

window.Vue = require('vue');
import VueRouter from 'vue-router';
import moment from 'moment';
import 'moment-timezone';
import VueConfirmDialog from "vue-confirm-dialog";
import Orders from './components/Orders.vue';
import OrderItems from './components/OrderItems.vue';
import ItemsMethods from './components/ItemsMethods.vue';

Vue.use(VueConfirmDialog);
Vue.use(VueRouter);

/**
 * The following block of code may be used to automatically register your
 * Vue components. It will recursively scan this directory for the Vue
 * components and automatically register them with their "basename".
 *
 * Eg. ./components/ExampleComponent.vue -> <example-component></example-component>
 */

// const files = require.context('./', true, /\.vue$/i)
// files.keys().map(key => Vue.component(key.split('/').pop().split('.')[0], files(key).default))

// Vue.component('example-component', require('./components/ExampleComponent.vue').default);
Vue.component('order-header', require('./components/OrderHeader.vue').default);
Vue.component('orders', require('./components/Orders.vue').default);
Vue.component('order-items', require('./components/OrderItems.vue').default);
Vue.component('items-methods', require('./components/ItemsMethods.vue').default);

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

var router = new VueRouter({
  routes: [
    {
      path: '/',
      name: 'home',
      component: Orders
    },
    {
      path: '/order/:id',
      name: 'order',
      component: OrderItems
    },
    {
      path: '/items/method',
      name: 'itemsMethod',
      component: ItemsMethods
    }
  ],
  mode: 'history'
});

const app = new Vue({
    el: '#app',
    router: router,
    components: {
        moment
    },
    data: {
        orders: [],
        items: [],
        products: [],
        product_type: 'all',
        methods: [],
        checkout_method: 'all',
        current: moment(new Date()).tz("America/New_York").format('YYYY-MM-DD'),
        tomorrow: null,
        message: null,
        sound: 'https://orchardfruitnyc.com/audio/new-order.mp3',
        stopAudio: true,
    },
    created: function () { 
        moment.tz.setDefault("America/New_York");
        
        // axios.defaults.baseURL = '/demo/orchardweb-staging/public/';
        this.fetchOrderItems();
        let audio = new Audio(this.sound);
        var _self = this;
        audio.addEventListener('ended', function() { 
            if(!_self.stopAudio){
                this.currentTime = 0;
                this.play();
            }else{ 
                this.pause();
            }
        }, false);
        
        Echo.private('items-update')
        .listen('ItemCompleted', (e) => { console.log('items complete event');
            this.fetchOrderItems();
        });

        Echo.private('items-added')
        .listen('ItemAdded', (e) => { console.log('items added event');
            // Vue.swal('New order arrived !');
            this.message = 'New order arrived and added to the list below!';
            this.stopAudio = false;
            audio.play();
            this.fetchOrderItems();
        });
    },
    mounted: function () {
        console.log('app mounted');
    },
    methods: {
        fetchOrderItems: function () {
            // document.getElementsByClassName('order-app-loader')[0].style.display="block";
            axios.post('/orders', { type: this.product_type, method: this.checkout_method, day: this.current }).then(response => {
                this.orders = response.data.orders;
                this.products = response.data.product_types;
                this.methods = response.data.methods;
                // document.getElementsByClassName('order-app-loader')[0].style.display="none";
            });
        },
        changeCurrent: function (selected) { 
            this.current = selected;
            this.fetchOrderItems();
        },
        changeProductType: function (type) { 
            this.product_type = type;
            this.fetchOrderItems();
        },
        changeCheckoutMethod: function (method) {
            this.checkout_method = method;
            this.fetchOrderItems();
        },
        stopOrderAlert: function () { 
            this.message = null;
            this.stopAudio = true;
        }
    }
});