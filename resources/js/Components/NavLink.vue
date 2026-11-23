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
        default: false,
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
        ? 'flex items-center px-3 py-2 text-sm font-bold rounded-lg bg-white/20 text-white shadow-xs border border-white/25 transition-colors duration-150 ease-in-out cursor-pointer select-none'
        : 'flex items-center px-3 py-2 text-sm font-medium text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-colors duration-150 ease-in-out cursor-pointer select-none',
);
</script>

<template>
    <Link :href="href" :prefetch="prefetch" :cache-for="cacheFor" :class="classes">
        <slot />
    </Link>
</template>
