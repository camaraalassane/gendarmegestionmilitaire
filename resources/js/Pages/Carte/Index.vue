<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { MALI_VIEW, REGION_SHAPES as SHAPES, project } from './maliRegions.js';
import { getCerclesByRegion } from './maliCercles.js';
import { downloadExport } from '@/Utils/exportHelper';

const toast = useToast();
const exportingCarte = ref(false);
const exportingStatutExcel = ref(false);
const exportingTheatresExcel = ref(false);

const props = defineProps({
    regions: { type: Array, default: () => [] },
    theatres: { type: Array, default: () => [] },
    non_rattaches_liste: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ non_rattaches: 0, generated_at: '', annee: new Date().getFullYear(), hors_region: {} }) },
    regionInitiale: { type: String, default: null },
});

// Références et gestion du mode plein écran
const carteZoneRef = ref(null);
const isFullscreen = ref(false);
const showSidePanelInFullscreen = ref(true);
const sidebarTab = ref('liste');

// Modal pour consulter et corriger les recrues non rattachées
const showNonRattachesModal = ref(false);
const searchRecrues = ref('');

const nonRattachesFiltres = computed(() => {
    const list = props.non_rattaches_liste || [];
    if (!searchRecrues.value || !searchRecrues.value.trim()) return list;
    const q = searchRecrues.value.toLowerCase().trim();
    return list.filter(m =>
        (m.nom && m.nom.toLowerCase().includes(q)) ||
        (m.prenom && m.prenom.toLowerCase().includes(q)) ||
        (m.matricule && String(m.matricule).toLowerCase().includes(q)) ||
        (m.mle_grm && String(m.mle_grm).toLowerCase().includes(q)) ||
        (m.grade_actuel && m.grade_actuel.toLowerCase().includes(q))
    );
});

// Modal interactif de détail par statut (Actifs, Détachés, Retraites, etc.) avec export Excel
const showStatutModal = ref(false);
const loadingStatutModal = ref(false);
const statutModalType = ref('');
const statutModalTitre = ref('');
const statutModalPerimetre = ref('');
const statutModalMilitaires = ref([]);
const statutModalTotal = ref(0);
const statutModalIsLimited = ref(false);
const searchStatutModal = ref('');

const statutMilitairesFiltres = computed(() => {
    const list = statutModalMilitaires.value || [];
    if (!searchStatutModal.value || !searchStatutModal.value.trim()) return list;
    const q = searchStatutModal.value.toLowerCase().trim();
    return list.filter(m =>
        (m.nom && m.nom.toLowerCase().includes(q)) ||
        (m.prenom && m.prenom.toLowerCase().includes(q)) ||
        (m.matricule && String(m.matricule).toLowerCase().includes(q)) ||
        (m.mle_grm && String(m.mle_grm).toLowerCase().includes(q)) ||
        (m.grade && m.grade.toLowerCase().includes(q)) ||
        (m.region_nom && m.region_nom.toLowerCase().includes(q)) ||
        (m.unite_nom && m.unite_nom.toLowerCase().includes(q)) ||
        (m.statut && m.statut.toLowerCase().includes(q)) ||
        (m.position && m.position.toLowerCase().includes(q))
    );
});

const ouvrirModalStatut = async (type) => {
    statutModalType.value = type;
    searchStatutModal.value = '';
    statutModalMilitaires.value = [];
    loadingStatutModal.value = true;
    showStatutModal.value = true;

    try {
        const params = new URLSearchParams({ type });
        if (sel.value) {
            params.append('region', sel.value);
        }
        const res = await fetch(`/carte/militaires-statut?${params.toString()}`);
        if (!res.ok) throw new Error('Erreur de chargement');
        const data = await res.json();
        statutModalTitre.value = data.titre || 'Détail de l\'effectif';
        statutModalPerimetre.value = data.perimetre || (selRegion.value ? selRegion.value.nom : 'Mali entier');
        statutModalMilitaires.value = data.militaires || [];
        statutModalTotal.value = data.count || 0;
        statutModalIsLimited.value = !!data.is_limited;
    } catch (err) {
        console.error('Erreur lors du chargement des militaires par statut:', err);
    } finally {
        loadingStatutModal.value = false;
    }
};

const exportCarte = async () => {
    const url = route('carte.export', sel.value ? { region: sel.value } : {});
    const regionNom = sel.value ? `region_${sel.value}` : 'global';
    await downloadExport(url, {
        defaultFilename: `statistiques_carte_${regionNom}_${new Date().toISOString().slice(0, 10)}.xlsx`,
        toast,
        onStart: () => { exportingCarte.value = true; },
        onFinish: () => { exportingCarte.value = false; },
        loadingMessage: 'Génération du fichier Excel de la carte... Veuillez patienter.',
        successMessage: 'Statistiques de la carte téléchargées avec succès !'
    });
};

const exporterStatutExcel = async () => {
    const params = new URLSearchParams({ type: statutModalType.value });
    if (sel.value) {
        params.append('region', sel.value);
    }
    const url = `/carte/export-statut?${params.toString()}`;
    await downloadExport(url, {
        defaultFilename: `militaires_${statutModalType.value}_${new Date().toISOString().slice(0, 10)}.xlsx`,
        toast,
        onStart: () => { exportingStatutExcel.value = true; },
        onFinish: () => { exportingStatutExcel.value = false; },
        loadingMessage: `Exportation de la liste (${statutModalType.value})...`,
        successMessage: 'Liste nominative téléchargée avec succès !'
    });
};

const exportTheatresExcel = async (theatreCode = null) => {
    const code = typeof theatreCode === 'string' ? theatreCode : (selTheatre.value || null);
    const params = new URLSearchParams();
    if (code) {
        params.append('theatre', code);
    }
    const url = `/carte/export-theatres${params.toString() ? '?' + params.toString() : ''}`;
    const filenameCode = code ? `theatre_${code.toLowerCase()}` : 'theatres_operations';
    await downloadExport(url, {
        defaultFilename: `situation_${filenameCode}_${new Date().toISOString().slice(0, 10)}.xlsx`,
        toast,
        onStart: () => { exportingTheatresExcel.value = true; },
        onFinish: () => { exportingTheatresExcel.value = false; },
        loadingMessage: code
            ? `Exportation de la situation opérationnelle (${code})...`
            : "Génération de l'export Excel des théâtres d'opérations...",
        successMessage: 'Situation opérationnelle des théâtres exportée avec succès !'
    });
};

const toggleFullscreen = async () => {
    if (!isFullscreen.value) {
        isFullscreen.value = true;
        if (carteZoneRef.value?.requestFullscreen) {
            try {
                await carteZoneRef.value.requestFullscreen();
            } catch (e) {
                // Ignore, le fallback CSS 'fixed inset-0' s'applique
            }
        } else if (carteZoneRef.value?.webkitRequestFullscreen) {
            try {
                await carteZoneRef.value.webkitRequestFullscreen();
            } catch (e) {
                // Ignore
            }
        }
    } else {
        isFullscreen.value = false;
        if (document.fullscreenElement || document.webkitFullscreenElement) {
            try {
                if (document.exitFullscreen) {
                    await document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    await document.webkitExitFullscreen();
                }
            } catch (e) {
                // ignore
            }
        }
    }
};

const onFullscreenChange = () => {
    if (!document.fullscreenElement && !document.webkitFullscreenElement) {
        isFullscreen.value = false;
    } else {
        isFullscreen.value = true;
    }
};

const onKeydown = (e) => {
    if (e.key === 'Escape' && isFullscreen.value) {
        if (document.fullscreenElement || document.webkitFullscreenElement) {
            document.exitFullscreen?.();
        }
        isFullscreen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('fullscreenchange', onFullscreenChange);
    document.addEventListener('webkitfullscreenchange', onFullscreenChange);
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('fullscreenchange', onFullscreenChange);
    document.removeEventListener('webkitfullscreenchange', onFullscreenChange);
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
});

const METRICS = {
    eff: { label: 'Effectif Réel (Commandement Direct)', unit: '' },
    eff_global: { label: 'Effectif Global (avec Détachés)', unit: '' },
    pres: { label: 'Présents', unit: '' },
    abs: { label: 'Absents', unit: '' },
    fem: { label: 'Femmes', unit: '' },
    opp: { label: 'Unités op.', unit: ' %' },
};

const metric = ref('eff');
const sel = ref(props.regionInitiale && props.regions.some(r => r.code === props.regionInitiale) ? props.regionInitiale : null); // code de la région choisie ou null = Mali entier
const selUnit = ref(null);     // id de l'unité mise en évidence
const searchInput = ref('');
const searchQuery = ref('');   // recherche rapide dans le volet déroulant (débouncée à 150ms)
let searchTimer = null;
watch(searchInput, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        searchQuery.value = val;
    }, 150);
});
const hoveredItem = ref(null); // objet survolé (région ou unité)
const hoveredCode = ref(null); // code de la région survolée

const fmt = (n) => {
    const num = Number(n);
    return (Number.isFinite(num) ? num : 0).toLocaleString('fr-FR');
};
const shortName = (n) => n.replace(/^(Région de |Région du |District de )/, '');

// ---------- Valeurs par région ----------
const opCount = (r) => r.unites.filter((u) => u.operationnelle).length;
const valueOf = (r) => {
    switch (metric.value) {
        case 'eff_global': return r.effectif;
        case 'pres': return r.effectif - r.absents;
        case 'abs': return r.absents;
        case 'fem': return r.femmes;
        case 'opp': return r.unites.length ? Math.round((opCount(r) / r.unites.length) * 100) : 0;
        default: return (r.actifs !== undefined && r.actifs !== null) ? r.actifs : r.effectif;
    }
};

const legende = computed(() => {
    const v = props.regions.map(valueOf);
    return v.length ? { min: Math.min(...v), max: Math.max(...v) } : { min: 0, max: 0 };
});

// Couleurs tactiques bleu marine adoucies (identiques à la photo de référence)
const fillFor = (r) => {
    if (!r) return '#1b2838';
    if (sel.value) {
        return 'color-mix(in srgb, #5f9bea 42%, #1b2838)';
    }
    const { min, max } = legende.value;
    const val = valueOf(r);
    let pct = 18;
    if (max > min) {
        pct = Math.round(20 + ((val - min) / (max - min)) * 68);
    } else {
        const uCount = r.unites?.length || 0;
        pct = Math.round(18 + Math.min(uCount / 40, 1) * 32);
    }
    if (val === 0 && max > min) {
        const uMod = ((r.unites?.length || 0) % 6) * 2;
        pct = 16 + uMod;
    }
    return `color-mix(in srgb, #5f9bea ${pct}%, #1b2838)`;
};

// ---------- Survol interactif ----------
const onHoverRegion = (r) => {
    hoveredCode.value = r.code;
    hoveredItem.value = {
        isUnit: false,
        title: r.nom,
        subtitle: `${fmt(valueOf(r))}${METRICS[metric.value].unit} (${METRICS[metric.value].label})`,
        extra: `${r.unites.length} unités répertoriées`
    };
};

const onHoverUnit = (u) => {
    hoveredItem.value = {
        isUnit: true,
        uId: u.id,
        op: u.operationnelle,
        title: u.nom,
        subtitle: `${fmt(u.effectif)} militaires`,
        extra: (!u.lat || !u.lon) ? 'Position approx. (sans GPS)' : (u.operationnelle ? 'Opérationnelle' : 'Non opérationnelle')
    };
};

const onLeaveHover = () => {
    hoveredItem.value = null;
    hoveredCode.value = null;
};

const hoveredInfo = computed(() => hoveredItem.value);

// ---------- Sélection / zoom interactif (Pan & Zoom) ----------
const selRegion = computed(() => props.regions.find((r) => r.code === sel.value));

const svgRef = ref(null);
const userZoom = ref(1); // 1 = 100%, 2 = 200%, etc.
const panOffset = ref({ x: 0, y: 0 });
const isDragging = ref(false);
const hasDragged = ref(false);
const dragStart = { x: 0, y: 0 };
let touchStartDist = 0;
let touchStartZoom = 1;

// Calcul automatique et compact de la largeur des badges tactiques selon le nom de région
const badgeWidth = (r) => {
    if (!r) return 46;
    const len = shortName(r.nom).length;
    return Math.max(38, Math.min(len * 5.6 + 12, 60));
};

const choisir = (code) => {
    sel.value = code;
    selUnit.value = null;
    searchInput.value = '';
    searchQuery.value = '';
    parcelSearchInput.value = '';
    parcelSearchQuery.value = '';
    const reg = props.regions.find(r => r.code === code);
    const withEffCount = (reg?.unites || []).filter(u => (Number(u.effectif) || 0) > 0).length;
    parcelFilterType.value = withEffCount > 0 ? 'with_eff' : 'all';
    userZoom.value = 1;
    panOffset.value = { x: 0, y: 0 };
    sidebarTab.value = 'liste';
};

const retour = () => {
    sel.value = null;
    selUnit.value = null;
    searchInput.value = '';
    searchQuery.value = '';
    parcelSearchInput.value = '';
    parcelSearchQuery.value = '';
    parcelFilterType.value = 'all';
    userZoom.value = 1;
    panOffset.value = { x: 0, y: 0 };
    sidebarTab.value = 'liste';
};

const onSelectRegionDropdown = (code) => {
    if (code) {
        choisir(code);
    } else {
        retour();
    }
};

const toggleUnit = (id) => {
    selUnit.value = selUnit.value === id ? null : id;
};

const handleRegionClick = (code) => {
    if (hasDragged.value) return;
    if (carteMode.value === 'theatres') {
        const ths = regionToTheatreMap.value[code] || [];
        if (ths.length > 0) {
            selTheatre.value = selTheatre.value === ths[0].code ? null : ths[0].code;
        }
        return;
    }
    choisir(code);
};

// ==========================================
// THÉÂTRES D'OPÉRATIONS & EMPRISE GÉOGRAPHIQUE
// ==========================================
const carteMode = ref('territoire'); // 'territoire' | 'theatres'
const selTheatre = ref(null);        // code du théâtre sélectionné (ex: 'TH-CTR') ou null
const hoveredTheatre = ref(null);    // code du théâtre survolé
const searchTheatreMilitaire = ref('');

const switchCarteMode = (mode) => {
    carteMode.value = mode;
    if (mode === 'theatres') {
        sel.value = null; // Sortir de la vue régionale pour voir l'emprise des théâtres
    } else {
        selTheatre.value = null;
    }
};

const selTheatreObj = computed(() => {
    if (!selTheatre.value) return null;
    return props.theatres.find(th => th.code === selTheatre.value) || null;
});

// Modal de consultation complète de la liste des militaires du théâtre
const showTheatreMilitairesModal = ref(false);
const theatreMilitaireCategorieFilter = ref('tous'); // 'tous' | 'officiers' | 'sous_officiers' | 'mdr' | 'blesses'
const loadingTheatreMilitaires = ref(false);

const ouvrirModalMilitairesTheatre = async (cat = 'tous', thTarget = null) => {
    if (thTarget) {
        selTheatre.value = thTarget.code;
    }
    theatreMilitaireCategorieFilter.value = cat;
    searchTheatreMilitaire.value = '';
    theatrePage.value = 1;
    showTheatreMilitairesModal.value = true;
    const th = thTarget || selTheatreObj.value;
    if (th && (!th.militaires || th.militaires.length === 0)) {
        loadingTheatreMilitaires.value = true;
        try {
            const res = await axios.get(route('carte.theatres.militaires', th.id));
            th.militaires = res.data;
        } catch (e) {
            console.error('Erreur chargement militaires théâtre', e);
        } finally {
            loadingTheatreMilitaires.value = false;
        }
    }
};

// =========================================================================
// CARTE OPÉRATIONNELLE DES THÉÂTRES (DISPOSITIF TACTIQUE SANS CHEVAUCHEMENT)
// =========================================================================
const BASE_THEATRES_CONFIG = {
    'TH-SOKOLO':         { x: 520, y: 240, c: '#f97316', nomCourt: 'Sokolo' },
    'TH-TOGORE-COUMBE':  { x: 580, y: 275, c: '#f59e0b', nomCourt: 'Togoré-Coumbé' },
    'TH-MBEWANI':        { x: 465, y: 260, c: '#14b8a6', nomCourt: "M'Béwani" },
    'TH-DOUGABOUGOU':    { x: 495, y: 295, c: '#10b981', nomCourt: 'Dougabougou' },
    'TH-TIMISSA':        { x: 545, y: 315, c: '#8b5cf6', nomCourt: 'Timissa' },
    'TH-SAYE':           { x: 525, y: 338, c: '#6366f1', nomCourt: 'Saye' },
    'TH-DIALLOUBE':      { x: 645, y: 295, c: '#ef4444', nomCourt: 'Dialloubé' },
    'TH-KIMPARANA':      { x: 475, y: 385, c: '#22c55e', nomCourt: 'Kimparana' },
    'TH-DANDERESSO':     { x: 430, y: 440, c: '#0284c7', nomCourt: 'Danderesso' },
    'TH-KALANA':         { x: 375, y: 465, c: '#38bdf8', nomCourt: 'Kalana' },
};

const tacticalTheatres = computed(() => {
    const K = 1.7, CX = 520, CY = 325, OX = 450, OY = 270;
    const P = (x, y) => [OX + (x - CX) * K, OY + (y - CY) * K];
    const LW = 158, LH = 44, TOP = 35, BOT = 525;

    const list = props.theatres.map(th => {
        const conf = BASE_THEATRES_CONFIG[th.code] || {};
        let x = conf.x;
        let y = conf.y;
        if (!x || !y) {
            if (th.lat !== null && th.lon !== null) {
                const minLat = 10.0, maxLat = 15.5;
                const minLon = -9.0, maxLon = -3.0;
                x = 350 + ((th.lon - minLon) / (maxLon - minLon)) * 320;
                y = 500 - ((th.lat - minLat) / (maxLat - minLat)) * 320;
            } else {
                x = 520;
                y = 315;
            }
        }
        const [px, py] = P(x, y);
        const eff = Number(th.effectif || th.effectif_gendarmerie || 0);
        const col = conf.c || th.couleur || '#38bdf8';
        const nomCourt = conf.nomCourt || (th.nom ? th.nom.replace(/^Détachement\s+(Opérationnel\s+)?de\s+/i, '') : th.code);

        return {
            ...th,
            nomCourt,
            effectif: eff,
            couleur: col,
            px: Math.round(px),
            py: Math.round(py),
            markerR: Math.max(8, Math.sqrt(eff) * 0.3 + 7),
            haloR: Math.max(16, Math.sqrt(eff) * 0.9 + 14),
            lx: 0,
            ly: 0,
            side: 'L',
            leadD: '',
        };
    });

    if (list.length === 0) return [];

    const sorted = [...list].sort((a, b) => a.px - b.px);
    const mid = sorted[Math.floor(sorted.length / 2)].px;
    const left = list.filter(t => t.px < mid).sort((a, b) => a.py - b.py);
    const right = list.filter(t => t.px >= mid).sort((a, b) => a.py - b.py);

    const stack = (col, xPos, side) => {
        const n = col.length;
        const step = Math.max(LH + 8, (BOT - TOP) / Math.max(n, 1));
        const used = (n - 1) * step;
        const start = Math.max(TOP, (TOP + BOT) / 2 - used / 2);
        col.forEach((t, i) => {
            t.lx = xPos;
            t.ly = Math.round(start + i * step);
            t.side = side;

            const ex = side === 'L' ? t.lx + LW : t.lx;
            const ey = Math.round(t.ly + LH / 2);
            const kx = side === 'L' ? ex + 16 : ex - 16;
            t.leadD = `M ${t.px} ${t.py} L ${kx} ${ey} L ${ex} ${ey}`;
        });
    };

    stack(left, 18, 'L');
    stack(right, 900 - 18 - LW, 'R');

    return list;
});

const tacticalTheatresSorted = computed(() => {
    return [...tacticalTheatres.value].sort((a, b) => b.effectif - a.effectif);
});

const totalProjeteTheatres = computed(() => {
    return tacticalTheatres.value.reduce((s, t) => s + (t.effectif || 0), 0);
});

// Tri strict du grade le plus élevé au plus petit (ordre DESC)
const theatreMilitairesTriesEtFiltres = computed(() => {
    const th = selTheatreObj.value;
    if (!th || !th.militaires) return [];

    let list = [...th.militaires];

    // Tri hiérarchique décroissant : grade le plus élevé en premier
    list.sort((a, b) => {
        const diff = (b.grade_ordre || 0) - (a.grade_ordre || 0);
        if (diff !== 0) return diff;
        return (a.nom || '').localeCompare(b.nom || '');
    });

    // Filtre par catégorie hiérarchique
    if (theatreMilitaireCategorieFilter.value === 'officiers') {
        list = list.filter(m => (m.grade_ordre || 0) >= 11);
    } else if (theatreMilitaireCategorieFilter.value === 'sous_officiers') {
        list = list.filter(m => (m.grade_ordre || 0) >= 5 && (m.grade_ordre || 0) <= 10);
    } else if (theatreMilitaireCategorieFilter.value === 'mdr') {
        list = list.filter(m => (m.grade_ordre || 0) <= 4);
    } else if (theatreMilitaireCategorieFilter.value === 'blesses') {
        list = list.filter(m => m.est_blesse_guerre);
    }

    // Filtre recherche textuelle
    if (searchTheatreMilitaire.value && searchTheatreMilitaire.value.trim()) {
        const q = searchTheatreMilitaire.value.toLowerCase().trim();
        list = list.filter(m =>
            (m.nom && m.nom.toLowerCase().includes(q)) ||
            (m.prenom && m.prenom.toLowerCase().includes(q)) ||
            (m.matricule && String(m.matricule).toLowerCase().includes(q)) ||
            (m.mle_grm && String(m.mle_grm).toLowerCase().includes(q)) ||
            (m.grade && m.grade.toLowerCase().includes(q)) ||
            (m.unite_nom && m.unite_nom.toLowerCase().includes(q)) ||
            (m.region_nom && m.region_nom.toLowerCase().includes(q)) ||
            (m.circonstance_blessure && m.circonstance_blessure.toLowerCase().includes(q))
        );
    }

    return list;
});

// Pagination pour la modale des militaires de théâtre
const theatrePage = ref(1);
const theatrePerPage = ref(25);

watch([theatreMilitaireCategorieFilter, searchTheatreMilitaire], () => {
    theatrePage.value = 1;
});

const theatreTotalPages = computed(() => {
    return Math.max(1, Math.ceil(theatreMilitairesTriesEtFiltres.value.length / theatrePerPage.value));
});

const theatreMilitairesPagine = computed(() => {
    const start = (theatrePage.value - 1) * theatrePerPage.value;
    return theatreMilitairesTriesEtFiltres.value.slice(start, start + theatrePerPage.value);
});

const theatreStatsHierarchiques = computed(() => {
    const th = selTheatreObj.value;
    if (!th) return { officiers: 0, sousOfficiers: 0, mdr: 0, blesses: 0 };
    return {
        officiers: th.officiers ?? (th.militaires ? th.militaires.filter(m => (m.grade_ordre || 0) >= 11).length : 0),
        sousOfficiers: th.sous_officiers ?? (th.militaires ? th.militaires.filter(m => (m.grade_ordre || 0) >= 5 && (m.grade_ordre || 0) <= 10).length : 0),
        mdr: th.mdr ?? (th.militaires ? th.militaires.filter(m => (m.grade_ordre || 0) <= 4).length : 0),
        blesses: th.blesses ?? (th.militaires ? th.militaires.filter(m => m.est_blesse_guerre || m.est_blesse).length : 0),
    };
});

const gradeBadgeStyle = (ordre) => {
    if (!ordre) return 'bg-slate-100 text-slate-700 border-slate-300';
    if (ordre >= 17) return 'bg-purple-100 text-purple-900 border-purple-300'; // Officiers Généraux & Colonels
    if (ordre >= 11) return 'bg-sky-100 text-sky-900 border-sky-300';       // Officiers Subalternes & Supérieurs
    if (ordre >= 5) return 'bg-emerald-100 text-emerald-900 border-emerald-300'; // Sous-officiers
    return 'bg-slate-100 text-slate-700 border-slate-300';                    // Militaires du rang
};

const hasTheatreAppuis = (th) => {
    return Array.isArray(th?.unites_appui) && th.unites_appui.length > 0;
};

const getTheatreAppuiString = (th) => {
    if (!hasTheatreAppuis(th)) return '';
    return th.unites_appui.map(ua => `${ua.code} ${ua.effectif}`).join(' • ');
};

const theatreBadgeWidth = (th) => {
    const title = `${th.code} • ${(th.pc_lieu || 'PC').toUpperCase()}`;
    const titleW = title.length * 6.5 + 24;
    const appuiStr = getTheatreAppuiString(th);
    const appuiW = appuiStr ? appuiStr.length * 5.6 + 28 : 0;
    return Math.max(115, Math.min(280, Math.max(titleW, appuiW)));
};

const theatreMilitairesFiltres = computed(() => theatreMilitairesTriesEtFiltres.value);

const regionToTheatreMap = computed(() => {
    const map = {};
    for (const th of props.theatres) {
        if (Array.isArray(th.regions_codes)) {
            for (const rc of th.regions_codes) {
                if (!map[rc]) map[rc] = [];
                map[rc].push(th);
            }
        }
    }
    return map;
});

const getTheatreFill = (regionCode) => {
    const ths = regionToTheatreMap.value[regionCode] || [];
    if (ths.length === 0) {
        return selTheatre.value ? '#0f172a' : '#16212e';
    }

    if (selTheatre.value) {
        const match = ths.find(t => t.code === selTheatre.value);
        if (match) {
            return `color-mix(in srgb, ${match.couleur} 52%, #0f172a)`;
        }
        return '#0f172a';
    }

    if (hoveredTheatre.value) {
        const match = ths.find(t => t.code === hoveredTheatre.value);
        if (match) {
            return `color-mix(in srgb, ${match.couleur} 50%, #0f172a)`;
        }
    }

    const mainTh = ths[0];
    return `color-mix(in srgb, ${mainTh.couleur} 32%, #141e2b)`;
};

const getTheatreStroke = (regionCode) => {
    const ths = regionToTheatreMap.value[regionCode] || [];
    if (ths.length === 0) return '#1e293b';

    if (selTheatre.value) {
        const match = ths.find(t => t.code === selTheatre.value);
        if (match) return match.couleur;
        return '#1e293b';
    }

    if (hoveredTheatre.value) {
        const match = ths.find(t => t.code === hoveredTheatre.value);
        if (match) return match.couleur;
    }

    return ths[0].couleur;
};

const projectedTheatres = computed(() => {
    // 1. Projection initiale avec dimensions et offset par défaut
    const items = props.theatres.map(th => {
        if (th.lat === null || th.lon === null || isNaN(Number(th.lat)) || isNaN(Number(th.lon))) {
            return {
                ...th,
                pt: null,
                w: theatreBadgeWidth(th),
                h: hasTheatreAppuis(th) ? 36 : 24,
                badgeOffset: { x: 0, y: 22 },
                hasOffset: false,
                isFlipped: false,
            };
        }
        const pt = project(Number(th.lat), Number(th.lon));
        const w = theatreBadgeWidth(th);
        const h = hasTheatreAppuis(th) ? 36 : 24;
        return {
            ...th,
            pt,
            w,
            h,
            badgeOffset: { x: 0, y: 22 },
            hasOffset: false,
            isFlipped: false,
        };
    });

    const active = items.filter(t => t.pt !== null);

    // 2. Détection et résolution automatique des collisions
    // Trier de l'Ouest vers l'Est (x croissant)
    active.sort((a, b) => a.pt.x - b.pt.x);

    for (let i = 0; i < active.length; i++) {
        const a = active[i];
        for (let j = i + 1; j < active.length; j++) {
            const b = active[j];

            const ax = a.pt.x + a.badgeOffset.x;
            const ay = a.pt.y + a.badgeOffset.y;
            const bx = b.pt.x + b.badgeOffset.x;
            const by = b.pt.y + b.badgeOffset.y;

            const marginX = 14;
            const marginY = 8;
            const overlapX = (a.w / 2 + b.w / 2 + marginX) - Math.abs(ax - bx);
            const overlapY = (a.h / 2 + b.h / 2 + marginY) - Math.abs(ay - by);

            if (overlapX > 0 && overlapY > 0) {
                // Collision détectée entre les badges des théâtres 'a' et 'b' !
                // Si aucun n'est encore basculé au-dessus
                if (!a.isFlipped && !b.isFlipped) {
                    // Basculer au-dessus du PC le théâtre le plus au nord
                    if (a.pt.y <= b.pt.y) {
                        a.badgeOffset.y = -(a.h + 10);
                        a.isFlipped = true;
                        a.hasOffset = true;
                    } else {
                        b.badgeOffset.y = -(b.h + 10);
                        b.isFlipped = true;
                        b.hasOffset = true;
                    }
                } else if (Math.abs(a.badgeOffset.y - b.badgeOffset.y) < 18) {
                    // Les deux sont du même côté (ex: multiples théâtres groupés) : décalage latéral
                    const push = Math.min(overlapX / 2 + 10, 60);
                    a.badgeOffset.x -= push;
                    b.badgeOffset.x += push;
                    a.hasOffset = true;
                    b.hasOffset = true;
                }
            }
        }
    }

    // 3. Ordonnancement SVG (Z-Index visuel) :
    // Le théâtre sélectionné ou survolé est placé en dernier élément du DOM pour être 100% au premier plan
    return items.slice().sort((a, b) => {
        const aActive = (a.code === selTheatre.value || a.code === hoveredTheatre.value) ? 1 : 0;
        const bActive = (b.code === selTheatre.value || b.code === hoveredTheatre.value) ? 1 : 0;
        return aActive - bActive;
    });
});

// Modal pour modifier les coordonnées GPS du PC
const showEditTheatreCoordsModal = ref(false);
const savingTheatreCoords = ref(false);
const selectedRegionForPCModal = ref('');
const editTheatreCoordsForm = ref({
    theatre: null,
    latitude: null,
    longitude: null,
    pc_lieu: '',
});

const onSelectRegionForPCModal = (code) => {
    if (!code) return;
    const reg = props.regions.find(r => r.code === code);
    if (reg) {
        editTheatreCoordsForm.value.pc_lieu = reg.chef_lieu || reg.nom.replace(/^(Région de |Région du |District de )/, '');
        if (reg.latitude !== null && reg.latitude !== undefined) {
            editTheatreCoordsForm.value.latitude = Number(reg.latitude);
        }
        if (reg.longitude !== null && reg.longitude !== undefined) {
            editTheatreCoordsForm.value.longitude = Number(reg.longitude);
        }
    }
};

const ouvrirEditTheatreCoordsModal = (th) => {
    if (!th) return;
    const matchedReg = props.regions.find(r =>
        (th.regions_codes && th.regions_codes.includes(r.code)) ||
        (th.pc_lieu && (
            (r.chef_lieu && r.chef_lieu.toLowerCase().includes(th.pc_lieu.toLowerCase())) ||
            (th.pc_lieu.toLowerCase().includes((r.chef_lieu || '').toLowerCase()))
        ))
    );
    selectedRegionForPCModal.value = matchedReg ? matchedReg.code : '';
    editTheatreCoordsForm.value = {
        theatre: th,
        latitude: th.lat,
        longitude: th.lon,
        pc_lieu: th.pc_lieu || '',
    };
    showEditTheatreCoordsModal.value = true;
};

const saveTheatreCoords = () => {
    const th = editTheatreCoordsForm.value.theatre;
    if (!th) return;

    savingTheatreCoords.value = true;
    router.patch(route('carte.theatres.coordinates', th.id), {
        latitude: editTheatreCoordsForm.value.latitude,
        longitude: editTheatreCoordsForm.value.longitude,
        pc_lieu: editTheatreCoordsForm.value.pc_lieu,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showEditTheatreCoordsModal.value = false;
            toast.add({
                severity: 'success',
                summary: 'Coordonnées actualisées',
                detail: `Les coordonnées GPS du PC « ${th.nom} » ont été mises à jour.`,
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Erreur',
                detail: 'Vérifiez la validité des coordonnées décimales (ex: 14.5333 / -4.1000).',
                life: 4000,
            });
        },
        onFinish: () => {
            savingTheatreCoords.value = false;
        },
    });
};

// --- GESTION & SUPPRESSION DES UNITÉS D'APPUI SUR LA CARTE ---
const showDeleteAppuiDialog = ref(false);
const appuiToDelete = ref(null);
const deletingAppui = ref(false);

const confirmDeleteAppui = (ua) => {
    appuiToDelete.value = ua;
    showDeleteAppuiDialog.value = true;
};

const executeDeleteAppui = () => {
    if (!appuiToDelete.value) return;
    deletingAppui.value = true;
    const uaId = appuiToDelete.value.id;
    const uaCode = appuiToDelete.value.code;

    router.delete(route('carte.unites-appui.destroy', uaId), {
        preserveScroll: true,
        onSuccess: () => {
            if (selTheatreObj.value && Array.isArray(selTheatreObj.value.unites_appui)) {
                selTheatreObj.value.unites_appui = selTheatreObj.value.unites_appui.filter(u => u.id !== uaId);
                selTheatreObj.value.effectif_appui = selTheatreObj.value.unites_appui.reduce((s, u) => s + (u.effectif || 0), 0);
            }
            const th = props.theatres.find(t => t.id === selTheatreObj.value?.id);
            if (th && Array.isArray(th.unites_appui)) {
                th.unites_appui = th.unites_appui.filter(u => u.id !== uaId);
                th.effectif_appui = th.unites_appui.reduce((s, u) => s + (u.effectif || 0), 0);
            }
            showDeleteAppuiDialog.value = false;
            appuiToDelete.value = null;
            toast.add({ severity: 'success', summary: 'Appui supprimé', detail: `L'unité d'appui « ${uaCode} » a été supprimée du théâtre avec succès.`, life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de supprimer cette unité d\'appui.', life: 3000 });
        },
        onFinish: () => {
            deletingAppui.value = false;
        }
    });
};

const showAddAppuiModal = ref(false);
const savingAppui = ref(false);
const newAppuiForm = ref({
    code: '',
    nom: '',
    effectif: null,
    chef_detachement: '',
    couleur: '#0284c7',
    observations: ''
});

const mapAppuiPresets = [
    { code: 'AA', nom: "Armée de l'Air", couleur: '#0284c7' },
    { code: 'AT', nom: "Armée de Terre", couleur: '#16a34a' },
    { code: 'DTTIA', nom: "Transmissions", couleur: '#9333ea' },
    { code: 'GENIE', nom: "Génie Militaire", couleur: '#d97706' },
    { code: 'DCSSA', nom: "Santé Militaire", couleur: '#e11d48' },
    { code: 'DMHTA', nom: "Matériel / Logistique", couleur: '#64748b' },
    { code: 'GN', nom: "Garde Nationale", couleur: '#4f46e5' },
    { code: 'POLICE', nom: "Police Nationale", couleur: '#0891b2' },
    { code: 'FS', nom: "Forces Spéciales", couleur: '#be123c' },
    { code: 'ART', nom: "Artillerie", couleur: '#ca8a04' },
    { code: 'CAV', nom: "Blindés / Cavalerie", couleur: '#854d0e' },
    { code: 'DRONE', nom: "Escadron Drones", couleur: '#0284c7' },
];

const openAddAppuiModal = () => {
    newAppuiForm.value = {
        code: '',
        nom: '',
        effectif: null,
        chef_detachement: '',
        couleur: '#0284c7',
        observations: ''
    };
    showAddAppuiModal.value = true;
};

const selectMapAppuiPreset = (p) => {
    newAppuiForm.value.code = p.code;
    newAppuiForm.value.nom = p.nom;
    newAppuiForm.value.couleur = p.couleur;
};

const saveNewAppuiOnMap = () => {
    if (!selTheatreObj.value) return;
    if (!newAppuiForm.value.code || !newAppuiForm.value.nom || newAppuiForm.value.effectif === null) {
        toast.add({ severity: 'warn', summary: 'Champs requis', detail: 'Le code, le nom et l’effectif sont obligatoires.', life: 3000 });
        return;
    }
    savingAppui.value = true;
    router.post(route('carte.theatres.unites-appui.store', selTheatreObj.value.id), newAppuiForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showAddAppuiModal.value = false;
            toast.add({ severity: 'success', summary: 'Appui ajouté', detail: `Unité d'appui « ${newAppuiForm.value.code} » ajoutée avec succès.`, life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Échec de l\'enregistrement de l\'unité d\'appui.', life: 3000 });
        },
        onFinish: () => {
            savingAppui.value = false;
        }
    });
};

const handleUnitClick = (id) => {
    if (hasDragged.value) return;
    toggleUnit(id);
    sidebarTab.value = 'liste';
};

// Contrôles de zoom (+ / - / recentrer)
const zoomIn = () => {
    userZoom.value = Math.min(Number((userZoom.value * 1.35).toFixed(2)), 20);
};

const zoomOut = () => {
    userZoom.value = Math.max(Number((userZoom.value / 1.35).toFixed(2)), 0.35);
};

const resetZoom = () => {
    userZoom.value = 1;
    panOffset.value = { x: 0, y: 0 };
};

// Projection des coordonnées écran vers le canevas SVG
const getSvgPoint = (clientX, clientY) => {
    if (!svgRef.value) return null;
    const pt = svgRef.value.createSVGPoint();
    pt.x = clientX;
    pt.y = clientY;
    const ctm = svgRef.value.getScreenCTM();
    if (!ctm) return null;
    return pt.matrixTransform(ctm.inverse());
};

// Zoom à la molette fluide centré sur le curseur
const onWheel = (e) => {
    const factor = e.deltaY < 0 ? 1.2 : 0.83;
    const newZoom = Math.min(Math.max(Number((userZoom.value * factor).toFixed(2)), 0.35), 20);
    if (newZoom === userZoom.value) return;

    const pt = getSvgPoint(e.clientX, e.clientY);
    if (pt) {
        const [vx, vy, vw, vh] = viewBox.value;
        const currentCenterX = vx + vw / 2;
        const currentCenterY = vy + vh / 2;
        const dx = (pt.x - currentCenterX) * (1 - 1 / factor);
        const dy = (pt.y - currentCenterY) * (1 - 1 / factor);
        panOffset.value.x += dx;
        panOffset.value.y += dy;
    }
    userZoom.value = newZoom;
};

// Double clic pour zoomer rapidement en avant
const onDblClick = (e) => {
    if (e.target.closest('button, select')) return;
    const pt = getSvgPoint(e.clientX, e.clientY);
    if (pt) {
        const [vx, vy, vw, vh] = viewBox.value;
        const currentCenterX = vx + vw / 2;
        const currentCenterY = vy + vh / 2;
        panOffset.value.x += (pt.x - currentCenterX) * 0.35;
        panOffset.value.y += (pt.y - currentCenterY) * 0.35;
    }
    userZoom.value = Math.min(Number((userZoom.value * 1.5).toFixed(2)), 20);
};

// Glisser-déplacer (Pan & Drag) à la souris
const onMouseDown = (e) => {
    if (e.button !== 0) return;
    if (e.target.closest('button, select, input')) return;

    isDragging.value = true;
    hasDragged.value = false;
    dragStart.x = e.clientX;
    dragStart.y = e.clientY;

    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
};

const onMouseMove = (e) => {
    if (!isDragging.value) return;
    const dx = e.clientX - dragStart.x;
    const dy = e.clientY - dragStart.y;
    if (Math.hypot(dx, dy) > 4) {
        hasDragged.value = true;
    }

    const svgRect = svgRef.value?.getBoundingClientRect();
    if (!svgRect || svgRect.width === 0 || svgRect.height === 0) return;

    const [,, vw, vh] = viewBox.value;
    panOffset.value.x -= dx * (vw / svgRect.width);
    panOffset.value.y -= dy * (vh / svgRect.height);

    dragStart.x = e.clientX;
    dragStart.y = e.clientY;
};

const onMouseUp = () => {
    isDragging.value = false;
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
};

// Gestes tactiles (mobile & tablettes)
const onTouchStart = (e) => {
    if (e.touches.length === 1) {
        if (e.target.closest('button, select, input')) return;
        isDragging.value = true;
        hasDragged.value = false;
        dragStart.x = e.touches[0].clientX;
        dragStart.y = e.touches[0].clientY;
    } else if (e.touches.length === 2) {
        isDragging.value = false;
        touchStartDist = Math.hypot(
            e.touches[0].clientX - e.touches[1].clientX,
            e.touches[0].clientY - e.touches[1].clientY
        );
        touchStartZoom = userZoom.value;
    }
};

const onTouchMove = (e) => {
    if (e.touches.length === 1 && isDragging.value) {
        const dx = e.touches[0].clientX - dragStart.x;
        const dy = e.touches[0].clientY - dragStart.y;
        if (Math.hypot(dx, dy) > 4) hasDragged.value = true;

        const svgRect = svgRef.value?.getBoundingClientRect();
        if (svgRect) {
            const [,, vw, vh] = viewBox.value;
            panOffset.value.x -= dx * (vw / svgRect.width);
            panOffset.value.y -= dy * (vh / svgRect.height);
        }
        dragStart.x = e.touches[0].clientX;
        dragStart.y = e.touches[0].clientY;
    } else if (e.touches.length === 2 && touchStartDist > 0) {
        const dist = Math.hypot(
            e.touches[0].clientX - e.touches[1].clientX,
            e.touches[0].clientY - e.touches[1].clientY
        );
        const factor = dist / touchStartDist;
        userZoom.value = Math.min(Math.max(Number((touchStartZoom * factor).toFixed(2)), 0.35), 20);
    }
};

const onTouchEnd = () => {
    isDragging.value = false;
    touchStartDist = 0;
};

// Cadrage de base de la région sélectionnée
const baseRegionBox = computed(() => {
    // Vue nationale : cadrage aéré incluant Kayes à l'ouest, Ménaka à l'est, Sikasso au sud et Taoudénit au nord
    const full = [-24, 30, 685, 575];
    const shape = sel.value && SHAPES[sel.value];
    if (!shape) return full;

    // Si District de Bamako : cadrage agrandi au maximum pour occuper tout le canevas sans marges vides inutiles
    if (sel.value === 'R1') {
        const [a, b, c, d] = [135.7, 462.3, 190.1, 531.1];
        const pw = c - a, ph = d - b;
        const pad = Math.max(pw, ph) * 0.02; // Cadrage serré 2% : la vue régionale s'agrandit pour remplir l'écran
        return [a - pad, b - pad, pw + 2 * pad, ph + 2 * pad];
    }

    const [a, b, c, d] = shape.bb;
    const minSpan = 28;
    const pw = Math.max(c - a, minSpan), ph = Math.max(d - b, minSpan);
    const pad = Math.max(pw, ph) * 0.025; // Cadrage dynamique serré à 2.5%
    return [a - pad, b - pad, pw + 2 * pad, ph + 2 * pad];
});

// Tracé SVG adapté (Bamako agrandi lorsqu'il est isolé pour que tout son maillage soit à l'intérieur)
const pathD = (r) => {
    if (r.code === 'R1' && sel.value === 'R1') {
        return 'M181.6 472.5L177.4 462.3L164.6 473.3L156.9 473.3L152.7 480.1L145.9 475.9L139.1 492.9L135.7 507.3L142.5 512.4L145.9 504.8L153.6 507.3L164.6 526L182.4 531.1L183.3 504.8L190.1 497.1L190.1 485.2L187.6 477.6L181.6 472.5Z';
    }
    return SHAPES[r.code]?.d || '';
};

// ViewBox réactive combinant boîte de base, niveau de zoom et décalage panoramique
const viewBox = computed(() => {
    const [baseX, baseY, baseW, baseH] = baseRegionBox.value;
    const z = Math.max(0.35, Math.min(userZoom.value, 20));
    const w = baseW / z;
    const h = baseH / z;
    const centerX = baseX + baseW / 2 + panOffset.value.x;
    const centerY = baseY + baseH / 2 + panOffset.value.y;
    return [centerX - w / 2, centerY - h / 2, w, h];
});

const zoom = computed(() => viewBox.value[2] / MALI_VIEW.w); // échelle pour pins et traits au zoom

// Échelle régionale normalisée pour que les cercles et chiffres aient la même taille parfaite sur toutes les régions
const rUnit = computed(() => {
    if (!sel.value) return 1;
    const [,, w, h] = baseRegionBox.value;
    const span = Math.max(w, h) || 70;
    return span / 70;
});

// --- Mode Régional : Découpage thématique en parcelles avec cercles proportionnels (Style Carte de France) ---
const parcelFilterType = ref('with_eff'); // Par défaut : 'with_eff' pour afficher immédiatement les unités déployées comme sur la carte de France
const parcelSearchInput = ref('');
const parcelSearchQuery = ref('');
let parcelSearchTimer = null;
watch(parcelSearchInput, (val) => {
    clearTimeout(parcelSearchTimer);
    parcelSearchTimer = setTimeout(() => {
        parcelSearchQuery.value = val;
    }, 150);
});

const countWithEff = computed(() => (selRegion.value?.unites || []).filter(u => (Number(u.effectif) || 0) > 0).length);
const countZeroEff = computed(() => (selRegion.value?.unites || []).filter(u => (Number(u.effectif) || 0) === 0).length);

// Prédicat de visibilité unitaire d'après les filtres actifs et la recherche (affichage seulement)
const isUnitVisible = (unit) => {
    if (!unit) return false;
    const eff = Number(unit.effectif) || 0;
    if (parcelFilterType.value === 'with_eff' && eff === 0) return false;
    if (parcelFilterType.value === 'zero_eff' && eff > 0) return false;
    if (parcelSearchQuery.value && parcelSearchQuery.value.trim()) {
        const q = parcelSearchQuery.value.toLowerCase().trim();
        const matchNom = unit.nom && unit.nom.toLowerCase().includes(q);
        const matchType = unit.type && unit.type.toLowerCase().includes(q);
        const matchVille = unit.ville && unit.ville.toLowerCase().includes(q);
        if (!matchNom && !matchType && !matchVille) return false;
    }
    return true;
};

const countUnitesVisibles = computed(() => {
    return (selRegion.value?.unites || []).filter(u => isUnitVisible(u)).length;
});

// 1. Unités de la région classées par effectif décroissant
const unitesClassees = computed(() => {
    if (!selRegion.value?.unites) return [];
    return selRegion.value.unites.filter(u => isUnitVisible(u));
});

// Découpage robuste de tout tracé SVG en un ou plusieurs polygones (anneaux / îlots / enclaves)
const parseSvgPathToRings = (svgPath) => {
    if (!svgPath || typeof svgPath !== 'string') return [];
    const subpaths = svgPath.match(/[Mm][^Mm]+/g) || [];
    const rings = [];

    for (const sub of subpaths) {
        const numRegex = /[-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?/g;
        const nums = [];
        let m;
        while ((m = numRegex.exec(sub)) !== null) {
            nums.push(Number(m[0]));
        }
        const coords = [];
        for (let i = 0; i + 1 < nums.length; i += 2) {
            if (!isNaN(nums[i]) && !isNaN(nums[i + 1])) {
                coords.push([nums[i], nums[i + 1]]);
            }
        }
        if (coords.length >= 3) {
            rings.push(coords);
        }
    }
    return rings;
};

// Test point-in-polygon multi-anneaux (Règle Even-Odd Ray-Casting standard SVG)
const isPointInRings = (x, y, rings) => {
    if (!rings || rings.length === 0) return true;
    let inside = false;
    for (let r = 0; r < rings.length; r++) {
        const ring = rings[r];
        for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            const xi = ring[i][0], yi = ring[i][1];
            const xj = ring[j][0], yj = ring[j][1];
            const intersect = ((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
            if (intersect) inside = !inside;
        }
    }
    return inside;
};

// 2. Découpage cartographique automatique par diagramme de Voronoi (Style Carte de France)
// Calculé sur l'intégralité des unités de la région (selRegion.unites) pour un maillage géographique stable
const regionalParcels = computed(() => {
    if (!sel.value || !selRegion.value) return [];
    const units = selRegion.value.unites || [];
    const n = units.length;
    if (n === 0) return [];

    let bbox = sel.value === 'R1' ? [135.7, 462.3, 190.1, 531.1] : (SHAPES[sel.value]?.bb || [0, 0, 632, 594]);
    const [minX, minY, maxX, maxY] = bbox;
    const cx = sel.value === 'R1' ? (minX + maxX) / 2 : (SHAPES[sel.value]?.cx ?? (minX + maxX) / 2);
    const cy = sel.value === 'R1' ? (minY + maxY) / 2 : (SHAPES[sel.value]?.cy ?? (minY + maxY) / 2);
    const pw = maxX - minX;
    const ph = maxY - minY;

    // Découpage multi-polygones robuste du contour de la région (gère îlots et enclaves)
    const rawPath = pathD(selRegion.value) || '';
    const rings = parseSvgPathToRings(rawPath);

    // Cas d'une seule unité
    if (n === 1) {
        const u = units[0];
        let pX = cx, pY = cy;
        if (u.lat !== null && u.lon !== null && !isNaN(Number(u.lat)) && !isNaN(Number(u.lon))) {
            const pt = project(Number(u.lat), Number(u.lon));
            if (sel.value === 'R1') {
                const baseCx = 164.6, baseCy = 495.4;
                pX = baseCx + (pt.x - baseCx) * 4.0;
                pY = baseCy + (pt.y - baseCy) * 4.0;
            } else {
                pX = pt.x;
                pY = pt.y;
            }
        }
        return [{
            unit: u,
            cx: pX,
            cy: pY
        }];
    }

    // 1) Projection des coordonnées GPS réelles de chaque unité
    const rawPoints = [];
    const coLocMap = new Map();

    for (let i = 0; i < n; i++) {
        const u = units[i];
        const hasGps = u.lat !== null && u.lon !== null && !isNaN(Number(u.lat)) && !isNaN(Number(u.lon));
        if (hasGps) {
            const pt = project(Number(u.lat), Number(u.lon));
            let x = pt.x, y = pt.y;
            if (sel.value === 'R1') {
                const baseCx = 164.6, baseCy = 495.4;
                x = baseCx + (x - baseCx) * 4.0;
                y = baseCy + (y - baseCy) * 4.0;
            }
            rawPoints.push({ hasGps: true, x, y, index: i, unit: u });
            const key = `${x.toFixed(2)},${y.toFixed(2)}`;
            coLocMap.set(key, (coLocMap.get(key) || 0) + 1);
        } else {
            rawPoints.push({ hasGps: false, x: cx, y: cy, index: i, unit: u });
        }
    }

    // 2) Micro-dispersion des unités co-localisées au même casernement/camp
    //    et positionnement de repli stable et déterministe pour les unités sans GPS
    const coLocSeen = new Map();
    const goldenAngle = Math.PI * (3 - Math.sqrt(5));
    const allRegionUnits = selRegion.value?.unites || [];
    const totalInRegion = Math.max(1, allRegionUnits.length);

    let points = [];
    for (let i = 0; i < n; i++) {
        const p = rawPoints[i];
        let x = p.x, y = p.y;

        if (p.hasGps) {
            const key = `${p.x.toFixed(2)},${p.y.toFixed(2)}`;
            const groupSize = coLocMap.get(key) || 1;
            const seen = coLocSeen.get(key) || 0;
            coLocSeen.set(key, seen + 1);

            if (groupSize > 1) {
                // Micro-dispersion déterministe autour de la position GPS réelle
                const rDisp = Math.min(pw, ph) * 0.035;
                const theta = (seen / groupSize) * 2 * Math.PI;
                x += rDisp * Math.cos(theta);
                y += rDisp * Math.sin(theta);
            }
        } else {
            // Position de repli stable et déterministe basée sur le rang fixe de l'unité dans sa région
            const sansGps = allRegionUnits.filter(u => !(u.lat !== null && u.lon !== null && !isNaN(Number(u.lat)) && !isNaN(Number(u.lon))));
            const rg = sansGps.findIndex(u => u.id === p.unit.id);
            const stableSeed = rg >= 0 ? rg : (p.unit.id % 50);
            const k = sel.value === 'R1' ? 4.0 : 1.0;

            let ox = cx, oy = cy;
            const rgn = selRegion.value;
            if (rgn?.chef_lat != null && rgn?.chef_lon != null) {
                const cp = project(Number(rgn.chef_lat), Number(rgn.chef_lon));
                ox = cp.x; oy = cp.y;
                if (sel.value === 'R1') {
                    const baseCx = 164.6, baseCy = 495.4;
                    ox = baseCx + (ox - baseCx) * k;
                    oy = baseCy + (oy - baseCy) * k;
                }
            }
            const r = (4 + 2.5 * Math.sqrt(stableSeed)) * k;
            const theta = stableSeed * goldenAngle;
            x = ox + r * Math.cos(theta);
            y = oy + r * Math.sin(theta);
        }

        // Vérification d'inclusion dans le contour multi-polygones de la région
        let steps = 0;
        while (!isPointInRings(x, y, rings) && steps < 25) {
            x = x * 0.93 + cx * 0.07;
            y = y * 0.93 + cy * 0.07;
            steps++;
        }

        points.push([x, y]);
    }

    // Séparation anti-collision : empêche les cercles d'unités de se chevaucher
    // tout en maintenant l'ancrage fidèle sur leurs coordonnées GPS dans leurs cercles
    if (n > 1 && n <= 250) {
        for (let pass = 0; pass < 3; pass++) {
            for (let i = 0; i < n; i++) {
                for (let j = i + 1; j < n; j++) {
                    const rI = parcelCircleR(units[i]) || 3.0;
                    const rJ = parcelCircleR(units[j]) || 3.0;
                    const targetDist = rI + rJ + 1.2;
                    const dx = points[j][0] - points[i][0];
                    const dy = points[j][1] - points[i][1];
                    const dist = Math.hypot(dx, dy);
                    if (dist < targetDist) {
                        const effectiveDist = dist > 0.001 ? dist : 0.001;
                        const overlap = (targetDist - effectiveDist) * 0.5;
                        const angle = dist > 0.001 ? Math.atan2(dy, dx) : ((i + j) * 1.618);
                        const nx = Math.cos(angle) * overlap;
                        const ny = Math.sin(angle) * overlap;
                        const nxI = points[i][0] - nx, nyI = points[i][1] - ny;
                        const nxJ = points[j][0] + nx, nyJ = points[j][1] + ny;
                        if (isPointInRings(nxI, nyI, rings)) { points[i][0] = nxI; points[i][1] = nyI; }
                        if (isPointInRings(nxJ, nyJ, rings)) { points[j][0] = nxJ; points[j][1] = nyJ; }
                    }
                }
            }
        }
    }

    return units.map((u, i) => ({
        unit: u,
        cx: points[i] ? points[i][0] : cx,
        cy: points[i] ? points[i][1] : cy
    }));
});

const selParcel = computed(() => {
    if (!selUnit.value) return null;
    return (regionalParcels.value || []).find(p => p.unit.id === selUnit.value);
});

// Cercles officiels de la région sélectionnée
const activeCercles = computed(() => {
    if (!sel.value) return [];
    return getCerclesByRegion(sel.value);
});

const cerclePathD = (cercle) => {
    if (cercle.code === 'Bamako' && sel.value === 'R1') {
        return 'M181.6 472.5L177.4 462.3L164.6 473.3L156.9 473.3L152.7 480.1L145.9 475.9L139.1 492.9L135.7 507.3L142.5 512.4L145.9 504.8L153.6 507.3L164.6 526L182.4 531.1L183.3 504.8L190.1 497.1L190.1 485.2L187.6 477.6L181.6 472.5Z';
    }
    return cercle.d || '';
};

const cercleCenter = (cercle) => {
    if (cercle.code === 'Bamako' && sel.value === 'R1') {
        return { x: 164.6, y: 495.4 };
    }
    return { x: cercle.cx, y: cercle.cy };
};

const unitTextColor = (unit) => {
    const eff = Number(unit?.effectif) || 0;
    const q = quantileThresholds.value || [1, 2, 3, 4];
    return eff >= (q[3] || 4) ? '#ffffff' : '#0f172a';
};

// Effectif maximum parmi TOUTES les unités de la région (échelle stable indépendante des filtres de recherche)
const maxRegionalEff = computed(() => {
    const list = selRegion.value?.unites || [];
    if (list.length === 0) return 1;
    let m = 1;
    for (const u of list) {
        const eff = Number(u.effectif) || 0;
        if (eff > m) m = eff;
    }
    return m;
});

// Seuils dynamiques par quantiles pour 5 classes de couleurs (20%, 40%, 60%, 80%) sur les effectifs non nuls de la région complète
const quantileThresholds = computed(() => {
    const nonZero = (selRegion.value?.unites || [])
        .map(u => Number(u.effectif) || 0)
        .filter(e => e > 0)
        .sort((a, b) => a - b);

    if (nonZero.length === 0) return [1, 2, 3, 4];
    if (nonZero.length === 1) {
        const val = nonZero[0];
        return [val, val, val, val];
    }

    const getQuantile = (p) => {
        const idx = (nonZero.length - 1) * p;
        const low = Math.floor(idx);
        const high = Math.ceil(idx);
        if (low === high) return nonZero[low];
        return nonZero[low] + (nonZero[high] - nonZero[low]) * (idx - low);
    };

    return [
        getQuantile(0.20),
        getQuantile(0.40),
        getQuantile(0.60),
        getQuantile(0.80)
    ];
});

// 3. Nuancier thématique dynamique en 5 classes par quantiles (Jaune doux -> Ambre -> Orange -> Rouge vif)
const parcelFill = (unit) => {
    const eff = Number(unit?.effectif) || 0;
    if (eff === 0) return '#f8fafc'; // Gris neutre clair (0 effectif)

    const [q1, q2, q3, q4] = quantileThresholds.value;

    if (eff > q4) return '#dc2626'; // Classe 5 : Rouge vif soutenu (Top 20%)
    if (eff > q3) return '#ea580c'; // Classe 4 : Orange profond (60-80%)
    if (eff > q2) return '#f97316'; // Classe 3 : Orange vif (40-60%)
    if (eff > q1) return '#f59e0b'; // Classe 2 : Ambre / Jaune d'or (20-40%)
    return '#fef08a';                // Classe 1 : Jaune doux lumineux (0-20%)
};

// 4. Rayon du cercle proportionnel rouge (Méthode de Jacques Bertin : Surface proportionnelle à l'effectif)
const parcelCircleR = (unit) => {
    const eff = Math.max(0, Number(unit?.effectif) || 0);
    if (eff === 0) return 0; // Strict Jacques Bertin : 0 effectif = AUCUN cercle

    const maxEff = maxRegionalEff.value;
    const isDense = (selRegion.value?.unites?.length || 0) > 15;
    const rMax = (isDense ? 5.2 : 8.5) * rUnit.value;
    const rMin = (isDense ? 1.6 : 3.2) * rUnit.value;

    // Formule Bertin : Surface proportionnelle à l'effectif => r = rMax * sqrt(eff / maxEff)
    const bertinR = rMax * Math.sqrt(eff / maxEff);
    return Math.max(rMin, bertinR);
};

// 5. Chiffre centré dans le cercle proportionnel (grand, gras 900, noir, lisible de loin)
const parcelCircleFontSize = (unit) => {
    const r = parcelCircleR(unit);
    if (r === 0) return 0;
    const eff = Math.max(0, Number(unit?.effectif) || 0);
    const len = String(eff).length;
    // Calibré dynamiquement pour 1 chiffre (ex: 8), 2 chiffres (ex: 45), 3 chiffres (ex: 350), 4+ chiffres (ex: 1200)
    const factor = len >= 4 ? 0.65 : (len === 3 ? 0.78 : (len === 2 ? 0.92 : 1.05));
    return r * factor;
};

// 6. Raccourcissement intelligent des noms pour les libellés de la carte
const shortUnitName = (nom) => {
    if (!nom) return '';
    let s = nom
        .replace(/^Groupement de Gendarmerie (du|de la|des|de)?\s*/i, 'Gpt ')
        .replace(/^Groupement (du|de la|des|de)?\s*/i, 'Gpt ')
        .replace(/^Compagnie Territoriale (du|de la|des|de)?\s*/i, 'Cie ')
        .replace(/^Compagnie (du|de la|des|de)?\s*/i, 'Cie ')
        .replace(/^Brigade Territoriale (du|de la|des|de)?\s*/i, 'B.T. ')
        .replace(/^Brigade de Recherches (du|de la|des|de)?\s*/i, 'B.R. ')
        .replace(/^Brigade d'Investigation Spécialisée.*$/i, 'B.I.S. Terrorisme')
        .replace(/^Brigade (du|de la|des|de)?\s*/i, 'Brigade ')
        .replace(/^Escadron (du|de la|des|de)?\s*/i, 'Esc. ')
        .replace(/^Poste de Sécurité (du|de la|des|de)?\s*/i, 'P.S. ')
        .replace(/^Peloton d'Intervention (du|de la|des|de)?\s*/i, 'P.I. ')
        .replace(/^Section de Recherches\s*/i, 'S.R. ')
        .replace(/^Ministère de la Défense.*$/i, 'Min. Défense')
        .replace(/^Ministère de l'Administration.*$/i, 'Min. Admin.')
        .replace(/^Ministère de la Sécurité.*$/i, 'Min. Sécurité')
        .replace(/^Direction Générale de la Protection Civile.*$/i, 'Prot. Civile')
        .replace(/^Direction de la Sécurité Militaire.*$/i, 'D.S.M.')
        .replace(/^Etat-Major Particulier de la Présidence.*$/i, 'E.M. Présidence')
        .replace(/^Etat-Major Général des Armées.*$/i, 'E.M.G.A.')
        .replace(/^Etat-Major Compagnie.*$/i, 'E.M. Cie')
        .replace(/^Sous-Direction des Finances.*$/i, 'S.D. Finances')
        .replace(/^Sous-Direction des Ressources Humaines.*$/i, 'S.D. R.H.')
        .replace(/^Direction de la Justice Militaire.*$/i, 'Justice Militaire')
        .replace(/^Direction de l'Office Central des Stupéfiants.*$/i, 'O.C.S.')
        .replace(/^Inspection de la Gendarmerie Nationale.*$/i, 'Inspection G.N.')
        .replace(/^Agence Nationale de la Sécurité d'Etat.*$/i, 'A.N.S.E.')
        .replace(/^Service d'Investigations Judiciaires.*$/i, 'S.I.J.')
        .replace(/Gendarmerie\s*/i, '')
        .replace(/Bamako\s*/i, '')
        .trim();
    if (s.length > 20) {
        s = s.slice(0, 18) + '…';
    }
    return s;
};

const shouldShowParcelLabel = (p) => {
    if (p.unit.effectif === 0) return false;
    if (selUnit.value === p.unit.id) return true;
    if (hoveredItem.value?.isUnit && hoveredItem.value?.uId === p.unit.id) return true;
    // Si la région est aérée (<= 6 unités dans la région, ex: Ségou, Sikasso, Kayes), afficher le libellé sous le grand cercle
    if ((selRegion.value?.unites?.length || 0) <= 6) return true;
    // Vue dense (Bamako 26 unités) : aucun libellé pour garder la carte épurée et lisible (exactement comme la carte de France !)
    return false;
};

const onHoverUnitParcel = (u) => {
    hoveredItem.value = {
        isUnit: true,
        uId: u.id,
        op: u.operationnelle,
        title: u.nom,
        subtitle: `${fmt(u.effectif)} militaires`,
        extra: (!u.lat || !u.lon) ? 'Position approx. (sans GPS)' : (u.operationnelle ? 'Opérationnelle' : 'Non opérationnelle')
    };
};

const hoveredUnitObj = computed(() => {
    if (hoveredItem.value?.isUnit && hoveredItem.value?.uId) {
        return (selRegion.value?.unites || []).find((u) => u.id === hoveredItem.value.uId);
    }
    return null;
});

// ---------- Panneaux statistiques ----------
const tot = (f) => props.regions.reduce((s, r) => s + (Number(f(r)) || 0), 0);

const valStat = (k) => {
    if (selRegion.value) {
        return Number(selRegion.value[k]) || 0;
    }
    const sumRegions = tot((r) => r[k]);
    const hors = Number(props.stats?.hors_region?.[k]) || 0;
    return sumRegions + hors;
};

const kpis = computed(() => {
    const r = selRegion.value;
    const eff = r ? r.effectif : (tot((x) => x.effectif) + (Number(props.stats?.non_rattaches) || 0));
    const abs = r ? r.absents : tot((x) => x.absents);
    const nU = r ? r.unites.length : tot((x) => x.unites.length);
    const nOp = r ? opCount(r) : tot(opCount);
    return [
        {
            label: r ? 'Effectif région' : 'Effectif total',
            value: fmt(eff),
            hint: r ? r.nom : `${fmt(tot((x) => x.effectif))} en région • ${fmt(props.stats?.non_rattaches || 0)} en attente`
        },
        {
            label: 'Présents',
            value: fmt(eff - abs),
            hint: eff ? Math.round(((eff - abs) / eff) * 100) + ' % d’activité' : '—'
        },
        {
            label: 'Absents',
            value: fmt(abs),
            hint: 'congés, missions, stages'
        },
        {
            label: 'Unités opérationnelles',
            value: `${fmt(nOp)} / ${fmt(nU)}`,
            hint: nU ? Math.round((nOp / nU) * 100) + ' % maillage' : '—'
        },
    ];
});

// Détail de l'effectif en activité : la somme des 4 catégories égale exactement l'effectif total
const repartition = computed(() => [
    { label: 'Actifs', value: valStat('actifs'), color: '#10b981' },
    { label: 'Détachés', value: valStat('detaches'), color: '#0ea5e9' },
    { label: 'En mission', value: valStat('missions'), color: '#8b5cf6' },
    { label: 'En stage', value: valStat('stages'), color: '#f59e0b' },
]);

// Hors effectif : Radiés, retraites, déserteurs et décédés de l'année (affichés et comptabilisés à part)
const horsEffectif = computed(() => {
    const annee = props.stats?.annee ?? new Date().getFullYear();
    return [
        { label: 'Radiés', value: valStat('radies'), color: '#64748b' },
        { label: `Retraites ${annee}`, value: valStat('retraites'), color: '#9333ea' },
        { label: 'Déserteurs', value: valStat('deserteurs'), color: '#ea580c' },
        { label: `Décédés ${annee}`, value: valStat('decedes'), color: '#e11d48' },
    ];
});

const detail = computed(() => {
    if (!selRegion.value) {
        return [
            { label: 'Circonscriptions', value: props.regions.length === 20 ? '19 Rég. + 1 Dist.' : props.regions.length },
            { label: 'Total Unités', value: tot((r) => r.unites.length) },
            { label: 'Commandement direct', value: fmt(tot((r) => r.actifs || 0)) },
            { label: 'Effectif Global', value: fmt(tot((r) => r.effectif) + (Number(props.stats?.non_rattaches) || 0)) },
        ];
    }
    const r = selRegion.value;
    const total = tot((x) => x.effectif);
    return [
        { label: 'Total Unités', value: r.unites.length },
        { label: 'Part nationale', value: (total ? (r.effectif / total) * 100 : 0).toFixed(1).replace('.', ',') + ' %' },
        { label: 'Hommes · Femmes', value: `${fmt(r.effectif - r.femmes)} · ${fmt(r.femmes)}` },
        { label: 'Présents', value: fmt(r.effectif - r.absents) },
        { label: 'Absents', value: fmt(r.absents) },
        { label: 'Unités op.', value: `${opCount(r)} / ${r.unites.length}` },
    ];
});

const lignes = computed(() => {
    if (selRegion.value) {
        const r = selRegion.value;
        const rows = r.unites.map((u) => ({
            key: 'u' + u.id,
            nom: u.nom,
            value: u.effectif,
            op: u.operationnelle,
            horsCarte: u.lat === null || u.lon === null,
            active: selUnit.value === u.id,
            rawUnit: u,
            click: () => toggleUnit(u.id),
        }));
        if (r.sans_unite > 0) {
            rows.push({
                key: 'su',
                nom: 'Sans unité précise',
                value: r.sans_unite,
                op: true,
                horsCarte: false,
                active: false,
                click: () => {},
            });
        }
        rows.sort((a, b) => b.value - a.value);
        const hi = rows[0]?.value || 1;
        return rows.map((x) => ({ ...x, pct: (x.value / hi) * 100 }));
    }

    const rows = props.regions
        .map((r) => ({
            key: r.code,
            nom: shortName(r.nom),
            fullName: r.nom,
            code: r.code,
            value: valueOf(r),
            rawRegion: r,
            active: false,
            click: () => choisir(r.code),
        }))
        .sort((a, b) => b.value - a.value);
    const hi = rows[0]?.value || 1;
    return rows.map((x) => ({ ...x, pct: (x.value / hi) * 100 }));
});

const selectedUnitObj = computed(() => {
    if (!selUnit.value || !selRegion.value) return null;
    return (selRegion.value.unites || []).find(u => u.id === selUnit.value) || null;
});

// ---------- Modal d'édition des coordonnées géographiques (GPS) ----------
const showEditCoordsModal = ref(false);
const editingUnit = ref(null);
const coordsForm = ref({
    latitude: '',
    longitude: '',
    ville: '',
    region_id: null,
});
const coordsErrors = ref({});
const savingCoords = ref(false);

const openEditCoords = (u) => {
    if (!u) return;
    editingUnit.value = u;
    coordsForm.value = {
        latitude: u.lat !== null && u.lat !== undefined ? String(u.lat) : '',
        longitude: u.lon !== null && u.lon !== undefined ? String(u.lon) : '',
        ville: u.ville || '',
        region_id: u.region_id || (selRegion.value?.id ?? null),
    };
    coordsErrors.value = {};
    showEditCoordsModal.value = true;
};

const saveCoords = () => {
    if (!editingUnit.value) return;
    savingCoords.value = true;
    coordsErrors.value = {};

    router.patch(route('carte.unites.coordinates', editingUnit.value.id), {
        latitude: coordsForm.value.latitude !== '' ? Number(coordsForm.value.latitude) : null,
        longitude: coordsForm.value.longitude !== '' ? Number(coordsForm.value.longitude) : null,
        ville: coordsForm.value.ville,
        region_id: coordsForm.value.region_id,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            savingCoords.value = false;
            showEditCoordsModal.value = false;
            toast.add({
                severity: 'success',
                summary: 'Coordonnées enregistrées',
                detail: `L'implantation de « ${editingUnit.value.nom} » a été actualisée.`,
                life: 4000,
            });
        },
        onError: (err) => {
            savingCoords.value = false;
            coordsErrors.value = err;
        },
    });
};

const lignesFiltrees = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return lignes.value;
    return lignes.value.filter((row) => {
        const text = (row.nom + ' ' + (row.fullName || '') + ' ' + (row.code || '')).toLowerCase();
        return text.includes(q);
    });
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Carte Opérationnelle/SPA" />
        <Toast position="top-right" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-amber-400/20 border border-amber-400/40 flex items-center justify-center text-amber-300">
                        <i class="pi pi-map text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-base font-bold text-white leading-tight">
                            Carte Opérationnelle/SPA
                        </h1>
                        <p class="text-[10px] text-sky-200">
                            {{ regions.length === 20 ? '19 Régions + 1 District' : regions.length + ' régions' }} • Maillage territorial souverain
                        </p>
                    </div>
                </div>
                <div class="hidden sm:flex items-center gap-2 text-[11px] text-sky-100 bg-sky-900/40 border border-sky-600/30 px-2.5 py-1 rounded-md">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Situation au {{ stats.generated_at || 'ce jour' }}</span>
                </div>
            </div>
        </template>

        <!-- Container principal fixé dans le viewport (zéro défilement externe de page) -->
        <div class="min-h-[calc(100vh-4.75rem)] flex flex-col gap-2 overflow-y-auto lg:overflow-hidden -m-1 sm:-m-2">
            <!-- 1. Chiffres clés (KPIs) CLAIRS / BLANCS (comme demandé : "pas les autres cards") -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 flex-none">
                <div
                    v-for="k in kpis"
                    :key="k.label"
                    class="bg-white rounded-lg border border-slate-200/90 px-3 py-1.5 shadow-xs flex items-center justify-between gap-2"
                >
                    <div class="min-w-0">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 truncate">
                            {{ k.label }}
                        </div>
                        <div class="text-[10px] text-slate-400 truncate">
                            {{ k.hint }}
                        </div>
                    </div>
                    <div class="text-lg sm:text-xl font-extrabold text-slate-900 tabular-nums leading-none flex-none">
                        {{ k.value }}
                    </div>
                </div>
            </div>

            <!-- Répartition de l'effectif en activité + éléments hors effectif sur UNE SEULE LIGNE COMPACTE -->
            <div class="flex items-center justify-between gap-2 px-3 py-1 rounded-lg border border-slate-200/90 bg-white text-[11px] shadow-2xs flex-none whitespace-nowrap overflow-x-auto custom-scrollbar">
                <!-- En activité -->
                <div class="flex items-center gap-2 flex-none">
                    <span class="font-bold uppercase tracking-wider text-[10px] text-sky-800 flex items-center gap-1 flex-none">
                        <i class="pi pi-shield text-[10px]"></i>
                        <span>En activité :</span>
                    </span>
                    <span v-for="h in repartition" :key="h.label" class="inline-flex items-center gap-1 flex-none">
                        <span class="w-1.5 h-1.5 rounded-full flex-none" :style="{ backgroundColor: h.color }"></span>
                        <span class="text-slate-500">{{ h.label }} :</span>
                        <strong class="text-slate-900 tabular-nums font-bold">{{ fmt(h.value) }}</strong>
                    </span>
                </div>

                <!-- Séparateur vertical discret -->
                <div class="h-3.5 w-px bg-slate-200 flex-none mx-1"></div>

                <!-- Hors effectif (comptabilisés strictement à part) -->
                <div class="flex items-center gap-2 flex-none">
                    <span class="font-bold uppercase tracking-wider text-[10px] text-rose-800 flex items-center gap-1 flex-none">
                        <i class="pi pi-info-circle text-[10px]"></i>
                        <span>Hors effectif :</span>
                    </span>
                    <span v-for="h in horsEffectif" :key="h.label" class="inline-flex items-center gap-1 flex-none">
                        <span class="w-1.5 h-1.5 rounded-full flex-none" :style="{ backgroundColor: h.color }"></span>
                        <span class="text-slate-500">{{ h.label }} :</span>
                        <strong class="text-slate-900 tabular-nums font-bold">{{ fmt(h.value) }}</strong>
                    </span>
                </div>
            </div>

            <!-- Alerte non rattachés si existants avec accès direct aux recrues pour correction -->
            <div
                v-if="stats.non_rattaches > 0"
                class="rounded-lg border border-amber-300 bg-amber-50 text-amber-900 text-xs px-3 py-2 flex flex-wrap items-center justify-between gap-2 flex-none shadow-xs"
            >
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-amber-200 text-amber-800 flex items-center justify-center shrink-0">
                        <i class="pi pi-exclamation-triangle text-xs"></i>
                    </span>
                    <div>
                        <div class="font-bold text-slate-900">
                            {{ fmt(stats.non_rattaches) }} recrues en attente d'affectation
                        </div>
                        <div class="text-[11px] text-amber-800">
                            Ces militaires sont enregistrés sans région ni unité d'affectation.
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="showNonRattachesModal = true"
                        class="px-2.5 py-1.5 bg-sky-800 hover:bg-sky-900 text-white rounded-md font-bold text-xs flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer"
                        title="Voir la liste détaillée des recrues pour leur assigner une région et unité"
                    >
                        <i class="pi pi-users text-xs"></i>
                        <span>Consulter & Corriger ({{ fmt(stats.non_rattaches) }})</span>
                    </button>
                    <Link
                        :href="route('militaires.index', { non_rattaches: 1 })"
                        class="px-2.5 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 rounded-md font-semibold text-xs flex items-center gap-1.5 transition-colors"
                        title="Ouvrir dans l'annuaire complet"
                    >
                        <i class="pi pi-external-link text-[10px]"></i>
                        <span>Dans l'annuaire</span>
                    </Link>
                </div>
            </div>

            <!-- 2. Zone principale : Cadre blanc standard, SEUL LE CANEVAS DE LA CARTE EST EN BLEU ARDOISE (#16212e) -->
            <div
                ref="carteZoneRef"
                class="flex-1 min-h-0 grid grid-rows-1 gap-2 items-stretch transition-all duration-200"
                :class="[
                    isFullscreen
                        ? 'fixed inset-0 z-50 p-2 sm:p-3 bg-slate-100 w-screen h-screen ' + (showSidePanelInFullscreen ? 'lg:grid-cols-[1fr_295px] xl:grid-cols-[1fr_310px]' : 'grid-cols-1')
                        : 'lg:grid-cols-[1fr_295px] xl:grid-cols-[1fr_310px]'
                ]"
            >
                <!-- PANNEAU CARTE (Cadre blanc standard, en-tête clair, et uniquement l'endroit de la carte en fond sombre #16212e) -->
                <div class="bg-white rounded-lg border border-slate-200 p-2 min-w-0 flex flex-col h-full max-h-full overflow-hidden shadow-sm">
                    <!-- En-tête de la carte CLAIR : BASCULE MODE MAILLAGE vs THÉÂTRES -->
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5 flex-none">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <!-- Sélecteur de Mode : Maillage Territorial vs Théâtres d'Opérations -->
                            <div class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-lg border border-slate-200">
                                <button
                                    type="button"
                                    @click="switchCarteMode('territoire')"
                                    :class="[
                                        'px-2 py-0.5 text-xs font-bold rounded-md transition flex items-center gap-1.5 cursor-pointer',
                                        carteMode === 'territoire'
                                            ? 'bg-white text-sky-900 shadow-xs border border-slate-200/80'
                                            : 'text-slate-600 hover:text-slate-900'
                                    ]"
                                >
                                    <i class="pi pi-map text-[11px]"></i>
                                    <span>Maillage Territorial</span>
                                </button>
                                <button
                                    type="button"
                                    @click="switchCarteMode('theatres')"
                                    :class="[
                                        'px-2 py-0.5 text-xs font-bold rounded-md transition flex items-center gap-1.5 cursor-pointer',
                                        carteMode === 'theatres'
                                            ? 'bg-white text-sky-900 shadow-xs border border-slate-200/80'
                                            : 'text-slate-600 hover:text-slate-900'
                                    ]"
                                >
                                    <i class="pi pi-flag text-sky-700 text-[11px]"></i>
                                    <span>Théâtres d'Opérations ({{ theatres.length }})</span>
                                </button>
                            </div>

                            <!-- CONTROLES DU MODE TERRITOIRE -->
                            <template v-if="carteMode === 'territoire'">
                                <template v-if="sel">
                                    <button
                                        class="chip bg-sky-900 text-white border-sky-900 hover:bg-sky-800 flex items-center gap-1.5 font-bold shadow-xs text-xs px-2.5 py-1"
                                        @click="retour"
                                    >
                                        <i class="pi pi-arrow-left text-[11px]"></i>
                                        <span>← Afficher tout le Mali</span>
                                    </button>
                                    <span class="text-xs font-bold px-2 py-0.5 rounded-full border border-slate-200 bg-slate-100 text-slate-800 flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-sky-600"></span>
                                        <span>{{ selRegion?.nom }}</span>
                                        <span class="text-slate-500 font-normal">({{ selRegion?.unites.length }} unités)</span>
                                    </span>
                                </template>
                                <template v-else>
                                    <button
                                        v-for="(m, key) in METRICS"
                                        :key="key"
                                        class="chip"
                                        :aria-pressed="metric === key"
                                        @click="metric = key"
                                    >
                                        {{ m.label }}
                                    </button>
                                </template>
                            </template>

                            <!-- CONTROLES DU MODE THÉÂTRES -->
                            <template v-else>
                                <template v-if="selTheatre">
                                    <button
                                        class="chip bg-sky-900 text-white border-sky-900 hover:bg-sky-800 flex items-center gap-1.5 font-bold shadow-xs text-xs px-2.5 py-1 cursor-pointer"
                                        @click="selTheatre = null"
                                    >
                                        <i class="pi pi-arrow-left text-[11px]"></i>
                                        <span>← Tous les théâtres</span>
                                    </button>
                                    <span class="text-xs font-bold px-2 py-0.5 rounded-full border border-slate-200 bg-sky-50 text-sky-900 flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: selTheatreObj?.couleur }"></span>
                                        <span>{{ selTheatreObj?.nom }}</span>
                                        <span class="text-sky-700 font-semibold">({{ selTheatreObj?.effectif }} militaires)</span>
                                    </span>
                                </template>
                                <template v-else>
                                    <span class="text-xs text-slate-500 font-medium hidden sm:inline">
                                        Emprises opérationnelles des {{ theatres.length }} théâtres FAMa / Gendarmerie
                                    </span>
                                </template>
                            </template>
                        </div>

                        <!-- Menu déroulant selon le mode -->
                        <div class="flex items-center gap-1.5">
                            <select
                                v-if="carteMode === 'territoire'"
                                :value="sel || ''"
                                @change="onSelectRegionDropdown($event.target.value)"
                                class="text-xs border border-slate-200 rounded-lg px-2 py-0.5 bg-white text-slate-700 focus:outline-none focus:border-sky-500 font-medium"
                                aria-label="Choisir une région"
                            >
                                <option value="">Tout le Mali (19 Régions + District)</option>
                                <option v-for="r in regions" :key="r.code" :value="r.code">
                                    {{ r.code }} - {{ r.nom }}
                                </option>
                            </select>

                            <select
                                v-else
                                :value="selTheatre || ''"
                                @change="selTheatre = $event.target.value || null"
                                class="text-xs border border-slate-200 rounded-lg px-2 py-1 bg-white text-slate-700 focus:outline-none focus:border-sky-500 font-semibold"
                                aria-label="Choisir un théâtre"
                            >
                                <option value="">Tous les théâtres ({{ theatres.length }})</option>
                                <option v-for="th in theatres" :key="th.code" :value="th.code">
                                    {{ th.code }} - {{ th.nom }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- En mode plein écran : bande récapitulative compacte de la situation opérationnelle -->
                    <div
                        v-if="isFullscreen"
                        class="flex items-center justify-between gap-2 px-2.5 py-1 mb-1.5 bg-slate-50 rounded-md border border-slate-200 text-xs text-slate-700 flex-none"
                    >
                        <div class="flex items-center gap-3 overflow-x-auto">
                            <span class="font-bold text-slate-900 flex items-center gap-1">
                                <i class="pi pi-shield text-sky-800 text-xs"></i>
                                <span>Situation opérationnelle :</span>
                            </span>
                            <span v-for="k in kpis" :key="k.label" class="inline-flex items-center gap-1 whitespace-nowrap">
                                <span class="text-slate-500">{{ k.label }} :</span>
                                <strong class="text-slate-900">{{ k.value }}</strong>
                                <span class="text-slate-300">•</span>
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-400 whitespace-nowrap hidden sm:block">
                            Touche <kbd class="px-1.5 py-0.5 bg-white border border-slate-300 rounded font-mono text-[10px] text-slate-700 shadow-2xs">Échap</kbd> ou bouton pour quitter
                        </div>
                    </div>

                    <!-- UNIQUEMENT CET ENDROIT : LE CANEVAS DE LA CARTE EN BLEU ARDOISE ADOUCI (#16212E) -->
                    <div
                        class="flex-1 min-h-[380px] lg:min-h-[430px] xl:min-h-[460px] w-full h-full relative flex items-center justify-center overflow-hidden bg-[#16212e] rounded-lg border border-[#263446] p-1 select-none"
                        :class="{ '!cursor-grabbing': isDragging, 'cursor-grab': true }"
                        @mousedown="onMouseDown($event)"
                        @wheel="onWheel($event)"
                        @touchstart="onTouchStart($event)"
                        @touchmove="onTouchMove($event)"
                        @touchend="onTouchEnd($event)"
                        @dblclick="onDblClick($event)"
                    >
                        <!-- Boutons d'action rapide en haut à droite sur le canevas tactique -->
                        <div class="absolute top-2 right-2 z-20 flex items-center gap-1.5 pointer-events-auto">
                            <button
                                v-if="sel"
                                type="button"
                                @click="retour"
                                class="bg-[#0e1621]/90 hover:bg-[#1a2636] text-white border border-slate-700/80 px-2.5 py-1 rounded-md shadow-md text-xs flex items-center gap-1.5 transition pointer-events-auto font-medium cursor-pointer"
                                title="Retour à la vue complète du Mali"
                            >
                                <i class="pi pi-arrow-left text-[10px] text-sky-300"></i>
                                <span class="hidden sm:inline">Tout le Mali</span>
                            </button>
                            <button
                                v-if="isFullscreen"
                                type="button"
                                @click="showSidePanelInFullscreen = !showSidePanelInFullscreen"
                                class="bg-[#0e1621]/90 hover:bg-[#1a2636] text-white border border-slate-700/80 px-2.5 py-1 rounded-md shadow-md text-xs flex items-center gap-1.5 transition pointer-events-auto font-medium cursor-pointer"
                                :title="showSidePanelInFullscreen ? 'Masquer le volet latéral' : 'Afficher le volet latéral'"
                            >
                                <i :class="showSidePanelInFullscreen ? 'pi pi-eye-slash text-slate-300' : 'pi pi-list text-sky-300'" class="text-xs"></i>
                                <span class="hidden sm:inline">{{ showSidePanelInFullscreen ? 'Masquer volet' : 'Volet détails' }}</span>
                            </button>
                            <button
                                type="button"
                                @click="toggleFullscreen"
                                class="bg-[#0e1621]/90 hover:bg-[#1a2636] text-white border border-slate-700/80 px-2.5 py-1 rounded-md shadow-md text-xs flex items-center gap-1.5 transition pointer-events-auto font-semibold cursor-pointer"
                                :title="isFullscreen ? 'Quitter le mode plein écran (Échap)' : 'Afficher la carte en plein écran'"
                            >
                                <i :class="isFullscreen ? 'pi pi-window-minimize text-amber-300' : 'pi pi-window-maximize text-sky-300'" class="text-xs"></i>
                                <span class="hidden sm:inline">{{ isFullscreen ? 'Réduire' : 'Plein écran' }}</span>
                            </button>
                        </div>

                        <!-- Contrôles interactifs de Zoom (+ / % / - / Recentrer) en bas à droite -->
                        <div class="absolute bottom-3 right-3 z-20 flex flex-col items-center bg-[#0e1621]/90 backdrop-blur border border-slate-700/80 rounded-lg shadow-xl overflow-hidden select-none pointer-events-auto">
                            <button
                                type="button"
                                @click.stop="zoomIn"
                                class="w-8 h-8 flex items-center justify-center text-white hover:bg-slate-700/60 active:bg-slate-700 transition cursor-pointer"
                                title="Zoomer (+)"
                                aria-label="Zoomer"
                            >
                                <i class="pi pi-plus text-xs"></i>
                            </button>
                            <div class="w-5 h-px bg-slate-700/80"></div>
                            <button
                                type="button"
                                @click.stop="resetZoom"
                                class="px-1.5 py-0.5 text-[10px] font-mono font-bold text-sky-400 hover:bg-slate-700/60 active:bg-slate-700 transition cursor-pointer"
                                title="Réinitialiser le zoom (100%)"
                            >
                                {{ Math.round(userZoom * 100) }}%
                            </button>
                            <div class="w-5 h-px bg-slate-700/80"></div>
                            <button
                                type="button"
                                @click.stop="zoomOut"
                                class="w-8 h-8 flex items-center justify-center text-white hover:bg-slate-700/60 active:bg-slate-700 transition cursor-pointer"
                                title="Dézoomer (-)"
                                aria-label="Dézoomer"
                            >
                                <i class="pi pi-minus text-xs"></i>
                            </button>
                            <div class="w-5 h-px bg-slate-700/80"></div>
                            <button
                                type="button"
                                @click.stop="resetZoom"
                                class="w-8 h-8 flex items-center justify-center text-slate-300 hover:text-white hover:bg-slate-700/60 active:bg-slate-700 transition cursor-pointer"
                                title="Recentrer la carte"
                                aria-label="Recentrer"
                            >
                                <i class="pi pi-compass text-xs"></i>
                            </button>
                        </div>

                        <!-- En vue nationale : HUD de survol d'une région -->
                        <transition name="fade">
                            <div
                                v-if="!sel && hoveredInfo"
                                class="absolute top-2 left-2 z-20 bg-[#0e1621]/95 backdrop-blur text-white px-2.5 py-1 rounded-md shadow-lg text-xs pointer-events-none flex items-center gap-2 border border-slate-700"
                            >
                                <span class="w-2 h-2 rounded-full flex-none bg-sky-400"></span>
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-white">{{ hoveredInfo.title }}</span>
                                    <span class="text-slate-400">•</span>
                                    <span class="text-sky-300 font-medium">{{ hoveredInfo.subtitle }}</span>
                                    <span v-if="hoveredInfo.extra" class="text-slate-400 text-[10px]">({{ hoveredInfo.extra }})</span>
                                </div>
                            </div>
                        </transition>

                        <!-- En vue régionale : En-tête de région, Filtres et Recherche sur la carte -->
                        <div
                            v-if="sel && selRegion"
                            class="absolute top-2 left-2 z-20 flex flex-col gap-1.5 pointer-events-auto max-w-[85%] sm:max-w-md"
                        >
                            <!-- Ligne 1 : Titre et effectif de la région -->
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <button
                                    type="button"
                                    @click="retour"
                                    class="px-2 py-0.5 rounded text-xs font-bold bg-[#090e17]/95 hover:bg-[#1a2636] text-white border border-slate-700/80 shadow-md flex items-center gap-1 transition cursor-pointer"
                                    title="Revenir à la vue complète du Mali"
                                >
                                    <i class="pi pi-arrow-left text-[10px] text-sky-400"></i>
                                    <span>Tout le Mali</span>
                                </button>
                                <span class="px-2 py-0.5 rounded text-xs font-black bg-[#090e17]/95 border border-sky-500/50 text-white shadow-md flex items-center gap-1.5">
                                    <span class="px-1.5 py-0.2 rounded text-[10px] bg-sky-500/30 text-sky-300 font-mono">{{ selRegion.code }}</span>
                                    <span>{{ selRegion.code === 'R1' ? 'BAMAKO (HQ)' : selRegion.nom.toUpperCase() }}</span>
                                    <span class="text-sky-400 font-bold">• {{ fmt(selRegion.effectif) }} militaires</span>
                                </span>
                            </div>

                            <!-- Ligne 2 : Filtres d'affichage & recherche -->
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <div class="inline-flex rounded shadow-md bg-[#090e17]/95 p-0.5 border border-slate-700/80 text-[11px]">
                                    <button
                                        type="button"
                                        @click="parcelFilterType = 'with_eff'"
                                        class="px-2 py-0.5 rounded transition flex items-center gap-1 cursor-pointer"
                                        :class="parcelFilterType === 'with_eff' ? 'bg-sky-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                                        title="Unités avec effectif > 0 (Style Carte de France)"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-white"></span>
                                        <span>Avec effectif ({{ countWithEff }})</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="parcelFilterType = 'all'"
                                        class="px-2 py-0.5 rounded transition cursor-pointer"
                                        :class="parcelFilterType === 'all' ? 'bg-sky-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                                    >
                                        Toutes ({{ selRegion.unites.length }})
                                    </button>
                                    <button
                                        type="button"
                                        @click="parcelFilterType = 'zero_eff'"
                                        class="px-2 py-0.5 rounded transition flex items-center gap-1 cursor-pointer"
                                        :class="parcelFilterType === 'zero_eff' ? 'bg-slate-700 text-white font-bold' : 'text-slate-400 hover:text-white'"
                                        title="Unités sans effectif (0)"
                                    >
                                        <span>Sans effectif ({{ countZeroEff }})</span>
                                    </button>
                                </div>

                                <div class="relative w-36 sm:w-44">
                                    <input
                                        v-model="parcelSearchInput"
                                        type="text"
                                        placeholder="Filtrer brigade..."
                                        class="w-full text-[11px] py-0.5 pl-5 pr-5 rounded border border-slate-700/80 bg-[#090e17]/95 text-white placeholder-slate-400 focus:outline-none focus:border-sky-400 shadow-md"
                                    />
                                    <i class="pi pi-search absolute left-1.5 top-1.5 text-[10px] text-slate-400"></i>
                                    <button
                                        v-if="parcelSearchInput"
                                        @click="parcelSearchInput = ''; parcelSearchQuery = ''"
                                        class="absolute right-1.5 top-1 text-slate-400 hover:text-white text-[10px] cursor-pointer"
                                    >
                                        <i class="pi pi-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- En vue régionale : HUD flottant d'infobulle au survol d'une unité -->
                        <transition name="fade">
                            <div
                                v-if="sel && hoveredUnitObj"
                                class="absolute bottom-12 left-2 z-20 bg-[#0e1621]/95 backdrop-blur text-white px-3 py-1.5 rounded-md shadow-xl text-xs pointer-events-none flex items-center gap-2 border border-slate-700"
                            >
                                <span
                                    class="w-2.5 h-2.5 rounded-full flex-none"
                                    :style="{ background: hoveredUnitObj.effectif > 0 ? '#ef4444' : '#64748b' }"
                                ></span>
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-white text-xs sm:text-[13px]">{{ hoveredUnitObj.nom }}</span>
                                    <span class="text-slate-400">•</span>
                                    <span class="text-amber-300 font-bold">{{ hoveredUnitObj.effectif }} gendarme(s)</span>
                                    <span v-if="hoveredUnitObj.ville" class="text-slate-400 text-[11px]">📍 {{ hoveredUnitObj.ville }}</span>
                                    <span
                                        v-if="!hoveredUnitObj.lat || !hoveredUnitObj.lon"
                                        class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-950/80 text-amber-300 border border-amber-600/50"
                                    >
                                        Position approximative (sans GPS)
                                    </span>
                                    <span
                                        class="px-1.5 py-0.2 rounded text-[10px] font-bold"
                                        :class="hoveredUnitObj.operationnelle ? 'bg-emerald-900/60 text-emerald-300 border border-emerald-500/40' : 'bg-amber-900/60 text-amber-300 border border-amber-500/40'"
                                    >
                                        {{ hoveredUnitObj.operationnelle ? 'Opérationnelle' : 'En réserve' }}
                                    </span>
                                </div>
                            </div>
                        </transition>

                        <!-- CARTE EN MODE THÉÂTRES D'OPÉRATIONS (DISPOSITIF TACTIQUE SANS CHEVAUCHEMENT) -->
                        <svg
                            v-if="carteMode === 'theatres'"
                            viewBox="0 0 900 560"
                            class="w-full h-full max-h-full block select-none pointer-events-auto"
                            preserveAspectRatio="xMidYMid meet"
                            role="img"
                            aria-label="Carte opérationnelle des théâtres"
                        >
                            <defs>
                                <pattern id="tactical-grid" width="40" height="40" patternUnits="userSpaceOnUse">
                                    <path d="M40 0 H0 V40" fill="none" stroke="#1b2a47" stroke-width="1" />
                                </pattern>
                            </defs>
                            <!-- Fond quadrillé tactique sombre -->
                            <rect width="900" height="560" fill="url(#tactical-grid)" />

                            <!-- Rayons tactiques proportionnels aux effectifs -->
                            <circle
                                v-for="t in tacticalTheatres"
                                :key="'halo-' + t.code"
                                :cx="t.px"
                                :cy="t.py"
                                :r="t.haloR"
                                :fill="t.couleur"
                                fill-opacity="0.14"
                                :stroke="t.couleur"
                                stroke-opacity="0.45"
                                stroke-dasharray="3 3"
                            />

                            <!-- Groupes interactifs de chaque théâtre (Ligne leader + PC star + Étiquette latérale) -->
                            <g
                                v-for="t in tacticalTheatres"
                                :key="'tactical-' + t.code"
                                :class="[
                                    'cursor-pointer transition-opacity duration-150',
                                    (selTheatre && selTheatre !== t.code) || (hoveredTheatre && hoveredTheatre !== t.code && !selTheatre) ? 'dim' : '',
                                    selTheatre === t.code ? 'sel' : '',
                                    hoveredTheatre === t.code ? 'hov' : ''
                                ]"
                                @mouseenter="hoveredTheatre = t.code"
                                @mouseleave="hoveredTheatre = null"
                                @click="ouvrirModalMilitairesTheatre('tous', t)"
                            >
                                <!-- Trait de rappel (leader line sans croisement) -->
                                <path
                                    class="lead"
                                    :d="t.leadD"
                                    :stroke="t.couleur"
                                    stroke-width="1.2"
                                />

                                <!-- Point de commandement central avec étoile ★ -->
                                <g class="mk" :transform="`translate(${t.px}, ${t.py})`">
                                    <circle class="halo" :r="t.markerR + 5" :fill="t.couleur" />
                                    <circle :r="t.markerR" fill="#0b1220" :stroke="t.couleur" stroke-width="3" />
                                    <text text-anchor="middle" y="4" :fill="t.couleur" font-size="11" font-weight="bold">★</text>
                                </g>

                                <!-- Étiquette latérale sur 2 colonnes (gauche / droite) sans chevauchement -->
                                <g class="lab" :transform="`translate(${t.lx}, ${t.ly})`">
                                    <rect
                                        width="158"
                                        height="44"
                                        :stroke="t.couleur"
                                    />
                                    <rect
                                        width="5"
                                        height="44"
                                        rx="2"
                                        :fill="t.couleur"
                                        stroke="none"
                                    />
                                    <text x="14" y="16" class="s">
                                        {{ t.code }} · {{ t.nomCourt }}
                                    </text>
                                    <text x="14" y="35" class="n">
                                        {{ fmt(t.effectif) }}
                                    </text>
                                    <text :x="14 + String(fmt(t.effectif)).length * 7.4 + 5" y="35" class="s">
                                        gendarme{{ t.effectif > 1 ? 's' : '' }}
                                    </text>
                                </g>
                            </g>
                        </svg>

                        <!-- CARTE EN MODE MAILLAGE TERRITORIAL (MALI ENTIER / RÉGIONS / BRIGADES) -->
                        <svg
                            v-else
                            ref="svgRef"
                            :viewBox="viewBox.join(' ')"
                            class="w-full h-full max-h-full block select-none pointer-events-auto"
                            preserveAspectRatio="xMidYMid meet"
                            role="img"
                            aria-label="Carte du Mali par région"
                        >
                            <defs>
                                <filter id="pin-shadow" x="-30%" y="-30%" width="160%" height="160%">
                                    <feDropShadow dx="0" dy="0.8" stdDeviation="0.8" flood-opacity="0.5" flood-color="#000000" />
                                </filter>
                                <filter id="badge-glow" x="-20%" y="-20%" width="140%" height="140%">
                                    <feDropShadow dx="0" dy="1.5" stdDeviation="2" flood-opacity="0.75" flood-color="#000000" />
                                </filter>
                                <clipPath id="region-clip-shape">
                                    <path v-if="sel && selRegion" :d="pathD(selRegion)" />
                                </clipPath>
                            </defs>

                            <!-- Vue nationale : Tracés des régions SVG quand !sel (Mali entier) -->
                            <template v-if="!sel">
                                <template v-for="r in regions" :key="r.code">
                                    <path
                                        v-if="SHAPES[r.code] && r.code !== 'R1'"
                                        :d="pathD(r)"
                                        class="reg"
                                        :class="{
                                            hovered: hoveredCode === r.code
                                        }"
                                        :style="{
                                            fill: fillFor(r),
                                            stroke: '#16212e',
                                            strokeWidth: 1.2 * zoom
                                        }"
                                        @click="handleRegionClick(r.code)"
                                        @mouseenter="onHoverRegion(r)"
                                        @mouseleave="onLeaveHover()"
                                    />
                                </template>

                                <!-- Vue nationale MAILLAGE TERRITORIAL : BADGES TACTIQUES HAUTE VISIBILITÉ DES RÉGIONS & CHIFFRES CLAIRS -->
                                <template v-if="carteMode !== 'theatres'">
                                    <!-- 1. Régions avec effectif > 0 (hors Bamako) : Badges tactiques contrastés avec chiffre agrandi -->
                                    <template v-for="r in regions" :key="'badge-' + r.code">
                                        <g
                                            v-if="SHAPES[r.code] && r.code !== 'R1' && valueOf(r) > 0"
                                            class="tactical-badge pointer-events-none"
                                            :transform="`translate(${SHAPES[r.code].cx}, ${SHAPES[r.code].cy})`"
                                        >
                                            <!-- Boîtier sombre avec bordure cyan lumineuse et ombre portée -->
                                            <rect
                                                :x="-badgeWidth(r) / 2"
                                                y="-11"
                                                :width="badgeWidth(r)"
                                                height="22"
                                                rx="5"
                                                ry="5"
                                                fill="#090e17"
                                                stroke="#38bdf8"
                                                stroke-width="1.4"
                                                filter="url(#badge-glow)"
                                            />
                                            <!-- Ligne 1 : Nom de la région en majuscules cyan pastel -->
                                            <text
                                                x="0"
                                                y="-2"
                                                text-anchor="middle"
                                                class="tactical-badge-nom"
                                            >
                                                {{ shortName(r.nom).toUpperCase() }}
                                            </text>
                                            <!-- Ligne 2 : CHIFFRE GRAND ET EXTRA-VISIBLE (BLANC ÉCLATANT) -->
                                            <text
                                                x="0"
                                                y="8"
                                                text-anchor="middle"
                                                class="tactical-badge-val"
                                            >
                                                {{ fmt(valueOf(r)) }}
                                            </text>
                                        </g>
                                    </template>

                                    <!-- 2. Régions avec effectif == 0 : Libellé discret mais propre (aucun flou) -->
                                    <template v-for="r in regions" :key="'zero-' + r.code">
                                        <g
                                            v-if="SHAPES[r.code] && r.code !== 'R1' && valueOf(r) === 0"
                                            class="lbl-zero pointer-events-none"
                                            :transform="`translate(${SHAPES[r.code].cx}, ${SHAPES[r.code].cy})`"
                                        >
                                            <text x="0" y="-2" text-anchor="middle" class="lbl-zero-nm">
                                                {{ shortName(r.nom) }}
                                            </text>
                                            <text x="0" y="8" text-anchor="middle" class="lbl-zero-num">
                                                0
                                            </text>
                                        </g>
                                    </template>

                                    <!-- 3. District de Bamako (R1) : Grand badge HQ avec ligne guide lumineuse sans chevauchement -->
                                    <template v-for="r in regions.filter(x => x.code === 'R1' && SHAPES.R1)" :key="'b'">
                                        <!-- Point d'ancrage central Bamako avec impulsion lumineuse -->
                                        <circle
                                            class="reg cursor-pointer"
                                            :cx="SHAPES.R1.cx"
                                            :cy="SHAPES.R1.cy"
                                            r="6"
                                            fill="#38bdf8"
                                            stroke="#ffffff"
                                            stroke-width="1.8"
                                            filter="url(#badge-glow)"
                                            @click="handleRegionClick('R1')"
                                            @mouseenter="onHoverRegion(r)"
                                            @mouseleave="onLeaveHover"
                                        />
                                        <circle
                                            :cx="SHAPES.R1.cx"
                                            :cy="SHAPES.R1.cy"
                                            r="2.5"
                                            fill="#ffffff"
                                            class="pointer-events-none"
                                        />
                                        <!-- Ligne guide tactique vers l'ouest dégagé -->
                                        <line
                                            class="lead pointer-events-none"
                                            :x1="SHAPES.R1.cx"
                                            :y1="SHAPES.R1.cy"
                                            :x2="SHAPES.R1.cx - 45"
                                            :y2="SHAPES.R1.cy + 22"
                                            stroke="#38bdf8"
                                            stroke-width="1.5"
                                            stroke-dasharray="3,2"
                                        />
                                        <!-- Grand badge tactique Bamako -->
                                        <g
                                            class="pointer-events-none"
                                            :transform="`translate(${SHAPES.R1.cx - 45}, ${SHAPES.R1.cy + 22})`"
                                        >
                                            <rect
                                                x="-38"
                                                y="-12"
                                                width="76"
                                                height="24"
                                                rx="6"
                                                ry="6"
                                                fill="#082f49"
                                                stroke="#38bdf8"
                                                stroke-width="1.8"
                                                filter="url(#badge-glow)"
                                            />
                                            <text x="0" y="-2" text-anchor="middle" class="tactical-bamako-nom">
                                                ★ BAMAKO (HQ)
                                            </text>
                                            <text x="0" y="8" text-anchor="middle" class="tactical-bamako-val">
                                                {{ fmt(valueOf(r)) }}
                                            </text>
                                        </g>
                                    </template>
                                </template>
                            </template>

                            <!-- Vue régionale : OPTION 1 - RÉGION DÉCOUPÉE EN PARCELLES GÉOGRAPHIQUES TRACÉES (STYLE CARTE DE FRANCE) -->
                            <template v-else>
                                <!-- 1. Fond neutre de la région -->
                                <path
                                    v-if="selRegion"
                                    :d="pathD(selRegion)"
                                    fill="#f8fafc"
                                    stroke="#0f172a"
                                    :stroke-width="0.35 * rUnit"
                                />

                                <!-- 2. Vrai découpage officiel des Cercles de la région -->
                                <g v-if="activeCercles.length > 0" clip-path="url(#region-clip-shape)">
                                    <template v-for="cercle in activeCercles" :key="'cercle-' + cercle.code">
                                        <!-- Délimitation géographique du cercle -->
                                        <path
                                            :d="cerclePathD(cercle)"
                                            fill="#ffffff"
                                            stroke="#94a3b8"
                                            :stroke-width="0.32 * rUnit"
                                            stroke-dasharray="3,1.5"
                                            stroke-linejoin="round"
                                            class="transition-colors duration-150 hover:fill-slate-50"
                                        />
                                        <!-- Nom officiel du cercle -->
                                        <text
                                            :x="cercleCenter(cercle).x"
                                            :y="cercleCenter(cercle).y"
                                            text-anchor="middle"
                                            dominant-baseline="central"
                                            class="select-none pointer-events-none"
                                            :style="{
                                                fontSize: (cercle.code === 'Segou' ? 1.6 : 2.0) * rUnit + 'px',
                                                fill: '#94a3b8',
                                                fontWeight: 700,
                                                letterSpacing: '0.05em',
                                                textTransform: 'uppercase'
                                            }"
                                        >
                                            {{ cercle.nom }}
                                        </text>
                                    </template>
                                </g>

                                <!-- Message si 0 unité visible dans la région -->
                                <g v-if="selRegion && countUnitesVisibles === 0" class="pointer-events-none select-none">
                                    <text
                                        :x="selRegion.code === 'R1' ? 163 : (SHAPES[selRegion.code]?.cx || 300)"
                                        :y="selRegion.code === 'R1' ? 495 : (SHAPES[selRegion.code]?.cy || 300)"
                                        text-anchor="middle"
                                        dominant-baseline="central"
                                        :style="{
                                            fontSize: 2.2 * rUnit + 'px',
                                            fill: '#64748b',
                                            fontWeight: 700
                                        }"
                                    >
                                        {{ parcelSearchQuery ? 'Aucune unité correspondante' : 'Aucune unité géolocalisée' }}
                                    </text>
                                </g>

                                <!-- 3. Bordure extérieure nette de la région en trait sombre -->
                                <path
                                    v-if="selRegion"
                                    :d="pathD(selRegion)"
                                    fill="none"
                                    stroke="#0f172a"
                                    :stroke-width="0.35 * rUnit"
                                    class="pointer-events-none"
                                />

                                <!-- 5. Disques proportionnels Jacques Bertin (halo & cercles style Carte de France) -->
                                <template v-for="p in regionalParcels" :key="'marker-' + p.unit.id">
                                    <g
                                        v-if="isUnitVisible(p.unit)"
                                        class="cursor-pointer"
                                        @click="handleUnitClick(p.unit.id)"
                                        @mouseenter="onHoverUnitParcel(p.unit)"
                                        @mouseleave="onLeaveHover"
                                    >
                                        <!-- Halo cyan si unité sélectionnée -->
                                        <circle
                                            v-if="selUnit === p.unit.id && parcelCircleR(p.unit) > 0"
                                            :cx="p.cx"
                                            :cy="p.cy"
                                            :r="parcelCircleR(p.unit) + 1.2 * rUnit"
                                            fill="none"
                                            stroke="#0284c7"
                                            :stroke-width="0.5 * rUnit"
                                            class="animate-pulse"
                                        />

                                        <!-- Double contour noir épais pour les unités majeures (Top quantile 5) -->
                                        <circle
                                            v-if="p.unit.effectif > 0 && p.unit.effectif >= quantileThresholds[3] && parcelCircleR(p.unit) > 0"
                                            :cx="p.cx"
                                            :cy="p.cy"
                                            :r="parcelCircleR(p.unit) + 0.65 * rUnit"
                                            fill="none"
                                            stroke="#000000"
                                            :stroke-width="0.4 * rUnit"
                                        />

                                        <!-- Cercle proportionnel avec couleur selon effectif (pointillé si position approx.) -->
                                        <circle
                                            v-if="parcelCircleR(p.unit) > 0"
                                            :cx="p.cx"
                                            :cy="p.cy"
                                            :r="parcelCircleR(p.unit)"
                                            :fill="parcelFill(p.unit)"
                                            :stroke="selUnit === p.unit.id ? '#0284c7' : '#0f172a'"
                                            :stroke-width="(selUnit === p.unit.id ? 0.6 : 0.35) * rUnit"
                                            :stroke-dasharray="(!p.unit.lat || !p.unit.lon) ? `${1.2 * rUnit} ${0.9 * rUnit}` : undefined"
                                            filter="url(#pin-shadow)"
                                        />
                                    </g>
                                </template>

                                <!-- 6. Chiffres des effectifs au premier plan (toujours au-dessus des cercles, ultra-lisibles) -->
                                <template v-for="p in regionalParcels" :key="'text-' + p.unit.id">
                                    <g
                                        v-if="isUnitVisible(p.unit)"
                                        class="pointer-events-none select-none"
                                    >
                                        <!-- Chiffre de l'effectif au centre du cercle proportionnel -->
                                        <text
                                            v-if="p.unit.effectif > 0 && parcelCircleFontSize(p.unit) > 0"
                                            :x="p.cx"
                                            :y="p.cy"
                                            text-anchor="middle"
                                            dominant-baseline="central"
                                            :font-size="parcelCircleFontSize(p.unit) + 'px'"
                                            font-weight="900"
                                            :fill="unitTextColor(p.unit)"
                                            style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;"
                                        >
                                            {{ p.unit.effectif }}
                                        </text>

                                        <!-- Libellé de l'unité : UNIQUEMENT pour les régions aérées (<= 6 unités) ou au survol/clic -->
                                        <text
                                            v-if="shouldShowParcelLabel(p)"
                                            class="font-bold"
                                            text-anchor="middle"
                                            :x="p.cx"
                                            :y="p.cy + parcelCircleR(p.unit) + 2.2 * rUnit"
                                            :style="{
                                                fontSize: (selUnit === p.unit.id ? 2.0 : 1.6) * rUnit + 'px',
                                                fill: '#0f172a',
                                                stroke: '#ffffff',
                                                strokeWidth: 0.6 * rUnit + 'px',
                                                paintOrder: 'stroke fill',
                                                fontWeight: 800
                                            }"
                                        >
                                            {{ shortUnitName(p.unit.nom) }}
                                        </text>
                                    </g>
                                </template>
                            </template>
                        </svg>
                    </div>

                    <!-- Barre de sélection rapide au bas de la carte (accès en 1 clic) CLAIRE -->
                    <div v-if="carteMode === 'theatres'" class="flex items-center gap-1 overflow-x-auto py-1 px-1 mt-1 bg-slate-50/90 rounded-md border border-slate-200 text-[11px] flex-none custom-scrollbar">
                        <button
                            class="px-2 py-0.5 rounded font-semibold transition-all flex items-center gap-1 flex-none cursor-pointer"
                            :class="!selTheatre ? 'bg-sky-900 text-white shadow-xs font-bold' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'"
                            @click="selTheatre = null"
                            title="Afficher tous les théâtres"
                        >
                            <span>⚔️ Tous les Théâtres</span>
                        </button>
                        <div class="h-3 w-px bg-slate-300 flex-none mx-0.5"></div>
                        <button
                            v-for="th in theatres"
                            :key="th.code"
                            class="px-2 py-0.5 rounded font-medium transition-all flex items-center gap-1 flex-none cursor-pointer"
                            :class="selTheatre === th.code ? 'bg-sky-900 text-white shadow-xs font-bold' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'"
                            @click="selTheatre = selTheatre === th.code ? null : th.code"
                            :title="th.nom + ' (PC ' + (th.pc_lieu || 'N/A') + ')'"
                        >
                            <span class="w-2.5 h-2.5 rounded-full flex-none"
                                :style="{ background: th.couleur }"
                            ></span>
                            <span class="font-mono font-bold">{{ th.code }}</span>
                            <span class="text-[10px] opacity-90 tabular-nums">
                                ({{ th.effectif }}<span v-if="th.effectif_appui" class="text-amber-600 font-bold ml-0.5">+{{ th.effectif_appui }}</span>)
                            </span>
                        </button>
                    </div>

                    <div v-else class="flex items-center gap-1 overflow-x-auto py-1 px-1 mt-1 bg-slate-50/90 rounded-md border border-slate-200 text-[11px] flex-none custom-scrollbar">
                        <button
                            class="px-2 py-0.5 rounded font-semibold transition-all flex items-center gap-1 flex-none cursor-pointer"
                            :class="!sel ? 'bg-sky-900 text-white shadow-xs' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'"
                            @click="retour"
                            title="Afficher la carte complète du Mali"
                        >
                            <span>🌐 Tout le Mali</span>
                        </button>
                        <div class="h-3 w-px bg-slate-300 flex-none mx-0.5"></div>
                        <button
                            v-for="r in regions"
                            :key="r.code"
                            class="px-1.5 py-0.5 rounded font-medium transition-all flex items-center gap-1 flex-none cursor-pointer"
                            :class="sel === r.code ? 'bg-sky-900 text-white shadow-xs font-bold' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'"
                            @click="choisir(r.code)"
                            :title="r.nom + ' (' + r.unites.length + ' unités)'"
                        >
                            <span
                                class="w-2 h-2 rounded-full flex-none"
                                :style="{ background: fillFor(r) }"
                            ></span>
                            <span>{{ r.code }}</span>
                            <span class="text-[10px] opacity-75 tabular-nums">({{ r.effectif }})</span>
                        </button>
                    </div>

                    <!-- Légende opérationnelle au bas de la carte -->
                    <div class="flex flex-wrap items-center justify-between gap-2 mt-1 text-[11px] text-slate-500 flex-none">
                        <template v-if="carteMode === 'theatres'">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-slate-700">Dispositif des Théâtres d'Opérations & Emprises territoriales</span>
                                <span class="text-slate-300">•</span>
                                <span class="text-slate-500">Cliquez sur un théâtre ou une région sous emprise pour consulter les militaires déployés</span>
                            </div>
                            <span class="text-[10px] text-slate-400">Total : {{ theatres.reduce((s, t) => s + (t.effectif || 0), 0) }} militaires déployés au front</span>
                        </template>
                        <template v-else-if="sel">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <!-- Règle 1 : Cercle effectif -->
                                <span class="flex items-center gap-1.5 font-semibold text-slate-800">
                                    <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-red-500 border border-black text-[8px] font-black text-black">N</span>
                                    <span>Disque : Effectif de l'unité</span>
                                </span>

                                <span class="text-slate-300">•</span>

                                <!-- Règle 2 : Niveaux d'effectif avec chiffres précis sans jargon mathématique -->
                                <span class="flex items-center gap-1 font-medium text-slate-700">
                                    <span class="text-slate-500 font-semibold mr-0.5">Niveau :</span>
                                    <span class="inline-flex items-center gap-0.5" title="Unité à 0 effectif">
                                        <span class="inline-block w-2.5 h-2.5 rounded-xs bg-slate-200 border border-slate-700"></span> 0
                                    </span>
                                    <span class="inline-flex items-center gap-0.5" :title="'Faible effectif : 1 à ' + Math.max(1, Math.round(quantileThresholds[0])) + ' militaire(s)'">
                                        <span class="inline-block w-2.5 h-2.5 rounded-xs bg-yellow-200 border border-slate-700 ml-1"></span>
                                        Faible (≤ {{ Math.max(1, Math.round(quantileThresholds[0])) }})
                                    </span>
                                    <span class="inline-flex items-center gap-0.5" title="Effectif modéré">
                                        <span class="inline-block w-2.5 h-2.5 rounded-xs bg-amber-400 border border-slate-700 ml-1"></span>
                                        Moyen
                                    </span>
                                    <span class="inline-flex items-center gap-0.5" title="Effectif important">
                                        <span class="inline-block w-2.5 h-2.5 rounded-xs bg-orange-500 border border-slate-700 ml-1"></span>
                                        Élevé
                                    </span>
                                    <span class="inline-flex items-center gap-0.5" :title="'Fort effectif : à partir de ' + Math.round(quantileThresholds[3]) + ' militaires'">
                                        <span class="inline-block w-2.5 h-2.5 rounded-xs bg-red-600 border border-slate-700 ml-1"></span>
                                        Très élevé (≥ {{ Math.round(quantileThresholds[3]) }})
                                    </span>
                                </span>

                                <span class="text-slate-300">•</span>

                                <!-- Règle 3 : Découpage des cercles -->
                                <span class="flex items-center font-medium text-slate-600" title="Découpage officiel en cercles administratifs">
                                    Découpage des cercles administratifs
                                </span>

                                <span class="text-slate-300">•</span>

                                <!-- Règle 4 : Unités sans GPS -->
                                <span class="flex items-center gap-1 font-medium text-slate-600" title="Unité sans coordonnées GPS : position estimée au chef-lieu de sa région">
                                    <svg width="12" height="12" class="inline-block shrink-0"><circle cx="6" cy="6" r="4.5" fill="none" stroke="#64748b" stroke-width="1.2" stroke-dasharray="2 1.5"/></svg>
                                    <span>Pointillé : Sans GPS (au chef-lieu)</span>
                                </span>
                            </div>

                            <span class="text-slate-600 font-bold tabular-nums">
                                {{ countUnitesVisibles }} unité(s) cartographiée(s)
                            </span>
                        </template>
                        <template v-else>
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-slate-700">20 Régions militaires cartographiées</span>
                                <span class="text-slate-300">•</span>
                                <span class="text-slate-500">Cliquez sur une région pour isoler ses brigades</span>
                            </div>
                            <span class="text-[10px] text-slate-400">Total : {{ tot(x => x.unites.length) }} unités répertoriées</span>
                        </template>
                    </div>
                </div>

                <!-- PANNEAU DROIT CLAIR / BLANC (même niveau et alignement que la carte) -->
                <div
                    v-show="!isFullscreen || showSidePanelInFullscreen"
                    class="bg-white rounded-lg border border-slate-200 p-2 min-w-0 flex flex-col h-full max-h-full overflow-hidden shadow-sm text-slate-900 transition-all"
                >
                    <!-- VOLET EN MODE THÉÂTRES D'OPÉRATIONS -->
                    <template v-if="carteMode === 'theatres'">
                        <!-- 1. VUE D'ENSEMBLE (Aucun théâtre sélectionné) -->
                        <template v-if="!selTheatre">
                            <!-- En-tête -->
                            <div class="flex items-center justify-between gap-2 flex-none pb-1.5 border-b border-slate-200">
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
                                        <i class="pi pi-flag text-sky-700 text-xs"></i>
                                        <span>Théâtres d'Opérations</span>
                                    </h2>
                                    <p class="text-[10px] text-slate-500">
                                        Dispositif opérationnel FAMa / Gendarmerie
                                    </p>
                                </div>
                                <div class="flex items-center gap-1.5 flex-none">
                                    <button
                                        type="button"
                                        @click="exportTheatresExcel(null)"
                                        :disabled="exportingTheatresExcel"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-bold bg-emerald-700 hover:bg-emerald-800 active:bg-emerald-900 text-white shadow-xs transition cursor-pointer disabled:opacity-50"
                                        title="Exporter la situation opérationnelle des théâtres et unités d'appui en Excel (.xlsx)"
                                    >
                                        <i :class="exportingTheatresExcel ? 'pi pi-spin pi-spinner text-[10px]' : 'pi pi-file-excel text-[10px]'"></i>
                                        <span>{{ exportingTheatresExcel ? 'Export...' : 'Excel' }}</span>
                                    </button>
                                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-sky-100 text-sky-800 border border-sky-200">
                                        {{ theatres.length }} théâtres
                                    </span>
                                </div>
                            </div>

                            <!-- Grille métriques globale théâtres -->
                            <div class="grid grid-cols-4 gap-1 my-1.5 p-1 border border-slate-200/70 flex-none bg-sky-50/50 rounded text-center">
                                <div class="min-w-0 px-0.5">
                                    <div class="text-[8.5px] text-slate-500 font-semibold truncate leading-tight uppercase">Gendarmes</div>
                                    <div class="text-xs font-bold text-sky-900 tabular-nums">
                                        {{ theatres.reduce((s, t) => s + (t.effectif_gendarmerie || t.effectif || 0), 0) }}
                                    </div>
                                </div>
                                <div class="min-w-0 px-0.5">
                                    <div class="text-[8.5px] text-amber-700 font-semibold truncate leading-tight uppercase">Appuis FAMa</div>
                                    <div class="text-xs font-bold text-amber-700 tabular-nums">
                                        +{{ theatres.reduce((s, t) => s + (t.effectif_appui || 0), 0) }}
                                    </div>
                                </div>
                                <div class="min-w-0 px-0.5">
                                    <div class="text-[8.5px] text-slate-600 font-semibold truncate leading-tight uppercase">Total Forces</div>
                                    <div class="text-xs font-bold text-slate-900 tabular-nums">
                                        {{ theatres.reduce((s, t) => s + (t.effectif_global || t.effectif || 0), 0) }}
                                    </div>
                                </div>
                                <div class="min-w-0 px-0.5">
                                    <div class="text-[8.5px] text-rose-600 font-semibold truncate leading-tight uppercase">Blessés</div>
                                    <div class="text-xs font-bold text-rose-700 tabular-nums">
                                        {{ theatres.reduce((s, t) => s + (t.blesses || 0), 0) }}
                                    </div>
                                </div>
                            </div>

                            <!-- Liste des 10 Théâtres triée par effectif décroissant -->
                            <div class="flex-1 overflow-y-auto space-y-2 pr-0.5 custom-scrollbar min-h-0">
                                <div
                                    v-for="th in tacticalTheatresSorted"
                                    :key="th.id"
                                    @click="ouvrirModalMilitairesTheatre('tous', th)"
                                    @mouseenter="hoveredTheatre = th.code"
                                    @mouseleave="hoveredTheatre = null"
                                    class="p-2.5 rounded-xl border transition-all cursor-pointer hover:shadow-xs group flex items-center justify-between gap-2.5"
                                    :class="selTheatre === th.code || hoveredTheatre === th.code ? 'border-sky-500 bg-sky-50/80 ring-1 ring-sky-400' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/80'"
                                >
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span
                                            class="w-3 h-3 rounded-full shrink-0 border border-black/20"
                                            :style="{ backgroundColor: th.couleur }"
                                        ></span>
                                        <div class="min-w-0">
                                            <div class="font-black text-xs font-mono text-slate-900 group-hover:text-sky-950 flex items-center gap-1.5">
                                                <span>{{ th.code }}</span>
                                                <span v-if="th.pc_lieu" class="text-[10px] font-normal text-slate-400">· PC {{ th.pc_lieu }}</span>
                                            </div>
                                            <div class="text-[11px] text-slate-600 truncate">Détachement de {{ th.nomCourt }}</div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-slate-100 text-slate-900 border border-slate-200 tabular-nums">
                                            {{ fmt(th.effectif) }}
                                        </span>
                                        <i class="pi pi-chevron-right text-[10px] text-slate-400 group-hover:text-sky-600 transition-colors"></i>
                                    </div>
                                </div>
                            </div>

                            <p class="text-[10px] text-slate-400 mt-auto pt-1 border-t border-slate-100 flex-none truncate">
                                Cliquez sur un théâtre pour consulter la liste nominale des militaires déployés.
                            </p>
                        </template>

                        <!-- 2. VUE DÉTAILLÉE DU THÉÂTRE SÉLECTIONNÉ -->
                        <template v-else-if="selTheatreObj">
                            <!-- En-tête avec bouton retour et bouton modifier GPS + export Excel -->
                            <div class="flex items-center justify-between gap-1.5 pb-1.5 border-b border-slate-200 flex-none">
                                <button
                                    type="button"
                                    @click="selTheatre = null"
                                    class="inline-flex items-center gap-1 text-xs text-sky-800 hover:text-sky-950 font-bold hover:underline cursor-pointer"
                                >
                                    <i class="pi pi-arrow-left text-[10px]"></i>
                                    <span>Tous les théâtres</span>
                                </button>
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        @click="exportTheatresExcel(selTheatreObj.code)"
                                        :disabled="exportingTheatresExcel"
                                        class="inline-flex items-center gap-1 text-[10px] font-bold text-white bg-emerald-700 hover:bg-emerald-800 active:bg-emerald-900 px-2 py-0.5 rounded transition cursor-pointer disabled:opacity-50"
                                        :title="`Exporter la situation opérationnelle de ${selTheatreObj.code} en Excel`"
                                    >
                                        <i :class="exportingTheatresExcel ? 'pi pi-spin pi-spinner text-[9px]' : 'pi pi-file-excel text-[9px]'"></i>
                                        <span>Excel</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="ouvrirEditTheatreCoordsModal(selTheatreObj)"
                                        class="inline-flex items-center gap-1 text-[10px] font-semibold text-sky-800 bg-sky-50 hover:bg-sky-100 border border-sky-200 px-2 py-0.5 rounded transition cursor-pointer"
                                        title="Modifier l'emplacement GPS du PC sur la carte"
                                    >
                                        <i class="pi pi-map-marker text-[9px]"></i>
                                        <span>GPS</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Fiche récapitulative du Théâtre -->
                            <div class="p-2.5 rounded-lg border border-slate-200 bg-slate-50/70 my-1.5 flex-none">
                                <div class="flex items-center justify-between gap-1 mb-1">
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="w-3.5 h-3.5 rounded-full shrink-0 border border-black/20"
                                            :style="{ backgroundColor: selTheatreObj.couleur }"
                                        ></span>
                                        <span class="font-mono font-bold text-xs text-slate-900">{{ selTheatreObj.code }}</span>
                                    </div>
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                        :class="selTheatreObj.statut === 'actif' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-slate-100 text-slate-700 border border-slate-300'"
                                    >
                                        {{ selTheatreObj.statut }}
                                    </span>
                                </div>
                                <div class="text-xs font-bold text-slate-900 leading-snug">{{ selTheatreObj.nom }}</div>
                                <div class="text-[11px] text-slate-600 mt-1 flex items-center justify-between gap-1">
                                    <span class="flex items-center gap-1 truncate">
                                        <i class="pi pi-compass text-sky-600 text-[10px]"></i>
                                        <span>PC : <strong>{{ selTheatreObj.pc_lieu || 'Non renseigné' }}</strong></span>
                                    </span>
                                    <span v-if="selTheatreObj.lat && selTheatreObj.lon" class="text-slate-400 font-mono text-[10px] shrink-0">
                                        {{ Number(selTheatreObj.lat).toFixed(3) }}, {{ Number(selTheatreObj.lon).toFixed(3) }}
                                    </span>
                                </div>
                            </div>

                            <!-- 1. BLOC SYNTHÉTIQUE CLIQUABLE D'EFFECTIF GLOBAL -->
                            <div
                                @click="ouvrirModalMilitairesTheatre('tous')"
                                class="p-3 bg-linear-to-br from-sky-50 via-slate-50 to-sky-100/70 hover:from-sky-100 hover:to-slate-100 border border-sky-200 hover:border-sky-300 rounded-xl cursor-pointer transition shadow-xs group mb-2.5 flex-none"
                                title="Cliquer pour afficher la liste nominative ordonnée par grade"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                                        <i class="pi pi-users text-sky-700"></i>
                                        <span>Effectif Total Déployé</span>
                                    </span>
                                    <span class="text-[9.5px] font-bold text-sky-800 bg-white/90 group-hover:bg-white px-2 py-0.5 rounded-full border border-sky-200 flex items-center gap-1 shadow-2xs">
                                        <span>Voir la liste</span>
                                        <i class="pi pi-arrow-up-right text-[8px]"></i>
                                    </span>
                                </div>
                                <div class="flex items-baseline gap-2">
                                    <div class="text-3xl font-black text-slate-900 tabular-nums">
                                        {{ fmt((selTheatreObj.effectif || 0) + (selTheatreObj.effectif_appui || 0)) }}
                                    </div>
                                    <div class="text-xs font-semibold text-slate-600">
                                        combattants au front
                                    </div>
                                </div>
                                <div class="text-[10.5px] text-slate-600 mt-1 flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-sky-900 bg-sky-100/70 px-1.5 py-0.2 rounded border border-sky-200">
                                        {{ fmt(selTheatreObj.effectif) }} Gendarmes
                                    </span>
                                    <span v-if="selTheatreObj.effectif_appui > 0" class="font-bold text-amber-800 bg-amber-100/70 px-1.5 py-0.2 rounded border border-amber-200">
                                        +{{ fmt(selTheatreObj.effectif_appui) }} Appuis FAMa
                                    </span>
                                </div>
                            </div>

                            <!-- SECTION UNITÉS D'APPUI INTERARMÉES (AA, AT, DTTIA, GÉNIE, ETC.) -->
                            <div class="mb-2.5 flex-none p-2.5 rounded-lg border border-amber-200/80 bg-amber-50/50">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-[10px] font-bold text-amber-900 uppercase tracking-wide flex items-center gap-1.5">
                                        <i class="pi pi-shield text-amber-700 text-xs"></i>
                                        <span>Appuis FAMa ({{ selTheatreObj.unites_appui?.length || 0 }})</span>
                                    </span>
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-black text-amber-800 bg-amber-100/80 px-2 py-0.5 rounded-full border border-amber-300 tabular-nums">
                                            +{{ selTheatreObj.effectif_appui || 0 }}
                                        </span>
                                        <button
                                            type="button"
                                            @click="openAddAppuiModal"
                                            class="text-[10.5px] font-bold text-sky-800 bg-white hover:bg-sky-50 px-2 py-0.5 rounded border border-sky-300 flex items-center gap-1 cursor-pointer transition shadow-2xs"
                                            title="Ajouter une unité d'appui à ce théâtre"
                                        >
                                            <i class="pi pi-plus text-[9px] text-sky-700"></i>
                                            <span>Ajouter</span>
                                        </button>
                                    </div>
                                </div>

                                <div v-if="selTheatreObj.unites_appui && selTheatreObj.unites_appui.length > 0" class="grid grid-cols-2 gap-1.5">
                                    <div
                                        v-for="ua in selTheatreObj.unites_appui"
                                        :key="ua.id"
                                        class="p-1.5 rounded bg-white border border-amber-200 shadow-2xs flex items-center justify-between gap-1 group hover:border-amber-400 transition"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1">
                                                <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: ua.couleur || '#0284c7' }"></span>
                                                <span class="font-mono font-black text-[11px] text-slate-900">{{ ua.code }}</span>
                                            </div>
                                            <div class="text-[9.5px] text-slate-500 truncate" :title="ua.nom">{{ ua.nom }}</div>
                                        </div>
                                        <div class="flex items-center gap-1 shrink-0">
                                            <span class="font-black text-xs px-1.5 py-0.5 rounded text-amber-900 bg-amber-50 border border-amber-200 tabular-nums">
                                                {{ ua.effectif }}
                                            </span>
                                            <button
                                                type="button"
                                                @click.stop="confirmDeleteAppui(ua)"
                                                class="p-1 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded transition cursor-pointer"
                                                title="Supprimer cette unité d'appui"
                                            >
                                                <i class="pi pi-trash text-[11px]"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-[10px] text-slate-500 italic text-center py-1">
                                    Aucune unité d'appui FAMa rattachée à ce théâtre.
                                </div>
                            </div>

                            <!-- 2. VENTILATION HIÉRARCHIQUE CLIQUABLE (OFFICIERS / SOUS-OFF / MDR / BLESSÉS) -->
                            <div class="grid grid-cols-2 gap-1.5 mb-2.5 flex-none">
                                <!-- Officiers -->
                                <button
                                    type="button"
                                    @click="ouvrirModalMilitairesTheatre('officiers')"
                                    class="p-2 rounded-lg border border-slate-200 bg-white hover:border-sky-300 hover:bg-sky-50/50 transition cursor-pointer text-left flex items-center justify-between group shadow-2xs"
                                >
                                    <div>
                                        <div class="text-[9.5px] font-bold text-slate-500 uppercase tracking-wide group-hover:text-sky-800">Officiers</div>
                                        <div class="text-sm font-black text-slate-900 tabular-nums">
                                            {{ theatreStatsHierarchiques.officiers }}
                                        </div>
                                    </div>
                                    <div class="w-7 h-7 rounded-md bg-sky-100 text-sky-800 flex items-center justify-center text-xs">
                                        <i class="pi pi-star-fill text-[11px]"></i>
                                    </div>
                                </button>

                                <!-- Sous-officiers -->
                                <button
                                    type="button"
                                    @click="ouvrirModalMilitairesTheatre('sous_officiers')"
                                    class="p-2 rounded-lg border border-slate-200 bg-white hover:border-emerald-300 hover:bg-emerald-50/50 transition cursor-pointer text-left flex items-center justify-between group shadow-2xs"
                                >
                                    <div>
                                        <div class="text-[9.5px] font-bold text-slate-500 uppercase tracking-wide group-hover:text-emerald-800">Sous-Officiers</div>
                                        <div class="text-sm font-black text-slate-900 tabular-nums">
                                            {{ theatreStatsHierarchiques.sousOfficiers }}
                                        </div>
                                    </div>
                                    <div class="w-7 h-7 rounded-md bg-emerald-100 text-emerald-800 flex items-center justify-center text-xs">
                                        <i class="pi pi-shield text-[11px]"></i>
                                    </div>
                                </button>

                                <!-- Militaires du Rang -->
                                <button
                                    type="button"
                                    @click="ouvrirModalMilitairesTheatre('mdr')"
                                    class="p-2 rounded-lg border border-slate-200 bg-white hover:border-slate-400 hover:bg-slate-50 transition cursor-pointer text-left flex items-center justify-between group shadow-2xs"
                                >
                                    <div>
                                        <div class="text-[9.5px] font-bold text-slate-500 uppercase tracking-wide group-hover:text-slate-800">Militaires du Rang</div>
                                        <div class="text-sm font-black text-slate-900 tabular-nums">
                                            {{ theatreStatsHierarchiques.mdr }}
                                        </div>
                                    </div>
                                    <div class="w-7 h-7 rounded-md bg-slate-100 text-slate-700 flex items-center justify-center text-xs">
                                        <i class="pi pi-user text-[11px]"></i>
                                    </div>
                                </button>

                                <!-- Blessés de Guerre -->
                                <button
                                    type="button"
                                    @click="ouvrirModalMilitairesTheatre('blesses')"
                                    class="p-2 rounded-lg border transition cursor-pointer text-left flex items-center justify-between group shadow-2xs"
                                    :class="selTheatreObj.blesses > 0 ? 'border-rose-200 bg-rose-50/70 hover:bg-rose-100/70 hover:border-rose-300' : 'border-slate-200 bg-white hover:bg-slate-50'"
                                >
                                    <div>
                                        <div class="text-[9.5px] font-bold uppercase tracking-wide" :class="selTheatreObj.blesses > 0 ? 'text-rose-700' : 'text-slate-500'">Blessés Guerre</div>
                                        <div class="text-sm font-black tabular-nums" :class="selTheatreObj.blesses > 0 ? 'text-rose-800' : 'text-slate-900'">
                                            {{ selTheatreObj.blesses }}
                                        </div>
                                    </div>
                                    <div class="w-7 h-7 rounded-md flex items-center justify-center text-xs" :class="selTheatreObj.blesses > 0 ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600'">
                                        <i class="pi pi-heart-fill text-[11px]"></i>
                                    </div>
                                </button>
                            </div>

                            <!-- 3. ÉTAT OPÉRATIONNEL AU COMBAT -->
                            <div class="p-2 bg-slate-50 rounded-lg border border-slate-200 mb-2.5 flex items-center justify-between text-xs flex-none">
                                <span class="text-slate-600 text-[11px] font-medium flex items-center gap-1.5">
                                    <i class="pi pi-check-circle text-emerald-600 text-[11px]"></i>
                                    <span>Actifs au front & opérationnels :</span>
                                </span>
                                <span class="font-extrabold text-emerald-800 font-mono text-xs">
                                    {{ selTheatreObj.actifs }} / {{ selTheatreObj.effectif }}
                                </span>
                            </div>

                            <!-- 4. EMPRISE GÉOGRAPHIQUE -->
                            <div class="mb-2.5 flex-none">
                                <div class="text-[10px] font-bold text-slate-600 mb-1 flex items-center gap-1">
                                    <i class="pi pi-map text-[9px] text-sky-600"></i>
                                    <span>Emprise géographique ({{ selTheatreObj.regions_codes?.length || 0 }} régions) :</span>
                                </div>
                                <div class="flex flex-wrap gap-1 max-h-24 overflow-y-auto">
                                    <span
                                        v-for="rc in selTheatreObj.regions_codes"
                                        :key="rc"
                                        class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-sky-50 text-sky-800 border border-sky-200"
                                    >
                                        {{ rc }} ({{ regions.find(r => r.code === rc)?.nom.replace(/^(Région de |Région du |District de )/, '') || rc }})
                                    </span>
                                </div>
                            </div>

                            <!-- 5. ORDRE DE MISSION / DESCRIPTION -->
                            <div v-if="selTheatreObj.description" class="p-2 rounded-lg bg-white border border-slate-200 text-xs text-slate-600 mb-3 flex-none">
                                <div class="text-[9.5px] font-bold uppercase text-slate-400 mb-0.5">Ordre de mission / Emprise</div>
                                <p class="text-[11px] leading-relaxed text-slate-700 italic">
                                    « {{ selTheatreObj.description }} »
                                </p>
                            </div>

                            <!-- 6. GRAND BOUTON D'ACCÈS NOMINATIF HIÉRARCHISÉ -->
                            <button
                                type="button"
                                @click="ouvrirModalMilitairesTheatre('tous')"
                                class="w-full mt-auto py-2.5 px-3 bg-gradient-to-r from-sky-800 to-sky-700 hover:from-sky-900 hover:to-sky-800 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-2 shadow-xs transition cursor-pointer flex-none"
                            >
                                <i class="pi pi-list text-xs"></i>
                                <span>Revue nominale par grade ({{ selTheatreObj.effectif }})</span>
                            </button>
                        </template>
                    </template>

                    <!-- VOLET EN MODE MAILLAGE TERRITORIAL (STANDARD) -->
                    <template v-else>
                    <!-- Entête du panneau -->
                    <div class="flex items-center justify-between gap-2 flex-none">
                        <div class="min-w-0">
                            <h2 class="text-sm font-bold text-slate-900 truncate">
                                {{ sel ? selRegion.nom : 'Vue Nationale (Mali)' }}
                            </h2>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[11px] text-slate-500 truncate">
                                    {{ sel ? (selRegion.chef_lieu ? 'Chef-lieu : ' + selRegion.chef_lieu : 'Région') : 'Tout le Mali' }}
                                </span>
                                <button
                                    type="button"
                                    @click="exportCarte"
                                    :disabled="exportingCarte"
                                    class="inline-flex items-center gap-1 text-[10px] font-semibold text-white bg-emerald-600 hover:bg-emerald-500 border border-emerald-500 px-1.5 py-0.5 rounded shadow-xs whitespace-nowrap transition-colors cursor-pointer disabled:opacity-50"
                                    :title="sel ? 'Exporter les statistiques de cette région en Excel' : 'Exporter les statistiques de la carte en Excel'"
                                >
                                    <i :class="exportingCarte ? 'pi pi-spin pi-spinner text-[9px]' : 'pi pi-file-excel text-[9px]'"></i>
                                    <span>{{ exportingCarte ? '...' : (sel ? 'Exporter' : 'Exporter') }}</span>
                                </button>
                            </div>
                        </div>
                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200 flex-none">
                            {{ sel ? selRegion.unites.length + ' unités' : (regions.length === 20 ? '19 Régions + 1 District' : regions.length + ' régions') }}
                        </span>
                    </div>

                    <!-- Grille métriques compacte claire -->
                    <div class="grid grid-cols-3 gap-1 my-1 p-1 border border-slate-200/70 flex-none bg-slate-50/90 rounded text-center">
                        <div v-for="d in detail" :key="d.label" class="min-w-0 px-0.5">
                            <div class="text-[9px] text-slate-500 truncate leading-tight">{{ d.label }}</div>
                            <div class="text-xs font-bold text-slate-900 tabular-nums truncate">
                                {{ d.value }}
                            </div>
                        </div>
                    </div>

                    <!-- Onglets de bascule : Liste vs Détail Effectif -->
                    <div class="flex items-center bg-slate-100 p-0.5 rounded-lg text-xs font-medium my-1 flex-none">
                        <button
                            type="button"
                            @click="sidebarTab = 'liste'"
                            class="flex-1 py-0.5 px-2 rounded-md transition-all flex items-center justify-center gap-1.5 cursor-pointer text-[11px]"
                            :class="sidebarTab === 'liste' ? 'bg-white text-sky-900 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        >
                            <i class="pi pi-list text-[10px]"></i>
                            <span>{{ sel ? 'Unités (' + selRegion.unites.length + ')' : (regions.length === 20 ? '19 Régions + 1 District (20)' : 'Régions (' + regions.length + ')') }}</span>
                        </button>
                        <button
                            type="button"
                            @click="sidebarTab = 'effectif'"
                            class="flex-1 py-0.5 px-2 rounded-md transition-all flex items-center justify-center gap-1.5 cursor-pointer text-[11px]"
                            :class="sidebarTab === 'effectif' ? 'bg-white text-sky-900 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        >
                            <i class="pi pi-chart-pie text-[10px]"></i>
                            <span>Détail Effectif</span>
                        </button>
                    </div>

                    <!-- VUE 1 : LISTE D'UNITÉS / RÉGIONS (DÉFAUT - PLEINE HAUTEUR FLUIDE & SCROLLABLE) -->
                    <template v-if="sidebarTab === 'liste'">
                        <!-- Titre de liste + Barre de recherche instantanée -->
                        <div class="flex items-center justify-between gap-1.5 mb-1 flex-none">
                            <h3 class="text-xs font-bold text-slate-900 truncate">
                                {{ sel ? 'Unités de la région' : 'Classement : ' + METRICS[metric].label }}
                            </h3>
                            <div class="relative w-36 sm:w-40">
                                <input
                                    v-model="searchInput"
                                    type="text"
                                    :placeholder="sel ? 'Filtrer brigades...' : 'Filtrer régions...'"
                                    class="w-full text-[11px] py-0.5 pl-5 pr-5 rounded border border-slate-200 bg-white placeholder-slate-400 focus:outline-none focus:border-sky-500"
                                />
                                <i class="pi pi-search absolute left-1.5 top-1.5 text-[9px] text-slate-400"></i>
                                <button
                                    v-if="searchInput"
                                    @click="searchInput = ''; searchQuery = ''"
                                    class="absolute right-1 top-1 text-slate-400 hover:text-slate-600 text-[10px]"
                                    title="Effacer"
                                >
                                    <i class="pi pi-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- LISTE DÉROULANTE INTERNE (HAUTEUR FIXE ALIGNÉE SUR LA CARTE - SCROLL INTERNE) -->
                        <div class="flex-1 min-h-0 max-h-[380px] lg:max-h-[410px] xl:max-h-[440px] overflow-y-auto pr-1 custom-scrollbar overscroll-contain">
                            <!-- Fiche résumé de l'unité sélectionnée avec bouton de modification directe -->
                            <div
                                v-if="sel && selectedUnitObj"
                                class="p-2 mb-2 rounded-lg bg-sky-50/90 border border-sky-200 text-xs flex items-center justify-between gap-2 shadow-2xs"
                            >
                                <div class="min-w-0">
                                    <div class="font-bold text-sky-950 truncate flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: parcelFill(selectedUnitObj) }"></span>
                                        <span class="truncate">{{ selectedUnitObj.nom }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 flex items-center gap-1.5 mt-0.5">
                                        <span v-if="selectedUnitObj.ville" class="font-medium text-slate-700">📍 {{ selectedUnitObj.ville }}</span>
                                        <span v-if="selectedUnitObj.lat && selectedUnitObj.lon" class="font-mono text-slate-500">
                                            ({{ Number(selectedUnitObj.lat).toFixed(4) }}, {{ Number(selectedUnitObj.lon).toFixed(4) }})
                                        </span>
                                        <span v-else class="text-amber-700 font-medium">⚠️ Sans coordonnées GPS</span>
                                        <span class="text-slate-300">•</span>
                                        <span class="font-bold text-sky-800">{{ selectedUnitObj.effectif }} gendarme(s)</span>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="openEditCoords(selectedUnitObj)"
                                    class="px-2 py-1 bg-sky-600 hover:bg-sky-700 text-white rounded text-[11px] font-bold flex items-center gap-1 cursor-pointer shrink-0 shadow-xs transition-colors"
                                    title="Modifier l'implantation géographique et les coordonnées GPS de cette unité"
                                >
                                    <i class="pi pi-pencil text-[9px]"></i>
                                    <span>Modifier GPS</span>
                                </button>
                            </div>

                            <ul v-if="lignesFiltrees.length > 0" class="rank">
                                <li v-for="row in lignesFiltrees" :key="row.key" class="flex items-center gap-1 group/row">
                                    <button
                                        :class="{ sel: row.active }"
                                        @click="row.click"
                                        class="flex-1 min-w-0"
                                        :title="sel ? row.nom : 'Cliquer pour isoler ' + row.nom"
                                    >
                                        <span class="n">
                                            <i
                                                v-if="sel"
                                                class="dot"
                                                :style="{ background: row.op ? '#10b981' : '#f59e0b' }"
                                            ></i>
                                            <i
                                                v-else
                                                class="dot"
                                                :style="{ background: fillFor(row.rawRegion) }"
                                            ></i>
                                            <span class="truncate text-slate-700 font-medium">{{ row.nom }}</span>
                                            <small v-if="row.horsCarte" class="ml-1 text-amber-700 text-[9px]" title="Pas de coordonnées GPS">
                                                (sans GPS)
                                            </small>
                                        </span>
                                        <span class="t">
                                            <i :style="{ width: Math.max(3, row.pct) + '%', background: sel ? '#0284c7' : '#1d4f91' }"></i>
                                        </span>
                                        <span class="v text-slate-900 font-bold">
                                            {{ fmt(row.value) }}{{ sel ? '' : METRICS[metric].unit }}
                                        </span>
                                    </button>
                                    <button
                                        v-if="sel && row.rawUnit"
                                        type="button"
                                        @click.stop="openEditCoords(row.rawUnit)"
                                        class="p-1 rounded text-slate-400 hover:text-sky-700 hover:bg-sky-50 transition cursor-pointer flex-none"
                                        title="Modifier les coordonnées géographiques (GPS) de cette unité"
                                    >
                                        <i class="pi pi-map-marker text-[11px]"></i>
                                    </button>
                                </li>
                            </ul>
                            <div v-else class="py-6 text-center text-xs text-slate-400">
                                Aucun élément pour « {{ searchInput }} »
                            </div>
                        </div>
                    </template>

                    <!-- VUE 2 : DÉTAIL DE L'EFFECTIF (AFFICHÉ QUAND ON CLIQUE SUR L'ONGLET) -->
                    <template v-else>
                        <div class="flex-1 min-h-0 max-h-[380px] lg:max-h-[410px] xl:max-h-[440px] overflow-y-auto pr-1 space-y-2 custom-scrollbar text-xs overscroll-contain">
                            <!-- En activité -->
                            <div class="p-2.5 rounded-lg border border-slate-200 bg-slate-50/70 space-y-2">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-700 flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        <span>En activité ({{ fmt(sel ? selRegion.effectif : (tot(r => r.effectif) + (stats.non_rattaches || 0))) }})</span>
                                    </span>
                                    <span class="text-[9px] text-slate-400 font-normal">{{ sel ? 'Somme = Effectif' : `${fmt(tot(r => r.effectif))} en région • ${fmt(stats.non_rattaches || 0)} en attente` }}</span>
                                </div>
                                <div class="grid grid-cols-2 gap-1.5 text-xs">
                                    <button
                                        type="button"
                                        @click="ouvrirModalStatut('actifs')"
                                        class="flex items-center justify-between p-2 rounded bg-white border border-slate-200/80 hover:border-sky-500 hover:bg-sky-50/50 hover:shadow-2xs transition-all text-left cursor-pointer group"
                                        title="Voir la liste nominative des militaires actifs au poste et exporter en Excel"
                                    >
                                        <span class="text-slate-600 group-hover:text-sky-900 flex items-center gap-1">
                                            <span>Actifs au poste</span>
                                            <i class="pi pi-arrow-up-right text-[9px] text-sky-600 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                        </span>
                                        <strong class="text-slate-900 group-hover:text-sky-900 tabular-nums font-bold">{{ fmt(valStat('actifs')) }}</strong>
                                    </button>
                                    <button
                                        type="button"
                                        @click="ouvrirModalStatut('detaches')"
                                        class="flex items-center justify-between p-2 rounded bg-white border border-slate-200/80 hover:border-sky-500 hover:bg-sky-50/50 hover:shadow-2xs transition-all text-left cursor-pointer group"
                                        title="Voir la liste nominative des militaires détachés et exporter en Excel"
                                    >
                                        <span class="text-slate-600 group-hover:text-sky-900 flex items-center gap-1">
                                            <span>Détachés</span>
                                            <i class="pi pi-arrow-up-right text-[9px] text-sky-600 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                        </span>
                                        <strong class="text-slate-900 group-hover:text-sky-900 tabular-nums font-bold">{{ fmt(valStat('detaches')) }}</strong>
                                    </button>
                                    <button
                                        type="button"
                                        @click="ouvrirModalStatut('missions')"
                                        class="flex items-center justify-between p-2 rounded bg-white border border-slate-200/80 hover:border-sky-500 hover:bg-sky-50/50 hover:shadow-2xs transition-all text-left cursor-pointer group"
                                        title="Voir la liste nominative des militaires en mission et exporter en Excel"
                                    >
                                        <span class="text-slate-600 group-hover:text-sky-900 flex items-center gap-1">
                                            <span>En mission</span>
                                            <i class="pi pi-arrow-up-right text-[9px] text-sky-600 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                        </span>
                                        <strong class="text-slate-900 group-hover:text-sky-900 tabular-nums font-bold">{{ fmt(valStat('missions')) }}</strong>
                                    </button>
                                    <button
                                        type="button"
                                        @click="ouvrirModalStatut('stages')"
                                        class="flex items-center justify-between p-2 rounded bg-white border border-slate-200/80 hover:border-sky-500 hover:bg-sky-50/50 hover:shadow-2xs transition-all text-left cursor-pointer group"
                                        title="Voir la liste nominative des militaires en stage et exporter en Excel"
                                    >
                                        <span class="text-slate-600 group-hover:text-sky-900 flex items-center gap-1">
                                            <span>En stage</span>
                                            <i class="pi pi-arrow-up-right text-[9px] text-sky-600 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                        </span>
                                        <strong class="text-slate-900 group-hover:text-sky-900 tabular-nums font-bold">{{ fmt(valStat('stages')) }}</strong>
                                    </button>
                                </div>
                            </div>

                            <!-- Hors effectif -->
                            <div class="p-2.5 rounded-lg border border-rose-200/80 bg-rose-50/40 space-y-2">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-rose-800 flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                        <span>Hors effectif (à part)</span>
                                    </span>
                                    <span class="text-[9px] text-rose-500 font-normal">Non comptés dans total</span>
                                </div>
                                <div class="grid grid-cols-2 gap-1.5 text-xs">
                                    <button
                                        type="button"
                                        @click="ouvrirModalStatut('radies')"
                                        class="flex items-center justify-between p-2 rounded bg-white border border-slate-200/80 hover:border-rose-400 hover:bg-rose-50/50 hover:shadow-2xs transition-all text-left cursor-pointer group"
                                        title="Voir la liste nominative des radiés et exporter en Excel"
                                    >
                                        <span class="text-slate-600 group-hover:text-rose-900 flex items-center gap-1">
                                            <span>Radiés</span>
                                            <i class="pi pi-arrow-up-right text-[9px] text-rose-600 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                        </span>
                                        <strong class="text-slate-900 group-hover:text-rose-900 tabular-nums font-bold">{{ fmt(valStat('radies')) }}</strong>
                                    </button>
                                    <button
                                        type="button"
                                        @click="ouvrirModalStatut('retraites')"
                                        class="flex items-center justify-between p-2 rounded bg-white border border-slate-200/80 hover:border-purple-400 hover:bg-purple-50/50 hover:shadow-2xs transition-all text-left cursor-pointer group"
                                        title="Voir la liste nominative des départs à la retraite et exporter en Excel"
                                    >
                                        <span class="text-slate-600 group-hover:text-purple-900 flex items-center gap-1">
                                            <span>Retraites {{ props.stats.annee ?? new Date().getFullYear() }}</span>
                                            <i class="pi pi-arrow-up-right text-[9px] text-purple-600 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                        </span>
                                        <strong class="text-slate-900 group-hover:text-purple-900 tabular-nums font-bold">{{ fmt(valStat('retraites')) }}</strong>
                                    </button>
                                    <button
                                        type="button"
                                        @click="ouvrirModalStatut('deserteurs')"
                                        class="flex items-center justify-between p-2 rounded bg-white border border-slate-200/80 hover:border-amber-400 hover:bg-amber-50/50 hover:shadow-2xs transition-all text-left cursor-pointer group"
                                        title="Voir la liste nominative des déserteurs et exporter en Excel"
                                    >
                                        <span class="text-slate-600 group-hover:text-amber-900 flex items-center gap-1">
                                            <span>Déserteurs</span>
                                            <i class="pi pi-arrow-up-right text-[9px] text-amber-600 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                        </span>
                                        <strong class="text-slate-900 group-hover:text-amber-900 tabular-nums font-bold">{{ fmt(valStat('deserteurs')) }}</strong>
                                    </button>
                                    <button
                                        type="button"
                                        @click="ouvrirModalStatut('decedes')"
                                        class="flex items-center justify-between p-2 rounded bg-white border border-slate-200/80 hover:border-rose-400 hover:bg-rose-50/50 hover:shadow-2xs transition-all text-left cursor-pointer group"
                                        title="Voir la liste nominative des décédés et exporter en Excel"
                                    >
                                        <span class="text-slate-600 group-hover:text-rose-900 flex items-center gap-1">
                                            <span>Décédés {{ props.stats.annee ?? new Date().getFullYear() }}</span>
                                            <i class="pi pi-arrow-up-right text-[9px] text-rose-600 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                        </span>
                                        <strong class="text-slate-900 group-hover:text-rose-900 tabular-nums font-bold">{{ fmt(valStat('decedes')) }}</strong>
                                    </button>
                                </div>
                            </div>

                            <!-- Astuce visuelle d'interactivité -->
                            <div class="px-2.5 py-1.5 rounded-lg bg-sky-50/80 border border-sky-200/70 text-[10.5px] text-sky-900 flex items-center gap-2">
                                <i class="pi pi-info-circle text-sky-600 text-xs shrink-0"></i>
                                <span>Cliquez sur un statut pour afficher la liste nominative et l'exporter en Excel.</span>
                            </div>
                        </div>
                    </template>

                    <!-- Guide d'interaction en bas (aligné au bas du panneau) -->
                    <p class="text-[10px] text-slate-400 mt-auto pt-1 border-t border-slate-100 flex-none truncate">
                        {{ sel
                            ? 'Cliquez sur une unité pour la cibler sur la carte.'
                            : 'Cliquez sur une région pour zoomer et afficher ses unités.' }}
                    </p>
                    </template>
                </div>
            </div>
        </div>

        <!-- Modal: Recrues en attente d'affectation (Sans région / unité) -->
        <Dialog
            v-model:visible="showNonRattachesModal"
            modal
            :header="`Recrues en attente d'affectation (${props.stats.non_rattaches} sans région/unité)`"
            :style="{ width: '920px', maxWidth: '96vw' }"
            class="p-fluid"
        >
            <div class="space-y-3">
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-900 flex items-start gap-2.5">
                    <i class="pi pi-info-circle text-amber-600 text-base mt-0.5 shrink-0"></i>
                    <div>
                        <div class="font-bold text-amber-950">
                            {{ props.stats.non_rattaches }} recrues actives sans affectation territoriale
                        </div>
                        <p class="text-amber-800 mt-0.5">
                            Ces soldats sont actifs dans l'effectif mais n'ont pas encore été affectés à une région militaire ni à une unité. Cliquez sur <strong>« Affecter »</strong> pour leur assigner une région et une unité.
                        </p>
                    </div>
                </div>

                <!-- Barre de recherche rapide + action globale -->
                <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                    <div class="relative flex-1 min-w-[220px]">
                        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        <input
                            v-model="searchRecrues"
                            type="text"
                            placeholder="Rechercher par nom, prénom, matricule, MLE GRM..."
                            class="w-full pl-8 pr-3 py-1.5 text-xs bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 text-slate-800"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-slate-500">
                            {{ nonRattachesFiltres.length }} sur {{ props.stats.non_rattaches }}
                        </span>
                        <Link
                            :href="route('militaires.index', { non_rattaches: 1 })"
                            class="px-2.5 py-1.5 bg-sky-900 hover:bg-sky-800 text-white rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-xs transition-colors"
                        >
                            <i class="pi pi-external-link text-xs"></i>
                            <span>Ouvrir dans l'Annuaire</span>
                        </Link>
                    </div>
                </div>

                <!-- Liste des recrues (Tableau responsive) -->
                <div class="max-h-[58vh] overflow-y-auto border border-slate-200 rounded-lg custom-scrollbar">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-slate-100 text-slate-700 sticky top-0 z-10 border-b border-slate-200">
                            <tr>
                                <th class="p-2.5 font-bold">Matricule / MLE</th>
                                <th class="p-2.5 font-bold">Nom & Prénom</th>
                                <th class="p-2.5 font-bold">Grade</th>
                                <th class="p-2.5 font-bold">Statut / Position</th>
                                <th class="p-2.5 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="m in nonRattachesFiltres"
                                :key="m.id"
                                class="hover:bg-sky-50/50 transition-colors"
                            >
                                <td class="p-2.5 font-mono text-[11px] font-semibold text-slate-800">
                                    <div>FAMA: {{ m.matricule || 'N/A' }}</div>
                                    <div class="text-[10px] text-slate-500">GRM: {{ m.mle_grm || 'N/A' }}</div>
                                </td>
                                <td class="p-2.5 font-bold text-slate-900">
                                    {{ m.nom }} {{ m.prenom }}
                                </td>
                                <td class="p-2.5">
                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 font-semibold text-[11px] border border-slate-200">
                                        {{ m.grade_actuel || 'Soldat 2' }}
                                    </span>
                                </td>
                                <td class="p-2.5">
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-semibold text-[10px]">
                                        {{ m.position_actuelle || m.statut || 'En attente' }}
                                    </span>
                                </td>
                                <td class="p-2.5 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <Link
                                            :href="route('militaires.edit', m.id)"
                                            class="px-2.5 py-1 bg-sky-800 hover:bg-sky-900 text-white rounded font-bold text-[11px] flex items-center gap-1 shadow-xs transition-colors"
                                            title="Affecter une région et une unité à cette recrue"
                                        >
                                            <i class="pi pi-pencil text-[10px]"></i>
                                            <span>Affecter</span>
                                        </Link>
                                        <Link
                                            :href="route('militaires.show', m.id)"
                                            class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 rounded font-semibold text-[11px] flex items-center gap-1 transition-colors"
                                            title="Voir la fiche détaillée"
                                        >
                                            <i class="pi pi-eye text-[10px]"></i>
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="nonRattachesFiltres.length === 0">
                                <td colspan="6" class="p-6 text-center text-slate-500">
                                    Aucune recrue trouvée pour cette recherche.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-between w-full pt-2 border-t border-slate-200">
                    <span class="text-xs text-slate-500">
                        Total recrues en attente : <strong>{{ props.stats.non_rattaches }}</strong>
                    </span>
                    <button
                        type="button"
                        @click="showNonRattachesModal = false"
                        class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-lg text-xs font-semibold cursor-pointer"
                    >
                        Fermer
                    </button>
                </div>
            </template>
        </Dialog>

        <!-- Modal: Détail des Militaires par Statut & Export Excel -->
        <Dialog
            v-model:visible="showStatutModal"
            modal
            :header="`${statutModalTitre} (${statutModalTotal})`"
            :style="{ width: '1050px', maxWidth: '96vw' }"
            class="p-fluid"
        >
            <div class="space-y-3">
                <!-- En-tête info avec badge périmètre & bouton Export Excel -->
                <div class="bg-sky-50 border border-sky-200 rounded-lg p-3 text-xs text-sky-950 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full bg-sky-600 text-white flex items-center justify-center font-bold text-sm shadow-xs shrink-0">
                            <i class="pi pi-users text-sm"></i>
                        </div>
                        <div>
                            <div class="font-bold text-sky-900 text-sm flex items-center gap-2">
                                <span>{{ statutModalTitre }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-200/80 text-sky-800">
                                    {{ statutModalPerimetre }}
                                </span>
                            </div>
                            <p class="text-sky-700 text-[11px] mt-0.5">
                                Liste nominative détaillée des personnels enregistrés sous cette catégorie.
                            </p>
                        </div>
                    </div>

                    <!-- Bouton Exporter en Excel -->
                    <button
                        type="button"
                        @click="exporterStatutExcel"
                        :disabled="loadingStatutModal || statutModalTotal === 0"
                        class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white rounded-lg text-xs font-bold flex items-center gap-1.5 shadow-sm transition-colors cursor-pointer"
                        title="Télécharger cette liste au format Excel (.xlsx)"
                    >
                        <i class="pi pi-file-excel text-sm"></i>
                        <span>Exporter en Excel (.xlsx)</span>
                    </button>
                </div>

                <!-- Avertissement si affichage tronqué (> 200 lignes pour préserver les performances du navigateur) -->
                <div v-if="statutModalIsLimited" class="bg-amber-50 border border-amber-200 rounded-lg p-2.5 text-xs text-amber-950 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-info-circle text-amber-600 text-sm shrink-0"></i>
                        <span>Affichage limité aux <strong>200</strong> premiers personnels pour préserver la fluidité (total : <strong>{{ statutModalTotal }}</strong>).</span>
                    </div>
                    <button
                        type="button"
                        @click="exporterStatutExcel"
                        class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-bold text-[11px] shrink-0 cursor-pointer flex items-center gap-1 shadow-2xs transition-colors"
                    >
                        <i class="pi pi-download text-[10px]"></i>
                        <span>Télécharger les {{ statutModalTotal }} en Excel</span>
                    </button>
                </div>

                <!-- Barre de recherche rapide -->
                <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                    <div class="relative flex-1 min-w-[240px]">
                        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        <input
                            v-model="searchStatutModal"
                            type="text"
                            placeholder="Filtrer par nom, prénom, matricule, grade, unité, région..."
                            class="w-full pl-8 pr-3 py-1.5 text-xs bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 text-slate-800"
                        />
                    </div>
                    <div class="text-xs font-semibold text-slate-500">
                        Affichage : <strong class="text-slate-800">{{ statutMilitairesFiltres.length }}</strong> sur {{ statutModalMilitaires.length }}
                    </div>
                </div>

                <!-- Indicateur de chargement -->
                <div v-if="loadingStatutModal" class="py-16 text-center text-slate-500 space-y-2">
                    <i class="pi pi-spin pi-spinner text-3xl text-sky-600"></i>
                    <p class="text-xs font-medium">Chargement des données en cours...</p>
                </div>

                <!-- Tableau nominatif responsive -->
                <div v-else class="max-h-[58vh] overflow-y-auto border border-slate-200 rounded-lg custom-scrollbar">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-slate-100 text-slate-700 sticky top-0 z-10 border-b border-slate-200">
                            <tr>
                                <th class="p-2.5 font-bold">N°</th>
                                <th class="p-2.5 font-bold">Matricule / MLE</th>
                                <th class="p-2.5 font-bold">Nom & Prénom</th>
                                <th class="p-2.5 font-bold">Grade</th>
                                <th class="p-2.5 font-bold">Région & Unité</th>
                                <th class="p-2.5 font-bold">Statut / Position</th>
                                <th v-if="statutModalType === 'retraites'" class="p-2.5 font-bold">Date retraite</th>
                                <th class="p-2.5 font-bold text-right">Fiche</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="(m, idx) in statutMilitairesFiltres"
                                :key="m.id"
                                class="hover:bg-sky-50/50 transition-colors"
                            >
                                <td class="p-2.5 font-mono text-slate-400 text-[11px]">
                                    {{ idx + 1 }}
                                </td>
                                <td class="p-2.5 font-mono text-[11px] font-semibold text-slate-800 whitespace-nowrap">
                                    <div>FAMA: {{ m.matricule || 'N/A' }}</div>
                                    <div class="text-[10px] text-slate-500">GRM: {{ m.mle_grm || 'N/A' }}</div>
                                </td>
                                <td class="p-2.5 font-bold text-slate-900 whitespace-nowrap">
                                    {{ m.nom }} {{ m.prenom }}
                                </td>
                                <td class="p-2.5">
                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 font-semibold text-[11px] border border-slate-200 whitespace-nowrap">
                                        {{ m.grade || 'Soldat 2' }}
                                    </span>
                                </td>
                                <td class="p-2.5 text-slate-700">
                                    <div class="font-medium text-slate-800">{{ m.unite_nom || 'Sans unité' }}</div>
                                    <div class="text-[10px] text-slate-500">{{ m.region_nom || 'Non rattaché' }}</div>
                                </td>
                                <td class="p-2.5">
                                    <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-semibold text-[10px] border border-slate-200 whitespace-nowrap">
                                        {{ m.position || m.statut || 'En attente' }}
                                    </span>
                                </td>
                                <td v-if="statutModalType === 'retraites'" class="p-2.5 font-mono font-semibold text-rose-700 whitespace-nowrap">
                                    {{ m.date_retraite || '-' }}
                                </td>
                                <td class="p-2.5 text-right whitespace-nowrap">
                                    <Link
                                        :href="route('militaires.show', m.id)"
                                        class="px-2 py-1 bg-slate-100 hover:bg-sky-100 hover:text-sky-800 text-slate-700 border border-slate-300 rounded font-semibold text-[11px] inline-flex items-center gap-1 transition-colors"
                                        title="Voir la fiche détaillée du militaire"
                                    >
                                        <i class="pi pi-id-card text-[10px]"></i>
                                        <span>Fiche</span>
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="statutMilitairesFiltres.length === 0">
                                <td :colspan="statutModalType === 'retraites' ? 8 : 7" class="p-8 text-center text-slate-400 text-xs">
                                    <i class="pi pi-inbox text-2xl text-slate-300 mb-1 block"></i>
                                    Aucun militaire trouvé pour ce critère.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-between w-full pt-2 border-t border-slate-200">
                    <span class="text-xs text-slate-500">
                        Total effectif : <strong>{{ statutModalTotal }}</strong> militaire(s)
                    </span>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="exporterStatutExcel"
                            :disabled="statutModalTotal === 0"
                            class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg text-xs font-bold flex items-center gap-1 cursor-pointer"
                        >
                            <i class="pi pi-file-excel"></i>
                            <span>Télécharger Excel</span>
                        </button>
                        <button
                            type="button"
                            @click="showStatutModal = false"
                            class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-lg text-xs font-semibold cursor-pointer"
                        >
                            Fermer
                        </button>
                    </div>
                </div>
            </template>
        </Dialog>

        <!-- Modal: Modifier l'implantation géographique et coordonnées GPS d'une unité -->
        <Dialog
            v-model:visible="showEditCoordsModal"
            modal
            :header="`📍 Implantation géographique & Coordonnées GPS`"
            :style="{ width: '560px', maxWidth: '95vw' }"
            class="p-fluid"
        >
            <form @submit.prevent="saveCoords" class="space-y-3.5 pt-1">
                <!-- En-tête de l'unité éditée -->
                <div v-if="editingUnit" class="p-3 rounded-lg bg-sky-50/80 border border-sky-200 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-sky-800">Unité sélectionnée</div>
                        <div class="font-extrabold text-slate-900 text-sm truncate">{{ editingUnit.nom }}</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">
                            Région : <strong class="text-slate-800">{{ editingUnit.region_nom || selRegion?.nom || 'N/A' }}</strong>
                            <span v-if="editingUnit.effectif !== undefined" class="ml-1">• {{ editingUnit.effectif }} militaires</span>
                        </div>
                    </div>
                    <span
                        class="px-2 py-0.5 rounded text-[10px] font-bold whitespace-nowrap"
                        :class="editingUnit.operationnelle ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-amber-100 text-amber-800 border border-amber-300'"
                    >
                        {{ editingUnit.operationnelle ? 'Opérationnelle' : 'En réserve' }}
                    </span>
                </div>

                <!-- Alerte d'erreur éventuelle renvoyée par le serveur (ex: coordonnées hors région) -->
                <div v-if="coordsErrors.latitude || coordsErrors.longitude" class="p-2.5 rounded-lg bg-rose-50 border border-rose-300 text-xs text-rose-800 flex items-start gap-2">
                    <i class="pi pi-exclamation-triangle text-rose-600 mt-0.5 shrink-0"></i>
                    <div>
                        <div class="font-bold">Contrôle territorial :</div>
                        <p>{{ coordsErrors.latitude || coordsErrors.longitude }}</p>
                    </div>
                </div>

                <!-- Champ 1 : Région de rattachement -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Région de rattachement
                    </label>
                    <select
                        v-model="coordsForm.region_id"
                        class="w-full text-xs py-1.5 px-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
                        required
                    >
                        <option v-for="r in regions" :key="r.id" :value="r.id">
                            {{ r.code }} - {{ r.nom }}
                        </option>
                    </select>
                </div>

                <!-- Champ 2 : Ville / Localité / Implantation -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Ville / Localité d'implantation
                    </label>
                    <input
                        v-model="coordsForm.ville"
                        type="text"
                        placeholder="Ex: Ségou, Bla, Pelengana, Niono..."
                        class="w-full text-xs py-1.5 px-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
                    />
                </div>

                <!-- Champs 3 & 4 : Latitude et Longitude GPS (décimaux) -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1 flex items-center justify-between">
                            <span>Latitude (Nord)</span>
                            <span class="text-[10px] text-slate-400 font-mono">-90 à 90</span>
                        </label>
                        <input
                            v-model="coordsForm.latitude"
                            type="text"
                            placeholder="Ex: 13.4333"
                            class="w-full text-xs py-1.5 px-2.5 font-mono rounded-lg border border-slate-300 bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
                            :class="{ '!border-rose-500 !bg-rose-50/50': coordsErrors.latitude }"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1 flex items-center justify-between">
                            <span>Longitude (Ouest / Est)</span>
                            <span class="text-[10px] text-slate-400 font-mono">-180 à 180</span>
                        </label>
                        <input
                            v-model="coordsForm.longitude"
                            type="text"
                            placeholder="Ex: -6.2667"
                            class="w-full text-xs py-1.5 px-2.5 font-mono rounded-lg border border-slate-300 bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
                            :class="{ '!border-rose-500 !bg-rose-50/50': coordsErrors.longitude }"
                        />
                    </div>
                </div>

                <!-- Conseil d'utilisation -->
                <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 text-[11px] text-slate-600 space-y-1">
                    <div class="font-semibold text-slate-800 flex items-center gap-1.5">
                        <i class="pi pi-compass text-sky-600"></i>
                        <span>Positionnement dans les Cercles officiels</span>
                    </div>
                    <p class="text-slate-500 leading-relaxed">
                        Les coordonnées GPS déterminent l'ancrage géographique exact de l'unité au sein de son cercle de rattachement. Dès validation, la carte actualise la position de la brigade en temps réel.
                    </p>
                </div>

                <!-- Actions du footer -->
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200">
                    <button
                        type="button"
                        @click="showEditCoordsModal = false"
                        class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold cursor-pointer transition-colors"
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        :disabled="savingCoords"
                        class="px-4 py-1.5 bg-sky-700 hover:bg-sky-800 disabled:opacity-50 text-white rounded-lg text-xs font-bold flex items-center gap-1.5 cursor-pointer shadow-xs transition-colors"
                    >
                        <i v-if="savingCoords" class="pi pi-spin pi-spinner text-xs"></i>
                        <i v-else class="pi pi-check text-xs"></i>
                        <span>{{ savingCoords ? 'Enregistrement...' : 'Enregistrer les coordonnées' }}</span>
                    </button>
                </div>
            </form>
        </Dialog>

        <!-- Modal: Revue nominale des effectifs d'un Théâtre d'Opérations (Trié du plus haut grade au plus petit) -->
        <Dialog
            v-model:visible="showTheatreMilitairesModal"
            modal
            :header="`Forces projetées • ${selTheatreObj?.nom || 'Théâtre'} (${selTheatreObj?.code || ''})`"
            :style="{ width: '920px', maxWidth: '96vw' }"
            class="p-fluid"
        >
            <div class="space-y-4 pt-1">
                <!-- En-tête récapitulatif du théâtre -->
                <div v-if="selTheatreObj" class="p-3.5 rounded-xl bg-slate-900 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm border border-slate-800">
                    <div class="flex items-center gap-3">
                        <span
                            class="w-4 h-4 rounded-full border-2 border-white shrink-0"
                            :style="{ backgroundColor: selTheatreObj.couleur }"
                        ></span>
                        <div>
                            <div class="text-sm font-black text-white flex items-center gap-2">
                                <span>{{ selTheatreObj.nom }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-sky-500/20 text-sky-200 border border-sky-500/30">
                                    {{ selTheatreObj.code }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-400 mt-0.5 flex flex-wrap items-center gap-2">
                                <span>PC : <strong class="text-slate-200">{{ selTheatreObj.pc_lieu || 'Non renseigné' }}</strong></span>
                                <span>•</span>
                                <span>Emprise : <strong class="text-slate-200">{{ selTheatreObj.regions_codes?.join(', ') || 'Nationale' }}</strong></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 sm:border-l sm:border-slate-800 sm:pl-4">
                        <div class="text-right">
                            <div class="text-[9.5px] uppercase font-bold text-slate-400">Gendarmerie</div>
                            <div class="text-lg font-black text-sky-300 font-mono">{{ selTheatreObj.effectif }}</div>
                        </div>
                        <div class="text-right border-l border-slate-800 pl-3">
                            <div class="text-[9.5px] uppercase font-bold text-amber-400">Appui FAMa</div>
                            <div class="text-lg font-black text-amber-300 font-mono">+{{ selTheatreObj.effectif_appui || 0 }}</div>
                        </div>
                        <div class="text-right border-l border-slate-800 pl-3">
                            <div class="text-[9.5px] uppercase font-bold text-emerald-400">Total Combattants</div>
                            <div class="text-xl font-black text-emerald-300 font-mono">{{ (selTheatreObj.effectif || 0) + (selTheatreObj.effectif_appui || 0) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Dispositif des Unités d'Appui rattachées -->
                <div v-if="selTheatreObj?.unites_appui && selTheatreObj.unites_appui.length > 0" class="p-2.5 rounded-lg bg-amber-50/70 border border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="font-bold text-amber-950 flex items-center gap-1">
                            <i class="pi pi-shield text-amber-700 text-xs"></i>
                            <span>Appuis Interarmées FAMa :</span>
                        </span>
                        <span
                            v-for="ua in selTheatreObj.unites_appui"
                            :key="ua.id"
                            class="px-2 py-0.5 rounded text-[11px] font-bold border"
                            :style="{ backgroundColor: (ua.couleur || '#0284c7') + '15', color: ua.couleur || '#0284c7', borderColor: (ua.couleur || '#0284c7') + '40' }"
                            :title="ua.nom + (ua.observations ? ' • ' + ua.observations : '')"
                        >
                            {{ ua.code }} : {{ ua.effectif }}
                        </span>
                    </div>
                    <span class="text-[11px] font-bold text-amber-800 shrink-0">
                        Total Appuis : {{ selTheatreObj.effectif_appui }} combattants
                    </span>
                </div>

                <!-- Barre d'outils : Filtres statutaires & Recherche textuelle -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-2.5 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                    <!-- Onglets de filtres hiérarchiques -->
                    <div class="flex flex-wrap items-center gap-1 text-xs">
                        <button
                            type="button"
                            @click="theatreMilitaireCategorieFilter = 'tous'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition cursor-pointer text-xs"
                            :class="theatreMilitaireCategorieFilter === 'tous' ? 'bg-sky-800 text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                        >
                            Tous ({{ selTheatreObj?.effectif || 0 }})
                        </button>
                        <button
                            type="button"
                            @click="theatreMilitaireCategorieFilter = 'officiers'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition cursor-pointer text-xs flex items-center gap-1"
                            :class="theatreMilitaireCategorieFilter === 'officiers' ? 'bg-sky-700 text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                        >
                            <i class="pi pi-star-fill text-[10px]"></i>
                            <span>Officiers ({{ theatreStatsHierarchiques.officiers }})</span>
                        </button>
                        <button
                            type="button"
                            @click="theatreMilitaireCategorieFilter = 'sous_officiers'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition cursor-pointer text-xs flex items-center gap-1"
                            :class="theatreMilitaireCategorieFilter === 'sous_officiers' ? 'bg-emerald-700 text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                        >
                            <i class="pi pi-shield text-[10px]"></i>
                            <span>Sous-Officiers ({{ theatreStatsHierarchiques.sousOfficiers }})</span>
                        </button>
                        <button
                            type="button"
                            @click="theatreMilitaireCategorieFilter = 'mdr'"
                            class="px-2.5 py-1 rounded-lg font-semibold transition cursor-pointer text-xs flex items-center gap-1"
                            :class="theatreMilitaireCategorieFilter === 'mdr' ? 'bg-slate-700 text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                        >
                            <i class="pi pi-user text-[10px]"></i>
                            <span>MDR ({{ theatreStatsHierarchiques.mdr }})</span>
                        </button>
                        <button
                            type="button"
                            @click="theatreMilitaireCategorieFilter = 'blesses'"
                            class="px-2.5 py-1 rounded-lg font-bold transition cursor-pointer text-xs flex items-center gap-1"
                            :class="theatreMilitaireCategorieFilter === 'blesses' ? 'bg-rose-700 text-white shadow-xs' : 'bg-rose-50 text-rose-800 border border-rose-200 hover:bg-rose-100'"
                        >
                            <i class="pi pi-heart-fill text-[10px]"></i>
                            <span>Blessés Guerre ({{ theatreStatsHierarchiques.blesses }})</span>
                        </button>
                    </div>

                    <!-- Champ recherche -->
                    <div class="relative w-full md:w-60">
                        <input
                            v-model="searchTheatreMilitaire"
                            type="text"
                            placeholder="Rechercher nom, grade, matricule..."
                            class="w-full text-xs py-1.5 pl-8 pr-2.5 rounded-lg border border-slate-300 bg-white focus:outline-none focus:ring-2 focus:ring-sky-500"
                        />
                        <i class="pi pi-search absolute left-2.5 top-2 text-xs text-slate-400"></i>
                    </div>
                </div>

                <!-- Indication d'ordonnancement -->
                <div class="flex items-center justify-between text-[11px] text-slate-500 px-1">
                    <span class="flex items-center gap-1.5 font-medium">
                        <i class="pi pi-sort-amount-down text-sky-700"></i>
                        <span>Liste classée <strong>par ordre hiérarchique décroissant</strong> (du plus haut grade au soldat).</span>
                    </span>
                    <span class="font-mono text-slate-600">
                        {{ theatreMilitairesTriesEtFiltres.length }} gendarme(s) listé(s)
                    </span>
                </div>

                <!-- Loader chargement asynchrone -->
                <div v-if="loadingTheatreMilitaires" class="py-16 text-center text-slate-500 font-semibold flex flex-col items-center justify-center gap-3 border border-slate-200 rounded-xl bg-slate-50/50">
                    <i class="pi pi-spin pi-spinner text-3xl text-sky-600"></i>
                    <span class="text-xs font-bold text-slate-700">Chargement des effectifs déployés sur ce théâtre d'opérations...</span>
                </div>

                <!-- Table nominative -->
                <div v-else class="max-h-[52vh] overflow-y-auto border border-slate-200 rounded-xl custom-scrollbar bg-white">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="sticky top-0 bg-slate-100/95 backdrop-blur-xs text-slate-700 font-bold border-b border-slate-200 uppercase text-[10px] tracking-wider z-10">
                            <tr>
                                <th class="p-2.5 pl-3">Grade & Identité</th>
                                <th class="p-2.5">Matricule / Mle</th>
                                <th class="p-2.5">Unité & Région d'origine</th>
                                <th class="p-2.5">Statut Opérationnel / Circonstance</th>
                                <th class="p-2.5 pr-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="m in theatreMilitairesPagine"
                                :key="m.id"
                                class="hover:bg-sky-50/40 transition"
                                :class="m.est_blesse_guerre ? 'bg-rose-50/30' : ''"
                            >
                                <!-- Grade & Nom -->
                                <td class="p-2.5 pl-3">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="px-2 py-0.5 rounded text-[10.5px] font-bold border shrink-0"
                                            :class="gradeBadgeStyle(m.grade_ordre)"
                                        >
                                            {{ m.grade }}
                                        </span>
                                        <div>
                                            <div class="font-bold text-slate-900 leading-tight">
                                                {{ m.nom }} {{ m.prenom }}
                                            </div>
                                            <div class="text-[10.5px] text-slate-400 capitalize">
                                                {{ (m.categorie_statutaire || '').toLowerCase().replace(/_/g, ' ') }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Matricule -->
                                <td class="p-2.5 font-mono text-slate-600 font-semibold text-[11px]">
                                    {{ m.matricule || m.mle_grm || 'Sans matricule' }}
                                </td>

                                <!-- Unité & Région -->
                                <td class="p-2.5">
                                    <div class="font-semibold text-slate-800 text-[11px] truncate max-w-[220px]" :title="m.unite_nom">
                                        {{ m.unite_nom }}
                                    </div>
                                    <div class="text-[10.5px] text-slate-500">
                                        {{ m.region_nom }}
                                    </div>
                                </td>

                                <!-- Statut & Circonstance de blessure -->
                                <td class="p-2.5">
                                    <div v-if="m.est_blesse_guerre" class="space-y-0.5">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">
                                            <i class="pi pi-exclamation-triangle text-[9px]"></i>
                                            <span>Blessé de guerre au combat</span>
                                        </span>
                                        <div v-if="m.circonstance_blessure" class="text-[11px] text-rose-900 font-medium italic leading-snug">
                                            « {{ m.circonstance_blessure }} »
                                        </div>
                                        <div v-if="m.nature_pathologie" class="text-[10px] text-slate-600">
                                            Pathologie : {{ m.nature_pathologie }}
                                        </div>
                                        <div v-if="m.date_blessure" class="text-[10px] text-slate-400 font-mono">
                                            Date : {{ m.date_blessure }}
                                        </div>
                                    </div>
                                    <div v-else>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                            <i class="pi pi-check text-[9px]"></i>
                                            <span>Opérationnel actif</span>
                                        </span>
                                    </div>
                                </td>

                                <!-- Action : Consulter Fiche -->
                                <td class="p-2.5 pr-3 text-right">
                                    <Link
                                        :href="route('militaires.show', m.id)"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-sky-800 hover:text-sky-950 bg-sky-50 hover:bg-sky-100 border border-sky-200 rounded-md transition shadow-2xs"
                                        title="Ouvrir la fiche individuelle"
                                    >
                                        <i class="pi pi-id-card text-[10px]"></i>
                                        <span>Fiche</span>
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="theatreMilitairesTriesEtFiltres.length === 0">
                                <td colspan="5" class="py-10 text-center text-slate-400 italic text-xs">
                                    Aucun militaire ne correspond à ces critères sur ce théâtre d'opérations.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Contrôles de pagination -->
                <div v-if="theatreMilitairesTriesEtFiltres.length > 0" class="flex flex-col sm:flex-row items-center justify-between gap-2 py-2 px-1 border-t border-slate-100 text-xs">
                    <div class="text-[11px] text-slate-500 font-medium">
                        Affichage de <span class="font-bold text-slate-700">{{ (theatrePage - 1) * theatrePerPage + 1 }}</span> à <span class="font-bold text-slate-700">{{ Math.min(theatrePage * theatrePerPage, theatreMilitairesTriesEtFiltres.length) }}</span> sur <span class="font-bold text-slate-700">{{ theatreMilitairesTriesEtFiltres.length }}</span> gendarmes
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            :disabled="theatrePage <= 1"
                            @click="theatrePage--"
                            class="px-2.5 py-1 rounded border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-medium flex items-center gap-1 transition cursor-pointer"
                        >
                            <i class="pi pi-chevron-left text-[10px]"></i>
                            <span>Précédent</span>
                        </button>

                        <span class="px-2.5 py-1 bg-sky-50 text-sky-800 font-bold rounded border border-sky-200 text-xs font-mono">
                            {{ theatrePage }} / {{ theatreTotalPages }}
                        </span>

                        <button
                            type="button"
                            :disabled="theatrePage >= theatreTotalPages"
                            @click="theatrePage++"
                            class="px-2.5 py-1 rounded border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-medium flex items-center gap-1 transition cursor-pointer"
                        >
                            <span>Suivant</span>
                            <i class="pi pi-chevron-right text-[10px]"></i>
                        </button>

                        <select
                            v-model.number="theatrePerPage"
                            @change="theatrePage = 1"
                            class="ml-2 text-[11px] py-1 px-2 rounded border border-slate-200 bg-white text-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer"
                        >
                            <option :value="25">25 / page</option>
                            <option :value="50">50 / page</option>
                            <option :value="100">100 / page</option>
                        </select>
                    </div>
                </div>

                <!-- Footer de la modale -->
                <div class="flex items-center justify-between pt-2 border-t border-slate-200">
                    <div class="text-[11px] text-slate-500">
                        Total des effectifs déployés : <strong class="text-slate-800">{{ selTheatreObj?.effectif || 0 }}</strong> personnels
                    </div>
                    <button
                        type="button"
                        @click="showTheatreMilitairesModal = false"
                        class="px-4 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold cursor-pointer transition"
                    >
                        Fermer
                    </button>
                </div>
            </div>
        </Dialog>

        <!-- Modal: Modifier les coordonnées GPS du Poste de Commandement (PC) d'un Théâtre d'Opérations -->
        <Dialog
            v-model:visible="showEditTheatreCoordsModal"
            modal
            :header="`📍 Coordonnées GPS du PC • ${editTheatreCoordsForm.theatre?.nom || ''}`"
            :style="{ width: '500px', maxWidth: '95vw' }"
            class="p-fluid"
        >
            <div class="space-y-3.5 pt-1">
                <div v-if="editTheatreCoordsForm.theatre" class="p-3 rounded-lg bg-sky-50 border border-sky-200 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-sky-900">Théâtre d'opérations</div>
                        <div class="font-extrabold text-slate-900 text-sm truncate">{{ editTheatreCoordsForm.theatre.nom }}</div>
                        <div class="text-[11px] text-slate-600 mt-0.5 font-mono">
                            Code : <strong>{{ editTheatreCoordsForm.theatre.code }}</strong>
                            <span class="ml-2 font-sans">• {{ editTheatreCoordsForm.theatre.effectif }} gendarme(s) engagé(s)</span>
                        </div>
                    </div>
                    <span
                        class="w-4 h-4 rounded-full border border-black/20 shrink-0"
                        :style="{ backgroundColor: editTheatreCoordsForm.theatre.couleur }"
                    ></span>
                </div>

                <!-- Sélection de la région pour coordonnées par défaut -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1 flex items-center justify-between">
                        <span><i class="pi pi-map text-sky-700 mr-1"></i>Région d'ancrage (Coordonnées par défaut)</span>
                        <span class="text-[10px] text-sky-700 font-normal">Pré-remplissage</span>
                    </label>
                    <select
                        v-model="selectedRegionForPCModal"
                        @change="onSelectRegionForPCModal(selectedRegionForPCModal)"
                        class="w-full text-xs py-2 px-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 font-medium"
                    >
                        <option value="">-- Choisir une région d'ancrage --</option>
                        <option v-for="r in regions" :key="r.code" :value="r.code">
                            [{{ r.code }}] {{ r.nom }} (Chef-lieu : {{ r.chef_lieu || 'Non renseigné' }})
                        </option>
                    </select>
                    <span class="text-[10px] text-slate-500 block mt-1">
                        Sélectionnez une région pour insérer automatiquement son chef-lieu et ses coordonnées GPS par défaut avant d'ajuster.
                    </span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Lieu / Localité d'implantation du Poste de Commandement (PC)
                    </label>
                    <input
                        v-model="editTheatreCoordsForm.pc_lieu"
                        type="text"
                        placeholder="Ex: Sévaré, Gao, Sikasso, Tombouctou..."
                        class="w-full text-xs py-1.5 px-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1 flex items-center justify-between">
                            <span>Latitude GPS (Nord)</span>
                            <span class="text-[10px] text-slate-400 font-mono">WGS84</span>
                        </label>
                        <input
                            v-model="editTheatreCoordsForm.latitude"
                            type="number"
                            step="0.000001"
                            placeholder="Ex: 14.5333"
                            class="w-full text-xs py-1.5 px-2.5 font-mono rounded-lg border border-slate-300 bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1 flex items-center justify-between">
                            <span>Longitude GPS</span>
                            <span class="text-[10px] text-slate-400 font-mono">WGS84</span>
                        </label>
                        <input
                            v-model="editTheatreCoordsForm.longitude"
                            type="number"
                            step="0.000001"
                            placeholder="Ex: -4.1000"
                            class="w-full text-xs py-1.5 px-2.5 font-mono rounded-lg border border-slate-300 bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
                        />
                    </div>
                </div>

                <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 text-[11px] text-slate-600 space-y-1">
                    <div class="font-semibold text-slate-800 flex items-center gap-1.5">
                        <i class="pi pi-compass text-sky-700"></i>
                        <span>Ancrage géographique du Poste de Commandement</span>
                    </div>
                    <p class="text-slate-500 leading-relaxed">
                        Ces coordonnées décimales positionnent le marqueur tactique du PC de ce théâtre d'opérations sur la carte opérationnelle.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200">
                    <button
                        type="button"
                        @click="showEditTheatreCoordsModal = false"
                        class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold cursor-pointer transition-colors"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        @click="saveTheatreCoords"
                        :disabled="savingTheatreCoords"
                        class="px-4 py-1.5 bg-gradient-to-r from-sky-800 to-sky-700 hover:from-sky-900 hover:to-sky-800 disabled:opacity-50 text-white rounded-lg text-xs font-bold flex items-center gap-1.5 cursor-pointer shadow-xs transition-colors"
                    >
                        <i v-if="savingTheatreCoords" class="pi pi-spin pi-spinner text-xs"></i>
                        <i v-else class="pi pi-check text-xs"></i>
                        <span>{{ savingTheatreCoords ? 'Enregistrement...' : 'Enregistrer les coordonnées' }}</span>
                    </button>
                </div>
            </div>
        </Dialog>

        <!-- MODAL CONFIRMATION SUPPRESSION UNITÉ D'APPUI -->
        <Dialog
            v-model:visible="showDeleteAppuiDialog"
            header="Confirmation de suppression"
            :modal="true"
            :style="{ width: '420px', zIndex: 3000 }"
            class="w-full max-w-md"
        >
            <div class="space-y-3 pt-1">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0 text-lg">
                        <i class="pi pi-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-900 mb-1">
                            Supprimer l'unité d'appui « {{ appuiToDelete?.code }} - {{ appuiToDelete?.nom }} » ?
                        </h4>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Êtes-vous sûr de vouloir retirer cet appui de <span class="font-bold text-slate-800">{{ appuiToDelete?.effectif }} combattants</span> de ce théâtre d'opérations ?
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                    <button
                        type="button"
                        @click="showDeleteAppuiDialog = false"
                        class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold cursor-pointer"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        @click="executeDeleteAppui"
                        :disabled="deletingAppui"
                        class="px-4 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold flex items-center gap-1.5 cursor-pointer shadow-xs disabled:opacity-50"
                    >
                        <i v-if="deletingAppui" class="pi pi-spin pi-spinner text-xs"></i>
                        <i v-else class="pi pi-trash text-xs"></i>
                        <span>{{ deletingAppui ? 'Suppression...' : 'Supprimer définitivement' }}</span>
                    </button>
                </div>
            </div>
        </Dialog>

        <!-- MODAL AJOUT D'UNITÉ D'APPUI DEPUIS LA CARTE -->
        <Dialog
            v-model:visible="showAddAppuiModal"
            :header="`Ajouter un appui • ${selTheatreObj?.nom || 'Théâtre'}`"
            :modal="true"
            :style="{ width: '520px', zIndex: 3000 }"
            class="w-full max-w-lg"
        >
            <div class="space-y-3 pt-1">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">Corps d'armée / Spécialité rapide :</label>
                    <div class="flex flex-wrap gap-1">
                        <button
                            v-for="p in mapAppuiPresets"
                            :key="p.code"
                            type="button"
                            @click="selectMapAppuiPreset(p)"
                            class="px-2 py-0.5 rounded text-[10.5px] font-semibold transition border flex items-center gap-1 cursor-pointer"
                            :class="newAppuiForm.code === p.code ? 'bg-sky-900 text-white border-sky-900 shadow-2xs' : 'bg-white text-slate-700 hover:bg-slate-100 border-slate-200'"
                        >
                            <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: p.couleur }"></span>
                            <span>{{ p.code }}</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <div>
                        <label class="block text-[10.5px] font-semibold text-slate-600 mb-0.5">Code militaire *</label>
                        <input
                            v-model="newAppuiForm.code"
                            placeholder="ex: AA, AT"
                            class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs uppercase font-mono font-bold"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[10.5px] font-semibold text-slate-600 mb-0.5">Désignation complète *</label>
                        <input
                            v-model="newAppuiForm.nom"
                            placeholder="ex: Armée de l'Air, Transmissions"
                            class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[10.5px] font-semibold text-slate-600 mb-0.5">Effectif déployé *</label>
                        <input
                            v-model="newAppuiForm.effectif"
                            type="number"
                            min="0"
                            placeholder="ex: 20"
                            class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs font-bold"
                        />
                    </div>
                    <div>
                        <label class="block text-[10.5px] font-semibold text-slate-600 mb-0.5">Chef de détachement</label>
                        <input
                            v-model="newAppuiForm.chef_detachement"
                            placeholder="ex: Cne Traoré"
                            class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-[10.5px] font-semibold text-slate-600 mb-0.5">Mission / Observation</label>
                    <input
                        v-model="newAppuiForm.observations"
                        placeholder="ex: Section mortier & déminage"
                        class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs"
                    />
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                    <button
                        type="button"
                        @click="showAddAppuiModal = false"
                        class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold cursor-pointer"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        @click="saveNewAppuiOnMap"
                        :disabled="savingAppui"
                        class="px-4 py-1.5 bg-sky-800 hover:bg-sky-900 text-white rounded-lg text-xs font-bold flex items-center gap-1.5 cursor-pointer shadow-xs disabled:opacity-50"
                    >
                        <i v-if="savingAppui" class="pi pi-spin pi-spinner text-xs"></i>
                        <i v-else class="pi pi-check text-xs"></i>
                        <span>{{ savingAppui ? 'Enregistrement...' : 'Enregistrer cet appui' }}</span>
                    </button>
                </div>
            </div>
        </Dialog>
    </AuthenticatedLayout>
</template>

<style scoped>
.chip {
    font-size: 11.5px;
    border: 1px solid #dde3ea;
    border-radius: 9999px;
    padding: 2.5px 9px;
    color: #14202e;
    background: transparent;
    cursor: pointer;
    transition: all 0.15s ease;
}
.chip[aria-pressed='true'] {
    background: #1d4f91;
    border-color: #1d4f91;
    color: #fff;
    box-shadow: 0 1px 2px rgba(29, 79, 145, 0.25);
}
.chip:hover:not([aria-pressed='true']) {
    background: #f1f5f9;
}

.reg {
    cursor: pointer;
    transition: filter 0.15s ease, opacity 0.2s ease, stroke 0.15s ease;
}
.reg:hover,
.reg.hovered {
    filter: brightness(1.25) drop-shadow(0 0 8px rgba(95, 155, 234, 0.45));
    stroke: #5f9bea !important;
}
.reg.sel {
    filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.4));
}

.unit-marker {
    transition: transform 0.15s ease;
}
.unit-marker:hover circle {
    filter: brightness(1.2);
}

/* Badges tactiques haute visibilité des régions */
.tactical-badge-nom {
    font-size: 7px;
    font-weight: 700;
    fill: #bae6fd;
    letter-spacing: 0.4px;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    pointer-events: none;
}
.tactical-badge-val {
    font-size: 11.5px;
    font-weight: 900;
    fill: #ffffff;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-variant-numeric: tabular-nums;
    pointer-events: none;
}
.tactical-bamako-nom {
    font-size: 7.5px;
    font-weight: 800;
    fill: #7dd3fc;
    letter-spacing: 0.5px;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    pointer-events: none;
}
.tactical-bamako-val {
    font-size: 13px;
    font-weight: 900;
    fill: #ffffff;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-variant-numeric: tabular-nums;
    pointer-events: none;
}
.lbl-zero-nm {
    font-size: 8px;
    font-weight: 600;
    fill: #64748b;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    pointer-events: none;
}
.lbl-zero-num {
    font-size: 9.5px;
    font-weight: 700;
    fill: #475569;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    pointer-events: none;
}

.lbl {
    font: 600 11px system-ui, -apple-system, sans-serif;
    fill: #ffffff;
    paint-order: stroke;
    stroke: #16212e;
    stroke-width: 3.5px;
    stroke-linejoin: round;
    pointer-events: none;
    text-anchor: middle;
}
.lbl.nm {
    font-size: 8.5px;
    font-weight: 500;
    fill: #e2e8f0;
    stroke: #16212e;
    stroke-width: 2.8px;
}
.lbl.num {
    font-size: 11.5px;
    font-weight: 700;
    fill: #ffffff;
    stroke: #16212e;
    stroke-width: 3.2px;
}
.lbl.text-highlight {
    fill: #38bdf8;
    font-weight: 800;
    stroke: #16212e;
}

.lead {
    stroke-width: 1.2;
    pointer-events: none;
}
.dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 4px;
    flex: none;
}

.rank {
    display: flex;
    flex-direction: column;
    gap: 2.5px;
    margin: 0;
    padding: 0;
    list-style: none;
}
.rank button {
    all: unset;
    box-sizing: border-box;
    display: grid;
    grid-template-columns: minmax(90px, 1.25fr) 1fr 38px;
    gap: 5px;
    align-items: center;
    width: 100%;
    padding: 2.5px 4px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11.5px;
    transition: background 0.12s ease;
}
.rank button:hover,
.rank button.sel {
    background: #f1f5f9;
}
.rank button.sel {
    border-left: 3px solid #1d4f91;
    font-weight: 600;
}
.rank button:focus-visible {
    outline: 2px solid #1d4f91;
}
.rank .n {
    display: flex;
    align-items: center;
    min-width: 0;
    white-space: nowrap;
}
.rank .t {
    height: 5px;
    background: #e2e8f0;
    border-radius: 3px;
    overflow: hidden;
}
.rank .t i {
    display: block;
    height: 100%;
    border-radius: 3px;
}
.rank .v {
    text-align: right;
    font-weight: 700;
    font-size: 11px;
    font-variant-numeric: tabular-nums;
}

/* Styles tactiques pour la Carte des Théâtres d'Opérations (conforme maquette RH DGGN) */
.lead {
    stroke-width: 1.2;
    fill: none;
    opacity: 0.8;
}
.lab rect {
    fill: #0c1527;
    rx: 7;
}
.lab text {
    fill: #e7edf8;
    pointer-events: none;
}
.lab .n {
    font-weight: 700;
    font-size: 12px;
}
.lab .s {
    fill: #8da0c0;
    font-size: 9.5px;
}
.mk {
    cursor: pointer;
}
.mk circle.halo {
    opacity: 0.25;
}
g.dim {
    opacity: 0.25;
}
g.sel .lab rect,
g.hov .lab rect {
    stroke-width: 2.5;
}

.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #94a3b8 #f1f5f9;
}
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

:fullscreen {
    background-color: #f1f5f9;
    padding: 0.5rem;
    width: 100vw;
    height: 100vh;
    box-sizing: border-box;
}
:-webkit-full-screen {
    background-color: #f1f5f9;
    padding: 0.5rem;
    width: 100vw;
    height: 100vh;
    box-sizing: border-box;
}
</style>
