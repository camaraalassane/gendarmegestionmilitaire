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
                class="flex-1 min-h-0 grid gap-2 items-stretch transition-all duration-200"
                :class="[
                    isFullscreen
                        ? 'fixed inset-0 z-50 p-2 sm:p-3 bg-slate-100 w-screen h-screen ' + (showSidePanelInFullscreen ? 'lg:grid-cols-[1fr_320px] xl:grid-cols-[1fr_340px]' : 'grid-cols-1')
                        : 'lg:grid-cols-[1fr_320px] xl:grid-cols-[1fr_340px]'
                ]"
            >
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
                        class="flex-1 min-h-[480px] lg:min-h-[540px] w-full h-full relative flex items-center justify-center overflow-hidden bg-[#16212e] rounded-lg border border-[#263446] p-1 cursor-grab select-none"
                        :class="{ '!cursor-grabbing': isDragging }"
                        @mousedown="onMouseDown"
                        @wheel.prevent="onWheel"
                        @touchstart="onTouchStart"
                        @touchmove="onTouchMove"
                        @touchend="onTouchEnd"
                        @dblclick="onDblClick"
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
                                :title="showSidePanelInFullscreen ? 'Masquer le volet latéral pour afficher la carte en 100% de largeur' : 'Afficher le volet latéral avec les unités'"
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
                                    @click="handleRegionClick(r.code)"
                                    @mouseenter="onHoverRegion(r)"
                                    @mouseleave="onLeaveHover"
                                />
                            </template>

                            <!-- Vue nationale : BADGES TACTIQUES HAUTE VISIBILITÉ DES RÉGIONS & CHIFFRES CLAIRS -->
                            <template v-if="!sel">
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

                            <!-- Région sélectionnée : PINS TACTIQUES HAUTE VISIBILITÉ DES UNITÉS -->
                            <template v-else>
                                <g
                                    v-for="u in unitesAvecPoint"
                                    :key="u.id"
                                    class="unit-marker cursor-pointer"
                                    @click="handleUnitClick(u.id)"
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
                <div
                    v-show="!isFullscreen || showSidePanelInFullscreen"
                    class="bg-white rounded-lg border border-slate-200 p-2.5 min-w-0 flex flex-col h-full overflow-hidden shadow-sm text-slate-900 transition-all"
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
                    <div class="grid grid-cols-3 gap-1.5 my-1.5 p-1.5 border border-slate-100 flex-none bg-slate-50/80 rounded-md text-center">
                        <div v-for="d in detail" :key="d.label" class="min-w-0">
                            <div class="text-[10px] text-slate-500 truncate">{{ d.label }}</div>
                            <div class="text-xs sm:text-sm font-bold text-slate-900 tabular-nums truncate">
                                {{ d.value }}
                            </div>
                        </div>
                    </div>

                    <!-- Onglets de bascule : Liste vs Détail Effectif -->
                    <div class="flex items-center bg-slate-100 p-0.5 rounded-lg text-xs font-medium my-1.5 flex-none">
                        <button
                            type="button"
                            @click="sidebarTab = 'liste'"
                            class="flex-1 py-1 px-2 rounded-md transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                            :class="sidebarTab === 'liste' ? 'bg-white text-sky-900 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        >
                            <i class="pi pi-list text-[10px]"></i>
                            <span>{{ sel ? 'Unités (' + selRegion.unites.length + ')' : 'Régions (' + regions.length + ')' }}</span>
                        </button>
                        <button
                            type="button"
                            @click="sidebarTab = 'effectif'"
                            class="flex-1 py-1 px-2 rounded-md transition-all flex items-center justify-center gap-1.5 cursor-pointer"
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

                        <!-- LISTE DÉROULANTE INTERNE (PLEINE HAUTEUR - FLUIDE ET SCROLLABLE) -->
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
                    </template>

                    <!-- VUE 2 : DÉTAIL DE L'EFFECTIF (AFFICHÉ QUAND ON CLIQUE SUR L'ONGLET) -->
                    <template v-else>
                        <div class="flex-1 min-h-0 overflow-y-auto pr-1 space-y-2.5 custom-scrollbar text-xs">
                            <!-- En activité -->
                            <div class="p-2.5 rounded-lg border border-slate-200 bg-slate-50/70 space-y-2">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-700 flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        <span>En activité ({{ fmt(sel ? selRegion.effectif : tot(r => r.effectif)) }})</span>
                                    </span>
                                    <span class="text-[9px] text-slate-400 font-normal">Somme = Effectif</span>
                                </div>
                                <div class="grid grid-cols-2 gap-1.5 text-xs">
                                    <div class="flex items-center justify-between p-2 rounded bg-white border border-slate-200/80">
                                        <span class="text-slate-600">Actifs au poste</span>
                                        <strong class="text-slate-900 tabular-nums font-bold">{{ fmt(valStat('actifs')) }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between p-2 rounded bg-white border border-slate-200/80">
                                        <span class="text-slate-600">Détachés</span>
                                        <strong class="text-slate-900 tabular-nums font-bold">{{ fmt(valStat('detaches')) }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between p-2 rounded bg-white border border-slate-200/80">
                                        <span class="text-slate-600">En mission</span>
                                        <strong class="text-slate-900 tabular-nums font-bold">{{ fmt(valStat('missions')) }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between p-2 rounded bg-white border border-slate-200/80">
                                        <span class="text-slate-600">En stage</span>
                                        <strong class="text-slate-900 tabular-nums font-bold">{{ fmt(valStat('stages')) }}</strong>
                                    </div>
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
                                    <div class="flex items-center justify-between p-2 rounded bg-white border border-slate-200/80">
                                        <span class="text-slate-600">Radiés</span>
                                        <strong class="text-slate-900 tabular-nums font-bold">{{ fmt(valStat('radies')) }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between p-2 rounded bg-white border border-slate-200/80">
                                        <span class="text-slate-600">Retraites {{ props.stats.annee ?? new Date().getFullYear() }}</span>
                                        <strong class="text-slate-900 tabular-nums font-bold">{{ fmt(valStat('retraites')) }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between p-2 rounded bg-white border border-slate-200/80">
                                        <span class="text-slate-600">Déserteurs</span>
                                        <strong class="text-slate-900 tabular-nums font-bold">{{ fmt(valStat('deserteurs')) }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between p-2 rounded bg-white border border-slate-200/80">
                                        <span class="text-slate-600">Décédés {{ props.stats.annee ?? new Date().getFullYear() }}</span>
                                        <strong class="text-slate-900 tabular-nums font-bold">{{ fmt(valStat('decedes')) }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Guide d'interaction en bas -->
                    <p class="text-[10px] text-slate-400 mt-1 pt-1 border-t border-slate-100 flex-none truncate">
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
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { MALI_VIEW, REGION_SHAPES as SHAPES, project } from './maliRegions.js';

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
const searchQuery = ref('');   // recherche rapide dans le volet déroulant
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
        extra: u.operationnelle ? 'Opérationnelle' : 'Non opérationnelle'
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
    searchQuery.value = '';
    userZoom.value = 1;
    panOffset.value = { x: 0, y: 0 };
    sidebarTab.value = 'liste';
};

const retour = () => {
    sel.value = null;
    selUnit.value = null;
    searchQuery.value = '';
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
    const [a, b, c, d] = shape.bb;
    const isBamako = sel.value === 'R1';
    const minSpan = isBamako ? 60 : 28;
    const pw = Math.max(c - a, minSpan), ph = Math.max(d - b, minSpan);
    const pad = Math.max(pw, ph) * (isBamako ? 0.08 : 0.16);
    let w = pw + 2 * pad, h = ph + 2 * pad;
    const ar = MALI_VIEW.w / MALI_VIEW.h;
    if (w / h < ar) w = h * ar; else h = w / ar;
    return [(a + c) / 2 - w / 2, (b + d) / 2 - h / 2, w, h];
});

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
    const eff = r ? r.effectif : tot((x) => x.effectif);
    const abs = r ? r.absents : tot((x) => x.absents);
    const nU = r ? r.unites.length : tot((x) => x.unites.length);
    const nOp = r ? opCount(r) : tot(opCount);
    return [
        {
            label: r ? 'Effectif région' : 'Effectif total',
            value: fmt(eff),
            hint: r ? r.nom : 'en activité (hors retraités & radiés)'
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
