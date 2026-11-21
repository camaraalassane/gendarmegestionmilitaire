<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Badge from 'primevue/badge';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import Checkbox from 'primevue/checkbox';
import { useToast } from 'primevue/usetoast';
import debounce from 'lodash/debounce';
import { downloadExport } from '@/Utils/exportHelper';

const toast = useToast();
const loading = ref(false);
const deleteDialogVisible = ref(false);
const militaireToDelete = ref(null);
const exportDialogVisible = ref(false);
const exporting = ref(false);
const exportProgressText = ref('');

// Sélection multiple et impression de certificats de présence
const selectedMilitaires = ref([]);
const batchCertificatDialogVisible = ref(false);
const batchAutoriteSignataire = ref("Colonel Moussa Ousmane DAO");
const batchTitreSignataire = ref("Sous-directeur des Ressources Humaines");
const batchDecorationSignataire = ref("Chevalier de l'Ordre National");
const batchMotifCertificat = ref("Constitution d'un Dossier Administratif.");
const batchNumeroPrefix = ref("3721/2 GRM-SDRH-DAGP.");
const batchDateCertificat = ref(new Date().toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' }));

const openBatchCertificatsModal = () => {
    if (selectedMilitaires.value.length === 0) return;
    batchCertificatDialogVisible.value = true;
};

const openSingleCertificat = (militaire) => {
    selectedMilitaires.value = [militaire];
    batchCertificatDialogVisible.value = true;
};

const removeSelectedMilitaire = (id) => {
    selectedMilitaires.value = selectedMilitaires.value.filter(m => m.id !== id);
    if (selectedMilitaires.value.length === 0) {
        batchCertificatDialogVisible.value = false;
    }
};

const escapeHtml = (val) => {
    if (val === null || val === undefined) return '';
    return String(val)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

const printBatchCertificats = () => {
    if (!selectedMilitaires.value || selectedMilitaires.value.length === 0) return;

    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    document.body.appendChild(iframe);

    const doc = iframe.contentWindow.document;
    doc.open();

    const certsHtml = selectedMilitaires.value.map((m) => `
        <div class="certificat-page">
            <div class="header-flex">
                <div class="header-left">
                    <div>Ministère de la Défense et<br>des Anciens Combattants</div>
                    <div class="stars">***************</div>
                    <div>Gendarmerie Nationale</div>
                    <div class="stars">***********</div>
                    <div>Direction Générale</div>
                    <div class="num-ref">N° ${escapeHtml(batchNumeroPrefix.value)}</div>
                </div>
                <div class="header-right">
                    <div>République du Mali</div>
                    <div class="devise">Un Peuple – Un But – Une Foi</div>
                    <div class="stars">***************</div>
                    <div class="date-loc">Bamako, le <strong>${escapeHtml(batchDateCertificat.value)}</strong></div>
                </div>
            </div>

            <div class="cert-title">
                <h1>Certificat de Présence au Corps</h1>
            </div>

            <div class="cert-body">
                Nous, soussignés, <strong>${escapeHtml(batchAutoriteSignataire.value)}</strong>, 
                ${escapeHtml(batchTitreSignataire.value)} de la Gendarmerie Nationale, certifions que l'<strong>${escapeHtml(m.grade_actuel || '')} ${escapeHtml(m.prenom || '')} ${escapeHtml(m.nom || '')}</strong> 
                matricule <strong>${escapeHtml(m.matricule || '')}</strong>${m.mle_grm ? ' (MLE GRM : ' + escapeHtml(m.mle_grm) + ')' : ''}, 
                est en service à la Gendarmerie nationale.
            </div>

            <div class="cert-motif">
                <strong>MOTIF</strong> : ${escapeHtml(batchMotifCertificat.value)}
            </div>

            <div class="sign-row">
                <div class="sign-box">
                    <div class="sign-title">Le ${escapeHtml(batchTitreSignataire.value)}</div>
                    <div class="sign-name">${escapeHtml(batchAutoriteSignataire.value)}</div>
                    <div class="sign-distinction">${escapeHtml(batchDecorationSignataire.value)}</div>
                </div>
            </div>
        </div>
    `).join('\\n');

    doc.write(`
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="utf-8">
            <title>Certificats de Présence au Corps</title>
            <style>
                @page {
                    size: A4 portrait;
                    margin: 14mm 20mm 12mm 20mm;
                }
                * {
                    box-sizing: border-box;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                html, body {
                    margin: 0;
                    padding: 0;
                    background: #ffffff;
                    color: #000000;
                    font-family: 'Times New Roman', Times, serif;
                    font-size: 12pt;
                    line-height: 1.5;
                }
                .certificat-page {
                    width: 100%;
                    max-width: 175mm;
                    margin: 0 auto;
                    page-break-inside: avoid;
                    break-inside: avoid;
                    page-break-after: always;
                    break-after: page;
                    max-height: 265mm;
                }
                .certificat-page:last-child {
                    page-break-after: auto;
                    break-after: auto;
                }
                .header-flex {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    font-size: 10.5pt;
                    line-height: 1.35;
                    margin-bottom: 12mm;
                }
                .header-left {
                    text-align: center;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                .header-left .stars {
                    font-size: 9pt;
                    letter-spacing: 2px;
                    margin: 2px 0;
                }
                .header-left .num-ref {
                    margin-top: 10px;
                    text-align: left;
                    font-family: 'Courier New', monospace;
                    font-weight: bold;
                    font-size: 10pt;
                }
                .header-right {
                    text-align: center;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                .header-right .devise {
                    font-size: 9.5pt;
                    font-style: italic;
                    text-transform: none;
                    margin-top: 2px;
                }
                .header-right .stars {
                    font-size: 9pt;
                    letter-spacing: 2px;
                    margin: 2px 0;
                }
                .header-right .date-loc {
                    margin-top: 10px;
                    text-align: right;
                    font-weight: normal;
                    text-transform: none;
                    font-size: 10.5pt;
                }
                .cert-title {
                    text-align: center;
                    margin: 10mm 0 12mm 0;
                }
                .cert-title h1 {
                    font-size: 16pt;
                    font-weight: 900;
                    text-transform: uppercase;
                    text-decoration: underline;
                    letter-spacing: 0.8px;
                    margin: 0;
                }
                .cert-body {
                    text-align: justify;
                    font-size: 12pt;
                    line-height: 1.75;
                    text-indent: 10mm;
                    margin: 8mm 0 8mm 0;
                }
                .cert-motif {
                    font-size: 12pt;
                    line-height: 1.6;
                    margin: 8mm 0 10mm 0;
                }
                .cert-motif strong {
                    text-decoration: underline;
                }
                .sign-row {
                    display: flex;
                    justify-content: flex-end;
                    margin-top: 8mm;
                }
                .sign-box {
                    width: 80mm;
                    text-align: center;
                }
                .sign-title {
                    font-weight: bold;
                    font-size: 11pt;
                    margin-bottom: 20mm;
                }
                .sign-name {
                    font-weight: bold;
                    font-size: 11.5pt;
                    text-decoration: underline;
                    margin-bottom: 2mm;
                }
                .sign-distinction {
                    font-size: 10pt;
                    font-style: italic;
                    color: #222;
                }
            </style>
        </head>
        <body>
            ${certsHtml}
        </body>
        </html>
    `);
    doc.close();

    iframe.contentWindow.focus();
    setTimeout(() => {
        iframe.contentWindow.print();
        setTimeout(() => {
            if (document.body.contains(iframe)) {
                document.body.removeChild(iframe);
            }
        }, 1500);
    }, 400);
};

const gradeOptions = ref([]);
const regionOptions = ref([]);
const positionOptions = ref([]);
const statutOptions = [
    { label: 'Tous les statuts', value: 'all' },
    { label: 'En activité (Actifs, Détachés, Stages)', value: 'actif' },
    { label: 'Sans région / unité (Recrues)', value: 'non_rattaches' },
    { label: 'Actifs stricts (Unité rég.)', value: 'actif_strict' },
    { label: 'Détachés / En détachement', value: 'détaché' },
    { label: 'En stage / Formation', value: 'stage' },
    { label: 'En mission', value: 'mission' },
    { label: "Cas d'otage", value: 'otage' },
    { label: 'Retraites N (au 31/12)', value: 'retraites_n' },
    { label: 'Retraites N+1', value: 'retraites_n1' },
    { label: 'Retraité', value: 'retraité' },
    { label: 'Déserteur', value: 'déserteur' },
    { label: 'Décédé', value: 'décédé' }
];

const isOtage = (m) => {
    if (!m) return false;
    if (m.is_otage) return true;
    const pos = (m.position_actuelle || '').toLowerCase();
    const obs = (m.observations || '').toLowerCase();
    return pos.includes('otage') || obs.includes('otage');
};

const props = defineProps({
    militaires: { type: Object, required: true },
    statistiques: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    grades: { type: Array, default: () => [] },
    regions: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
    theatres: { type: Array, default: () => [] },
    unites: { type: Array, default: () => [] }
});

const formatAnciennete = (annees) => {
    if (!annees && annees !== 0) return '0 an';
    return `${Math.floor(annees)} ans`;
};

onMounted(() => {
    gradeOptions.value = [
        { label: 'Tous les grades', value: null },
        ...props.grades.map(g => ({ label: g.nom_grade, value: g.id ?? g.nom_grade }))
    ];

    regionOptions.value = [
        { label: 'Toutes les régions', value: null },
        ...props.regions.map(r => ({ label: r.nom ?? r, value: r.id ?? r }))
    ];

    positionOptions.value = [
        { label: 'Toutes les positions', value: null },
        ...props.positions.map(p => ({ label: p.nom ?? p, value: p.id ?? p }))
    ];
});

const filters = reactive({
    search: props.filters?.search || '',
    grade: props.filters?.grade || null,
    region: props.filters?.region || null,
    position: props.filters?.position || null,
    statut: props.filters?.statut || 'actif'
});

const setStatutFilter = (statut) => {
    if (filters.statut === statut) {
        loadMilitaires(1);
    } else {
        filters.statut = statut;
    }
};

const exportFilters = reactive({
    grade: null,
    region: null,
    statut: null,
    useCurrentFilters: true,
    includeSearch: true
});

const debouncedSearch = debounce(() => {
    loadMilitaires(1);
}, 500);

const onSearchInput = () => {
    debouncedSearch();
};

const onFilterChange = () => {
    loadMilitaires(1);
};

watch(() => filters.search, () => debouncedSearch());
watch([() => filters.grade, () => filters.region, () => filters.position, () => filters.statut], () => loadMilitaires(1));

const getStatutStyle = (statut) => {
    const s = (statut || '').toLowerCase();
    if (s.includes('actif')) return { background: '#16a34a', color: '#ffffff' };
    if (s.includes('détach') || s.includes('detach')) return { background: '#0284c7', color: '#ffffff' };
    if (s.includes('stage') || s.includes('format')) return { background: '#d97706', color: '#ffffff' };
    if (s.includes('miss')) return { background: '#7c3aed', color: '#ffffff' };
    if (s.includes('retrait')) return { background: '#64748b', color: '#ffffff' };
    if (s.includes('d_ser') || s.includes('deser')) return { background: '#dc2626', color: '#ffffff' };
    if (s.includes('d_c_d') || s.includes('mort')) return { background: '#1e293b', color: '#ffffff' };
    return { background: '#64748b', color: '#ffffff' };
};

const loadMilitaires = (page = 1) => {
    loading.value = true;
    router.get(route('militaires.index'), {
        page,
        search: filters.search,
        grade: filters.grade,
        region: filters.region,
        position: filters.position,
        statut: filters.statut
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => { loading.value = false; },
        onError: () => {
            loading.value = false;
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les militaires', life: 3000 });
        }
    });
};

const resetFilters = () => {
    filters.search = '';
    filters.grade = null;
    filters.region = null;
    filters.position = null;
    filters.statut = 'actif';
    loadMilitaires(1);
};

const onPageChange = (event) => changePage(event.page + 1);

const changePage = (page) => {
    if (page >= 1 && page <= props.militaires.last_page) loadMilitaires(page);
};

const viewMilitaire = (id) => router.visit(route('militaires.show', id));
const editMilitaire = (id) => router.visit(route('militaires.edit', id));
const createMilitaire = () => router.visit(route('militaires.create'));
const importExcel = () => router.visit(route('militaires.import'));

const openExportModal = () => {
    exportFilters.grade = filters.grade;
    exportFilters.region = filters.region;
    exportFilters.statut = filters.statut;
    exportFilters.useCurrentFilters = true;
    exportFilters.includeSearch = true;
    exportDialogVisible.value = true;
};

const exportMilitaires = async () => {
    exporting.value = true;

    let params = {};

    if (exportFilters.useCurrentFilters) {
        if (filters.search) params.search = filters.search;
        if (filters.grade) params.grade = filters.grade;
        if (filters.region) params.region = filters.region;
        if (filters.statut) params.statut = filters.statut;
    } else {
        if (exportFilters.includeSearch && filters.search) params.search = filters.search;
        if (exportFilters.grade) params.grade = exportFilters.grade;
        if (exportFilters.region) params.region = exportFilters.region;
        if (exportFilters.statut) params.statut = exportFilters.statut;
    }

    // Nettoyer les valeurs null/undefined
    Object.keys(params).forEach(key => {
        if (params[key] === null || params[key] === undefined || params[key] === '') delete params[key];
    });

    const baseUrl = '/militaires/export';
    const queryString = new URLSearchParams(params).toString();
    const url = baseUrl + (queryString ? '?' + queryString : '');

    await downloadExport(url, {
        defaultFilename: `militaires_${new Date().toISOString().slice(0, 10)}.xlsx`,
        toast,
        onStart: () => {
            exporting.value = true;
            exportProgressText.value = 'Génération du fichier Excel en cours...';
        },
        onProgress: (percent) => {
            if (percent !== null) {
                exportProgressText.value = `Téléchargement : ${percent}%`;
            } else {
                exportProgressText.value = 'Réception du fichier en cours...';
            }
        },
        onFinish: () => {
            exporting.value = false;
            exportDialogVisible.value = false;
            exportProgressText.value = '';
        },
        loadingMessage: 'Préparation du fichier Excel des militaires... Veuillez patienter.',
        successMessage: 'Liste des militaires téléchargée avec succès !'
    });
};

const confirmDelete = (militaire) => {
    militaireToDelete.value = militaire;
    deleteDialogVisible.value = true;
};

const deleteMilitaire = () => {
    if (!militaireToDelete.value) return;
    router.delete(route('militaires.destroy', militaireToDelete.value.id), {
        onSuccess: () => {
            deleteDialogVisible.value = false;
            militaireToDelete.value = null;
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Militaire supprimé avec succès', life: 3000 });
            loadMilitaires(1);
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de supprimer le militaire', life: 3000 });
        }
    });
};

// ==========================================
// AFFECTATION COLLECTIVE / DÉPLOIEMENT EN MASSE
// ==========================================
const bulkAssignDialogVisible = ref(false);
const savingBulkAssign = ref(false);
const loadingCandidates = ref(false);

const bulkTarget = reactive({
    theatre_id: null,       // null = pas de modif, 'remove' = retirer, ID = theatre
    region_id: null,        // null = pas de modif, ID = region
    unite_id: null,         // null = pas de modif, ID = unite
    position_id: null,      // null = pas de modif, ID = position
});

const bulkFilters = reactive({
    search: '',
    grade_id: null,
    categorie: 'tous',      // 'tous', 'officiers', 'sous_officiers', 'mdr'
});

const bulkCandidates = ref([]);
const bulkSelectedIds = ref(new Set());

// Unités filtrées par la région sélectionnée pour l'affectation
const filteredUnitesForBulk = computed(() => {
    if (!bulkTarget.region_id) return props.unites;
    return props.unites.filter(u => u.region_id === bulkTarget.region_id);
});

// Lorsqu'on change de région dans la destination, on réinitialise l'unité si elle n'appartient pas à la région
watch(() => bulkTarget.region_id, (newRegId) => {
    if (newRegId && bulkTarget.unite_id) {
        const u = props.unites.find(item => item.id === bulkTarget.unite_id);
        if (u && u.region_id !== newRegId) {
            bulkTarget.unite_id = null;
        }
    }
});

const openBulkAssignModal = () => {
    bulkTarget.theatre_id = null;
    bulkTarget.region_id = null;
    bulkTarget.unite_id = null;
    bulkTarget.position_id = null;
    bulkFilters.search = '';
    bulkFilters.grade_id = null;
    bulkFilters.categorie = 'tous';

    bulkSelectedIds.value = new Set(selectedMilitaires.value.map(m => m.id));
    bulkAssignDialogVisible.value = true;
    fetchBulkCandidates();
};

const debouncedFetchCandidates = debounce(() => {
    fetchBulkCandidates();
}, 300);

watch([() => bulkFilters.search, () => bulkFilters.grade_id, () => bulkFilters.categorie], () => {
    debouncedFetchCandidates();
});

const fetchBulkCandidates = async () => {
    loadingCandidates.value = true;
    try {
        const params = new URLSearchParams();
        if (bulkFilters.search) params.append('search', bulkFilters.search);
        if (bulkFilters.grade_id) params.append('grade_id', bulkFilters.grade_id);
        if (bulkFilters.categorie && bulkFilters.categorie !== 'tous') params.append('categorie', bulkFilters.categorie);

        const res = await fetch(`${route('militaires.search-selection')}?${params.toString()}`);
        if (res.ok) {
            const data = await res.json();
            bulkCandidates.value = data;
        }
    } catch (e) {
        console.error('Erreur recherche candidats affectation:', e);
    } finally {
        loadingCandidates.value = false;
    }
};

const toggleSelectAllCandidates = () => {
    const allVisibleSelected = bulkCandidates.value.every(m => bulkSelectedIds.value.has(m.id));
    if (allVisibleSelected) {
        bulkCandidates.value.forEach(m => bulkSelectedIds.value.delete(m.id));
    } else {
        bulkCandidates.value.forEach(m => bulkSelectedIds.value.add(m.id));
    }
};

const toggleCandidate = (m) => {
    if (bulkSelectedIds.value.has(m.id)) {
        bulkSelectedIds.value.delete(m.id);
    } else {
        bulkSelectedIds.value.add(m.id);
    }
};

const executeBulkAssign = () => {
    const ids = Array.from(bulkSelectedIds.value);
    if (ids.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'Aucun militaire sélectionné',
            detail: 'Veuillez cocher au moins un militaire à affecter.',
            life: 3000
        });
        return;
    }

    if (bulkTarget.theatre_id === null && bulkTarget.region_id === null && bulkTarget.unite_id === null && bulkTarget.position_id === null) {
        toast.add({
            severity: 'warn',
            summary: 'Aucune destination choisie',
            detail: 'Veuillez choisir au moins un Théâtre d\'opérations ou une Région/Unité à attribuer.',
            life: 4000
        });
        return;
    }

    savingBulkAssign.value = true;
    router.post(route('militaires.bulk-assign'), {
        militaire_ids: ids,
        theatre_id: bulkTarget.theatre_id,
        region_id: bulkTarget.region_id,
        unite_id: bulkTarget.unite_id,
        position_id: bulkTarget.position_id,
        clear_theatre: bulkTarget.theatre_id === 'remove',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            savingBulkAssign.value = false;
            bulkAssignDialogVisible.value = false;
            selectedMilitaires.value = [];
            bulkSelectedIds.value.clear();
            toast.add({
                severity: 'success',
                summary: 'Affectation réussie',
                detail: `${ids.length} militaire(s) affecté(s) / déployé(s) avec succès.`,
                life: 4000
            });
            loadMilitaires(1);
        },
        onError: () => {
            savingBulkAssign.value = false;
            toast.add({
                severity: 'error',
                summary: 'Erreur',
                detail: 'Une erreur est survenue lors de l\'affectation collective.',
                life: 4000
            });
        }
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-white leading-tight">
                    Gestion des Militaires — Gendarmerie Nationale
                </h2>
            </div>
        </template>

        <div class="py-4 sm:py-8">
            <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 min-w-0">
                <!-- Header de la page et barre d'actions responsive -->
                <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-3 md:gap-4 mb-4 sm:mb-6">
                    <div class="min-w-0">
                        <h2 class="font-bold text-lg sm:text-xl md:text-2xl text-sky-950 truncate">Gestion des Militaires - Gendarmerie Nationale</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Annuaire et suivi du personnel militaire et territorial</p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap shrink-0">
                        <!-- Bouton d'affectation collective toujours disponible -->
                        <button @click="openBulkAssignModal" type="button"
                            class="px-3 py-2 bg-gradient-to-r from-sky-900 to-slate-800 text-white rounded-lg font-semibold hover:from-sky-950 hover:to-slate-900 transition-all flex items-center gap-1.5 shadow-xs text-xs whitespace-nowrap cursor-pointer shrink-0 border border-sky-700/60"
                            title="Affecter ou déployer en masse des militaires vers un théâtre ou une région/unité">
                            <i class="pi pi-flag text-xs text-sky-300"></i> <span>Affectation collective</span>
                        </button>

                        <!-- Bouton d'impression groupée visible dès qu'au moins 1 militaire est coché -->
                        <button v-if="selectedMilitaires.length > 0" @click="openBatchCertificatsModal" type="button"
                            class="px-3 py-2 bg-gradient-to-r from-emerald-700 to-emerald-600 text-white rounded-lg font-semibold hover:from-emerald-800 hover:to-emerald-700 transition-all flex items-center gap-1.5 shadow-xs text-xs whitespace-nowrap cursor-pointer shrink-0">
                            <i class="pi pi-print text-xs"></i> <span>Imprimer certificats ({{ selectedMilitaires.length }})</span>
                        </button>

                        <button @click="createMilitaire" type="button"
                            class="px-3 py-2 bg-gradient-to-r from-sky-800 to-sky-700 text-white rounded-lg font-semibold hover:from-sky-900 hover:to-sky-800 transition-all flex items-center gap-1.5 shadow-xs text-xs whitespace-nowrap cursor-pointer shrink-0">
                            <i class="pi pi-plus text-xs"></i> <span>Nouveau</span>
                        </button>
                        <button @click="importExcel" type="button"
                            class="px-3 py-2 bg-white text-sky-900 rounded-lg font-semibold hover:bg-sky-50 transition-colors flex items-center gap-1.5 shadow-xs text-xs border border-sky-200 whitespace-nowrap cursor-pointer shrink-0">
                            <i class="pi pi-upload text-xs"></i> <span>Importer</span>
                        </button>
                        <button @click="openExportModal" type="button"
                            class="px-3 py-2 bg-white text-sky-900 rounded-lg font-semibold hover:bg-sky-50 transition-colors flex items-center gap-1.5 shadow-xs text-xs border border-sky-200 whitespace-nowrap cursor-pointer shrink-0">
                            <i class="pi pi-file-excel text-xs"></i> <span>Exporter</span>
                        </button>
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
                    <div @click="setStatutFilter('all')" 
                         :class="filters.statut === 'all' ? 'ring-2 ring-sky-800 shadow-md bg-sky-50/50' : 'bg-white shadow-sm hover:shadow-md'"
                         class="rounded-lg p-3 sm:p-4 border-l-4 border-sky-900 cursor-pointer transition-all">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs uppercase tracking-wider font-semibold text-gray-500">Total militaires</span>
                            <i class="pi pi-users text-sky-800 text-sm"></i>
                        </div>
                        <div class="text-xl md:text-2xl font-bold text-sky-950">{{ statistiques.total }}</div>
                    </div>
                    <div @click="setStatutFilter('actif')" 
                         :class="filters.statut === 'actif' ? 'ring-2 ring-emerald-500 shadow-md bg-emerald-50/50' : 'bg-white shadow-sm hover:shadow-md'"
                         class="rounded-lg p-3 sm:p-4 border-l-4 border-emerald-500 cursor-pointer transition-all">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs uppercase tracking-wider font-semibold text-gray-500">Militaires actifs</span>
                            <i class="pi pi-check-circle text-emerald-600 text-sm"></i>
                        </div>
                        <div class="text-xl md:text-2xl font-bold text-emerald-600">{{ statistiques.actifs }}</div>
                        <p class="text-[11px] text-emerald-700 font-medium mt-0.5">Actifs, détachés, stages</p>
                    </div>
                    <div @click="setStatutFilter('non_rattaches')" 
                         :class="(filters.statut === 'non_rattaches' || filters.non_rattaches) ? 'ring-2 ring-amber-500 shadow-md bg-amber-50/70' : 'bg-white shadow-sm hover:shadow-md'"
                         class="rounded-lg p-3 sm:p-4 border-l-4 border-amber-500 cursor-pointer transition-all">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs uppercase tracking-wider font-semibold text-gray-500">Sans région / unité</span>
                            <i class="pi pi-exclamation-triangle text-amber-600 text-sm"></i>
                        </div>
                        <div class="text-xl md:text-2xl font-bold text-amber-600">{{ statistiques.non_rattaches ?? 0 }}</div>
                        <p class="text-[11px] text-amber-700 font-medium mt-0.5">Recrues à affecter</p>
                    </div>
                    <div @click="setStatutFilter('retraites_n')" 
                         :class="filters.statut === 'retraites_n' ? 'ring-2 ring-purple-500 shadow-md bg-purple-50/50' : 'bg-white shadow-sm hover:shadow-md'"
                         class="rounded-lg p-3 sm:p-4 border-l-4 border-purple-500 cursor-pointer transition-all">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs uppercase tracking-wider font-semibold text-gray-500">Retraites N</span>
                            <i class="pi pi-calendar text-purple-600 text-sm"></i>
                        </div>
                        <div class="text-xl md:text-2xl font-bold text-purple-700">{{ statistiques.retraites_n ?? 0 }}</div>
                    </div>
                    <div @click="setStatutFilter('retraites_n1')" 
                         :class="filters.statut === 'retraites_n1' ? 'ring-2 ring-indigo-500 shadow-md bg-indigo-50/50' : 'bg-white shadow-sm hover:shadow-md'"
                         class="rounded-lg p-3 sm:p-4 border-l-4 border-indigo-500 cursor-pointer transition-all">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs uppercase tracking-wider font-semibold text-gray-500">Retraites N+1</span>
                            <i class="pi pi-calendar text-indigo-600 text-sm"></i>
                        </div>
                        <div class="text-xl md:text-2xl font-bold text-indigo-700">{{ statistiques.retraites_n1 ?? 0 }}</div>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1 uppercase tracking-wide">Recherche</label>
                                <div class="relative w-full">
                                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs z-10"></i>
                                    <InputText v-model="filters.search" placeholder="Nom, matricule, position, unité..." class="w-full text-sm !pl-8"
                                        @input="onSearchInput" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1 uppercase tracking-wide">Grade</label>
                                <Select v-model="filters.grade" :options="gradeOptions" optionLabel="label"
                                    optionValue="value" placeholder="Tous les grades" class="w-full text-sm" showClear />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1 uppercase tracking-wide">Région</label>
                                <Select v-model="filters.region" :options="regionOptions" optionLabel="label"
                                    optionValue="value" placeholder="Toutes les régions" class="w-full text-sm" showClear />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1 uppercase tracking-wide">Position / Théâtre</label>
                                <Select v-model="filters.position" :options="positionOptions" optionLabel="label"
                                    optionValue="value" placeholder="Toutes les positions" class="w-full text-sm" showClear />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1 uppercase tracking-wide">Statut</label>
                                <Select v-model="filters.statut" :options="statutOptions" optionLabel="label"
                                    optionValue="value" placeholder="Tous les statuts" class="w-full text-sm" showClear />
                            </div>
                            <div>
                                <Button label="Réinitialiser" icon="pi pi-times"
                                    class="p-button-sm bg-gray-500 hover:bg-gray-600 border-gray-500 text-white w-full"
                                    @click="resetFilters" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bannière d'action groupée pour affectation & impression -->
                <div v-if="selectedMilitaires.length > 0" 
                    class="mb-4 bg-sky-50 border border-sky-200 rounded-xl p-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-xs">
                    <div class="flex items-center gap-2 text-xs sm:text-sm text-sky-950 font-medium">
                        <span class="w-6 h-6 rounded-full bg-sky-800 text-white flex items-center justify-center font-bold text-xs">{{ selectedMilitaires.length }}</span>
                        <span>militaire(s) sélectionné(s)</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                        <button type="button" @click="openBulkAssignModal"
                            class="px-3 py-1.5 bg-gradient-to-r from-sky-900 to-slate-800 hover:from-sky-950 hover:to-slate-900 text-white rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-xs cursor-pointer border border-sky-700/50">
                            <i class="pi pi-flag text-xs text-sky-300"></i> <span>Déployer / Affecter ({{ selectedMilitaires.length }})</span>
                        </button>
                        <button type="button" @click="openBatchCertificatsModal"
                            class="px-3 py-1.5 bg-gradient-to-r from-sky-800 to-sky-700 hover:from-sky-900 hover:to-sky-800 text-white rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-xs cursor-pointer">
                            <i class="pi pi-file-check text-xs"></i> <span>Générer les certificats ({{ selectedMilitaires.length }})</span>
                        </button>
                        <button type="button" @click="selectedMilitaires = []"
                            class="px-2.5 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-medium cursor-pointer">
                            Désélectionner tout
                        </button>
                    </div>
                </div>

                <!-- Alerte contextuelle lorsque le filtre des recrues sans région/unité est actif -->
                <div v-if="filters.statut === 'non_rattaches'" 
                    class="mb-4 p-3 bg-amber-50 border border-amber-300 rounded-lg flex flex-wrap items-center justify-between gap-3 text-xs text-amber-900 shadow-xs">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-exclamation-triangle text-amber-600 text-base"></i>
                        <div>
                            <span class="font-bold">Affichage des {{ statistiques.non_rattaches ?? 0 }} recrues en attente d'affectation</span>
                            <span class="text-amber-800 ml-1">Ces militaires sont enregistrés sans région ni unité. Cliquez sur <strong>Modifier</strong> pour leur attribuer leur unité ou région.</span>
                        </div>
                    </div>
                    <button type="button" @click="setStatutFilter('actif')" 
                        class="px-2.5 py-1 bg-white hover:bg-amber-100 text-amber-800 border border-amber-300 rounded font-semibold text-xs whitespace-nowrap cursor-pointer transition-colors">
                        Voir tous les actifs
                    </button>
                </div>

                <!-- DataTable -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-4 md:p-6">
                        <DataTable :value="militaires.data" stripedRows responsiveLayout="scroll" :loading="loading"
                            paginator lazy :rows="militaires.per_page" :totalRecords="militaires.total"
                            @page="onPageChange" class="p-datatable-sm"
                            v-model:selection="selectedMilitaires" dataKey="id">

                            <!-- Colonne Sélection Multi-lignes -->
                            <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>

                            <!-- Colonne Photo d'identité (3:4 ratio miniature) -->
                            <Column header="Photo" style="width: 60px;">
                                <template #body="slotProps">
                                    <div class="w-10 h-12 rounded bg-slate-100 flex items-center justify-center overflow-hidden border border-slate-200 shadow-2xs">
                                        <img v-if="slotProps.data.photo_url" :src="slotProps.data.photo_url" :alt="slotProps.data.nom"
                                            class="w-full h-full object-cover" />
                                        <i v-else class="pi pi-user text-slate-400 text-base"></i>
                                    </div>
                                </template>
                            </Column>

                            <!-- Colonne Identifiants (Matricule & GRM) -->
                            <Column header="Matricule / GRM" style="min-width: 125px;">
                                <template #body="slotProps">
                                    <div class="flex flex-col gap-0.5">
                                        <Tag :value="slotProps.data.matricule"
                                            style="background: #e0f2fe; color: #075985;" class="font-mono text-xs font-semibold" />
                                        <span v-if="slotProps.data.mle_grm" class="text-[11px] text-slate-600 font-mono font-medium">
                                            GRM: {{ slotProps.data.mle_grm }}
                                        </span>
                                    </div>
                                </template>
                            </Column>

                            <!-- Colonne Nom & Prénom -->
                            <Column field="nom" header="Nom & Prénom" style="min-width: 170px;">
                                <template #body="slotProps">
                                    <div>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <Button :label="slotProps.data.nom + ' ' + slotProps.data.prenom"
                                                class="p-button-link p-0 text-sky-900 hover:text-sky-700 font-bold text-left text-sm"
                                                @click="viewMilitaire(slotProps.data.id)" />
                                            <Tag v-if="isOtage(slotProps.data)" value="CAS D'OTAGE"
                                                style="background: #dc2626; color: white;" class="text-[10px] font-black tracking-wider px-1.5 py-0.5 animate-pulse" />
                                            <Badge v-if="slotProps.data.alertes_count > 0" value="!"
                                                style="background: #f97316; color: white;" class="ml-1" />
                                        </div>
                                        <div v-if="isOtage(slotProps.data)" class="text-[11px] text-red-700 font-semibold mt-0.5 flex items-center gap-1" :title="slotProps.data.observations || 'Militaire en otage - Maintenu à l\'effectif'">
                                            <i class="pi pi-shield text-[10px] shrink-0"></i>
                                            <span class="truncate max-w-[210px]">{{ slotProps.data.observations || "Cas d'otage - Maintenu à l'effectif" }}</span>
                                        </div>
                                        <span v-if="slotProps.data.numero_info" class="text-[11px] text-slate-400 block font-mono">
                                            Solde: {{ slotProps.data.numero_info }}
                                        </span>
                                    </div>
                                </template>
                            </Column>

                            <!-- Colonne Grade -->
                            <Column field="grade_actuel" header="Grade" style="min-width: 120px;">
                                <template #body="slotProps">
                                    <Tag :value="slotProps.data.grade_actuel"
                                        style="background: #bae6fd; color: #0369a1;" class="text-xs font-semibold" />
                                </template>
                            </Column>

                            <!-- Colonne Unité & Région -->
                            <Column header="Affectation & Déploiement" style="min-width: 170px;">
                                <template #body="slotProps">
                                    <div class="text-xs space-y-0.5">
                                        <div class="font-semibold text-slate-800 truncate max-w-[170px]" :title="slotProps.data.unite || '-'">
                                            {{ slotProps.data.unite || '-' }}
                                        </div>
                                        <div v-if="slotProps.data.region" class="text-sky-800 flex items-center gap-1 font-medium">
                                            <i class="pi pi-map-marker text-[10px]"></i>
                                            <span>{{ slotProps.data.region }}</span>
                                        </div>
                                        <div v-if="slotProps.data.position_actuelle" class="flex items-center gap-1 font-medium" :title="'Position actuelle : ' + slotProps.data.position_actuelle">
                                            <i class="pi pi-compass text-[10px]" :class="isOtage(slotProps.data) ? 'text-red-600' : 'text-teal-700'"></i>
                                            <span v-if="isOtage(slotProps.data)" class="px-1.5 py-0.5 rounded bg-red-100 text-red-800 font-bold text-[10px] border border-red-300">
                                                Otage (En captivité)
                                            </span>
                                            <span v-else class="text-teal-700 truncate max-w-[170px]">{{ slotProps.data.position_actuelle }}</span>
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <!-- Colonne Entrée service -->
                            <Column field="date_entree_service" header="Entrée service" style="min-width: 110px;">
                                <template #body="slotProps">
                                    <span class="text-xs text-slate-700">{{ slotProps.data.date_entree_service || '-' }}</span>
                                </template>
                            </Column>

                            <!-- Colonne Âge & Ancienneté -->
                            <Column header="Âge / Anc." style="min-width: 100px;">
                                <template #body="slotProps">
                                    <div class="text-xs text-slate-700">
                                        <span class="font-medium">{{ slotProps.data.age ? slotProps.data.age + ' ans' : '-' }}</span>
                                        <span class="text-slate-400 block text-[11px]">{{ formatAnciennete(slotProps.data.anciennete) }}</span>
                                    </div>
                                </template>
                            </Column>

                            <!-- Colonne Statut -->
                            <Column header="Statut" style="min-width: 90px;">
                                <template #body="slotProps">
                                    <Tag :value="slotProps.data.statut" :style="getStatutStyle(slotProps.data.statut)"
                                        class="text-xs uppercase font-medium" />
                                </template>
                            </Column>

                            <!-- Colonne Actions -->
                            <Column header="Actions" style="min-width: 140px;">
                                <template #body="slotProps">
                                    <div class="flex gap-1">
                                        <Button icon="pi pi-file-check"
                                            class="p-button-rounded p-button-text p-button-sm text-sky-800 hover:text-sky-950"
                                            @click="openSingleCertificat(slotProps.data)" v-tooltip.top="'Certificat de présence'" />
                                        <Button icon="pi pi-eye"
                                            class="p-button-rounded p-button-text p-button-sm text-sky-800 hover:text-sky-950"
                                            @click="viewMilitaire(slotProps.data.id)" v-tooltip.top="'Consulter la fiche'" />
                                        <Button icon="pi pi-pencil"
                                            class="p-button-rounded p-button-text p-button-sm text-amber-500 hover:text-amber-600"
                                            @click="editMilitaire(slotProps.data.id)" v-tooltip.top="'Modifier'" />
                                        <Button icon="pi pi-trash"
                                            class="p-button-rounded p-button-text p-button-sm text-red-500 hover:text-red-600"
                                            @click="confirmDelete(slotProps.data)" v-tooltip.top="'Supprimer'" />
                                    </div>
                                </template>
                            </Column>

                            <template #empty>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="pi pi-users text-4xl mb-2 text-slate-400"></i>
                                    <p>Aucun militaire trouvé pour ces critères de recherche.</p>
                                </div>
                            </template>
                        </DataTable>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dialog suppression -->
        <Dialog v-model:visible="deleteDialogVisible" header="Confirmation" :modal="true"
            :style="{ width: '90%', maxWidth: '400px' }" class="p-fluid">
            <div class="flex items-center gap-3 mb-4">
                <i class="pi pi-exclamation-triangle text-3xl text-amber-500"></i>
                <p class="text-gray-700 text-sm">Êtes-vous sûr de vouloir supprimer le militaire <strong>{{
                    militaireToDelete?.nom }} {{ militaireToDelete?.prenom }}</strong> ?</p>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Non" icon="pi pi-times" class="p-button-text text-gray-500 hover:text-gray-700"
                        @click="deleteDialogVisible = false" />
                    <Button label="Oui" icon="pi pi-check" class="bg-red-500 hover:bg-red-600 border-red-500 text-white"
                        @click="deleteMilitaire" />
                </div>
            </template>
        </Dialog>



        <!-- Modal d'export -->
        <Dialog v-model:visible="exportDialogVisible" header="Exporter la liste" :modal="true"
            :style="{ width: '90%', maxWidth: '500px' }" class="p-fluid">
            <div class="space-y-4">
                <p class="text-sm text-gray-600">Choisissez les options d'export :</p>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Grade</label>
                    <Select v-model="exportFilters.grade" :options="gradeOptions" optionLabel="label"
                        optionValue="value" placeholder="Tous les grades" class="w-full" showClear />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Région</label>
                    <Select v-model="exportFilters.region" :options="regionOptions" optionLabel="label"
                        optionValue="value" placeholder="Toutes les régions" class="w-full" showClear />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                    <Select v-model="exportFilters.statut" :options="statutOptions" optionLabel="label"
                        optionValue="value" placeholder="Tous les statuts" class="w-full" showClear />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox v-model="exportFilters.useCurrentFilters" binary />
                    <label class="text-sm text-gray-700">Utiliser les filtres actuels</label>
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox v-model="exportFilters.includeSearch" binary />
                    <label class="text-sm text-gray-700">Appliquer la recherche en cours</label>
                </div>

                <!-- Indicateur visuel pendant la préparation de l'export -->
                <div v-if="exporting" class="p-3 bg-sky-50 border border-sky-200 rounded-lg flex items-center gap-3 text-sky-900 text-xs">
                    <i class="pi pi-spin pi-spinner text-sky-600 text-base shrink-0"></i>
                    <div class="flex-1">
                        <p class="font-semibold">{{ exportProgressText || 'Génération du fichier Excel en cours...' }}</p>
                        <p class="text-[11px] text-sky-700">Traitement sécurisé en arrière-plan (aucun onglet ouvert).</p>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Annuler" icon="pi pi-times" class="p-button-text text-gray-500 hover:text-gray-700"
                        :disabled="exporting"
                        @click="exportDialogVisible = false" />
                    <Button :label="exporting ? 'Exportation...' : 'Exporter'" icon="pi pi-file-excel"
                        class="bg-gradient-to-r from-sky-900 to-sky-800 hover:from-sky-950 hover:to-sky-900 border-sky-800 text-white shadow-sm font-medium" :loading="exporting"
                        @click="exportMilitaires" />
                </div>
            </template>
        </Dialog>

        <!-- Modal d'impression groupée des certificats de présence -->
        <Dialog v-model:visible="batchCertificatDialogVisible" header="Impression des Certificats de Présence au Corps" :modal="true"
            :style="{ width: '90%', maxWidth: '750px' }" class="p-fluid">
            <div class="space-y-4 text-xs">
                <!-- Info bannière -->
                <div class="p-3 bg-sky-50 border border-sky-200 rounded-lg text-sky-900 flex items-center gap-3">
                    <i class="pi pi-info-circle text-xl text-sky-700 shrink-0"></i>
                    <div>
                        <div class="font-bold text-sm">Génération de {{ selectedMilitaires.length }} certificat(s) de présence</div>
                        <p class="text-xs text-sky-800 mt-0.5">Chaque certificat sera formaté strictement sur <strong>1 page A4 individuelle</strong> prête pour signature manuelle et cachet.</p>
                    </div>
                </div>

                <!-- Liste des militaires sélectionnés (chips) -->
                <div>
                    <label class="block font-bold text-slate-700 mb-1.5 uppercase tracking-wide">Militaires inclus ({{ selectedMilitaires.length }}) :</label>
                    <div class="flex flex-wrap gap-1.5 max-h-36 overflow-y-auto p-2 bg-slate-50 rounded-lg border border-slate-200">
                        <span v-for="m in selectedMilitaires" :key="m.id" 
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white border border-slate-300 text-slate-800 font-medium">
                            <span class="font-bold text-sky-900">{{ m.grade_actuel }}</span> {{ m.nom }} {{ m.prenom }} <span class="font-mono text-[10px] text-slate-500">({{ m.matricule }})</span>
                            <i class="pi pi-times text-[10px] text-slate-400 hover:text-red-500 cursor-pointer ml-1" @click="removeSelectedMilitaire(m.id)"></i>
                        </span>
                    </div>
                </div>

                <!-- Champs de personnalisation -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Autorité signataire (Nom & Grade) :</label>
                        <input type="text" v-model="batchAutoriteSignataire" 
                            class="w-full text-xs p-2 border border-slate-300 rounded-lg bg-white text-slate-800 font-semibold focus:border-sky-500" />
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Titre / Fonction signataire :</label>
                        <input type="text" v-model="batchTitreSignataire" 
                            class="w-full text-xs p-2 border border-slate-300 rounded-lg bg-white text-slate-800 focus:border-sky-500" />
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Distinction signataire :</label>
                        <input type="text" v-model="batchDecorationSignataire" 
                            class="w-full text-xs p-2 border border-slate-300 rounded-lg bg-white text-slate-800 focus:border-sky-500" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="font-bold text-slate-700 block mb-1">Motif de délivrance :</label>
                        <input type="text" v-model="batchMotifCertificat" 
                            class="w-full text-xs p-2 border border-slate-300 rounded-lg bg-white text-slate-800 focus:border-sky-500" />
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Préfixe Réf. Numéro :</label>
                        <input type="text" v-model="batchNumeroPrefix" 
                            class="w-full text-xs p-2 border border-slate-300 rounded-lg bg-white font-mono text-slate-800 focus:border-sky-500" />
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-between items-center w-full pt-3 border-t">
                    <Button label="Annuler" icon="pi pi-times" class="p-button-text p-button-sm text-slate-600"
                        @click="batchCertificatDialogVisible = false" />
                    <button type="button" @click="printBatchCertificats"
                        class="px-4 py-2 bg-gradient-to-r from-sky-800 to-sky-700 hover:from-sky-900 hover:to-sky-800 text-white rounded-lg text-xs font-semibold flex items-center gap-2 shadow-xs cursor-pointer">
                        <i class="pi pi-print text-xs"></i> <span>Lancer l'impression ({{ selectedMilitaires.length }} page{{ selectedMilitaires.length > 1 ? 's' : '' }})</span>
                    </button>
                </div>
            </template>
        </Dialog>

        <!-- MODAL D'AFFECTATION / DÉPLOIEMENT COLLECTIF -->
        <Dialog
            v-model:visible="bulkAssignDialogVisible"
            modal
            header="Affectation & Déploiement Collectif (Théâtres, Régions, Unités)"
            :style="{ width: '960px', maxWidth: '96vw' }"
            class="p-fluid"
        >
            <div class="space-y-4 pt-1">
                <!-- BLOC 1 : CHOIX DE LA DESTINATION (APPLIQUÉE À TOUS LES MILITAIRES COCHÉS) -->
                <div class="p-3.5 bg-gradient-to-r from-sky-50/80 via-slate-50 to-sky-50/50 border border-sky-200 rounded-xl space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-sky-800 text-white flex items-center justify-center text-sm shadow-xs font-bold">1</span>
                            <div>
                                <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wide">
                                    Définir la destination (attribuée à tous les militaires cochés)
                                </h3>
                                <p class="text-[11px] text-slate-500">
                                    Choisissez le Théâtre d'opérations et/ou la Région & Unité d'ancrage.
                                </p>
                            </div>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-extrabold bg-sky-800 text-white shadow-xs">
                            {{ bulkSelectedIds.size }} militaire(s) ciblé(s)
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-1">
                        <!-- Théâtre d'opérations -->
                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1 flex items-center gap-1.5">
                                <i class="pi pi-flag text-sky-700"></i>
                                <span>Théâtre d'opérations</span>
                            </label>
                            <select
                                v-model="bulkTarget.theatre_id"
                                class="w-full text-xs py-2 px-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-sky-500"
                            >
                                <option :value="null">-- Ne pas modifier le théâtre --</option>
                                <option value="remove">❌ Retirer du théâtre (Hors théâtre)</option>
                                <option v-for="th in theatres" :key="th.id" :value="th.id">
                                    [{{ th.code }}] {{ th.nom }} (PC: {{ th.pc_lieu || 'Non spécifié' }})
                                </option>
                            </select>
                        </div>

                        <!-- Région -->
                        <div>
                            <label class="block text-xs font-bold text-sky-950 mb-1 flex items-center gap-1.5">
                                <i class="pi pi-map text-sky-600"></i>
                                <span>Région administrative</span>
                            </label>
                            <select
                                v-model="bulkTarget.region_id"
                                class="w-full text-xs py-2 px-2.5 rounded-lg border border-sky-300 bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-sky-500"
                            >
                                <option :value="null">-- Ne pas modifier la région --</option>
                                <option v-for="r in regions" :key="r.id" :value="r.id">
                                    [{{ r.code }}] {{ r.nom }}
                                </option>
                            </select>
                        </div>

                        <!-- Unité -->
                        <div>
                            <label class="block text-xs font-bold text-sky-950 mb-1 flex items-center gap-1.5">
                                <i class="pi pi-shield text-sky-600"></i>
                                <span>Unité / Brigade / Escadron</span>
                            </label>
                            <select
                                v-model="bulkTarget.unite_id"
                                class="w-full text-xs py-2 px-2.5 rounded-lg border border-sky-300 bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-sky-500"
                            >
                                <option :value="null">-- Ne pas modifier l'unité --</option>
                                <option v-for="u in filteredUnitesForBulk" :key="u.id" :value="u.id">
                                    {{ u.nom }} {{ u.code ? '(' + u.code + ')' : '' }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- BLOC 2 : SÉLECTION DES MILITAIRES -->
                <div class="space-y-2.5">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-sky-800 text-white flex items-center justify-center text-sm shadow-xs font-bold">2</span>
                        <div>
                            <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wide">
                                Sélectionner les personnels à affecter
                            </h3>
                            <p class="text-[11px] text-slate-500">
                                Filtrez par grade, recherchez par nom/matricule, puis cochez les militaires.
                            </p>
                        </div>
                    </div>

                    <!-- Barre de filtre rapide -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                        <div class="flex flex-wrap items-center gap-2 flex-1">
                            <!-- Catégorie hiérarchique -->
                            <div class="flex items-center gap-1 text-xs">
                                <button
                                    type="button"
                                    @click="bulkFilters.categorie = 'tous'"
                                    class="px-2 py-1 rounded text-xs font-semibold cursor-pointer transition"
                                    :class="bulkFilters.categorie === 'tous' ? 'bg-sky-800 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                                >
                                    Tous
                                </button>
                                <button
                                    type="button"
                                    @click="bulkFilters.categorie = 'officiers'"
                                    class="px-2 py-1 rounded text-xs font-semibold cursor-pointer transition"
                                    :class="bulkFilters.categorie === 'officiers' ? 'bg-purple-700 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                                >
                                    Officiers
                                </button>
                                <button
                                    type="button"
                                    @click="bulkFilters.categorie = 'sous_officiers'"
                                    class="px-2 py-1 rounded text-xs font-semibold cursor-pointer transition"
                                    :class="bulkFilters.categorie === 'sous_officiers' ? 'bg-emerald-700 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                                >
                                    Sous-officiers
                                </button>
                                <button
                                    type="button"
                                    @click="bulkFilters.categorie = 'mdr'"
                                    class="px-2 py-1 rounded text-xs font-semibold cursor-pointer transition"
                                    :class="bulkFilters.categorie === 'mdr' ? 'bg-slate-700 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                                >
                                    MDR
                                </button>
                            </div>

                            <!-- Filtre par Grade précis -->
                            <select
                                v-model="bulkFilters.grade_id"
                                class="text-xs py-1 px-2 rounded border border-slate-300 bg-white text-slate-700 max-w-[180px]"
                            >
                                <option :value="null">Tous les grades</option>
                                <option v-for="g in grades" :key="g.id" :value="g.id">{{ g.nom_grade }}</option>
                            </select>
                        </div>

                        <!-- Recherche textuelle -->
                        <div class="relative w-full sm:w-64">
                            <input
                                v-model="bulkFilters.search"
                                type="text"
                                placeholder="Rechercher nom, matricule..."
                                class="w-full text-xs py-1.5 pl-7 pr-2 rounded-lg border border-slate-300 bg-white focus:outline-none focus:ring-2 focus:ring-sky-500"
                            />
                            <i class="pi pi-search absolute left-2 top-2 text-xs text-slate-400"></i>
                        </div>
                    </div>

                    <!-- Contrôle Tout cocher / Décocher -->
                    <div class="flex items-center justify-between text-xs px-1 text-slate-600">
                        <button
                            type="button"
                            @click="toggleSelectAllCandidates"
                            class="text-sky-800 font-bold hover:underline flex items-center gap-1 cursor-pointer"
                        >
                            <i class="pi pi-check-square text-xs"></i>
                            <span>Tout cocher / décocher parmi les résultats visibles</span>
                        </button>
                        <span class="font-semibold text-slate-500">
                            {{ bulkCandidates.length }} résultat(s) trouvé(s) • {{ bulkSelectedIds.size }} sélectionné(s)
                        </span>
                    </div>

                    <!-- Table des candidats -->
                    <div class="max-h-[38vh] overflow-y-auto border border-slate-200 rounded-xl bg-white custom-scrollbar">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="sticky top-0 bg-slate-100 text-slate-700 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200 z-10">
                                <tr>
                                    <th class="p-2 text-center w-10">
                                        <input
                                            type="checkbox"
                                            :checked="bulkCandidates.length > 0 && bulkCandidates.every(m => bulkSelectedIds.has(m.id))"
                                            @change="toggleSelectAllCandidates"
                                            class="rounded text-sky-700 focus:ring-sky-600 cursor-pointer"
                                        />
                                    </th>
                                    <th class="p-2">Grade & Nom</th>
                                    <th class="p-2">Matricule</th>
                                    <th class="p-2">Unité & Région actuelles</th>
                                    <th class="p-2">Théâtre actuel</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="m in bulkCandidates"
                                    :key="m.id"
                                    @click="toggleCandidate(m)"
                                    class="hover:bg-sky-50/50 cursor-pointer transition"
                                    :class="bulkSelectedIds.has(m.id) ? 'bg-sky-50/60' : ''"
                                >
                                    <td class="p-2 text-center" @click.stop>
                                        <input
                                            type="checkbox"
                                            :checked="bulkSelectedIds.has(m.id)"
                                            @change="toggleCandidate(m)"
                                            class="rounded text-sky-700 focus:ring-sky-600 cursor-pointer"
                                        />
                                    </td>
                                    <td class="p-2">
                                        <div class="font-bold text-slate-900 flex items-center gap-1.5">
                                            <span class="font-semibold text-sky-900">[{{ m.grade }}]</span>
                                            <span>{{ m.nom }} {{ m.prenom }}</span>
                                        </div>
                                    </td>
                                    <td class="p-2 font-mono text-slate-600 font-semibold text-[11px]">
                                        {{ m.matricule || m.mle_grm || '-' }}
                                    </td>
                                    <td class="p-2 text-slate-700">
                                        <div class="font-medium text-[11px] truncate max-w-[200px]" :title="m.unite">{{ m.unite || 'Non assigné' }}</div>
                                        <div class="text-[10px] text-slate-400">{{ m.region || '-' }}</div>
                                    </td>
                                    <td class="p-2">
                                        <span
                                            v-if="m.theatre_code"
                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-900 border border-sky-200"
                                        >
                                            {{ m.theatre_code }}
                                        </span>
                                        <span v-else class="text-slate-400 text-[10.5px] italic">
                                            Aucun théâtre
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="bulkCandidates.length === 0 && !loadingCandidates">
                                    <td colspan="5" class="py-8 text-center text-slate-400 italic text-xs">
                                        Aucun personnel militaire trouvé pour ces critères de recherche.
                                    </td>
                                </tr>
                                <tr v-if="loadingCandidates">
                                    <td colspan="5" class="py-8 text-center text-slate-500 text-xs">
                                        <i class="pi pi-spin pi-spinner mr-2"></i>Chargement des personnels...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer modal avec actions -->
                <div class="flex items-center justify-between pt-3 border-t border-slate-200">
                    <div class="text-xs text-slate-600">
                        <strong class="text-sky-900 font-extrabold text-sm">{{ bulkSelectedIds.size }}</strong> militaire(s) recevront cette nouvelle affectation.
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="bulkAssignDialogVisible = false"
                            class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold cursor-pointer transition"
                        >
                            Annuler
                        </button>
                        <button
                            type="button"
                            @click="executeBulkAssign"
                            :disabled="savingBulkAssign || bulkSelectedIds.size === 0"
                            class="px-4 py-2 bg-gradient-to-r from-sky-800 to-sky-700 hover:from-sky-900 hover:to-sky-800 disabled:opacity-50 text-white rounded-lg text-xs font-bold flex items-center gap-2 shadow-xs cursor-pointer transition"
                        >
                            <i v-if="savingBulkAssign" class="pi pi-spin pi-spinner text-xs"></i>
                            <i v-else class="pi pi-check text-xs"></i>
                            <span>{{ savingBulkAssign ? 'Application en cours...' : 'Appliquer l\'affectation aux ' + bulkSelectedIds.size + ' militaire(s)' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Dialog>
    </AuthenticatedLayout>
</template>

<style scoped>
:deep(.p-datatable) {
    font-size: 0.875rem;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
    background: #f8fafc;
    color: #1e293b;
    font-weight: 600;
    padding: 0.75rem 0.75rem;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}

:deep(.p-datatable .p-datatable-tbody > tr > td) {
    padding: 0.6rem 0.75rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}

:deep(.p-datatable .p-datatable-tbody > tr:hover) {
    background-color: #f0f9ff;
}

:deep(.p-tag) {
    padding: 0.2rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.75rem;
}

:deep(.p-badge) {
    font-size: 0.65rem;
    min-width: 1.2rem;
    height: 1.2rem;
    line-height: 1.2rem;
}
</style>
