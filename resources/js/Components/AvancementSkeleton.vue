<script setup>
defineProps({
    rows: {
        type: Number,
        default: 10
    }
});
</script>

<template>
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden animate-pulse">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <div class="h-4 w-48 bg-slate-200 rounded"></div>
            <div class="h-4 w-32 bg-slate-100 rounded"></div>
        </div>
        <div class="w-full overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="p-3 w-12"><div class="h-3 w-6 bg-slate-300 rounded"></div></th>
                        <th class="p-3 w-28"><div class="h-3 w-20 bg-slate-300 rounded"></div></th>
                        <th class="p-3 w-48"><div class="h-3 w-36 bg-slate-300 rounded"></div></th>
                        <th class="p-3 w-28"><div class="h-3 w-20 bg-slate-300 rounded"></div></th>
                        <th class="p-3 w-24"><div class="h-3 w-16 bg-slate-300 rounded"></div></th>
                        <th class="p-3 w-24"><div class="h-3 w-16 bg-slate-300 rounded"></div></th>
                        <th class="p-3 w-24"><div class="h-3 w-16 bg-slate-300 rounded"></div></th>
                        <th class="p-3 w-20"><div class="h-3 w-12 bg-slate-300 rounded"></div></th>
                        <th class="p-3 w-16"><div class="h-3 w-8 bg-slate-300 rounded"></div></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="i in rows" :key="'skel-' + i" class="hover:bg-slate-50/50">
                        <td class="p-3"><div class="h-3.5 w-6 bg-slate-200 rounded"></div></td>
                        <td class="p-3"><div class="h-3.5 w-24 bg-slate-200 rounded font-mono"></div></td>
                        <td class="p-3">
                            <div class="space-y-1">
                                <div class="h-3.5 w-32 bg-slate-300 rounded"></div>
                                <div class="h-2.5 w-24 bg-slate-100 rounded"></div>
                            </div>
                        </td>
                        <td class="p-3"><div class="h-5 w-20 bg-sky-100 rounded-full"></div></td>
                        <td class="p-3"><div class="h-3.5 w-16 bg-slate-200 rounded"></div></td>
                        <td class="p-3"><div class="h-3.5 w-16 bg-slate-200 rounded"></div></td>
                        <td class="p-3"><div class="h-3.5 w-16 bg-slate-200 rounded"></div></td>
                        <td class="p-3"><div class="h-5 w-14 bg-emerald-100 rounded-md"></div></td>
                        <td class="p-3"><div class="h-6 w-8 bg-slate-200 rounded-full"></div></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="p-3 border-t border-slate-100 flex items-center justify-between">
            <div class="h-3 w-36 bg-slate-200 rounded"></div>
            <div class="flex gap-1">
                <div v-for="p in 5" :key="'p-' + p" class="h-7 w-7 bg-slate-100 rounded"></div>
            </div>
        </div>
    </div>
</template>
