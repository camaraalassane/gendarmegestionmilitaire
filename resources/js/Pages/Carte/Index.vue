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
                <a
                    :href="route('carte.export', sel ? { region: sel } : {})"
                    class="flex items-center gap-1.5 text-[11px] font-semibold text-white bg-emerald-600 hover:bg-emerald-500 border border-emerald-400/50 px-2.5 py-1 rounded-md shadow-sm whitespace-nowrap transition-colors"
                    :title="sel ? 'Exporter les statistiques de cette région en Excel' : 'Exporter les statistiques de la carte en Excel'"
                >
                    <i class="pi pi-file-excel text-xs"></i>
                    <span>{{ sel ? 'Exporter la région' : 'Exporter Excel' }}</span>
                </a>
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
                        class="px-2.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-md font-bold text-xs flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer"
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
                                        :class="parcelFilterType === 'with_eff' ? 'bg-red-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
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

                        <svg
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
                                        @mouseleave="onLeaveHover"
                                    />
                                </template>

                                <!-- Vue nationale : BADGES TACTIQUES HAUTE VISIBILITÉ DES RÉGIONS & CHIFFRES CLAIRS -->
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

                                <!-- 2. Découpage en parcelles Voronoi clipsé à la forme exacte de la région -->
                                <g clip-path="url(#region-clip-shape)">
                                    <template v-for="p in regionalParcels" :key="'parcel-' + p.unit.id">
                                        <path
                                            v-if="p.path && isUnitVisible(p.unit)"
                                            :d="p.path"
                                            :fill="parcelFill(p.unit)"
                                            stroke="#0f172a"
                                            :stroke-width="0.22 * rUnit"
                                            stroke-linejoin="round"
                                            class="cursor-pointer transition-colors duration-150 hover:brightness-110"
                                            @click="handleUnitClick(p.unit.id)"
                                            @mouseenter="onHoverUnitParcel(p.unit)"
                                            @mouseleave="onLeaveHover"
                                        />
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

                                <!-- 3. Contour de la parcelle sélectionnée -->
                                <template v-if="selParcel && isUnitVisible(selParcel.unit) && selParcel.path">
                                    <path
                                        :d="selParcel.path"
                                        clip-path="url(#region-clip-shape)"
                                        fill="none"
                                        stroke="#0284c7"
                                        :stroke-width="0.5 * rUnit"
                                        class="pointer-events-none"
                                    />
                                </template>

                                <!-- 4. Bordure extérieure nette de la région en noir épais -->
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

                                        <!-- Cercle proportionnel rouge Jacques Bertin avec contour noir prononcé (pointillé si position approx.) -->
                                        <circle
                                            v-if="parcelCircleR(p.unit) > 0"
                                            :cx="p.cx"
                                            :cy="p.cy"
                                            :r="parcelCircleR(p.unit)"
                                            fill="#ef4444"
                                            :stroke="selUnit === p.unit.id ? '#0284c7' : '#000000'"
                                            :stroke-width="(selUnit === p.unit.id ? 0.55 : 0.35) * rUnit"
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
                                        <!-- Chiffre de l'effectif au centre du cercle proportionnel (grand, noir, gras 900) -->
                                        <text
                                            v-if="p.unit.effectif > 0 && parcelCircleFontSize(p.unit) > 0"
                                            :x="p.cx"
                                            :y="p.cy"
                                            text-anchor="middle"
                                            dominant-baseline="central"
                                            :font-size="parcelCircleFontSize(p.unit) + 'px'"
                                            font-weight="900"
                                            fill="#000000"
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
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <!-- Règle 1 : Cercle effectif -->
                                <span class="flex items-center gap-1.5 font-semibold text-slate-800">
                                    <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-red-500 border border-black text-[8px] font-black text-black">N</span>
                                    <span>Cercle rouge : Effectif de l'unité</span>
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

                                <!-- Règle 3 : Secteurs géographiques -->
                                <span class="flex items-center font-medium text-slate-600" title="Zone de compétence géographique assignée à chaque unité">
                                    Secteurs des unités
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
                            <span>{{ sel ? 'Unités (' + selRegion.unites.length + ')' : 'Régions (' + regions.length + ')' }}</span>
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
                                            class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded font-bold text-[11px] flex items-center gap-1 shadow-xs transition-colors"
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
                        class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded font-bold text-[11px] shrink-0 cursor-pointer flex items-center gap-1 shadow-2xs"
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
                                        target="_blank"
                                        class="px-2 py-1 bg-slate-100 hover:bg-sky-100 hover:text-sky-800 text-slate-700 border border-slate-300 rounded font-semibold text-[11px] inline-flex items-center gap-1 transition-colors"
                                        title="Voir la fiche détaillée du militaire"
                                    >
                                        <i class="pi pi-eye text-[10px]"></i>
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
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { MALI_VIEW, REGION_SHAPES as SHAPES, project } from './maliRegions.js';
import { Delaunay } from 'd3-delaunay';

const props = defineProps({
    regions: { type: Array, default: () => [] },
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

const exporterStatutExcel = () => {
    const params = new URLSearchParams({ type: statutModalType.value });
    if (sel.value) {
        params.append('region', sel.value);
    }
    window.location.href = `/carte/export-statut?${params.toString()}`;
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
    eff: { label: 'Effectif', unit: '' },
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
    choisir(code);
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
            path: `M ${minX - 10} ${minY - 10} H ${maxX + 10} V ${maxY + 10} H ${minX - 10} Z`,
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
            // L'unité reste strictement au même endroit même lors d'un filtrage ou d'une recherche
            // Regroupées autour du chef-lieu de la région (sinon centre du tracé), sur un petit cercle
            const sansGps = allRegionUnits.filter(u => !(u.lat !== null && u.lon !== null && !isNaN(Number(u.lat)) && !isNaN(Number(u.lon))));
            const rg = sansGps.findIndex(u => u.id === p.unit.id);
            const stableSeed = rg >= 0 ? rg : (p.unit.id % 50);
            const k = sel.value === 'R1' ? 4.0 : 1.0;   // Bamako est tracée agrandie 4 fois

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

    const pad = Math.max(pw, ph) * 0.25;
    const bounds = [minX - pad, minY - pad, maxX + pad, maxY + pad];

    // Lloyd relaxation modérée (1 passe pondérée) pour équilibrer les frontières territoriales
    // tout en maintenant l'ancrage sur la position géographique réelle (80% GPS, 20% Centroïde)
    if (n <= 150) {
        try {
            const v = Delaunay.from(points).voronoi(bounds);
            const newPoints = [];
            for (let i = 0; i < n; i++) {
                const poly = v.cellPolygon(i);
                if (poly && poly.length > 2) {
                    let sx = 0, sy = 0;
                    const len = poly.length - 1;
                    for (let k = 0; k < len; k++) {
                        sx += poly[k][0];
                        sy += poly[k][1];
                    }
                    const px = sx / len;
                    const py = sy / len;
                    const targetX = points[i][0] * 0.8 + px * 0.2;
                    const targetY = points[i][1] * 0.8 + py * 0.2;
                    if (isPointInRings(targetX, targetY, rings)) {
                        newPoints.push([targetX, targetY]);
                    } else {
                        newPoints.push(points[i]);
                    }
                } else {
                    newPoints.push(points[i]);
                }
            }
            points = newPoints;
        } catch (e) {
            // Continuer sans relaxation en cas d'exception
        }
    }

    // Séparation anti-collision : SEULEMENT si n <= 150 (évite pénalité O(n²) sur grandes volumétries)
    if (n > 5 && n <= 150) {
        for (let pass = 0; pass < 2; pass++) {
            for (let i = 0; i < n; i++) {
                for (let j = i + 1; j < n; j++) {
                    const rI = parcelCircleR(units[i]);
                    const rJ = parcelCircleR(units[j]);
                    if (rI === 0 && rJ === 0) continue;
                    const targetDist = rI + rJ + 0.6;
                    const dx = points[j][0] - points[i][0];
                    const dy = points[j][1] - points[i][1];
                    const dist = Math.hypot(dx, dy);
                    if (dist > 0 && dist < targetDist) {
                        const overlap = (targetDist - dist) * 0.5;
                        const nx = (dx / dist) * overlap;
                        const ny = (dy / dist) * overlap;
                        const nxI = points[i][0] - nx, nyI = points[i][1] - ny;
                        const nxJ = points[j][0] + nx, nyJ = points[j][1] + ny;
                        if (isPointInRings(nxI, nyI, rings)) { points[i][0] = nxI; points[i][1] = nyI; }
                        if (isPointInRings(nxJ, nyJ, rings)) { points[j][0] = nxJ; points[j][1] = nyJ; }
                    }
                }
            }
        }
    }

    try {
        const finalV = Delaunay.from(points).voronoi(bounds);
        return units.map((u, i) => {
            const cellPath = finalV.renderCell(i);
            const path = (cellPath && cellPath.length > 5) ? cellPath : '';
            return {
                unit: u,
                path,
                cx: points[i][0],
                cy: points[i][1]
            };
        });
    } catch (e) {
        console.warn('Voronoi calculation fallback:', e);
        return units.map((u, i) => ({
            unit: u,
            path: '',
            cx: points[i] ? points[i][0] : cx,
            cy: points[i] ? points[i][1] : cy
        }));
    }
});

const selParcel = computed(() => {
    if (!selUnit.value) return null;
    return (regionalParcels.value || []).find(p => p.unit.id === selUnit.value);
});

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
            { label: 'Régions', value: props.regions.length },
            { label: 'Total Unités', value: tot((r) => r.unites.length) },
            { label: 'Effectif Global', value: fmt(tot((r) => r.effectif) + (Number(props.stats?.non_rattaches) || 0)) },
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
