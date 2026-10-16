
<script setup lang="ts">
import { onMounted, shallowRef, computed,  ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { type PartialCartItem, type CartInfo, type CartItem } from  '@/lib/store_items';
import { 
    type PartialProduct, 
    type AmountBreakdown, 
    type Money, 
    type Product 
} from '@/lib/products';
import { PaymentMethod } from '@/lib/payment_providers';    
import ShoppingCart from '@/lib/store_items';
import PayPalLogo from '@/components/logos/PayPalLogo.vue';
import PaddleLogo from '@/components/logos/PaddleLogo.vue';
import MpesaLogo from '@/components/logos/MpesaLogo.vue';
import MpesaLogoSvg from '@/components/logos/MpesaLogoSvg.vue';

import { loadScript } from "@paypal/paypal-js";
import type { 
    PayPalNamespace,
    CreateOrderData, CreateOrderActions,
    OnApproveData, OnApproveActions, 
    CreateSubscriptionActions
} from "@paypal/paypal-js";

import { initializePaddle, type Paddle } from '@paddle/paddle-js';
import { defaultFormatter } from '@/lib/utils';
import { Checkbox } from "@/components/ui/checkbox";
import { CreditCard, CreditCardIcon, Cross } from "lucide-vue-next";
import PaymentCard from '@/components/paymentcard/PaymentCard.vue';
import CybersourceUnified from '@/components/paymentcard/CybersourceUnified.vue';
import type { PaymentOptions } from '@/lib/orders';
import { CybersourceMicroformHandler } from '@/lib/payment_providers';
import { JWT } from '@/lib/utils';

import { HttpError, HttpRequest, HttpResponse } from '@/lib/utils';

import { checkout as checkoutRoute } from '@/routes/store/cart';

import { initiate_payment, setup_payment_token } from '@/routes/api/orders';
import { complete_order_with_token } from '@/routes/payment'

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Checkout',
                href: checkoutRoute(),
            },
        ],
    },
});


const props = defineProps<{
    cart_info: CartInfo;
    cart_items: PartialCartItem[],
    payment_methods: PaymentMethod[]
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Store',
        href: '/store',
    },
];

const tabs = ['card', 'paypal', 'paypaljs', 'polar', 'paddle', 'card:cybersource-unified']
const currentTab = ref('paypal')

function formatPrice(currency: string, amount: number | bigint | Intl.StringNumericLiteral) 
{
    return defaultFormatter(currency, amount);
}

const payment_initiated = ref(false)

const paypalSrcImg = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAxcHgi…A5NS40MzcgMyA5NS4zMzcgMy4zIFoiPjwvcGF0aD48L3N2Zz4'

const paypal = ref<PayPalNamespace | null>(null); 
const alerts = document.getElementById("alerts") as HTMLElement | null;
const intent = "capture";
const save_token = ref(false)

const paddle = ref<Paddle | null>(null);

const initiatePayment = async (provider: string, options: PaymentOptions = {} ) => {

    if (
        provider === 'card' 
        && ['cybersource_unified_checkout', 'cybersource_click_to_pay'].includes(options?.card_gateway ?? '')
        && options?.card_gateway_phase === 1
    ) {
        payment_initiated.value = false
    } else {
        payment_initiated.value = true
    }

    switch(provider) {

        case 'card':
            switch (options.card_gateway) {
                case 'cybersource_unified_checkout': 
                    return await HttpRequest.post(initiate_payment.url(), {
                        provider: 'card',
                        products: props.cart_items,
                        ...options
                    }).then(res => {
                        return res;
                    }).catch(error => {
                        console.error('Error: ', error.response, error.response?.data)
                        payment_initiated.value = false

                        throw error;
                    })
                case 'cybersource_click_to_pay':
                    return await HttpRequest.post(initiate_payment.url(), {
                        provider: 'card',
                        products: props.cart_items,
                        ...options
                    }).then(res => {
                        return res;
                    }).catch(error => {
                        console.error('Error: ', error.response, error.response?.data)
                        payment_initiated.value = false

                        throw error;
                    })
                default:
                    return await HttpRequest.post(initiate_payment.url(), {
                        provider: 'card',
                        products: props.cart_items,
                        ...options
                    }).then(res => {
                        return res;
                    }).catch(error => {
                        console.error('Error: ', error.response, error.response?.data)
                        payment_initiated.value = false

                        throw error;
                    })
            }
        case 'paypal':
            return await HttpRequest.post(initiate_payment.url(), {
                provider: 'paypal',
                products: props.cart_items
            }).then(res => {
                console.log('initiatePaymentResponse:PayPal', res)
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

                return res
                
            }).catch(error => {
                console.error('Error: ', error.response, error.response?.data)

                payment_initiated.value = false
            })
        case 'polar':
            return await HttpRequest.post(initiate_payment.url(), {
                provider: 'polar',
                products: props.cart_items
            }).then(res => {
                console.log('initiatePaymentResponse:Polar', res)
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


                payment_initiated.value = false
            })
        case 'paddle':

            if (! paddle.value) {
                console.error('Error: Paddle instance not available');
                payment_initiated.value = false;

                return;
            }

            return await HttpRequest.post(initiate_payment.url(), {
                provider: 'paddle',
                products: props.cart_items
            }).then(res => {
                console.log('initiatePaymentResponse:Paddle', res)
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
                                successUrl: import.meta.env.VITE_PADDLE_SUCCESS_URL,
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
                return res
            }).catch(error => {
                console.error('Error: ', error.response, error.response?.data)


                payment_initiated.value = false
            })
        default:
            return;

    }

}


onMounted(async () => {

    try {
        paypal.value = await loadScript({ clientId: import.meta.env.VITE_PAYPAL_CLIENT_ID });
    } catch (error) {
        console.error("failed to load the PayPal JS SDK script", error);
    }

    console.log('PayPal Namespace: ', paypal.value)

    if (paypal.value !== null) {
        //Handle loading spinner
        document.getElementById("loading")?.classList.add("hide");
        document.getElementById("content")?.classList.remove("hide");

        setTimeout(async () => {
            try {
                await paypal.value.Buttons({
                    style: {
                        layout: 'vertical', // vertical, horizontal
                        color:  'gold', // "gold" | "blue" | "silver" | "white" | "black" | undefined
                        shape:  'rect', // "rect" | "pill" | "sharp" | undefined
                        label:  'checkout', // "paypal" | "checkout" | "buynow" | "pay" | "installment" | "subscribe" | "donate" | undefined
                        
                    },
                    createVaultSetupToken: async () => {

                        return await HttpRequest.post(setup_payment_token.url(), {
                            provider: 'paypal',
                            products: props.cart_items,
                            save_token: save_token.value
                        })
                        .then(response => {
                            console.log('Initiate Vault Setup Token', response)
                            return response.data.metadata.id
                        }).catch(error => {
                            console.error(error.response.data);

                            throw new Error('Failed to create vault setup token', { cause: error });
                        });
                    },
                    onApprove: async (data: OnApproveData, actions: OnApproveActions) => {
                        console.log('onAprrove: Data ', data);

                        const completionURL = complete_order_with_token({
                            query: {
                                provider: 'paypal', 
                                token: data.vaultSetupToken,
                                PayerID: data.payerID,
                                facilitatorAccessToken: data.facilitatorAccessToken,
                                vaultSetupToken: data.vaultSetupToken,
                                saveMethod: true
                            }
                        });

                        router.get(completionURL, {
                            provider: 'paypal', 
                            token: data.vaultSetupToken,
                            PayerID: data.payerID,
                            facilitatorAccessToken: data.facilitatorAccessToken,
                            vaultSetupToken: data.vaultSetupToken,
                            saveMethod: true
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

        payment_initiated.value = false
    });
})

const savedPaymentMethodSelected = ref(false)
const selectedSavedPaymentMethod = ref<PaymentMethod | null>(null)
const selectSavedPaymentMethod = (payment_method: PaymentMethod) => {
    savedPaymentMethodSelected.value = !savedPaymentMethodSelected.value

    selectedSavedPaymentMethod.value = payment_method;

}


const completePayment = () => {

    payment_initiated.value = true;

    if (! selectedSavedPaymentMethod.value) return;

    const completionData =  {
        provider: 'paypal', 
        token: selectedSavedPaymentMethod.value?.billable_payment_method_id,
    }

    const completionURL = complete_order_with_token({
        query: completionData
    });

    router.get(completionURL, completionData, {
        onCancelToken: cancelToken => {},
        onCancel: () => {
            payment_initiated.value = false;
        },
        onBefore: visit => {},
        onStart: visit => {},
        onProgress: progress => {},
        onSuccess: page => {},
        onError: errors => {},
        onFinish: visit => {},
        onPrefetching: () => {},
        onPrefetched: () => {},
    })
}

const paddle_started = ref(false)

function createPaddle() {

    paddle_started.value = true

    setTimeout(() => {
        paddle_started.value = false
    }, 10000)
   // txn_01k54jf29fz7fdmqspj14tjmht
    paddle.value?.Checkout.open({
        transactionId: 'txn_01k54jf29fz7fdmqspj14tjmht',
        settings: {
            displayMode: 'inline', // type DisplayMode = "inline" | "overlay"
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
            successUrl: import.meta.env.VITE_PADDLE_SUCCESS_URL,
            // allowedPaymentMethods?: AvailablePaymentMethod[]; // type AvailablePaymentMethod = "paypal" | "alipay" | "apple_pay" | "bancontact" | "card" | "google_pay" | "ideal" | "saved_payment_methods"
            // allowDiscountRemoval?: boolean;
            // variant?: Variant; // type Variant = "multi-page" | "one-page"
        }
    });
}
</script>

<template>
    <Head title="Checkout Cart" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div class="relative min-h-[100vh] flex flex-col  lg:grid grid-cols-2 flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">

            <div class="flex flex-col gap-8">
                <div class="mt-2 flex flex-col px-2 gap-8">
                    <h1 class="text-xxl font-bold">Payment Methods</h1>

                    <div v-show="payment_methods.length > 0" class="py-4 flex flex-col gap-2">
                        <h2>
                            Saved Methods
                        </h2>

                        <template v-for="payment_method in payment_methods" :key="payment_method.id"> 
                            <div >
                                <button @click="selectSavedPaymentMethod(payment_method)" class="cursor-pointer flex items-center justify-start gap-6 border rounded-md p-4 border-neutral-600/65 active:border-neutral-600">
                                    <PayPalLogo v-if="payment_method.payment_provider.startsWith('paypal')" :svg_height="20"/>
                                    <!-- <MpesaLogo v-if="tab === 'Mpesa'" :height="28" :width="110"/> -->
                                    <MpesaLogoSvg v-if="payment_method.payment_provider.startsWith('mpesa')" :svg_height="85" />
    
                                    <svg v-if="payment_method.payment_provider.startsWith('polar')" width="24" height="24" viewBox="0 0 29 29" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-black dark:text-white">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M9.07727 23.0572C13.8782 26.307 20.4046 25.0496 23.6545 20.2487C26.9043 15.4478 25.6469 8.92133 20.846 5.67149C16.0451 2.42165 9.51862 3.67905 6.26878 8.47998C3.01894 13.2809 4.27634 19.8073 9.07727 23.0572ZM10.4703 23.1428C14.862 25.3897 20.433 23.2807 22.9135 18.4322C25.394 13.5838 23.8447 7.83194 19.4531 5.58511C15.0614 3.33829 9.49042 5.4473 7.00991 10.2957C4.52939 15.1442 6.07867 20.896 10.4703 23.1428Z" fill="currentColor"></path>
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M11.7222 24.2898C15.6865 25.58 20.35 22.1715 22.1385 16.6765C23.927 11.1815 22.1632 5.68099 18.1989 4.39071C14.2346 3.10043 9.5711 6.509 7.78261 12.004C5.99412 17.4989 7.75793 22.9995 11.7222 24.2898ZM12.9347 23.872C16.2897 24.5876 19.9174 20.9108 21.0374 15.6596C22.1574 10.4084 20.3457 5.57134 16.9907 4.85575C13.6357 4.14016 10.008 7.817 8.88797 13.0682C7.76793 18.3194 9.57971 23.1564 12.9347 23.872Z" fill="currentColor"></path>
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M13.8537 24.7382C16.5062 25.0215 19.1534 20.5972 19.7664 14.8563C20.3794 9.1155 18.7261 4.23202 16.0736 3.94879C13.4211 3.66556 10.7739 8.08983 10.1609 13.8307C9.54788 19.5715 11.2012 24.455 13.8537 24.7382ZM15.0953 22.9906C17.015 22.9603 18.5101 19.0742 18.4349 14.3108C18.3596 9.54747 16.7424 5.71058 14.8228 5.7409C12.9032 5.77123 11.408 9.6573 11.4833 14.4207C11.5585 19.184 13.1757 23.0209 15.0953 22.9906Z" fill="currentColor"></path>
                                    </svg>
    
                                    <CreditCard v-if="payment_method.payment_provider.startsWith('card')" />
    
                                    <span class="text-sm">{{ payment_method.billing_email }}</span>
                                </button>
                            </div>
                        </template>

                    </div>

                    <div v-show="savedPaymentMethodSelected === false" class="xl:max-w-[calc(100%-40%)] max-w-full w-full flex flex-col gap-4 scale-90">
                        <template v-for="(tab, index) in tabs" :key="tab">
                            <div  class="grid space-y-3">
                                <div class="relative flex items-center payment-selection justify-between">
                                    <label :for="`radio-tab-${index}`" class="form-control flex items-center gap-[0.5em]">
                                        <input
                                            :id="`radio-tab-${index}`"
                                            @change="currentTab = tab"
                                            v-model="currentTab"
                                            name="payment"
                                            type="radio"
                                            class="custom-radio "
                                            :value="tab"
                                        />
                                        <PayPalLogo v-if="tab.startsWith('paypal')" :svg_height="30"/>
                                        <!-- <MpesaLogo v-if="tab === 'Mpesa'" :height="28" :width="110"/> -->
                                        <MpesaLogoSvg v-if="tab === 'Mpesa'" :svg_height="85" />
    
                                        <svg v-if="tab === 'polar'" width="32" height="32" viewBox="0 0 29 29" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-black dark:text-white">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M9.07727 23.0572C13.8782 26.307 20.4046 25.0496 23.6545 20.2487C26.9043 15.4478 25.6469 8.92133 20.846 5.67149C16.0451 2.42165 9.51862 3.67905 6.26878 8.47998C3.01894 13.2809 4.27634 19.8073 9.07727 23.0572ZM10.4703 23.1428C14.862 25.3897 20.433 23.2807 22.9135 18.4322C25.394 13.5838 23.8447 7.83194 19.4531 5.58511C15.0614 3.33829 9.49042 5.4473 7.00991 10.2957C4.52939 15.1442 6.07867 20.896 10.4703 23.1428Z" fill="currentColor"></path>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M11.7222 24.2898C15.6865 25.58 20.35 22.1715 22.1385 16.6765C23.927 11.1815 22.1632 5.68099 18.1989 4.39071C14.2346 3.10043 9.5711 6.509 7.78261 12.004C5.99412 17.4989 7.75793 22.9995 11.7222 24.2898ZM12.9347 23.872C16.2897 24.5876 19.9174 20.9108 21.0374 15.6596C22.1574 10.4084 20.3457 5.57134 16.9907 4.85575C13.6357 4.14016 10.008 7.817 8.88797 13.0682C7.76793 18.3194 9.57971 23.1564 12.9347 23.872Z" fill="currentColor"></path>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M13.8537 24.7382C16.5062 25.0215 19.1534 20.5972 19.7664 14.8563C20.3794 9.1155 18.7261 4.23202 16.0736 3.94879C13.4211 3.66556 10.7739 8.08983 10.1609 13.8307C9.54788 19.5715 11.2012 24.455 13.8537 24.7382ZM15.0953 22.9906C17.015 22.9603 18.5101 19.0742 18.4349 14.3108C18.3596 9.54747 16.7424 5.71058 14.8228 5.7409C12.9032 5.77123 11.408 9.6573 11.4833 14.4207C11.5585 19.184 13.1757 23.0209 15.0953 22.9906Z" fill="currentColor"></path>
                                        </svg>
    
                                        <CreditCard v-if="tab === 'card'" :size="32" />
                                        <Cross v-if="tab === 'card:cybersource-unified'" :size="32" />
    
                                        <PaddleLogo v-if="tab === 'paddle'"  :svg_height="30" />
                                        <!-- <img v-if="tab === 'paddle'" alt="Paddle logo" loading="lazy" width="105" height="32" decoding="async" data-nimg="1" style="color:transparent" src="https://www.paddle.com/_next/static/media/logo-primary.1520b460.svg"> -->

                                        <!-- <PaddleLogo v-if="tab === 'paddle'"  :svg_height="30" >
                                            <img alt="Paddle logo" loading="lazy" width="105" height="32" decoding="async" data-nimg="1" style="color:transparent" src="https://www.paddle.com/_next/static/media/logo-primary.1520b460.svg">
                                        </PaddleLogo> -->
                                    </label>
                                    <span class="text-lg text-neutral-700 dark:text-neutral-300">{{ tab }}</span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="px-2 flex flex-col gap-5">
                    <h1 class="text-2xl font-bold">Items</h1>

                    <template v-for="sci in cart_info.items" :index="sci.id">
                        <div class="px-2 py-2 flex flex-col gap-4" >
                            <div class="ms-card ms-fill flex gap-4">
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
                                    <img 
                                        loading="lazy" 
                                        :src="sci.thumbnail" 
                                        class="
                                            [--card-padding:0.2rem] 
                                            [--card-radius:1rem] 
                                            aspect-5/6 
                                            rounded-[calc(var(--card-radius)-var(--card-padding))]
                                            bg-gray-800 
                                            object-cover 
                                            shadow-2xl
                                            outline 
                                            outline-white/10 
                                            -outline-offset-1
                                        "
                                    >
                                </div>
    
                                <div class="mt-2 flex flex-col gap-2">
                                    <h2 class="font-semibold text-xl dark:text-white">{{ sci.name }}</h2>
    
                                    <h2 class="font-bold text-xl dark:text-white">Total: {{ formatPrice(sci.unit_amount.currency_code, Number(sci.unit_amount.total_formatted_value)) }}</h2>
                                    <h2 class="font-bold text-xl dark:text-white">Quantity: {{ sci.quantity }}</h2>
                                </div>
                            </div>
                        </div>
                    </template>


                </div>
            </div>

            <div class="flex w-full mx-auto justify-center">
                <div  class="max-w-[calc(100%-40%)] w-full pt-6">

                    <div v-show="savedPaymentMethodSelected === true">
                        <article v-show="selectedSavedPaymentMethod?.payment_provider === 'paypal'" class="">

                            <button @click="completePayment" :disbaled="payment_initiated" :class="[{'opacity-25 cursor-not-allowed': payment_initiated }]"
                                class="bg-[#0070BB] w-full rounded-sm items-center justify-center cursor-pointer px-4 py-2.25 flex flex-1 gap-4"
                            >
                                <span v-if="payment_initiated" class="loader"></span>
                                <span v-else class="text-white">Continue with </span>
                                <!-- <img  width="64" src="https://1000logos.net/wp-content/uploads/2017/05/Paypal-Logo-2022-500x281.png"> -->
                                <img class="" width="75" alt="Paypal Logo" src="/assets/paypal-logo-white.png">
                            </button>
                        </article>

                    </div>

                    <div v-show="savedPaymentMethodSelected === false">
                        <article v-show="currentTab === 'paypal'" class="">

                            <button @click="initiatePayment('paypal')" :disbaled="payment_initiated" :class="[{'opacity-25 cursor-not-allowed': payment_initiated }]"
                                class="bg-[#FFC439] w-full rounded-sm items-center justify-center cursor-pointer px-4 py-2.25 flex flex-1 gap-4"
                            >
                                <span v-if="payment_initiated" class="loader"></span>
                                <!-- <img  width="64" src="https://1000logos.net/wp-content/uploads/2017/05/Paypal-Logo-2022-500x281.png"> -->
                                <img class="py-0.25" alt="Paypal Logo" src="https://www.paypalobjects.com/webstatic/mktg/Logo/pp-logo-100px.png">
                            </button>
                        </article>

                        <article v-show="currentTab === 'paypaljs'" >
                            <div id="content" class="hide">
                                <div id="paypal-button-container"></div>

                                <div class="flex items-center space-x-2 mt-4">
                                    <Checkbox class="border-neutral-800 dark:border-neutral-200" @update:model-value="(value: boolean | 'indeterminate') => {
                                        if (typeof value === 'boolean') {
                                            save_token = value;
                                        }

                                        if (typeof value === 'string' && value === 'indeterminate') {
                                            save_token = false;
                                        }
                                    }" id="terms" />
                                    <label
                                        for="terms"
                                        class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                    >
                                    Save payment method for future purchases
                                    </label>
                                </div>
                            </div>
                        
                            <div id="alerts" class="ms-text-center pt-6"></div>

                            <div id="loading" class="spinner-container ms-div-center grid place-items-center">
                                <div class="spinner"></div>
                            </div>
                        </article>

                        <article v-show="currentTab === 'polar'" class="">

                            <button @click="initiatePayment('polar')" :disbaled="payment_initiated" :class="[{'opacity-25 cursor-not-allowed': payment_initiated }]"
                                class="bg-[#181818] hover:bg-[#0f0f0f] text-white shadow-sm shadow-white/5 w-full rounded-sm items-center justify-center cursor-pointer px-4 py-2.25 flex flex-1 gap-4"
                            >
                                <span v-if="payment_initiated" class="loader"></span>
                                <!-- <img  width="64" src="https://1000logos.net/wp-content/uploads/2017/05/Paypal-Logo-2022-500x281.png"> -->
                                <svg width="32" height="32" viewBox="0 0 29 29" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-white dark:text-white">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9.07727 23.0572C13.8782 26.307 20.4046 25.0496 23.6545 20.2487C26.9043 15.4478 25.6469 8.92133 20.846 5.67149C16.0451 2.42165 9.51862 3.67905 6.26878 8.47998C3.01894 13.2809 4.27634 19.8073 9.07727 23.0572ZM10.4703 23.1428C14.862 25.3897 20.433 23.2807 22.9135 18.4322C25.394 13.5838 23.8447 7.83194 19.4531 5.58511C15.0614 3.33829 9.49042 5.4473 7.00991 10.2957C4.52939 15.1442 6.07867 20.896 10.4703 23.1428Z" fill="currentColor"></path>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M11.7222 24.2898C15.6865 25.58 20.35 22.1715 22.1385 16.6765C23.927 11.1815 22.1632 5.68099 18.1989 4.39071C14.2346 3.10043 9.5711 6.509 7.78261 12.004C5.99412 17.4989 7.75793 22.9995 11.7222 24.2898ZM12.9347 23.872C16.2897 24.5876 19.9174 20.9108 21.0374 15.6596C22.1574 10.4084 20.3457 5.57134 16.9907 4.85575C13.6357 4.14016 10.008 7.817 8.88797 13.0682C7.76793 18.3194 9.57971 23.1564 12.9347 23.872Z" fill="currentColor"></path>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M13.8537 24.7382C16.5062 25.0215 19.1534 20.5972 19.7664 14.8563C20.3794 9.1155 18.7261 4.23202 16.0736 3.94879C13.4211 3.66556 10.7739 8.08983 10.1609 13.8307C9.54788 19.5715 11.2012 24.455 13.8537 24.7382ZM15.0953 22.9906C17.015 22.9603 18.5101 19.0742 18.4349 14.3108C18.3596 9.54747 16.7424 5.71058 14.8228 5.7409C12.9032 5.77123 11.408 9.6573 11.4833 14.4207C11.5585 19.184 13.1757 23.0209 15.0953 22.9906Z" fill="currentColor"></path>
                                </svg>
                            </button>
                        </article>

                        <article v-show="currentTab === 'paddle'" class="">

                            <button @click="initiatePayment('paddle')" :disbaled="payment_initiated" :class="[{'opacity-25 cursor-not-allowed': payment_initiated }]"
                                class="bg-[#171C17] hover:bg-[#21261B] shadow-sm shadow-white/5 w-full rounded-sm items-center justify-center cursor-pointer px-4 py-2.25 flex flex-1 gap-4"
                            >
                                <span v-if="payment_initiated" class="loader"></span>
                                <img alt="Paddle logo" loading="lazy" width="105" height="32" decoding="async" data-nimg="1" style="color:transparent" src="https://www.paddle.com/_next/static/media/paddle-logo-inverted.02kelud1d95as.svg">
                            </button>
                        </article>

                        <article v-show="currentTab === 'card'" class="">
                            <PaymentCard :initiate-payment="initiatePayment"/>
                        </article>

                        <article v-show="currentTab === 'card:cybersource-unified'" class="">
                            <CybersourceUnified :initiate-payment="initiatePayment"/>
                        </article>
                    </div>
                    

                    
                </div>
            </div>
        </div>
    </div>

</template>

<style scoped>

input[type="radio"] {
    height: 1.1rem;
    width: 1.1rem;
    margin-right: 0.5rem;
    outline: 1px solid #999;
}


.custom-radio {
    /* hiding browser el */
    appearance: none;
    /* Safari support */
    -webkit-appearance: none;
    border: 0.2rem solid #fff;
    background-color: none;
    border-radius: 50%;
}

:where(html.dark) input[type="radio"]  {
    outline: none;
    background-color: white;
} 

.custom-radio:checked {
    background-color: #3b82f6 ;
}

/* keyboard navigation focus appearance */
.custom-radio:focus-visible {
    outline-offset: 0;
}

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