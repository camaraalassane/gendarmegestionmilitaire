<template>
    <AuthenticatedLayout>
        <Head title="Carte Opérationnelle du Mali" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-amber-400/20 border border-amber-400/40 flex items-center justify-center text-amber-300">
                        <i class="pi pi-map text-base"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-white leading-tight">
                            Carte Opérationnelle du Mali
                        </h1>
                        <p class="text-[11px] text-sky-200">
                            {{ regions.length }} régions administratives • Maillage territorial en direct
                        </p>
                    </div>
                </div>
                <div class="hidden sm:flex items-center gap-2 text-xs text-sky-100 bg-sky-900/40 border border-sky-600/30 px-3 py-1.5 rounded-lg">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Situation au {{ stats.generated_at || 'ce jour' }}</span>
                </div>
            </div>
        </template>

        <!-- Container principal fixé dans le viewport (aucun défilement global de la page) -->
        <div class="h-[calc(100vh-5.25rem)] flex flex-col gap-2.5 overflow-hidden -m-1 sm:-m-2">
            <!-- 1. Chiffres clés (KPIs) en une seule ligne compacte -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 flex-none">
                <div
                    v-for="k in kpis"
                    :key="k.label"
                    class="bg-white rounded-lg border border-slate-200 px-3.5 py-2 shadow-sm flex flex-col justify-between"
                >
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 truncate">
                        {{ k.label }}
                    </div>
                    <div class="text-xl sm:text-2xl font-bold text-slate-900 tabular-nums leading-tight my-0.5">
                        {{ k.value }}
                    </div>
                    <div class="text-[11px] text-slate-500 truncate">
                        {{ k.hint }}
                    </div>
                </div>
            </div>

            <!-- Alerte non rattachés si existants -->
            <div
                v-if="stats.non_rattaches > 0"
                class="rounded-lg border border-amber-300 bg-amber-50 text-amber-900 text-xs px-3 py-1.5 flex items-center justify-between flex-none"
            >
                <div class="flex items-center gap-2">
                    <i class="pi pi-exclamation-triangle text-amber-600"></i>
                    <span>
                        <strong>{{ fmt(stats.non_rattaches) }}</strong> militaires ne sont rattachés à aucune région ni unité répertoriée (non comptés sur la carte).
                    </span>
                </div>
            </div>

            <!-- 2. Zone principale : Carte à gauche (56%) et Détails/Classement à droite (44%) -->
            <div class="flex-1 min-h-0 grid gap-2.5 lg:grid-cols-[minmax(0,1.28fr)_minmax(0,1fr)] items-stretch">
                <!-- PANNEAU CARTE -->
                <div class="bg-white rounded-lg border border-slate-200 p-3 min-w-0 flex flex-col h-full overflow-hidden shadow-sm">
                    <!-- Barre d'actions / Filtres au dessus de la carte -->
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2 flex-none">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <template v-if="sel">
                                <button
                                    class="chip bg-sky-900 text-white border-sky-900 hover:bg-sky-800 flex items-center gap-1.5 font-medium shadow-sm"
                                    @click="retour"
                                >
                                    <i class="pi pi-arrow-left text-xs"></i>
                                    <span>Retour au Mali entier</span>
                                </button>
                                <span class="text-xs font-semibold text-slate-700 bg-slate-100 px-2.5 py-1 rounded-full border border-slate-200">
                                    {{ selRegion?.nom }} : {{ selRegion?.unites.length }} unités
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
                        </div>

                        <!-- Menu rapide pour cibler une région -->
                        <div class="flex items-center gap-1.5">
                            <select
                                :value="sel || ''"
                                @change="onSelectRegionDropdown($event.target.value)"
                                class="text-xs border border-slate-200 rounded-lg px-2.5 py-1 bg-white text-slate-700 focus:outline-none focus:border-sky-500 font-medium"
                                aria-label="Choisir une région"
                            >
                                <option value="">Tout le Mali (20 régions)</option>
                                <option v-for="r in regions" :key="r.code" :value="r.code">
                                    {{ r.code }} - {{ r.nom }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Canevas Carte SVG (redimensionnement automatique pour remplir l'espace disponible) -->
                    <div class="flex-1 min-h-0 w-full h-full relative flex items-center justify-center overflow-hidden bg-gradient-to-b from-slate-50/50 to-sky-50/20 rounded-lg border border-slate-100">
                        <!-- Entête au survol interactif (affiche immédiatement nom, code, effectif et unités de la région survolée) -->
                        <transition name="fade">
                            <div
                                v-if="hoveredInfo"
                                class="absolute top-2.5 left-2.5 z-20 bg-slate-900/90 backdrop-blur text-white px-3 py-1.5 rounded-lg shadow-lg text-xs pointer-events-none flex items-center gap-2 border border-slate-700"
                            >
                                <span
                                    class="w-2.5 h-2.5 rounded-full flex-none"
                                    :style="{ background: hoveredInfo.isUnit ? (hoveredInfo.op ? '#10b981' : '#f59e0b') : '#38bdf8' }"
                                ></span>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-white">{{ hoveredInfo.title }}</span>
                                    <span class="text-slate-300">•</span>
                                    <span class="text-sky-300 font-medium">{{ hoveredInfo.subtitle }}</span>
                                    <span v-if="hoveredInfo.extra" class="text-slate-400 text-[11px]">({{ hoveredInfo.extra }})</span>
                                </div>
                            </div>
                        </transition>

                        <svg
                            :viewBox="viewBox.join(' ')"
                            class="w-full h-full max-h-full block select-none"
                            preserveAspectRatio="xMidYMid meet"
                            role="img"
                            aria-label="Carte du Mali par région"
                        >
                            <!-- Tracés des régions SVG -->
                            <template v-for="r in regions" :key="r.code">
                                <path
                                    v-if="SHAPES[r.code] && !(r.code === 'R1' && !sel)"
                                    :d="SHAPES[r.code].d"
                                    class="reg"
                                    :class="{
                                        sel: r.code === sel,
                                        dim: sel && r.code !== sel,
                                        hovered: hoveredCode === r.code
                                    }"
                                    :style="{ fill: fillFor(r), strokeWidth: 1.2 * zoom }"
                                    @click="choisir(r.code)"
                                    @mouseenter="onHoverRegion(r)"
                                    @mouseleave="onLeaveHover"
                                >
                                    <title>{{ r.nom }} : {{ fmt(valueOf(r)) }}{{ METRICS[metric].unit }}</title>
                                </path>
                            </template>

                            <!-- Vue nationale : étiquettes noms + chiffres -->
                            <template v-if="!sel">
                                <template v-for="r in regions" :key="'l' + r.code">
                                    <g v-if="SHAPES[r.code] && r.code !== 'R1'" class="lbl-g">
                                        <text class="lbl nm" :x="SHAPES[r.code].cx" :y="SHAPES[r.code].cy - 2">
                                            {{ shortName(r.nom) }}
                                        </text>
                                        <text class="lbl" :x="SHAPES[r.code].cx" :y="SHAPES[r.code].cy + 10">
                                            {{ fmt(valueOf(r)) }}
                                        </text>
                                    </g>
                                </template>

                                <!-- District de Bamako : point + ligne directrice + étiquette -->
                                <template v-for="r in regions.filter(x => x.code === 'R1' && SHAPES.R1)" :key="'b'">
                                    <circle
                                        class="reg"
                                        :cx="SHAPES.R1.cx"
                                        :cy="SHAPES.R1.cy"
                                        r="6"
                                        :style="{ fill: fillFor(r) }"
                                        @click="choisir('R1')"
                                        @mouseenter="onHoverRegion(r)"
                                        @mouseleave="onLeaveHover"
                                    >
                                        <title>{{ r.nom }} : {{ fmt(valueOf(r)) }}{{ METRICS[metric].unit }}</title>
                                    </circle>
                                    <line
                                        class="lead"
                                        :x1="SHAPES.R1.cx"
                                        :y1="SHAPES.R1.cy"
                                        :x2="SHAPES.R1.cx - 26"
                                        :y2="SHAPES.R1.cy + 30"
                                    />
                                    <text class="lbl" :x="SHAPES.R1.cx - 26" :y="SHAPES.R1.cy + 42">
                                        Bamako {{ fmt(valueOf(r)) }}
                                    </text>
                                </template>
                            </template>

                            <!-- Région sélectionnée : pins géolocalisés des unités -->
                            <template v-else>
                                <circle
                                    v-for="u in unitesAvecPoint"
                                    :key="u.id"
                                    class="pin"
                                    :class="{ on: selUnit === u.id }"
                                    :cx="u.x"
                                    :cy="u.y"
                                    :r="rayon(u) * (selUnit === u.id ? 1.6 : 1)"
                                    :style="{
                                        fill: u.operationnelle ? '#1f7a4d' : '#b4540a',
                                        strokeWidth: (selUnit === u.id ? 2.5 : 1.6) * zoom
                                    }"
                                    @click="toggleUnit(u.id)"
                                    @mouseenter="onHoverUnit(u)"
                                    @mouseleave="onLeaveHover"
                                >
                                    <title>{{ u.nom }} : {{ fmt(u.effectif) }} militaires ({{ u.operationnelle ? 'Opérationnelle' : 'Non opérationnelle' }})</title>
                                </circle>

                                <!-- Étiquette unité active -->
                                <text
                                    v-if="unitePointee"
                                    class="lbl text-highlight"
                                    :style="{ fontSize: Math.max(9, 12 * zoom) + 'px', strokeWidth: 3 * zoom + 'px' }"
                                    :x="unitePointee.x"
                                    :y="unitePointee.y - 12 * zoom"
                                >
                                    {{ unitePointee.nom }} ({{ fmt(unitePointee.effectif) }} pers.)
                                </text>
                            </template>
                        </svg>
                    </div>

                    <!-- Légende au bas de la carte -->
                    <div class="flex flex-wrap items-center justify-between gap-3 mt-2 pt-2 border-t border-slate-100 text-xs text-slate-500 flex-none">
                        <template v-if="sel">
                            <div class="flex items-center gap-3">
                                <span class="flex items-center"><i class="dot" style="background:#1f7a4d"></i>Opérationnelle</span>
                                <span class="flex items-center"><i class="dot" style="background:#b4540a"></i>Non opérationnelle</span>
                                <span>• Rayon = effectif</span>
                            </div>
                            <span v-if="unitesSansPoint > 0" class="text-amber-700 font-medium">
                                • {{ unitesSansPoint }} unité(s) sans coordonnées GPS (listées à droite)
                            </span>
                        </template>
                        <template v-else>
                            <div class="flex items-center gap-2">
                                <span class="tabular-nums font-semibold">{{ fmt(legende.min) }}{{ METRICS[metric].unit }}</span>
                                <i class="legend-bar"></i>
                                <span class="tabular-nums font-semibold">{{ fmt(legende.max) }}{{ METRICS[metric].unit }}</span>
                            </div>
                            <span class="font-medium text-slate-600">• Indicateur : {{ METRICS[metric].label }}</span>
                        </template>
                    </div>
                </div>

                <!-- PANNEAU DROIT : DÉTAIL & CLASSEMENT OU RÉPARTITION (SEUL CE VOLET EST SCROLLABLE EN INTERNE) -->
                <div class="bg-white rounded-lg border border-slate-200 p-3 min-w-0 flex flex-col h-full overflow-hidden shadow-sm">
                    <!-- Entête du panneau -->
                    <div class="flex items-center justify-between gap-2 flex-none">
                        <div class="min-w-0">
                            <h2 class="text-sm sm:text-base font-bold text-slate-900 truncate">
                                {{ sel ? selRegion.nom : 'Vue Nationale (Mali)' }}
                            </h2>
                            <p v-if="sel && selRegion.chef_lieu" class="text-xs text-slate-500 truncate">
                                Chef-lieu : {{ selRegion.chef_lieu }}
                            </p>
                        </div>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200">
                            {{ sel ? selRegion.unites.length + ' unités' : regions.length + ' régions' }}
                        </span>
                    </div>

                    <!-- Grille métriques détaillées (3 ou 6 cellules) -->
                    <div class="grid grid-cols-3 gap-x-2 gap-y-1.5 my-2 pb-2 border-b border-slate-100 flex-none bg-slate-50/70 p-2 rounded-lg">
                        <div v-for="d in detail" :key="d.label" class="min-w-0">
                            <div class="text-[11px] text-slate-500 truncate">{{ d.label }}</div>
                            <div class="text-sm sm:text-base font-bold text-slate-900 tabular-nums truncate">
                                {{ d.value }}
                            </div>
                        </div>
                    </div>

                    <!-- Titre de liste + Barre de recherche instantanée -->
                    <div class="flex items-center justify-between gap-2 mb-1.5 flex-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900 truncate">
                            {{ sel ? 'Unités de la région' : 'Classement : ' + METRICS[metric].label }}
                        </h3>

                        <!-- Champ filtre rapide -->
                        <div class="relative w-36 sm:w-44">
                            <i class="pi pi-search absolute left-2 top-2 text-[11px] text-slate-400"></i>
                            <input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="sel ? 'Filtrer unités...' : 'Filtrer régions...'"
                                class="w-full pl-6 pr-6 py-1 text-xs border border-slate-200 rounded-md focus:outline-none focus:border-sky-500 bg-white placeholder:text-slate-400"
                            />
                            <button
                                v-if="searchQuery"
                                @click="searchQuery = ''"
                                class="absolute right-1.5 top-1.5 text-slate-400 hover:text-slate-600 text-xs"
                                aria-label="Effacer recherche"
                            >
                                <i class="pi pi-times"></i>
                            </button>
                        </div>
                    </div>

                    <!-- LISTE DÉROULANTE INTERNE (SEUL LE FOND DES RÉGIONS / UNITÉS EST SCROLLABLE) -->
                    <div class="flex-1 min-h-0 overflow-y-auto pr-1 custom-scrollbar">
                        <ul v-if="lignesFiltrees.length > 0" class="rank">
                            <li v-for="row in lignesFiltrees" :key="row.key">
                                <button
                                    :class="{ sel: row.active }"
                                    @click="row.click"
                                    :title="sel ? row.nom : 'Cliquez pour isoler ' + row.nom"
                                >
                                    <span class="n">
                                        <i
                                            v-if="sel"
                                            class="dot"
                                            :style="{ background: row.op ? '#1f7a4d' : '#b4540a' }"
                                        ></i>
                                        <span class="truncate">{{ row.nom }}</span>
                                        <small v-if="row.horsCarte" class="ml-1 text-amber-700 text-[10px]" title="Pas de coordonnées GPS">
                                            (sans GPS)
                                        </small>
                                    </span>
                                    <span class="t">
                                        <i :style="{ width: Math.max(3, row.pct) + '%' }"></i>
                                    </span>
                                    <span class="v">
                                        {{ fmt(row.value) }}{{ sel ? '' : METRICS[metric].unit }}
                                    </span>
                                </button>
                            </li>
                        </ul>
                        <div v-else class="py-8 text-center text-xs text-slate-400">
                            Aucun élément ne correspond à « {{ searchQuery }} »
                        </div>
                    </div>

                    <!-- Guide d'interaction en pied de volet -->
                    <p class="text-[11px] text-slate-400 mt-1.5 pt-1.5 border-t border-slate-100 flex-none truncate">
                        {{ sel
                            ? 'Cliquez sur une unité pour la pointer sur la carte. Le zoom s\'adapte aux limites de la région.'
                            : 'Cliquez sur une région pour zoomer et afficher le détail de ses unités opérationnelles.' }}
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { MALI_VIEW, REGION_SHAPES as SHAPES, project } from './maliRegions.js';

const props = defineProps({
    regions: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ non_rattaches: 0, generated_at: '' }) },
});

const METRICS = {
    eff: { label: 'Effectif', unit: '' },
    pres: { label: 'Présents', unit: '' },
    abs: { label: 'Absents', unit: '' },
    fem: { label: 'Femmes', unit: '' },
    opp: { label: 'Unités op.', unit: ' %' },
};

const metric = ref('eff');
const sel = ref(null);         // code de la région choisie (ex. "R4") ou null = Mali entier
const selUnit = ref(null);     // id de l'unité mise en évidence
const searchQuery = ref('');   // recherche rapide dans le volet déroulant
const hoveredItem = ref(null); // objet survolé (région ou unité)
const hoveredCode = ref(null); // code de la région survolée

const fmt = (n) => Number(n ?? 0).toLocaleString('fr-FR');
const shortName = (n) => n.replace(/^(Région de |Région du |District de )/, '');

// ---------- Valeurs par région ----------
const opCount = (r) => r.unites.filter((u) => u.operationnelle).length;
const valueOf = (r) => {
    switch (metric.value) {
        case 'pres': return r.effectif - r.absents;
        case 'abs': return r.absents;
        case 'fem': return r.femmes;
        case 'opp': return r.unites.length ? Math.round((opCount(r) / r.unites.length) * 100) : 0;
        default: return r.effectif;
    }
};

const legende = computed(() => {
    const v = props.regions.map(valueOf);
    return v.length ? { min: Math.min(...v), max: Math.max(...v) } : { min: 0, max: 0 };
});

const fillFor = (r) => {
    let pct = 8;
    if (!sel.value) {
        const { min, max } = legende.value;
        pct = Math.round(12 + (max === min ? 1 : (valueOf(r) - min) / (max - min)) * 88);
    } else if (r.code === sel.value) {
        pct = 18;
    }
    return `color-mix(in srgb, #1d4f91 ${pct}%, #e4ebf3)`;
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
        op: u.operationnelle,
        title: u.nom,
        subtitle: `${fmt(u.effectif)} militaires`,
        extra: u.operationnelle ? 'Opérationnelle' : 'Non opérationnelle'
    };
};

const onLeaveHover = () => {
    hoveredItem.value = null;
    hoveredCode.value = null;
};

const hoveredInfo = computed(() => hoveredItem.value);

// ---------- Sélection / zoom ----------
const selRegion = computed(() => props.regions.find((r) => r.code === sel.value));

const choisir = (code) => {
    sel.value = code;
    selUnit.value = null;
    searchQuery.value = '';
};

const retour = () => {
    sel.value = null;
    selUnit.value = null;
    searchQuery.value = '';
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

const viewBox = computed(() => {
    const full = [0, 0, MALI_VIEW.w, MALI_VIEW.h];
    const shape = sel.value && SHAPES[sel.value];
    if (!shape) return full;
    const [a, b, c, d] = shape.bb;
    const pw = Math.max(c - a, 32), ph = Math.max(d - b, 32), pad = Math.max(pw, ph) * 0.15;
    let w = pw + 2 * pad, h = ph + 2 * pad;
    const ar = MALI_VIEW.w / MALI_VIEW.h;
    if (w / h < ar) w = h * ar; else h = w / ar;
    return [(a + c) / 2 - w / 2, (b + d) / 2 - h / 2, w, h];
});

const zoom = computed(() => viewBox.value[2] / MALI_VIEW.w); // garde les traits et pins lisibles à l'échelle du zoom

// ---------- Unités de la région choisie ----------
const unitesAvecPoint = computed(() => {
    if (!selRegion.value) return [];
    return selRegion.value.unites
        .filter((u) => u.lat !== null && u.lon !== null)
        .map((u) => ({ ...u, ...project(u.lat, u.lon) }));
});

const unitesSansPoint = computed(() => (selRegion.value ? selRegion.value.unites.length - unitesAvecPoint.value.length : 0));
const maxEff = computed(() => Math.max(1, ...(selRegion.value?.unites ?? []).map((u) => u.effectif)));
const rayon = (u) => (3.5 + (u.effectif / maxEff.value) * 5) * zoom.value;
const unitePointee = computed(() => unitesAvecPoint.value.find((u) => u.id === selUnit.value));

// ---------- Panneaux statistiques ----------
const tot = (f) => props.regions.reduce((s, r) => s + f(r), 0);

const kpis = computed(() => {
    const r = selRegion.value;
    const eff = r ? r.effectif : tot((x) => x.effectif);
    const abs = r ? r.absents : tot((x) => x.absents);
    const nU = r ? r.unites.length : tot((x) => x.unites.length);
    const nOp = r ? opCount(r) : tot(opCount);
    return [
        {
            label: r ? 'Effectif de la région' : 'Effectif total',
            value: fmt(eff),
            hint: r ? r.nom : 'hors retraités et radiés'
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
            value: `${nOp} / ${nU}`,
            hint: nU ? Math.round((nOp / nU) * 100) + ' % maillage' : '—'
        },
    ];
});

const detail = computed(() => {
    const r = selRegion.value;
    if (!r) {
        return [
            { label: 'Régions', value: props.regions.length },
            { label: 'Total Unités', value: fmt(tot((x) => x.unites.length)) },
            { label: 'Effectif Global', value: fmt(tot((x) => x.effectif)) },
        ];
    }
    const total = tot((x) => x.effectif);
    return [
        { label: 'Effectif', value: fmt(r.effectif) },
        { label: 'Part nationale', value: (total ? (r.effectif / total) * 100 : 0).toFixed(1).replace('.', ',') + ' %' },
        { label: 'Femmes', value: fmt(r.femmes) },
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
            active: false,
            click: () => choisir(r.code),
        }))
        .sort((a, b) => b.value - a.value);
    const hi = rows[0]?.value || 1;
    return rows.map((x) => ({ ...x, pct: (x.value / hi) * 100 }));
});

const lignesFiltrees = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return lignes.value;
    return lignes.value.filter((row) => {
        const text = (row.nom + ' ' + (row.fullName || '') + ' ' + (row.code || '')).toLowerCase();
        return text.includes(q);
    });
});
</script>

<style scoped>
.chip {
    font-size: 12px;
    border: 1px solid #dde3ea;
    border-radius: 9999px;
    padding: 3px 10px;
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
    stroke: #ffffff;
    cursor: pointer;
    transition: opacity 0.15s, stroke-width 0.15s;
}
.reg:hover,
.reg.hovered {
    opacity: 0.85;
    stroke: #0284c7;
}
.reg.sel {
    stroke: #0f172a;
    stroke-width: 2.2;
}
.reg.dim {
    opacity: 0.45;
}

.pin {
    stroke: #ffffff;
    cursor: pointer;
    transition: r 0.15s, stroke 0.15s;
}
.pin:hover {
    stroke: #0284c7;
}
.pin.on {
    stroke: #0f172a;
}

.lbl {
    font: 600 11px system-ui, -apple-system, sans-serif;
    fill: #0f172a;
    paint-order: stroke;
    stroke: #ffffff;
    stroke-width: 3px;
    stroke-linejoin: round;
    pointer-events: none;
    text-anchor: middle;
}
.lbl.nm {
    font-size: 8.5px;
    font-weight: 500;
    stroke-width: 2.5px;
}
.lbl.text-highlight {
    fill: #1e3a8a;
    font-weight: 700;
}

.lead {
    stroke: #475569;
    stroke-width: 1;
    pointer-events: none;
}
.dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 5px;
    flex: none;
}
.legend-bar {
    width: 140px;
    max-width: 35%;
    height: 7px;
    border-radius: 4px;
    background: linear-gradient(90deg, color-mix(in srgb, #1d4f91 12%, #e4ebf3), #1d4f91);
}

.rank {
    display: flex;
    flex-direction: column;
    gap: 3px;
    margin: 0;
    padding: 0;
    list-style: none;
}
.rank button {
    all: unset;
    box-sizing: border-box;
    display: grid;
    grid-template-columns: minmax(110px, 1.3fr) 1fr 50px;
    gap: 8px;
    align-items: center;
    width: 100%;
    padding: 4px 6px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    transition: background 0.12s ease;
}
.rank button:hover,
.rank button.sel {
    background: #f1f5f9;
}
.rank button.sel {
    border-left: 3px solid #1d4f91;
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
    height: 8px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
}
.rank .t i {
    display: block;
    height: 100%;
    background: #1d4f91;
    border-radius: 4px;
}
.rank .v {
    text-align: right;
    font-weight: 600;
    font-size: 12px;
    font-variant-numeric: tabular-nums;
    color: #0f172a;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f8fafc;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
