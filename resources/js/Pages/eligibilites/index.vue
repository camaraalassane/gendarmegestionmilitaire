<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from 'primevue/button';
import Select from 'primevue/select';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Badge from 'primevue/badge';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { downloadExport } from '@/Utils/exportHelper';

const props = defineProps({
    formationsListe: { type: Array, default: () => [] },
    gradesListe: { type: Array, default: () => [] },
    initialType: { type: String, default: 'promotions' },
    initialData: { type: Array, default: () => [] },
    initialTotal: { type: Number, default: 0 },
    initialStatistiques: { type: Object, default: () => ({}) },
    initialFormationCounts: { type: Object, default: () => ({}) },
    initialGradeCounts: { type: Object, default: () => ({}) },
});

const toast = useToast();
const loading = ref(false);
const exportingFiltered = ref(false);
const exportingAll = ref(false);
const selectedType = ref(props.initialType || 'promotions');
const promotions = ref(props.initialType === 'promotions' ? props.initialData : []);
const formations = ref(props.initialType === 'formations' ? props.initialData : []);
const retraites = ref(props.initialType === 'retraites' ? props.initialData : []);
const contrats = ref(props.initialType === 'contrats' ? props.initialData : []);
const mutations = ref(props.initialType === 'mutations' ? props.initialData : []);

// Pagination
const currentPage = ref(1);
const perPage = ref(30);
const totalItems = ref(props.initialTotal || 0);
const first = ref(0);

// Totaux globaux
const totalPromotions = ref(props.initialStatistiques?.total_promotions || 0);
const totalFormations = ref(props.initialStatistiques?.total_formations || 0);
const totalContrats = ref(props.initialStatistiques?.total_contrats || 0);
const totalMutations = ref(props.initialStatistiques?.total_mutations || 0);

// Filtres
const selectedFormation = ref('');
const selectedGrade = ref('');
const formationCounts = ref(props.initialFormationCounts || {});
const gradeCounts = ref(props.initialGradeCounts || {});

const gradeOrder = [
    'Général de division', 'Général de brigade', 'Colonel-Major', 'Colonel',
    'Lieutenant-colonel', 'Commandant', 'Capitaine', 'Lieutenant', 'Sous-lieutenant',
    'Major', 'Adjudant-Chef', 'Adjudant', 'Sergent-Chef', 'Sergent',
    'Caporal-chef', 'Caporal', 'Soldat 1', 'Soldat 2'
];

const currentList = computed(() => {
    if (selectedType.value === 'promotions') return promotions.value;
    if (selectedType.value === 'formations') return formations.value;
    if (selectedType.value === 'retraites') return retraites.value;
    if (selectedType.value === 'contrats') return contrats.value;
    if (selectedType.value === 'mutations') return mutations.value;
    return [];
});

const hasData = computed(() => currentList.value.length > 0);

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('fr-FR');
};

const formatMoisRestants = (mois) => {
    if (mois === 0) return "Aujourd'hui";
    if (mois === 1) return "Dans 1 mois";
    return `Dans ${mois} mois`;
};

const getMoisRestantsStyle = (mois) => {
    if (mois <= 0) return { background: '#fecaca', color: '#991b1b' };
    if (mois <= 1) return { background: '#fed7aa', color: '#c2410c' };
    if (mois <= 3) return { background: '#fef3c7', color: '#92400e' };
    return { background: '#dbeafe', color: '#1e40af' };
};

const onPageChange = (event) => {
    const newPage = event.page + 1;
    if (newPage !== currentPage.value) {
        currentPage.value = newPage;
        first.value = event.first;
        loadData();
    }
};

const selectType = (type) => {
    selectedType.value = type;
    selectedFormation.value = '';
    selectedGrade.value = '';
    currentPage.value = 1;
    first.value = 0;
    loadData();
};

const toggleFormation = (formationId) => {
    selectedFormation.value = selectedFormation.value === formationId ? '' : formationId;
    currentPage.value = 1;
    first.value = 0;
    loadData();
};

const clearFormationFilter = () => {
    selectedFormation.value = '';
    currentPage.value = 1;
    first.value = 0;
    loadData();
};

const toggleGrade = (gradeId) => {
    selectedGrade.value = selectedGrade.value === gradeId ? '' : gradeId;
    currentPage.value = 1;
    first.value = 0;
    loadData();
};

const clearGradeFilter = () => {
    selectedGrade.value = '';
    currentPage.value = 1;
    first.value = 0;
    loadData();
};

const getFormationCount = (formationId) => {
    return formationCounts.value[formationId] || 0;
};

const getGradeCount = (gradeId) => {
    return gradeCounts.value[gradeId] || 0;
};

const gradeOptions = computed(() => {
    return (props.gradesListe || []).map(g => ({
        id: g.id,
        nom: g.nom,
        label: `${g.nom} (${getGradeCount(g.id)})`,
        count: getGradeCount(g.id)
    }));
});

const formationOptions = computed(() => {
    return (props.formationsListe || []).map(f => ({
        id: f.id,
        nom: f.nom,
        label: `${f.nom} (${getFormationCount(f.id)})`,
        count: getFormationCount(f.id)
    }));
});

const getGradeName = (gradeId) => {
    const found = (props.gradesListe || []).find(g => g.id === gradeId);
    return found ? found.nom : gradeId;
};

const getFormationName = (formationId) => {
    const found = (props.formationsListe || []).find(f => f.id === formationId);
    return found ? found.nom : formationId;
};

const onFilterChange = () => {
    currentPage.value = 1;
    first.value = 0;
    loadData();
};

const clearAllFilters = () => {
    selectedFormation.value = '';
    selectedGrade.value = '';
    currentPage.value = 1;
    first.value = 0;
    loadData();
};

const loadData = async () => {
    if (!selectedType.value) return;

    loading.value = true;
    try {
        const params = {
            type: selectedType.value,
            page: currentPage.value,
            per_page: perPage.value
        };
        if (selectedFormation.value) params.formation = selectedFormation.value;
        if (selectedGrade.value) params.grade = selectedGrade.value;

        const response = await axios.get(route('eligibilites.filtered'), { params });
        const data = response.data;

        // Mise à jour des données
        if (selectedType.value === 'promotions') {
            promotions.value = data.data || [];
        } else if (selectedType.value === 'formations') {
            formations.value = data.data || [];
        } else if (selectedType.value === 'retraites') {
            retraites.value = data.data || [];
        } else if (selectedType.value === 'contrats') {
            contrats.value = data.data || [];
        } else if (selectedType.value === 'mutations') {
            mutations.value = data.data || [];
        }

        totalItems.value = data.total || 0;

        // Mise à jour des statistiques
        if (data.statistiques) {
            totalPromotions.value = data.statistiques.total_promotions || 0;
            totalFormations.value = data.statistiques.total_formations || 0;
            totalContrats.value = data.statistiques.total_contrats || 0;
            totalMutations.value = data.statistiques.total_mutations || 0;
        }

        // Mise à jour des compteurs pour les filtres (reçus directement du backend optimisé)
        if (data.formation_counts) {
            formationCounts.value = data.formation_counts;
        }
        if (data.grade_counts) {
            gradeCounts.value = data.grade_counts;
        }

    } catch (error) {
        console.error('Erreur:', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les données', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const exportData = async () => {
    const params = new URLSearchParams();
    params.append('type', selectedType.value);
    if (selectedFormation.value) params.append('formation', selectedFormation.value);
    if (selectedGrade.value) params.append('grade', selectedGrade.value);
    params.append('export_all', 'false');
    const url = route('eligibilites.export') + '?' + params.toString();

    await downloadExport(url, {
        defaultFilename: `eligibilites_${selectedType.value}_${new Date().toISOString().slice(0, 10)}.xlsx`,
        toast,
        onStart: () => { exportingFiltered.value = true; },
        onFinish: () => { exportingFiltered.value = false; },
        loadingMessage: `Préparation de l'export (${selectedType.value})... Veuillez patienter.`,
        successMessage: `Fichier des éligibilités (${selectedType.value}) téléchargé avec succès !`
    });
};

const exportAllData = async () => {
    const params = new URLSearchParams();
    params.append('type', selectedType.value);
    if (selectedFormation.value) params.append('formation', selectedFormation.value);
    if (selectedGrade.value) params.append('grade', selectedGrade.value);
    params.append('export_all', 'true');
    const url = route('eligibilites.export') + '?' + params.toString();

    await downloadExport(url, {
        defaultFilename: `eligibilites_toutes_${new Date().toISOString().slice(0, 10)}.xlsx`,
        toast,
        onStart: () => { exportingAll.value = true; },
        onFinish: () => { exportingAll.value = false; },
        loadingMessage: 'Génération de l\'export complet des éligibilités... Veuillez patienter.',
        successMessage: 'Fichier complet des éligibilités téléchargé avec succès !'
    });
};

const viewMilitaire = (id) => {
    router.visit(route('militaires.show', id));
};

const goToRenewal = (militaireId) => {
    router.visit(route('contrats.index', {
        militaire: Number(militaireId),
        openModal: 1
    }));
};

// Charger les données au montage seulement si elles n'ont pas été pré-injectées par Inertia
onMounted(() => {
    if (!promotions.value.length && !formations.value.length && !contrats.value.length && !mutations.value.length) {
        loadData();
    }
});
</script>

<template>
    <AuthenticatedLayout>
        <!-- Header -->
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-white">
                    Éligibilités
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Cartes de navigation -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <!-- Carte Promotions -->
                    <div class="cursor-pointer transition-all rounded-xl overflow-hidden border p-4 flex items-center justify-between"
                        :class="selectedType === 'promotions' 
                            ? 'bg-gradient-to-r from-sky-800 via-sky-700 to-sky-800 text-white shadow-md border-sky-600' 
                            : 'bg-white text-slate-800 border-slate-200 hover:border-sky-400 hover:shadow-xs'"
                        @click="selectType('promotions')">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-lg flex items-center justify-center shrink-0"
                                :class="selectedType === 'promotions' ? 'bg-white/20 text-white' : 'bg-sky-50 text-sky-900'">
                                <i class="pi pi-star text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold" :class="selectedType === 'promotions' ? 'text-white' : 'text-slate-800'">Promotions</h3>
                                <p class="text-xs" :class="selectedType === 'promotions' ? 'text-white/80' : 'text-slate-500'">Propositions de grade</p>
                            </div>
                        </div>
                        <div class="text-3xl font-extrabold" :class="selectedType === 'promotions' ? 'text-white' : 'text-sky-950'">{{ totalPromotions }}</div>
                    </div>

                    <!-- Carte Formations -->
                    <div class="cursor-pointer transition-all rounded-xl overflow-hidden border p-4 flex items-center justify-between"
                        :class="selectedType === 'formations' 
                            ? 'bg-gradient-to-r from-sky-800 via-sky-700 to-sky-800 text-white shadow-md border-sky-600' 
                            : 'bg-white text-slate-800 border-slate-200 hover:border-sky-400 hover:shadow-xs'"
                        @click="selectType('formations')">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-lg flex items-center justify-center shrink-0"
                                :class="selectedType === 'formations' ? 'bg-white/20 text-white' : 'bg-sky-50 text-sky-900'">
                                <i class="pi pi-book text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold" :class="selectedType === 'formations' ? 'text-white' : 'text-slate-800'">Formations</h3>
                                <p class="text-xs" :class="selectedType === 'formations' ? 'text-white/80' : 'text-slate-500'">Formations disponibles</p>
                            </div>
                        </div>
                        <div class="text-3xl font-extrabold" :class="selectedType === 'formations' ? 'text-white' : 'text-sky-950'">{{ totalFormations }}</div>
                    </div>

                    <!-- Carte Contrats -->
                    <div class="cursor-pointer transition-all rounded-xl overflow-hidden border p-4 flex items-center justify-between"
                        :class="selectedType === 'contrats' 
                            ? 'bg-gradient-to-r from-sky-800 via-sky-700 to-sky-800 text-white shadow-md border-sky-600' 
                            : 'bg-white text-slate-800 border-slate-200 hover:border-sky-400 hover:shadow-xs'"
                        @click="selectType('contrats')">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-lg flex items-center justify-center shrink-0"
                                :class="selectedType === 'contrats' ? 'bg-white/20 text-white' : 'bg-sky-50 text-sky-900'">
                                <i class="pi pi-file text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold" :class="selectedType === 'contrats' ? 'text-white' : 'text-slate-800'">Contrats</h3>
                                <p class="text-xs" :class="selectedType === 'contrats' ? 'text-white/80' : 'text-slate-500'">Renouvellements</p>
                            </div>
                        </div>
                        <div class="text-3xl font-extrabold" :class="selectedType === 'contrats' ? 'text-white' : 'text-sky-950'">{{ totalContrats }}</div>
                    </div>

                    <!-- Carte Mutations -->
                    <div class="cursor-pointer transition-all rounded-xl overflow-hidden border p-4 flex items-center justify-between"
                        :class="selectedType === 'mutations' 
                            ? 'bg-gradient-to-r from-sky-800 via-sky-700 to-sky-800 text-white shadow-md border-sky-600' 
                            : 'bg-white text-slate-800 border-slate-200 hover:border-sky-400 hover:shadow-xs'"
                        @click="selectType('mutations')">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-lg flex items-center justify-center shrink-0"
                                :class="selectedType === 'mutations' ? 'bg-white/20 text-white' : 'bg-sky-50 text-sky-900'">
                                <i class="pi pi-arrows-alt text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold" :class="selectedType === 'mutations' ? 'text-white' : 'text-slate-800'">Mutations</h3>
                                <p class="text-xs" :class="selectedType === 'mutations' ? 'text-white/80' : 'text-slate-500'">En poste ≥ 3 ans</p>
                            </div>
                        </div>
                        <div class="text-3xl font-extrabold" :class="selectedType === 'mutations' ? 'text-white' : 'text-sky-950'">{{ totalMutations }}</div>
                    </div>
                </div>

                <!-- Filtres sous forme de recherche compacte et responsive -->
                <div v-if="selectedType" class="bg-white rounded-xl shadow-xs p-4 mb-6 border border-slate-200">
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
                        <!-- Recherche / Sélection Grade -->
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
                                <i class="pi pi-star text-amber-500"></i> Filtrer par grade
                            </label>
                            <Select v-model="selectedGrade" 
                                :options="gradeOptions" 
                                optionLabel="label" 
                                optionValue="id" 
                                filter 
                                showClear 
                                placeholder="Rechercher ou sélectionner un grade..." 
                                class="w-full text-sm" 
                                @change="onFilterChange" />
                        </div>

                        <!-- Recherche / Sélection Formation (si sélectionné formations) -->
                        <div v-if="selectedType === 'formations'" class="flex-1">
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
                                <i class="pi pi-book text-sky-600"></i> Filtrer par formation
                            </label>
                            <Select v-model="selectedFormation" 
                                :options="formationOptions" 
                                optionLabel="label" 
                                optionValue="id" 
                                filter 
                                showClear 
                                placeholder="Rechercher ou sélectionner une formation..." 
                                class="w-full text-sm" 
                                @change="onFilterChange" />
                        </div>

                        <!-- Bouton réinitialiser -->
                        <div v-if="selectedGrade || selectedFormation" class="shrink-0 sm:self-end pb-0.5">
                            <button @click="clearAllFilters" 
                                type="button"
                                class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 cursor-pointer">
                                <i class="pi pi-filter-slash text-xs"></i> <span>Réinitialiser</span>
                            </button>
                        </div>
                        <!-- Boutons d'exportation dans le card de filtre -->
                        <div class="shrink-0 sm:self-end flex items-center gap-2 pb-0.5 sm:ml-auto">
                            <Button :label="exportingFiltered ? 'Exportation...' : 'Exporter'" icon="pi pi-file-excel"
                                size="small" severity="success" outlined
                                :loading="exportingFiltered"
                                class="text-xs font-semibold"
                                @click="exportData" />
                            <Button :label="exportingAll ? 'Exportation...' : 'Tout exporter'" icon="pi pi-file-excel"
                                size="small" severity="success"
                                :loading="exportingAll"
                                class="text-xs font-semibold" 
                                @click="exportAllData" />
                        </div>
                    </div>

                    

                    <!-- Badges des filtres actifs -->
                    <div v-if="selectedGrade || selectedFormation" class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-slate-100">
                        <span class="text-xs text-slate-500 font-medium">Filtre(s) actif(s) :</span>
                        <span v-if="selectedGrade" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-sky-100 text-sky-800">
                            <span>Grade : {{ getGradeName(selectedGrade) }}</span>
                            <i class="pi pi-times-circle cursor-pointer hover:text-sky-950" @click="clearGradeFilter"></i>
                        </span>
                        <span v-if="selectedFormation" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                            <span>Formation : {{ getFormationName(selectedFormation) }}</span>
                            <i class="pi pi-times-circle cursor-pointer hover:text-indigo-950" @click="clearFormationFilter"></i>
                        </span>
                    </div>
                </div>

                <!-- Loading -->
                <div v-if="loading" class="flex justify-center py-12">
                    <i class="pi pi-spin pi-spinner text-3xl text-sky-800"></i>
                </div>

                <!-- Tableau PROMOTIONS -->
                <div v-else-if="selectedType === 'promotions' && promotions.length > 0"
                    class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <DataTable :value="promotions" stripedRows responsiveLayout="scroll" class="p-datatable-sm"
                        paginator :rows="perPage" :totalRecords="totalItems" @page="onPageChange" v-model:first="first"
                        lazy>
                        <Column field="militaire.matricule" header="Matricule" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.militaire.matricule"
                                    style="background: #bae6fd; color: #0369a1;" />
                            </template>
                        </Column>
                        <Column field="militaire.nom" header="Nom" sortable>
                            <template #body="slotProps">
                                <Button :label="slotProps.data.militaire.nom + ' ' + slotProps.data.militaire.prenom"
                                    class="p-button-link p-0 text-sky-800 hover:text-sky-950 font-medium"
                                    @click="viewMilitaire(slotProps.data.militaire.id)" />
                            </template>
                        </Column>
                        <Column field="militaire.grade_actuel" header="Grade actuel" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.militaire.grade_actuel"
                                    style="background: #e5e7eb; color: #374151;" />
                            </template>
                        </Column>
                        <Column field="grade_cible" header="Grade cible" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.grade_cible" style="background: #dbeafe; color: #1e40af;" />
                            </template>
                        </Column>
                        <Column field="message" header="Condition" />
                        <Column field="date_estimation" header="Date estimation" sortable>
                            <template #body="slotProps">
                                {{ formatDate(slotProps.data.date_estimation) }}
                            </template>
                        </Column>
                    </DataTable>
                    <div class="p-3 text-sm text-gray-600 text-center border-t">
                        Affichage de {{ promotions.length }} résultat(s) sur {{ totalItems }}
                    </div>
                </div>

                <!-- Tableau FORMATIONS -->
                <div v-else-if="selectedType === 'formations' && formations.length > 0"
                    class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <DataTable :value="formations" stripedRows responsiveLayout="scroll" class="p-datatable-sm"
                        paginator :rows="perPage" :totalRecords="totalItems" @page="onPageChange" v-model:first="first"
                        lazy>
                        <Column field="militaire.matricule" header="Matricule" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.militaire.matricule"
                                    style="background: #bae6fd; color: #0369a1;" />
                            </template>
                        </Column>
                        <Column field="militaire.nom" header="Nom" sortable>
                            <template #body="slotProps">
                                <Button :label="slotProps.data.militaire.nom + ' ' + slotProps.data.militaire.prenom"
                                    class="p-button-link p-0 text-sky-800 hover:text-sky-950 font-medium"
                                    @click="viewMilitaire(slotProps.data.militaire.id)" />
                            </template>
                        </Column>
                        <Column field="militaire.grade_actuel" header="Grade actuel" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.militaire.grade_actuel"
                                    style="background: #e5e7eb; color: #374151;" />
                            </template>
                        </Column>
                        <Column field="nom_formation" header="Formation" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.nom_formation"
                                    style="background: #fef3c7; color: #92400e;" />
                            </template>
                        </Column>
                        <Column field="message" header="Condition" />
                        <Column field="date_estimation" header="Date estimation" sortable>
                            <template #body="slotProps">
                                {{ formatDate(slotProps.data.date_estimation) }}
                            </template>
                        </Column>
                    </DataTable>
                    <div class="p-3 text-sm text-gray-600 text-center border-t">
                        Affichage de {{ formations.length }} résultat(s) sur {{ totalItems }}
                    </div>
                </div>

                <!-- Tableau RETRAITES -->
                <div v-else-if="selectedType === 'retraites' && retraites.length > 0"
                    class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <DataTable :value="retraites" stripedRows responsiveLayout="scroll" class="p-datatable-sm" paginator
                        :rows="perPage" :totalRecords="totalItems" @page="onPageChange" v-model:first="first" lazy>
                        <Column field="militaire.matricule" header="Matricule" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.militaire.matricule"
                                    style="background: #bae6fd; color: #0369a1;" />
                            </template>
                        </Column>
                        <Column field="militaire.nom" header="Nom" sortable>
                            <template #body="slotProps">
                                <Button :label="slotProps.data.militaire.nom + ' ' + slotProps.data.militaire.prenom"
                                    class="p-button-link p-0 text-sky-800 hover:text-sky-950 font-medium"
                                    @click="viewMilitaire(slotProps.data.militaire.id)" />
                            </template>
                        </Column>
                        <Column field="militaire.grade_actuel" header="Grade actuel" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.militaire.grade_actuel"
                                    style="background: #e5e7eb; color: #374151;" />
                            </template>
                        </Column>
                        <Column field="date_retraite_formatted" header="Date retraite" sortable />
                        <Column header="Mois restants" sortable>
                            <template #body="slotProps">
                                <Tag :value="formatMoisRestants(slotProps.data.mois_restants)"
                                    :style="getMoisRestantsStyle(slotProps.data.mois_restants)" />
                            </template>
                        </Column>
                    </DataTable>
                    <div class="p-3 text-sm text-gray-600 text-center border-t">
                        Affichage de {{ retraites.length }} résultat(s) sur {{ totalItems }}
                    </div>
                </div>

                <!-- Tableau CONTRATS -->
                <div v-else-if="selectedType === 'contrats' && contrats.length > 0"
                    class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <DataTable :value="contrats" stripedRows responsiveLayout="scroll" class="p-datatable-sm" paginator
                        :rows="perPage" :totalRecords="totalItems" @page="onPageChange" v-model:first="first" lazy>
                        <Column field="militaire.matricule" header="Matricule" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.militaire.matricule"
                                    style="background: #bae6fd; color: #0369a1;" />
                            </template>
                        </Column>
                        <Column field="militaire.nom" header="Nom" sortable>
                            <template #body="slotProps">
                                <Button :label="slotProps.data.militaire.nom + ' ' + slotProps.data.militaire.prenom"
                                    class="p-button-link p-0 text-sky-800 hover:text-sky-950 font-medium"
                                    @click="viewMilitaire(slotProps.data.militaire.id)" />
                            </template>
                        </Column>
                        <Column field="militaire.grade_actuel" header="Grade actuel" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.militaire.grade_actuel"
                                    style="background: #e5e7eb; color: #374151;" />
                            </template>
                        </Column>
                        <Column field="annees_service" header="Années service" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.annees_service + ' ans'"
                                    style="background: #dbeafe; color: #1e40af;" />
                            </template>
                        </Column>
                        <Column field="statut_contrat" header="Statut" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.statut_contrat" :style="slotProps.data.statut_contrat === 'actif'
                                    ? { background: '#dcfce7', color: '#166534', border: '1px solid #bbf7d0', fontSize: '0.72rem', fontWeight: '600' }
                                    : { background: '#fef3c7', color: '#92400e', border: '1px solid #fde68a', fontSize: '0.72rem', fontWeight: '600' }" />
                            </template>
                        </Column>
                        <Column field="date_echeance" header="Échéance" sortable>
                            <template #body="slotProps">
                                {{ formatDate(slotProps.data.date_echeance) }}
                            </template>
                        </Column>
                        <Column header="Action">
                            <template #body="slotProps">
                                <Button label="Renouveler" icon="pi pi-refresh"
                                    class="p-button-sm !bg-gradient-to-r !from-sky-800 !to-sky-700 hover:!from-sky-900 hover:!to-sky-800 !text-white !border-0 text-xs font-semibold px-2.5 py-1.5 shadow-2xs whitespace-nowrap"
                                    @click="goToRenewal(slotProps.data.militaire.id)" />
                            </template>
                        </Column>
                    </DataTable>
                    <div class="p-3 text-sm text-gray-600 text-center border-t">
                        Affichage de {{ contrats.length }} résultat(s) sur {{ totalItems }}
                    </div>
                </div>

                <!-- Tableau MUTATIONS -->
                <div v-else-if="selectedType === 'mutations' && mutations.length > 0"
                    class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <DataTable :value="mutations" stripedRows responsiveLayout="scroll" class="p-datatable-sm" paginator
                        :rows="perPage" :totalRecords="totalItems" @page="onPageChange" v-model:first="first" lazy>
                        <Column field="matricule" header="Matricule" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.matricule"
                                    style="background: #bae6fd; color: #0369a1;" />
                            </template>
                        </Column>
                        <Column field="nom" header="Nom & Prénom" sortable>
                            <template #body="slotProps">
                                <Button :label="slotProps.data.nom + ' ' + slotProps.data.prenom"
                                    class="p-button-link p-0 text-sky-800 hover:text-sky-950 font-medium"
                                    @click="viewMilitaire(slotProps.data.id)" />
                            </template>
                        </Column>
                        <Column field="grade_actuel" header="Grade actuel" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.grade_actuel"
                                    style="background: #e5e7eb; color: #374151;" />
                            </template>
                        </Column>
                        <Column field="unite" header="Unité d'affectation" sortable />
                        <Column field="region" header="Région" sortable />
                        <Column field="date_mutation" header="Dernière mutation" sortable />
                        <Column field="duree_annees" header="Présence" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.duree_annees + ' ans'"
                                    style="background: #fef3c7; color: #92400e; font-weight: 700;" />
                            </template>
                        </Column>
                        <Column header="Action">
                            <template #body="slotProps">
                                <Button label="Consulter" icon="pi pi-eye" class="p-button-sm p-button-outlined"
                                    @click="viewMilitaire(slotProps.data.id)" />
                            </template>
                        </Column>
                    </DataTable>
                    <div class="p-3 text-sm text-gray-600 text-center border-t">
                        Affichage de {{ mutations.length }} résultat(s) sur {{ totalItems }}
                    </div>
                </div>

                <!-- Aucun résultat -->
                <div v-else-if="selectedType && !loading && currentList.length === 0"
                    class="bg-white rounded-lg shadow-sm p-12 text-center">
                    <i class="pi pi-inbox text-5xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500">Aucune éligibilité trouvée</p>
                    <p class="text-sm text-gray-400 mt-1">Essayez un autre filtre</p>
                </div>

                <!-- Message initial -->
                <div v-if="!selectedType" class="bg-white rounded-lg shadow-sm p-12 text-center">
                    <i class="pi pi-hand-point-up text-5xl text-sky-300 mb-3"></i>
                    <p class="text-gray-500">Cliquez sur une carte pour voir les éligibilités</p>
                </div>
            </div>
        </div>

        <Toast position="top-right" />
    </AuthenticatedLayout>
</template>
