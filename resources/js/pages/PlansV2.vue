<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { onUnmounted, onMounted, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from "@/components/ui/card";
import { CheckCircle, XCircle } from "lucide-vue-next";
import Button from '@/components/Button.vue'
import { convertCurrency } from '@/lib/utils'
import CustomSwitch from '@/components/CustomSwitch.vue';
import { Plan, PlanPrice } from '@/lib/plans';
import { defaultFormatter } from '@/lib/utils';

import { planv2 as planRouteV2 } from '@/routes/checkout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Plan',
        href: '/plansv2',
    },
];


const { plans } = defineProps<{
    plans: Plan[]
}>()

type Op = Intl.NumberFormatOptions;

const isAnnual = ref(false);
const showDefault = ref(false)

const formatPrice = (currency: string, price: number) => new Intl.NumberFormat("en", { style: "currency", currency }).format(price)

const convertedPrice = ref<string | null>(null);

const convertCurrencyAmount = async (amount: number) => await convertCurrency(amount, 'KES', 'USD').then(response => response.formatted)

const convertedPrices = ref<Record<number, { monthly?: string; yearly?: string }>>({});
const defaultPrices = ref<Record<number, { monthly?: string; yearly?: string }>>({});


onMounted(async () => {
    console.log('plans: ', plans)
    const result: Record<number, { monthly?: string; yearly?: string }> = {};

    for (const plan of plans) {
        const monthlyPrice = Number(plan.billing_prices.find((p: PlanPrice) => p.interval === 'MONTHLY')?.formatted_amount || '0');
        const yearlyPrice = Number(plan.billing_prices.find((p: PlanPrice) => p.interval === 'YEARLY')?.formatted_amount || '0');

        const convertedMonthly = monthlyPrice !== null ||  monthlyPrice !== undefined
        ? await convertCurrency(monthlyPrice, 'KES', 'USD').then((res) => res.formatted)
        : undefined;

        const convertedYearly = yearlyPrice !== null ||  yearlyPrice !== undefined
        ? await convertCurrency(yearlyPrice, 'KES', 'USD').then((res) => res.formatted)
        : undefined;

        defaultPrices.value[plan.id] = { monthly: defaultFormatter('KES', monthlyPrice), yearly: defaultFormatter('KES', yearlyPrice)  } 

        result[plan.id] = {
            monthly: convertedMonthly,
            yearly: convertedYearly,
        };
    }

    convertedPrices.value = result;
});

</script>

<template>
    <Head title="Plans" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
        <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
            <div class="text-center max-w-3xl mx-auto mb-12">
                <h1 class="text-3xl md:text-4xl font-display font-bold text-neutral-900 dark:text-neutral-100 mb-4 ">
                    Choose a plan that works for you
                </h1>
                <p class="text-lg text-neutral-600 dark:text-neutral-400">No hidden fees. Cancel anytime.</p>

                <div class="flex items-center justify-center mt-8">
                    <span :class="{'text-neutral-900 dark:text-neutral-100' : !isAnnual  }" class="text-neutral-600 dark:text-neutral-400 font-medium mr-2" >
                        Monthly
                    </span>
                    <CustomSwitch 
                        :condition="isAnnual"
                        @toggled="isAnnual = !isAnnual" 
                    />
                    
                    <span :class="{'text-neutral-900 dark:text-neutral-100' : isAnnual  }" class="text-neutral-600 dark:text-neutral-400 font-medium ml-2">
                        Annual <span class="text-theme-primary text-xs font-medium ml-1">Save 20%</span>
                    </span>
                </div>

                <div class="flex items-center justify-center mt-8">
                    <span :class="{'text-neutral-900 dark:text-neutral-100' : !showDefault  }" class="text-neutral-600 dark:text-neutral-400 font-medium mr-2" >
                        Toggle Currencies
                    </span>
                    <CustomSwitch 
                        :condition="showDefault"
                        @toggled="showDefault = !showDefault" 
                    />
                </div>
            </div>

            <!-- <template  v-for="(plan, index) in plans">
                <pre>{{ plan }}</pre>
            </template> -->

            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-8 max-w-7xl mx-auto items-stretch">
                <Card
                    v-for="(plan, index) in plans"
                    :key="index"
                    :class="{
                        'border-2 border-theme-primary shadow-lg md:scale-105 py-0 pb-6': plan.name === 'Silver',
                        'border border-neutral-200 dark:border-neutral-800 shadow-sm': plan.name !== 'Silver'
                    }"
                    class="bg-white dark:bg-neutral-900 rounded-xl overflow-hidden flex flex-col h-full"
                >
                    <div v-if="plan.name === 'Silver'" class="py-1 top-0 bg-theme-primary text-white text-center text-sm font-medium">
                        Most Popular
                    </div>

                    <CardHeader class="border-b border-neutral-200 dark:border-neutral-800">
                        <CardTitle class="font-display font-semibold text-xl text-neutral-900 dark:text-neutral-100">
                            {{ plan.name }}
                        </CardTitle>
                        <div class="mt-4 flex items-baseline">
                            <span class="text-3xl font-display font-extrabold text-neutral-900 dark:text-neutral-100">
                                {{  showDefault ? (isAnnual ? defaultPrices[plan.id]?.yearly ?? 'Loading...'
                                                : defaultPrices[plan.id]?.monthly ?? 'Loading...')  : (isAnnual ? convertedPrices[plan.id]?.yearly ?? 'Loading...'
                                                : convertedPrices[plan.id]?.monthly ?? 'Loading...') 
                                }} 
                            </span>
                            <span class="ml-1 text-neutral-500">/{{ isAnnual ? 'year' : 'month' }}</span>
                        </div>
                        
                        <CardDescription class="mt-4 text-neutral-600 dark:text-neutral-400">
                            {{ plan.description }}
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-4">
                        <div v-for="(feature, i) in plan.features" :key="i" class="flex">
                            <CheckCircle v-if="feature" class="h-5 w-5 text-theme-primary flex-shrink-0" />
                            <XCircle v-else class="h-5 w-5 text-neutral-400 flex-shrink-0" />
                            <span :class="{ 'text-neutral-600 dark:text-neutral-400': feature, 'text-neutral-600 dark:text-neutral-400 opacity-50': !feature }" class="ml-3 text-sm">
                                {{ feature }}
                            </span>
                        </div>
                    </CardContent>

                    <CardFooter class="bg-neutral-50 dark:bg-neutral-900 mt-auto">
                        <Link 
                            :href="planRouteV2({ name: plan.name }, { 
                                query: { 
                                    plan_id: plan.billing_plan_id, 
                                    interval: isAnnual ? 'yearly' : 'monthly',
                                    currency: 'USD' 
                                }
                            })"
                            class="w-full "
                        >
                            <Button
                                :as-child="true"
                                :class="{
                                    'bg-theme-primary hover:bg-theme-primary/80 text-white': plan.name === 'Silver',
                                    'bg-white border border-neutral-300 text-neutral-700 hover:bg-black hover:text-white hover:border-none': plan.name !== 'Silver'
                                }"
                                class="w-full "
                                :variant="plan.name === 'Silver' ? 'default' : 'outline'"
                            >
                                {{ plan.name }}
                            </Button>
                        </Link>
                    </CardFooter>
                </Card>
            </div>

        </div>
    </div>
</template>
<style scoped>

</style>