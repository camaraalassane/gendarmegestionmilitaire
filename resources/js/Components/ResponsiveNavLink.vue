<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
    prefetch: {
        type: [Boolean, String],
        default: 'hover',
    },
    cacheFor: {
        type: [String, Number],
        default: '30s',
    },
});

const classes = computed(() =>
    props.active
        ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-sky-600 text-start text-base font-medium text-sky-800 bg-sky-50 focus:outline-none focus:text-sky-900 focus:bg-sky-100 focus:border-sky-700 transition duration-150 ease-in-out'
        : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:text-gray-800 focus:bg-gray-50 focus:border-gray-300 transition duration-150 ease-in-out',
);
</script>

<template>
    <Link :href="href" :prefetch="prefetch" :cache-for="cacheFor" :class="classes">
        <slot />
    </Link>
</template>
