<script setup lang="ts">
import { onBeforeMount, onMounted, shallowRef, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { type Product } from '@/lib/products';
import { Store } from 'lucide-vue-next';
import { CheckCircle, XCircle } from "lucide-vue-next";
import { convertCurrency } from '@/lib/utils'
import { Plan, PlanPrice, PlanPaymentProviderInformation } from '@/lib/plans';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from "@/components/ui/card";

import { defaultFormatter } from '@/lib/utils';

import { subscriptions } from '@/routes';

import { useCart } from '@/stores/cart';
import { storeToRefs } from 'pinia';
const useShoppingCart = useCart()
const { cart } = storeToRefs(useShoppingCart)

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Store',
        href: '/store',
    },
];

const { plan , plan_price } = defineProps<{
    message?: string;
    plan: Plan;
    plan_price: PlanPrice;
}>();

onBeforeMount(() => {
    useShoppingCart.clear()
})

const convertedCurrency = ref(defaultFormatter(plan_price.currency, plan_price.formatted_amount));

onMounted(async () => {
    convertedCurrency.value = plan_price.currency === 'KES' ? await convertCurrency(Number(plan_price.formatted_amount), 'KES', 'USD').then((res) => defaultFormatter(plan_price.currency, Number(res.formatted)) )
        : defaultFormatter(plan_price.currency, plan_price.formatted_amount)
})

</script>


<template>
    <Head title="Checkout Success" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div class="relative min-h-[100vh] flex flex-col gap-6 items-center flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
            <div id="inner" class="text-center py-4">
                <!-- <h1 v-if="$page.props.flash.payment_status"  class="font-semibold lg:text-2xl text-xl ">{{ $page.props.flash.payment_status }}</h1> -->

                <h1 class="font-semibold lg:text-2xl text-xl">{{ message ?? 'Thank you for your purchase!'}}</h1>

                <p class="py-4">Your order is being processed and you will receive an email with your order details shortly.</p>
            </div>

            <section>
                <section class="rounded-lg  border dark:border-neutral-700 p-4">
                    <section class="flex items-center gap-2 mb-4">
                        <div class="flex aspect-square size-10 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                            <AppLogoIcon class="size-6 fill-current text-white dark:text-black" />
                        </div>

                        <h3 class="font-bold text-xl">{{ plan.name }}</h3>
                    </section>

                    <section class="flex justify-between mb-4">
                        <h4> {{ convertedCurrency }} / {{ plan_price.interval.toLocaleLowerCase() }}</h4>
                    </section>

                    <section class="mb-4 flex flex-col gap-4">
                        <h3 class="font-bold text-xl">Features</h3>
                        <CardContent class="space-y-4">
                            <div v-for="(feature, i) in plan.features" :key="i" class="flex">
                                <CheckCircle v-if="feature" class="h-5 w-5 text-theme-primary flex-shrink-0" />
                                <XCircle v-else class="h-5 w-5 text-neutral-400 flex-shrink-0" />
                                <span :class="{ 'text-neutral-600 dark:text-neutral-400': feature, 'text-neutral-600 dark:text-neutral-400 opacity-50': !feature }" class="ml-3 text-sm">
                                    {{ feature }}
                                </span>
                            </div>
                        </CardContent>
                    </section>

                    <section class="flex justify-between mb-4 mt-8 ">
                        <h4 class="font-bold text-3xl">Total: </h4>

                        <h4 class="font-bold text-3xl"> {{ convertedCurrency }} </h4>
                    </section>
                </section>
            </section>

            <div class="mt-2">
                <Link :href="subscriptions()" prefetch="hover" :cacheFor="['20m']"  class="gap-3 dark:text-black text-white hover:bg-neutral-950/80 bg-neutral-950 dark:bg-[#FFFFFF] dark:hover:bg-white/80  font-medium py-2 px-4 rounded-md flex items-center justify-center transition-colors duration-200">
                    <component :is="Store" /> Manage Subscriptions
                </Link>
            </div>
        </div>
    </div>
</template>