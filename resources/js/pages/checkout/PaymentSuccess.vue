<script setup lang="ts">
import { onBeforeMount, onMounted, shallowRef, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { type Product } from '@/lib/products';
import { Store } from 'lucide-vue-next';

import { store as storeRoute } from '@/routes';

import { useCart } from '@/stores/cart';
import { storeToRefs } from 'pinia';
const useShoppingCart = useCart()
const { cart } = storeToRefs(useShoppingCart)

import { type Order } from '@/lib/orders';
import { defaultFormatter } from '@/lib/utils';

function formatPrice(currency: string, amount: number | bigint | Intl.StringNumericLiteral) 
{
    return defaultFormatter(currency, amount);
}


const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Store',
        href: storeRoute.url(),
    },
];

defineProps<{
    message?: string;
    order: Order;
}>();

onBeforeMount(() => {
    useShoppingCart.clear()
})

</script>


<template>
    <Head title="Checkout Success" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div class="relative min-h-[100vh] flex  flex-col items-center flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
            <div id="inner" class="text-center py-4">
                <!-- <h1 v-if="$page.props.flash.payment_status"  class="font-semibold lg:text-2xl text-xl ">{{ $page.props.flash.payment_status }}</h1> -->

                <h1 class="font-semibold lg:text-2xl text-xl">{{ message ?? 'Thank you for your purchase!'}}</h1>

                <p class="py-4">Your order is being processed and you will receive an email with your order details shortly.</p>
            </div>

            <div>
                <Link :href="storeRoute()" prefetch="hover" :cacheFor="['20m']"  class="gap-3 dark:text-black text-white hover:bg-neutral-950/80 bg-neutral-950 dark:bg-[#FFFFFF] dark:hover:bg-white/80  font-medium py-2 px-4 rounded-md flex items-center justify-center transition-colors duration-200">
                    <component :is="Store" /> Go to Store
                </Link>
            </div>

            <div class="mt-4">
                <h3 class="font-semibold text-xl dark:text-white">{{ order.order_number }}</h3>

                <h3 class="font-semibold text-xl dark:text-white">{{ order.status }}</h3>
                <h3 class="font-semibold text-xl dark:text-white">{{ order.payment_status }}</h3>


                <img v-if="order.payment_provider === 'stripe'"  width="96" src="https://demo.saasykit.com/images/payment-providers/stripe.png">
                <img v-if="order.payment_provider === 'paypal'" width="96" src="https://1000logos.net/wp-content/uploads/2017/05/Paypal-Logo-2022-500x281.png">
                <svg v-if="order.payment_provider === 'polar'" width="48" height="48" viewBox="0 0 29 29" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-black dark:text-white">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9.07727 23.0572C13.8782 26.307 20.4046 25.0496 23.6545 20.2487C26.9043 15.4478 25.6469 8.92133 20.846 5.67149C16.0451 2.42165 9.51862 3.67905 6.26878 8.47998C3.01894 13.2809 4.27634 19.8073 9.07727 23.0572ZM10.4703 23.1428C14.862 25.3897 20.433 23.2807 22.9135 18.4322C25.394 13.5838 23.8447 7.83194 19.4531 5.58511C15.0614 3.33829 9.49042 5.4473 7.00991 10.2957C4.52939 15.1442 6.07867 20.896 10.4703 23.1428Z" fill="currentColor"></path>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M11.7222 24.2898C15.6865 25.58 20.35 22.1715 22.1385 16.6765C23.927 11.1815 22.1632 5.68099 18.1989 4.39071C14.2346 3.10043 9.5711 6.509 7.78261 12.004C5.99412 17.4989 7.75793 22.9995 11.7222 24.2898ZM12.9347 23.872C16.2897 24.5876 19.9174 20.9108 21.0374 15.6596C22.1574 10.4084 20.3457 5.57134 16.9907 4.85575C13.6357 4.14016 10.008 7.817 8.88797 13.0682C7.76793 18.3194 9.57971 23.1564 12.9347 23.872Z" fill="currentColor"></path>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M13.8537 24.7382C16.5062 25.0215 19.1534 20.5972 19.7664 14.8563C20.3794 9.1155 18.7261 4.23202 16.0736 3.94879C13.4211 3.66556 10.7739 8.08983 10.1609 13.8307C9.54788 19.5715 11.2012 24.455 13.8537 24.7382ZM15.0953 22.9906C17.015 22.9603 18.5101 19.0742 18.4349 14.3108C18.3596 9.54747 16.7424 5.71058 14.8228 5.7409C12.9032 5.77123 11.408 9.6573 11.4833 14.4207C11.5585 19.184 13.1757 23.0209 15.0953 22.9906Z" fill="currentColor"></path>
                </svg>

                <pre>
                    <!-- {{ order }} -->
                </pre>
            </div>

            <div class="mt-4"  v-if="order.billing_order_items">

                <div class="px-2 py-2 flex flex-col gap-4 mt-4" v-for="(order_item, index) in order.billing_order_items" :index="index">
                    <div class=" grid grid-cols-2 gap-4">
                        <div class="
                            ms-card-content 
                            max-w-full
                            w-full
                            2xl:w-[200px]
                            bg-white/20 
                            outline -outline-offset-1
                            outline-white/25 
                            [--card-padding:0.2rem] 
                            [--card-radius:1rem] 
                            p-[--card-padding] 
                            rounded-[--card-radius]
                        ">
                            <img v-if="order_item.thumbnail"
                                loading="lazy" 
                                :src="order_item.thumbnail" 
                                class="
                                    [--card-padding:0.2rem] 
                                    [--card-radius:1rem] 
                                    aspect-5/6 
                                    rounded-[calc(var(--card-radius)-var(--card-padding))]
                                    bg-gray-800 
                                    object-cover w-full h-full 
                                    shadow-2xl
                                    outline 
                                    outline-white/10 
                                    -outline-offset-1
                                "
                            >
                        </div>

                        <div class="mt-2 flex flex-col gap-2">
                            <h2 class="font-semibold text-xl dark:text-white">{{ order_item.name }}</h2>

                            <h3 class="font-semibold text-xl dark:text-white">{{ order_item.status }}</h3>
                            <h3 class="font-semibold text-xl dark:text-white">{{ order_item.payment_status }}</h3>
                            <h3 class="font-semibold text-xl dark:text-white">{{ order_item.delivery_status }}</h3>
                            <h3 class="font-semibold text-xl dark:text-white">{{ order_item.fulfillment_status }}</h3>

                            <h2 class="font-bold text-xl dark:text-white">Price: {{ formatPrice(order_item.currency, Number(order_item.subtotal)) }}</h2>
                            <h2 class="font-bold text-xl dark:text-white">Quantity: {{ order_item.quantity }}</h2>
                            <h2 class="font-bold text-xl dark:text-white">Tax: {{ formatPrice(order_item.currency, Number(order_item.tax)) }}</h2>
                            <h2 class="font-bold text-xl dark:text-white">Discount: {{ formatPrice(order_item.currency, Number(order_item.discount)) }}</h2>
                            <h2 class="font-bold text-xl dark:text-white">Shipping: {{ formatPrice(order_item.currency, Number(order_item.shipping)) }}</h2>

                            <h2 class="font-bold text-xl dark:text-white">Total: {{ formatPrice(order_item.currency, Number(order_item.total)) }}</h2>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>

</template>
<style scoped>
.ms-card-content { border-radius: 12px;}
.ms-card-content img {
     inline-size: 100%; /* fill the entire width of its parent */

    /* make image stay square and is scaled and cropped to stay perfectly within its boundaries */
    aspect-ratio: 1; 
    transition: scale 0.5s ease-in-out;
}

.ms-card-content:hover img {
    scale: 0.95;
    filter: grayscale(100%); /* Applies a grayscale filter */
}

</style>