<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-white leading-tight">Tableau de bord</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        
                        <!-- Cartes Principales (Redirection vers les pages correspondantes) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">
                            <!-- 1. Total Militaires -> renvoie sur /militaires -->
                            <div @click="router.visit(route('militaires.index', { statut: 'all' }))"
                                 class="bg-white rounded-xl shadow-xs p-4 border border-slate-200 border-l-4 border-sky-800 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all"
                                 title="Voir tous les militaires">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs uppercase tracking-wider font-semibold text-slate-500">Total Militaires</span>
                                    <div class="w-8 h-8 rounded-full bg-sky-50 flex items-center justify-center text-sky-800">
                                        <i class="pi pi-users text-base"></i>
                                    </div>
                                </div>
                                <div class="text-2xl md:text-3xl font-extrabold text-sky-950">{{ statistiques.total_militaires }}</div>
                                <div class="text-[11px] text-sky-700 mt-1 flex items-center gap-1 font-medium">
                                    <span>Consulter</span> <i class="pi pi-arrow-right text-[9px]"></i>
                                </div>
                            </div>
                            
                            <!-- 2. Militaires Actifs -> renvoie sur /militaires?statut=actif -->
                            <div @click="router.visit(route('militaires.index', { statut: 'actif' }))"
                                 class="bg-white rounded-xl shadow-xs p-4 border border-slate-200 border-l-4 border-emerald-500 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all"
                                 title="Voir les militaires actifs">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs uppercase tracking-wider font-semibold text-slate-500">Militaires Actifs</span>
                                    <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                                        <i class="pi pi-check-circle text-base"></i>
                                    </div>
                                </div>
                                <div class="text-2xl md:text-3xl font-extrabold text-emerald-600">{{ statistiques.militaires_actifs }}</div>
                                <div class="text-[11px] text-emerald-700 mt-1 flex items-center gap-1 font-medium">
                                    <span>Consulter</span> <i class="pi pi-arrow-right text-[9px]"></i>
                                </div>
                            </div>

                            <!-- 3. Retraités -> renvoie sur /militaires?statut=retraité -->
                            <div @click="router.visit(route('militaires.index', { statut: 'retraité' }))"
                                 class="bg-white rounded-xl shadow-xs p-4 border border-slate-200 border-l-4 border-slate-500 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all"
                                 title="Voir les retraités archivés">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs uppercase tracking-wider font-semibold text-slate-500">Retraités</span>
                                    <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-600">
                                        <i class="pi pi-history text-base"></i>
                                    </div>
                                </div>
                                <div class="text-2xl md:text-3xl font-extrabold text-slate-700">{{ statistiques.total_retraites || 0 }}</div>
                                <div class="text-[11px] text-slate-600 mt-1 flex items-center gap-1 font-medium">
                                    <span>Consulter</span> <i class="pi pi-arrow-right text-[9px]"></i>
                                </div>
                            </div>

                            <!-- 4. Décédés -> renvoie sur /militaires?statut=décédé -->
                            <div @click="router.visit(route('militaires.index', { statut: 'décédé' }))"
                                 class="bg-white rounded-xl shadow-xs p-4 border border-slate-200 border-l-4 border-rose-500 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all"
                                 title="Voir les militaires décédés">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs uppercase tracking-wider font-semibold text-slate-500">Décédés</span>
                                    <div class="w-8 h-8 rounded-full bg-rose-50 flex items-center justify-center text-rose-600">
                                        <i class="pi pi-heart text-base"></i>
                                    </div>
                                </div>
                                <div class="text-2xl md:text-3xl font-extrabold text-rose-700">{{ statistiques.total_decedes || 0 }}</div>
                                <div class="text-[11px] text-rose-700 mt-1 flex items-center gap-1 font-medium">
                                    <span>Consulter</span> <i class="pi pi-arrow-right text-[9px]"></i>
                                </div>
                            </div>
                            
                            <!-- 5. Alertes Non Vues -> renvoie sur /alertes -->
                            <div @click="router.visit(route('alertes.index'))"
                                 class="bg-white rounded-xl shadow-xs p-4 border border-slate-200 border-l-4 border-amber-500 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all"
                                 title="Voir les alertes">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs uppercase tracking-wider font-semibold text-slate-500">Alertes Non Vues</span>
                                    <div class="w-8 h-8 rounded-full bg-amber-50 flex items-center justify-center text-amber-600">
                                        <i class="pi pi-bell text-base"></i>
                                    </div>
                                </div>
                                <div class="text-2xl md:text-3xl font-extrabold text-amber-600">{{ statistiques.alertes_non_vues }}</div>
                                <div class="text-[11px] text-amber-700 mt-1 flex items-center gap-1 font-medium">
                                    <span>Consulter</span> <i class="pi pi-arrow-right text-[9px]"></i>
                                </div>
                            </div>
                        </div>

                        <!-- 2ème groupe : Suivi des départs et avancements (clic affiche direct dans le tableau en bas) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
                            <!-- Retraites N -->
                            <div @click="loadSection('retraitesN')"
                                 :class="activeSection === 'retraitesN' ? 'ring-2 ring-purple-500 shadow-md bg-purple-50/40' : 'bg-white shadow-xs hover:shadow-md'"
                                 class="rounded-xl p-4 border border-slate-200 border-l-4 border-purple-500 cursor-pointer transition-all"
                                 title="Afficher dans le tableau en bas">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs uppercase tracking-wider font-semibold text-slate-500">Retraites N</span>
                                    <div class="w-8 h-8 rounded-full bg-purple-50 flex items-center justify-center text-purple-600">
                                        <i class="pi pi-calendar text-base"></i>
                                    </div>
                                </div>
                                <div class="text-2xl md:text-3xl font-extrabold text-purple-700">{{ retraitesNTotal }}</div>
                                <div class="text-[11px] text-purple-600 mt-1 flex items-center gap-1 font-medium">
                                    <i class="pi pi-table text-[10px]"></i> <span>Afficher dans le tableau</span>
                                </div>
                            </div>
                            
                            <!-- Retraites N+1 -->
                            <div @click="loadSection('retraitesN1')"
                                 :class="activeSection === 'retraitesN1' ? 'ring-2 ring-indigo-500 shadow-md bg-indigo-50/40' : 'bg-white shadow-xs hover:shadow-md'"
                                 class="rounded-xl p-4 border border-slate-200 border-l-4 border-indigo-500 cursor-pointer transition-all"
                                 title="Afficher dans le tableau en bas">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs uppercase tracking-wider font-semibold text-slate-500">Retraites N+1</span>
                                    <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                                        <i class="pi pi-calendar text-base"></i>
                                    </div>
                                </div>
                                <div class="text-2xl md:text-3xl font-extrabold text-indigo-700">{{ retraitesN1Total }}</div>
                                <div class="text-[11px] text-indigo-600 mt-1 flex items-center gap-1 font-medium">
                                    <i class="pi pi-table text-[10px]"></i> <span>Afficher dans le tableau</span>
                                </div>
                            </div>
                            
                            <!-- Proposables N -->
                            <div @click="loadSection('proposablesN')"
                                 :class="activeSection === 'proposablesN' ? 'ring-2 ring-sky-600 shadow-md bg-sky-50/40' : 'bg-white shadow-xs hover:shadow-md'"
                                 class="rounded-xl p-4 border border-slate-200 border-l-4 border-sky-600 cursor-pointer transition-all"
                                 title="Afficher dans le tableau en bas">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs uppercase tracking-wider font-semibold text-slate-500">Proposables N</span>
                                    <div class="w-8 h-8 rounded-full bg-sky-50 flex items-center justify-center text-sky-600">
                                        <i class="pi pi-star text-base"></i>
                                    </div>
                                </div>
                                <div class="text-2xl md:text-3xl font-extrabold text-sky-800">{{ proposablesNTotal }}</div>
                                <div class="text-[11px] text-sky-700 mt-1 flex items-center gap-1 font-medium">
                                    <i class="pi pi-table text-[10px]"></i> <span>Afficher dans le tableau</span>
                                </div>
                            </div>
                            
                            <!-- Proposables N+1 -->
                            <div @click="loadSection('proposablesN1')"
                                 :class="activeSection === 'proposablesN1' ? 'ring-2 ring-teal-600 shadow-md bg-teal-50/40' : 'bg-white shadow-xs hover:shadow-md'"
                                 class="rounded-xl p-4 border border-slate-200 border-l-4 border-teal-600 cursor-pointer transition-all"
                                 title="Afficher dans le tableau en bas">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs uppercase tracking-wider font-semibold text-slate-500">Proposables N+1</span>
                                    <div class="w-8 h-8 rounded-full bg-teal-50 flex items-center justify-center text-teal-600">
                                        <i class="pi pi-star text-base"></i>
                                    </div>
                                </div>
                                <div class="text-2xl md:text-3xl font-extrabold text-teal-700">{{ proposablesN1Total }}</div>
                                <div class="text-[11px] text-teal-700 mt-1 flex items-center gap-1 font-medium">
                                    <i class="pi pi-table text-[10px]"></i> <span>Afficher dans le tableau</span>
                                </div>
                            </div>
                        </div>


                        <div v-if="loading" class="text-center py-12">
                            <i class="pi pi-spin pi-spinner text-3xl text-sky-600"></i>
                            <p class="mt-2 text-gray-500">Chargement...</p>
                        </div>
                        <div v-else-if="sectionData">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">{{ sectionTitle }}</h3>
                                <Button label="Exporter en Excel" 
                                        icon="pi pi-file-excel" 
                                        class="p-button-success p-button-sm"
                                        @click="exportCurrentSection" />
                            </div>
                            <Card>
                                <template #title>
                                    <div class="flex items-center gap-2">
                                        <i :class="sectionIcon"></i>
                                        <span>{{ sectionTitle }}</span>
                                        <Tag :value="sectionTotal" severity="warning" />
                                    </div>
                                </template>
                                <template #content>
                                    <DataTable :value="sectionData" 
                                               stripedRows 
                                               responsiveLayout="scroll"
                                               class="p-datatable-sm"
                                               :rows="10"
                                               paginator
                                               :rowsPerPageOptions="[10, 20, 50, 100]"
                                               :sortField="sortField"
                                               :sortOrder="1">
                                        <Column v-for="col in columns" :key="col.field" :field="col.field" :header="col.header" :sortable="col.sortable">
                                            <template #body="slotProps">
                                                <span v-if="col.field === 'matricule'">
                                                    <Tag :value="slotProps.data.matricule" severity="info" />
                                                </span>
                                                <span v-else-if="col.field === 'grade_cible'">
                                                    <Tag :value="slotProps.data.grade_cible" severity="success" />
                                                </span>
                                                <span v-else-if="col.field === 'grade_actuel'">
                                                    <Tag :value="slotProps.data.grade_actuel" severity="secondary" />
                                                </span>
                                                <div v-else-if="col.field === 'date_proposition_formatted'" class="flex items-center gap-2 whitespace-nowrap">
                                                    <i class="pi pi-calendar"></i>
                                                    <span>{{ slotProps.data.date_proposition_formatted }}</span>
                                                </div>
                                                <div v-else-if="col.field === 'date_retraite_formatted'" class="flex items-center gap-2 whitespace-nowrap">
                                                    <i class="pi pi-calendar"></i>
                                                    <span>{{ slotProps.data.date_retraite_formatted }}</span>
                                                </div>
                                                <div v-else-if="col.field === 'date_anciennete_formatted'" class="flex items-center gap-2 whitespace-nowrap">
                                                    <i class="pi pi-clock"></i>
                                                    <span>{{ slotProps.data.date_anciennete_formatted }}</span>
                                                </div>
                                                <span v-else>{{ slotProps.data[col.field] }}</span>
                                            </template>
                                        </Column>
                                        <template #empty>
                                            <div class="text-center p-8 text-gray-500">
                                                <i class="pi pi-info-circle text-4xl mb-2"></i>
                                                <p>Aucune donnée pour cette section</p>
                                            </div>
                                        </template>
                                    </DataTable>
                                </template>
                            </Card>
                        </div>
                        <div v-else class="text-center py-12 text-gray-500">
                            <i class="pi pi-info-circle text-4xl mb-2"></i>
                            <p>Sélectionnez une section ci-dessus</p>
                        </div>

                        <div class="mt-8">
                            <Card>
                                <template #title>
                                    <div class="flex items-center gap-2">
                                        <i class="pi pi-bell" :class="alertesNonVues.length > 0 ? 'text-yellow-500' : ''"></i>
                                        <span>Alertes récentes ({{ alertesNonVues.length }})</span>
                                    </div>
                                </template>
                                <template #content>
                                    <div v-if="alertesNonVues.length > 0" class="space-y-3">
                                        <div v-for="alerte in alertesNonVues" :key="alerte.id" 
                                             class="p-3 border rounded-lg transition-all hover:shadow-md"
                                             :class="getAlerteClass(alerte.type_alerte)">
                                            <div class="flex justify-between items-start">
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <i class="pi pi-user"></i>
                                                        <strong>{{ alerte.militaire?.nom || 'Inconnu' }} {{ alerte.militaire?.prenom || '' }}</strong>
                                                        <Tag v-if="alerte.militaire?.matricule" :value="alerte.militaire.matricule" severity="info" />
                                                    </div>
                                                    <p class="text-sm">{{ alerte.message }}</p>
                                                    <div class="flex items-center gap-2 mt-2 text-xs text-gray-600">
                                                        <i class="pi pi-calendar"></i>
                                                        <span>Échéance: {{ formatDate(alerte.date_echeance) }}</span>
                                                        <Tag :value="joursRestants(alerte.date_echeance)" 
                                                             :severity="getJoursRestantsSeverity(joursRestants(alerte.date_echeance))" />
                                                    </div>
                                                </div>
                                                <Button icon="pi pi-check" 
                                                        class="p-button-rounded p-button-text p-button-sm"
                                                        :loading="loadingStates[alerte.id]"
                                                        @click="marquerAlerteVue(alerte.id)" />
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-center py-8 text-gray-500">
                                        <i class="pi pi-check-circle text-4xl mb-2 text-green-500"></i>
                                        <p>Aucune alerte pour le moment</p>
                                    </div>
                                </template>
                            </Card>
                        </div>

                        <Card class="mt-8">
                            <template #title>
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-chart-bar"></i>
                                    <span>Répartition par grade</span>
                                </div>
                            </template>
                            <template #content>
                                <DataTable :value="gradesFiltres" stripedRows responsiveLayout="scroll" class="p-datatable-sm">
                                    <Column field="nom_grade" header="Grade">
                                        <template #body="slotProps">
                                            <div class="font-medium">{{ slotProps.data.nom_grade }}</div>
                                            <small class="text-gray-500">{{ slotProps.data.type_grade }}</small>
                                        </template>
                                    </Column>
                                    <Column field="militaires_count" header="Nombre">
                                        <template #body="slotProps">
                                            <Tag :value="slotProps.data.militaires_count" severity="info" />
                                        </template>
                                    </Column>
                                    <Column header="Pourcentage">
                                        <template #body="slotProps">
                                            <div class="flex items-center gap-2">
                                                <ProgressBar :value="calculerPourcentage(slotProps.data.militaires_count)" class="w-48 h-2" />
                                                <span class="text-sm font-medium">{{ calculerPourcentage(slotProps.data.militaires_count).toFixed(1) }}%</span>
                                            </div>
                                        </template>
                                    </Column>
                                </DataTable>
                            </template>
                        </Card>
                    </div>
                </div>
            </div>
        </div>

        <Toast position="top-right" />
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from 'primevue/card';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import ProgressBar from 'primevue/progressbar';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    alertesNonVues: Array,
    statistiques: Object,
    grades: Array,
    initialSection: Object,
});

const toast = useToast();
const loadingStates = ref({});
const activeSection = ref(props.initialSection ? 'retraitesN' : null);
const sectionData = ref(props.initialSection?.data || null);
const sectionTitle = ref(props.initialSection?.title || '');
const sectionIcon = ref(props.initialSection?.icon || '');
const sectionTotal = ref(props.initialSection?.total || 0);
const sortField = ref(props.initialSection?.sortField || '');
const columns = ref(props.initialSection?.columns || []);
const loading = ref(false);

const retraitesNTotal = ref(props.statistiques?.total_retraites_n || props.initialSection?.total || 0);
const retraitesN1Total = ref(props.statistiques?.total_retraites_n1 || 0);
const proposablesNTotal = ref(props.statistiques?.total_proposables_n || 0);
const proposablesN1Total = ref(props.statistiques?.total_proposables_n1 || 0);

const gradesFiltres = computed(() => props.grades.filter(g => g.militaires_count > 0));

const loadSection = (section) => {
    if (activeSection.value === section && sectionData.value) return;
    loading.value = true;
    activeSection.value = section;

    axios.get(route('dashboard.section'), { params: { section } })
        .then(response => {
            const data = response.data;
            sectionData.value = data.data;
            sectionTitle.value = data.title;
            sectionIcon.value = data.icon;
            sectionTotal.value = data.total;
            sortField.value = data.sortField;
            columns.value = data.columns;
            
            switch (section) {
                case 'retraitesN':
                    retraitesNTotal.value = data.total;
                    break;
                case 'retraitesN1':
                    retraitesN1Total.value = data.total;
                    break;
                case 'proposablesN':
                    proposablesNTotal.value = data.total;
                    break;
                case 'proposablesN1':
                    proposablesN1Total.value = data.total;
                    break;
            }
            loading.value = false;
        })
        .catch(error => {
            console.error(error);
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger la section', life: 3000 });
            loading.value = false;
        });
};

onMounted(() => {
    if (!sectionData.value) {
        loadSection('retraitesN');
    }
});

const exportCurrentSection = () => {
    let routeName = '';
    if (activeSection.value === 'retraitesN') routeName = 'dashboard.export-retraites-annee-n';
    else if (activeSection.value === 'retraitesN1') routeName = 'dashboard.export-retraites-annee-n1';
    else if (activeSection.value === 'proposablesN') routeName = 'dashboard.export-proposables-annee-n';
    else if (activeSection.value === 'proposablesN1') routeName = 'dashboard.export-proposables-annee-n1';
    if (routeName) window.open(route(routeName), '_blank');
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('fr-FR');
};

const joursRestants = (date) => {
    if (!date) return 0;
    const diff = Math.ceil((new Date(date) - new Date()) / (1000 * 60 * 60 * 24));
    return diff > 0 ? diff : 0;
};

const getJoursRestantsSeverity = (jours) => {
    if (jours <= 7) return 'danger';
    if (jours <= 30) return 'warning';
    return 'info';
};

const getAlerteClass = (type) => {
    const classes = {
        'promotion': 'border-blue-200 bg-blue-50',
        'formation': 'border-yellow-200 bg-yellow-50',
        'retraite': 'border-red-200 bg-red-50',
    };
    return classes[type] || 'border-gray-200 bg-gray-50';
};

const calculerPourcentage = (count) => {
    if (!props.statistiques || props.statistiques.militaires_actifs === 0) return 0;
    return (count / props.statistiques.militaires_actifs) * 100;
};

const marquerAlerteVue = (alerteId) => {
    loadingStates.value[alerteId] = true;
    router.post(route('alertes.marquer-vue', alerteId), {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Alerte marquée comme vue', life: 3000 });
            loadingStates.value[alerteId] = false;
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de marquer l\'alerte', life: 3000 });
            loadingStates.value[alerteId] = false;
        }
    });
};
</script>

<style scoped>
:deep(.p-card) { border-radius: 12px; overflow: hidden; }
:deep(.p-datatable .p-datatable-tbody > tr:hover) { background-color: #f3f4f6 !important; }
</style>