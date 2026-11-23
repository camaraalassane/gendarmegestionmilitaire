<script setup>
</script>

<template>
    <div class="space-y-6 sm:space-y-8 animate-pulse">
        <!-- 1. AXE A : 5 CARTES EFFECTIF EN ACTIVITÉ -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <div class="h-3.5 w-48 bg-slate-300 rounded"></div>
                    <div class="h-2.5 w-72 bg-slate-200 rounded"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div v-for="i in 5" :key="'axe-a-' + i" class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="h-3 w-24 bg-slate-200 rounded"></div>
                        <div class="w-8 h-8 rounded-full bg-slate-100"></div>
                    </div>
                    <div class="h-8 w-20 bg-slate-200 rounded-md"></div>
                    <div class="h-2.5 w-28 bg-slate-100 rounded"></div>
                </div>
            </div>
        </div>

        <!-- 2. AXE B : 4 CARTES SITUATION ADMINISTRATIVE & CARRIÈRE -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <div class="h-3.5 w-48 bg-slate-300 rounded"></div>
                    <div class="h-2.5 w-64 bg-slate-200 rounded"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div v-for="i in 4" :key="'axe-b-' + i" class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="h-3 w-28 bg-slate-200 rounded"></div>
                        <div class="w-8 h-8 rounded-full bg-slate-100"></div>
                    </div>
                    <div class="h-8 w-16 bg-slate-200 rounded-md"></div>
                    <div class="h-2.5 w-32 bg-slate-100 rounded"></div>
                </div>
            </div>
        </div>

        <!-- 3. DEUX GRANDS GRAPHIQUES (Positions & Régions) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Graphique 1 -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div class="h-4 w-44 bg-slate-300 rounded"></div>
                    <div class="h-3 w-20 bg-slate-200 rounded"></div>
                </div>
                <div class="h-80 bg-slate-50 rounded-xl p-4 flex flex-col justify-between">
                    <div v-for="j in 8" :key="'bar-a-' + j" class="h-5 bg-slate-200 rounded" :style="{ width: (25 + (j * 17) % 70) + '%' }"></div>
                </div>
            </div>

            <!-- Graphique 2 -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div class="h-4 w-44 bg-slate-300 rounded"></div>
                    <div class="h-3 w-20 bg-slate-200 rounded"></div>
                </div>
                <div class="h-80 bg-slate-50 rounded-xl p-4 flex flex-col justify-between">
                    <div v-for="k in 8" :key="'bar-b-' + k" class="h-5 bg-slate-200 rounded" :style="{ width: (30 + (k * 13) % 65) + '%' }"></div>
                </div>
            </div>
        </div>

        <!-- 4. PYRAMIDE DES CATÉGORIES & RÉPARTITION PAR GRADE -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs space-y-4">
                <div class="h-4 w-40 bg-slate-300 rounded"></div>
                <div class="h-64 flex items-center justify-center">
                    <div class="w-44 h-44 rounded-full border-8 border-slate-200 border-t-slate-300"></div>
                </div>
            </div>

            <div class="lg:col-span-2 bg-white rounded-2xl p-5 border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div class="h-4 w-48 bg-slate-300 rounded"></div>
                    <div class="h-3 w-24 bg-slate-200 rounded"></div>
                </div>
                <div class="h-64 bg-slate-50 rounded-xl p-4 flex items-end justify-between gap-2">
                    <div v-for="m in 14" :key="'grade-b-' + m" class="bg-slate-200 rounded-t w-full" :style="{ height: (15 + (m * 11) % 80) + '%' }"></div>
                </div>
            </div>
        </div>
    </div>
</template>
