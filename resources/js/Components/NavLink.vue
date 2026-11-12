<template>
    <Link :href="href" :class="classes" prefetch>
        <slot />
    </Link>
</template>

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
});

const classes = computed(() =>
    props.active
        ? 'flex items-center px-3 py-2 text-sm font-bold rounded-lg bg-white/20 text-white shadow-xs border border-white/25 transition-colors duration-150 ease-in-out'
        : 'flex items-center px-3 py-2 text-sm font-medium text-white/90 hover:text-white hover:bg-white/10 rounded-lg transition-colors duration-150 ease-in-out',
);
</script>