<script setup>
import { ref, computed, watch } from 'vue';
import { router, Head, Link, Deferred } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AvancementSkeleton from '@/Components/AvancementSkeleton.vue';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import { downloadExport } from '@/Utils/exportHelper';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    organismes: { type: Array, default: () => [] },
    regions: { type: Array, default: () => [] },
    grades: { type: Array, default: () => [] },
    unites: { type: Array, default: () => [] },
    matrice: { type: Object, default: () => ({}) },
    regionsSituation: { type: Object, default: () => ({}) },
    organismesCards: { type: Object, default: () => ({}) },
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

// Téléchargement Excel Officiel avec progression et notifications
const exportingExcel = ref(false);

const telechargerExcel = async () => {
    await downloadExport(route('prise-armes.export'), {
        defaultFilename: `situation_prise_armes_${new Date().toISOString().slice(0, 10)}.xlsx`,
        toast,
        onStart: () => { exportingExcel.value = true; },
        onFinish: () => { exportingExcel.value = false; },
        loadingMessage: 'Préparation du fichier Excel de la Prise d\'Armes... Veuillez patienter.',
        successMessage: 'Situation officielle Prise d\'Armes téléchargée avec succès !'
    });
};

const localRegistres = ref(Array.isArray(props.registres) && props.registres.length > 0 ? props.registres : []);
const loadingRegistres = ref(false);

watch(() => props.registres, (newVal) => {
    if (newVal && newVal.length > 0) {
        localRegistres.value = newVal;
    }
});

const chargerRegistresSiBesoin = async () => {
    if (localRegistres.value && localRegistres.value.length > 0) return;
    loadingRegistres.value = true;
    try {
        const res = await axios.get(route('prise-armes.registres-data'));
        localRegistres.value = Array.isArray(res.data) ? res.data : [];
    } catch (e) {
        console.error('Erreur lors du chargement des registres', e);
    } finally {
        loadingRegistres.value = false;
    }
};

watch(activeTab, (tab) => {
    if (['blesses', 'disparus', 'malades', 'decedes', 'deserteurs'].includes(tab)) {
        chargerRegistresSiBesoin();
    }
}, { immediate: true });

// Filtrage local en temps réel des registres affichés
const registresFiltres = computed(() => {
    let list = localRegistres.value;

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

// Sous-vue active dans la Situation des Effectifs (SPA) : Régions ou Organismes de Détachement
const spaCardsView = ref('regions'); // 'regions' | 'organismes'

// Modal de consultation nominale (Hommes & Dames) par grade
const showGradeModal = ref(false);
const loadingGradeModal = ref(false);
const modalGradeInfo = ref({
    grade_col: '',
    grade_nom: '',
    perimetre: '',
    total: 0,
    total_h: 0,
    total_d: 0,
    militaires: [],
});
const modalGradeSexeFilter = ref('tous'); // 'tous' | 'H' | 'D'
const modalGradeSearch = ref('');
const modalGradePage = ref(1);
const modalGradePerPage = ref(25);

const ouvrirModalGrade = async (target, gradeCode, type = 'region') => {
    const gradeCol = gradeCode.toUpperCase();
    const gData = (target.grades && target.grades[gradeCol]) || {
        total: target[gradeCol] || 0,
        h: target[gradeCol + '_h'] || 0,
        d: target[gradeCol + '_d'] || 0
    };
    if (!gData.total || gData.total <= 0) {
        return;
    }

    modalGradeSexeFilter.value = 'tous';
    modalGradeSearch.value = '';
    modalGradePage.value = 1;
    showGradeModal.value = true;
    loadingGradeModal.value = true;

    try {
        const params = { grade_col: gradeCol };
        if (type === 'region') {
            params.region_id = target.id;
        } else {
            params.organisme_id = target.id;
        }
        const res = await axios.get(route('prise-armes.militaires-grade'), { params });
        modalGradeInfo.value = res.data;
    } catch (e) {
        console.error('Erreur chargement militaires du grade', e);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger la liste des militaires.', life: 3000 });
    } finally {
        loadingGradeModal.value = false;
    }
};

const modalMilitairesFiltres = computed(() => {
    let list = modalGradeInfo.value.militaires || [];
    if (modalGradeSexeFilter.value === 'H') {
        list = list.filter(m => m.sexe === 'H');
    } else if (modalGradeSexeFilter.value === 'D') {
        list = list.filter(m => m.sexe === 'D');
    }
    if (modalGradeSearch.value && modalGradeSearch.value.trim() !== '') {
        const q = modalGradeSearch.value.toLowerCase().trim();
        list = list.filter(m =>
            (m.nom_complet && m.nom_complet.toLowerCase().includes(q)) ||
            (m.matricule && String(m.matricule).toLowerCase().includes(q)) ||
            (m.mle_grm && String(m.mle_grm).toLowerCase().includes(q)) ||
            (m.unite_nom && m.unite_nom.toLowerCase().includes(q)) ||
            (m.position && m.position.toLowerCase().includes(q))
        );
    }
    return list;
});

const modalTotalPages = computed(() => {
    return Math.max(1, Math.ceil(modalMilitairesFiltres.value.length / modalGradePerPage.value));
});

const modalMilitairesPagine = computed(() => {
    const start = (modalGradePage.value - 1) * modalGradePerPage.value;
    return modalMilitairesFiltres.value.slice(start, start + modalGradePerPage.value);
});

const fmt = (n) => (n !== null && n !== undefined ? Number(n).toLocaleString('fr-FR') : '0');

// Définition hiérarchique officielle des 20 grades pour l'affichage en cartes
const SPA_CARD_GRADES = [
    // Ligne 1 : Officiers Généraux (Doré / Ambre)
    { code: 'GAR', title: "Général d'Armée", cat: 'gen' },
    { code: 'GCA', title: "Général de Corps d'Armée", cat: 'gen' },
    { code: 'GDD', title: "Général de Division", cat: 'gen' },
    { code: 'GDB', title: "Général de Brigade", cat: 'gen' },
    // Ligne 2 : Officiers Supérieurs
    { code: 'CLM', title: "Colonel-Major", cat: 'sup' },
    { code: 'COL', title: "Colonel", cat: 'sup' },
    { code: 'LCL', title: "Lieutenant-Colonel", cat: 'sup' },
    { code: 'CES', title: "Chef d'Escadron", cat: 'sup' },
    // Ligne 3 : Officiers Subalternes
    { code: 'CNE', title: "Capitaine", cat: 'sub' },
    { code: 'LTN', title: "Lieutenant", cat: 'sub' },
    { code: 'SLT', title: "Sous-Lieutenant", cat: 'sub' },
    { code: 'ASP', title: "Aspirant", cat: 'sub' },
    // Ligne 4 : Élèves & Sous-officiers Supérieurs
    { code: 'EOA', title: "Élève Officier d'Active", cat: 'eoa' },
    { code: 'MJR', title: "Major / Adjudant-Chef Major", cat: 'so_sup' },
    { code: 'ADC', title: "Adjudant-Chef", cat: 'so_sup' },
    { code: 'ADJ', title: "Adjudant", cat: 'so_sup' },
    // Ligne 5 : Sous-officiers Subalternes & Rang
    { code: 'MDC', title: "Maréchal des Logis-Chef", cat: 'so_sub' },
    { code: 'MDL', title: "Maréchal des Logis", cat: 'so_sub' },
    { code: 'ESOA', title: "Élève Sous-Officier d'Active", cat: 'esoa' },
    { code: 'MRG', title: "Militaire du Rang / Gendarmes", cat: 'mrg' },
];

// Liste ordonnée des 20 régions (19 régions + 1 District de Bamako) depuis la base avec décomposition H / D
const liste20Regions = computed(() => {
    return props.regions.map(r => {
        const sit = props.regionsSituation[r.code] || {};
        return {
            id: r.id,
            code: r.code,
            nom: r.nom,
            total: sit.total || 0,
            total_h: sit.total_h || 0,
            total_d: sit.total_d || 0,
            grades: sit.grades || {},
            GAR: sit.GAR || 0, GAR_h: sit.GAR_h || 0, GAR_d: sit.GAR_d || 0,
            GCA: sit.GCA || 0, GCA_h: sit.GCA_h || 0, GCA_d: sit.GCA_d || 0,
            GDD: sit.GDD || 0, GDD_h: sit.GDD_h || 0, GDD_d: sit.GDD_d || 0,
            GDB: sit.GDB || 0, GDB_h: sit.GDB_h || 0, GDB_d: sit.GDB_d || 0,
            CLM: sit.CLM || 0, CLM_h: sit.CLM_h || 0, CLM_d: sit.CLM_d || 0,
            COL: sit.COL || 0, COL_h: sit.COL_h || 0, COL_d: sit.COL_d || 0,
            LCL: sit.LCL || 0, LCL_h: sit.LCL_h || 0, LCL_d: sit.LCL_d || 0,
            CES: sit.CES || 0, CES_h: sit.CES_h || 0, CES_d: sit.CES_d || 0,
            CNE: sit.CNE || 0, CNE_h: sit.CNE_h || 0, CNE_d: sit.CNE_d || 0,
            LTN: sit.LTN || 0, LTN_h: sit.LTN_h || 0, LTN_d: sit.LTN_d || 0,
            SLT: sit.SLT || 0, SLT_h: sit.SLT_h || 0, SLT_d: sit.SLT_d || 0,
            ASP: sit.ASP || 0, ASP_h: sit.ASP_h || 0, ASP_d: sit.ASP_d || 0,
            EOA: sit.EOA || 0, EOA_h: sit.EOA_h || 0, EOA_d: sit.EOA_d || 0,
            MJR: sit.MJR || 0, MJR_h: sit.MJR_h || 0, MJR_d: sit.MJR_d || 0,
            ADC: sit.ADC || 0, ADC_h: sit.ADC_h || 0, ADC_d: sit.ADC_d || 0,
            ADJ: sit.ADJ || 0, ADJ_h: sit.ADJ_h || 0, ADJ_d: sit.ADJ_d || 0,
            MDC: sit.MDC || 0, MDC_h: sit.MDC_h || 0, MDC_d: sit.MDC_d || 0,
            MDL: sit.MDL || 0, MDL_h: sit.MDL_h || 0, MDL_d: sit.MDL_d || 0,
            ESOA: sit.ESOA || 0, ESOA_h: sit.ESOA_h || 0, ESOA_d: sit.ESOA_d || 0,
            MRG: sit.MRG || 0, MRG_h: sit.MRG_h || 0, MRG_d: sit.MRG_d || 0,
        };
    });
});

// Liste des 48 Organismes de Détachement au format Cartes avec décomposition H / D
const listeOrganismesCards = computed(() => {
    return props.organismes.map(o => {
        const sit = (props.organismesCards && props.organismesCards[o.code]) || {};
        return {
            id: o.id,
            code: o.code,
            nom: o.nom,
            total: sit.total || 0,
            total_h: sit.total_h || 0,
            total_d: sit.total_d || 0,
            grades: sit.grades || {},
            GAR: sit.GAR || 0, GAR_h: sit.GAR_h || 0, GAR_d: sit.GAR_d || 0,
            GCA: sit.GCA || 0, GCA_h: sit.GCA_h || 0, GCA_d: sit.GCA_d || 0,
            GDD: sit.GDD || 0, GDD_h: sit.GDD_h || 0, GDD_d: sit.GDD_d || 0,
            GDB: sit.GDB || 0, GDB_h: sit.GDB_h || 0, GDB_d: sit.GDB_d || 0,
            CLM: sit.CLM || 0, CLM_h: sit.CLM_h || 0, CLM_d: sit.CLM_d || 0,
            COL: sit.COL || 0, COL_h: sit.COL_h || 0, COL_d: sit.COL_d || 0,
            LCL: sit.LCL || 0, LCL_h: sit.LCL_h || 0, LCL_d: sit.LCL_d || 0,
            CES: sit.CES || 0, CES_h: sit.CES_h || 0, CES_d: sit.CES_d || 0,
            CNE: sit.CNE || 0, CNE_h: sit.CNE_h || 0, CNE_d: sit.CNE_d || 0,
            LTN: sit.LTN || 0, LTN_h: sit.LTN_h || 0, LTN_d: sit.LTN_d || 0,
            SLT: sit.SLT || 0, SLT_h: sit.SLT_h || 0, SLT_d: sit.SLT_d || 0,
            ASP: sit.ASP || 0, ASP_h: sit.ASP_h || 0, ASP_d: sit.ASP_d || 0,
            EOA: sit.EOA || 0, EOA_h: sit.EOA_h || 0, EOA_d: sit.EOA_d || 0,
            MJR: sit.MJR || 0, MJR_h: sit.MJR_h || 0, MJR_d: sit.MJR_d || 0,
            ADC: sit.ADC || 0, ADC_h: sit.ADC_h || 0, ADC_d: sit.ADC_d || 0,
            ADJ: sit.ADJ || 0, ADJ_h: sit.ADJ_h || 0, ADJ_d: sit.ADJ_d || 0,
            MDC: sit.MDC || 0, MDC_h: sit.MDC_h || 0, MDC_d: sit.MDC_d || 0,
            MDL: sit.MDL || 0, MDL_h: sit.MDL_h || 0, MDL_d: sit.MDL_d || 0,
            ESOA: sit.ESOA || 0, ESOA_h: sit.ESOA_h || 0, ESOA_d: sit.ESOA_d || 0,
            MRG: sit.MRG || 0, MRG_h: sit.MRG_h || 0, MRG_d: sit.MRG_d || 0,
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
                            :icon="exportingExcel ? 'pi pi-spin pi-spinner' : 'pi pi-download'"
                            :label="exportingExcel ? 'Exportation...' : 'Exporter Excel Officiel'"
                            :loading="exportingExcel"
                            :disabled="exportingExcel"
                            severity="warning"
                            size="small"
                            class="!rounded-xl !shadow-lg !font-bold cursor-pointer disabled:opacity-50"
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
            <!-- TAB 1 : SITUATION DES EFFECTIFS (PRISE D'ARMES)          -->
            <!-- ======================================================== -->
            <Deferred :data="['stats', 'matrice', 'regionsSituation', 'organismesCards']">
                <template #fallback>
                    <div class="py-4">
                        <AvancementSkeleton :rows="12" />
                    </div>
                </template>
                <div v-if="activeTab === 'spa'" class="space-y-6">
                <!-- 1. CARD TOTAL GLOBAL DE LA GENDARMERIE NATIONALE (FOND BLANC PROFESSIONNEL ÉLÉGANT) -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 text-slate-800 shadow-sm relative overflow-hidden">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-5 border-b border-slate-100 relative z-10">
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-amber-50 text-amber-800 border border-amber-200 uppercase tracking-wider">
                                    Situation de Revue & Prise d'Armes
                                </span>
                                <span class="px-2 py-0.5 rounded text-xs bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold">
                                    Direct Base de Données
                                </span>
                            </div>
                            <h2 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 mt-1.5 flex items-center gap-2">
                                <i class="pi pi-shield text-amber-600"></i>
                                <span>TOTAL GLOBAL · GENDARMERIE NATIONALE</span>
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Répartition officielle par grade, genre (Hommes/Dames) et structures de déploiement
                            </p>
                        </div>

                        <div class="flex items-center gap-2.5 flex-wrap">
                            <Button
                                @click="telechargerExcel"
                                :icon="exportingExcel ? 'pi pi-spin pi-spinner' : 'pi pi-file-excel'"
                                :label="exportingExcel ? 'Génération en cours...' : 'Exporter la Situation DGGN (Excel .xlsx)'"
                                :loading="exportingExcel"
                                :disabled="exportingExcel"
                                severity="success"
                                size="small"
                                class="!rounded-xl !bg-emerald-600 hover:!bg-emerald-500 !border-emerald-500 shadow-sm font-bold cursor-pointer disabled:opacity-50"
                            />
                        </div>
                    </div>

                    <!-- Métriques Globales Clés -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-5 relative z-10">
                        <div class="bg-slate-50/80 border border-slate-200 rounded-xl p-3.5">
                            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Effectif Total SPA</div>
                            <div class="text-2xl sm:text-3xl font-black text-slate-900 mt-1 tabular-nums">
                                {{ fmt(stats.total_spa) }} <span class="text-xs font-semibold text-sky-700">pax</span>
                            </div>
                            <div class="text-[10px] text-slate-400 mt-0.5">En activité / Détachement</div>
                        </div>

                        <div class="bg-sky-50/60 border border-sky-100 rounded-xl p-3.5">
                            <div class="text-[11px] font-bold text-sky-700 uppercase tracking-wider flex items-center justify-between">
                                <span>Hommes (H)</span>
                                <span class="text-[10px] text-sky-800 font-extrabold">
                                    {{ stats.total_spa > 0 ? Math.round(((stats.total_hommes || 0) / stats.total_spa) * 100) : 0 }}%
                                </span>
                            </div>
                            <div class="text-2xl sm:text-3xl font-black text-sky-950 mt-1 tabular-nums">
                                {{ fmt(stats.total_hommes) }}
                            </div>
                            <div class="text-[10px] text-sky-600/80 mt-0.5">Personnels masculins</div>
                        </div>

                        <div class="bg-rose-50/60 border border-rose-100 rounded-xl p-3.5">
                            <div class="text-[11px] font-bold text-rose-700 uppercase tracking-wider flex items-center justify-between">
                                <span>Dames (D)</span>
                                <span class="text-[10px] text-rose-800 font-extrabold">
                                    {{ stats.total_spa > 0 ? Math.round(((stats.total_dames || 0) / stats.total_spa) * 100) : 0 }}%
                                </span>
                            </div>
                            <div class="text-2xl sm:text-3xl font-black text-rose-950 mt-1 tabular-nums">
                                {{ fmt(stats.total_dames) }}
                            </div>
                            <div class="text-[10px] text-rose-600/80 mt-0.5">Personnels féminins</div>
                        </div>

                        <div class="bg-amber-50/60 border border-amber-100 rounded-xl p-3.5">
                            <div class="text-[11px] font-bold text-amber-700 uppercase tracking-wider">Détachés Externes</div>
                            <div class="text-2xl sm:text-3xl font-black text-amber-950 mt-1 tabular-nums">
                                {{ fmt(stats.total_detaches) }}
                            </div>
                            <div class="text-[10px] text-amber-600/80 mt-0.5">Hors Gendarmerie (47 Org.)</div>
                        </div>
                    </div>

                    <!-- Décomposition hiérarchique par grandes catégories de grades -->
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 pt-4 border-t border-slate-100 mt-4 relative z-10 text-xs">
                        <div class="bg-amber-50/80 border border-amber-200/80 rounded-lg p-2 text-center">
                            <div class="text-[10px] text-amber-800 font-bold uppercase truncate">Off. Généraux</div>
                            <div class="text-base font-black text-amber-950 tabular-nums">
                                {{ fmt(stats.total_officiers_generaux || 0) }}
                            </div>
                            <div class="text-[9px] text-amber-600">GAR · GCA · GDD · GDB</div>
                        </div>
                        <div class="bg-sky-50/80 border border-sky-200/80 rounded-lg p-2 text-center">
                            <div class="text-[10px] text-sky-800 font-bold uppercase truncate">Off. Supérieurs</div>
                            <div class="text-base font-black text-sky-950 tabular-nums">
                                {{ fmt(stats.total_officiers_superieurs || 0) }}
                            </div>
                            <div class="text-[9px] text-sky-600">CLM · COL · LCL · CES</div>
                        </div>
                        <div class="bg-sky-50/80 border border-sky-200/80 rounded-lg p-2 text-center">
                            <div class="text-[10px] text-sky-800 font-bold uppercase truncate">Off. Subalternes</div>
                            <div class="text-base font-black text-sky-950 tabular-nums">
                                {{ fmt(stats.total_officiers_subalternes || 0) }}
                            </div>
                            <div class="text-[9px] text-sky-600">CNE · LTN · SLT · ASP · EOA</div>
                        </div>
                        <div class="bg-emerald-50/80 border border-emerald-200/80 rounded-lg p-2 text-center">
                            <div class="text-[10px] text-emerald-800 font-bold uppercase truncate">Sous-Officiers</div>
                            <div class="text-base font-black text-emerald-950 tabular-nums">
                                {{ fmt(stats.total_sous_officiers || 0) }}
                            </div>
                            <div class="text-[9px] text-emerald-600">MJR · ADC · ADJ · MDC · MDL</div>
                        </div>
                        <div class="bg-slate-100/80 border border-slate-200/80 rounded-lg p-2 text-center col-span-2 sm:col-span-1">
                            <div class="text-[10px] text-slate-700 font-bold uppercase truncate">Rang & Gendarmes</div>
                            <div class="text-base font-black text-slate-900 tabular-nums">
                                {{ fmt(stats.total_militaires_rang || 0) }}
                            </div>
                            <div class="text-[9px] text-slate-500">MRG · ESOA · Gendarmes</div>
                        </div>
                    </div>
                </div>

                <!-- 2. SÉLECTEUR DE VUE CARTES : 20 RÉGIONS vs 48 ORGANISMES DE DÉTACHEMENT -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
                        <div>
                            <h3 class="text-base font-black text-slate-900">Affichage Cartes des Effectifs par Grade</h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Chaque case de grade affiche le total ainsi que le décompte distinct <b class="text-sky-700">Hommes (H)</b> et <b class="text-rose-600">Dames (D)</b>. Cliquez sur un grade pour ouvrir la liste nominale.
                            </p>
                        </div>

                        <!-- Boutons bascule Régions / Organismes -->
                        <div class="flex items-center gap-1.5 p-1 bg-slate-100 rounded-xl border border-slate-200 self-start sm:self-auto">
                            <button
                                type="button"
                                @click="spaCardsView = 'regions'"
                                :class="[
                                    'px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5 cursor-pointer',
                                    spaCardsView === 'regions'
                                        ? 'bg-sky-800 text-white shadow-xs'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-white/60'
                                ]"
                            >
                                <i class="pi pi-map text-xs"></i>
                                <span>20 Régions Territoriales</span>
                            </button>

                            <button
                                type="button"
                                @click="spaCardsView = 'organismes'"
                                :class="[
                                    'px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5 cursor-pointer',
                                    spaCardsView === 'organismes'
                                        ? 'bg-sky-800 text-white shadow-xs'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-white/60'
                                ]"
                            >
                                <i class="pi pi-sitemap text-xs"></i>
                                <span>48 Organismes de Détachement</span>
                            </button>
                        </div>
                    </div>

                    <!-- 3. GRILLE DE CARTES (RÉGIONS OU ORGANISMES) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="item in (spaCardsView === 'regions' ? liste20Regions : listeOrganismesCards)"
                            :key="item.code"
                            class="bg-slate-50/80 rounded-2xl border border-slate-200 p-4 hover:shadow-md transition-shadow flex flex-col justify-between"
                        >
                            <!-- En-tête de la carte -->
                            <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-200/70">
                                <div class="flex items-center space-x-2 min-w-0">
                                    <span class="px-2 py-0.5 rounded-lg text-xs font-black bg-sky-800 text-white shrink-0">
                                        {{ item.code }}
                                    </span>
                                    <h4 class="font-bold text-sm text-slate-800 truncate" :title="item.nom">
                                        {{ item.nom }}
                                    </h4>
                                </div>
                                <div class="text-right shrink-0 ml-2">
                                    <span class="text-sm font-black text-sky-950 bg-sky-100 px-2.5 py-0.5 rounded-full tabular-nums">
                                        {{ fmt(item.total) }} pax
                                    </span>
                                    <div class="text-[10px] text-slate-500 font-semibold mt-0.5 flex items-center justify-end gap-1">
                                        <span class="text-sky-700">H:{{ fmt(item.total_h) }}</span>
                                        <span>·</span>
                                        <span class="text-rose-600">D:{{ fmt(item.total_d) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Grille des 20 Grades (4 cols x 5 rows) avec H / D et clic modal -->
                            <div class="grid grid-cols-4 gap-1.5 text-center">
                                <div
                                    v-for="g in SPA_CARD_GRADES"
                                    :key="g.code"
                                    @click="item[g.code] > 0 ? ouvrirModalGrade(item, g.code, spaCardsView === 'regions' ? 'region' : 'organisme') : null"
                                    :class="[
                                        'p-1.5 rounded-lg border transition-all text-center select-none',
                                        item[g.code] > 0
                                            ? 'cursor-pointer hover:border-sky-500 hover:shadow-xs active:scale-95'
                                            : 'opacity-40 cursor-default',
                                        g.cat === 'gen'
                                            ? (item[g.code] > 0 ? 'bg-amber-50/90 border-amber-300 hover:bg-amber-100' : 'bg-amber-50/30 border-amber-200/50')
                                            : (item[g.code] > 0 ? 'bg-white border-slate-200 hover:bg-sky-50/70' : 'bg-slate-50/50 border-slate-200/60')
                                    ]"
                                    :title="g.title + (item[g.code] > 0 ? ' • Cliquer pour voir la liste nominale' : '')"
                                >
                                    <div
                                        class="text-[9px] font-black truncate"
                                        :class="g.cat === 'gen' ? 'text-amber-800' : (g.cat === 'sup' ? 'text-sky-800' : 'text-slate-600')"
                                    >
                                        {{ g.code }}
                                    </div>
                                    <div
                                        class="text-xs font-black tabular-nums"
                                        :class="g.cat === 'gen' ? 'text-amber-950' : (item[g.code] > 0 ? 'text-slate-900' : 'text-slate-400')"
                                    >
                                        {{ item[g.code] || 0 }}
                                    </div>
                                    <div class="text-[8px] font-bold mt-0.5 flex items-center justify-center gap-0.5">
                                        <span :class="item[g.code + '_h'] > 0 ? 'text-sky-700 font-extrabold' : 'text-slate-400'">H:{{ item[g.code + '_h'] || 0 }}</span>
                                        <span class="text-slate-300">·</span>
                                        <span :class="item[g.code + '_d'] > 0 ? 'text-rose-600 font-extrabold' : 'text-slate-400'">D:{{ item[g.code + '_d'] || 0 }}</span>
                                    </div>
                                </div>
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
                            <tr v-if="loadingRegistres">
                                <td colspan="10" class="px-4 py-12 text-center text-slate-500 text-xs">
                                    <div class="flex flex-col items-center justify-center space-y-2">
                                        <i class="pi pi-spin pi-spinner text-3xl text-sky-600"></i>
                                        <p class="font-medium text-slate-600">Chargement des données du registre...</p>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else-if="paginatedRegistres.length === 0">
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
            </Deferred>

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
        <!-- Modal consultation nominale par Grade & Structure (Hommes & Dames) -->
        <Dialog
            v-model:visible="showGradeModal"
            modal
            dismissableMask
            :style="{ width: '920px', maxWidth: '96vw' }"
            :header="false"
            class="overflow-hidden"
        >
            <div class="p-4 sm:p-5 space-y-4">
                <!-- En-tête de la modale -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200">
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-sky-900 text-white">
                                {{ modalGradeInfo.grade_col }}
                            </span>
                            <h3 class="text-lg font-black text-slate-900">
                                {{ modalGradeInfo.grade_nom }}
                            </h3>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Périmètre : <b class="text-slate-800">{{ modalGradeInfo.perimetre }}</b>
                        </p>
                    </div>

                    <!-- KPI Badges Total, Hommes, Dames -->
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="px-2.5 py-1 rounded-full text-xs font-black bg-slate-100 text-slate-800 border border-slate-200">
                            Total : {{ fmt(modalGradeInfo.total) }}
                        </span>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-sky-100 text-sky-800 border border-sky-200 flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-sky-600"></span>
                            <span>Hommes : {{ fmt(modalGradeInfo.total_h) }}</span>
                        </span>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200 flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-rose-600"></span>
                            <span>Dames : {{ fmt(modalGradeInfo.total_d) }}</span>
                        </span>
                    </div>
                </div>

                <!-- Filtres et Recherche rapide dans la modale -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <!-- Onglets Tous / Hommes / Dames -->
                    <div class="flex items-center gap-1 p-1 bg-slate-100 rounded-xl border border-slate-200 self-start sm:self-auto">
                        <button
                            type="button"
                            @click="modalGradeSexeFilter = 'tous'; modalGradePage = 1"
                            :class="[
                                'px-3 py-1 text-xs font-bold rounded-lg transition-all cursor-pointer',
                                modalGradeSexeFilter === 'tous'
                                    ? 'bg-sky-800 text-white shadow-xs'
                                    : 'text-slate-600 hover:text-slate-900'
                            ]"
                        >
                            Tous ({{ modalGradeInfo.total || 0 }})
                        </button>
                        <button
                            type="button"
                            @click="modalGradeSexeFilter = 'H'; modalGradePage = 1"
                            :class="[
                                'px-3 py-1 text-xs font-bold rounded-lg transition-all cursor-pointer',
                                modalGradeSexeFilter === 'H'
                                    ? 'bg-sky-800 text-white shadow-xs'
                                    : 'text-slate-600 hover:text-slate-900'
                            ]"
                        >
                            Hommes ({{ modalGradeInfo.total_h || 0 }})
                        </button>
                        <button
                            type="button"
                            @click="modalGradeSexeFilter = 'D'; modalGradePage = 1"
                            :class="[
                                'px-3 py-1 text-xs font-bold rounded-lg transition-all cursor-pointer',
                                modalGradeSexeFilter === 'D'
                                    ? 'bg-rose-700 text-white shadow-xs'
                                    : 'text-slate-600 hover:text-slate-900'
                            ]"
                        >
                            Dames ({{ modalGradeInfo.total_d || 0 }})
                        </button>
                    </div>

                    <!-- Champ de recherche + Nombre par page -->
                    <div class="flex items-center gap-2">
                        <div class="relative w-full sm:w-60">
                            <i class="pi pi-search absolute left-2.5 top-2.5 text-slate-400 text-xs"></i>
                            <input
                                v-model="modalGradeSearch"
                                type="text"
                                placeholder="Rechercher nom, matricule..."
                                class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl border border-slate-300 focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                            />
                        </div>

                        <select
                            v-model="modalGradePerPage"
                            @change="modalGradePage = 1"
                            class="py-1.5 px-2 text-xs rounded-xl border border-slate-300 bg-white text-slate-700 cursor-pointer"
                        >
                            <option :value="10">10 / page</option>
                            <option :value="25">25 / page</option>
                            <option :value="50">50 / page</option>
                            <option :value="100">100 / page</option>
                        </select>
                    </div>
                </div>

                <!-- État de chargement -->
                <div v-if="loadingGradeModal" class="py-12 text-center text-slate-500 space-y-2">
                    <i class="pi pi-spin pi-spinner text-2xl text-sky-700"></i>
                    <p class="text-xs font-bold">Chargement des militaires en cours...</p>
                </div>

                <!-- Tableau nominal paginé -->
                <div v-else-if="modalMilitairesFiltres.length > 0" class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                        <thead class="bg-slate-100 text-slate-700 font-bold">
                            <tr>
                                <th class="px-3 py-2 text-left">MATRICULE / MLE</th>
                                <th class="px-3 py-2 text-left">GRADE & IDENTITÉ</th>
                                <th class="px-2 py-2 text-center">SEXE</th>
                                <th class="px-3 py-2 text-left">UNITÉ</th>
                                <th class="px-3 py-2 text-left">RÉGION</th>
                                <th class="px-3 py-2 text-left">POSITION / STATUT</th>
                                <th class="px-3 py-2 text-center">ACTION</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr
                                v-for="m in modalMilitairesPagine"
                                :key="m.id"
                                class="hover:bg-slate-50/80 transition-colors"
                            >
                                <td class="px-3 py-2 font-mono font-bold text-slate-800 whitespace-nowrap">
                                    {{ m.matricule || m.mle_grm || '-' }}
                                </td>
                                <td class="px-3 py-2">
                                    <div class="font-bold text-slate-900">{{ m.nom_complet }}</div>
                                    <div class="text-[10px] text-slate-500">{{ m.grade }}</div>
                                </td>
                                <td class="px-2 py-2 text-center whitespace-nowrap">
                                    <span
                                        class="px-2 py-0.5 rounded text-[10px] font-black"
                                        :class="m.sexe === 'D' ? 'bg-rose-100 text-rose-800' : 'bg-sky-100 text-sky-800'"
                                    >
                                        {{ m.sexe === 'D' ? 'D (Féminin)' : 'H (Masculin)' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-slate-700 truncate max-w-[200px]" :title="m.unite_nom">
                                    {{ m.unite_nom }}
                                </td>
                                <td class="px-3 py-2 text-slate-600 truncate max-w-[150px]">
                                    {{ m.region_nom }}
                                </td>
                                <td class="px-3 py-2 text-slate-600 truncate max-w-[180px]">
                                    {{ m.position }}
                                </td>
                                <td class="px-3 py-2 text-center whitespace-nowrap">
                                    <Link
                                        :href="route('militaires.show', m.id)"
                                        class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-sky-50 text-sky-800 hover:bg-sky-100 border border-sky-200 inline-flex items-center gap-1"
                                    >
                                        <i class="pi pi-id-card text-[10px]"></i>
                                        <span>Fiche</span>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Aucun résultat -->
                <div v-else class="py-10 text-center text-slate-500 space-y-1 bg-slate-50 rounded-xl border border-slate-200">
                    <i class="pi pi-inbox text-3xl text-slate-400"></i>
                    <p class="text-xs font-bold text-slate-700">Aucun militaire trouvé pour ce critère</p>
                    <p class="text-[11px] text-slate-400">Vérifiez les filtres de recherche ou sélectionnez un autre groupe.</p>
                </div>

                <!-- Pagination de la modale -->
                <div v-if="modalMilitairesFiltres.length > 0" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-2 border-t border-slate-100 text-xs text-slate-600">
                    <div>
                        Affichage de <b>{{ (modalGradePage - 1) * modalGradePerPage + 1 }}</b> à <b>{{ Math.min(modalGradePage * modalGradePerPage, modalMilitairesFiltres.length) }}</b> sur <b>{{ modalMilitairesFiltres.length }}</b> gendarme(s)
                    </div>

                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            @click="modalGradePage = Math.max(1, modalGradePage - 1)"
                            :disabled="modalGradePage === 1"
                            class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs"
                        >
                            Précédent
                        </button>
                        <span class="px-2 py-1 font-bold text-slate-800">
                            Page {{ modalGradePage }} / {{ modalTotalPages }}
                        </span>
                        <button
                            type="button"
                            @click="modalGradePage = Math.min(modalTotalPages, modalGradePage + 1)"
                            :disabled="modalGradePage >= modalTotalPages"
                            class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs"
                        >
                            Suivant
                        </button>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end p-2 border-t border-slate-200">
                    <Button
                        label="Fermer"
                        icon="pi pi-times"
                        severity="secondary"
                        size="small"
                        class="!rounded-xl"
                        @click="showGradeModal = false"
                    />
                </div>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
