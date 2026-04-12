<template>
    <!-- https://worldvectorlogo.com/downloaded/paypal-3 -->
    <div>
        <img src="/logos/mpesa_sm.png" class="" :width="width" :height="height" loading="lazy" >
    </div>

</template>

<script setup lang="ts">

interface Dimensions {
    height: number
    width: number
}

const { height = 150, width = 150 } = defineProps<Dimensions>()


</script>