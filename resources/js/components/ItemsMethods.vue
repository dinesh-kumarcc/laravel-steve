<template>
	<div class="item-methods">
		<div class="items-panel clearfix table-responsive">
	        <table class="table table-bordered">
	            <thead>
	                <tr><th></th><th>Delivery</th><th>Pickup</th><th>Shipping</th></tr>
	            </thead>
	            <tbody>
	                <tr v-for="(item,index) in items">
	                    <td>
	                        <div>{{item.name}}</div>
	                    </td>
	                    <td>{{item.delivery}}</td>
	                    <td>{{item.pickup}}</td>
	                    <td>{{item.shipping}}</td>
	                </tr>
	            </tbody>
	        </table>
	    </div>
	</div>
</template>

<script type="text/javascript">
export default {
	name: 'ItemsMethod',
	data: function() {
        return {
            items: [],
        }
    },
	mounted: function(){
		axios.get('/items/methods').then(response => {
            this.items = response.data;
        });
	}

}
</script>