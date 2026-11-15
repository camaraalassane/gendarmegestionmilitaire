<template>
    <div class="min-h-screen py-3 sm:py-6 px-3 sm:px-4 flex flex-col items-center justify-center bg-gradient-to-br from-sky-950 via-sky-900 to-slate-950 relative overflow-y-auto">
        <!-- Formes décoratives animées -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-40 -right-40 w-80 h-80 bg-white/10 rounded-full blur-3xl animate-pulse"></div>
            <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-white/10 rounded-full blur-3xl animate-pulse animation-delay-2000"></div>
        </div>
        
        <div class="relative z-10 w-full max-w-sm sm:max-w-md mx-auto my-auto flex flex-col items-center">
            <!-- Logo et titre - très compact -->
            <div class="text-center mb-2.5 sm:mb-3 animate-fade-in-down">
                <div class="inline-flex items-center justify-center w-12 h-12 sm:w-14 sm:h-14 bg-white/20 backdrop-blur-sm rounded-2xl shadow-lg mb-1.5 transition-all duration-300 hover:scale-105 p-1.5">
                    <img src="/images/logo-gendarmerie-officiel.png" alt="Gendarmerie Nationale" class="w-full h-full object-contain" />
                </div>
                <h1 class="text-white text-lg sm:text-xl font-bold tracking-tight leading-tight">Gestion Militaire</h1>
                <p class="text-sky-200/90 text-[10px] sm:text-[11px] mt-0.5 font-medium tracking-wide">Gendarmerie Nationale • Direction Générale de la Gendarmerie Nationale</p>
            </div>
            
            <!-- Carte de connexion - ultra compacte, paddings réduits -->
            <div class="w-full bg-white rounded-2xl shadow-2xl overflow-hidden border border-slate-100 transition-all duration-300">
                <!-- En-tête de la carte -->
                <div class="text-center py-2.5 sm:py-3 px-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 via-white to-gray-50">
                    <div class="inline-flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 bg-gradient-to-r from-sky-800 to-sky-700 rounded-lg shadow-sm mb-1 text-white">
                        <i class="pi pi-user text-sm"></i>
                    </div>
                    <h2 class="text-base sm:text-lg font-bold text-gray-800 leading-tight">Se connecter</h2>
                    <p class="text-gray-500 text-[10px] sm:text-[11px] mt-0.5">Accédez à votre espace</p>
                </div>

                <!-- Corps du formulaire -->
                <div class="p-3.5 sm:p-5">
                    <form @submit.prevent="submit" class="space-y-2.5 sm:space-y-3">
                        <!-- Session Status -->
                        <Message v-if="status" severity="success" :closable="false" class="mb-2 text-xs">
                            <div class="flex items-center gap-1">
                                <i class="pi pi-check-circle text-green-500 text-xs"></i>
                                <span>{{ status }}</span>
                            </div>
                        </Message>

                        <!-- Email -->
                        <div class="field">
                            <label class="text-xs font-semibold text-gray-700 mb-1 flex items-center gap-1">
                                <i class="pi pi-envelope text-sky-800 text-[11px]"></i>
                                Email
                            </label>
                            <div class="relative group">
                                <InputText 
                                    v-model="form.email" 
                                    type="email" 
                                    class="w-full pl-8 pr-3 py-1.5 sm:py-2 text-xs sm:text-sm border-gray-200 focus:border-sky-800 focus:ring-sky-800"
                                    :class="{ 'p-invalid': form.errors.email }"
                                    placeholder="admin@gendarmerie.ml" />
                            </div>
                            <small v-if="form.errors.email" class="text-red-500 text-[10px] mt-0.5 flex items-center gap-1">
                                <i class="pi pi-exclamation-circle text-[9px]"></i>
                                {{ form.errors.email }}
                            </small>
                        </div>

                        <!-- Password -->
                        <div class="field">
                            <label class="text-xs font-semibold text-gray-700 mb-1 flex items-center gap-1">
                                <i class="pi pi-lock text-sky-800 text-[11px]"></i>
                                Mot de passe
                            </label>
                            <div class="relative group">
                                <InputText 
                                    :type="showPassword ? 'text' : 'password'"
                                    v-model="form.password" 
                                    class="w-full pl-8 pr-8 py-1.5 sm:py-2 text-xs sm:text-sm border-gray-200 focus:border-sky-800 focus:ring-sky-800"
                                    :class="{ 'p-invalid': form.errors.password }"
                                    placeholder="••••••••" />
                                <button type="button" @click="showPassword = !showPassword" class="absolute right-2.5 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-sky-800">
                                    <i :class="showPassword ? 'pi pi-eye-slash text-xs' : 'pi pi-eye text-xs'"></i>
                                </button>
                            </div>
                            <small v-if="form.errors.password" class="text-red-500 text-[10px] mt-0.5 flex items-center gap-1">
                                <i class="pi pi-exclamation-circle text-[9px]"></i>
                                {{ form.errors.password }}
                            </small>
                        </div>

                        <!-- Remember & Forgot -->
                        <div class="flex items-center justify-between pt-0.5">
                            <label class="flex items-center gap-1.5 cursor-pointer group">
                                <Checkbox v-model="form.remember" :binary="true" class="scale-75" />
                                <span class="text-[11px] text-gray-600 group-hover:text-gray-800 font-medium">Se souvenir</span>
                            </label>
                            <Link 
                                v-if="canResetPassword" 
                                :href="route('password.request')" 
                                class="text-[11px] text-sky-800 hover:text-sky-950 hover:underline font-semibold">
                                Mot de passe oublié ?
                            </Link>
                        </div>

                        <!-- Submit Button -->
                        <Button 
                            type="submit" 
                            :label="form.processing ? 'Connexion...' : 'Se connecter'"
                            :icon="form.processing ? 'pi pi-spinner pi-spin' : 'pi pi-sign-in'"
                            :loading="form.processing"
                            class="w-full !bg-gradient-to-r !from-sky-800 !to-sky-700 hover:!from-sky-900 hover:!to-sky-800 !border-0 text-white py-2 text-xs sm:text-sm font-semibold shadow-md cursor-pointer mt-1" />
                        
                        <!-- Footer dans la carte bien visible -->
                        <div class="text-center pt-2.5 border-t border-gray-100">
                            <div class="flex justify-center items-center gap-2 text-[11px] font-semibold text-slate-500">
                                <span>Gendarmerie</span>
                                <span class="text-slate-300">•</span>
                                <span>Gestion Militaire</span>
                                <span class="text-slate-300">•</span>
                                <span class="px-1.5 py-0.2 bg-slate-100 rounded text-slate-600 text-[10px]">v1.0</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Footer extérieur sous la carte : très net et bien visible -->
            <div class="text-center mt-2.5 sm:mt-3 animate-fade-in-up">
                <p class="text-sky-200/90 text-[11px] sm:text-xs font-medium tracking-wide drop-shadow-xs">
                    © {{ new Date().getFullYear() }} — Gestion Militaire (Gendarmerie Nationale)
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Message from 'primevue/message';

defineProps({
    canResetPassword: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<style scoped>
@keyframes fade-in-down {
    from {
        opacity: 0;
        transform: translateY(-15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-down {
    animation: fade-in-down 0.5s ease-out;
}

.animate-fade-in-up {
    animation: fade-in-up 0.5s ease-out;
}

.animation-delay-2000 {
    animation-delay: 2s;
}

:deep(.p-card) {
    border-radius: 0.875rem;
    overflow: hidden;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.98);
}

:deep(.p-card .p-card-header) {
    padding: 0;
}

:deep(.p-card .p-card-content) {
    padding: 1rem 1.25rem;
}

:deep(.p-inputtext) {
    border-radius: 0.5rem;
    font-size: 0.8125rem;
    padding: 0.5rem 0.75rem;
}

:deep(.p-inputtext:focus) {
    box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.1);
}

:deep(.p-checkbox .p-checkbox-box) {
    width: 0.875rem;
    height: 0.875rem;
    border-radius: 0.25rem;
}

:deep(.p-checkbox .p-checkbox-box.p-highlight) {
    background: #0284c7;
    border-color: #0284c7;
}

:deep(.p-button) {
    border-radius: 0.5rem;
    padding: 0.5rem;
    font-size: 0.8125rem;
}

:deep(.p-message) {
    border-radius: 0.5rem;
}

:deep(.p-message .p-message-wrapper) {
    padding: 0.375rem 0.625rem;
}

@media (max-height: 680px) {
    :deep(.p-card .p-card-content) {
        padding: 0.75rem 1rem;
    }
    
    .mb-4 {
        margin-bottom: 0.5rem;
    }
    
    .mt-3 {
        margin-top: 0.5rem;
    }
    
    .space-y-3 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 0.5rem;
    }
}
</style>