<script setup lang="ts">
import { onMounted, onUnmounted, shallowRef, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { type Product as ProductType } from '@/lib/products';
import { imagePlaceholder } from '@/lib/image';
import { defaultFormatter } from '@/lib/utils';

import { store } from '@/routes';
import { product as productRoute } from '@/routes/store';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Store',
        href: store.url(),
    },
];

const props = defineProps<{
    products: ProductType[];
}>();

function formatPrice(currency: string, amount: number | bigint | Intl.StringNumericLiteral) 
{
    return defaultFormatter(currency, amount);
}

const intersection_observer = ref<IntersectionObserver | null>(null);

onMounted(() => {
    // const lazyImgs = document.querySelectorAll('.lazy') as NodeListOf<HTMLImageElement>;

    // intersection_observer.value = new IntersectionObserver((entries, observer) => {
    //     entries.forEach(entry => {
    //         if(entry.isIntersecting) {
    //             let img = entry.target as HTMLImageElement;
    //             img.src = img.dataset.src || imagePlaceholder;
    //             img.classList.remove('loading');
    //             img.classList.add('loaded');
    //             observer.unobserve(img);
    //         }
    //     });
    // });

    // lazyImgs.forEach(img => {
    //     if (intersection_observer.value) intersection_observer.value.observe(img);
    // });

    console.log('Products', props.products)
})

onUnmounted(() => {
    // Disconnect the observer to prevent memory leaks
    if (intersection_observer.value) {
        intersection_observer.value.disconnect();
    }
});

</script>


<template>
    <Head title="Store" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
            <div class="">
                <div class=" max-w-2xl px-4 py-4 sm:px-6 sm:py-6 lg:max-w-7xl lg:px-8">
                    <h2 class="lg:text-4xl text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Products</h2>
    
                    <div class="mt-6 grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4 xl:gap-x-8">
                        <template v-for="product in products" :key="product.id">
                            <div  class="group relative">
                                
                                <img 
                                    :src="product.thumbnail"
                                    :alt="product.name" 
                                    class="imglzy loading aspect-square w-full rounded-md bg-gray-200 object-cover group-hover:opacity-75 lg:aspect-auto lg:h-52" 
                                    @load="(e: Event) => {
                                        const target = e.target as HTMLImageElement;
    
                                        if (target) {
                                            target.classList.remove('loading');
                                            target.classList.add('loaded');
                                        }
                                    }" 
                                />
    
                                <div class="mt-4 flex justify-between">
                                    <div>
                                        <h3 class="text-sm text-gray-700 dark:text-gray-300">
                                            <Link :href="productRoute({ product: product.id })" prefetch="hover" :cacheFor="['20m']" >
                                                <span aria-hidden="true" class="absolute inset-0" />
                                                    {{ product.name }}
                                            </Link>
                                        </h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 truncate">{{ product.colour }}</p>
                                    </div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ formatPrice(product.currency, product.formatted_price) }}</p>
                                </div>
                            </div>
                        </template>
                    </div>
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


img.imglzy:hover {
    scale: 0.95;
    filter: grayscale(100%); /* Applies a grayscale filter */
}
</style>