<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h1 class="text-xl font-bold text-white flex items-center gap-2">
                        <i class="pi pi-map text-amber-400"></i>
                        Carte Opérationnelle du Mali
                    </h1>
                    <p class="text-xs text-sky-200">
                        Territoire national souverain du Mali • Situation tactique et maillage territorial en direct
                    </p>
                </div>

                <!-- Badges rapides -->
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="bg-white/10 backdrop-blur-sm px-3 py-1 rounded-lg border border-white/20 text-white text-xs">
                        <span class="text-sky-200">Militaires :</span>
                        <strong class="ml-1 text-amber-300">{{ statsGlobales.total_militaires }}</strong>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm px-3 py-1 rounded-lg border border-white/20 text-white text-xs">
                        <span class="text-sky-200">Unités op. :</span>
                        <strong class="ml-1 text-emerald-300">{{ statsGlobales.unites_operationnelles }} / {{ statsGlobales.total_unites }}</strong>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm px-3 py-1 rounded-lg border border-white/20 text-white text-xs">
                        <span class="text-sky-200">Régions :</span>
                        <strong class="ml-1 text-white">{{ statsGlobales.total_regions }}</strong>
                    </div>
                </div>
            </div>
        </template>

        <div class="space-y-2.5">
            <!-- Barre d'action supérieure compacte et épurée -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 px-3 py-2 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <!-- Statut vue actuelle -->
                <div class="flex items-center gap-2 text-xs">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="font-bold text-slate-800">RÉPUBLIQUE DU MALI</span>
                    <span v-if="selectedRegion" class="bg-amber-100 text-amber-900 font-bold px-2 py-0.5 rounded text-[11px] border border-amber-300 flex items-center gap-1">
                        <span>📍 Vue isolée : {{ selectedRegion.nom }} ({{ selectedRegion.code }})</span>
                    </span>
                    <span v-else class="text-slate-500 text-[11px] hidden md:inline">
                        • Vue nationale (20 Régions • Seul le Mali est affiché)
                    </span>
                </div>

                <!-- Boutons d'action -->
                <div class="flex items-center gap-2 text-xs">
                    <button
                        @click="resetToFullMaliView"
                        :class="[
                            'px-3 py-1 rounded-lg font-bold border transition-all flex items-center gap-1.5 shadow-2xs text-xs',
                            selectedRegion !== null
                                ? 'bg-amber-500 hover:bg-amber-600 text-slate-950 border-amber-600 ring-2 ring-amber-300'
                                : 'bg-sky-50 hover:bg-sky-100 text-sky-800 border-sky-200'
                        ]"
                        title="Afficher tout le Mali (vue générale complète)"
                    >
                        <i :class="selectedRegion ? 'pi pi-arrow-left text-xs' : 'pi pi-globe text-xs'"></i>
                        <span>{{ selectedRegion ? '← Afficher tout le Mali' : 'Recentrer le Mali' }}</span>
                    </button>

                    <button
                        @click="toggleSidebar"
                        class="px-2.5 py-1 text-xs rounded-lg border border-slate-200 bg-slate-50 hover:bg-white text-slate-700 font-medium transition-all flex items-center gap-1.5 shadow-2xs hover:border-slate-300"
                        :title="isSidebarOpen ? 'Agrandir la carte au maximum' : 'Afficher le volet latéral'"
                    >
                        <i :class="isSidebarOpen ? 'pi pi-arrows-alt text-sky-700' : 'pi pi-list text-sky-700'"></i>
                        <span>{{ isSidebarOpen ? 'Agrandir carte' : 'Volet Régions' }}</span>
                    </button>
                </div>
            </div>

            <!-- Grille principale : Carte (pleine hauteur) + Volet latéral -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
                <!-- Zone Carte : 8 ou 9 colonnes (ou 12 si volet fermé) -->
                <div :class="[
                    isSidebarOpen ? 'lg:col-span-8 xl:col-span-9' : 'col-span-12',
                    'bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden flex flex-col relative transition-all duration-300',
                    'h-[calc(100vh-175px)] min-h-[660px]'
                ]">
                    <!-- Carte Leaflet -->
                    <div id="map-mali" class="w-full h-full z-0 bg-slate-100"></div>

                    <!-- Bouton flottant de retour en haut à gauche si région isolée -->
                    <div v-if="selectedRegion" class="absolute top-3 left-3 z-[400] flex items-center gap-2">
                        <button
                            @click="resetToFullMaliView"
                            class="bg-sky-950/90 hover:bg-sky-900 text-white px-3 py-1.5 rounded-lg shadow-xl font-bold text-xs flex items-center gap-1.5 border border-sky-500 backdrop-blur-sm transition-all"
                            title="Quitter la vue isolée et réafficher tout le Mali"
                        >
                            <i class="pi pi-arrow-left text-amber-300 text-xs"></i>
                            <span>Tout le Mali</span>
                        </button>
                        <div class="bg-white/95 backdrop-blur-sm text-sky-950 font-bold px-3 py-1.5 rounded-lg shadow-md border border-slate-200 text-xs flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                            <span>{{ selectedRegion.nom }} • Chef-lieu : {{ selectedRegion.chef_lieu }}</span>
                        </div>
                    </div>

                    <!-- Bouton flottant réouvrir volet si masqué -->
                    <button
                        v-if="!isSidebarOpen"
                        @click="toggleSidebar"
                        class="absolute top-3 right-3 z-[400] bg-white/95 hover:bg-white text-sky-900 font-bold px-3 py-1.5 rounded-lg shadow-xl text-xs flex items-center gap-1.5 border border-sky-300 backdrop-blur-sm transition-all"
                    >
                        <i class="pi pi-list text-sky-700"></i>
                        <span>Volet Régions (20)</span>
                    </button>

                    <!-- Barre de sélection rapide au bas de la carte -->
                    <div class="absolute bottom-3 left-3 right-3 z-[400] pointer-events-none">
                        <div class="bg-white/95 backdrop-blur-md px-2.5 py-1.5 rounded-xl border border-slate-200/90 shadow-xl pointer-events-auto overflow-x-auto no-scrollbar flex items-center gap-1.5">
                            <!-- Bouton Tout le Mali -->
                            <button
                                @click="resetToFullMaliView"
                                :class="[
                                    'px-3 py-1 text-xs rounded-lg font-bold whitespace-nowrap transition-all shrink-0 border flex items-center gap-1.5',
                                    selectedRegion === null
                                        ? 'bg-sky-900 text-white border-sky-950 shadow-xs ring-2 ring-sky-300'
                                        : 'bg-white text-slate-700 border-slate-200 hover:bg-sky-50 hover:text-sky-800'
                                ]"
                            >
                                <i class="pi pi-globe text-xs"></i>
                                <span>Tout le Mali</span>
                            </button>

                            <span class="text-slate-300 px-0.5">|</span>

                            <!-- Boutons de pointage direct pour chaque région R1 à R20 -->
                            <button
                                v-for="reg in regionsTerritoriales"
                                :key="reg.id"
                                @click="selectAndFocusRegion(reg)"
                                :class="[
                                    'px-2 py-0.5 text-xs rounded-md font-semibold whitespace-nowrap transition-all shrink-0 border',
                                    selectedRegion?.id === reg.id
                                        ? 'bg-amber-500 text-slate-950 border-amber-600 shadow-xs ring-2 ring-amber-300 font-bold'
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-sky-50 hover:text-sky-700'
                                ]"
                            >
                                📍 {{ reg.code }} <span class="text-[10px] opacity-75 font-normal">({{ reg.stats?.total || 0 }})</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Volet latéral : Léger, moderne et bien organisé -->
                <div v-show="isSidebarOpen" class="lg:col-span-4 xl:col-span-3 flex flex-col h-[calc(100vh-175px)] min-h-[660px]">
                    <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden flex flex-col h-full">
                        <!-- En-tête du volet -->
                        <div class="p-3 border-b border-slate-200/80 bg-slate-50/70 flex items-center justify-between shrink-0">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-map text-sky-700 text-sm"></i>
                                <span class="text-xs font-bold text-slate-800">
                                    {{ selectedRegion ? `Détail : ${selectedRegion.nom}` : `Régions du Mali` }}
                                </span>
                                <span v-if="!selectedRegion" class="text-[10px] font-semibold bg-sky-100 text-sky-800 px-1.5 py-0.2 rounded-full">
                                    {{ regionsTerritoriales.length }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1">
                                <button
                                    v-if="selectedRegion"
                                    @click="resetToFullMaliView"
                                    class="text-[11px] font-bold text-sky-700 hover:text-sky-900 hover:underline flex items-center gap-1 mr-1"
                                >
                                    <i class="pi pi-arrow-left text-[10px]"></i>
                                    <span>Toutes</span>
                                </button>
                                <button
                                    @click="toggleSidebar"
                                    class="text-slate-400 hover:text-slate-600 text-xs p-1"
                                    title="Fermer le volet"
                                >
                                    <i class="pi pi-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Corps du volet -->
                        <div class="flex-1 overflow-hidden flex flex-col">
                            <!-- CAS 1 : UNE RÉGION EST SÉLECTIONNÉE (AFFICHE SES UNITÉS ET SES STATS) -->
                            <div v-if="selectedRegion" class="flex flex-col flex-1 overflow-hidden p-3 space-y-2.5">
                                <!-- En-tête de la région sélectionnée -->
                                <div class="bg-gradient-to-r from-slate-900 to-sky-950 text-white rounded-lg p-3 shadow-xs shrink-0">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="w-7 h-7 rounded-md bg-amber-500 text-slate-950 font-bold text-xs flex items-center justify-center">
                                                {{ selectedRegion.code }}
                                            </span>
                                            <div>
                                                <h3 class="font-bold text-xs">{{ selectedRegion.nom }}</h3>
                                                <p class="text-[10px] text-sky-300">
                                                    Chef-lieu : <strong class="text-white">{{ selectedRegion.chef_lieu || 'N/A' }}</strong>
                                                </p>
                                            </div>
                                        </div>
                                        <button
                                            @click="resetToFullMaliView"
                                            class="text-[10px] text-amber-300 hover:text-amber-200 border border-amber-400/40 px-2 py-0.5 rounded transition-colors"
                                        >
                                            <i class="pi pi-times mr-0.5"></i> Fermer
                                        </button>
                                    </div>

                                    <!-- Statistiques régionales en 3 colonnes -->
                                    <div class="grid grid-cols-3 gap-1.5 mt-2.5 pt-2 border-t border-white/10 text-center">
                                        <div class="bg-white/5 rounded p-1">
                                            <div class="text-[9px] text-sky-200">TOTAL</div>
                                            <div class="text-xs font-bold text-white">{{ selectedRegion.stats?.total || 0 }}</div>
                                            <div class="text-[8px] text-slate-400">Gendarmes</div>
                                        </div>
                                        <div class="bg-white/5 rounded p-1">
                                            <div class="text-[9px] text-emerald-300">PRÉSENTS</div>
                                            <div class="text-xs font-bold text-emerald-400">{{ selectedRegion.stats?.presents || 0 }}</div>
                                            <div class="text-[8px] text-emerald-400/80">{{ selectedRegion.stats?.taux_presence || 100 }}%</div>
                                        </div>
                                        <div class="bg-white/5 rounded p-1">
                                            <div class="text-[9px] text-rose-300">ABSENTS</div>
                                            <div class="text-xs font-bold text-rose-400">{{ (selectedRegion.stats?.total || 0) - (selectedRegion.stats?.presents || 0) }}</div>
                                            <div class="text-[8px] text-rose-300/80">Indispos</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Recherche rapide de brigade -->
                                <div class="relative shrink-0">
                                    <i class="pi pi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                    <input
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="Filtrer les brigades..."
                                        class="w-full pl-8 pr-7 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:ring-1 focus:ring-sky-500 focus:bg-white transition-all placeholder:text-slate-400"
                                    />
                                    <button
                                        v-if="searchQuery"
                                        @click="searchQuery = ''"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs"
                                    >
                                        <i class="pi pi-times text-[10px]"></i>
                                    </button>
                                </div>

                                <!-- Indicateur des unités de la région -->
                                <div class="flex items-center justify-between text-[11px] text-slate-600 px-0.5 shrink-0">
                                    <span class="font-semibold">
                                        Points d'unités ({{ filteredRegionUnits.length }})
                                    </span>
                                    <button
                                        @click="filterOnlyOp = !filterOnlyOp"
                                        :class="[
                                            'text-[10px] px-2 py-0.5 rounded border transition-colors',
                                            filterOnlyOp ? 'bg-emerald-600 text-white border-emerald-700' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'
                                        ]"
                                    >
                                        {{ filterOnlyOp ? '✓ Op. seules' : 'Toutes op.' }}
                                    </button>
                                </div>

                                <!-- Liste scrollable des unités -->
                                <div class="space-y-1.5 overflow-y-auto flex-1 pr-1 custom-scrollbar">
                                    <div
                                        v-for="unite in filteredRegionUnits"
                                        :key="unite.id"
                                        @click="focusOnUnit(unite)"
                                        :class="[
                                            'p-2 rounded-lg border transition-all cursor-pointer text-xs space-y-1 shadow-2xs',
                                            selectedUnit?.id === unite.id
                                                ? 'border-sky-500 bg-sky-50/80 ring-1 ring-sky-300'
                                                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                                        ]"
                                    >
                                        <div class="flex items-center justify-between gap-1.5">
                                            <span class="font-bold text-slate-900 truncate flex items-center gap-1">
                                                <span class="text-rose-500">📍</span>
                                                <span>{{ unite.nom }}</span>
                                            </span>
                                            <span
                                                :class="[
                                                    'px-1.5 py-0.2 rounded text-[9px] font-bold shrink-0',
                                                    unite.est_operationnelle
                                                        ? 'bg-emerald-100 text-emerald-800'
                                                        : 'bg-rose-100 text-rose-700'
                                                ]"
                                            >
                                                {{ unite.est_operationnelle ? 'Op.' : 'Non op.' }}
                                            </span>
                                        </div>

                                        <div class="flex items-center justify-between text-[10px] text-slate-500">
                                            <span class="truncate">{{ unite.type || 'Unité' }} <span v-if="unite.ville">• {{ unite.ville }}</span></span>
                                            <span class="text-sky-700 font-semibold hover:underline flex items-center gap-1 shrink-0">
                                                <i class="pi pi-search text-[9px]"></i> Zoomer
                                            </span>
                                        </div>
                                    </div>

                                    <p v-if="filteredRegionUnits.length === 0" class="text-xs text-slate-400 italic text-center py-4">
                                        Aucune brigade trouvée avec ce filtre.
                                    </p>
                                </div>
                            </div>

                            <!-- CAS 2 : AUCUNE RÉGION N'EST SÉLECTIONNÉE (LISTE DES 20 RÉGIONS DU MALI) -->
                            <div v-else class="flex flex-col flex-1 overflow-hidden p-3 space-y-2.5">
                                <!-- Recherche rapide de région -->
                                <div class="relative shrink-0">
                                    <i class="pi pi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                    <input
                                        v-model="regionSearchQuery"
                                        type="text"
                                        placeholder="Filtrer par région, chef-lieu..."
                                        class="w-full pl-8 pr-7 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:ring-1 focus:ring-sky-500 focus:bg-white transition-all placeholder:text-slate-400"
                                    />
                                    <button
                                        v-if="regionSearchQuery"
                                        @click="regionSearchQuery = ''"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs"
                                    >
                                        <i class="pi pi-times text-[10px]"></i>
                                    </button>
                                </div>

                                <!-- Indication discrète -->
                                <div class="flex items-center justify-between text-[11px] text-slate-500 px-0.5 shrink-0">
                                    <span class="flex items-center gap-1 text-slate-600">
                                        <i class="pi pi-compass text-sky-600"></i>
                                        <span>Cliquez pour isoler une région</span>
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-medium">
                                        {{ filteredRegions.length }} / {{ regionsTerritoriales.length }}
                                    </span>
                                </div>

                                <!-- Liste légère des 20 Régions -->
                                <div class="space-y-1.5 overflow-y-auto flex-1 pr-1 custom-scrollbar">
                                    <div
                                        v-for="reg in filteredRegions"
                                        :key="reg.id"
                                        @click="selectAndFocusRegion(reg)"
                                        class="p-2 rounded-lg border border-slate-200/80 bg-white hover:border-sky-300 hover:bg-sky-50/50 cursor-pointer transition-all flex items-center justify-between group shadow-2xs"
                                    >
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <span class="w-8 h-8 rounded-md bg-sky-50 border border-sky-200/80 text-sky-800 font-bold text-xs flex items-center justify-center shrink-0 group-hover:bg-sky-700 group-hover:text-white group-hover:border-sky-700 transition-colors">
                                                {{ reg.code }}
                                            </span>
                                            <div class="min-w-0">
                                                <span class="font-bold text-xs text-slate-800 group-hover:text-sky-900 block truncate">
                                                    {{ reg.nom }}
                                                </span>
                                                <span class="text-[11px] text-slate-500 block truncate">
                                                    {{ reg.chef_lieu || 'N/A' }} • <span class="text-slate-600 font-medium">{{ reg.unites_count }} unités</span>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="text-right shrink-0 pl-2">
                                            <span class="inline-block text-[11px] font-bold text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded group-hover:bg-sky-100 group-hover:text-sky-800 transition-colors">
                                                {{ reg.stats?.total || 0 }} pers.
                                            </span>
                                            <div class="text-[10px] text-emerald-600 font-medium flex items-center justify-end gap-1 mt-0.5">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                <span>{{ reg.stats?.taux_presence || 100 }}%</span>
                                            </div>
                                        </div>
                                    </div>

                                    <p v-if="filteredRegions.length === 0" class="text-xs text-slate-400 italic text-center py-4">
                                        Aucune région ne correspond à votre recherche.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';

// Sécurisation globale contre les crashs Leaflet pendant les animations de zoom / transition
if (L.Marker && L.Marker.prototype._animateZoom) {
    const origMarker = L.Marker.prototype._animateZoom;
    L.Marker.prototype._animateZoom = function (opt) {
        if (!this._map) return;
        return origMarker.call(this, opt);
    };
}
if (L.Popup && L.Popup.prototype._animateZoom) {
    const origPopup = L.Popup.prototype._animateZoom;
    L.Popup.prototype._animateZoom = function (opt) {
        if (!this._map) return;
        return origPopup.call(this, opt);
    };
}
if (L.Tooltip && L.Tooltip.prototype._animateZoom) {
    const origTooltip = L.Tooltip.prototype._animateZoom;
    L.Tooltip.prototype._animateZoom = function (opt) {
        if (!this._map) return;
        return origTooltip.call(this, opt);
    };
}
import { 
    MALI_BOUNDS, 
    REGION_BOUNDS, 
    MALI_20_REGIONS, 
    MALI_BORDER,
    CHEF_LIEUX_COORDS,
    WORLD_MASK_RING,
    MALI_BORDER_LATLNGS,
    REGION_RINGS_LATLNGS
} from './maliGeoData.js';

const props = defineProps({
    regions: {
        type: Array,
        default: () => []
    },
    statsGlobales: {
        type: Object,
        default: () => ({
            total_militaires: 0,
            total_regions: 0,
            total_unites: 0,
            unites_operationnelles: 0,
            unites_non_operationnelles: 0,
        })
    }
});

// État réactif
const mapInstance = ref(null);
const maskLayer = ref(null);
const borderLayer = ref(null);
const regionsLayerGroup = ref(null);
const markersLayerGroup = ref(null);
const unitsLayerGroup = ref(null);

let canvasRenderer = null;

const isSidebarOpen = ref(true);
const regionSearchQuery = ref('');
const selectedRegion = ref(null);
const selectedUnit = ref(null);
const searchQuery = ref('');
const filterOnlyOp = ref(false);

// Toutes les régions sont des régions territoriales officielles du Mali (20 Régions)
const regionsTerritoriales = computed(() => {
    return props.regions;
});

// Filtre dynamique des régions dans le volet latéral
const filteredRegions = computed(() => {
    if (!regionSearchQuery.value) return regionsTerritoriales.value;
    const q = regionSearchQuery.value.toLowerCase().trim();
    return regionsTerritoriales.value.filter(r =>
        r.nom.toLowerCase().includes(q) ||
        r.code.toLowerCase().includes(q) ||
        (r.chef_lieu && r.chef_lieu.toLowerCase().includes(q))
    );
});

// Unités avec coordonnées garanties à l'intérieur de leur région
const unitesAvecCoords = computed(() => {
    const list = [];
    props.regions.forEach(r => {
        const chef = CHEF_LIEUX_COORDS[r.code] || (r.latitude && r.longitude ? [Number(r.latitude), Number(r.longitude)] : null);
        if (r.unites) {
            r.unites.forEach((u, idx) => {
                let lat = u.latitude !== null && u.latitude !== undefined ? Number(u.latitude) : null;
                let lon = u.longitude !== null && u.longitude !== undefined ? Number(u.longitude) : null;

                // Fallback garanti à proximité du Chef-lieu si pas de coordonnées
                if ((lat === null || lon === null || isNaN(lat) || isNaN(lon)) && chef) {
                    const angle = (idx * 137.5) * (Math.PI / 180);
                    const radius = 0.015 + (idx % 6) * 0.006;
                    lat = chef[0] + Math.sin(angle) * radius;
                    lon = chef[1] + Math.cos(angle) * radius;
                }

                if (lat !== null && lon !== null && !isNaN(lat) && !isNaN(lon)) {
                    list.push({
                        ...u,
                        latitude: lat,
                        longitude: lon,
                        region_id: r.id,
                        region_nom: r.nom,
                        region_code: r.code
                    });
                }
            });
        }
    });
    return list;
});

// Unités filtrées dans le volet latéral
const filteredRegionUnits = computed(() => {
    if (!selectedRegion.value || !selectedRegion.value.unites) return [];
    let list = selectedRegion.value.unites;

    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(u =>
            u.nom.toLowerCase().includes(q) ||
            (u.ville && u.ville.toLowerCase().includes(q)) ||
            (u.type && u.type.toLowerCase().includes(q))
        );
    }

    if (filterOnlyOp.value) {
        list = list.filter(u => u.est_operationnelle);
    }

    return list;
});

// Bascule du volet latéral pour maximiser l'espace de la carte
const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
    nextTick(() => {
        setTimeout(() => {
            if (mapInstance.value) {
                mapInstance.value.invalidateSize();
            }
        }, 200);
    });
};

// ===============================================================
// PINS / ÉPINGLES POINTANT PRÉCISÉMENT SUR LA CARTE
// ===============================================================

/**
 * Pin pointé pour les Régions / Chefs-lieux
 */
const createRegionPin = (code, count, isSelected = false) => {
    const pinColor = isSelected ? '#d97706' : '#0369a1';
    const ringColor = isSelected ? '#fbbf24' : '#38bdf8';

    const html = `
        <div style="position: relative; width: 34px; height: 42px; cursor: pointer; transition: transform 0.2s ease;">
            <svg width="34" height="42" viewBox="0 0 34 42" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 3px 6px rgba(0,0,0,0.45));">
                <path d="M17 0C7.61116 0 0 7.61116 0 17C0 26.5 13.5 39.8 16.15 41.6C16.65 41.95 17.35 41.95 17.85 41.6C20.5 39.8 34 26.5 34 17C34 7.61116 26.3888 0 17 0Z" fill="${pinColor}"/>
                <circle cx="17" cy="16" r="11" fill="#0f172a" stroke="${ringColor}" stroke-width="2"/>
            </svg>
            <div style="position: absolute; top: 10px; left: 0; width: 34px; text-align: center; color: #ffffff; font-weight: 800; font-size: ${code.length > 2 ? '8px' : '9.5px'}; line-height: 1; pointer-events: none; letter-spacing: -0.5px;">
                ${code}
            </div>
            <div style="position: absolute; top: -6px; right: -6px; background: #eab308; color: #000; font-weight: 800; font-size: 9px; border-radius: 999px; padding: 1px 4px; border: 1px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.3);">
                ${count}
            </div>
        </div>
    `;

    return L.divIcon({
        className: 'mali-pin-region',
        html: html,
        iconSize: [34, 42],
        iconAnchor: [17, 42],
        popupAnchor: [0, -38]
    });
};

/**
 * Pin pointé pour les Unités / Brigades de terrain
 */
const createUnitPin = (estOp = true, isSelected = false) => {
    const pinColor = estOp ? '#10b981' : '#ef4444';
    const strokeColor = isSelected ? '#f59e0b' : '#ffffff';
    const strokeWidth = isSelected ? '2.5' : '1.5';

    const html = `
        <div style="position: relative; width: 20px; height: 26px; cursor: pointer;">
            <svg width="20" height="26" viewBox="0 0 20 26" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.35));">
                <path d="M10 0C4.47715 0 0 4.47715 0 10C0 16 8 24.5 9.4 25.7C9.75 25.95 10.25 25.95 10.6 25.7C12 24.5 20 16 20 10C20 4.47715 15.5228 0 10 0Z" fill="${pinColor}" stroke="${strokeColor}" stroke-width="${strokeWidth}"/>
                <circle cx="10" cy="10" r="4" fill="#ffffff"/>
            </svg>
        </div>
    `;

    return L.divIcon({
        className: 'mali-pin-unit',
        html: html,
        iconSize: [20, 26],
        iconAnchor: [10, 26],
        popupAnchor: [0, -24]
    });
};

// ===============================================================
// GESTION DU MASQUE CANVAS ANTI-BUG
// ===============================================================

/**
 * Met à jour le masque Canvas opaque qui recouvre tous les pays voisins
 * et isole éventuellement la région sélectionnée
 */
const updateMask = (holeLatLngs) => {
    if (!mapInstance.value) return;

    if (maskLayer.value) {
        mapInstance.value.removeLayer(maskLayer.value);
    }

    if (!canvasRenderer) {
        canvasRenderer = L.canvas({ padding: 0.5 });
    }

    maskLayer.value = L.polygon([WORLD_MASK_RING, holeLatLngs], {
        fillColor: '#f8fafc',
        fillOpacity: 1.0,
        stroke: false,
        interactive: false,
        renderer: canvasRenderer
    }).addTo(mapInstance.value);

    maskLayer.value.bringToBack();
};

// ===============================================================
// INITIALISATION DE LA CARTE LEAFLET
// ===============================================================
const initMap = async () => {
    await nextTick();
    const mapContainer = document.getElementById('map-mali');
    if (!mapContainer || mapInstance.value) return;

    const map = L.map('map-mali', {
        center: [16.5, -3.5],
        zoom: 6,
        minZoom: 5.5,
        maxZoom: 18,
        maxBounds: L.latLngBounds([9.5, -13.0], [25.5, 4.8]),
        maxBoundsViscosity: 1.0,
    });

    // 1. Fond de carte OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap | Gendarmerie Nationale du Mali'
    }).addTo(map);

    // 2. Initialiser le Canvas Renderer
    canvasRenderer = L.canvas({ padding: 0.5 });

    // 3. Masque Canvas initial couvrant tous les pays voisins (Mali seul visible)
    maskLayer.value = L.polygon([WORLD_MASK_RING, MALI_BORDER_LATLNGS], {
        fillColor: '#f8fafc',
        fillOpacity: 1.0,
        stroke: false,
        interactive: false,
        renderer: canvasRenderer
    }).addTo(map);

    // 4. Contour officiel de la frontière nationale du Mali
    borderLayer.value = L.geoJSON(MALI_BORDER, {
        style: {
            color: '#0369a1',
            weight: 3.0,
            opacity: 0.95,
            fill: false,
            interactive: false,
        }
    }).addTo(map);

    // 5. Groupes de couches dynamiques
    regionsLayerGroup.value = L.layerGroup().addTo(map);
    markersLayerGroup.value = L.layerGroup().addTo(map);
    unitsLayerGroup.value = L.layerGroup().addTo(map);

    mapInstance.value = map;

    // 6. Rendu initial : Tout le Mali (20 pins régionaux au Chef-lieu, 0 unités pour aérer)
    updateMapDisplay();

    // 7. Centrer sur l'ensemble du territoire national
    map.fitBounds(MALI_BOUNDS, { padding: [25, 25] });
};

/**
 * Met à jour dynamiquement toutes les couches de la carte
 */
const updateMapDisplay = () => {
    if (!mapInstance.value) return;

    // Si une région est isolée, masquer le contour national extérieur
    if (borderLayer.value) {
        borderLayer.value.setStyle({
            opacity: selectedRegion.value ? 0 : 0.95
        });
    }

    renderRegions();
    renderRegionPins();
    renderUnitPins();
};

/**
 * Rendu dynamique des polygones des régions :
 * - Si région sélectionnée : affiche uniquement cette région avec contour net
 * - Par défaut : affiche les 20 régions avec contours fins
 */
const renderRegions = () => {
    if (!mapInstance.value || !regionsLayerGroup.value) return;
    regionsLayerGroup.value.clearLayers();

    const isIsolated = selectedRegion.value !== null;

    const featuresToShow = isIsolated
        ? MALI_20_REGIONS.features.filter(f => f.properties?.code === selectedRegion.value.code)
        : MALI_20_REGIONS.features;

    const layer = L.geoJSON({ type: 'FeatureCollection', features: featuresToShow }, {
        style: (feature) => {
            const isTarget = isIsolated && feature.properties?.code === selectedRegion.value?.code;
            return {
                fillColor: '#0284c7',
                fillOpacity: isTarget ? 0.06 : 0.04,
                color: isTarget ? '#0369a1' : '#0284c7',
                weight: isTarget ? 3.5 : 1.5,
                dashArray: isTarget ? '' : '3, 4',
            };
        },
        onEachFeature: (feature, l) => {
            const code = feature.properties?.code;
            const reg = props.regions.find(r => r.code === code);
            const nomAffiche = reg ? reg.nom : (feature.properties?.nom || code);
            const chef = reg?.chef_lieu || feature.properties?.chef_lieu || '';
            const effectif = reg?.stats?.total || 0;
            const presence = reg?.stats?.taux_presence || 100;

            l.bindTooltip(`
                <div style="font-size: 12px; font-weight: bold; color: #0c4a6e;">
                    📍 ${nomAffiche} (${code})
                </div>
                <div style="font-size: 11px; color: #334155;">
                    Chef-lieu : <strong>${chef}</strong><br/>
                    Effectif : <strong>${effectif}</strong> gendarmes (${presence}% présents)
                </div>
            `, { sticky: true });

            l.on({
                mouseover: (e) => {
                    if (!isIsolated) {
                        e.target.setStyle({
                            weight: 2.5,
                            color: '#0369a1',
                            fillOpacity: 0.12
                        });
                        e.target.bringToFront();
                    }
                },
                mouseout: (e) => {
                    if (!isIsolated) {
                        layer.resetStyle(e.target);
                    }
                },
                click: () => {
                    if (reg) {
                        selectAndFocusRegion(reg);
                    }
                }
            });
        }
    });

    regionsLayerGroup.value.addLayer(layer);
};

/**
 * Pointer les Régions sur la carte :
 * - Si isolée : n'afficher que le pin au Chef-lieu de cette région
 * - Par défaut (vue nationale) : afficher les 20 pins au Chef-lieu
 */
const renderRegionPins = () => {
    if (!mapInstance.value || !markersLayerGroup.value) return;
    markersLayerGroup.value.clearLayers();

    const list = selectedRegion.value
        ? regionsTerritoriales.value.filter(r => r.id === selectedRegion.value.id || r.code === selectedRegion.value.code)
        : regionsTerritoriales.value;

    list.forEach(reg => {
        const coords = CHEF_LIEUX_COORDS[reg.code] || (reg.latitude && reg.longitude ? [Number(reg.latitude), Number(reg.longitude)] : null);
        if (!coords) return;

        const isSelected = selectedRegion.value?.id === reg.id || selectedRegion.value?.code === reg.code;
        const icon = createRegionPin(reg.code, reg.stats?.total || 0, isSelected);

        const marker = L.marker(coords, {
            icon,
            zIndexOffset: isSelected ? 1200 : 1000
        });

        marker.on('click', () => {
            selectAndFocusRegion(reg);
        });

        marker.bindPopup(`
            <div style="font-size: 12px; min-width: 175px; line-height: 1.4;">
                <div style="font-size: 13px; font-weight: 800; color: #0369a1; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; margin-bottom: 4px;">
                    📍 ${reg.nom} (${reg.code})
                </div>
                <span>Chef-lieu : <strong>${reg.chef_lieu || 'N/A'}</strong></span><br/>
                <span>Effectif total : <strong style="color: #0369a1;">${reg.stats?.total || 0}</strong></span><br/>
                <span>Présents en service : <strong style="color: #059669;">${reg.stats?.presents || 0}</strong></span><br/>
                <span>Unités rattachées : <strong>${reg.unites_count}</strong></span>
            </div>
        `);

        markersLayerGroup.value.addLayer(marker);
    });
};

/**
 * Pointer les Unités / Brigades de terrain :
 * - En vue nationale : 0 unité affichée (carte aérée et épurée)
 * - En vue isolée : affiche STRICTEMENT les unités de la région sélectionnée
 */
const renderUnitPins = () => {
    if (!mapInstance.value || !unitsLayerGroup.value) return;
    unitsLayerGroup.value.clearLayers();

    // RÈGLE DEMANDÉE : À l'arrivée sur la carte, les unités de terrain sont masquées.
    // Elles ne s'affichent QUE lorsqu'une région est sélectionnée.
    if (!selectedRegion.value) return;

    let list = unitesAvecCoords.value.filter(u => 
        u.region_id === selectedRegion.value.id || u.region_code === selectedRegion.value.code
    );

    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(u =>
            u.nom.toLowerCase().includes(q) ||
            (u.ville && u.ville.toLowerCase().includes(q)) ||
            (u.type && u.type.toLowerCase().includes(q))
        );
    }

    if (filterOnlyOp.value) {
        list = list.filter(u => u.est_operationnelle);
    }

    list.forEach(u => {
        const isSelected = selectedUnit.value?.id === u.id;
        const icon = createUnitPin(u.est_operationnelle, isSelected);

        const marker = L.marker([u.latitude, u.longitude], {
            icon,
            zIndexOffset: isSelected ? 900 : 500
        });

        marker.on('click', () => {
            selectedUnit.value = u;
        });

        marker.bindPopup(`
            <div style="font-size: 12px; min-width: 170px; line-height: 1.4;">
                <div style="font-weight: 800; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; margin-bottom: 4px;">
                    📍 ${u.nom}
                </div>
                <span style="color: #64748b;">${u.type || 'Unité'} • ${u.ville || ''}</span><br/>
                <span>Région : <strong>${u.region_nom}</strong></span><br/>
                <span>Statut : <strong style="color: ${u.est_operationnelle ? '#059669' : '#dc2626'};">${u.est_operationnelle ? 'Opérationnelle' : 'Non opérationnelle'}</strong></span><br/>
                <span>Effectif : <strong>${u.stats?.total || 0}</strong></span>
            </div>
        `);

        unitsLayerGroup.value.addLayer(marker);
    });
};

/**
 * Sélectionne une région et active l'isolation parfaite
 */
const selectAndFocusRegion = (reg) => {
    if (!mapInstance.value) return;

    // Arrêter toute animation en cours pour éviter les désorganisations de points
    mapInstance.value.stop();
    mapInstance.value.closePopup();

    selectedRegion.value = reg;
    selectedUnit.value = null;
    isSidebarOpen.value = true;

    // 1. Mettre à jour le masque Canvas avec l'anneau de cette région
    const regionRing = REGION_RINGS_LATLNGS[reg.code];
    if (regionRing) {
        updateMask(regionRing);
    }

    // 2. Mettre à jour l'affichage (pins et unités)
    updateMapDisplay();

    // 3. Zoom fluide sur les limites de la région
    mapInstance.value.invalidateSize();
    const bounds = REGION_BOUNDS[reg.code];
    if (bounds) {
        mapInstance.value.fitBounds(bounds, {
            padding: [45, 45],
            maxZoom: 10,
            animate: true,
            duration: 0.8
        });
    } else {
        const chef = CHEF_LIEUX_COORDS[reg.code] || [Number(reg.latitude), Number(reg.longitude)];
        if (chef) {
            mapInstance.value.flyTo(chef, 9, { duration: 0.8 });
        }
    }
};

/**
 * Zoomer sur une brigade ou unité précise
 */
const focusOnUnit = (unite) => {
    selectedUnit.value = unite;
    renderUnitPins();

    if (mapInstance.value && unite.latitude && unite.longitude) {
        mapInstance.value.stop();
        mapInstance.value.flyTo([unite.latitude, unite.longitude], 12, {
            duration: 1.0
        });
    }
};

/**
 * Réinitialiser la vue complète (Tout le Mali)
 */
const resetToFullMaliView = () => {
    if (!mapInstance.value) return;

    // Arrêter toute animation en cours
    mapInstance.value.stop();
    mapInstance.value.closePopup();

    selectedRegion.value = null;
    selectedUnit.value = null;

    // 1. Restaurer le masque Canvas national (Mali seul visible, pays voisins masqués)
    updateMask(MALI_BORDER_LATLNGS);

    // 2. Restaurer l'affichage (20 pins régionaux, 0 unités)
    updateMapDisplay();

    // 3. Recentrer de manière fiable sur l'ensemble du territoire national
    mapInstance.value.invalidateSize();
    mapInstance.value.fitBounds(MALI_BOUNDS, {
        padding: [30, 30],
        animate: true,
        duration: 0.8
    });
};

// Watchers
watch(searchQuery, () => {
    renderUnitPins();
});

watch(filterOnlyOp, () => {
    renderUnitPins();
});

onMounted(() => {
    initMap();
});
</script>

<style>
/* Style des popups Leaflet adaptés au thème */
.leaflet-popup-content-wrapper {
    border-radius: 10px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
}
.leaflet-container {
    font-family: inherit;
    background-color: #f8fafc;
}
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
