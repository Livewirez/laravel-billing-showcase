<script lang="ts" setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { onUnmounted, onMounted, ref } from 'vue';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

import { defaultFormatter } from '@/lib/utils';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Subscriptions',
        href: '/subscriptions',
    },
];


function formatPrice(currency: string, amount: number | bigint | Intl.StringNumericLiteral) 
{
    return defaultFormatter(currency, amount);
}

interface Subscription {
    id: number;
    billing_plan_name: string;
    interval: string;
    status: number;
    // currency: string;
    // amount: number; 
    payment_provider: string;
    // formatted_amount: string;
    ends_at: string;
}

// 'id', 'interval', 'plan_name',  'status', 'amount', 'formatted_amount', 'ends_at'
const { subscription } = defineProps<{
    subscription?: Subscription;
}>();


const formatDate = (date: string | Date) => {

    const options  = {
        year: "numeric",
        month: "numeric",
        day: "numeric",
        hour: "numeric",
        minute: "numeric",
        second: "numeric",
        hour12: false,
    };

    if (! (date instanceof Date)) {
        date = new Date(date);
    }

    return new Intl.DateTimeFormat("en-GB", options).format(date)
}


const invoices = [
  {
    invoice: 'INV001',
    paymentStatus: 'Paid',
    totalAmount: '$250.00',
    paymentMethod: 'Credit Card',
  },
  {
    invoice: 'INV002',
    paymentStatus: 'Pending',
    totalAmount: '$150.00',
    paymentMethod: 'PayPal',
  },
  {
    invoice: 'INV003',
    paymentStatus: 'Unpaid',
    totalAmount: '$350.00',
    paymentMethod: 'Bank Transfer',
  },
  {
    invoice: 'INV004',
    paymentStatus: 'Paid',
    totalAmount: '$450.00',
    paymentMethod: 'Credit Card',
  },
  {
    invoice: 'INV005',
    paymentStatus: 'Paid',
    totalAmount: '$550.00',
    paymentMethod: 'PayPal',
  },
  {
    invoice: 'INV006',
    paymentStatus: 'Pending',
    totalAmount: '$200.00',
    paymentMethod: 'Bank Transfer',
  },
  {
    invoice: 'INV007',
    paymentStatus: 'Unpaid',
    totalAmount: '$300.00',
    paymentMethod: 'Credit Card',
  },
]

</script>

<template>
    <Head title="Subscriptions" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
        <div class="relative min-h-[100vh] flex-1  rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
            <article class="flex gap-4 flex-col py-4">
                <h1 class="text-3xl font-bold">Subscriptions</h1>

                <Table>
                    <TableCaption>A list of your recent invoices.</TableCaption>
                    <TableHeader>
                    <TableRow>
                        <TableHead class="w-[100px]">
                            Plan Name
                        </TableHead>
                        <!-- <TableHead>Price</TableHead> -->
                        <TableHead>Current Period End</TableHead>
                        <TableHead>Payment Method</TableHead>
                        <TableHead class="text-right">
                            Status
                        </TableHead>
                    </TableRow>
                    </TableHeader>
                    <TableBody>
                    <TableRow>
                        <TableCell class="font-medium">
                            {{ subscription?.billing_plan_name }}
                        </TableCell>
                        <!-- <TableCell>{{ formatPrice(subscription.currency, Number(subscription.formatted_amount)) }} / {{ subscription.interval === 'YEARLY' ? 'year' : 'month' }}</TableCell> -->
                        <TableCell>{{ subscription?.ends_at ? formatDate(subscription.ends_at) : '' }}</TableCell>
                        <TableCell>{{ subscription?.payment_provider }}</TableCell> <!-- paymentMethod-->
                        <TableCell class="text-right">
                            {{ subscription?.status }}
                        </TableCell>
                    </TableRow>
                    </TableBody>
                </Table>
            </article>
        </div>
    </div>
</template>