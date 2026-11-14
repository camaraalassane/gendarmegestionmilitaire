<template>
    <AuthenticatedLayout>
        <Head title="Carte Opérationnelle du Mali" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-amber-400/20 border border-amber-400/40 flex items-center justify-center text-amber-300">
                        <i class="pi pi-map text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-base font-bold text-white leading-tight">
                            Carte Opérationnelle du Mali
                        </h1>
                        <p class="text-[10px] text-sky-200">
                            {{ regions.length }} régions • Maillage territorial souverain
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
        <div class="h-[calc(100vh-4.75rem)] flex flex-col gap-2 overflow-hidden -m-1 sm:-m-2">
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

            <!-- Alerte non rattachés si existants -->
            <div
                v-if="stats.non_rattaches > 0"
                class="rounded-lg border border-amber-300 bg-amber-50 text-amber-900 text-xs px-2.5 py-1 flex items-center justify-between flex-none"
            >
                <div class="flex items-center gap-1.5">
                    <i class="pi pi-exclamation-triangle text-amber-600 text-xs"></i>
                    <span>
                        <strong>{{ fmt(stats.non_rattaches) }}</strong> militaires non rattachés à une région/unité.
                    </span>
                </div>
            </div>

            <!-- 2. Zone principale : Cadre blanc standard, SEUL LE CANEVAS DE LA CARTE EST EN BLEU ARDOISE (#16212e) -->
            <div class="flex-1 min-h-0 grid gap-2 lg:grid-cols-[1fr_320px] xl:grid-cols-[1fr_340px] items-stretch">
                <!-- PANNEAU CARTE (Cadre blanc standard, en-tête clair, et uniquement l'endroit de la carte en fond sombre #16212e) -->
                <div class="bg-white rounded-lg border border-slate-200 p-2.5 min-w-0 flex flex-col h-full overflow-hidden shadow-sm">
                    <!-- En-tête de la carte CLAIR (comme demandé : "même l'en-tête faut laisser") -->
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5 flex-none">
                        <div class="flex items-center gap-1.5 flex-wrap">
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
                        </div>

                        <!-- Menu déroulant pour sélectionner directement une région -->
                        <div class="flex items-center gap-1.5">
                            <select
                                :value="sel || ''"
                                @change="onSelectRegionDropdown($event.target.value)"
                                class="text-xs border border-slate-200 rounded-lg px-2 py-0.5 bg-white text-slate-700 focus:outline-none focus:border-sky-500 font-medium"
                                aria-label="Choisir une région"
                            >
                                <option value="">Tout le Mali (20 régions)</option>
                                <option v-for="r in regions" :key="r.code" :value="r.code">
                                    {{ r.code }} - {{ r.nom }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- UNIQUEMENT CET ENDROIT : LE CANEVAS DE LA CARTE EN BLEU ARDOISE ADOUCI (#16212E) -->
                    <div class="flex-1 min-h-0 w-full h-full relative flex items-center justify-center overflow-hidden bg-[#16212e] rounded-lg border border-[#263446] p-1">
                        <!-- Entête au survol interactif (HUD élégant) -->
                        <transition name="fade">
                            <div
                                v-if="hoveredInfo"
                                class="absolute top-2 left-2 z-20 bg-[#0e1621]/95 backdrop-blur text-white px-2.5 py-1 rounded-md shadow-lg text-xs pointer-events-none flex items-center gap-2 border border-slate-700"
                            >
                                <span
                                    class="w-2 h-2 rounded-full flex-none"
                                    :style="{ background: hoveredInfo.isUnit ? (hoveredInfo.op ? '#10b981' : '#f59e0b') : '#5f9bea' }"
                                ></span>
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-white">{{ hoveredInfo.title }}</span>
                                    <span class="text-slate-400">•</span>
                                    <span class="text-sky-300 font-medium">{{ hoveredInfo.subtitle }}</span>
                                    <span v-if="hoveredInfo.extra" class="text-slate-400 text-[10px]">({{ hoveredInfo.extra }})</span>
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
                            <defs>
                                <filter id="pin-shadow" x="-30%" y="-30%" width="160%" height="160%">
                                    <feDropShadow dx="0" dy="0.8" stdDeviation="0.8" flood-opacity="0.5" flood-color="#000000" />
                                </filter>
                            </defs>

                            <!-- Tracés des régions SVG (Nuances tactiques de bleu marine comme sur la photo ; sans infobulle native redondante) -->
                            <template v-for="r in regions" :key="r.code">
                                <path
                                    v-if="SHAPES[r.code] && (!sel ? (r.code !== 'R1') : (r.code === sel))"
                                    :d="SHAPES[r.code].d"
                                    class="reg"
                                    :class="{
                                        sel: r.code === sel,
                                        hovered: hoveredCode === r.code
                                    }"
                                    :style="{
                                        fill: fillFor(r),
                                        stroke: sel === r.code ? '#38bdf8' : '#16212e',
                                        strokeWidth: (sel === r.code ? 2.2 : 1.2) * zoom
                                    }"
                                    @click="choisir(r.code)"
                                    @mouseenter="onHoverRegion(r)"
                                    @mouseleave="onLeaveHover"
                                />
                            </template>

                            <!-- Vue nationale : étiquettes noms + chiffres blancs nets avec halo sombre -->
                            <template v-if="!sel">
                                <template v-for="r in regions" :key="'l' + r.code">
                                    <g v-if="SHAPES[r.code] && r.code !== 'R1'" class="lbl-g pointer-events-none">
                                        <text class="lbl nm" :x="SHAPES[r.code].cx" :y="SHAPES[r.code].cy - 2">
                                            {{ shortName(r.nom) }}
                                        </text>
                                        <text class="lbl num" :x="SHAPES[r.code].cx" :y="SHAPES[r.code].cy + 11">
                                            {{ fmt(valueOf(r)) }}
                                        </text>
                                    </g>
                                </template>

                                <!-- District de Bamako (R1) : cercle bleu + trait guide + étiquette blanche -->
                                <template v-for="r in regions.filter(x => x.code === 'R1' && SHAPES.R1)" :key="'b'">
                                    <circle
                                        class="reg"
                                        :cx="SHAPES.R1.cx"
                                        :cy="SHAPES.R1.cy"
                                        r="6"
                                        style="fill: #5f9bea; stroke: #ffffff; stroke-width: 1.2px;"
                                        @click="choisir('R1')"
                                        @mouseenter="onHoverRegion(r)"
                                        @mouseleave="onLeaveHover"
                                    />
                                    <line
                                        class="lead"
                                        :x1="SHAPES.R1.cx"
                                        :y1="SHAPES.R1.cy"
                                        :x2="SHAPES.R1.cx - 26"
                                        :y2="SHAPES.R1.cy + 30"
                                        style="stroke: #5f9bea; stroke-width: 1.2px;"
                                    />
                                    <text class="lbl num" :x="SHAPES.R1.cx - 26" :y="SHAPES.R1.cy + 42">
                                        Bamako {{ fmt(valueOf(r)) }}
                                    </text>
                                </template>
                            </template>

                            <!-- Région sélectionnée : PINS TACTIQUES HAUTE VISIBILITÉ DES UNITÉS -->
                            <template v-else>
                                <g
                                    v-for="u in unitesAvecPoint"
                                    :key="u.id"
                                    class="unit-marker cursor-pointer"
                                    @click="toggleUnit(u.id)"
                                    @mouseenter="onHoverUnit(u)"
                                    @mouseleave="onLeaveHover"
                                >
                                    <!-- Anneau de sélection / pulsation cyan si actif -->
                                    <circle
                                        v-if="selUnit === u.id"
                                        :cx="u.x"
                                        :cy="u.y"
                                        :r="rayon(u) * 2.3"
                                        fill="none"
                                        stroke="#38bdf8"
                                        :stroke-width="2 * zoom"
                                        opacity="0.9"
                                    />

                                    <!-- Disque de contour extérieur avec ombre -->
                                    <circle
                                        :cx="u.x"
                                        :cy="u.y"
                                        :r="rayon(u) * (selUnit === u.id ? 1.5 : 1)"
                                        fill="#ffffff"
                                        :stroke="selUnit === u.id ? '#0f172a' : (u.operationnelle ? '#065f46' : '#9a3412')"
                                        :stroke-width="1.8 * zoom"
                                        filter="url(#pin-shadow)"
                                    />

                                    <!-- Cœur coloré vif (Vert émeraude = opérationnelle, Orange vif = non op) -->
                                    <circle
                                        :cx="u.x"
                                        :cy="u.y"
                                        :r="rayon(u) * (selUnit === u.id ? 1.1 : 0.72)"
                                        :fill="u.operationnelle ? '#10b981' : '#f59e0b'"
                                    />

                                    <!-- Pip central blanc -->
                                    <circle
                                        :cx="u.x"
                                        :cy="u.y"
                                        :r="rayon(u) * 0.28"
                                        fill="#ffffff"
                                    />
                                </g>

                                <!-- Libellé de l'unité sélectionnée ou survolée -->
                                <template v-if="uniteActivePourLabel">
                                    <text
                                        class="lbl text-highlight"
                                        :style="{ fontSize: 12.5 * zoom + 'px', strokeWidth: 3.5 * zoom + 'px' }"
                                        :x="uniteActivePourLabel.x"
                                        :y="uniteActivePourLabel.y - rayon(uniteActivePourLabel) * 1.7"
                                    >
                                        {{ uniteActivePourLabel.nom }}
                                    </text>
                                </template>
                            </template>
                        </svg>
                    </div>

                    <!-- Barre de sélection rapide de régions au bas de la carte (accès en 1 clic) CLAIRE -->
                    <div class="flex items-center gap-1 overflow-x-auto py-1 px-1 mt-1 bg-slate-50/90 rounded-md border border-slate-200 text-[11px] flex-none custom-scrollbar">
                        <button
                            class="px-2 py-0.5 rounded font-semibold transition-all flex items-center gap-1 flex-none"
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
                            class="px-1.5 py-0.5 rounded font-medium transition-all flex items-center gap-1 flex-none"
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
                        <template v-if="sel">
                            <div class="flex items-center gap-3">
                                <span class="flex items-center font-medium text-emerald-800">
                                    <i class="dot" style="background:#10b981"></i>Opérationnelle
                                </span>
                                <span class="flex items-center font-medium text-amber-800">
                                    <i class="dot" style="background:#f59e0b"></i>Non opérationnelle
                                </span>
                                <span class="text-slate-400">• Rayon = effectif</span>
                            </div>
                            <span v-if="unitesSansPoint > 0" class="text-amber-700 font-semibold">
                                • {{ unitesSansPoint }} unité(s) sans GPS
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

                <!-- PANNEAU DROIT CLAIR / BLANC (comme demandé : "pas les autres cards") -->
                <div class="bg-white rounded-lg border border-slate-200 p-2.5 min-w-0 flex flex-col h-full overflow-hidden shadow-sm text-slate-900">
                    <!-- Entête du panneau -->
                    <div class="flex items-center justify-between gap-2 flex-none">
                        <div class="min-w-0">
                            <h2 class="text-sm font-bold text-slate-900 truncate">
                                {{ sel ? selRegion.nom : 'Vue Nationale (Mali)' }}
                            </h2>
                            <p v-if="sel && selRegion.chef_lieu" class="text-[11px] text-slate-500 truncate">
                                Chef-lieu : {{ selRegion.chef_lieu }}
                            </p>
                        </div>
                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200 flex-none">
                            {{ sel ? selRegion.unites.length + ' unités' : regions.length + ' régions' }}
                        </span>
                    </div>

                    <!-- Grille métriques compacte claire -->
                    <div class="grid grid-cols-3 gap-1.5 my-1.5 p-1.5 border border-slate-100 flex-none bg-slate-50/80 rounded-md text-center">
                        <div v-for="d in detail" :key="d.label" class="min-w-0">
                            <div class="text-[10px] text-slate-500 truncate">{{ d.label }}</div>
                            <div class="text-xs sm:text-sm font-bold text-slate-900 tabular-nums truncate">
                                {{ d.value }}
                            </div>
                        </div>
                    </div>

                    <!-- Titre de liste + Barre de recherche instantanée -->
                    <div class="flex items-center justify-between gap-1.5 mb-1 flex-none">
                        <h3 class="text-xs font-bold text-slate-900 truncate">
                            {{ sel ? 'Unités de la région' : 'Classement : ' + METRICS[metric].label }}
                        </h3>
                        <div class="relative w-36 sm:w-44">
                            <input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="sel ? 'Filtrer brigades...' : 'Filtrer régions...'"
                                class="w-full text-[11px] py-1 pl-6 pr-2 rounded border border-slate-200 bg-white placeholder-slate-400 focus:outline-none focus:border-sky-500"
                            />
                            <i class="pi pi-search absolute left-2 top-2 text-[10px] text-slate-400"></i>
                            <button
                                v-if="searchQuery"
                                @click="searchQuery = ''"
                                class="absolute right-1.5 top-1.5 text-slate-400 hover:text-slate-600 text-[10px]"
                                title="Effacer"
                            >
                                <i class="pi pi-times"></i>
                            </button>
                        </div>
                    </div>

                    <!-- LISTE DÉROULANTE INTERNE (SEUL CE BLOC DÉFILE) -->
                    <div class="flex-1 min-h-0 overflow-y-auto pr-1 custom-scrollbar">
                        <ul v-if="lignesFiltrees.length > 0" class="rank">
                            <li v-for="row in lignesFiltrees" :key="row.key">
                                <button
                                    :class="{ sel: row.active }"
                                    @click="row.click"
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
                            </li>
                        </ul>
                        <div v-else class="py-6 text-center text-xs text-slate-400">
                            Aucun élément pour « {{ searchQuery }} »
                        </div>
                    </div>

                    <!-- Guide d'interaction en bas -->
                    <p class="text-[10px] text-slate-400 mt-1 pt-1 border-t border-slate-100 flex-none truncate">
                        {{ sel
                            ? 'Cliquez sur une unité pour la cibler sur la carte.'
                            : 'Cliquez sur une région pour zoomer et afficher ses unités.' }}
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
    regionInitiale: { type: String, default: null },
});

const METRICS = {
    eff: { label: 'Effectif', unit: '' },
    pres: { label: 'Présents', unit: '' },
    abs: { label: 'Absents', unit: '' },
    fem: { label: 'Femmes', unit: '' },
    opp: { label: 'Unités op.', unit: ' %' },
};

const metric = ref('eff');
const sel = ref(props.regionInitiale && props.regions.some(r => r.code === props.regionInitiale) ? props.regionInitiale : null); // code de la région choisie ou null = Mali entier
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
    const pw = Math.max(c - a, 28), ph = Math.max(d - b, 28);
    const pad = Math.max(pw, ph) * 0.16; // 16% de marge élégante
    let w = pw + 2 * pad, h = ph + 2 * pad;
    const ar = MALI_VIEW.w / MALI_VIEW.h;
    if (w / h < ar) w = h * ar; else h = w / ar;
    return [(a + c) / 2 - w / 2, (b + d) / 2 - h / 2, w, h];
});

const zoom = computed(() => viewBox.value[2] / MALI_VIEW.w); // garde les traits et pins visibles à toute échelle

// ---------- Unités de la région choisie avec dispersion intelligente en grappes tactiques ----------
const unitesAvecPoint = computed(() => {
    if (!selRegion.value) return [];
    const units = selRegion.value.unites.filter((u) => u.lat !== null && u.lon !== null);
    if (!units.length) return [];

    const pts = units.map((u) => {
        const p = project(Number(u.lat), Number(u.lon));
        return { ...u, origX: p.x, origY: p.y };
    });

    const baseR = 5.2 * zoom.value;
    const proximityThreshold = baseR * 2.8;

    const clusters = [];
    pts.forEach((pt) => {
        let targetCluster = null;
        for (const cl of clusters) {
            const dx = pt.origX - cl.cx;
            const dy = pt.origY - cl.cy;
            if (Math.hypot(dx, dy) < proximityThreshold) {
                targetCluster = cl;
                break;
            }
        }
        if (targetCluster) {
            targetCluster.items.push(pt);
            targetCluster.cx = targetCluster.items.reduce((s, it) => s + it.origX, 0) / targetCluster.items.length;
            targetCluster.cy = targetCluster.items.reduce((s, it) => s + it.origY, 0) / targetCluster.items.length;
        } else {
            clusters.push({
                cx: pt.origX,
                cy: pt.origY,
                items: [pt]
            });
        }
    });

    const result = [];
    clusters.forEach((cl) => {
        const count = cl.items.length;
        if (count === 1) {
            result.push({
                ...cl.items[0],
                x: cl.items[0].origX,
                y: cl.items[0].origY,
            });
        } else {
            let itemIdx = 0;
            let ring = 1;
            const ringSpacing = baseR * 2.3;

            while (itemIdx < count) {
                const ringCap = ring === 1 ? Math.min(6, count) : ring * 6;
                const countInRing = Math.min(ringCap, count - itemIdx);
                const dist = ring * ringSpacing;
                const offset = (ring % 2 === 0) ? Math.PI / countInRing : 0;

                for (let k = 0; k < countInRing; k++) {
                    const u = cl.items[itemIdx++];
                    const angle = (k * 2 * Math.PI / countInRing) + offset;
                    result.push({
                        ...u,
                        x: cl.cx + Math.cos(angle) * dist,
                        y: cl.cy + Math.sin(angle) * dist,
                    });
                }
                ring++;
            }
        }
    });

    return result;
});

const unitesSansPoint = computed(() => (selRegion.value ? selRegion.value.unites.length - unitesAvecPoint.value.length : 0));
const maxEff = computed(() => Math.max(1, ...(selRegion.value?.unites ?? []).map((u) => u.effectif)));

const rayon = (u) => {
    const base = 5.2;
    const add = maxEff.value > 0 ? (u.effectif / maxEff.value) * 4 : 0;
    return (base + add) * zoom.value;
};

const unitePointee = computed(() => unitesAvecPoint.value.find((u) => u.id === selUnit.value));
const hoveredUnitObj = computed(() => {
    if (hoveredItem.value?.isUnit && hoveredItem.value?.uId) {
        return unitesAvecPoint.value.find((u) => u.id === hoveredItem.value.uId);
    }
    return null;
});
const uniteActivePourLabel = computed(() => unitePointee.value || hoveredUnitObj.value);

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
            label: r ? 'Effectif région' : 'Effectif total',
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
            hint: 'congés, missions'
        },
        {
            label: 'Unités opérationnelles',
            value: `${fmt(nOp)} / ${fmt(nU)}`,
            hint: nU ? Math.round((nOp / nU) * 100) + ' % maillage' : '—'
        },
    ];
});

const detail = computed(() => {
    if (!selRegion.value) {
        return [
            { label: 'Régions', value: props.regions.length },
            { label: 'Total Unités', value: tot((r) => r.unites.length) },
            { label: 'Effectif Global', value: fmt(tot((r) => r.effectif)) },
        ];
    }
    const r = selRegion.value;
    const total = tot((x) => x.effectif);
    return [
        { label: 'Total Unités', value: r.unites.length },
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
            rawRegion: r,
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
    grid-template-columns: minmax(105px, 1.25fr) 1fr 45px;
    gap: 6px;
    align-items: center;
    width: 100%;
    padding: 3.5px 5px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 12px;
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
    height: 6px;
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
    font-weight: 600;
    font-size: 11.5px;
    font-variant-numeric: tabular-nums;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 5px;
    height: 5px;
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
