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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Store',
        href: storeRoute.url(),
    },
];

defineProps<{
    message?: string;
}>();

onBeforeMount(() => {
    useShoppingCart.clear()
})

</script>


<template>
    <Head title="Checkout Success" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div class="relative min-h-[100vh] flex flex-col items-center flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
            <div id="inner" class="text-center py-4">
                <!-- <h1 v-if="$page.props.flash.payment_status"  class="font-semibold lg:text-2xl text-xl ">{{ $page.props.flash.payment_status }}</h1> -->

                <h1 class="font-semibold lg:text-2xl text-xl">{{ message ?? 'Sorry an error occurred with your payment!'}}</h1>

                <p class="py-4">Your order failed to be processed, please try again later.</p>
            </div>

            <div>
                <Link :href="storeRoute()" prefetch="hover" :cacheFor="['20m']"  class="gap-3 dark:text-black text-white hover:bg-neutral-950/80 bg-neutral-950 dark:bg-[#FFFFFF] dark:hover:bg-white/80  font-medium py-2 px-4 rounded-md flex items-center justify-center transition-colors duration-200">
                    <component :is="Store" /> Go to Store
                </Link>
            </div>
        </div>
    </div>
</template>