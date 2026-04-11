<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { dashboard } from '@/routes';
import type { Team } from '@/types';
import { router } from "@inertiajs/vue3";

import { createCyberSourceMicroformSession } from "@/actions/App/Http/Controllers/OrdersController";
import { HttpError, HttpRequest, HttpResponse } from '@/lib/utils';

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const  testJ = async () => {
   
    const j = createCyberSourceMicroformSession()

    console.log('Tst J: ', j)

    // const res = await fetch(createCyberSourceMicroformSession.url(), {
    //     method: 'POST',
    //     headers: {
    //         'Content-Type': 'application/json',
    //     },
    //     body: JSON.stringify({
    //         // Include any necessary data for the session creation
    //     }),
    // });
    // console.log('Test J: ', j);
    // console.log('Test Res: ', res);
    // if (res.ok) {
    //     const data = await res.json();
    //     console.log('Microform Session Created:', data);
    // } else {
    //     console.error('Failed to create microform session:', res.statusText);
    // }

    await HttpRequest.post(createCyberSourceMicroformSession.url(), {
        // Include any necessary data for the session creation
    }).then((res: HttpResponse) => {
        console.log('Microform Session Created:', res.data);
    }).catch((error: HttpError) => {
        console.error('Failed to create microform session:', error);
    });

}
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <button class="bg-emerald-500 px-1.5 py-0.5"  @click="testJ()">Click Me</button>

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                
                <PlaceholderPattern />
            </div>
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
        </div>
        <div
            class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
    </div>
</template>
