<script setup lang="ts">
import { onMounted, onUnmounted, shallowRef, computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { type CartItem } from  '@/lib/store_items';
import { type Product as ProductType } from '@/lib/products';
import { Button } from '@/components/ui/button';
import { imagePlaceholder } from '@/lib/image';

import { store as storeRoute } from '@/routes';
import { product as productRoute } from '@/routes/store';
import { checkout as checkoutRoute } from '@/routes/store/cart';
import { buy_now as buyNowRoute } from '@/routes/store/product';
import { update as updateCartRoute } from '@/routes/api/cart'

import { HttpError, HttpRequest, HttpResponse } from '@/lib/utils';

import { useCart } from '@/stores/cart';
import { storeToRefs } from 'pinia';
const useShoppingCart = useCart();
const { cart } = storeToRefs(useShoppingCart);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Store',
        href: storeRoute.url(),
    },
];

const props = defineProps<{
    product: ProductType;
}>();

const formattedPrice = computed(
    () => new Intl.NumberFormat("en", { style: "currency", currency: props.product.currency }).format(props.product.formatted_price)
)

const intersection_observer = ref<IntersectionObserver | null>(null);

const count = ref(1)
const isSaving = ref(false)
const saveTimeout = ref<number | null>(null)

watch(count, (nv, ov) => {
    if (nv < 1) {
        nv = 1
    }
})

const addToCart = (e: PointerEvent) => {
    useShoppingCart.addProduct(props.product, count.value)
}

// Debounce function
function debounce<T extends (...args: any[]) => void>(func: T, delay: number) {
    let timeout: ReturnType<typeof setTimeout>;

    return function (this: ThisParameterType<T>, ...args: Parameters<T>) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
}


// Watch for changes to the shopping cart and debounce the save operation
watch(() => cart.value, () => {
    // Clear any existing timeout
    if (saveTimeout.value !== null) {
        clearTimeout(saveTimeout.value)
    }
    
    // Set a new timeout to save the cart data
    saveTimeout.value = setTimeout(() => {
        if (!isSaving.value) {
            saveDataServerSide()
        }
    }, 2000) // Wait 2 seconds after the last cart change
}, { deep: true })

const saveDataServerSide_ = async () => {
    // Prevent concurrent saves
    if (isSaving.value) return
    
    try {
        isSaving.value = true
        // save cart server side
        console.log('Saving cart to server...')
        
        // Simulate API call
        await new Promise(resolve => setTimeout(resolve, 300))
        
        console.log('Cart saved successfully')
    } catch (error) {
        console.error('Failed to save cart:', error)
    } finally {
        isSaving.value = false
    }
}

const saveDataServerSide = async () => {
    // Prevent concurrent saves
    if (isSaving.value || cart.value.all().length === 0) return

    isSaving.value = true
    // save cart server side
    console.log('Saving cart to server...', cart.value)

    await HttpRequest.post(updateCartRoute.url(), {
        products: cart.value.all().map((sci: CartItem) => ({ product: sci.product.id, quantity: sci.quantity }))
    })
    .then(response => {
        console.log('Cart saved successfully', response.data)
    })
    .catch(error => {
        console.error('Failed to save cart:', error, error.response)
    })
    .finally(() => {
        isSaving.value = false
    })
}


onMounted(() => {
    const lazyImgs = document.querySelectorAll('.lazy') as NodeListOf<HTMLImageElement>;

    intersection_observer.value = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if(entry.isIntersecting) {
                let img = entry.target as HTMLImageElement;
                img.src = img.dataset.src || imagePlaceholder;
                img.classList.remove('loading');
                img.classList.add('loaded');
                observer.unobserve(img);
            }
        });
    });

    lazyImgs.forEach(img => {
        if (intersection_observer.value) intersection_observer.value.observe(img);
    });
})

onUnmounted(() => {
    // Disconnect the observer to prevent memory leaks
    if (intersection_observer.value) {
        intersection_observer.value.disconnect();
    }

    if (saveTimeout.value !== null) {
        clearTimeout(saveTimeout.value)
    }
});


</script>


<template>
    <Head :title="product.name" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div class="flex h-full   gap-4 ">
            <div class="w-4/6 flex gap-2 relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <div class="px-2 py-2">
                    <div class="
                        ms-card-content 
                        max-w-[500px]
                        w-[500px]
                        xl:w-[500px]
                        bg-white/20 
                        outline -outline-offset-1
                        outline-white/25 
                        [--card-padding:0.1rem] 
                        [--card-radius:1rem] 
                        p-[--card-padding] 
                        rounded-[--card-radius]
                    ">
                        <img 
                            loading="lazy" 
                            :data-src="product.thumbnail"
                            :src="imagePlaceholder" 
                            class=" hidden
                                [--card-padding:0.5rem] 
                                [--card-radius:1rem] 
                                aspect-5/6 
                                rounded-[calc(var(--card-radius)-var(--card-padding))]
                                bg-gray-800 
                                object-cover 
                                shadow-2xl
                                outline 
                                outline-white/10 
                                -outline-offset-1
                                lazy
                            "
                        >

                        <img 
                            loading="lazy" 
                            :src="product.thumbnail"
                            @load="(e: Event) => {
                                const target = e.target as HTMLImageElement;

                                if (target) {
                                    target.classList.remove('loading');
                                    target.classList.add('loaded');                                
                                }
                            }" 
                            class="
                                imglzy
                                [--card-padding:0.5rem] 
                                [--card-radius:1rem] 
                                aspect-5/6 
                                rounded-[calc(var(--card-radius)-var(--card-padding))] 
                                object-cover 
                                shadow-2xl 
                                outline 
                                outline-white/10 
                                -outline-offset-1 
                                lazyimg 
                                loading 
                                bg-gray-800 
                                transition-opacity 
                                duration-300 
                                ease-in-out 
                            "
                        >
                    </div>
                </div>
                <div class="px-2 py-2 flex flex-col gap-4">
                
                    <h1 class="font-bold text-5xl dark:text-white"> 
                        {{ formattedPrice }}
                    </h1>

                    <h2 class="font-semibold text-3xl dark:text-white">{{ product.name }}</h2>

                    <p class="font-semibold text-base dark:text-gray-500">{{ product.description }}</p>

                </div>
            </div>
            <div class="w-2/6 p-2 relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <div class="w-2/3 flex flex-col">

                    <div>
                        <div class="w-full flex flex-col justify-start">
                            <h4 class="font-semibold">Quantity</h4>

                            <div class="flex gap-4 items-center mt-4">
                                <button @click="count--"
                                    class="text-xl text-center font-bold bg-yellow-500 dark:bg-yellow-500 hover:bg-red-600 transition text-white rounded-full w-7 h-7 flex items-center justify-center drop-shadow-xl"
                                >
                                    -
                                </button>
                                <span ref="counter" class="text-xl font-bold">{{ count }}</span>
                                <button @click="count++"
                                    class="text-xl text-center font-bold bg-green-500 dark:bg-green-500 hover:bg-blue-600 transition text-white rounded-full w-7 h-7 flex items-center justify-center drop-shadow-xl"
                                >
                                    +
                                </button>
                            </div>

                        </div>
                    </div>

                    <!--:href="route('store.cart.checkout')"-->
                    <Link v-if="useShoppingCart.all().length > 0"
                        :href="checkoutRoute()"
                        as="button"
                        method="get"
                        aria-label="Checkout" 
                        prefetch="click"
                        class="
                            mt-4 w-full text-white bg-blue-500 hover:bg-blue-600
                            inline-flex py-2 items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0
                        "
                    >
                        Checkout
                    </Link>

                    <Button @click="addToCart" class="mt-4 w-full ">
                        Add to Cart
                    </Button>

                    
                    <!-- route('store.product.buy_now', product.id) -->
                    <Link  
                        :href="buyNowRoute(product.id)"
                        as="button"
                        method="get"
                        aria-label="Buy Now" 
                        prefetch="click" :cacheFor="['20m']" 
                        class="
                            mt-4 w-full text-white bg-blue-400 hover:bg-blue-600 dark:bg-blue-500 dark:hover:bg-blue-700
                            inline-flex py-2 items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0
                        "
                    >
                        Buy Now
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Add this to your global CSS or inside a <style> block */
img.imglzy.loading {
    background: linear-gradient(90deg, #2d2d2d 25%, #3a3a3a 37%, #2d2d2d 63%);
    background-size: 400% 100%;
    animation: shimmer 1.4s ease infinite;
}

img.imglzy {
    opacity: 0;
}
img.imglzy.loaded {
    opacity: 1;
    transition: opacity 0.3s ease-in-out;
}

@keyframes shimmer {
    0% { background-position: -400px 0; }
    100% { background-position: 400px 0; }
}
</style>