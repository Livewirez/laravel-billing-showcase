<template>
    <div>
        <svg role="img" class="text-black dark:text-white" viewBox="-.02682843 0 123.63183286 30.17842908" :height="svg_height" :width="svg_width" xmlns="http://www.w3.org/2000/svg">
            <title>Paddle</title>
            <path fill="currentColor" d="M2.363 7.904v.849a3.95 3.95 0 0 1 3.65 2.425c.198.476.3.987.299 1.502h.791c0-1.04.416-2.037 1.157-2.772a3.962 3.962 0 0 1 2.792-1.149V7.91a3.959 3.959 0 0 1-3.65-2.425 3.893 3.893 0 0 1-.299-1.502h-.791c0 1.04-.416 2.037-1.157 2.772a3.96 3.96 0 0 1-2.792 1.149M13.105 2.51H6.312V0h6.793c4.772 0 8.532 3.735 8.532 8.314 0 4.58-3.76 8.314-8.532 8.314H9.156V24H6.312v-9.882h6.793c3.319 0 5.688-2.352 5.688-5.804 0-3.451-2.37-5.804-5.688-5.804"/>
        </svg>
    </div>
</template>


<script setup lang="ts">

import { ref, onMounted, watch } from 'vue'


interface Dimensions {
    svg_height?: number
    svg_width?: number
}

const { svg_height = 120, svg_width = 120 } = defineProps<Dimensions>()

const container = ref<HTMLElement | null>(null)
const svg = ref<SVGSVGElement | null>(null)

onMounted(async () => {
    // const res = await fetch('https://www.paddle.com/_next/static/media/logo-primary.1520b460.svg')
    // const text = await res.text()

    // const parser = new DOMParser()
    // const doc = parser.parseFromString(text, 'image/svg+xml')
    // svg.value = doc.querySelector('svg')

    // if (svg.value && container.value) {
    //     svg.value.setAttribute('height', svg_height.toString())
    //     svg.value.setAttribute('width', svg_width.toString())

    //     container.value.appendChild(svg.value) // ✅ now it's a real HTMLSVGElement
    // }
})

// watch([() => svg_height, () => svg_width], ([newHeight, newWidth]) => {
//     if (svg.value) {
//         svg.value.setAttribute('height', newHeight.toString())
//         svg.value.setAttribute('width', newWidth.toString())
//     }
// })



</script>