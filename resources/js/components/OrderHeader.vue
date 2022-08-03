<template>
    <div class="right-header">
        <div class="current-selected">
            Orders for: <span class="current-date">{{ formattedDate }}</span>
        </div>
        <div class="days_select" v-bind:class="[ disableFilters ? 'disable-options' : '' ]"> 
            <div class="both_day_select">  
                <div class="left_section record-day" v-bind:class="[ isActive == 'today' ? 'active' : '']" v-on:click="todayDate()">Today</div>
                <div class="right_section record-day tomorrow-pick" v-bind:class="[ isActive == 'tomorrow' ? 'active' : '']" v-on:click="tomorrowDate()">Choose date</div>
                <Datepicker v-model="date" ref="startDatePicker" format="yyyy-MM-dd">
                </Datepicker>
            </div>
        </div>
        <div class="right_selector page-filters">
            <div v-bind:class="[ disableFilters ? 'disable-options' : '' ]" class="selectdiv checkout-method-wrapper">
                <label>
                    <select name="method_selector" @input="onCheckoutMethodChange($event)" v-model="checkoutMethod" class="checkout-method-selector">
                        <option :value="method" v-for="method in methods">{{ method }}</option>
                    </select>
                </label>
            </div>
            <div v-bind:class="[ disableFilters ? 'disable-options' : '' ]" class="selectdiv product-type-wrapper">
                <label>
                    <select name="product_type_selector" @change="onProductTypeChange($event)" v-model="productType"  class="product-type-selector">
                        <option :value="type" v-for="type in types">{{ type }}</option>
                    </select>
                </label>
            </div> 

            <div class="btn_list_detail">
                <!-- <router-link class="btn_details" class-active="active" to="/" exact>Home</router-link>
                <router-link class="btn_details method-page" class-active="active" to="/items/method" exact>List Details</router-link> -->
            </div>
        </div>
        <div class="current-order">
            <strong><span class="current-date">{{ formattedDate }}</span></strong>
        </div>
    </div>
    
</template>

<script>
import Datepicker from 'vuejs-datepicker';
import moment from 'moment';

export default {
    name: 'OrderHeader',
    components: {
        Datepicker
    },
    props: ['types','producttype','methods','checkoutmethod'],
    data: function() {
        return {
            isActive: 'today',
            date: moment().tz("America/New_York").add(1, 'days').format(),
            formattedDate: moment(new Date()).tz("America/New_York").format("dddd, MMMM Do YYYY"),
            disableFilters: false,
            checkoutMethod: this.checkoutmethod,
            productType: this.producttype,
        }
    },
    mounted: function() {
        var _self = this;
        var container = document.getElementsByClassName('vdp-datepicker')[0];
        document.addEventListener('click', function( event ) {
            if (container !== event.target && !container.contains(event.target) && document.getElementsByClassName('tomorrow-pick')[0] !== event.target) {
                _self.$refs.startDatePicker.close();
            }
        });
    },
    created: function() {
        moment.tz.setDefault("America/New_York");
    },
    watch: {
        date: function(val) {
            console.log(this.date);
            this.formattedDate = moment(this.date).format("dddd, MMMM Do YYYY");
            this.$emit('tabchange', moment(this.date).format('YYYY-MM-DD'));
        },
        $route() { 
            if (this.$route.path === '/') {
                this.disableFilters = false;
            }else {
                this.todayDate();
                this.disableFilters = true;
            }
            return this.disableFilters;
        }
    },
    methods: {
        todayDate: function () { 
            this.$refs.startDatePicker.close();
            this.isActive = 'today';
            this.date = moment(new Date()).format();
            this.$emit('tabchange', moment(new Date()).format('YYYY-MM-DD'));
        },
        tomorrowDate: function () { 
            this.$refs.startDatePicker.showCalendar();
            this.isActive = 'tomorrow';
            this.date = moment().add(1, 'days').format();
        },
        onProductTypeChange: function (event) {
            this.$emit('typechange', event.target.value);
        },
        onCheckoutMethodChange: function (event) {
            this.$emit('methodchange', event.target.value);
        },
        routeTo: function(path){
            this.todayDate();
            this.$router.push({ name: path });
        }
    }
}
</script>