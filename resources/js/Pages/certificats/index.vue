<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-white">
                    Liste des certificats
                </h2>
                <div class="flex items-center gap-3">
                    <Badge :value="`${certificats.total} certificats`" 
                           style="background: rgba(255,255,255,0.2); color: white;" 
                           size="large" />
                </div>
            </div>
        </template>
        
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <!-- Message si aucun certificat -->
                        <div v-if="certificats.data.length === 0" class="text-center py-8 text-gray-500">
                            <i class="pi pi-file-pdf text-5xl mb-3 text-gray-400"></i>
                            <p class="text-lg">Aucun certificat trouvé.</p>
                            <Button label="Créer le premier certificat" 
                                    icon="pi pi-plus"
                                    class="p-button-outlined mt-4 border-sky-800 text-sky-900 hover:bg-sky-50"
                                    @click="createCertificat" />
                        </div>

                        <!-- Tableau des certificats -->
                        <template v-else>
                            <!-- Filtres avec recherche automatique -->
                            <div class="mb-5 bg-slate-50/80 p-3.5 rounded-xl border border-slate-200">
                                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                    <div :class="filters.search || filters.niveau ? 'sm:col-span-6' : 'sm:col-span-7'">
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Recherche</label>
                                        <span class="p-input-icon-left w-full">
                                            <i class="pi pi-search" />
                                            <InputText v-model="filters.search" 
                                                      placeholder="Rechercher par nom, niveau, grade..." 
                                                      class="w-full text-xs"
                                                      @input="onSearchInput" />
                                        </span>
                                    </div>
                                    <div :class="filters.search || filters.niveau ? 'sm:col-span-4' : 'sm:col-span-5'">
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Niveau</label>
                                        <Select v-model="filters.niveau" 
                                                :options="niveauxOptions" 
                                                optionLabel="label" 
                                                optionValue="value"
                                                placeholder="Filtrer par niveau"
                                                class="w-full text-xs"
                                                showClear
                                                @change="onFilterChange" />
                                    </div>
                                    <div v-if="filters.search || filters.niveau" class="sm:col-span-2">
                                        <Button label="Réinitialiser" 
                                                icon="pi pi-filter-slash"
                                                class="p-button-sm p-button-outlined p-button-secondary text-xs w-full cursor-pointer"
                                                @click="resetFilters" />
                                    </div>
                                </div>
                            </div>

                            <!-- En-tête du tableau avec bouton Nouveau certificat -->
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4 pb-3 border-b border-slate-100">
                                <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                                    <i class="pi pi-file-pdf text-sky-800"></i>
                                    <span>Certificats militaires enregistrés</span>
                                </h3>
                                <Button label="Nouveau certificat" 
                                        icon="pi pi-plus"
                                        class="!bg-sky-800 hover:!bg-sky-900 text-white font-semibold text-xs px-4 py-2 rounded-lg shadow-sm cursor-pointer whitespace-nowrap self-start sm:self-auto"
                                        @click="createCertificat" />
                            </div>

                            <DataTable :value="certificats.data" 
                                       stripedRows 
                                       responsiveLayout="scroll"
                                       :loading="loading"
                                       paginator
                                       lazy
                                       :rows="certificats.per_page"
                                       :totalRecords="certificats.total"
                                       @page="onPageChange"
                                       class="p-datatable-sm">
                                
                                <!-- Colonne Niveau avec badge sky blue -->
                                <Column field="niveau_certificat" header="Niveau" style="width: 100px">
                                    <template #body="slotProps">
                                        <Tag :value="slotProps.data.niveau_certificat" 
                                             :style="getNiveauStyle(slotProps.data.niveau_certificat)" />
                                    </template>
                                </Column>

                                <!-- Colonne Nom du certificat -->
                                <Column field="nom_certificat" header="Nom du certificat">
                                    <template #body="slotProps">
                                        <Button :label="slotProps.data.nom_certificat"
                                                class="p-button-link p-0 text-sky-800 hover:text-sky-950 font-medium"
                                                @click="viewCertificat(slotProps.data.id)" />
                                    </template>
                                </Column>

                                <!-- Colonne Grade associé -->
                                <Column field="grade_associe" header="Grade associé">
                                    <template #body="slotProps">
                                        <Tag :value="slotProps.data.grade_associe" 
                                             style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 600;" />
                                    </template>
                                </Column>

                                <!-- Colonne Ancienneté requise -->
                                <Column field="anciennete_requise" header="Ancienneté" style="width: 120px">
                                    <template #body="slotProps">
                                        <template v-if="slotProps.data.anciennete_requise">
                                            <Tag :value="slotProps.data.anciennete_requise + ' ans'" 
                                                 style="background: #fef3c7; color: #92400e; border: 1px solid #fde68a; font-weight: 600;" />
                                        </template>
                                        <span v-else class="text-gray-400">—</span>
                                    </template>
                                </Column>

                                <!-- Colonne Certificat prérequis -->
                                <Column field="certificat_precedent" header="Prérequis">
                                    <template #body="slotProps">
                                        <div v-if="slotProps.data.certificat_precedent">
                                            <Tag :value="slotProps.data.certificat_precedent" 
                                                 style="background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; font-weight: 600;" />
                                            <small v-if="slotProps.data.duree_certificat_precedent" 
                                                   class="text-gray-500 block mt-1">
                                                ≥ {{ slotProps.data.duree_certificat_precedent }} ans
                                            </small>
                                        </div>
                                        <span v-else class="text-gray-400">Aucun</span>
                                    </template>
                                </Column>

                                <!-- Colonne Actions -->
                                <Column header="Actions" style="width: 100px">
                                    <template #body="slotProps">
                                        <Button icon="pi pi-eye" 
                                                class="p-button-rounded p-button-text p-button-sm text-sky-800 hover:text-sky-950"
                                                @click="viewCertificat(slotProps.data.id)" />
                                    </template>
                                </Column>

                                <template #empty>
                                    <div class="text-center py-8 text-gray-500">
                                        <i class="pi pi-file-pdf text-4xl mb-2"></i>
                                        <p>Aucun certificat trouvé</p>
                                    </div>
                                </template>
                            </DataTable>

                            <!-- Simple information de pagination -->
                            <div class="text-center sm:text-left text-sm text-gray-600 mt-4">
                                Affichage de {{ certificats.from }} à {{ certificats.to }} sur {{ certificats.total }} certificats
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <Toast position="top-right" />
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Badge from 'primevue/badge';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import debounce from 'lodash/debounce';

const props = defineProps({
    certificats: {
        type: Object,
        required: true
    }
});

const toast = useToast();
const loading = ref(false);

// Options de filtres par niveau
const niveauxOptions = [
    { label: 'Tous les niveaux', value: null },
    { label: 'CAT1', value: 'CAT1' },
    { label: 'CAT2', value: 'CAT2' },
    { label: 'CIA', value: 'CIA' },
    { label: 'BSP', value: 'BSP' },
    { label: 'BSG', value: 'BSG' },
    { label: 'BSC', value: 'BSC' },
    { label: 'CSG', value: 'CSG' }
];

// État des filtres
const filters = reactive({
    search: '',
    niveau: null
});

// Déterminer le style du badge selon le niveau
const getNiveauStyle = (niveau) => {
    const styles = {
        'CAT1': { background: '#e0f2fe', color: '#0369a1', border: '1px solid #bae6fd', fontWeight: '600' },
        'CAT2': { background: '#dbeafe', color: '#1d4ed8', border: '1px solid #bfdbfe', fontWeight: '600' },
        'CIA':  { background: '#e0e7ff', color: '#4338ca', border: '1px solid #c7d2fe', fontWeight: '600' },
        'BSP':  { background: '#fef3c7', color: '#92400e', border: '1px solid #fde68a', fontWeight: '600' },
        'BSG':  { background: '#fee2e2', color: '#b91c1c', border: '1px solid #fecaca', fontWeight: '600' },
        'BSC':  { background: '#f1f5f9', color: '#475569', border: '1px solid #cbd5e1', fontWeight: '600' },
        'CSG':  { background: '#f5f3ff', color: '#6d28d9', border: '1px solid #ddd6fe', fontWeight: '600' }
    };
    return styles[niveau] || { background: '#e0f2fe', color: '#0369a1', border: '1px solid #bae6fd', fontWeight: '600' };
};

// Recherche automatique avec debounce
const debouncedSearch = debounce(() => {
    loadCertificats(1);
}, 500);

const onSearchInput = () => {
    debouncedSearch();
};

const onFilterChange = () => {
    loadCertificats(1);
};

// Watcher pour surveiller les changements de recherche
watch(() => filters.search, () => {
    debouncedSearch();
});

watch(() => filters.niveau, () => {
    loadCertificats(1);
});

// Charger les certificats avec les filtres
const loadCertificats = (page = 1) => {
    loading.value = true;
    
    router.get(route('certificats.index'), {
        page,
        search: filters.search,
        niveau: filters.niveau
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            loading.value = false;
        },
        onError: (errors) => {
            loading.value = false;
            toast.add({
                severity: 'error',
                summary: 'Erreur',
                detail: 'Impossible de charger les certificats',
                life: 3000
            });
        }
    });
};

// Réinitialiser les filtres
const resetFilters = () => {
    filters.search = '';
    filters.niveau = null;
};

// Changement de page via la pagination DataTable
const onPageChange = (event) => {
    loadCertificats(event.page + 1);
};

// Voir les détails d'un certificat
const viewCertificat = (id) => {
    router.visit(route('certificats.show', id));
};

// Créer un nouveau certificat
const createCertificat = () => {
    toast.add({
        severity: 'info',
        summary: 'Information',
        detail: 'Fonctionnalité de création à venir',
        life: 3000
    });
};
</script>

<style scoped>
:deep(.p-datatable) {
    font-size: 0.95rem;
}

:deep(.p-datatable .p-datatable-tbody > tr:hover) {
    background-color: #f8f9fa;
} 

:deep(.p-paginator) {
    padding: 0.5rem 0;
}

:deep(.p-input-icon-left) {
    width: 100%;
}

:deep(.p-inputtext) {
    width: 100%;
    padding-left: 2.5rem;
}

:deep(.p-tag) {
    font-size: 0.85rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
}

:deep(.p-button.p-button-sm) {
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
}

/* Style pour le badge de total */
:deep(.p-badge.p-badge-lg) {
    font-size: 1rem;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
}

/* Styles personnalisés */
.bg-sky-600 {
    background-color: #0284c7;
}

.hover\:bg-sky-700:hover {
    background-color: #0369a1;
}

.border-sky-600 {
    border-color: #0284c7;
}

.text-sky-600 {
    color: #0284c7;
}

.hover\:text-sky-800:hover {
    color: #075985;
}

.hover\:bg-sky-50:hover {
    background-color: #f0f9ff;
}

.bg-gray-500 {
    background-color: #6b7280;
}

.hover\:bg-gray-600:hover {
    background-color: #4b5563;
}

.border-gray-500 {
    border-color: #6b7280;
}

/* Style pour les liens */
:deep(.p-button-link) {
    text-decoration: none;
    font-weight: 500;
}

:deep(.p-button-link:hover) {
    text-decoration: underline;
}

/* Style pour les badges */
:deep(.p-tag) {
    font-weight: 500;
}
</style>