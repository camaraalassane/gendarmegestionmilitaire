<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-sky-950 via-sky-900 to-slate-950">
        <!-- Overlay décoratif -->
        <div class="absolute inset-0 bg-black/20"></div>
        
        <!-- Forme décorative en arrière-plan -->
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute -top-40 -right-40 w-80 h-80 bg-white/10 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-white/10 rounded-full blur-3xl"></div>
        </div>
        
        <div class="relative z-10 w-full max-w-md mx-4">
            <!-- Logo et titre -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-white/20 backdrop-blur-sm rounded-full shadow-lg mb-4 p-2">
                    <ApplicationLogo class="w-20 h-20" />
                </div>
                <h2 class="text-white text-2xl font-bold tracking-tight">Gestion Militaire</h2>
                <p class="text-white/80 text-sm mt-1 uppercase font-semibold">Gendarmerie Nationale du Mali</p>
            </div>
            
            <!-- Contenu du formulaire -->
            <div class="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl overflow-hidden">
                <div class="p-6 sm:p-8">
                    <slot />
                </div>
            </div>
            
            <!-- Footer -->
            <div class="text-center mt-6 text-white/70 text-xs">
                Gendarmerie Nationale du Mali &bull; « Renseigner - Éduquer - au besoin Réprimer »
            </div>
        </div>
    </div>
</template>

<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>
