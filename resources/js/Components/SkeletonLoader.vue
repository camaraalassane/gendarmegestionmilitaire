<script setup>
defineProps({
    type: {
        type: String,
        default: 'card', // 'metric', 'card', 'table', 'list', 'chart'
    },
    count: {
        type: Number,
        default: 4,
    },
    rows: {
        type: Number,
        default: 5,
    },
    cols: {
        type: Number,
        default: 6,
    },
});
</script>

<template>
    <!-- 1. METRICS / KPI SKELETON -->
    <div v-if="type === 'metric'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 animate-pulse">
        <div
            v-for="i in count"
            :key="'sk-m-' + i"
            class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs space-y-3"
        >
            <div class="flex items-center justify-between">
                <div class="h-3 w-24 bg-slate-200 rounded-md"></div>
                <div class="w-8 h-8 rounded-full bg-slate-200"></div>
            </div>
            <div class="h-8 w-16 bg-slate-300 rounded-lg"></div>
            <div class="h-3 w-32 bg-slate-200 rounded-md"></div>
        </div>
    </div>

    <!-- 2. TABLE SKELETON -->
    <div v-else-if="type === 'table'" class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden animate-pulse">
        <!-- Table Header Skeleton -->
        <div class="bg-slate-50 border-b border-slate-200 px-4 py-3 flex items-center justify-between gap-4">
            <div v-for="c in cols" :key="'sk-th-' + c" class="h-3.5 bg-slate-200 rounded flex-1"></div>
        </div>
        <!-- Table Rows Skeleton -->
        <div class="divide-y divide-slate-100">
            <div
                v-for="r in rows"
                :key="'sk-tr-' + r"
                class="px-4 py-3 flex items-center justify-between gap-4"
            >
                <div class="h-3.5 bg-slate-200 rounded w-16"></div>
                <div class="h-3.5 bg-slate-200 rounded flex-1 max-w-[140px]"></div>
                <div class="h-3.5 bg-slate-200 rounded flex-1 max-w-[120px]"></div>
                <div class="h-3.5 bg-slate-200 rounded w-20"></div>
                <div class="h-3.5 bg-slate-200 rounded w-24"></div>
                <div class="h-3.5 bg-slate-200 rounded w-12"></div>
            </div>
        </div>
    </div>

    <!-- 3. CHART SKELETON -->
    <div v-else-if="type === 'chart'" class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs animate-pulse space-y-4">
        <div class="flex items-center justify-between">
            <div class="h-4 w-40 bg-slate-200 rounded-md"></div>
            <div class="h-3 w-20 bg-slate-200 rounded-md"></div>
        </div>
        <div class="h-64 bg-slate-100 rounded-xl flex items-end justify-between p-4 gap-2">
            <div v-for="b in 10" :key="'bar-' + b" class="bg-slate-200 rounded-t w-full" :style="{ height: (20 + (b * 13) % 75) + '%' }"></div>
        </div>
    </div>

    <!-- 4. LIST SKELETON -->
    <div v-else-if="type === 'list'" class="space-y-2.5 animate-pulse">
        <div
            v-for="i in count"
            :key="'sk-l-' + i"
            class="bg-white rounded-xl p-3 border border-slate-200/80 flex items-center justify-between"
        >
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-slate-200"></div>
                <div class="space-y-1.5">
                    <div class="h-3.5 w-32 bg-slate-200 rounded"></div>
                    <div class="h-2.5 w-20 bg-slate-100 rounded"></div>
                </div>
            </div>
            <div class="h-6 w-16 bg-slate-200 rounded-md"></div>
        </div>
    </div>

    <!-- 5. DEFAULT GENERIC CARD -->
    <div v-else class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs animate-pulse space-y-3">
        <div class="h-4 w-36 bg-slate-200 rounded-md"></div>
        <div class="h-24 bg-slate-100 rounded-xl"></div>
    </div>
</template>
