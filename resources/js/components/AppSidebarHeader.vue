<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';
import { ShoppingCart, RefreshCw, Trash  } from 'lucide-vue-next';

import { store as storeRoute } from '@/routes';
import { product as productRoute } from '@/routes/store';
import { checkout as checkoutRoute } from '@/routes/store/cart';
import { buy_now as buyNowRoute } from '@/routes/store/product';
import { update as updateCartRoute } from '@/routes/api/cart'

import { HttpError, HttpRequest, HttpResponse } from '@/lib/utils';


withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

import { useCart } from '@/stores/cart';
import { storeToRefs } from 'pinia';
const useShoppingCart = useCart()
const { cart } = storeToRefs(useShoppingCart)
onMounted(async () => {
    await useShoppingCart.sync();
})

const syncing = ref(false);
async function syncCart() {
    syncing.value = true;
    await useShoppingCart.sync()
    .then(res => {
        syncing.value = false;
    });
}

const deletingCart = ref(false);
async function deleteCart() {
    deletingCart.value = true;
    await useShoppingCart.deleteCart()
    .then(res => {
        deletingCart.value = false;
    });
}
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="grid grid-cols-3 gap-6 grid-flow-row">
            <div class="cart-container">
                <Link :href="checkoutRoute()">
                    <component :is="ShoppingCart" />
                    <span v-if="useShoppingCart.all().length > 0" class="cart-badge">{{ useShoppingCart.all().length }}</span>
                </Link>
            </div>

            <div class="cart-container">

                <button class="cursor-pointer h-7 w-7" :disabled="syncing" :class="{ 'opacity-25 cursor-not-allowed' : syncing }" @click="syncCart">
                    <component :is="RefreshCw"  />
                </button>
            </div>

            <div class="cart-container">
                <button class="cursor-pointer h-7 w-7" :disabled="deletingCart" :class="{ 'opacity-25 cursor-not-allowed' : deletingCart }" @click="deleteCart">
                    <component :is="Trash" />
                </button>
            </div>
        </div>
    </header>
</template>

<style scoped>
.cart-container {
  position: relative;
  display: inline-block;
  gap: 1.5rem;
}

.cart-icon {
  font-size: 24px;
}

.cart-badge {
  position: absolute;
  top: -10px;
  right: -20px;
  background-color: red;
  color: white;
  font-size: 12px;
  font-weight: bold;
  padding: 4px 10px;
  border-radius: 100%;
}
</style>