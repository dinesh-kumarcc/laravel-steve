<template>
    <div v-if="!show">
        <div v-if="message" class="alert new-order-message" role="alert">
            <button type="button" class="close" @click="closeNotification()">&times;</button>
             {{message}}
        </div>
        <div class="items-panel clearfix table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr><th>Items</th><th>Need</th><th>Completed</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(item,index) in items">
                        <td>
                            <div><span class="item-name">{{item.name}}</span></div>
                        </td>
                        <td>{{item.need}}</td>
                        <td>
                            <div class="number">
                                <span class="minus" id="decrease-item" @click="decreaseItemCounter(index,item)">-</span>
                                <span class="text" id="current-count">{{item.counter}}</span>
                                <span class="plus" id="increase-item" @click="increaseItemCounter(index,item)">+</span>
                            </div> 
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="items-footer">
                <button class="return-to orchard-btn" @click="routeToHome">Return to orders</button>
                <button class="mark-complete orchard-btn" @click="markComplete">Mark as completed</button>
            </div>
        </div>
    </div>
</template>

<script>


export default {
    name: 'OrderItems',
    props: ['message','show'],
    data: function() {
        return  {
            order: {},
            items: []
        }
    },
    created: function ()  { 
        this.orderId = this.$route.params.id;
    },
    mounted: function() {
        console.log('items Mounted', this.orderId);
        this.fetchOrderItems();
    },
    methods: {
        fetchOrderItems: function () {
            axios.post('/items', { order_id : this.orderId }).then(response => {
                this.order = response.data.order;
                this.items = response.data.items;
            });
            console.log('items', this.items);
        },
        routeToHome: function () {
            this.$router.push({
                name: 'home'
            });
        },
        markComplete: function () {
            let self = this;
            this.$confirm(
                {
                  message: `Are you sure?`,
                  button: {
                    no: 'No',
                    yes: 'Yes'
                  },
                  /**
                  * Callback Function
                  * @param {Boolean} confirm 
                  */
                  callback: confirm => {
                    if (confirm) {
                        let order = self.order;
                        axios.post('/order/update', order).then(response => {
                            console.log(response);
                        }).catch(error => {
                            console.log(error);
                        });
                    }
                  }
                }
            )
        },
        increaseItemCounter: function (index,updatedItem) { 
            console.log('item', updatedItem);
            if(updatedItem.counter < updatedItem.total){
                let self = this;
                this.$confirm(
                    {
                      message: `Are you sure?`,
                      button: {
                        no: 'No',
                        yes: 'Yes'
                      },
                      /**
                      * Callback Function
                      * @param {Boolean} confirm 
                      */
                      callback: confirm => {
                        if (confirm) {
                            self.items.find(item => item.id === updatedItem.id).counter = updatedItem.counter+1;
                            self.items.find(item => item.id === updatedItem.id).need = updatedItem.need-1;
                            updatedItem.action = 'plus';
                            axios.post('/item/status', updatedItem).then(response => {
                                // console.log(response.data);
                            }).catch(error => {
                                console.log(error);
                            });
                        }
                      }
                    }
                )
            }
        },
        decreaseItemCounter: function (index,updatedItem) { 
            console.log('item', updatedItem);
            if(updatedItem.counter > 0){ 
                let self = this;
                this.$confirm(
                    {
                      message: `Are you sure?`,
                      button: {
                        no: 'No',
                        yes: 'Yes'
                      },
                      /**
                      * Callback Function
                      * @param {Boolean} confirm 
                      */
                      callback: confirm => {
                        if (confirm) {
                            self.items.find(item => item.id === updatedItem.id).counter = updatedItem.counter-1;
                            self.items.find(item => item.id === updatedItem.id).need = updatedItem.need+1;
                            updatedItem.action = 'minus';
                            axios.post('/item/status', updatedItem).then(response => {
                                // console.log(response.data);
                            }).catch(error => {
                                console.log(error);
                            });
                        }
                      }
                    }
                )
            }
        },
        closeNotification: function () {
            this.$emit('disablenotification');
        },
    }
}
</script>