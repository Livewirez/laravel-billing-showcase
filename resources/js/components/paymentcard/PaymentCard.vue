<template>

    <form @submit.prevent="startPayment" id="cybersource_microform_form" class="flex gap-6 flex-col">
        <article class="grid-container ">
            <section class="cardnumber grid gap-2">
                <!-- <label for="cardnumber">Card Number</label>
                <input id="cardnumber" type="text" pattern="[0-9]*" inputmode="numeric"></input> -->
                <Label for="cardnumber">Card Number</Label>
                <div id="cardnumber" class="cs-field" :class="cn(
                    'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                    'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                )"></div>
            </section>

            <section class="month grid gap-2">
                <!-- <label for="expirationmonth">Month</label>
                <input id="expirationmonth" type="text" pattern="[0-9]*" inputmode="numeric" min="1" max="31"> -->
                <Label for="expirationmonth">Month</Label>
                <Input
                    id="expirationmonth"
                    type="number"
                    min="1"
                    max="12"
                    placeholder="MM"
                    v-model="cardDetails.expirationMonth"
                    required
                    autofocus
                    :tabindex="1"
                /><!-- e.target.value.slice(0, 2)-->
            </section>

            <section class="year grid gap-2">
                <!-- <label for="expirationyear">Year</label>
                <input id="expirationyear" type="text" pattern="[0-9]*" inputmode="numeric" min="1" max="31"> -->
                <Label for="expirationyear">Year</Label>
                <Input 
                    id="expirationyear"
                    type="number"
                    :min="new Date().getFullYear()" 
                    max="2100"
                    placeholder="YYYY"
                    v-model="cardDetails.expirationYear"
                    required
                    autofocus
                    :tabindex="1"
                /> <!-- e.target.value.slice(0, 4)-->
            </section>

            <section class="cvv grid gap-2">
                <!-- <label for="securitycode">Security Code</label>
                <input id="securitycode" type="text" pattern="[0-9]*" inputmode="numeric"> -->
                <Label for="securitycode">Security Code</Label>
                <div id="securitycode" class="cs-field" :class="cn(
                    'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                    'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                )"
                ></div>
            </section>

        </article>

        <article>
            <!-- @click="initiatePayment('polar')" :disbaled="payment_initiated" :class="[{'opacity-25 cursor-not-allowed': payment_initiated }]" -->
            <button type="submit" :disbaled="payment_initiated" :class="[{'opacity-25 cursor-not-allowed': payment_initiated }]" class="text-xl text-white bg-[#0070BB] hover:bg-[#0070BB]/50 shadow-sm shadow-white/5 w-full rounded-sm items-center justify-center cursor-pointer px-4 py-2.25 flex flex-1 gap-4"
            >
                <span v-if="payment_initiated" class="loader"></span>
                <CreditCard :size="32" /> Pay
            </button>
            <div v-if="payment_initiated" class="text-black dark:text-white">Please Wait....</div>
        </article>
    </form>

</template>

<script setup lang="ts">
import { onMounted, ref, onBeforeMount, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { cn } from '@/lib/utils'
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { CreditCard, CreditCardIcon } from "lucide-vue-next";
import { JWT } from '@/lib/utils';
import type { PaymentOptions } from '@/lib/orders';

import { HttpError, HttpRequest, HttpResponse } from '@/lib/utils';

import { setup_cybersource_session } from '@/routes/api/orders';
import { complete_order } from '@/routes/payment';

const { initiatePayment } = defineProps<{
    initiatePayment: (provider: string, options?: PaymentOptions) => Promise<void | HttpResponse>
    // initiatePayment: (provider: string, options?: PaymentOptions) => Promise<void | AxiosResponse<any, any, {}>>;
}>();

const cardDetails = ref({
    expirationMonth: '',
    expirationYear: ''
})

const token = ref<string|null>(null);
const libraryUrl = ref<string|null>(null);
const integrity = ref<string|null>(null);
const clientScript = ref<HTMLScriptElement | null>(null);
const microform = ref<any>(null);
const payment_initiated = ref(false)


onBeforeMount(async () => {
    await HttpRequest.post(setup_cybersource_session.url())
    .then(res => {
        console.log('CybersourceToken', res)
       
        token.value = res.data.token;
        console.log('Parsed JWT', JWT.parse(res.data.token));
        const clientLibrary: Record<string, any> = JWT.parse(res.data.token).ctx[0].data;
        console.log('Client Library', clientLibrary);
        libraryUrl.value = clientLibrary.clientLibrary;
        integrity.value = clientLibrary.clientLibraryIntegrity;
        console.log({ libUrl :libraryUrl.value, libIntegrity: integrity.value })

        createForm()
        
    }).catch(error => {
        console.error('Error: ', error.response, error.response?.data)
    })
})

const createForm = () => {

    if (libraryUrl.value && integrity.value) {
        console.log('Creating client script')

        clientScript.value = document.createElement("script");
        clientScript.value.src = libraryUrl.value;
        clientScript.value.type = "text/javascript";
        clientScript.value.async = true;
        clientScript.value.integrity = integrity.value;
        clientScript.value.crossOrigin = "anonymous";
        // run setup code after script has loaded successfully
        clientScript.value.onload = function () {

            const myStyles = {  
                'input': {    
                    'font-size': '14px',     
                    'color': '#555'  
                },  
                ':focus': { 'color': 'blue' },  
                ':disabled': { 'cursor': 'not-allowed' },  
                'valid': { 'color': '#3c763d' },  
                'invalid': { 'color': '#a94442' }
            };

            const flex = new (window as any).Flex(token.value);
            console.log('Flex', flex)
            microform.value = flex.microform("card", { styles: myStyles });
            console.log('Microform', microform.value)

            console.log('Flex Microform', { 
                flex: flex, microform: microform.value
            })

            const number = microform.value.createField('number', { placeholder: 'Enter card number' });
            const securityCode = microform.value.createField('securityCode', { placeholder: '•••' });
            number.load('#cardnumber');
            securityCode.load('#securitycode');
        };
        // insert to document
        document.head.appendChild(clientScript.value);

    }
}

const startPayment = async () => {

    if (microform.value) {

        payment_initiated.value = true

        const params = {
            expirationMonth: String(cardDetails.value.expirationMonth).padStart(2, "0"),
            expirationYear: String(cardDetails.value.expirationYear),
        }

        microform.value.createToken(params, async (error: any, token?: string) => {

            if (error) {
                console.error('Cybersource Start Payment Microform Error: ', error)

                switch (error.reason) {
                    case 'CREATE_TOKEN_NO_FIELDS_LOADED':
                        break;
                    case 'CREATE_TOKEN_TIMEOUT':
                        break;
                    case 'CREATE_TOKEN_NO_FIELDS':
                        break;
                    case 'CREATE_TOKEN_VALIDATION_PARAMS':
                        break;
                    case 'CREATE_TOKEN_VALIDATION_FIELDS':
                        break;
                    case 'CREATE_TOKEN_VALIDATION_SERVERSIDE':
                        break;
                    case 'CREATE_TOKEN_UNABLE_TO_START':
                        break;
                    default:
                        console.error('Unknown error');
                        break;
                }

                payment_initiated.value = false
            } else {
                await initiatePayment('card', { card_gateway: 'cybersource', token: token })
                .then(res => {
                    const response = res as HttpResponse;

                    switch(response.data.status) {
                        case 'pending':
                        case 'created':
                        case 'approved':

                            const completeOrderData =  {
                                provider: 'card', 
                                card_gateway: 'cybersource',
                                token: token,
                            }

                            const completeOrderURL = complete_order({
                                query: completeOrderData
                            })
                            
                            router.get(completeOrderURL, completeOrderData, {
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
                            break
                        case 'failed':
                            break;
                        default:
                            break;
                    }

                }).catch(error => {
                    console.error('Cybeersource Card Start Payment Error: ', error.response, error.response?.data)

                    payment_initiated.value = false
                })        
            }
        })
    }


}

</script>

<style scoped>

.grid-container {
    display: grid;
    grid-template-columns: auto-fit;
    grid-template-rows: auto auto;
    grid-template-areas:
        "cardnumber cardnumber cardnumber cardnumber"
        "month year year cvv";
    gap: 1rem;
}

.cardnumber {
    grid-area: cardnumber;
}

.month {
    grid-area: month;
}

.year {
    grid-area: year;
}

.cvv {
    grid-area: cvv;
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



