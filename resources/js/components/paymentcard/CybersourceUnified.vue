<template>

   <div>
        <div v-if="loading" id="spinner" class="spinner-container">
            <div class="spinner"></div>
        </div>
        <section v-else>
            <div id="buttonPaymentListContainer"></div>
            <div id="embeddedPaymentContainer"></div>
            <p v-if="errors" class="text-red-500">{{ errors }}</p>
        </section>
   </div>

</template>

<script setup lang="ts">
import { onMounted, ref, onBeforeMount, watch, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { cn } from '@/lib/utils'
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { CreditCard, CreditCardIcon } from "lucide-vue-next";
import { JWT } from '@/lib/utils';
import type { PaymentOptions } from '@/lib/orders';

import { HttpError, HttpRequest, HttpResponse } from '@/lib/utils';

import { complete_order } from '@/routes/payment';

const { initiatePayment } = defineProps<{
    initiatePayment: (provider: string, options?: PaymentOptions) => Promise<void | HttpResponse>
    // initiatePayment: (provider: string, options?: PaymentOptions) => Promise<void | AxiosResponse<any, any, {}>>;
}>();

const loading = ref<bool>(true);
const token = ref<string|null>(null);
const libraryUrl = ref<string|null>(null);
const integrity = ref<string|null>(null);
const clientScript = ref<HTMLScriptElement | null>(null);
const payment_initiated = ref(false);
const controller = new AbortController();
const errors = ref<string|null>(null)

onBeforeMount(async () => {
    await initiatePayment('card', { card_gateway: 'cybersource_unified_checkout', card_gateway_phase: 1 })
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

onUnmounted(() => {
    if (controller) {
        controller.abort()
    }
})

const createForm = () => {

    if (libraryUrl.value && integrity.value) {
        console.log('Creating Unified Checkout Client Script')

        clientScript.value = document.createElement("script");
        clientScript.value.src = libraryUrl.value;
        clientScript.value.type = "text/javascript";
        clientScript.value.async = true;
        clientScript.value.integrity = integrity.value;
        clientScript.value.crossOrigin = "anonymous";
        // run setup code after script has loaded successfully
        clientScript.value.onload = async function (ev: Event) {
            console.log('JS Load OK', ev)
            loading.value = false
            const showArgs = {
                containers: {
                    paymentSelection: "#buttonPaymentListContainer", 
                    paymentScreen: "#embeddedPaymentContainer", 
                }
            }

            try {
                const accept = await window.Accept(token.value);
                const up = await accept.unifiedPayments(false);
                const tt = await up.show(showArgs);
                const completeResponse = await up.complete(tt);
                let paymentResponse = JWT.parse(completeResponse);
                console.log('[Payment Response]: ', paymentResponse)
                /**{
                    "details": {
                        "clientReferenceInformation": {
                            "code": "ORD-2026-1227120F04F0B631"
                        },
                        "consumerAuthenticationInformation": {
                            "eci": "05",
                            "ecommerceIndicator": "vbv"
                        },
                        "errorInformation": {
                            "reason": "DECISION_PROFILE_REVIEW"
                        },
                        "orderInformation": {
                            "amountDetails": {
                                "authorizedAmount": "29.00",
                                "currency": "USD"
                            }
                        },
                        "paymentInformation": {
                            "card": {
                                "type": "VISA"
                            }
                        },
                        "processorInformation": {
                            "approvalCode": "831000",
                            "networkTransactionId": "016150703802094",
                            "responseCode": "00",
                            "transactionId": "016150703802094"
                        },
                        "reconciliationId": "1BUITV5M6HH1",
                        "submitTimeUtc": "2026-10-15T22:02:52Z"
                    },
                    "id": "7921017717926704904009",
                    "message": "The order is marked for review by Decision Manager",
                    "outcome": "AUTHORIZED_PENDING_REVIEW",
                    "status": "AUTHORIZED_PENDING_REVIEW"
                } */
                if (['AUTHORIZED', 'AUTHORIZED_PENDING_REVIEW'].includes(paymentResponse.status)) {
                    await initiatePayment('card', { card_gateway: 'cybersource_unified_checkout', card_gateway_phase: 2, token: paymentResponse.id })
                    .then(res => {
                        const response = res as HttpResponse;

                        switch(response.data.status) {
                            case 'pending':
                            case 'created':
                            case 'approved':

                                const completeOrderData =  {
                                    provider: 'card', 
                                    card_gateway: 'cybersource_unified_checkout',
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
                        console.error('Cybersource Unified Checkout Card Start Payment Error: ', error.response, error.response?.data)

                        payment_initiated.value = false
                    })     
                } else {
                    errors.value = 'Payment Declined, please try another card or error occurred'
                }

            } catch (error) {
                 console.error('[Something went wrong]', error)
            }
            
        };
        // insert to document
        document.head.appendChild(clientScript.value);

    }
}

</script>

<style scoped>

.spinner-container {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 20px;
}

.spinner {
  width: 50px;
  height: 50px;
  border: 5px solid #f3f3f3; /* Light grey background track */
  border-top: 5px solid #3498db; /* Blue rotating edge */
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Utility class to hide the spinner */
.hidden {
  display: none !important;
}
</style>



