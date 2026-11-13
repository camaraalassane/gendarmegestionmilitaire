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

        <div class="space-y-4">
            <!-- Barre d'outils et filtres -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-3 sm:p-4">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <!-- Recherche dynamique -->
                    <div class="relative flex-1 max-w-md">
                        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Rechercher une région, brigade, ville..."
                            class="w-full pl-9 pr-3 py-1.5 text-sm bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:border-sky-500 transition-colors"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs"
                        >
                            <i class="pi pi-times"></i>
                        </button>
                    </div>

                    <!-- Contrôles des points pointés sur la carte -->
                    <div class="flex items-center gap-2 flex-wrap text-xs">
                        <!-- Mode d'affichage des points -->
                        <div class="inline-flex rounded-lg border border-gray-300 p-0.5 bg-gray-50 shadow-sm">
                            <button
                                @click="filterDisplayMode = 'all'"
                                :class="[
                                    'px-2.5 py-1 rounded-md font-medium transition-colors',
                                    filterDisplayMode === 'all'
                                        ? 'bg-sky-800 text-white shadow-xs'
                                        : 'text-gray-600 hover:text-gray-900'
                                ]"
                            >
                                Tous les points ({{ activeRegionsCount + activeUnitsCount }})
                            </button>
                            <button
                                @click="filterDisplayMode = 'regions'"
                                :class="[
                                    'px-2.5 py-1 rounded-md font-medium transition-colors',
                                    filterDisplayMode === 'regions'
                                        ? 'bg-sky-800 text-white shadow-xs'
                                        : 'text-gray-600 hover:text-gray-900'
                                ]"
                            >
                                Régions seules ({{ activeRegionsCount }})
                            </button>
                            <button
                                @click="filterDisplayMode = 'unites'"
                                :class="[
                                    'px-2.5 py-1 rounded-md font-medium transition-colors',
                                    filterDisplayMode === 'unites'
                                        ? 'bg-sky-800 text-white shadow-xs'
                                        : 'text-gray-600 hover:text-gray-900'
                                ]"
                            >
                                Brigades seules ({{ activeUnitsCount }})
                            </button>
                        </div>

                        <!-- Filtre unités opérationnelles -->
                        <button
                            v-if="filterDisplayMode !== 'regions'"
                            @click="filterOnlyOp = !filterOnlyOp"
                            :class="[
                                'px-2.5 py-1.5 rounded-lg font-medium border transition-all flex items-center gap-1.5 shadow-sm',
                                filterOnlyOp
                                    ? 'bg-emerald-600 text-white border-emerald-700'
                                    : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                            ]"
                        >
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            <span>Opérationnelles uniquement</span>
                        </button>

                        <!-- Bouton Tout le Mali / Réinitialiser -->
                        <button
                            @click="resetToFullMaliView"
                            :class="[
                                'px-3 py-1.5 rounded-lg border font-bold transition-all flex items-center gap-1.5 shadow-sm',
                                selectedRegion !== null
                                    ? 'bg-amber-500 hover:bg-amber-600 text-slate-950 border-amber-600 ring-2 ring-amber-300 animate-pulse'
                                    : 'bg-sky-50 hover:bg-sky-100 text-sky-800 border-sky-200'
                            ]"
                            title="Afficher tout le Mali (vue complète par défaut)"
                        >
                            <i class="pi pi-globe text-sky-700"></i>
                            <span>{{ selectedRegion ? '← Afficher tout le Mali' : 'Recentrer Mali' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Conteneur principal : Carte + Volet de Commandement -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <!-- Zone Carte (8 colonnes) -->
                <div class="lg:col-span-8 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col relative min-h-[580px] lg:min-h-[680px]">
                    <!-- En-tête statut carte -->
                    <div class="bg-gray-50/90 backdrop-blur-sm px-4 py-2 border-b border-gray-200 flex items-center justify-between text-xs text-gray-600 z-10">
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="font-bold text-gray-800">RÉPUBLIQUE DU MALI</span>
                            <span v-if="selectedRegion" class="text-sky-800 font-bold ml-2 bg-sky-100 px-2 py-0.5 rounded">
                                ▸ Vue isolée : {{ selectedRegion.nom }} ({{ selectedRegion.code }})
                            </span>
                            <span v-else class="text-emerald-700 font-medium ml-2">
                                ▸ Vue générale par défaut (11 Régions)
                            </span>
                        </div>
                        <div class="flex items-center gap-3 text-gray-600 text-[11px]">
                            <span class="flex items-center gap-1">
                                <span class="w-3 h-3 rounded-full bg-sky-800 border border-white inline-block"></span>
                                Points Régions (R1 à R11)
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 border border-white inline-block"></span>
                                Opérationnelle
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="w-2.5 h-2.5 rounded-full bg-red-500 border border-white inline-block"></span>
                                Non opérationnelle
                            </span>
                        </div>
                    </div>

                    <!-- Carte Leaflet -->
                    <div id="map-mali" class="flex-1 w-full h-full min-h-[520px] lg:min-h-[620px] z-0 bg-slate-100"></div>

                    <!-- Bouton flottant de retour en haut de la carte si région pointée -->
                    <div v-if="selectedRegion" class="absolute top-12 left-3 z-[400] flex items-center gap-2">
                        <button
                            @click="resetToFullMaliView"
                            class="bg-sky-900/95 hover:bg-sky-800 text-white px-3.5 py-1.5 rounded-lg shadow-xl font-bold text-xs flex items-center gap-2 border border-sky-400 backdrop-blur-sm transition-all"
                            title="Quitter la vue isolée et afficher toutes les régions du Mali"
                        >
                            <i class="pi pi-arrow-left text-amber-300"></i>
                            <span>Afficher tout le Mali</span>
                        </button>
                        <div class="bg-white/95 backdrop-blur-sm text-sky-900 font-bold px-3 py-1.5 rounded-lg shadow-md border border-gray-200 text-xs flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                            <span>{{ selectedRegion.nom }} • {{ selectedRegion.chef_lieu }}</span>
                        </div>
                    </div>

                    <!-- Barre de sélection rapide au bas de la carte -->
                    <div class="absolute bottom-3 left-3 right-3 z-[400] pointer-events-none">
                        <div class="bg-white/95 backdrop-blur-md p-2 rounded-xl border border-gray-300 shadow-xl pointer-events-auto overflow-x-auto no-scrollbar flex items-center gap-1.5">
                            <!-- Bouton Tout afficher -->
                            <button
                                @click="resetToFullMaliView"
                                :class="[
                                    'px-3 py-1.5 text-xs rounded-lg font-bold whitespace-nowrap transition-all shrink-0 border flex items-center gap-1.5',
                                    selectedRegion === null
                                        ? 'bg-sky-900 text-white border-sky-950 shadow-sm ring-2 ring-sky-300'
                                        : 'bg-white text-gray-700 border-gray-300 hover:bg-sky-50 hover:text-sky-800'
                                ]"
                            >
                                <i class="pi pi-globe"></i>
                                <span>Tout le Mali</span>
                            </button>

                            <span class="text-gray-300">|</span>

                            <!-- Boutons de pointage direct pour chaque région -->
                            <button
                                v-for="reg in regionsTerritoriales"
                                :key="reg.id"
                                @click="selectAndFocusRegion(reg)"
                                :class="[
                                    'px-2.5 py-1 text-xs rounded-lg font-bold whitespace-nowrap transition-all shrink-0 border',
                                    selectedRegion?.id === reg.id
                                        ? 'bg-amber-500 text-slate-950 border-amber-600 shadow-md ring-2 ring-amber-300'
                                        : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-sky-50 hover:text-sky-700'
                                ]"
                            >
                                📍 {{ reg.code }} <span class="text-[10px] font-normal opacity-80">({{ reg.stats.total }})</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Volet latéral de Commandement (4 colonnes) -->
                <div class="lg:col-span-4 flex flex-col gap-4">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col h-full max-h-[720px]">
                        <!-- Onglets du volet -->
                        <div class="flex border-b border-gray-200 bg-gray-50 text-xs font-semibold">
                            <button
                                @click="activeSideTab = 'territorial'"
                                :class="[
                                    'flex-1 py-2.5 px-3 text-center border-b-2 transition-colors flex items-center justify-center gap-1.5',
                                    activeSideTab === 'territorial'
                                        ? 'border-sky-700 text-sky-800 bg-white font-bold'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                <i class="pi pi-globe"></i>
                                <span>Régions terrain ({{ regionsTerritoriales.length }})</span>
                            </button>
                            <button
                                @click="activeSideTab = 'central'"
                                :class="[
                                    'flex-1 py-2.5 px-3 text-center border-b-2 transition-colors flex items-center justify-center gap-1.5',
                                    activeSideTab === 'central'
                                        ? 'border-sky-700 text-sky-800 bg-white font-bold'
                                        : 'border-transparent text-gray-500 hover:text-gray-700'
                                ]"
                            >
                                <i class="pi pi-shield"></i>
                                <span>Centraux & Réserves ({{ regionsCentrales.length }})</span>
                            </button>
                        </div>

                        <!-- CONTENU 1 : DÉTAIL DE LA RÉGION SÉLECTIONNÉE OU VUE D'ENSEMBLE -->
                        <div v-if="activeSideTab === 'territorial'" class="p-4 overflow-y-auto flex-1 space-y-4">
                            <!-- CAS A : UNE RÉGION EST POINTÉE ET ISOLÉE -->
                            <div v-if="selectedRegion" class="space-y-4">
                                <!-- En-tête Région -->
                                <div class="bg-gradient-to-br from-sky-800 via-sky-700 to-sky-900 rounded-xl p-4 text-white shadow-md">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <span class="px-2 py-0.5 text-[10px] font-bold bg-amber-400 text-slate-900 rounded-md tracking-wider">
                                                {{ selectedRegion.code }}
                                            </span>
                                            <h3 class="text-base font-bold mt-1 text-white">{{ selectedRegion.nom }}</h3>
                                            <p v-if="selectedRegion.chef_lieu" class="text-xs text-sky-200 flex items-center gap-1 mt-0.5">
                                                <i class="pi pi-map-marker text-amber-300"></i>
                                                Chef-lieu : <strong class="text-white">{{ selectedRegion.chef_lieu }}</strong>
                                            </p>
                                        </div>

                                        <button
                                            @click="resetToFullMaliView"
                                            class="bg-white/15 hover:bg-white/25 rounded-lg px-2 py-1 text-xs text-amber-300 border border-white/20 transition-colors flex items-center gap-1"
                                            title="Revenir à la vue générale de tout le Mali"
                                        >
                                            <i class="pi pi-times"></i>
                                            <span>Fermer</span>
                                        </button>
                                    </div>

                                    <p v-if="selectedRegion.description" class="text-xs text-sky-100/90 mt-2 bg-black/15 p-2 rounded-lg">
                                        {{ selectedRegion.description }}
                                    </p>
                                </div>

                                <!-- Indicateurs clés -->
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="bg-sky-50 border border-sky-100 rounded-xl p-2.5 text-center">
                                        <span class="text-[10px] uppercase font-bold text-sky-700 block">Total</span>
                                        <span class="text-lg font-bold text-sky-900">{{ selectedRegion.stats.total }}</span>
                                        <span class="text-[10px] text-gray-500 block">Gendarmes</span>
                                    </div>
                                    <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-2.5 text-center">
                                        <span class="text-[10px] uppercase font-bold text-emerald-700 block">Présents</span>
                                        <span class="text-lg font-bold text-emerald-800">{{ selectedRegion.stats.presents }}</span>
                                        <span class="text-[10px] text-emerald-600 block">{{ selectedRegion.stats.taux_presence }}%</span>
                                    </div>
                                    <div class="bg-amber-50 border border-amber-100 rounded-xl p-2.5 text-center">
                                        <span class="text-[10px] uppercase font-bold text-amber-700 block">Absents</span>
                                        <span class="text-lg font-bold text-amber-800">{{ selectedRegion.stats.absents }}</span>
                                        <span class="text-[10px] text-amber-600 block">Indispos</span>
                                    </div>
                                </div>

                                <!-- Répartition genre & indisponibilités -->
                                <div class="bg-gray-50 rounded-xl p-3 border border-gray-200 space-y-2 text-xs">
                                    <div class="flex justify-between items-center text-gray-600">
                                        <span>Effectif :</span>
                                        <span class="font-medium">
                                            <strong>{{ selectedRegion.stats.hommes }}</strong> Hommes • <strong>{{ selectedRegion.stats.femmes }}</strong> Femmes
                                        </span>
                                    </div>
                                    <div v-if="selectedRegion.stats.absents > 0" class="pt-2 border-t border-gray-200">
                                        <span class="font-semibold text-gray-700 block mb-1">Motifs d'absence :</span>
                                        <div class="grid grid-cols-2 gap-1.5 text-[11px] text-gray-600">
                                            <span v-if="selectedRegion.stats.en_mission">✈️ Missions : <strong>{{ selectedRegion.stats.en_mission }}</strong></span>
                                            <span v-if="selectedRegion.stats.en_conge">🏖️ Congés : <strong>{{ selectedRegion.stats.en_conge }}</strong></span>
                                            <span v-if="selectedRegion.stats.en_stage">🎓 Stages/Form. : <strong>{{ selectedRegion.stats.en_stage }}</strong></span>
                                            <span v-if="selectedRegion.stats.autres_absences">⚠️ Autres : <strong>{{ selectedRegion.stats.autres_absences }}</strong></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Liste des Unités / Brigades pointées pour cette région -->
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700">
                                            Points d'unités de cette région ({{ filteredRegionUnits.length }})
                                        </h4>
                                        <span class="text-[10px] text-emerald-600 font-semibold">
                                            {{ selectedRegion.unites_operationnelles_count }} op.
                                        </span>
                                    </div>

                                    <div class="space-y-1.5 max-h-64 overflow-y-auto pr-1">
                                        <div
                                            v-for="unite in filteredRegionUnits"
                                            :key="unite.id"
                                            @click="focusOnUnit(unite)"
                                            :class="[
                                                'p-2.5 rounded-lg border text-xs cursor-pointer transition-all hover:shadow-sm',
                                                selectedUnit?.id === unite.id
                                                    ? 'bg-sky-50 border-sky-400 ring-1 ring-sky-300'
                                                    : 'bg-white border-gray-200 hover:border-gray-300'
                                            ]"
                                        >
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <span class="font-semibold text-gray-800 block truncate" :title="unite.nom">
                                                        📍 {{ unite.nom }}
                                                    </span>
                                                    <span class="text-[11px] text-gray-500 block">
                                                        {{ unite.type || 'Unité' }} <span v-if="unite.ville">• {{ unite.ville }}</span>
                                                    </span>
                                                </div>

                                                <span
                                                    :class="[
                                                        'px-1.5 py-0.5 rounded text-[10px] font-bold shrink-0',
                                                        unite.est_operationnelle
                                                            ? 'bg-emerald-100 text-emerald-800'
                                                            : 'bg-red-100 text-red-700'
                                                    ]"
                                                >
                                                    {{ unite.est_operationnelle ? 'Op.' : 'Non op.' }}
                                                </span>
                                            </div>

                                            <div class="flex items-center justify-between mt-2 pt-1.5 border-t border-gray-100 text-[11px] text-gray-600">
                                                <span>Effectif : <strong>{{ unite.stats?.total || 0 }}</strong></span>
                                                <span v-if="unite.latitude && unite.longitude" class="text-sky-700 hover:underline flex items-center gap-1 font-semibold">
                                                    <i class="pi pi-search text-[10px]"></i> Zoomer point
                                                </span>
                                            </div>
                                        </div>

                                        <p v-if="filteredRegionUnits.length === 0" class="text-xs text-gray-500 italic text-center py-4">
                                            Aucune unité trouvée avec ce filtre.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- CAS B : AUCUNE RÉGION N'EST POINTÉE (PAR DÉFAUT : VUE GLOBALE DU MALI) -->
                            <div v-else class="space-y-3">
                                <div class="bg-sky-50 border border-sky-200 rounded-xl p-3 text-xs text-sky-900">
                                    <p class="font-bold flex items-center gap-1.5 text-sky-950">
                                        <i class="pi pi-info-circle text-sky-600"></i>
                                        Vue Globale du Territoire National
                                    </p>
                                    <p class="text-[11px] text-sky-800 mt-1">
                                        Toutes les 11 régions et leurs unités sont affichées sur la carte. Cliquez sur n'importe quel point ou région pour n'afficher que sa carte isolée.
                                    </p>
                                </div>

                                <div class="space-y-1.5 max-h-[500px] overflow-y-auto pr-1">
                                    <div
                                        v-for="reg in regionsTerritoriales"
                                        :key="reg.id"
                                        @click="selectAndFocusRegion(reg)"
                                        class="p-2.5 rounded-lg border border-gray-200 bg-white hover:border-sky-400 hover:bg-sky-50/50 cursor-pointer transition-all flex items-center justify-between"
                                    >
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-800">
                                                    {{ reg.code }}
                                                </span>
                                                <span class="font-bold text-xs text-gray-900">{{ reg.nom }}</span>
                                            </div>
                                            <span class="text-[11px] text-gray-500 block mt-0.5">
                                                Chef-lieu : {{ reg.chef_lieu || 'N/A' }} • {{ reg.unites_count }} unités
                                            </span>
                                        </div>

                                        <div class="text-right">
                                            <span class="text-xs font-bold text-sky-900 block">{{ reg.stats.total }}</span>
                                            <span class="text-[10px] text-emerald-600 font-medium">{{ reg.stats.taux_presence }}%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CONTENU 2 : GRANDS COMMANDEMENTS & RÉSERVES -->
                        <div v-else class="p-4 overflow-y-auto flex-1 space-y-3">
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-900">
                                <p class="font-semibold flex items-center gap-1.5">
                                    <i class="pi pi-shield text-amber-700"></i>
                                    Commandements Centraux & Réserves
                                </p>
                                <p class="text-[11px] text-amber-800/90 mt-1">
                                    Structures nationales hors-sol (DGGN, GSIGN, Réserves ministérielles CDURM, Unités frontières CDUSF/GARSI, Écoles CDECF, Inspection, SIJ, Détachements).
                                </p>
                            </div>

                            <div class="space-y-2">
                                <div
                                    v-for="cReg in regionsCentrales"
                                    :key="cReg.id"
                                    class="bg-white rounded-xl border border-gray-200 p-3 shadow-sm hover:border-sky-300 transition-colors"
                                >
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <span class="px-2 py-0.5 text-[10px] font-bold bg-sky-100 text-sky-800 rounded">
                                                {{ cReg.code }}
                                            </span>
                                            <h4 class="text-xs font-bold text-gray-900 mt-1">{{ cReg.nom }}</h4>
                                            <p class="text-[11px] text-gray-500">{{ cReg.description }}</p>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-sm font-bold text-sky-900">{{ cReg.stats.total }}</span>
                                            <span class="text-[10px] text-gray-500 block">militaires</span>
                                        </div>
                                    </div>

                                    <div class="mt-2.5 pt-2 border-t border-gray-100 flex items-center justify-between text-xs text-gray-600">
                                        <span>{{ cReg.unites_count }} unités rattachées</span>
                                        <button
                                            v-if="cReg.latitude && cReg.longitude"
                                            @click="focusCentralHQ(cReg)"
                                            class="text-sky-700 hover:text-sky-900 font-semibold text-[11px] flex items-center gap-1"
                                        >
                                            <i class="pi pi-compass"></i> Pointer QG
                                        </button>
                                    </div>
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
import { MALI_BOUNDS, REGION_BOUNDS, MALI_11_REGIONS, MALI_BORDER, MALI_MASK } from './maliGeoData.js';

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

const activeSideTab = ref('territorial');
const selectedRegion = ref(null);
const selectedUnit = ref(null);
const searchQuery = ref('');
const filterDisplayMode = ref('all'); // 'all', 'regions', 'unites'
const filterOnlyOp = ref(false);

// Séparation des régions territoriales vs centrales
const regionsTerritoriales = computed(() => {
    return props.regions.filter(r => !r.is_central);
});

const regionsCentrales = computed(() => {
    return props.regions.filter(r => r.is_central);
});

// Régions avec coordonnées
const regionsAvecCoords = computed(() => {
    return props.regions.filter(r => r.latitude && r.longitude && !r.is_central);
});

// Unités avec coordonnées
const unitesAvecCoords = computed(() => {
    const list = [];
    props.regions.forEach(r => {
        if (r.unites) {
            r.unites.forEach(u => {
                if (u.latitude && u.longitude) {
                    list.push({ ...u, region_nom: r.nom, region_code: r.code });
                }
            });
        }
    });
    return list;
});

// Compteurs dynamiques selon le mode d'isolation
const activeRegionsCount = computed(() => {
    if (selectedRegion.value) return 1;
    return regionsAvecCoords.value.length;
});

const activeUnitsCount = computed(() => {
    if (selectedRegion.value) {
        return unitesAvecCoords.value.filter(u => u.region_code === selectedRegion.value.code).length;
    }
    return unitesAvecCoords.value.length;
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

// ===============================================================
// VRAIS PINS / ÉPINGLES POINTANT PRÉCISÉMENT SUR LA CARTE
// ===============================================================

/**
 * Pin pointé pour les Régions / États-Majors (Grand Commandement)
 * Point d'ancrage exact : le bout de la pointe [17px, 42px]
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
            <div style="position: absolute; top: 9px; left: 0; width: 34px; text-align: center; color: #ffffff; font-weight: 800; font-size: 10px; line-height: 1; pointer-events: none; letter-spacing: -0.5px;">
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
 * Point d'ancrage exact : le bout de la pointe [10px, 26px]
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
        maxBounds: L.latLngBounds([9.0, -13.5], [26.0, 5.5]),
        maxBoundsViscosity: 0.9,
    });

    // 1. Fond de carte OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap | Gendarmerie Nationale du Mali'
    }).addTo(map);

    // 2. Masque 100% opaque pour recouvrir TOUS les pays voisins
    maskLayer.value = L.geoJSON(MALI_MASK, {
        style: {
            fillColor: '#f8fafc',
            fillOpacity: 1.0,
            color: '#cbd5e1',
            weight: 1,
            interactive: false,
        }
    }).addTo(map);

    // 3. Contour officiel de la frontière nationale du Mali
    borderLayer.value = L.geoJSON(MALI_BORDER, {
        style: {
            color: '#0369a1',
            weight: 3.5,
            opacity: 1,
            fill: false,
            interactive: false,
        }
    }).addTo(map);

    // 4. Groupes de couches dynamiques
    regionsLayerGroup.value = L.layerGroup().addTo(map);
    markersLayerGroup.value = L.layerGroup().addTo(map);
    unitsLayerGroup.value = L.layerGroup().addTo(map);

    mapInstance.value = map;

    // 5. Rendu initial : Tout afficher par défaut
    updateMapDisplay();

    // 6. Centrer sur l'ensemble du territoire national
    map.fitBounds(MALI_BOUNDS, { padding: [25, 25] });
};

/**
 * Met à jour dynamiquement toutes les couches de la carte :
 * - Régions découpées (Isolées si une région est sélectionnée, ou toutes si vue par défaut)
 * - Pins de commandement
 * - Pins des brigades
 */
const updateMapDisplay = () => {
    renderRegions();
    renderRegionPins();
    renderUnitPins();
};

/**
 * Rendu dynamique des polygones des régions :
 * - Si sélectionnée : N'AFFICHE STRICTEMENT QUE LA RÉGION SÉLECTIONNÉE
 * - Par défaut : Affiche toutes les 11 régions
 */
const renderRegions = () => {
    if (!mapInstance.value || !regionsLayerGroup.value) return;
    regionsLayerGroup.value.clearLayers();

    const isIsolated = selectedRegion.value !== null;

    // Filtrer les features : n'avoir que la région pointée si isolée
    const featuresToShow = isIsolated
        ? MALI_11_REGIONS.features.filter(f => f.properties?.code === selectedRegion.value.code)
        : MALI_11_REGIONS.features;

    const layer = L.geoJSON({ type: 'FeatureCollection', features: featuresToShow }, {
        style: (feature) => {
            const isTarget = isIsolated && feature.properties?.code === selectedRegion.value?.code;
            return {
                fillColor: '#0284c7',
                fillOpacity: isTarget ? 0.35 : 0.18,
                color: isTarget ? '#0369a1' : '#0284c7',
                weight: isTarget ? 3.5 : 2,
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
                    const target = e.target;
                    target.setStyle({
                        weight: 3.5,
                        color: '#b45309',
                        dashArray: '',
                        fillOpacity: 0.45
                    });
                    target.bringToFront();
                },
                mouseout: (e) => {
                    layer.resetStyle(e.target);
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
 * - Si une région est isolée : n'afficher que le point de cette région
 * - Par défaut : afficher tous les points R1 à R11
 */
const renderRegionPins = () => {
    if (!mapInstance.value || !markersLayerGroup.value) return;
    markersLayerGroup.value.clearLayers();

    if (filterDisplayMode.value === 'unites') return;

    const list = selectedRegion.value
        ? regionsTerritoriales.value.filter(r => r.id === selectedRegion.value.id)
        : regionsTerritoriales.value;

    list.forEach(reg => {
        if (!reg.latitude || !reg.longitude) return;

        const isSelected = selectedRegion.value?.id === reg.id;
        const icon = createRegionPin(reg.code, reg.stats?.total || 0, isSelected);

        const marker = L.marker([reg.latitude, reg.longitude], {
            icon,
            zIndexOffset: 1000
        });

        marker.on('click', () => {
            selectAndFocusRegion(reg);
        });

        marker.bindPopup(`
            <div style="font-size: 12px; min-width: 170px; line-height: 1.4;">
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
 * Pointer les Unités / Brigades de terrain sur la carte :
 * - Si une région est sélectionnée : n'afficher QUE les unités de cette région
 * - Par défaut : afficher toutes les unités
 */
const renderUnitPins = () => {
    if (!mapInstance.value || !unitsLayerGroup.value) return;
    unitsLayerGroup.value.clearLayers();

    if (filterDisplayMode.value === 'regions') return;

    let list = unitesAvecCoords.value;

    if (selectedRegion.value) {
        list = list.filter(u => u.region_code === selectedRegion.value.code);
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
            const reg = props.regions.find(r => r.code === u.region_code);
            if (reg && (!selectedRegion.value || selectedRegion.value.id !== reg.id)) {
                selectAndFocusRegion(reg);
            }
        });

        marker.bindPopup(`
            <div style="font-size: 12px; min-width: 160px; line-height: 1.4;">
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
 * Sélectionne une région et active l'isolation ("qu'il n'affiche que cette carte")
 */
const selectAndFocusRegion = (reg) => {
    selectedRegion.value = reg;
    selectedUnit.value = null;
    activeSideTab.value = 'territorial';

    // 1. Mise à jour dynamique immédiate
    updateMapDisplay();

    // 2. Zoom immédiat sur les bornes géographiques de cette région
    if (mapInstance.value) {
        const bounds = REGION_BOUNDS[reg.code];
        if (bounds) {
            mapInstance.value.flyToBounds(bounds, {
                padding: [40, 40],
                maxZoom: 11,
                duration: 1.2
            });
        } else if (reg.latitude && reg.longitude) {
            mapInstance.value.flyTo([reg.latitude, reg.longitude], 9, { duration: 1.2 });
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
        mapInstance.value.flyTo([unite.latitude, unite.longitude], 12, {
            duration: 1.2
        });
    }
};

/**
 * Pointer le QG d'un commandement central (GSIGN, DGGN, etc.)
 */
const focusCentralHQ = (cReg) => {
    if (mapInstance.value && cReg.latitude && cReg.longitude) {
        mapInstance.value.flyTo([cReg.latitude, cReg.longitude], 13, { duration: 1.2 });
    }
};

/**
 * Réinitialiser la vue complète :
 * Par défaut, tout afficher (toutes les 11 régions, tous les pins, tout le Mali)
 */
const resetToFullMaliView = () => {
    selectedRegion.value = null;
    selectedUnit.value = null;

    // 1. Restauration dynamique de toutes les couches
    updateMapDisplay();

    // 2. Recentrer la caméra sur tout le Mali
    if (mapInstance.value) {
        mapInstance.value.flyToBounds(MALI_BOUNDS, {
            padding: [25, 25],
            duration: 1.2
        });
    }
};

// Watchers
watch(filterDisplayMode, () => {
    renderRegionPins();
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
    background-color: #f1f5f9;
}
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
