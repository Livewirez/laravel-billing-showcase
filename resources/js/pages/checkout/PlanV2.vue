<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { onUnmounted, onMounted, ref, computed } from 'vue';
import { CheckCircle, XCircle } from "lucide-vue-next";
import Button from '@/components/Button.vue'
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import { convertCurrency, defaultFormatter } from '@/lib/utils'
import CustomSwitch from '@/components/CustomSwitch.vue';
import { Plan, PlanPrice, PlanPaymentProviderInformation } from '@/lib/plans';
import AppLogo from '@/components/AppLogo.vue';
import { loadScript } from "@paypal/paypal-js";
import type { 
    PayPalNamespace, OnApproveData,
    OnApproveActions, CreateSubscriptionActions
} from "@paypal/paypal-js";
import { initializePaddle, type Paddle } from '@paddle/paddle-js';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from "@/components/ui/card";
import { ArrowBigLeft } from 'lucide-vue-next';

import { HttpError, HttpRequest, HttpResponse } from '@/lib/utils';
import { initializeSubscription } from "@/actions/App/Http/Controllers/SubscriptionsController";
import { start_subscription_with_token  } from "@/routes/subscriptions";
import { setup_subscription_payment_token } from "@/routes/api/subscriptions";

import { dashboard, plansv2 as plansv2Route } from '@/routes';

const page = usePage();

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);


const selected = ref(false)

const { plan, plan_price } = defineProps<{
    plan: Plan;
    plan_price: PlanPrice;
}>();

const paypal = ref<PayPalNamespace | null>(null); 
const paddle = ref<Paddle | null>(null);

type PaymentProvider = 'paypal' | 'stripe' | 'polar' | 'mpesa' | 'stripe' | 'lemon_squeezy' | 'paystack';

const selectedProvider = ref<string>('paypal');

const convertedCurrency = ref(defaultFormatter(plan_price.currency, plan_price.formatted_amount));

const alerts = document.getElementById("alerts") as HTMLElement | null;

/**
 * @source https://www.w3schools.com/w3css/tryit.asp?filename=tryw3css_tabulators
 * 
 */
function openProviderTab(event: MouseEvent, providerName: string) {

    selectedProvider.value = providerName;

    const target = event.target as HTMLButtonElement;

    if (target) {
        target.classList.add('bg-zinc-300', 'dark:bg-neutral-950');

        const parent = target.parentNode;

        if (parent) {
            Array.from(parent.children).forEach((c: Element) => {
                if ((c as HTMLButtonElement) === target) return;

                c.classList.remove('bg-zinc-300', 'dark:bg-neutral-950');
            })
        }
    }


    var i;
    var x = document.getElementsByClassName("provider") as HTMLCollectionOf<HTMLElement>;
    for (i = 0; i < x.length; i++) {
            x[i].style.display = "none";  
    }

    // so as not to clash with paypal
    (document.getElementById(`provider-${providerName}`) as HTMLElement).style.display = "block";  
}

const subscription_initiated = ref(false)
const initiateSubscription = async (provider: string | null = null ) => {

    subscription_initiated.value = true;

    switch(provider ?? selectedProvider.value) {
        case 'paypal':
            await HttpRequest.post(initializeSubscription.url(), {
                provider: 'paypal',
                plan_price: plan_price.id
            }).then(res => {
                console.log('initiateSubscriptionResponse', res)
                switch(res.data.status) {
                    case 'pending':
                    case 'created':
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
                        window.location.assign(res.data.redirect)
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
        case 'paddle':

            if (! paddle.value) {
                console.error('Error: Paddle instance not available');
                subscription_initiated.value = false;

                return;
            }

            await HttpRequest.post(initializeSubscription.url(), {
                provider: 'paddle',
                plan_price: plan_price.id
            }).then(res => {
                console.log('initiateSubscriptionResponse:Paddle', res)
                switch(res.data.status) {
                    case 'pending':
                    case 'created':
                        paddle.value?.Checkout.open({
                            transactionId: res.data.transaction_id,
                            settings: {
                                displayMode: 'overlay', // type DisplayMode = "inline" | "overlay"
                                theme: (() => {
                                    const mediaQueryList = window.matchMedia('(prefers-color-scheme: dark)');
                                    return mediaQueryList.matches ? 'dark' : 'light';
                                })(), // type Theme = "light" | "dark"
                                // locale?: string;
                                // allowLogout?: boolean;
                                // showAddDiscounts?: boolean;
                                // showAddTaxId?: boolean;
                                // frameTarget?: string;
                                // frameStyle?: string;
                                // frameInitialHeight?: number;
                                successUrl: import.meta.env.VITE_PADDLE_SUBSCRIPTION_SUCCESS_URL,
                                // allowedPaymentMethods?: AvailablePaymentMethod[]; // type AvailablePaymentMethod = "paypal" | "alipay" | "apple_pay" | "bancontact" | "card" | "google_pay" | "ideal" | "saved_payment_methods"
                                // allowDiscountRemoval?: boolean;
                                // variant?: Variant; // type Variant = "multi-page" | "one-page"
                            }
                        });
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

onMounted(async () => {

    console.log({
        plan: plan,
        plan_price: plan_price
    })
    
    try {
        paypal.value = await loadScript({ clientId: import.meta.env.VITE_PAYPAL_CLIENT_ID, vault: true });
    } catch (error) {
        console.error("failed to load the PayPal JS SDK script", error);
    }

    console.log('PayPal Namespace: ', paypal.value)

    if (paypal.value !== null) {
        document.getElementById("loading")?.classList.add("hide");
        document.getElementById("content")?.classList.remove("hide");
        
        setTimeout(async () => {
            try {
                await paypal.value.Buttons({
                    style: {
                        layout: 'vertical', // vertical, horizontal
                        color:  'blue',
                        shape:  'rect',
                        label:  'subscribe',
                        
                    },
                    createVaultSetupToken: async () => {  

                        return await HttpRequest.post(setup_subscription_payment_token.url(), {
                            provider: 'paypal',
                        })
                        .then(response => {
                            console.log('Initiate Vault Setup Token', response)
                            return response.data.metadata.id
                        }).catch(error => {
                            console.error(error.response.data);

                            throw new Error('Failed to create vault setup token', { cause: error.response?.statusText });
                        });
                    },  

                    async onApprove(data: OnApproveData, actions: OnApproveActions) {

                        console.log('ApproveData', data);

                        router.get(start_subscription_with_token({
                            query: {
                                provider: 'paypal', 
                                token: data.vaultSetupToken,
                                PayerID: data.payerID,
                                facilitatorAccessToken: data.facilitatorAccessToken,
                                vaultSetupToken: data.vaultSetupToken,
                            }
                        }), {
                            provider: 'paypal', 
                            token: data.vaultSetupToken,
                            PayerID: data.payerID,
                            facilitatorAccessToken: data.facilitatorAccessToken,
                            vaultSetupToken: data.vaultSetupToken,
                        }, {
                            onCancelToken: cancelToken => {},
                            onCancel: () => {},
                            onBefore: visit => {},
                            onStart: visit => {},
                            onProgress: progress => {},
                            onSuccess: page => {},
                            onError: errors => {},
                            onFinish: visit => {},
                            onPrefetching: () => {},
                            onPrefetched: () => {},
                        })

                    },
                    onCancel: () => {
                        if (alerts) {
                            alerts.innerHTML = `<div class="ms-alert ms-action2 ms-small grid place-items-center font-semibold text-lg text-yellow-500"><span class="ms-close"></span><p>Order cancelled!</p></div>`;
                        }
                    },
                    onError: (err: any) => {
                        console.error(err);
                    }  
                }).render("#paypal-button-container");
            } catch (error) {
                console.error("failed to render the PayPal Buttons", error);
            }
        }, 1500)
    }

    await initializePaddle({
        environment: import.meta.env.VITE_PADDLE_ENVIRONMENT,
        token: import.meta.env.VITE_PADDLE_CLIENT_TOKEN // replace with a client-side token
    }).then((p: Paddle | undefined) => {
        if (p) {
            paddle.value = p

            return;
        }

        throw new Error('Could not initialize Paddle instance');
    }).catch(e => {

        console.error('Error: ', e)

        subscription_initiated.value = false
    });

    convertedCurrency.value = plan_price.currency === 'KES' ? await convertCurrency(plan_price.formatted_amount, 'KES', 'USD').then((res) => defaultFormatter(plan_price.currency, Number(res.formatted)) )
        : defaultFormatter(plan_price.currency, plan_price.formatted_amount);
})


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
                        
                <Link :href="plansv2Route()" class="text-lg flex items-center">
                    <!-- &lt;&lt; Back -->
                    <ArrowBigLeft /> Back
                </Link>

                <section class="hidden xl:block">
                    <AppearanceTabs />
                </section>
            </section>
        </header>
        <main class="pt-8 flex items-center flex-col gap-4">
            <section class="flex items-center flex-col">
                <h2 class="text-xl xl:text-2xl text-zinc-600 block">Pay Securely, Cancel Any Time.</h2>
                <h2 class="text-3xl xl:text-5xl font-bold block">Complete Subscription</h2>
            </section>
            <section class="relative grid grid-flow-row xl:grid-cols-10 w-full xl:max-w-[80rem] px-4  xl:px-0 space-y-4 gap-6">
                <section id="A" class="flex flex-1 flex-col gap-4 col-span-7 order-2 xl:order-1 ">
                    <h2 class="text-2xl font-bold block">Pay With</h2>
                    <section>

                        <section class="rounded-lg  border dark:border-neutral-700">
                            <section class="grid grid-flow-col bg-neutral-900 rounded-lg" style="grid-template-columns: repeat(auto, minmax(0, 1fr));">
                                <button :id="`btn-${provider.payment_provider}`" v-for="(provider, index) in plan_price.billing_plan_payment_provider_information" 
                                    :class="[
                                        index === 0 ? 'bg-zinc-300 dark:bg-neutral-950': '' ,
                                        index === 0 ? 'rounded-tl-lg' : index === (plan_price.billing_plan_payment_provider_information || []).length - 1 ? 'rounded-tr-lg' : ''
                                    ]" 
                                    class="active:bg-zinc-300 dark:active:bg-neutral-950 rounded-tl-lg  p-2.5" 
                                    @click="openProviderTab($event, provider.payment_provider)"
                                >
                                    <section class="inline-block">
                                        <img v-if="provider.payment_provider === 'stripe'"  width="96" src="https://demo.saasykit.com/images/payment-providers/stripe.png">
                                        <img v-if="provider.payment_provider === 'paypal'" width="96" src="https://1000logos.net/wp-content/uploads/2017/05/Paypal-Logo-2022-500x281.png">
                                        <svg v-if="provider.payment_provider === 'polar'" width="32" height="32" viewBox="0 0 29 29" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-black dark:text-white">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M9.07727 23.0572C13.8782 26.307 20.4046 25.0496 23.6545 20.2487C26.9043 15.4478 25.6469 8.92133 20.846 5.67149C16.0451 2.42165 9.51862 3.67905 6.26878 8.47998C3.01894 13.2809 4.27634 19.8073 9.07727 23.0572ZM10.4703 23.1428C14.862 25.3897 20.433 23.2807 22.9135 18.4322C25.394 13.5838 23.8447 7.83194 19.4531 5.58511C15.0614 3.33829 9.49042 5.4473 7.00991 10.2957C4.52939 15.1442 6.07867 20.896 10.4703 23.1428Z" fill="currentColor"></path>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M11.7222 24.2898C15.6865 25.58 20.35 22.1715 22.1385 16.6765C23.927 11.1815 22.1632 5.68099 18.1989 4.39071C14.2346 3.10043 9.5711 6.509 7.78261 12.004C5.99412 17.4989 7.75793 22.9995 11.7222 24.2898ZM12.9347 23.872C16.2897 24.5876 19.9174 20.9108 21.0374 15.6596C22.1574 10.4084 20.3457 5.57134 16.9907 4.85575C13.6357 4.14016 10.008 7.817 8.88797 13.0682C7.76793 18.3194 9.57971 23.1564 12.9347 23.872Z" fill="currentColor"></path>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M13.8537 24.7382C16.5062 25.0215 19.1534 20.5972 19.7664 14.8563C20.3794 9.1155 18.7261 4.23202 16.0736 3.94879C13.4211 3.66556 10.7739 8.08983 10.1609 13.8307C9.54788 19.5715 11.2012 24.455 13.8537 24.7382ZM15.0953 22.9906C17.015 22.9603 18.5101 19.0742 18.4349 14.3108C18.3596 9.54747 16.7424 5.71058 14.8228 5.7409C12.9032 5.77123 11.408 9.6573 11.4833 14.4207C11.5585 19.184 13.1757 23.0209 15.0953 22.9906Z" fill="currentColor"></path>
                                        </svg>
                                        <img v-if="provider.payment_provider === 'paddle'" alt="Paddle logo" loading="lazy" width="105" height="32" decoding="async" data-nimg="1" style="color:transparent" src="https://www.paddle.com/_next/static/media/logo-primary.1520b460.svg">
                                    </section>
                                </button>
                            </section>
                            <section v-for="(provider, index) in plan_price.billing_plan_payment_provider_information" :id="`provider-${provider.payment_provider}`" class="provider p-4 flex gap-4" :style="`display: ${index === 0 ? 'block' : 'none'};`">
                                
                                <section class="mb-8">
                                    <h2 class="text-2xl ">{{ provider.payment_provider.charAt(0).toUpperCase() + provider.payment_provider.slice(1) }}</h2>
                                </section>

                                <section class="mb-8">
                                    <p class="text-sm">
                                        Services are subscription based and will automatically renew annually for an additional 1-year term after the first 2 years at the then-applicable renewal price, unless canceled. 
                                        The price is subject to change, but we will notify you of any adjustments before charging. 
                                        You can cancel auto-renewal anytime by following the instructions in your Nord Account. 
                                        Payments are in USD and may include provider fees.
                                        By purchasing, you agree to the terms of service, auto-renewal terms, to receive electronic documents, and acknowledge our privacy policy.
                                    </p>
                                </section>

                                <section v-if="provider.payment_provider === 'paypal'">
                                    <section class="max-w-[400px] w-full">
                                        <div id="paypal-button-container"></div>
    
                                    </section>
    
                                    <div id="alerts" class="ms-text-center pt-6"></div>
    
                                    <div id="loading" class="spinner-container ms-div-center grid place-items-center">
                                        <div class="spinner"></div>
                                    </div>
                                </section>

                                <section v-else>
                                    <button v-if="provider.payment_provider === 'polar'" @click="initiateSubscription('polar')" :disbaled="subscription_initiated" :class="[{'opacity-25 cursor-not-allowed': subscription_initiated }]"
                                        class="max-w-[400px] bg-[#181818] hover:bg-[#0f0f0f] shadow-sm shadow-white/5 w-full rounded-sm items-center justify-center cursor-pointer px-4 py-2.25 flex flex-1 gap-4"
                                    >
                                        <span v-if="subscription_initiated" class="loader"></span>
                                        <!-- <img  width="64" src="https://1000logos.net/wp-content/uploads/2017/05/Paypal-Logo-2022-500x281.png"> -->
                                        <svg width="32" height="32" viewBox="0 0 29 29" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-black dark:text-white">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M9.07727 23.0572C13.8782 26.307 20.4046 25.0496 23.6545 20.2487C26.9043 15.4478 25.6469 8.92133 20.846 5.67149C16.0451 2.42165 9.51862 3.67905 6.26878 8.47998C3.01894 13.2809 4.27634 19.8073 9.07727 23.0572ZM10.4703 23.1428C14.862 25.3897 20.433 23.2807 22.9135 18.4322C25.394 13.5838 23.8447 7.83194 19.4531 5.58511C15.0614 3.33829 9.49042 5.4473 7.00991 10.2957C4.52939 15.1442 6.07867 20.896 10.4703 23.1428Z" fill="currentColor"></path>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M11.7222 24.2898C15.6865 25.58 20.35 22.1715 22.1385 16.6765C23.927 11.1815 22.1632 5.68099 18.1989 4.39071C14.2346 3.10043 9.5711 6.509 7.78261 12.004C5.99412 17.4989 7.75793 22.9995 11.7222 24.2898ZM12.9347 23.872C16.2897 24.5876 19.9174 20.9108 21.0374 15.6596C22.1574 10.4084 20.3457 5.57134 16.9907 4.85575C13.6357 4.14016 10.008 7.817 8.88797 13.0682C7.76793 18.3194 9.57971 23.1564 12.9347 23.872Z" fill="currentColor"></path>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M13.8537 24.7382C16.5062 25.0215 19.1534 20.5972 19.7664 14.8563C20.3794 9.1155 18.7261 4.23202 16.0736 3.94879C13.4211 3.66556 10.7739 8.08983 10.1609 13.8307C9.54788 19.5715 11.2012 24.455 13.8537 24.7382ZM15.0953 22.9906C17.015 22.9603 18.5101 19.0742 18.4349 14.3108C18.3596 9.54747 16.7424 5.71058 14.8228 5.7409C12.9032 5.77123 11.408 9.6573 11.4833 14.4207C11.5585 19.184 13.1757 23.0209 15.0953 22.9906Z" fill="currentColor"></path>
                                        </svg>
                                    </button>

                                    <button v-if="provider.payment_provider === 'paddle' && selectedProvider === 'paddle'" @click="initiateSubscription('paddle')" :disbaled="subscription_initiated" :class="[{'opacity-25 cursor-not-allowed': subscription_initiated }]"
                                        class="max-w-[400px] bg-[#171C17] hover:bg-[#21261B] shadow-sm shadow-white/5 w-full rounded-sm items-center justify-center cursor-pointer px-4 py-2.25 flex flex-1 gap-4"
                                    >
                                        <span v-if="subscription_initiated" class="loader"></span>
                                        <img alt="Paddle logo" loading="lazy" width="105" height="32" decoding="async" data-nimg="1" style="color:transparent" src="https://www.paddle.com/_next/static/media/logo-primary.1520b460.svg">
                                    </button>
                                </section>


                            </section>
                        </section>
                    </section>
                </section> 

                <section id="B" class="col-span-10 xl:col-span-3 relative mx-auto order-1 xl:order-2 xl:mx-0 ">
                    <section class="sticky top-2 flex  flex-1 flex-col gap-4">  
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
    </article>
</template>
<style scoped>
.hide {
    display:none !important;
}
.spinner-container {

    /* display: flex;
    justify-content: center;
    align-items: center; */
}

.spinner {
    border: 8px solid rgba(0, 0, 0, 0.1);
    border-top-color: lightblue;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}


.loader {
    width: 25px;
    height: 25px;
    border: 1.75px solid #FFF;
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