<script setup>
import { ref, computed, watch } from 'vue';
import { router, Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    organismes: { type: Array, default: () => [] },
    regions: { type: Array, default: () => [] },
    grades: { type: Array, default: () => [] },
    unites: { type: Array, default: () => [] },
    matrice: { type: Object, default: () => ({}) },
    regionsSituation: { type: Object, default: () => ({}) },
    registres: { type: Array, default: () => [] },
    militairesLookup: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const toast = useToast();
const urlParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
const activeTab = ref((urlParams && urlParams.get('tab')) ? urlParams.get('tab') : 'spa');

// Filtres pour les registres (strictement numériques pour fluidité et rapidité)
const filterTypeRegistre = ref(props.filters.type_registre_code ? Number(props.filters.type_registre_code) : null);
const filterRegion = ref(props.filters.region_id ? Number(props.filters.region_id) : null);
const filterGrade = ref(props.filters.grade_id ? Number(props.filters.grade_id) : null);
const filterAnnee = ref(props.filters.annee_evenement ? Number(props.filters.annee_evenement) : null);
const searchQuery = ref(props.filters.search || '');

const appliedFilters = () => {
    const f = {};
    if (filterTypeRegistre.value) f.type_registre_code = filterTypeRegistre.value;
    if (filterRegion.value) f.region_id = filterRegion.value;
    if (filterGrade.value) f.grade_id = filterGrade.value;
    if (filterAnnee.value) f.annee_evenement = filterAnnee.value;
    if (searchQuery.value) f.search = searchQuery.value;
    return f;
};

// Téléchargement Excel Officiel (100% données réelles de la base)
const telechargerExcel = () => {
    window.location.href = route('prise-armes.export');
};

// Filtrage local en temps réel des registres affichés
const registresFiltres = computed(() => {
    let list = props.registres;

    if (activeTab.value === 'blesses') list = list.filter(r => r.type_registre_code === 1);
    else if (activeTab.value === 'disparus') list = list.filter(r => r.type_registre_code === 2);
    else if (activeTab.value === 'malades') list = list.filter(r => r.type_registre_code === 3);
    else if (activeTab.value === 'decedes') list = list.filter(r => r.type_registre_code === 4);
    else if (activeTab.value === 'deserteurs') list = list.filter(r => r.type_registre_code === 5);
    else if (filterTypeRegistre.value) list = list.filter(r => r.type_registre_code === filterTypeRegistre.value);

    if (filterRegion.value) {
        list = list.filter(r => r.region_id === filterRegion.value);
    }
    if (filterGrade.value) {
        list = list.filter(r => r.grade_id === filterGrade.value);
    }
    if (filterAnnee.value) {
        list = list.filter(r => r.annee_evenement === filterAnnee.value);
    }
    if (searchQuery.value && searchQuery.value.trim() !== '') {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(r =>
            (r.matricule && String(r.matricule).toLowerCase().includes(q)) ||
            (r.nom && r.nom.toLowerCase().includes(q)) ||
            (r.prenom && r.prenom.toLowerCase().includes(q)) ||
            (r.unite_texte && r.unite_texte.toLowerCase().includes(q)) ||
            (r.circonstances && r.circonstances.toLowerCase().includes(q))
        );
    }
    return list;
});

// Pagination Registres
const perPage = ref(10);
const perPageOptions = [5, 10, 25, 50, 100];
const currentPage = ref(1);
watch([activeTab, filterTypeRegistre, filterRegion, filterGrade, filterAnnee, searchQuery, perPage], () => {
    currentPage.value = 1;
});
const totalPages = computed(() => Math.ceil(registresFiltres.value.length / perPage.value) || 1);
const visiblePages = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }
    const delta = 2;
    const range = [];
    for (let i = Math.max(2, current - delta); i <= Math.min(total - 1, current + delta); i++) {
        range.push(i);
    }
    if (current - delta > 2) {
        range.unshift('...');
    }
    if (current + delta < total - 1) {
        range.push('...');
    }
    range.unshift(1);
    range.push(total);
    return range;
});
const startIndex = computed(() => (currentPage.value - 1) * perPage.value);
const endIndex = computed(() => Math.min(startIndex.value + perPage.value, registresFiltres.value.length));
const paginatedRegistres = computed(() => {
    return registresFiltres.value.slice(startIndex.value, endIndex.value);
});

// Total colonne matrice helper (calculé en direct)
const getColTotal = (orgCode, gender) => {
    let tot = 0;
    for (const row of Object.values(props.matrice)) {
        if (row && row[orgCode] && typeof row[orgCode][gender] === 'number') {
            tot += row[orgCode][gender];
        }
    }
    return tot;
};

// ==========================================
// MODAL DIALOGS : AJOUT / ÉDITION REGISTRE
// ==========================================
const showRegistreModal = ref(false);
const isEditingRegistre = ref(false);
const editingRegistreId = ref(null);

const formRegistre = ref({
    type_registre_code: 1,
    numero_ordre: null,
    matricule: '',
    nom: '',
    prenom: '',
    grade_id: null,
    region_id: null,
    unite_id: null,
    unite_texte: '',
    date_evenement: '',
    circonstances: '',
    nature_pathologie: '',
    observations: '',
});

const matriculeFoundNotice = ref(null);

// Liste des unités filtrées selon la région sélectionnée
const unitesDeLaRegion = computed(() => {
    if (!formRegistre.value.region_id) {
        return props.unites;
    }
    return props.unites.filter(u => u.region_id === formRegistre.value.region_id);
});

const onRegionChange = () => {
    if (formRegistre.value.unite_id) {
        const u = props.unites.find(item => item.id === formRegistre.value.unite_id);
        if (u && u.region_id !== formRegistre.value.region_id) {
            formRegistre.value.unite_id = null;
            formRegistre.value.unite_texte = '';
        }
    }
};

const onUniteSelectChange = () => {
    if (formRegistre.value.unite_id) {
        const u = props.unites.find(item => item.id === formRegistre.value.unite_id);
        if (u) {
            formRegistre.value.unite_texte = u.nom;
            if (u.region_id && !formRegistre.value.region_id) {
                formRegistre.value.region_id = u.region_id;
            }
        }
    } else {
        formRegistre.value.unite_texte = '';
    }
};

const localMilitairesCache = ref({});
let lookupTimeout = null;

const applyFoundMilitaire = (found) => {
    formRegistre.value.nom = found.nom || formRegistre.value.nom;
    formRegistre.value.prenom = found.prenom || formRegistre.value.prenom;
    if (found.grade_id) formRegistre.value.grade_id = found.grade_id;
    if (found.region_id) formRegistre.value.region_id = found.region_id;
    if (found.unite_id) {
        formRegistre.value.unite_id = found.unite_id;
        const u = props.unites.find(item => item.id === found.unite_id);
        formRegistre.value.unite_texte = u ? u.nom : (found.unite || '');
    } else if (found.unite) {
        const u = props.unites.find(item => item.nom.toLowerCase().trim() === found.unite.toLowerCase().trim());
        if (u) {
            formRegistre.value.unite_id = u.id;
            formRegistre.value.unite_texte = u.nom;
            if (!formRegistre.value.region_id && u.region_id) formRegistre.value.region_id = u.region_id;
        } else {
            formRegistre.value.unite_id = null;
            formRegistre.value.unite_texte = found.unite;
        }
    }
    matriculeFoundNotice.value = `${found.nom} ${found.prenom} (Mle: ${found.matricule})`;
};

const onMatriculeInput = () => {
    if (!formRegistre.value.matricule) {
        matriculeFoundNotice.value = null;
        return;
    }
    const clean = String(formRegistre.value.matricule).trim().toLowerCase();
    if (clean.length < 2) {
        matriculeFoundNotice.value = null;
        return;
    }

    // Vérifier d'abord le cache local
    if (localMilitairesCache.value[clean]) {
        applyFoundMilitaire(localMilitairesCache.value[clean]);
        return;
    }

    // Ou dans les props s'il y en a
    if (props.militairesLookup && props.militairesLookup.length > 0) {
        const found = props.militairesLookup.find(m => String(m.matricule).toLowerCase() === clean);
        if (found) {
            localMilitairesCache.value[clean] = found;
            applyFoundMilitaire(found);
            return;
        }
    }

    // Recherche asynchrone débouncée
    clearTimeout(lookupTimeout);
    lookupTimeout = setTimeout(async () => {
        try {
            const resp = await axios.get(route('prise-armes.lookup-militaire'), { params: { matricule: clean } });
            if (resp.data && resp.data.length > 0) {
                const found = resp.data.find(m => String(m.matricule).toLowerCase() === clean) || resp.data[0];
                localMilitairesCache.value[clean] = found;
                applyFoundMilitaire(found);
            } else {
                matriculeFoundNotice.value = null;
            }
        } catch {
            matriculeFoundNotice.value = null;
        }
    }, 250);
};

const openNewRegistreModal = (typeCode = 1) => {
    isEditingRegistre.value = false;
    editingRegistreId.value = null;
    matriculeFoundNotice.value = null;
    formRegistre.value = {
        type_registre_code: typeCode,
        numero_ordre: registresFiltres.value.length + 1,
        matricule: '',
        nom: '',
        prenom: '',
        grade_id: null,
        region_id: null,
        unite_id: null,
        unite_texte: '',
        date_evenement: '',
        circonstances: '',
        nature_pathologie: '',
        observations: '',
    };
    showRegistreModal.value = true;
};

const openEditRegistreModal = (item) => {
    isEditingRegistre.value = true;
    editingRegistreId.value = item.id;
    matriculeFoundNotice.value = null;
    if (item.matricule) {
        const clean = String(item.matricule).trim().toLowerCase();
        if (localMilitairesCache.value[clean]) {
            const found = localMilitairesCache.value[clean];
            matriculeFoundNotice.value = `${found.nom} ${found.prenom} (Mle: ${found.matricule})`;
        } else {
            axios.get(route('prise-armes.lookup-militaire'), { params: { matricule: clean } })
                .then(resp => {
                    if (resp.data && resp.data.length > 0) {
                        const found = resp.data.find(m => String(m.matricule).toLowerCase() === clean) || resp.data[0];
                        localMilitairesCache.value[clean] = found;
                        matriculeFoundNotice.value = `${found.nom} ${found.prenom} (Mle: ${found.matricule})`;
                    }
                })
                .catch(() => {});
        }
    }

    let resolvedUniteId = item.unite_id || null;
    if (!resolvedUniteId && item.unite_texte) {
        const match = props.unites.find(u => u.nom.toLowerCase().trim() === item.unite_texte.toLowerCase().trim());
        if (match) resolvedUniteId = match.id;
    }
    let resolvedRegionId = item.region_id || null;
    if (!resolvedRegionId && resolvedUniteId) {
        const match = props.unites.find(u => u.id === resolvedUniteId);
        if (match && match.region_id) resolvedRegionId = match.region_id;
    }

    formRegistre.value = {
        type_registre_code: item.type_registre_code,
        numero_ordre: item.numero_ordre,
        matricule: item.matricule || '',
        nom: item.nom || '',
        prenom: item.prenom || '',
        grade_id: item.grade_id,
        region_id: resolvedRegionId,
        unite_id: resolvedUniteId,
        unite_texte: item.unite_texte || '',
        date_evenement: item.date_evenement ? item.date_evenement.substring(0, 10) : '',
        circonstances: item.circonstances || '',
        nature_pathologie: item.nature_pathologie || '',
        observations: item.observations || '',
    };
    showRegistreModal.value = true;
};

const saveRegistre = () => {
    if (!formRegistre.value.nom || formRegistre.value.nom.trim() === '') {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Le nom est obligatoire', life: 3000 });
        return;
    }

    if (isEditingRegistre.value) {
        router.put(route('prise-armes.registres.update', editingRegistreId.value), formRegistre.value, {
            onSuccess: () => {
                showRegistreModal.value = false;
                toast.add({ severity: 'success', summary: 'Succès', detail: 'Entrée mise à jour et synchronisée avec la fiche du militaire.', life: 4000 });
            }
        });
    } else {
        router.post(route('prise-armes.registres.store'), formRegistre.value, {
            onSuccess: () => {
                showRegistreModal.value = false;
                toast.add({ severity: 'success', summary: 'Succès', detail: 'Entrée enregistrée et synchronisée avec la fiche du militaire.', life: 4000 });
            }
        });
    }
};

const deleteDialogVisible = ref(false);
const itemToDelete = ref(null);
const deletingRegistre = ref(false);

const deleteRegistre = (item) => {
    itemToDelete.value = item;
    deleteDialogVisible.value = true;
};

const executeDeleteRegistre = () => {
    if (!itemToDelete.value) return;
    deletingRegistre.value = true;
    router.delete(route('prise-armes.registres.destroy', itemToDelete.value.id), {
        onSuccess: () => {
            deleteDialogVisible.value = false;
            itemToDelete.value = null;
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Entrée supprimée de la base.', life: 3000 });
        },
        onError: () => {
            deleteDialogVisible.value = false;
            itemToDelete.value = null;
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de supprimer cette entrée.', life: 3000 });
        },
        onFinish: () => {
            deletingRegistre.value = false;
        }
    });
};

// Liste ordonnée des 20 régions (19 régions + 1 District de Bamako) depuis la base
const liste20Regions = computed(() => {
    return props.regions.map(r => {
        const sit = props.regionsSituation[r.code] || {};
        return {
            id: r.id,
            code: r.code,
            nom: r.nom,
            GAR: sit.GAR || 0,
            GCA: sit.GCA || 0,
            GDD: sit.GDD || 0,
            GDB: sit.GDB || 0,
            CLM: sit.CLM || 0,
            COL: sit.COL || 0,
            LCL: sit.LCL || 0,
            CES: sit.CES || 0,
            CNE: sit.CNE || 0,
            LTN: sit.LTN || 0,
            SLT: sit.SLT || 0,
            ASP: sit.ASP || 0,
            EOA: sit.EOA || 0,
            MJR: sit.MJR || 0,
            ADC: sit.ADC || 0,
            ADJ: sit.ADJ || 0,
            MDC: sit.MDC || 0,
            MDL: sit.MDL || 0,
            ESOA: sit.ESOA || 0,
            MRG: sit.MRG || 0,
            total: sit.total || 0,
        };
    });
});
</script>

<template>
    <Head title="Situation de Prise d'Armes & Registres Opérationnels" />

    <AuthenticatedLayout>
        <Toast />

        <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
            <!-- EN-TÊTE PRINCIPAL (100% BASE DE DONNÉES EN TEMPS RÉEL) -->
            <div class="bg-gradient-to-r from-sky-950 via-sky-900 to-sky-950 rounded-2xl p-6 text-white shadow-xl relative overflow-hidden border border-sky-800">
                <div class="absolute -right-8 -top-8 w-44 h-44 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>

                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 relative z-10">
                    <div class="flex items-center space-x-4">
                        <div class="w-14 h-14 rounded-2xl bg-white/10 border border-white/20 flex items-center justify-center shadow-inner">
                            <i class="pi pi-file-excel text-2xl text-amber-300"></i>
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-800/80 border border-sky-600/50 text-sky-200">
                                    N° Ref : 4 GRM-SDRH-DAGP
                                </span>
                                <span class="px-2 py-0.5 rounded text-xs bg-emerald-700/80 text-emerald-100 font-medium">
                                    19 Régions + 1 District Bamako (20 Régions)
                                </span>
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-500 text-white flex items-center space-x-1.5 shadow-xs">
                                    <span class="w-2 h-2 rounded-full bg-white inline-block animate-pulse"></span>
                                    <span>Données Réelles en Direct</span>
                                </span>
                            </div>
                            <h1 class="text-2xl font-black tracking-tight mt-1">Situation de Prise d'Armes & Registres</h1>
                            <p class="text-sky-200 text-xs sm:text-sm">
                                Direction Générale de la Gendarmerie Nationale • Données issues directement de la Base
                            </p>
                        </div>
                    </div>

                    <!-- BOUTON EXPORT EXCEL OFFICIEL IDENTIQUE -->
                    <div class="flex items-center space-x-2">
                        <Button
                            @click="telechargerExcel"
                            icon="pi pi-download"
                            label="Exporter Excel Officiel"
                            severity="warning"
                            size="small"
                            class="!rounded-xl !shadow-lg !font-bold"
                        />
                    </div>
                </div>

                <!-- CARTES KPI NUMÉRIQUES EN HAUT (CLIQUABLES POUR NAVIGUER DIRECTEMENT ENTRE LES REGISTRES) -->
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mt-6 pt-5 border-t border-white/15">
                    <button
                        type="button"
                        @click="activeTab = 'spa'"
                        :class="[
                            'rounded-xl p-3 backdrop-blur-sm border transition-all text-left focus:outline-none focus:ring-2 focus:ring-white/70',
                            activeTab === 'spa'
                                ? 'bg-white/25 border-white ring-2 ring-white/90 shadow-lg scale-[1.03]'
                                : 'bg-white/10 border-white/10 hover:bg-white/20 hover:scale-[1.02] cursor-pointer'
                        ]"
                    >
                        <div class="text-sky-200 text-xs font-semibold uppercase tracking-wider flex items-center justify-between">
                            <span>Effectif SPA</span>
                            <i class="pi pi-table text-[10px] opacity-70"></i>
                        </div>
                        <div class="text-xl font-black mt-0.5 text-white">{{ (stats.total_spa || 0).toLocaleString('fr-FR') }}</div>
                        <div class="text-[11px] text-sky-300">{{ stats.total_hommes || 0 }} H • {{ stats.total_dames || 0 }} D</div>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'blesses'"
                        :class="[
                            'rounded-xl p-3 backdrop-blur-sm border transition-all text-left focus:outline-none focus:ring-2 focus:ring-amber-300/70',
                            activeTab === 'blesses'
                                ? 'bg-white/25 border-amber-300 ring-2 ring-amber-300/90 shadow-lg scale-[1.03]'
                                : 'bg-white/10 border-white/10 hover:bg-white/20 hover:scale-[1.02] cursor-pointer'
                        ]"
                    >
                        <div class="text-sky-200 text-xs font-semibold uppercase tracking-wider flex items-center justify-between">
                            <span>Blessés Guerre</span>
                            <i class="pi pi-shield text-[10px] text-amber-300 opacity-80"></i>
                        </div>
                        <div class="text-xl font-black mt-0.5 text-amber-300">{{ stats.total_blesses || 0 }}</div>
                        <div class="text-[11px] text-sky-300">OPEX & Missions</div>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'disparus'"
                        :class="[
                            'rounded-xl p-3 backdrop-blur-sm border transition-all text-left focus:outline-none focus:ring-2 focus:ring-rose-300/70',
                            activeTab === 'disparus'
                                ? 'bg-white/25 border-rose-300 ring-2 ring-rose-300/90 shadow-lg scale-[1.03]'
                                : 'bg-white/10 border-white/10 hover:bg-white/20 hover:scale-[1.02] cursor-pointer'
                        ]"
                    >
                        <div class="text-sky-200 text-xs font-semibold uppercase tracking-wider flex items-center justify-between">
                            <span>Disparus / Otages</span>
                            <i class="pi pi-search text-[10px] text-rose-300 opacity-80"></i>
                        </div>
                        <div class="text-xl font-black mt-0.5 text-rose-300">{{ stats.total_disparus || 0 }}</div>
                        <div class="text-[11px] text-sky-300">Maintenus au fichier</div>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'malades'"
                        :class="[
                            'rounded-xl p-3 backdrop-blur-sm border transition-all text-left focus:outline-none focus:ring-2 focus:ring-cyan-300/70',
                            activeTab === 'malades'
                                ? 'bg-white/25 border-cyan-300 ring-2 ring-cyan-300/90 shadow-lg scale-[1.03]'
                                : 'bg-white/10 border-white/10 hover:bg-white/20 hover:scale-[1.02] cursor-pointer'
                        ]"
                    >
                        <div class="text-sky-200 text-xs font-semibold uppercase tracking-wider flex items-center justify-between">
                            <span>Grands Malades</span>
                            <i class="pi pi-heart text-[10px] text-cyan-300 opacity-80"></i>
                        </div>
                        <div class="text-xl font-black mt-0.5 text-cyan-300">{{ stats.total_grands_malades || 0 }}</div>
                        <div class="text-[11px] text-sky-300">Inaptes / Sédentaires</div>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'decedes'"
                        :class="[
                            'rounded-xl p-3 backdrop-blur-sm border transition-all text-left focus:outline-none focus:ring-2 focus:ring-purple-300/70',
                            activeTab === 'decedes'
                                ? 'bg-white/25 border-purple-300 ring-2 ring-purple-300/90 shadow-lg scale-[1.03]'
                                : 'bg-white/10 border-white/10 hover:bg-white/20 hover:scale-[1.02] cursor-pointer'
                        ]"
                    >
                        <div class="text-sky-200 text-xs font-semibold uppercase tracking-wider flex items-center justify-between">
                            <span>Militaires Décédés</span>
                            <i class="pi pi-times-circle text-[10px] text-purple-200 opacity-80"></i>
                        </div>
                        <div class="text-xl font-black mt-0.5 text-purple-200">{{ stats.total_decedes || 0 }}</div>
                        <div class="text-[11px] text-sky-300">En combat / service</div>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'deserteurs'"
                        :class="[
                            'rounded-xl p-3 backdrop-blur-sm border transition-all text-left focus:outline-none focus:ring-2 focus:ring-orange-300/70',
                            activeTab === 'deserteurs'
                                ? 'bg-white/25 border-orange-300 ring-2 ring-orange-300/90 shadow-lg scale-[1.03]'
                                : 'bg-white/10 border-white/10 hover:bg-white/20 hover:scale-[1.02] cursor-pointer'
                        ]"
                    >
                        <div class="text-sky-200 text-xs font-semibold uppercase tracking-wider flex items-center justify-between">
                            <span>Déserteurs</span>
                            <i class="pi pi-exclamation-triangle text-[10px] text-orange-300 opacity-80"></i>
                        </div>
                        <div class="text-xl font-black mt-0.5 text-orange-300">{{ stats.total_deserteurs || 0 }}</div>
                        <div class="text-[11px] text-sky-300">Avis de recherche</div>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'organismes'"
                        :class="[
                            'rounded-xl p-3 backdrop-blur-sm border transition-all text-left col-span-2 sm:col-span-1 focus:outline-none focus:ring-2 focus:ring-emerald-300/70',
                            activeTab === 'organismes'
                                ? 'bg-white/25 border-emerald-300 ring-2 ring-emerald-300/90 shadow-lg scale-[1.03]'
                                : 'bg-white/10 border-white/10 hover:bg-white/20 hover:scale-[1.02] cursor-pointer'
                        ]"
                    >
                        <div class="text-sky-200 text-xs font-semibold uppercase tracking-wider flex items-center justify-between">
                            <span>Détachés</span>
                            <i class="pi pi-building text-[10px] text-emerald-300 opacity-80"></i>
                        </div>
                        <div class="text-xl font-black mt-0.5 text-emerald-300">{{ stats.total_detaches || 0 }} pax</div>
                        <div class="text-[11px] text-sky-300">{{ stats.total_organismes || 48 }} structures</div>
                    </button>
                </div>
            </div>

            <!-- BARRE DES ONGLETS (IDENTIQUE AU DESIGN DE LA PAGE CONFIGURATION) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-2 flex flex-wrap gap-1">
                <button
                    @click="activeTab = 'spa'"
                    :class="[
                        'px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center space-x-2',
                        activeTab === 'spa'
                            ? 'bg-sky-800 text-white shadow-md'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                    ]"
                >
                    <i class="pi pi-table"></i>
                    <span>Situation Prise d'Armes</span>
                </button>

                <button
                    @click="activeTab = 'regions'"
                    :class="[
                        'px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center space-x-2',
                        activeTab === 'regions'
                            ? 'bg-sky-800 text-white shadow-md'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                    ]"
                >
                    <i class="pi pi-map"></i>
                    <span>Situation par Région (20)</span>
                </button>

                <button
                    @click="activeTab = 'blesses'"
                    :class="[
                        'px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center space-x-2',
                        activeTab === 'blesses'
                            ? 'bg-sky-800 text-white shadow-md'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                    ]"
                >
                    <i class="pi pi-shield"></i>
                    <span>Blessés de Guerre ({{ stats.total_blesses || 0 }})</span>
                </button>

                <button
                    @click="activeTab = 'disparus'"
                    :class="[
                        'px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center space-x-2',
                        activeTab === 'disparus'
                            ? 'bg-sky-800 text-white shadow-md'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                    ]"
                >
                    <i class="pi pi-search"></i>
                    <span>Disparus & Otages ({{ stats.total_disparus || 0 }})</span>
                </button>

                <button
                    @click="activeTab = 'malades'"
                    :class="[
                        'px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center space-x-2',
                        activeTab === 'malades'
                            ? 'bg-sky-800 text-white shadow-md'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                    ]"
                >
                    <i class="pi pi-heart"></i>
                    <span>Grands Malades ({{ stats.total_grands_malades || 0 }})</span>
                </button>

                <button
                    @click="activeTab = 'decedes'"
                    :class="[
                        'px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center space-x-2',
                        activeTab === 'decedes'
                            ? 'bg-sky-800 text-white shadow-md'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                    ]"
                >
                    <i class="pi pi-times-circle"></i>
                    <span>Décédés ({{ stats.total_decedes || 0 }})</span>
                </button>

                <button
                    @click="activeTab = 'deserteurs'"
                    :class="[
                        'px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center space-x-2',
                        activeTab === 'deserteurs'
                            ? 'bg-sky-800 text-white shadow-md'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                    ]"
                >
                    <i class="pi pi-exclamation-triangle"></i>
                    <span>Déserteurs ({{ stats.total_deserteurs || 0 }})</span>
                </button>

                <button
                    @click="activeTab = 'organismes'"
                    :class="[
                        'px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center space-x-2',
                        activeTab === 'organismes'
                            ? 'bg-sky-800 text-white shadow-md'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                    ]"
                >
                    <i class="pi pi-sitemap"></i>
                    <span>48 Organismes</span>
                </button>
            </div>

            <!-- ======================================================== -->
            <!-- TAB 1 : MATRICE DE SITUATION DE PRISE D'ARMES            -->
            <!-- ======================================================== -->
            <div v-if="activeTab === 'spa'" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                    <div>
                        <div class="flex items-center space-x-2">
                            <h2 class="text-lg font-black text-slate-800">Matrice de Répartition par Grade et Organisme</h2>
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                {{ stats.total_spa }} militaires en base ({{ stats.total_hommes }} H, {{ stats.total_dames }} D)
                            </span>
                        </div>
                        <p class="text-xs text-slate-500">
                            17 Grades × 48 Structures de Détachement • Décomposition Hommes (H) / Dames (D) • 100% conforme au tableau DGGN
                        </p>
                    </div>

                    <div class="flex items-center space-x-2">
                        <Button
                            @click="telechargerExcel"
                            icon="pi pi-file-excel"
                            label="Télécharger XLSX"
                            severity="success"
                            size="small"
                            outlined
                            class="!rounded-xl"
                        />
                    </div>
                </div>

                <!-- TABLEAU SCROLLABLE DE LA MATRICE (100% IDENTIQUE À LA STRUCTURE EXCEL) -->
                <div class="overflow-x-auto rounded-xl border border-slate-200 max-h-[650px]">
                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                        <thead class="bg-sky-900 text-white sticky top-0 z-20">
                            <tr>
                                <th rowspan="2" class="px-3 py-2 text-left font-bold border-r border-sky-800 min-w-[120px] bg-sky-950">
                                    GRADES
                                </th>
                                <template v-for="org in organismes" :key="org.id">
                                    <th colspan="2" class="px-2 py-1 text-center font-bold border-r border-sky-800 whitespace-nowrap text-[11px]" :title="org.nom">
                                        {{ org.code }}
                                    </th>
                                </template>
                                <th colspan="3" class="px-3 py-1 text-center font-black bg-amber-600 text-white">
                                    TOTAUX
                                </th>
                            </tr>
                            <tr class="bg-sky-800 text-[10px] text-sky-200">
                                <template v-for="org in organismes" :key="'sub_' + org.id">
                                    <th class="px-1.5 py-1 text-center border-r border-sky-700 font-semibold">H</th>
                                    <th class="px-1.5 py-1 text-center border-r border-sky-700 font-semibold">D</th>
                                </template>
                                <th class="px-2 py-1 text-center bg-amber-700 text-white font-bold">H</th>
                                <th class="px-2 py-1 text-center bg-amber-700 text-white font-bold">D</th>
                                <th class="px-2 py-1 text-center bg-amber-800 text-white font-black">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr
                                v-for="(ligne, gradeNom) in matrice"
                                :key="gradeNom"
                                class="hover:bg-sky-50/60 transition-colors"
                            >
                                <td class="px-3 py-1.5 font-bold text-slate-800 bg-slate-50 border-r border-slate-200 whitespace-nowrap sticky left-0 z-10">
                                    {{ gradeNom }}
                                </td>
                                <template v-for="org in organismes" :key="gradeNom + '_' + org.id">
                                    <td class="px-1.5 py-1 text-center border-r border-slate-100 text-slate-700">
                                        {{ ligne[org.code]?.h ? ligne[org.code].h : '' }}
                                    </td>
                                    <td class="px-1.5 py-1 text-center border-r border-slate-100 text-rose-600 font-medium">
                                        {{ ligne[org.code]?.d ? ligne[org.code].d : '' }}
                                    </td>
                                </template>
                                <td class="px-2 py-1 text-center font-bold bg-amber-50 text-slate-800 border-l border-amber-200">
                                    {{ ligne.total_h ?? 0 }}
                                </td>
                                <td class="px-2 py-1 text-center font-bold bg-amber-50 text-rose-700">
                                    {{ ligne.total_d ?? 0 }}
                                </td>
                                <td class="px-2 py-1 text-center font-black bg-amber-100 text-sky-950">
                                    {{ ligne.total ?? 0 }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-slate-100 font-black text-slate-900 sticky bottom-0 z-20 border-t-2 border-slate-300">
                            <tr>
                                <td class="px-3 py-2 text-left sticky left-0 bg-slate-200 z-10 border-r border-slate-300">
                                    TOTAL GENERAL
                                </td>
                                <template v-for="org in organismes" :key="'tot_' + org.id">
                                    <td class="px-1.5 py-2 text-center border-r border-slate-200">
                                        {{ getColTotal(org.code, 'h') || '' }}
                                    </td>
                                    <td class="px-1.5 py-2 text-center border-r border-slate-200 text-rose-700">
                                        {{ getColTotal(org.code, 'd') || '' }}
                                    </td>
                                </template>
                                <td class="px-2 py-2 text-center bg-amber-200 text-slate-900 border-l border-amber-300">
                                    {{ stats.total_hommes || 0 }}
                                </td>
                                <td class="px-2 py-2 text-center bg-amber-200 text-rose-800">
                                    {{ stats.total_dames || 0 }}
                                </td>
                                <td class="px-2 py-2 text-center bg-amber-300 text-sky-950 text-sm">
                                    {{ stats.total_spa || 0 }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- TAB 2 : SITUATION PAR RÉGION (19 + 1 DISTRICT BAMAKO)    -->
            <!-- ======================================================== -->
            <div v-if="activeTab === 'regions'" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div>
                        <div class="flex items-center space-x-2">
                            <h2 class="text-lg font-black text-slate-800">Déploiement Territorial des 20 Régions du Mali</h2>
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                {{ stats.total_spa }} militaires répartis
                            </span>
                        </div>
                        <p class="text-xs text-slate-500">
                            District de Bamako (R1) et les 19 Régions Militaires (R2 à R20) avec décompte par grade
                        </p>
                    </div>

                    <Button
                        @click="telechargerExcel"
                        icon="pi pi-download"
                        label="Exporter Régions (Excel)"
                        severity="info"
                        size="small"
                        outlined
                        class="!rounded-xl"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="reg in liste20Regions"
                        :key="reg.code"
                        class="bg-slate-50 rounded-2xl border border-slate-200 p-4 hover:shadow-md transition-shadow"
                    >
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded-lg text-xs font-bold bg-sky-800 text-white">
                                    {{ reg.code }}
                                </span>
                                <h3 class="font-bold text-sm text-slate-800 truncate">{{ reg.nom }}</h3>
                            </div>
                            <span class="text-sm font-black text-sky-900 bg-sky-100 px-2.5 py-0.5 rounded-full">
                                {{ reg.total }} pax
                            </span>
                        </div>

                        <div class="grid grid-cols-4 gap-1.5 text-[11px] text-center">
                            <!-- LIGNE 1 : OFFICIERS GÉNÉRAUX (DORÉ / AMBRE) -->
                            <div class="bg-amber-50/80 p-1 rounded border border-amber-200 shadow-2xs" title="Général d'Armée">
                                <div class="text-[9px] text-amber-800 font-extrabold">GAR</div>
                                <div class="font-black text-amber-950">{{ reg.GAR || 0 }}</div>
                            </div>
                            <div class="bg-amber-50/80 p-1 rounded border border-amber-200 shadow-2xs" title="Général de Corps d'Armée">
                                <div class="text-[9px] text-amber-800 font-extrabold">GCA</div>
                                <div class="font-black text-amber-950">{{ reg.GCA || 0 }}</div>
                            </div>
                            <div class="bg-amber-50/80 p-1 rounded border border-amber-200 shadow-2xs" title="Général de Division">
                                <div class="text-[9px] text-amber-800 font-extrabold">GDD</div>
                                <div class="font-black text-amber-950">{{ reg.GDD || 0 }}</div>
                            </div>
                            <div class="bg-amber-50/80 p-1 rounded border border-amber-200 shadow-2xs" title="Général de Brigade">
                                <div class="text-[9px] text-amber-800 font-extrabold">GDB</div>
                                <div class="font-black text-amber-950">{{ reg.GDB || 0 }}</div>
                            </div>

                            <!-- LIGNE 2 : OFFICIERS SUPÉRIEURS -->
                            <div class="bg-white p-1 rounded border border-slate-200" title="Colonel-Major">
                                <div class="text-[9px] text-sky-800 font-bold">CLM</div>
                                <div class="font-bold text-slate-800">{{ reg.CLM || 0 }}</div>
                            </div>
                            <div class="bg-white p-1 rounded border border-slate-100" title="Colonel">
                                <div class="text-[9px] text-sky-800 font-bold">COL</div>
                                <div class="font-bold text-slate-700">{{ reg.COL || 0 }}</div>
                            </div>
                            <div class="bg-white p-1 rounded border border-slate-100" title="Lieutenant-Colonel">
                                <div class="text-[9px] text-sky-800 font-bold">LCL</div>
                                <div class="font-bold text-slate-700">{{ reg.LCL || 0 }}</div>
                            </div>
                            <div class="bg-white p-1 rounded border border-slate-100" title="Chef d'Escadron / Commandant">
                                <div class="text-[9px] text-sky-800 font-bold">CES</div>
                                <div class="font-bold text-slate-700">{{ reg.CES || 0 }}</div>
                            </div>

                            <!-- LIGNE 3 : OFFICIERS SUBALTERNES -->
                            <div class="bg-white p-1 rounded border border-slate-100" title="Capitaine">
                                <div class="text-[9px] text-slate-600 font-bold">CNE</div>
                                <div class="font-bold text-slate-700">{{ reg.CNE || 0 }}</div>
                            </div>
                            <div class="bg-white p-1 rounded border border-slate-100" title="Lieutenant">
                                <div class="text-[9px] text-slate-600 font-bold">LTN</div>
                                <div class="font-bold text-slate-700">{{ reg.LTN || 0 }}</div>
                            </div>
                            <div class="bg-white p-1 rounded border border-slate-100" title="Sous-Lieutenant">
                                <div class="text-[9px] text-slate-600 font-bold">SLT</div>
                                <div class="font-bold text-slate-700">{{ reg.SLT || 0 }}</div>
                            </div>
                            <div class="bg-white p-1 rounded border border-slate-100" title="Aspirant">
                                <div class="text-[9px] text-sky-700 font-bold">ASP</div>
                                <div class="font-bold text-slate-700">{{ reg.ASP || 0 }}</div>
                            </div>

                            <!-- LIGNE 4 : ÉLÈVES OFFICIERS & SOUS-OFFICIERS SUPÉRIEURS -->
                            <div class="bg-white p-1 rounded border border-slate-100" title="Élève Officier d'Active">
                                <div class="text-[9px] text-sky-700 font-bold">EOA</div>
                                <div class="font-bold text-slate-700">{{ reg.EOA || 0 }}</div>
                            </div>
                            <div class="bg-white p-1 rounded border border-slate-100" title="Adjudant-Chef Major / Major">
                                <div class="text-[9px] text-slate-500 font-bold">MJR</div>
                                <div class="font-bold text-slate-700">{{ reg.MJR || 0 }}</div>
                            </div>
                            <div class="bg-white p-1 rounded border border-slate-100" title="Adjudant-Chef">
                                <div class="text-[9px] text-slate-500 font-bold">ADC</div>
                                <div class="font-bold text-slate-700">{{ reg.ADC || 0 }}</div>
                            </div>
                            <div class="bg-white p-1 rounded border border-slate-100" title="Adjudant">
                                <div class="text-[9px] text-slate-400 font-bold">ADJ</div>
                                <div class="font-bold text-slate-700">{{ reg.ADJ || 0 }}</div>
                            </div>

                            <!-- LIGNE 5 : SOUS-OFFICIERS SUBALTERNES, ÉLÈVES SOUS-OFF & MILITAIRES DU RANG -->
                            <div class="bg-white p-1 rounded border border-slate-100" title="Maréchal des Logis-Chef">
                                <div class="text-[9px] text-slate-400 font-bold">MDC</div>
                                <div class="font-bold text-slate-700">{{ reg.MDC || 0 }}</div>
                            </div>
                            <div class="bg-white p-1 rounded border border-slate-100" title="Maréchal des Logis">
                                <div class="text-[9px] text-slate-400 font-bold">MDL</div>
                                <div class="font-bold text-slate-700">{{ reg.MDL || 0 }}</div>
                            </div>
                            <div class="bg-white p-1 rounded border border-slate-100" title="Élève Sous-Officier d'Active">
                                <div class="text-[9px] text-slate-500 font-bold">ESOA</div>
                                <div class="font-bold text-slate-700">{{ reg.ESOA || 0 }}</div>
                            </div>
                            <div class="bg-white p-1 rounded border border-slate-100" title="Militaire du Rang / Gendarmes / Soldats">
                                <div class="text-[9px] text-slate-400 font-bold">MRG</div>
                                <div class="font-bold text-slate-700">{{ reg.MRG || 0 }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- TABS 3 À 7 : REGISTRES OPÉRATIONNELS                     -->
            <!-- ======================================================== -->
            <div
                v-if="['blesses', 'disparus', 'malades', 'decedes', 'deserteurs'].includes(activeTab)"
                class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4"
            >
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                    <div>
                        <h2 class="text-lg font-black text-slate-800 capitalize">
                            Registre des {{ activeTab === 'blesses' ? 'Blessés de Guerre' : activeTab === 'disparus' ? 'Portés Disparus & Otages' : activeTab === 'malades' ? 'Grands Malades & Inaptes' : activeTab === 'decedes' ? 'Militaires Décédés' : 'Déserteurs' }}
                        </h2>
                        <p class="text-xs text-slate-500">
                            {{ registresFiltres.length }} enregistrement(s) dans la base de données
                        </p>
                    </div>

                    <div class="flex items-center space-x-2">
                        <Button
                            @click="openNewRegistreModal(activeTab === 'blesses' ? 1 : activeTab === 'disparus' ? 2 : activeTab === 'malades' ? 3 : activeTab === 'decedes' ? 4 : 5)"
                            icon="pi pi-plus"
                            label="Ajouter une entrée"
                            size="small"
                            class="!rounded-xl !bg-sky-800"
                        />
                    </div>
                </div>

                <!-- FILTRES STRICTEMENT NUMÉRIQUES POUR LA FLUIDITÉ ET LA RAPIDITÉ -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Recherche (Mle, Nom, Unité)</label>
                        <InputText
                            v-model="searchQuery"
                            placeholder="Ex: 9059, KONE, Gao..."
                            class="w-full !text-xs !py-1.5 !rounded-lg"
                        />
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Région (Chiffre ID)</label>
                        <select
                            v-model="filterRegion"
                            class="w-full bg-white border border-slate-300 rounded-lg p-1.5 text-xs text-slate-700 focus:outline-none"
                        >
                            <option :value="null">Toutes les régions (20)</option>
                            <option v-for="r in regions" :key="r.id" :value="r.id">[{{ r.code }}] {{ r.nom }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Grade (Chiffre ID)</label>
                        <select
                            v-model="filterGrade"
                            class="w-full bg-white border border-slate-300 rounded-lg p-1.5 text-xs text-slate-700 focus:outline-none"
                        >
                            <option :value="null">Tous les grades</option>
                            <option v-for="g in grades" :key="g.id" :value="g.id">[{{ g.code_grade }}] {{ g.nom_grade }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Année Événement</label>
                        <InputText
                            v-model.number="filterAnnee"
                            type="number"
                            placeholder="Ex: 2020, 2021..."
                            class="w-full !text-xs !py-1.5 !rounded-lg"
                        />
                    </div>
                </div>

                <!-- TABLEAU DES REGISTRES -->
                <div class="overflow-x-auto rounded-xl border border-slate-200 shadow-2xs">
                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                        <thead class="bg-slate-100 text-slate-700 font-bold">
                            <tr>
                                <th class="px-3 py-2.5 text-left w-12">N°</th>
                                <th class="px-3 py-2.5 text-left">Grade</th>
                                <th class="px-3 py-2.5 text-left">Prénoms & Nom</th>
                                <th class="px-3 py-2.5 text-left">Matricule</th>
                                <th class="px-3 py-2.5 text-left">Région</th>
                                <th class="px-3 py-2.5 text-left">Unité d'affectation</th>
                                <th v-if="activeTab === 'malades'" class="px-3 py-2.5 text-left">Pathologie / Aménagement</th>
                                <th v-else class="px-3 py-2.5 text-left">Circonstances</th>
                                <th class="px-3 py-2.5 text-left">Date</th>
                                <th class="px-3 py-2.5 text-center w-24">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="paginatedRegistres.length === 0">
                                <td colspan="10" class="px-4 py-12 text-center text-slate-400 text-xs">
                                    <div class="flex flex-col items-center justify-center space-y-2">
                                        <i class="pi pi-inbox text-3xl text-slate-300"></i>
                                        <p class="font-medium text-slate-500">Aucun enregistrement dans la base de données pour ce registre.</p>
                                        <p class="text-[11px] text-slate-400">Cliquez sur « Ajouter une entrée » ci-dessus pour insérer une nouvelle ligne.</p>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="(item, idx) in paginatedRegistres"
                                :key="item.id"
                                class="hover:bg-slate-50/80 transition-colors"
                            >
                                <td class="px-3 py-2 font-mono text-slate-400 text-[11px]">
                                    {{ item.numero_ordre ?? (startIndex + idx + 1) }}
                                </td>
                                <td class="px-3 py-2 font-bold text-slate-800 whitespace-nowrap">
                                    <Tag :value="item.grade_texte || '-'" severity="info" class="!text-[10px] !py-0" />
                                </td>
                                <td class="px-3 py-2 font-bold whitespace-nowrap">
                                    <Link
                                        v-if="item.militaire_id"
                                        :href="route('militaires.show', item.militaire_id)"
                                        class="text-sky-950 hover:text-sky-700 hover:underline inline-flex items-center gap-1 group font-bold"
                                        title="Consulter la fiche individuelle du militaire"
                                    >
                                        <span>{{ item.prenom }} {{ item.nom }}</span>
                                        <i class="pi pi-external-link text-[10px] text-sky-500 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                    </Link>
                                    <span v-else class="text-slate-900 font-bold">
                                        {{ item.prenom }} {{ item.nom }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-mono whitespace-nowrap">
                                    <Link
                                        v-if="item.militaire_id"
                                        :href="route('militaires.show', item.militaire_id)"
                                        class="text-sky-800 hover:text-sky-950 font-semibold hover:underline"
                                        title="Voir la fiche détaillée"
                                    >
                                        {{ item.matricule || '-' }}
                                    </Link>
                                    <span v-else class="text-slate-600 font-semibold">
                                        {{ item.matricule || '-' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <span v-if="item.region || item.unite?.region" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold bg-sky-50 text-sky-800 border border-sky-200 shadow-2xs">
                                        <span class="font-mono text-[10px] text-sky-600 font-bold">{{ (item.region || item.unite?.region)?.code }}</span>
                                        <span>{{ (item.region || item.unite?.region)?.nom }}</span>
                                    </span>
                                    <span v-else class="text-slate-400 text-xs italic">-</span>
                                </td>
                                <td class="px-3 py-2 text-slate-700 font-medium">
                                    {{ item.unite_texte || item.unite?.nom || '-' }}
                                </td>
                                <td v-if="activeTab === 'malades'" class="px-3 py-2 text-slate-700 max-w-xs truncate" :title="item.nature_pathologie">
                                    {{ item.nature_pathologie || '-' }}
                                </td>
                                <td v-else class="px-3 py-2 text-slate-700 max-w-xs truncate" :title="item.circonstances">
                                    {{ item.circonstances || '-' }}
                                </td>
                                <td class="px-3 py-2 text-slate-500 whitespace-nowrap text-[11px]">
                                    {{ item.date_evenement ? item.date_evenement.substring(0, 10) : (item.annee_evenement ? 'En ' + item.annee_evenement : '-') }}
                                </td>
                                <td class="px-3 py-2 text-center whitespace-nowrap space-x-1">
                                    <button
                                        @click="openEditRegistreModal(item)"
                                        class="p-1 rounded text-sky-700 hover:bg-sky-50 transition-colors"
                                        title="Modifier"
                                    >
                                        <i class="pi pi-pencil text-xs"></i>
                                    </button>
                                    <button
                                        @click="deleteRegistre(item)"
                                        class="p-1 rounded text-rose-600 hover:bg-rose-50 transition-colors"
                                        title="Supprimer"
                                    >
                                        <i class="pi pi-trash text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION TOUJOURS VISIBLE ET INTERACTIVE -->
                <div v-if="registresFiltres.length > 0" class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-200 text-xs">
                    <div class="flex items-center gap-2 text-slate-600">
                        <span>Affichage de <strong>{{ startIndex + 1 }}</strong> à <strong>{{ endIndex }}</strong> sur <strong>{{ registresFiltres.length }}</strong> enregistrements</span>
                        <span class="text-slate-300">|</span>
                        <div class="flex items-center gap-1.5">
                            <span class="text-slate-500">Lignes par page :</span>
                            <select
                                v-model.number="perPage"
                                class="bg-white border border-slate-300 rounded px-2 py-0.5 text-xs text-slate-700 font-semibold focus:outline-hidden focus:ring-1 focus:ring-sky-500"
                            >
                                <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <Button
                            @click="currentPage = Math.max(1, currentPage - 1)"
                            :disabled="currentPage <= 1"
                            icon="pi pi-chevron-left"
                            label="Précédent"
                            size="small"
                            outlined
                            severity="secondary"
                            class="!text-xs !py-1 !px-2.5"
                        />
                        <div class="flex items-center gap-1 px-1">
                            <template v-for="(p, idx) in visiblePages" :key="idx">
                                <span
                                    v-if="p === '...'"
                                    class="w-6 text-center text-xs text-slate-400 select-none"
                                >
                                    ...
                                </span>
                                <button
                                    v-else
                                    @click="currentPage = p"
                                    :class="[
                                        'w-7 h-7 rounded text-xs font-bold transition-colors flex items-center justify-center cursor-pointer',
                                        currentPage === p
                                            ? 'bg-sky-800 text-white shadow-xs'
                                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                                    ]"
                                >
                                    {{ p }}
                                </button>
                            </template>
                        </div>
                        <Button
                            @click="currentPage = Math.min(totalPages, currentPage + 1)"
                            :disabled="currentPage >= totalPages"
                            icon="pi pi-chevron-right"
                            iconPos="right"
                            label="Suivant"
                            size="small"
                            outlined
                            severity="secondary"
                            class="!text-xs !py-1 !px-2.5"
                        />
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- TAB 8 : GESTION DES ORGANISMES DE DÉTACHEMENT (48)      -->
            <!-- ======================================================== -->
            <div v-if="activeTab === 'organismes'" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div>
                        <h2 class="text-lg font-black text-slate-800">Organismes de Détachement et d'Emploi ({{ organismes.length }})</h2>
                        <p class="text-xs text-slate-500">
                            Structures extérieures et ministères figurant dans la Situation de Prise d'Armes officielle
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <div
                        v-for="org in organismes"
                        :key="org.id"
                        class="p-3 bg-slate-50 rounded-xl border border-slate-200 flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="px-2 py-0.5 rounded text-xs font-black bg-sky-900 text-white">
                                    {{ org.code }}
                                </span>
                                <span v-if="org.est_organique" class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">
                                    Organique
                                </span>
                            </div>
                            <h4 class="text-xs font-semibold text-slate-800 leading-tight mt-1">{{ org.nom }}</h4>
                        </div>
                        <div class="text-[10px] text-slate-400 mt-2 font-mono">
                            Ordre affichage: {{ org.ordre }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- DIALOG MODAL : AJOUTER / MODIFIER UNE ENTRÉE DU REGISTRE -->
        <!-- ======================================================== -->
        <Dialog
            v-model:visible="showRegistreModal"
            modal
            :header="isEditingRegistre ? 'Modifier l\'entrée du registre' : 'Ajouter une entrée au registre'"
            :style="{ width: '550px' }"
            class="!rounded-2xl"
        >
            <div class="space-y-4 pt-2 text-xs">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Type de Registre</label>
                        <select
                            v-model="formRegistre.type_registre_code"
                            class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs text-slate-800"
                        >
                            <option :value="1">1 - Blessé de guerre / OPEX</option>
                            <option :value="2">2 - Porté disparu / Otage</option>
                            <option :value="3">3 - Non mobilisable / Grand malade</option>
                            <option :value="4">4 - Décédé en service / OPEX</option>
                            <option :value="5">5 - Déserteur</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Matricule</label>
                        <InputText
                            v-model="formRegistre.matricule"
                            @input="onMatriculeInput"
                            placeholder="Ex: 9059"
                            class="w-full !text-xs !p-2"
                        />
                        <div v-if="matriculeFoundNotice" class="mt-1 text-[11px] text-emerald-700 font-bold flex items-center gap-1">
                            <i class="pi pi-check-circle text-xs text-emerald-600"></i>
                            <span class="truncate">Fiche liée : {{ matriculeFoundNotice }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nom *</label>
                        <InputText v-model="formRegistre.nom" placeholder="Ex: DOUMBIA" class="w-full !text-xs !p-2" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Prénom(s)</label>
                        <InputText v-model="formRegistre.prenom" placeholder="Ex: Cheick Oumar" class="w-full !text-xs !p-2" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Grade</label>
                        <select
                            v-model="formRegistre.grade_id"
                            class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs text-slate-800"
                        >
                            <option :value="null">Sélectionner un grade</option>
                            <option v-for="g in grades" :key="g.id" :value="g.id">{{ g.nom_grade }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Région Militaire</label>
                        <select
                            v-model="formRegistre.region_id"
                            @change="onRegionChange"
                            class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-600 focus:border-sky-600"
                        >
                            <option :value="null">Sélectionner une région</option>
                            <option v-for="r in regions" :key="r.id" :value="r.id">[{{ r.code }}] {{ r.nom }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block font-bold text-slate-700">Unité d'affectation</label>
                            <span v-if="formRegistre.region_id" class="text-[10px] text-sky-700 font-semibold">
                                {{ unitesDeLaRegion.length }} unité(s)
                            </span>
                        </div>
                        <select
                            v-model="formRegistre.unite_id"
                            @change="onUniteSelectChange"
                            class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-600 focus:border-sky-600 truncate"
                        >
                            <option :value="null">-- Choisir une unité de la région --</option>
                            <option v-for="u in unitesDeLaRegion" :key="u.id" :value="u.id">
                                {{ u.nom }}
                            </option>
                        </select>
                        <div v-if="formRegistre.unite_texte && !formRegistre.unite_id" class="mt-1 text-[11px] text-amber-800 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 truncate" :title="formRegistre.unite_texte">
                            <i class="pi pi-info-circle text-[10px] mr-1"></i>
                            <span>Actuelle : {{ formRegistre.unite_texte }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Date de l'événement</label>
                        <InputText v-model="formRegistre.date_evenement" type="date" class="w-full !text-xs !p-2" />
                    </div>
                </div>

                <div v-if="formRegistre.type_registre_code === 3">
                    <label class="block font-bold text-slate-700 mb-1">Nature de la Pathologie / Aménagement</label>
                    <textarea
                        v-model="formRegistre.nature_pathologie"
                        rows="2"
                        placeholder="Ex: Diabète grave, Amputation, Emploi sédentaire requis..."
                        class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs text-slate-800 focus:outline-none"
                    ></textarea>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Circonstances / Faits</label>
                    <textarea
                        v-model="formRegistre.circonstances"
                        rows="2"
                        placeholder="Ex: Blessé par éclat de grenade lors de l'attaque..."
                        class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs text-slate-800 focus:outline-none"
                    ></textarea>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Observations</label>
                    <InputText v-model="formRegistre.observations" placeholder="Ex: Opération, Mission commandée, À maintenir au fichier..." class="w-full !text-xs !p-2" />
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end space-x-2 pt-3">
                    <Button
                        label="Annuler"
                        icon="pi pi-times"
                        text
                        size="small"
                        @click="showRegistreModal = false"
                    />
                    <Button
                        :label="isEditingRegistre ? 'Enregistrer les modifications' : 'Ajouter à la base'"
                        icon="pi pi-check"
                        size="small"
                        class="!bg-sky-800"
                        @click="saveRegistre"
                    />
                </div>
            </template>
        </Dialog>

        <!-- Modal confirmation suppression registre in-app -->
        <Dialog
            v-model:visible="deleteDialogVisible"
            header="Confirmation de suppression"
            modal
            :style="{ width: '420px' }"
        >
            <div class="flex items-start gap-3 pt-2 text-xs">
                <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0 text-base">
                    <i class="pi pi-exclamation-triangle"></i>
                </div>
                <div>
                    <p class="font-bold text-slate-800 text-sm mb-1">Supprimer cette entrée ?</p>
                    <p class="text-slate-600">
                        Êtes-vous sûr de vouloir supprimer {{ itemToDelete?.grade_texte || '' }} {{ itemToDelete?.nom || '' }} de la base de données ?
                    </p>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2 pt-2">
                    <Button label="Annuler" text severity="secondary" size="small" @click="deleteDialogVisible = false" />
                    <Button label="Supprimer" icon="pi pi-trash" severity="danger" size="small" :loading="deletingRegistre" @click="executeDeleteRegistre" />
                </div>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
