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

        <div class="space-y-3">
            <!-- Barre d'outils et filtres compacte -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 p-2.5 sm:p-3">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-2.5">
                    <!-- Recherche globale -->
                    <div class="relative flex-1 max-w-md">
                        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Recherche rapide dans la carte (brigade, ville...)"
                            class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:ring-1 focus:ring-sky-500 focus:bg-white transition-colors placeholder:text-slate-400"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs"
                        >
                            <i class="pi pi-times text-[10px]"></i>
                        </button>
                    </div>

                    <!-- Contrôles des points pointés sur la carte -->
                    <div class="flex items-center gap-2 flex-wrap text-xs">
                        <!-- Mode d'affichage des points -->
                        <div class="inline-flex rounded-lg border border-slate-200 p-0.5 bg-slate-50">
                            <button
                                @click="filterDisplayMode = 'all'"
                                :class="[
                                    'px-2.5 py-1 rounded-md font-medium transition-colors text-xs',
                                    filterDisplayMode === 'all'
                                        ? 'bg-sky-800 text-white shadow-xs'
                                        : 'text-slate-600 hover:text-slate-900'
                                ]"
                            >
                                Tous les points ({{ activeRegionsCount + activeUnitsCount }})
                            </button>
                            <button
                                @click="filterDisplayMode = 'regions'"
                                :class="[
                                    'px-2.5 py-1 rounded-md font-medium transition-colors text-xs',
                                    filterDisplayMode === 'regions'
                                        ? 'bg-sky-800 text-white shadow-xs'
                                        : 'text-slate-600 hover:text-slate-900'
                                ]"
                            >
                                Régions seules ({{ activeRegionsCount }})
                            </button>
                            <button
                                @click="filterDisplayMode = 'unites'"
                                :class="[
                                    'px-2.5 py-1 rounded-md font-medium transition-colors text-xs',
                                    filterDisplayMode === 'unites'
                                        ? 'bg-sky-800 text-white shadow-xs'
                                        : 'text-slate-600 hover:text-slate-900'
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
                                'px-2.5 py-1 rounded-lg font-medium border transition-all flex items-center gap-1.5 shadow-2xs text-xs',
                                filterOnlyOp
                                    ? 'bg-emerald-600 text-white border-emerald-700'
                                    : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
                            ]"
                        >
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            <span>Opérationnelles</span>
                        </button>

                        <!-- Bouton Tout le Mali / Réinitialiser -->
                        <button
                            @click="resetToFullMaliView"
                            :class="[
                                'px-2.5 py-1 rounded-lg border font-bold transition-all flex items-center gap-1.5 shadow-2xs text-xs',
                                selectedRegion !== null
                                    ? 'bg-amber-500 hover:bg-amber-600 text-slate-950 border-amber-600 ring-2 ring-amber-300 animate-pulse'
                                    : 'bg-sky-50 hover:bg-sky-100 text-sky-800 border-sky-200'
                            ]"
                            title="Afficher tout le Mali (vue générale complète)"
                        >
                            <i class="pi pi-globe text-sky-700 text-xs"></i>
                            <span>{{ selectedRegion ? '← Afficher tout le Mali' : 'Recentrer Mali' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Grille principale : Carte + Volet latéral -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5">
                <!-- Zone Carte : occupe 8 col sur lg, 9 col sur xl (75% largeur) quand le volet est ouvert, ou 12 col quand il est fermé ! -->
                <div :class="[
                    isSidebarOpen ? 'lg:col-span-8 xl:col-span-9' : 'col-span-12',
                    'bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden flex flex-col relative transition-all duration-300',
                    'h-[calc(100vh-210px)] min-h-[660px] lg:min-h-[750px]'
                ]">
                    <!-- En-tête statut carte -->
                    <div class="bg-white/95 backdrop-blur-sm px-3.5 py-2 border-b border-slate-200 flex items-center justify-between text-xs text-slate-700 z-10 shrink-0">
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="font-bold text-slate-900 tracking-wide">RÉPUBLIQUE DU MALI</span>
                            <span v-if="selectedRegion" class="text-sky-900 font-bold ml-1 bg-sky-100 px-2 py-0.5 rounded text-[11px]">
                                ▸ Vue isolée : {{ selectedRegion.nom }} ({{ selectedRegion.code }})
                            </span>
                            <span v-else class="text-emerald-700 font-medium ml-1 text-[11px] hidden sm:inline">
                                ▸ Vue générale par défaut (11 Régions)
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Légende miniature -->
                            <div class="hidden md:flex items-center gap-2.5 text-slate-500 text-[11px] pr-2 border-r border-slate-200">
                                <span class="flex items-center gap-1">
                                    <span class="w-2.5 h-2.5 rounded-full bg-sky-700 border border-white inline-block"></span>
                                    Régions
                                </span>
                                <span class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 border border-white inline-block"></span>
                                    Opér.
                                </span>
                                <span class="flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-red-500 border border-white inline-block"></span>
                                    Non op.
                                </span>
                            </div>

                            <!-- Bouton Agrandir / Réduire la carte (Toggle volet) -->
                            <button
                                @click="toggleSidebar"
                                class="px-2.5 py-1 text-xs rounded-lg border border-slate-200 bg-slate-50 hover:bg-white text-slate-700 font-medium transition-all flex items-center gap-1.5 shadow-2xs hover:border-slate-300"
                                :title="isSidebarOpen ? 'Masquer le volet pour agrandir la carte au maximum (100% largeur)' : 'Afficher le volet latéral des régions'"
                            >
                                <i :class="isSidebarOpen ? 'pi pi-arrows-alt text-sky-700' : 'pi pi-list text-sky-700'"></i>
                                <span>{{ isSidebarOpen ? 'Agrandir la carte' : 'Afficher volet' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Carte Leaflet -->
                    <div id="map-mali" class="flex-1 w-full h-full min-h-[580px] z-0 bg-slate-100"></div>

                    <!-- Bouton flottant de retour en haut de la carte si région pointée -->
                    <div v-if="selectedRegion" class="absolute top-12 left-3 z-[400] flex items-center gap-2">
                        <button
                            @click="resetToFullMaliView"
                            class="bg-sky-900/95 hover:bg-sky-800 text-white px-3 py-1.5 rounded-lg shadow-xl font-bold text-xs flex items-center gap-1.5 border border-sky-400 backdrop-blur-sm transition-all"
                            title="Quitter la vue isolée et réafficher tout le Mali"
                        >
                            <i class="pi pi-arrow-left text-amber-300 text-xs"></i>
                            <span>Afficher tout le Mali</span>
                        </button>
                        <div class="bg-white/95 backdrop-blur-sm text-sky-900 font-bold px-3 py-1.5 rounded-lg shadow-md border border-slate-200 text-xs flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                            <span>{{ selectedRegion.nom }} • {{ selectedRegion.chef_lieu }}</span>
                        </div>
                    </div>

                    <!-- Bouton flottant réouvrir le volet si masqué -->
                    <button
                        v-if="!isSidebarOpen"
                        @click="toggleSidebar"
                        class="absolute top-12 right-3 z-[400] bg-white/95 hover:bg-white text-sky-900 font-bold px-3 py-1.5 rounded-lg shadow-xl text-xs flex items-center gap-1.5 border border-sky-300 backdrop-blur-sm transition-all"
                        title="Réouvrir le volet latéral des régions"
                    >
                        <i class="pi pi-list text-sky-700"></i>
                        <span>Volet Régions (11)</span>
                    </button>

                    <!-- Barre de sélection rapide au bas de la carte (ultra-légère) -->
                    <div class="absolute bottom-3 left-3 right-3 z-[400] pointer-events-none">
                        <div class="bg-white/90 backdrop-blur-md px-2 py-1.5 rounded-xl border border-slate-200/90 shadow-lg pointer-events-auto overflow-x-auto no-scrollbar flex items-center gap-1">
                            <!-- Bouton Tout le Mali -->
                            <button
                                @click="resetToFullMaliView"
                                :class="[
                                    'px-2.5 py-1 text-xs rounded-lg font-bold whitespace-nowrap transition-all shrink-0 border flex items-center gap-1',
                                    selectedRegion === null
                                        ? 'bg-sky-900 text-white border-sky-950 shadow-xs ring-1 ring-sky-300'
                                        : 'bg-white text-slate-700 border-slate-200 hover:bg-sky-50 hover:text-sky-800'
                                ]"
                            >
                                <i class="pi pi-globe text-xs"></i>
                                <span>Tout le Mali</span>
                            </button>

                            <span class="text-slate-300 px-0.5">|</span>

                            <!-- Boutons de pointage direct pour chaque région R1 à R11 -->
                            <button
                                v-for="reg in regionsTerritoriales"
                                :key="reg.id"
                                @click="selectAndFocusRegion(reg)"
                                :class="[
                                    'px-2 py-0.5 text-xs rounded-md font-semibold whitespace-nowrap transition-all shrink-0 border',
                                    selectedRegion?.id === reg.id
                                        ? 'bg-amber-500 text-slate-950 border-amber-600 shadow-xs ring-1 ring-amber-300 font-bold'
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-sky-50 hover:text-sky-700'
                                ]"
                            >
                                📍 {{ reg.code }} <span class="text-[10px] opacity-75 font-normal">({{ reg.stats?.total || 0 }})</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Volet latéral de Commandement : Léger, moderne et bien organisé -->
                <div v-show="isSidebarOpen" class="lg:col-span-4 xl:col-span-3 flex flex-col h-[calc(100vh-210px)] min-h-[660px] lg:min-h-[750px]">
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
                                    class="text-[11px] text-sky-700 hover:text-sky-900 font-semibold flex items-center gap-1 bg-white px-2 py-1 rounded-md border border-slate-200 hover:border-slate-300 transition-colors shadow-2xs"
                                    title="Revenir à la liste de toutes les régions"
                                >
                                    <i class="pi pi-arrow-left text-[10px]"></i>
                                    <span>Toutes</span>
                                </button>

                                <button
                                    @click="toggleSidebar"
                                    class="text-slate-400 hover:text-slate-700 p-1 rounded-md hover:bg-slate-200/60 transition-colors"
                                    title="Réduire le volet pour agrandir la carte"
                                >
                                    <i class="pi pi-times text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <!-- CONTENU DU VOLET -->
                        <div class="flex-1 overflow-hidden flex flex-col">
                            <!-- CAS A : UNE RÉGION EST POINTÉE ET ISOLÉE -->
                            <div v-if="selectedRegion" class="flex flex-col flex-1 overflow-hidden p-3 space-y-2.5">
                                <!-- En-tête Région épuré -->
                                <div class="bg-gradient-to-r from-slate-900 via-sky-950 to-slate-900 rounded-lg p-3 text-white shadow-xs shrink-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="px-1.5 py-0.5 text-[10px] font-bold bg-amber-400 text-slate-950 rounded tracking-wider">
                                                    {{ selectedRegion.code }}
                                                </span>
                                                <h3 class="text-xs font-bold text-white">{{ selectedRegion.nom }}</h3>
                                            </div>
                                            <p v-if="selectedRegion.chef_lieu" class="text-[11px] text-sky-200 flex items-center gap-1 mt-1">
                                                <i class="pi pi-map-marker text-amber-300 text-[10px]"></i>
                                                Chef-lieu : <strong class="text-white">{{ selectedRegion.chef_lieu }}</strong>
                                            </p>
                                        </div>

                                        <button
                                            @click="resetToFullMaliView"
                                            class="bg-white/10 hover:bg-white/20 rounded px-2 py-1 text-[11px] text-amber-300 border border-white/15 transition-colors flex items-center gap-1 shrink-0"
                                            title="Revenir à la vue complète de tout le Mali"
                                        >
                                            <i class="pi pi-times text-[10px]"></i>
                                            <span>Fermer</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Indicateurs clés compacts (3 colonnes) -->
                                <div class="grid grid-cols-3 gap-1.5 shrink-0">
                                    <div class="bg-sky-50/70 border border-sky-100 rounded-lg p-1.5 text-center">
                                        <span class="text-[9px] uppercase font-bold text-sky-700 block">Total</span>
                                        <span class="text-base font-extrabold text-sky-950 leading-tight">{{ selectedRegion.stats.total }}</span>
                                        <span class="text-[9px] text-slate-500 block">Gendarmes</span>
                                    </div>
                                    <div class="bg-emerald-50/70 border border-emerald-100 rounded-lg p-1.5 text-center">
                                        <span class="text-[9px] uppercase font-bold text-emerald-700 block">Présents</span>
                                        <span class="text-base font-extrabold text-emerald-800 leading-tight">{{ selectedRegion.stats.presents }}</span>
                                        <span class="text-[9px] text-emerald-600 block">{{ selectedRegion.stats.taux_presence }}%</span>
                                    </div>
                                    <div class="bg-amber-50/70 border border-amber-100 rounded-lg p-1.5 text-center">
                                        <span class="text-[9px] uppercase font-bold text-amber-700 block">Absents</span>
                                        <span class="text-base font-extrabold text-amber-800 leading-tight">{{ selectedRegion.stats.absents }}</span>
                                        <span class="text-[9px] text-amber-600 block">Indispos</span>
                                    </div>
                                </div>

                                <!-- Effectif hommes / femmes -->
                                <div class="bg-slate-50 rounded-lg p-2 border border-slate-200/80 text-[11px] text-slate-600 flex items-center justify-between shrink-0">
                                    <span>Effectif :</span>
                                    <span class="font-medium text-slate-800">
                                        <strong>{{ selectedRegion.stats.hommes }}</strong> H • <strong>{{ selectedRegion.stats.femmes }}</strong> F
                                    </span>
                                </div>

                                <!-- Barre de filtre des brigades pour cette région -->
                                <div class="relative shrink-0">
                                    <i class="pi pi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                    <input
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="Filtrer les brigades..."
                                        class="w-full pl-8 pr-7 py-1 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:ring-1 focus:ring-sky-500 focus:bg-white transition-all placeholder:text-slate-400"
                                    />
                                    <button
                                        v-if="searchQuery"
                                        @click="searchQuery = ''"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs"
                                    >
                                        <i class="pi pi-times text-[10px]"></i>
                                    </button>
                                </div>

                                <!-- Liste des brigades légères -->
                                <div class="flex-1 overflow-y-auto pr-1 space-y-1.5 custom-scrollbar">
                                    <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500 px-0.5 pb-0.5">
                                        <span>Points d'unités ({{ filteredRegionUnits.length }})</span>
                                        <span class="text-emerald-600 text-[10px]">{{ selectedRegion.unites_operationnelles_count }} op.</span>
                                    </div>

                                    <div
                                        v-for="unite in filteredRegionUnits"
                                        :key="unite.id"
                                        @click="focusOnUnit(unite)"
                                        :class="[
                                            'p-2 rounded-lg border text-xs cursor-pointer transition-all flex flex-col gap-1',
                                            selectedUnit?.id === unite.id
                                                ? 'bg-sky-50 border-sky-400 ring-1 ring-sky-300'
                                                : 'bg-white border-slate-200 hover:border-sky-300 hover:bg-sky-50/30'
                                        ]"
                                    >
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="font-bold text-xs text-slate-800 truncate" :title="unite.nom">
                                                📍 {{ unite.nom }}
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

                            <!-- CAS B : AUCUNE RÉGION N'EST POINTÉE (PAR DÉFAUT : LISTE LÉGÈRE ET ORGANISÉE DES RÉGIONS) -->
                            <div v-else class="flex flex-col flex-1 overflow-hidden p-3 space-y-2.5">
                                <!-- Recherche rapide et filtre de région -->
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

                                <!-- Indication discrète et élégante (remplace le pavé bleu lourd) -->
                                <div class="flex items-center justify-between text-[11px] text-slate-500 px-0.5 shrink-0">
                                    <span class="flex items-center gap-1 text-slate-600">
                                        <i class="pi pi-compass text-sky-600"></i>
                                        <span>Cliquez pour isoler un secteur</span>
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-medium">
                                        {{ filteredRegions.length }} / {{ regionsTerritoriales.length }}
                                    </span>
                                </div>

                                <!-- Liste légère des 11 Régions (cartes compactes, aérées et fluides) -->
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

const isSidebarOpen = ref(true);
const regionSearchQuery = ref('');
const activeSideTab = ref('territorial');
const selectedRegion = ref(null);
const selectedUnit = ref(null);
const searchQuery = ref('');
const filterDisplayMode = ref('all'); // 'all', 'regions', 'unites'
const filterOnlyOp = ref(false);

// Toutes les régions sont des régions territoriales officielles du Mali
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

// Régions avec coordonnées
const regionsAvecCoords = computed(() => {
    return props.regions.filter(r => r.latitude && r.longitude);
});

// Unités avec coordonnées
const unitesAvecCoords = computed(() => {
    const list = [];
    props.regions.forEach(r => {
        if (r.unites) {
            r.unites.forEach(u => {
                if (u.latitude && u.longitude) {
                    list.push({ ...u, region_id: r.id, region_nom: r.nom, region_code: r.code });
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
        return unitesAvecCoords.value.filter(u => u.region_id === selectedRegion.value.id || u.region_code === selectedRegion.value.code).length;
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
    // Adapter le contour de frontière nationale selon le mode
    if (borderLayer.value) {
        if (selectedRegion.value) {
            borderLayer.value.setStyle({ opacity: 0.15, weight: 1.5 });
        } else {
            borderLayer.value.setStyle({ opacity: 0.95, weight: 3 });
        }
    }
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
                fillOpacity: isTarget ? 0.32 : 0.16,
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
        ? regionsTerritoriales.value.filter(r => r.id === selectedRegion.value.id || r.code === selectedRegion.value.code)
        : regionsTerritoriales.value;

    list.forEach(reg => {
        if (!reg.latitude || !reg.longitude) return;

        const isSelected = selectedRegion.value?.id === reg.id || selectedRegion.value?.code === reg.code;
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
        list = list.filter(u => u.region_id === selectedRegion.value.id || u.region_code === selectedRegion.value.code);
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
            const reg = props.regions.find(r => r.code === u.region_code || r.id === u.region_id);
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
    isSidebarOpen.value = true; // S'assurer que le volet s'ouvre pour voir les détails de cette région

    // 1. Mise à jour dynamique immédiate
    updateMapDisplay();

    // 2. Zoom immédiat sur les bornes géographiques de cette région
    if (mapInstance.value) {
        const bounds = REGION_BOUNDS[reg.code];
        if (bounds) {
            mapInstance.value.flyToBounds(bounds, {
                padding: [45, 45],
                maxZoom: 10,
                duration: 1.0
            });
        } else if (reg.latitude && reg.longitude) {
            mapInstance.value.flyTo([reg.latitude, reg.longitude], 9, { duration: 1.0 });
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
