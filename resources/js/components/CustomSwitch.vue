<script setup lang="ts">
import { ref } from 'vue';

const emit = defineEmits(['toggled'])

const { condition = false} = defineProps<{
    condition: boolean
}>()
</script>

<template>
    <button
        @click="$emit('toggled', !condition)"
        :class="[
            'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-theme-primary disabled:cursor-not-allowed disabled:bg-gray-100',
            condition ? 'bg-theme-primary' : 'bg-gray-300 dark:bg-gray-700'
        ]"
        role="switch"
        type="button"
        :aria-checked="condition"
    >
        <span
        :class="[
            'inline-block h-5 w-5 transform rounded-full shadow transition duration-200 ease-in-out bg-white dark:bg-gray-100',
            condition ? 'translate-x-5' : 'translate-x-0'
        ]"
        ></span>
    </button>
</template>