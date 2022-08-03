<template>
        <div class="orders-panel items-panel clearfix table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr><th>Orders</th><th>Source</th><th>Items</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(order,index) in orders">
                        <td>
                            <div>
                                <span class="order-name">{{order.shopify_order_name}}</span> - <span class="customer-name" v-if="order.customer">{{order.customer.name}}</span> - <span class="delivery-method">{{order.checkout_method}}</span>
                                <span class="due-time" v-if="order.checkout_method == 'pickup'"> - {{order.due_time}}</span>
                            </div>
                        </td>
                        <td>{{order.source}}</td>
                        <td>{{order.line_items_count}}</td>
                        <td>
                            <button :class="[ 'orchard-btn', 'phase-'+order.phase ]" v-if="order.phase == 1" @click="routeToOrderDetail(order.id)">In progress</button>
                            <button :class="[ 'orchard-btn', 'phase-'+order.phase ]" v-if="order.phase == 2" @click="routeToOrderDetail(order.id)">Completed</button>
                            <button :class="[ 'orchard-btn', 'phase-'+order.phase ]" v-else @click="routeToOrderDetail(order.id)">Prepare</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
</template>

<script>

export default {
    name: 'Orders',
    props: ['orders'],
    mounted: function() {
        console.log('Order Mounted', this.orders);
    },
    methods: {
        routeToOrderDetail: function(id){
            this.$router.push({
                name: 'order',
                params: {
                    id
                }
            });
        }
    }
}
</script>