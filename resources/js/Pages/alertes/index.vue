<script setup>
import { ref, reactive, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from 'primevue/card';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import Dialog from 'primevue/dialog';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import debounce from 'lodash/debounce';

const props = defineProps({
    alertes: { type: Object, required: true },
    statistiques: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) }
});

const toast = useToast();
const page = usePage();
const loading = ref(false);
const loadingStates = ref({});
const generatingAll = ref(false);
const activeTab = ref(props.filters?.type || 'all');

const showRenewModal = ref(false);
const submitting = ref(false);
const selectedMilitaire = ref(null);

// FIX : source de vérité unique pour la date du nouveau contrat.
// form.date_debut a été supprimé — on lit directement selectedDate au submit.
const selectedDate = ref(null);

const form = reactive({
    militaire_id: null,
    duree_annees: 5,
    observations: ''
});

const filters = reactive({
    search: props.filters?.search || ''
});

// Watcher pour les messages flash
watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        toast.add({ severity: 'success', summary: 'Succès', detail: flash.success, life: 5000 });
    }
    if (flash?.error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: flash.error, life: 5000 });
    }
    if (flash?.info) {
        toast.add({ severity: 'info', summary: 'Information', detail: flash.info, life: 5000 });
    }
}, { immediate: true });

const getTabTitle = () => {
    const titles = {
        'promotion': 'Alertes de promotion',
        'formation': 'Alertes de formation',
        'contrat': 'Alertes de contrat',
        'mutation': 'Alertes de mutation (durée d\'affectation >= 3 ans)',
        'all': 'Toutes les alertes'
    };
    return titles[activeTab.value] || 'Toutes les alertes';
};

const debouncedSearch = debounce(() => { loadAlertes(1); }, 500);
const onSearchInput = () => { debouncedSearch(); };
watch(() => filters.search, () => { debouncedSearch(); });

const getTypeLabel = (type) => {
    const labels = {
        'promotion': 'Promotion',
        'formation': 'Formation',
        'retraite': 'Retraite',
        'certificat': 'Certificat',
        'contrat': 'Contrat',
        'mutation': 'Mutation'
    };
    return labels[type] || type;
};

const getTypeStyle = (type) => {
    const styles = {
        'promotion': { background: '#e0f2fe', color: '#0369a1', border: '1px solid #bae6fd', fontSize: '0.72rem', fontWeight: '600', padding: '0.2rem 0.55rem', borderRadius: '0.375rem' },
        'formation': { background: '#e0e7ff', color: '#3730a3', border: '1px solid #c7d2fe', fontSize: '0.72rem', fontWeight: '600', padding: '0.2rem 0.55rem', borderRadius: '0.375rem' },
        'contrat': { background: '#f0fdf4', color: '#15803d', border: '1px solid #bbf7d0', fontSize: '0.72rem', fontWeight: '600', padding: '0.2rem 0.55rem', borderRadius: '0.375rem' },
        'mutation': { background: '#fef3c7', color: '#92400e', border: '1px solid #fde68a', fontSize: '0.72rem', fontWeight: '600', padding: '0.2rem 0.55rem', borderRadius: '0.375rem' },
        'retraite': { background: '#fef2f2', color: '#b91c1c', border: '1px solid #fecaca', fontSize: '0.72rem', fontWeight: '600', padding: '0.2rem 0.55rem', borderRadius: '0.375rem' },
        'certificat': { background: '#f5f3ff', color: '#6d28d9', border: '1px solid #ddd6fe', fontSize: '0.72rem', fontWeight: '600', padding: '0.2rem 0.55rem', borderRadius: '0.375rem' },
    };
    return styles[type] || { background: '#f1f5f9', color: '#475569', border: '1px solid #cbd5e1', fontSize: '0.72rem', fontWeight: '600', padding: '0.2rem 0.55rem', borderRadius: '0.375rem' };
};

const isEcheanceProche = (date) => {
    if (!date) return false;
    const today = new Date();
    const echeance = new Date(date);
    const diffDays = Math.ceil((echeance - today) / (1000 * 60 * 60 * 24));
    return diffDays <= 30 && diffDays >= 0;
};

const rowClass = (data) => data.est_vue ? '' : 'bg-sky-50/40';

const loadAlertes = (page = 1) => {
    loading.value = true;
    const params = { page, search: filters.search };
    if (activeTab.value !== 'all') params.type = activeTab.value;

    router.get(route('alertes.index'), params, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => { loading.value = false; },
        onError: () => {
            loading.value = false;
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les alertes', life: 3000 });
        }
    });
};

const setActiveTab = (tab) => {
    if (activeTab.value === tab) return;
    activeTab.value = tab;
    loadAlertes(1);
};

const onPageChange = (event) => { loadAlertes(event.page + 1); };

const markAsRead = (alerteId) => {
    loadingStates.value[alerteId] = true;
    router.post(route('alertes.marquer-vue', alerteId), {}, {
        preserveScroll: true,
        onError: () => {
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de marquer l\'alerte', life: 3000 });
        },
        onFinish: () => {
            loadingStates.value[alerteId] = false;
        }
    });
};

const markAllAsRead = () => {
    router.post(route('alertes.marquer-tout-vue'), {}, {
        preserveScroll: true,
        onError: () => {
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de marquer toutes les alertes', life: 3000 });
        }
    });
};

const generateAllAlerts = () => {
    generatingAll.value = true;
    router.post(route('alertes.generer'), {}, {
        preserveScroll: true,
        onError: () => {
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de générer les alertes', life: 3000 });
        },
        onFinish: () => {
            generatingAll.value = false;
        }
    });
};

// Ouvrir le modal de renouvellement
const openRenewModal = (militaire) => {
    selectedMilitaire.value = militaire;
    form.militaire_id = militaire.id;
    selectedDate.value = new Date();
    form.duree_annees = 5;
    form.observations = '';
    showRenewModal.value = true;
};

// Fermer le modal
const closeModal = () => {
    showRenewModal.value = false;
    form.militaire_id = null;
    selectedDate.value = null;
    form.duree_annees = 5;
    form.observations = '';
};

// Formater une date locale (année-mois-jour) sans décalage de fuseau horaire
const formatDateLocal = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// Renouveler le contrat
const renewContract = () => {
    if (!selectedDate.value) {
        toast.add({
            severity: 'error',
            summary: 'Erreur',
            detail: 'Veuillez sélectionner une date de début',
            life: 3000
        });
        return;
    }

    submitting.value = true;

    const data = {
        militaire_id: form.militaire_id,
        date_debut: formatDateLocal(selectedDate.value),
        duree_annees: form.duree_annees,
        observations: form.observations
    };

    router.post(route('contrats.store'), data, {
        preserveScroll: true,
        onSuccess: () => {
            submitting.value = false;
            showRenewModal.value = false;
            toast.add({
                severity: 'success',
                summary: 'Succès',
                detail: 'Contrat renouvelé avec succès',
                life: 3000
            });
            loadAlertes(props.alertes.current_page);
        },
        onError: (errors) => {
            submitting.value = false;
            toast.add({
                severity: 'error',
                summary: 'Erreur',
                detail: Object.values(errors).flat()[0] || 'Impossible de renouveler le contrat',
                life: 3000
            });
        }
    });
};

const viewMilitaire = (id) => {
    router.visit(route('militaires.show', id));
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center">
                <h2 class="font-bold text-lg md:text-xl text-white tracking-wide">
                    Liste des alertes
                </h2>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- 1. STATISTIQUES GLOBALES (CARTES HARMONISÉES) -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white rounded-xl shadow-xs p-4 border-l-4 border-sky-900 flex items-center justify-between border border-slate-200/80">
                        <div>
                            <div class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Total Alertes</div>
                            <div class="text-2xl font-extrabold text-sky-950 mt-1">{{ statistiques.total }}</div>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-sky-50 flex items-center justify-center">
                            <i class="pi pi-bell text-sky-900 text-lg"></i>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-xs p-4 border-l-4 border-amber-500 flex items-center justify-between border border-slate-200/80">
                        <div>
                            <div class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Non Vues</div>
                            <div class="text-2xl font-extrabold text-amber-600 mt-1">{{ statistiques.non_vues }}</div>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-amber-50 flex items-center justify-center">
                            <i class="pi pi-exclamation-triangle text-amber-600 text-lg"></i>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-xs p-4 border-l-4 border-emerald-500 flex items-center justify-between border border-slate-200/80">
                        <div>
                            <div class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Vues</div>
                            <div class="text-2xl font-extrabold text-emerald-600 mt-1">{{ statistiques.vues }}</div>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center">
                            <i class="pi pi-check-circle text-emerald-600 text-lg"></i>
                        </div>
                    </div>
                </div>

                <!-- 2. BARRE D'ONGLETS & RECHERCHE (RESPONSIVE & UNIFIÉE) -->
                <div class="bg-white rounded-xl shadow-xs p-4 border border-slate-200 space-y-3">
                    <!-- Ligne 1: Filtres par catégorie d'alerte -->
                    <div class="flex items-center gap-2 overflow-x-auto pb-1 -mx-1 px-1">
                        <button @click="setActiveTab('all')"
                            :class="[
                                'px-3.5 py-2 rounded-lg text-xs sm:text-sm font-semibold transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap shrink-0',
                                activeTab === 'all'
                                    ? 'bg-gradient-to-r from-sky-800 to-sky-700 text-white shadow-xs'
                                    : 'bg-white text-slate-700 hover:bg-sky-50 hover:text-sky-900 border border-slate-200'
                            ]">
                            <span>Toutes</span>
                            <span class="text-xs px-2 py-0.5 rounded-full" :class="activeTab === 'all' ? 'bg-white/20 text-white font-bold' : 'bg-slate-100 text-slate-700 font-medium'">
                                {{ statistiques.total || 0 }}
                            </span>
                        </button>

                        <button @click="setActiveTab('promotion')"
                            :class="[
                                'px-3.5 py-2 rounded-lg text-xs sm:text-sm font-semibold transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap shrink-0',
                                activeTab === 'promotion'
                                    ? 'bg-gradient-to-r from-sky-800 to-sky-700 text-white shadow-xs'
                                    : 'bg-white text-slate-700 hover:bg-sky-50 hover:text-sky-900 border border-slate-200'
                            ]">
                            <i class="pi pi-star text-xs"></i>
                            <span>Promotions</span>
                            <span class="text-xs px-2 py-0.5 rounded-full" :class="activeTab === 'promotion' ? 'bg-white/20 text-white font-bold' : 'bg-slate-100 text-slate-700 font-medium'">
                                {{ statistiques.promotions || 0 }}
                            </span>
                        </button>

                        <button @click="setActiveTab('formation')"
                            :class="[
                                'px-3.5 py-2 rounded-lg text-xs sm:text-sm font-semibold transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap shrink-0',
                                activeTab === 'formation'
                                    ? 'bg-gradient-to-r from-sky-800 to-sky-700 text-white shadow-xs'
                                    : 'bg-white text-slate-700 hover:bg-sky-50 hover:text-sky-900 border border-slate-200'
                            ]">
                            <i class="pi pi-book text-xs"></i>
                            <span>Formations</span>
                            <span class="text-xs px-2 py-0.5 rounded-full" :class="activeTab === 'formation' ? 'bg-white/20 text-white font-bold' : 'bg-slate-100 text-slate-700 font-medium'">
                                {{ statistiques.formations || 0 }}
                            </span>
                        </button>

                        <button @click="setActiveTab('contrat')"
                            :class="[
                                'px-3.5 py-2 rounded-lg text-xs sm:text-sm font-semibold transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap shrink-0',
                                activeTab === 'contrat'
                                    ? 'bg-gradient-to-r from-sky-800 to-sky-700 text-white shadow-xs'
                                    : 'bg-white text-slate-700 hover:bg-sky-50 hover:text-sky-900 border border-slate-200'
                            ]">
                            <i class="pi pi-file text-xs"></i>
                            <span>Contrats</span>
                            <span class="text-xs px-2 py-0.5 rounded-full" :class="activeTab === 'contrat' ? 'bg-white/20 text-white font-bold' : 'bg-slate-100 text-slate-700 font-medium'">
                                {{ statistiques.contrats || 0 }}
                            </span>
                        </button>

                        <button @click="setActiveTab('mutation')"
                            :class="[
                                'px-3.5 py-2 rounded-lg text-xs sm:text-sm font-semibold transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap shrink-0',
                                activeTab === 'mutation'
                                    ? 'bg-gradient-to-r from-sky-800 to-sky-700 text-white shadow-xs'
                                    : 'bg-white text-slate-700 hover:bg-sky-50 hover:text-sky-900 border border-slate-200'
                            ]">
                            <i class="pi pi-arrows-alt text-xs"></i>
                            <span>Mutations</span>
                            <span class="text-xs px-2 py-0.5 rounded-full" :class="activeTab === 'mutation' ? 'bg-white/20 text-white font-bold' : 'bg-slate-100 text-slate-700 font-medium'">
                                {{ statistiques.mutations || 0 }}
                            </span>
                        </button>
                    </div>

                    <!-- Ligne 2: Recherche sous les onglets (100% alignée et responsive) -->
                    <div class="pt-3 border-t border-slate-100">
                        <div class="relative w-full">
                            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-sm z-10"></i>
                            <InputText v-model="filters.search" 
                                placeholder="Rechercher par militaire (nom, prénom, matricule), type, message, échéance ou date..." 
                                class="w-full text-sm !pl-9 !pr-9 py-2 rounded-lg border-slate-200 focus:border-sky-500" 
                                @input="onSearchInput" />
                            <button v-if="filters.search" @click="filters.search = ''; onSearchInput()" 
                                type="button"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1 cursor-pointer z-10">
                                <i class="pi pi-times-circle text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tableau des alertes -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-4">
                        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100">
                            <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                                <i class="pi pi-list text-sky-800"></i>
                                <span>{{ getTabTitle() }}</span>
                            </h3>
                            <div class="flex items-center gap-2 shrink-0 flex-wrap">
                                <Button v-if="statistiques.non_vues > 0" label="Tout marquer comme vu" icon="pi pi-check-circle"
                                    size="small" severity="success" outlined
                                    class="text-xs font-semibold"
                                    @click="markAllAsRead" />
                                <Button label="Générer toutes les alertes" icon="pi pi-sync"
                                    size="small" severity="primary"
                                    :loading="generatingAll"
                                    class="text-xs font-semibold shadow-sm"
                                    @click="generateAllAlerts" />
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <DataTable :value="alertes.data" stripedRows responsiveLayout="scroll" :loading="loading"
                                paginator lazy :rows="alertes.per_page" :totalRecords="alertes.total"
                                @page="onPageChange" class="p-datatable-sm" :rowClass="rowClass">

                                <Column field="militaire.nom" header="Militaire">
                                    <template #body="slotProps">
                                        <div v-if="slotProps.data.militaire">
                                            <Button
                                                :label="slotProps.data.militaire.nom + ' ' + slotProps.data.militaire.prenom"
                                                class="p-button-link p-0 text-sky-800 hover:text-sky-950 font-medium text-sm"
                                                @click="viewMilitaire(slotProps.data.militaire.id)" />
                                            <div class="text-xs text-gray-500">{{ slotProps.data.militaire.matricule }}
                                            </div>
                                        </div>
                                        <span v-else class="text-gray-400 text-sm">Militaire supprimé</span>
                                    </template>
                                </Column>

                                <Column field="type_alerte" header="Type">
                                    <template #body="slotProps">
                                        <Tag :value="getTypeLabel(slotProps.data.type_alerte)"
                                            :style="getTypeStyle(slotProps.data.type_alerte)" />
                                    </template>
                                </Column>

                                <Column field="message" header="Message">
                                    <template #body="slotProps">
                                        <span class="text-sm">{{ slotProps.data.message }}</span>
                                    </template>
                                </Column>

                                <Column field="date_echeance_formatted" header="Échéance">
                                    <template #body="slotProps">
                                        <div :class="{ 'font-bold text-red-600 text-sm': isEcheanceProche(slotProps.data.date_echeance) }"
                                            class="text-sm whitespace-nowrap">
                                            {{ slotProps.data.date_echeance_formatted }}
                                        </div>
                                    </template>
                                </Column>

                                <Column field="created_at" header="Créée le">
                                    <template #body="slotProps">
                                        <span class="text-sm whitespace-nowrap">{{ slotProps.data.created_at }}</span>
                                    </template>
                                </Column>

                                <Column header="Statut" style="min-width: 6.5rem;">
                                    <template #body="slotProps">
                                        <Tag v-if="slotProps.data.est_vue" value="Vue"
                                            style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-size: 0.72rem; font-weight: 600; padding: 0.2rem 0.55rem; border-radius: 0.375rem;" />
                                        <Tag v-else value="Non vue"
                                            style="background: #fef3c7; color: #92400e; border: 1px solid #fde68a; font-size: 0.72rem; font-weight: 600; padding: 0.2rem 0.55rem; border-radius: 0.375rem;" />
                                    </template>
                                </Column>

                                <Column header="Action" style="min-width: 14rem;">
                                    <template #body="slotProps">
                                        <div class="flex items-center gap-2 flex-nowrap">
                                            <Button v-if="!slotProps.data.est_vue" label="Marquer vue"
                                                icon="pi pi-check"
                                                class="p-button-sm !bg-gradient-to-r !from-sky-800 !to-sky-700 hover:!from-sky-900 hover:!to-sky-800 !border-0 !text-white text-xs font-semibold px-2.5 py-1.5 shadow-2xs whitespace-nowrap shrink-0"
                                                :loading="loadingStates[slotProps.data.id]"
                                                @click="markAsRead(slotProps.data.id)" />
                                            <Button
                                                v-if="slotProps.data.type_alerte === 'contrat' && slotProps.data.militaire"
                                                label="Renouveler" icon="pi pi-refresh"
                                                class="p-button-sm !bg-white hover:!bg-sky-50 !text-sky-900 !border !border-sky-300 text-xs font-semibold px-2.5 py-1.5 shadow-2xs whitespace-nowrap shrink-0"
                                                @click="openRenewModal(slotProps.data.militaire)" />
                                        </div>
                                    </template>
                                </Column>

                                <template #empty>
                                    <div class="text-center py-6 text-gray-500">
                                        <i class="pi pi-bell-slash text-3xl mb-2"></i>
                                        <p class="text-sm">Aucune alerte trouvée</p>
                                    </div>
                                </template>
                            </DataTable>
                        </div>

                        <div class="text-center sm:text-left text-sm text-gray-600 mt-4">
                            Affichage de {{ alertes.from }} à {{ alertes.to }} sur {{ alertes.total }} alertes
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de renouvellement de contrat -->
        <Dialog v-model:visible="showRenewModal" header="Renouvellement de contrat" :style="{ width: '450px' }"
            :modal="true" class="p-fluid">
            <div class="space-y-4">
                <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-sm text-gray-600">
                        <span class="font-semibold">Militaire :</span>
                        {{ selectedMilitaire?.nom }} {{ selectedMilitaire?.prenom }}
                    </p>
                    <p class="text-sm text-gray-600">
                        <span class="font-semibold">Matricule :</span>
                        {{ selectedMilitaire?.matricule }}
                    </p>
                    <p class="text-sm text-gray-600">
                        <span class="font-semibold">Grade :</span>
                        {{ selectedMilitaire?.grade_actuel }}
                    </p>
                    <p class="text-sm text-gray-600" v-if="selectedMilitaire?.contrat_actif">
                        <span class="font-semibold">Contrat actuel :</span>
                        {{ selectedMilitaire.contrat_actif.date_debut }} → {{ selectedMilitaire.contrat_actif.date_fin
                        }}
                    </p>
                </div>

                <div class="field">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date de début du nouveau contrat</label>
                    <!--
                        FIX : une seule source de vérité (selectedDate). On a supprimé
                        form.date_debut ainsi que les listeners @date-select /
                        @update:model-value redondants qui causaient la désynchronisation
                        (le formulaire soumettait parfois l'ancienne valeur "aujourd'hui"
                        même après avoir choisi une autre date).
                        :minDate est maintenant une valeur figée au montage du composant
                        (computed une seule fois) plutôt que "new Date()" recréé à chaque
                        re-render, ce qui pouvait perturber la validation interne du DatePicker.
                    -->
                    <DatePicker v-model="selectedDate" dateFormat="dd/mm/yy" class="w-full" :showIcon="true" />
                    <small class="text-gray-500 text-xs">Sélectionnez la date de début du nouveau contrat</small>
                </div>

                <div class="field">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Durée (en années)</label>
                    <InputNumber v-model="form.duree_annees" :min="1" :max="10" class="w-full" />
                    <small class="text-gray-500 text-xs">Durée recommandée: 5 ans</small>
                </div>

                <div class="field">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Observations</label>
                    <Textarea v-model="form.observations" rows="3" placeholder="Ajouter des observations..." />
                </div>
            </div>

            <template #footer>
                <Button label="Annuler" icon="pi pi-times" @click="closeModal" class="p-button-text" />
                <Button label="Renouveler le contrat" icon="pi pi-check" @click="renewContract" :loading="submitting"
                    class="!bg-gradient-to-r !from-sky-800 !to-sky-700 hover:!from-sky-900 hover:!to-sky-800 !text-white font-medium !border-0 shadow-xs" />
            </template>
        </Dialog>

        <Toast position="top-right" />
    </AuthenticatedLayout>
</template>

<style scoped>
:deep(.p-card) {
    border-radius: 8px;
    overflow: hidden;
}

:deep(.p-datatable) {
    font-size: 0.85rem;
}

:deep(.p-datatable .p-datatable-tbody > tr.bg-sky-50\/40) {
    background-color: #f0f9ff;
}

:deep(.p-datatable .p-datatable-tbody > tr.bg-sky-50\/40:hover) {
    background-color: #e0f2fe !important;
}

:deep(.p-tag) {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 0.25rem;
}

:deep(.p-button-link) {
    text-decoration: none;
    font-size: 0.85rem;
}

:deep(.p-button-link:hover) {
    text-decoration: underline;
}

:deep(.p-button.p-button-sm) {
    font-size: 0.75rem;
    padding: 0.3rem 0.6rem;
}

:deep(.p-dialog .p-dialog-content) {
    padding: 1.5rem;
}

.bg-sky-600 {
    background-color: #0284c7;
}

.hover\:bg-sky-700:hover {
    background-color: #0369a1;
}

.text-sky-600 {
    color: #0284c7;
}

.bg-emerald-600 {
    background-color: #059669;
}

.hover\:bg-emerald-700:hover {
    background-color: #047857;
}

.bg-amber-600 {
    background-color: #d97706;
}

.hover\:bg-amber-700:hover {
    background-color: #b45309;
}
</style>
