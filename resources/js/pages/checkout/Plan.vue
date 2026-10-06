<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { onUnmounted, onMounted, ref } from 'vue';
import { CheckCircle, XCircle } from "lucide-vue-next";
import Button from '@/components/Button.vue'
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import { convertCurrency, defaultFormatter } from '@/lib/utils'
import CustomSwitch from '@/components/CustomSwitch.vue';
import { Plan, PlanPrice, PlanPaymentProviderInformation } from '@/lib/plans';
import AppLogo from '@/components/AppLogo.vue';
import { loadScript } from "@paypal/paypal-js";
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from "@/components/ui/card";
import { ArrowBigLeft } from 'lucide-vue-next';
import { HttpError, HttpRequest, HttpResponse } from '@/lib/utils';
import { initializeSubscription } from "@/actions/App/Http/Controllers/SubscriptionsController";
import { dashboard,  plans as plansRoute } from '@/routes';

const page = usePage();

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const selected = ref(false)

const { plan, plan_price } = defineProps<{
    plan: Plan;
    plan_price: PlanPrice;
}>();

const convertedCurrency = ref(defaultFormatter(plan_price.currency, plan_price.formatted_amount));

onMounted(async () => {

    console.log({
        plan: plan,
        plan_price: plan_price
    })

    convertedCurrency.value = plan_price.currency === 'KES' ? await convertCurrency(plan_price.formatted_amount, 'KES', 'USD').then((res) => defaultFormatter(plan_price.currency, Number(res.formatted)) )
        : defaultFormatter(plan_price.currency, plan_price.formatted_amount);
})

const subscription_initiated = ref(false)
const payment_provider = ref<string>('paypal');

const initiateSubscription = async () => {

    subscription_initiated.value = true

    switch(payment_provider.value) {
        case 'paypal':
            await HttpRequest.post(initializeSubscription.url(), {
                provider: 'paypal',
                plan_price: plan_price.id
            }).then(res => {
                console.log('initiateSubscriptionResponse', res)
                switch(res.data.status) {
                    case 'pending':
                    case 'created':
                    case 'successful':
                        window.location.href = res.data.redirect
                        break
                    case 'failed':
                        break;
                    default:
                        break;
                }
                
            }).catch(error => {
                console.error('Error: ', error.response, error.response?.data)


                subscription_initiated.value = false
            })
           break;
        case 'polar':
            await HttpRequest.post(initializeSubscription.url(), {
                provider: 'polar',
                plan_price: plan_price.id
            }).then(res => {
                console.log('initiateSubscriptionResponse', res)
                switch(res.data.status) {
                    case 'pending':
                    case 'created':
                    case 'successful':
                        window.location.href = res.data.redirect
                        break
                    case 'failed':
                        break;
                    default:
                        break;
                }
                
            }).catch(error => {
                console.error('Error: ', error.response, error.response?.data)


                subscription_initiated.value = false
            })
           break;
        default:
            break;

    }

}


</script>

<template>
    <Head title="Pay Plan" />

    <article class="relative min-h-[100vh] flex items-center flex-col flex-1">
        <header class="grid grid-cols-2 w-full place-content-center px-16 py-6">
            <!-- Left Section -->
            <section class="">
                <Link :href="dashboardUrl">
                    <div class="flex aspect-square size-10 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                        <AppLogoIcon class="size-6 fill-current text-white dark:text-black" />
                    </div>
                </Link>
            </section>

            <!-- Right Section -->
            <section class="flex flex-row-reverse gap-4 items-center">
            
                <Link :href="plansRoute()" class="text-lg flex items-center">
                    <!-- &lt;&lt; Back -->
                    <ArrowBigLeft /> Back
                </Link>

                <section class="hidden xl:block">
                    <AppearanceTabs />
                </section>
            </section>
        </header>
        <main class="pt-8 flex items-center flex-col gap-4 relative">
            <section class="flex items-center flex-col">
                <h2 class="text-xl xl:text-2xl text-zinc-600 block">Pay Securely, Cancel Any Time.</h2>
                <h2 class="text-3xl xl:text-5xl font-bold block">Complete Subscription</h2>
            </section>
            <section class="relative grid grid-flow-row xl:grid-cols-10 w-full xl:max-w-[80rem] px-4  xl:px-0 space-y-4 gap-6">
                <section class="flex flex-1 flex-col gap-4 col-span-7 order-2 xl:order-1 ">
                    <h2 class="text-2xl font-bold block">Pay With</h2>
                    <section > <!--v-for="(item, index) in [...Array(10).keys()] " :key="index"-->
                        <section v-for="(provider, index) in plan_price.billing_plan_payment_provider_information" :key="index" class="border rounded-t-lg p-4 flex items-center ">
                            <section class="px-4 py-2 flex flex-col flex-1 gap-2">
                                <h2 class="text-2xl">
                                    {{ provider.payment_provider.charAt(0).toUpperCase() + provider.payment_provider.slice(1) }}
                                    <section class="inline-block">
                                        <img v-if="provider.payment_provider === 'stripe'"  width="64" src="https://demo.saasykit.com/images/payment-providers/stripe.png">
                                        <img v-if="provider.payment_provider === 'paypal'" width="64" src="https://1000logos.net/wp-content/uploads/2017/05/Paypal-Logo-2022-500x281.png">
                                        <svg v-if="provider.payment_provider === 'polar'" width="32" height="32" viewBox="0 0 29 29" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-black dark:text-white">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M9.07727 23.0572C13.8782 26.307 20.4046 25.0496 23.6545 20.2487C26.9043 15.4478 25.6469 8.92133 20.846 5.67149C16.0451 2.42165 9.51862 3.67905 6.26878 8.47998C3.01894 13.2809 4.27634 19.8073 9.07727 23.0572ZM10.4703 23.1428C14.862 25.3897 20.433 23.2807 22.9135 18.4322C25.394 13.5838 23.8447 7.83194 19.4531 5.58511C15.0614 3.33829 9.49042 5.4473 7.00991 10.2957C4.52939 15.1442 6.07867 20.896 10.4703 23.1428Z" fill="currentColor"></path>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M11.7222 24.2898C15.6865 25.58 20.35 22.1715 22.1385 16.6765C23.927 11.1815 22.1632 5.68099 18.1989 4.39071C14.2346 3.10043 9.5711 6.509 7.78261 12.004C5.99412 17.4989 7.75793 22.9995 11.7222 24.2898ZM12.9347 23.872C16.2897 24.5876 19.9174 20.9108 21.0374 15.6596C22.1574 10.4084 20.3457 5.57134 16.9907 4.85575C13.6357 4.14016 10.008 7.817 8.88797 13.0682C7.76793 18.3194 9.57971 23.1564 12.9347 23.872Z" fill="currentColor"></path>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M13.8537 24.7382C16.5062 25.0215 19.1534 20.5972 19.7664 14.8563C20.3794 9.1155 18.7261 4.23202 16.0736 3.94879C13.4211 3.66556 10.7739 8.08983 10.1609 13.8307C9.54788 19.5715 11.2012 24.455 13.8537 24.7382ZM15.0953 22.9906C17.015 22.9603 18.5101 19.0742 18.4349 14.3108C18.3596 9.54747 16.7424 5.71058 14.8228 5.7409C12.9032 5.77123 11.408 9.6573 11.4833 14.4207C11.5585 19.184 13.1757 23.0209 15.0953 22.9906Z" fill="currentColor"></path>
                                        </svg>
                                        <img v-if="provider.payment_provider === 'paddle'" alt="Paddle logo" loading="lazy" width="105" height="32" decoding="async" data-nimg="1" style="color:transparent" src="https://www.paddle.com/_next/static/media/paddle-logo-inverted.02kelud1d95as.svg">
                                    </section>
                                </h2>
                                <h3>{{ plan.description ?? 'You will be redirected to complete your payment' }}</h3>
                            </section>
                            <section class="">
                                <CustomSwitch 
                                    :condition="payment_provider === provider.payment_provider"
                                    @toggled="payment_provider = provider.payment_provider" 
                                />
                            </section>
                        </section>
                        <section class="border rounded-t-lg p-4 hidden items-center ">
                            <section class="px-4 py-2 flex flex-col flex-1 gap-2">
                                <h2 class="text-2xl">
                                    Paypal 
                                    <section class="inline-block">
                                        <img  width="64" src="https://1000logos.net/wp-content/uploads/2017/05/Paypal-Logo-2022-500x281.png">
                                    </section>
                                </h2>
                                <h3>Lorem ipsum dolor sit amet consectetur, adipisicing elit.</h3>
                            </section>
                            <section class="">
                                <CustomSwitch 
                                    :condition="selected"
                                    @toggled="selected = !selected" 
                                />
                            </section>
                        </section>
                    </section>
                </section> 

                <section class="col-span-10 xl:col-span-3 relative mx-auto order-1 xl:order-2 xl:mx-0 ">
                    <section class="flex flex-1 flex-col gap-4 col-span-3 sticky top-2">  
                        <h2 class="text-2xl font-bold block">Plan Details</h2>
    
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
                </section>
            </section>
        </main>
        <footer class="sticky xl:sticky flex justify-center items-center bottom-0 py-6 w-full  shadow-2xl shadow-emerald-400/80 bg-[var(--background)]" >
            <article class="flex items-center justify-center w-full gap-4 text-wrap">
                <p class="text-zinc-600 text-md text-wrap">By continuing, you agree to our Terms of Service and Privacy Policy.</p>
                <button @click="initiateSubscription" :disbaled="subscription_initiated" :class="[{'opacity-25 cursor-not-allowed': subscription_initiated }]" 
                    class="text-white py-3 px-8 bg-emerald-500 hover:bg-emerald-600 rounded-4xl flex items-center gap-1.5"
                >
                    <section v-if="subscription_initiated" class="loader"></section> 
                    <section>Confirm & Pay</section>
                </button>
            </article>
        </footer>
    </article>
</template>
<style scoped>
.loader {
    width: 25px;
    height: 25px;
    border: 5px solid #FFF;
    border-bottom-color: transparent;
    border-radius: 50%;
    display: inline-block;
    box-sizing: border-box;
    animation: rotation 1s linear infinite;
}

@keyframes rotation {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
} 
</style>