<template>
    <AuthenticatedLayout>
        <!-- Header épuré -->
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-xl text-white flex items-center gap-2">
                        <i class="pi pi-sort-amount-down text-sky-200"></i>
                        Tableau d'Avancement
                    </h2>
                    <p class="text-xs text-sky-100 mt-0.5">
                        Commission d'avancement — Notation des Officiers et Classement à l'ancienneté des Sous-Officiers
                    </p>
                </div>
                <!-- Session toujours année en clôture -->
                <div class="hidden sm:flex items-center gap-2 bg-white/15 px-3 py-1.5 rounded-lg border border-white/20 text-white text-xs font-bold">
                    <i class="pi pi-calendar text-sky-200"></i>
                    <span>Session : {{ currentAnnee }} (Clôture)</span>
                </div>
            </div>
        </template>

        <div class="py-5 max-w-[98%] mx-auto">
            <!-- SÉLECTEUR D'ONGLETS MAJEUR : OFFICIERS vs SOUS-OFFICIERS & RANG -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 mb-5 print:hidden">
                <div class="inline-flex p-1 bg-slate-200/80 rounded-2xl border border-slate-300 shadow-inner">
                    <!-- Onglet Officiers -->
                    <button @click="switchTab('officiers')"
                        :class="[
                            'flex items-center gap-2.5 px-5 py-2.5 rounded-xl font-extrabold text-xs transition-all cursor-pointer',
                            activeTab === 'officiers'
                                ? 'bg-gradient-to-r from-sky-800 via-sky-700 to-sky-800 text-white shadow-md'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-white/60'
                        ]">
                        <i class="pi pi-shield text-sm" :class="activeTab === 'officiers' ? 'text-sky-200' : 'text-slate-400'"></i>
                        <span class="tracking-wide uppercase">Officiers (Notation & Points)</span>
                        <span :class="[
                            'px-2 py-0.5 rounded-full text-[10px] font-black',
                            activeTab === 'officiers' ? 'bg-white/25 text-white' : 'bg-slate-300 text-slate-700'
                        ]">
                            {{ stats.total_officiers }}
                        </span>
                    </button>

                    <!-- Onglet Sous-Officiers & Rang -->
                    <button @click="switchTab('sous_officiers')"
                        :class="[
                            'flex items-center gap-2.5 px-5 py-2.5 rounded-xl font-extrabold text-xs transition-all cursor-pointer',
                            activeTab === 'sous_officiers'
                                ? 'bg-gradient-to-r from-emerald-800 via-emerald-700 to-emerald-800 text-white shadow-md'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-white/60'
                        ]">
                        <i class="pi pi-list text-sm" :class="activeTab === 'sous_officiers' ? 'text-emerald-200' : 'text-slate-400'"></i>
                        <span class="tracking-wide uppercase">Sous-Officiers & Rang (Ancienneté)</span>
                        <span :class="[
                            'px-2 py-0.5 rounded-full text-[10px] font-black',
                            activeTab === 'sous_officiers' ? 'bg-white/25 text-white' : 'bg-slate-300 text-slate-700'
                        ]">
                            {{ stats.total_sous_officiers }}
                        </span>
                    </button>
                </div>

                <div class="text-xs text-slate-500 font-medium hidden md:block">
                    <span v-if="activeTab === 'officiers'" class="inline-flex items-center gap-1.5 bg-sky-50 text-sky-800 px-3 py-1.5 rounded-lg border border-sky-200 font-bold">
                        <i class="pi pi-info-circle text-sky-600"></i>
                        Officiers : Notation manuelle, malus punitions & limite d'âge, calcul automatique du total
                    </span>
                    <span v-else class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-800 px-3 py-1.5 rounded-lg border border-emerald-200 font-bold">
                        <i class="pi pi-check-circle text-emerald-600"></i>
                        Sous-Officiers : Classement 100% automatique par Ancienneté dans le grade
                    </span>
                </div>
            </div>

            <!-- Bandeau de synthèse KPI : Adapté à l'onglet actif -->
            <!-- CAS 1 : OFFICIERS -->
            <div v-if="activeTab === 'officiers'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 mb-5 print:hidden">
                <div class="bg-white rounded-xl p-3.5 border border-slate-200 shadow-xs flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-sky-100 text-sky-800 flex items-center justify-center shrink-0">
                            <i class="pi pi-users text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium">Officiers éligibles</p>
                            <h3 class="text-xl font-extrabold text-slate-800">{{ stats.total_officiers }}</h3>
                        </div>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded bg-sky-50 text-sky-800 border border-sky-200 font-bold">
                        Session {{ currentAnnee }}
                    </span>
                </div>

                <div class="bg-white rounded-xl p-3.5 border border-slate-200 shadow-xs flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-sky-100 text-sky-800 flex items-center justify-center shrink-0">
                            <i class="pi pi-star text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium">Grades Officiers</p>
                            <h3 class="text-xl font-extrabold text-slate-800">{{ activeAvailableGrades.length }}</h3>
                        </div>
                    </div>
                    <span class="text-xs text-sky-800 bg-sky-50 border border-sky-200 px-2 py-0.5 rounded font-bold">Du + élevé au - élevé</span>
                </div>

                <!-- Règle Limite d'âge -->
                <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-xl p-3 border border-amber-200 shadow-xs flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-lg bg-amber-200 text-amber-900 flex items-center justify-center shrink-0">
                        <i class="pi pi-clock text-base"></i>
                    </div>
                    <div class="text-[11px] leading-tight text-amber-900">
                        <strong class="uppercase font-bold block text-amber-950">Règle Limite d'Âge</strong>
                        <span>Reste 2 ans = <b class="text-red-700">-1 pt</b> | Reste 1 an = <b class="text-red-700">-2 pts</b></span>
                    </div>
                </div>

                <!-- Règle Punitions -->
                <div class="bg-gradient-to-r from-red-50 to-rose-50 rounded-xl p-3 border border-red-200 shadow-xs flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-lg bg-red-200 text-red-900 flex items-center justify-center shrink-0">
                        <i class="pi pi-exclamation-triangle text-base"></i>
                    </div>
                    <div class="text-[11px] leading-tight text-red-900">
                        <strong class="uppercase font-bold block text-red-950">Règle des Punitions</strong>
                        <span>Chaque jour de punition = <b class="text-red-700">-0,1 point</b> du total</span>
                    </div>
                </div>
            </div>

            <!-- CAS 2 : SOUS-OFFICIERS & RANG -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 mb-5 print:hidden">
                <div class="bg-white rounded-xl p-3.5 border border-slate-200 shadow-xs flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center shrink-0">
                            <i class="pi pi-users text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium">Sous-Officiers & Rang éligibles</p>
                            <h3 class="text-xl font-extrabold text-slate-800">{{ stats.total_sous_officiers }}</h3>
                        </div>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold">
                        Session {{ currentAnnee }}
                    </span>
                </div>

                <div class="bg-white rounded-xl p-3.5 border border-slate-200 shadow-xs flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center shrink-0">
                            <i class="pi pi-star text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium">Grades représentés</p>
                            <h3 class="text-xl font-extrabold text-slate-800">{{ activeAvailableGrades.length }}</h3>
                        </div>
                    </div>
                    <span class="text-xs text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded font-bold">Du + élevé au - élevé</span>
                </div>

                <!-- Explication Ancienneté -->
                <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-xl p-3 border border-emerald-200 shadow-xs flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-lg bg-emerald-200 text-emerald-900 flex items-center justify-center shrink-0">
                        <i class="pi pi-history text-base"></i>
                    </div>
                    <div class="text-[11px] leading-tight text-emerald-900">
                        <strong class="uppercase font-bold block text-emerald-950">Règle Statutaire d'Ancienneté</strong>
                        <span>1. Date nomination au grade ➔ 2. Date entrée service ➔ 3. Âge</span>
                    </div>
                </div>
            </div>

            <!-- DEUX CARTES ALIGNÉES : 1) Filtres & Affichage  2) Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-5 print:hidden">
                <!-- Carte 1 : Filtres & Recherche -->
                <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200 shadow-xs p-3.5 flex flex-col justify-between">
                    <div class="flex items-center gap-2 mb-2.5 pb-2 border-b border-slate-100">
                        <i class="pi pi-filter text-sky-800 text-xs font-bold"></i>
                        <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                            Filtres & Affichage ({{ activeTab === 'officiers' ? 'Officiers' : 'Sous-Officiers' }})
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Filtre par Grade -->
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-bold text-slate-600 uppercase">Grade :</label>
                            <select v-model="currentGrade" @change="currentPage = 1"
                                class="px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-bold text-slate-700 focus:ring-2 focus:ring-sky-500">
                                <option value="all">Tous les grades ({{ activeAvailableGrades.length }})</option>
                                <option v-for="g in activeAvailableGrades" :key="g" :value="g">
                                    {{ g }}
                                </option>
                            </select>
                        </div>

                        <!-- Recherche -->
                        <div class="relative flex-1 min-w-[190px]">
                            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" v-model="searchQuery" @input="currentPage = 1" placeholder="Rechercher nom, matricule..."
                                class="w-full pl-8 pr-3 py-1.5 border border-slate-300 rounded-lg text-xs focus:ring-2 focus:ring-sky-500 focus:border-sky-500" />
                        </div>

                        <!-- Éléments par page -->
                        <div class="flex items-center gap-1.5 text-xs text-slate-600">
                            <label class="font-bold">Afficher :</label>
                            <select v-model.number="perPage" @change="currentPage = 1"
                                class="px-2.5 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-sky-500">
                                <option :value="15">15 / page</option>
                                <option :value="25">25 / page</option>
                                <option :value="50">50 / page</option>
                                <option :value="100">100 / page</option>
                                <option :value="9999">Tout afficher</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Carte 2 : Actions -->
                <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200 shadow-xs p-3.5 flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-2.5 pb-2 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-cog text-sky-800 text-xs font-bold"></i>
                            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Actions</span>
                        </div>
                        <!-- Badge modifications non enregistrées (uniquement Officiers) -->
                        <span v-if="activeTab === 'officiers' && hasUnsavedChanges"
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300 animate-pulse">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            Notes non enregistrées
                        </span>
                        <span v-else-if="activeTab === 'sous_officiers'"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                            <i class="pi pi-check text-[9px]"></i>
                            Ancienneté automatique
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Input fichier masqué pour l'import Excel Officiers -->
                        <input type="file" ref="fileInputRef" accept=".xlsx,.xls,.csv" class="hidden" @change="onFileSelected" />

                        <!-- Sauvegarder les notes (Uniquement Officiers) -->
                        <button v-if="activeTab === 'officiers'" @click="saveNotes" :disabled="saving"
                            class="flex-1 min-w-[135px] px-3.5 py-1.5 bg-gradient-to-r from-sky-800 via-sky-700 to-sky-800 hover:from-sky-900 hover:to-sky-900 text-white font-bold rounded-lg shadow-xs flex items-center justify-center gap-2 transition-all disabled:opacity-50 text-xs cursor-pointer">
                            <i :class="saving ? 'pi pi-spin pi-spinner' : 'pi pi-save'"></i>
                            <span>{{ saving ? 'Enregistrement...' : 'Sauvegarder les notes' }}</span>
                        </button>

                        <!-- Exporter Excel -->
                        <button @click="exportExcel" :disabled="exporting"
                            :class="[
                                'px-3.5 py-1.5 text-white font-bold rounded-lg shadow-xs flex items-center gap-1.5 transition-all text-xs disabled:opacity-50 cursor-pointer',
                                activeTab === 'officiers' ? 'bg-sky-700 hover:bg-sky-800' : 'bg-emerald-700 hover:bg-emerald-800'
                            ]">
                            <i :class="exporting ? 'pi pi-spin pi-spinner' : 'pi pi-file-excel'"></i>
                            <span>{{ exporting ? 'Export...' : (activeTab === 'officiers' ? 'Exporter Excel (Officiers)' : 'Exporter Excel (Sous-officiers)') }}</span>
                        </button>

                        <!-- Importer Excel (Uniquement Officiers) -->
                        <button v-if="activeTab === 'officiers'" @click="triggerFileInput" :disabled="importing"
                            class="px-3.5 py-1.5 bg-sky-900 hover:bg-sky-950 text-white font-bold rounded-lg shadow-xs flex items-center gap-1.5 transition-all text-xs disabled:opacity-50 cursor-pointer"
                            title="Importer un fichier Excel complété avec les notes et punitions">
                            <i :class="importing ? 'pi pi-spin pi-spinner' : 'pi pi-upload text-sky-300'"></i>
                            <span>{{ importing ? 'Import...' : 'Importer Excel' }}</span>
                        </button>

                        <!-- Imprimer -->
                        <button @click="printTable"
                            class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white font-bold rounded-lg shadow-xs flex items-center gap-1.5 transition-all text-xs cursor-pointer">
                            <i class="pi pi-print text-slate-200"></i>
                            <span>Imprimer</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- En-tête officiel pour impression -->
            <div class="hidden print:block mb-3 text-center border-b pb-2">
                <h1 class="text-base font-extrabold uppercase tracking-wider text-slate-900">
                    TABLEAU D'AVANCEMENT {{ activeTab === 'officiers' ? 'OFFICIERS' : 'SOUS-OFFICIERS & RANG' }} — SESSION {{ currentAnnee }}
                </h1>
                <p class="text-[10px] text-slate-600 mt-0.5">
                    {{ activeTab === 'officiers' ? 'Grille de notation et classement par total des points' : 'Classement automatique par ancienneté statutaire dans le grade' }}
                    | Filtre : {{ currentGrade === 'all' ? 'Tous les grades' : currentGrade }} | Date d'édition : {{ dateImpression }}
                </p>
            </div>

            <!-- Liste vide -->
            <div v-if="paginatedCandidates.length === 0"
                class="bg-white rounded-xl border border-slate-200 p-12 text-center shadow-xs">
                <i class="pi pi-inbox text-5xl text-slate-300 mb-3"></i>
                <h3 class="text-base font-semibold text-slate-700">Aucun candidat trouvé pour cette sélection</h3>
                <p class="text-sm text-slate-400 mt-1">
                    Vérifiez le filtre de grade ou les propositions d'avancement de la session {{ currentAnnee }}.
                </p>
            </div>

            <!-- ======================================================== -->
            <!-- VUE 1 : TABLEAU OFFICIERS (Notation complète 12 colonnes) -->
            <!-- ======================================================== -->
            <div v-else-if="activeTab === 'officiers'" class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden print:border-none print:shadow-none">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs print-table">
                        <thead>
                            <tr class="bg-gradient-to-r from-sky-800 via-sky-700 to-sky-800 text-white font-bold border-b border-sky-900 print:bg-slate-900">
                                <th class="py-2.5 px-1 text-center w-[3.5%] print:w-[3.5%]">Rang</th>
                                <th class="py-2.5 px-2 text-left w-[16%] print:w-[15%]">Militaire</th>
                                <th class="py-2.5 px-1 text-left w-[11%] print:w-[10%]">Grade Actuel ➔ Cible</th>
                                <th class="py-2.5 px-1 text-center w-[6.5%] print:w-[6%]" title="Points attribués à la fonction actuelle">
                                    Fonction
                                </th>
                                <th class="py-2.5 px-1 text-center w-[6.5%] print:w-[6%]" title="Note de rendement">
                                    Rendement
                                </th>
                                <th class="py-2.5 px-1 text-center w-[6.5%] print:w-[6%]" title="Bonus dernier emploi tenu">
                                    Bonus Emploi
                                </th>
                                <th class="py-2.5 px-1 text-center w-[6.5%] print:w-[6%]" title="Points pour les diplômes académiques">
                                    Diplômes
                                </th>
                                <th class="py-2.5 px-1 text-center w-[6.5%] print:w-[6%]" title="Points pour les décorations et médailles">
                                    Décorations
                                </th>
                                <th class="py-2.5 px-1 text-center w-[6.5%] print:w-[6%]" title="Points d'ancienneté dans le grade">
                                    Anc. Grade
                                </th>
                                <th class="py-2.5 px-1 text-center w-[8%] print:w-[8%] bg-red-900/40 print:bg-red-50 print:text-red-900" title="Jours de punition (-0.1 pt/jour)">
                                    Punitions
                                </th>
                                <th class="py-2.5 px-1 text-center w-[8.5%] print:w-[8.5%] bg-amber-900/30 print:bg-amber-50 print:text-amber-900" title="Règle Limite d'âge : -1 si reste 2 ans, -2 si reste 1 an">
                                    Limite d'Âge
                                </th>
                                <th class="py-2.5 px-1 text-center w-[8%] print:w-[8%] bg-sky-900 text-white font-extrabold print:bg-sky-100 print:text-sky-950">
                                    TOTAL
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <template v-for="(group, gradeName) in paginatedGroupedByGrade" :key="gradeName">
                                <!-- En-tête séparateur de Grade -->
                                <tr class="bg-sky-50/90 border-y-2 border-sky-200 font-bold text-sky-900 grade-banner">
                                    <td colspan="12" class="py-2 px-3">
                                        <div class="flex items-center justify-between">
                                            <span class="flex items-center gap-2 text-xs tracking-wide">
                                                <i class="pi pi-shield text-sky-700"></i>
                                                GRADE : <span class="uppercase font-extrabold text-sky-950">{{ gradeName }}</span>
                                            </span>
                                            <span class="text-[11px] bg-sky-200/70 text-sky-900 px-2 py-0.5 rounded-full font-semibold print:text-[9px]">
                                                {{ group.length }} candidat(s)
                                            </span>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Candidats Officiers -->
                                <tr v-for="item in group" :key="item.militaire_id"
                                    :class="[
                                        'transition-colors hover:bg-slate-50/80',
                                        item.rang === 1 ? 'bg-amber-50/40 font-medium' : ''
                                    ]">
                                    <!-- Rang dans le grade -->
                                    <td class="py-2 px-1 text-center whitespace-nowrap">
                                        <span v-if="item.rang === 1"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-400 text-amber-950 font-extrabold text-xs shadow-2xs border border-amber-500 print:bg-transparent print:border-none print:w-auto print:h-auto print:font-bold"
                                            title="1er du grade">
                                            🥇 1
                                        </span>
                                        <span v-else-if="item.rang === 2"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-300 text-slate-800 font-extrabold text-xs shadow-2xs border border-slate-400 print:bg-transparent print:border-none print:w-auto print:h-auto print:font-bold"
                                            title="2e du grade">
                                            🥈 2
                                        </span>
                                        <span v-else-if="item.rang === 3"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-700/20 text-amber-900 font-extrabold text-xs shadow-2xs border border-amber-700/40 print:bg-transparent print:border-none print:w-auto print:h-auto print:font-bold"
                                            title="3e du grade">
                                            🥉 3
                                        </span>
                                        <span v-else
                                            class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-100 text-slate-600 font-bold text-xs border border-slate-200 print:bg-transparent print:border-none print:w-auto print:h-auto">
                                            {{ item.rang }}
                                        </span>
                                    </td>

                                    <!-- Militaire -->
                                    <td class="py-2 px-2">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-sky-100 border border-sky-300 overflow-hidden shrink-0 flex items-center justify-center text-sky-800 font-bold text-[11px] print:hidden">
                                                <img v-if="item.photo_url" :src="item.photo_url" class="w-full h-full object-cover" />
                                                <span v-else>{{ (item.nom?.[0] || 'M') }}</span>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-extrabold text-slate-900 text-xs uppercase leading-tight truncate">
                                                    {{ item.nom }} {{ item.prenom }}
                                                </div>
                                                <div class="text-[10px] text-slate-500 font-mono">
                                                    Mle: <span class="font-bold text-slate-700">{{ item.matricule }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Grade Actuel ➔ Cible -->
                                    <td class="py-2 px-1">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-800 border border-slate-200 truncate">
                                                {{ item.grade_actuel }}
                                            </span>
                                            <span v-if="item.grade_cible" class="text-[10px] text-sky-800 font-bold truncate">
                                                ➔ {{ item.grade_cible }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Fonction actuelle -->
                                    <td class="py-2 px-1 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.points_fonction_actuelle"
                                            @input="handleInputChange(item)"
                                            class="w-12 px-1 py-0.5 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-1 focus:ring-sky-500 text-xs" />
                                        <div class="text-[9px] text-slate-500 truncate mt-0.5 print:hidden" :title="item.fonction_nom">
                                            {{ item.fonction_nom || '-' }}
                                        </div>
                                    </td>

                                    <!-- Rendement -->
                                    <td class="py-2 px-1 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.note_rendement"
                                            @input="handleInputChange(item)"
                                            class="w-12 px-1 py-0.5 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-1 focus:ring-sky-500 text-xs" />
                                    </td>

                                    <!-- Bonus Emploi -->
                                    <td class="py-2 px-1 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.bonus_dernier_emploi"
                                            @input="handleInputChange(item)"
                                            class="w-12 px-1 py-0.5 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-1 focus:ring-sky-500 text-xs" />
                                    </td>

                                    <!-- Diplômes -->
                                    <td class="py-2 px-1 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.points_diplomes"
                                            @input="handleInputChange(item)"
                                            class="w-12 px-1 py-0.5 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-1 focus:ring-sky-500 text-xs" />
                                    </td>

                                    <!-- Décorations -->
                                    <td class="py-2 px-1 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.points_decorations"
                                            @input="handleInputChange(item)"
                                            class="w-12 px-1 py-0.5 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-1 focus:ring-sky-500 text-xs" />
                                    </td>

                                    <!-- Ancienneté dans le grade -->
                                    <td class="py-2 px-1 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.points_anciennete_grade"
                                            @input="handleInputChange(item)"
                                            class="w-12 px-1 py-0.5 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-1 focus:ring-sky-500 text-xs" />
                                        <div class="text-[9px] text-slate-500 mt-0.5 print:hidden">
                                            {{ item.anciennete_grade_annees }} an(s)
                                        </div>
                                    </td>

                                    <!-- Punitions (Nombre de jours x -0.1 point) -->
                                    <td class="py-2 px-1 text-center bg-red-50/40 border-x border-red-100">
                                        <div class="flex items-center justify-center gap-0.5">
                                            <input type="number" step="1" min="0" max="365"
                                                v-model.number="item.jours_punition"
                                                @input="handleInputChange(item)"
                                                placeholder="0 j"
                                                class="w-12 px-1 py-0.5 text-center font-bold text-red-700 bg-white border border-red-300 rounded focus:ring-1 focus:ring-red-500 text-xs" />
                                            <span class="text-[9px] text-slate-500 font-semibold print:hidden">j</span>
                                        </div>
                                        <div class="mt-0.5">
                                            <span v-if="item.jours_punition > 0"
                                                class="inline-block px-1 py-0.2 rounded text-[9px] font-extrabold bg-red-100 text-red-800 border border-red-200">
                                                -{{ item.malus_punition }} pt
                                            </span>
                                            <span v-else class="text-[9px] text-slate-500 font-bold">
                                                0 pt
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Limite d'âge (-1 pt si 2 ans restants, -2 pts si 1 an restant) -->
                                    <td class="py-2 px-1 text-center bg-amber-50/30 border-x border-amber-100">
                                        <div class="flex items-center justify-center">
                                            <span v-if="item.malus_limite_age > 0"
                                                class="inline-block px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-red-100 text-red-800 border border-red-200">
                                                -{{ item.malus_limite_age }} pt{{ item.malus_limite_age > 1 ? 's' : '' }}
                                            </span>
                                            <span v-else class="inline-block px-1 py-0.5 rounded text-[10px] font-bold text-slate-600 bg-slate-100 border border-slate-200">
                                                0 pt
                                            </span>
                                        </div>
                                        <div class="text-[9px] text-slate-500 mt-0.5 print:hidden">
                                            <span v-if="item.annees_restantes_retraite !== null">
                                                Reste : <b>{{ item.annees_restantes_retraite }} a</b>
                                            </span>
                                            <span v-else>{{ item.age }} ans</span>
                                        </div>
                                    </td>

                                    <!-- TOTAL GÉNÉRAL -->
                                    <td class="py-2 px-1 text-center bg-sky-50/70 border-x border-sky-200">
                                        <span class="inline-block px-2 py-0.5 rounded-lg text-xs font-extrabold shadow-2xs print:bg-transparent print:border-none print:shadow-none print:text-slate-900"
                                            :class="item.total_points > 0 ? 'bg-sky-800 text-white' : 'bg-slate-200 text-slate-700'">
                                            {{ Number(item.total_points).toFixed(2) }}
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Officiers -->
                <div class="p-3 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-600 gap-3 print:hidden">
                    <div>
                        Affichage de <strong>{{ paginationInfo.from }}</strong> à <strong>{{ paginationInfo.to }}</strong> sur <strong>{{ paginationInfo.total }}</strong> officier(s).
                    </div>

                    <div v-if="totalPages > 1" class="flex items-center gap-1">
                        <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1"
                            class="px-2.5 py-1 border border-slate-300 rounded bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs cursor-pointer">
                            ← Précédent
                        </button>

                        <button v-for="p in visiblePages" :key="p" @click="currentPage = p"
                            :class="[
                                'px-2.5 py-1 border rounded text-xs font-bold transition-colors cursor-pointer',
                                currentPage === p ? 'bg-sky-800 text-white border-sky-800' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'
                            ]">
                            {{ p }}
                        </button>

                        <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages"
                            class="px-2.5 py-1 border border-slate-300 rounded bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs cursor-pointer">
                            Suivant →
                        </button>
                    </div>

                    <div class="text-[11px] text-slate-500 font-medium">
                        💡 Classement dynamique en temps réel selon le total de points.
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- VUE 2 : TABLEAU SOUS-OFFICIERS & RANG (Ancienneté)       -->
            <!-- ======================================================== -->
            <div v-else class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden print:border-none print:shadow-none">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs print-table">
                        <thead>
                            <tr class="bg-gradient-to-r from-emerald-800 via-emerald-700 to-emerald-800 text-white font-bold border-b border-emerald-900 print:bg-slate-900">
                                <th class="py-2.5 px-1 text-center w-[4%] print:w-[4%]">Rang</th>
                                <th class="py-2.5 px-3 text-left w-[18%] print:w-[17%]">Militaire</th>
                                <th class="py-2.5 px-2 text-left w-[12%] print:w-[11%]">Grade Actuel ➔ Cible</th>
                                <th class="py-2.5 px-2 text-center w-[11%] print:w-[11%]" title="Date de nomination au grade actuel">
                                    Date Nomination Grade
                                </th>
                                <th class="py-2.5 px-2 text-center w-[11%] print:w-[11%]" title="Durée accomplie dans le grade actuel">
                                    Ancienneté Grade
                                </th>
                                <th class="py-2.5 px-2 text-center w-[11%] print:w-[11%]" title="Date d'incorporation ou entrée en service">
                                    Date Incorporation
                                </th>
                                <th class="py-2.5 px-2 text-center w-[11%] print:w-[11%]" title="Ancienneté totale sous les drapeaux">
                                    Ancienneté Service
                                </th>
                                <th class="py-2.5 px-2 text-center w-[12%] print:w-[12%]" title="Certificats militaires et diplômes">
                                    Diplômes / Certificats
                                </th>
                                <th class="py-2.5 px-2 text-center w-[10%] print:w-[10%] bg-emerald-900/60 print:bg-emerald-50 print:text-emerald-950">
                                    Éligibilité
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <template v-for="(group, gradeName) in paginatedGroupedByGrade" :key="gradeName">
                                <!-- En-tête séparateur de Grade -->
                                <tr class="bg-emerald-50/90 border-y-2 border-emerald-200 font-bold text-emerald-900 grade-banner">
                                    <td colspan="9" class="py-2 px-3">
                                        <div class="flex items-center justify-between">
                                            <span class="flex items-center gap-2 text-xs tracking-wide">
                                                <i class="pi pi-shield text-emerald-700"></i>
                                                GRADE : <span class="uppercase font-extrabold text-emerald-950">{{ gradeName }}</span>
                                            </span>
                                            <span class="text-[11px] bg-emerald-200/70 text-emerald-900 px-2.5 py-0.5 rounded-full font-semibold print:text-[9px]">
                                                {{ group.length }} candidat(s) classé(s) par ancienneté
                                            </span>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Candidats Sous-Officiers -->
                                <tr v-for="item in group" :key="item.militaire_id"
                                    :class="[
                                        'transition-colors hover:bg-slate-50/80',
                                        item.rang === 1 ? 'bg-amber-50/40 font-medium' : ''
                                    ]">
                                    <!-- Rang dans le grade -->
                                    <td class="py-2.5 px-1 text-center whitespace-nowrap">
                                        <span v-if="item.rang === 1"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-400 text-amber-950 font-extrabold text-xs shadow-2xs border border-amber-500 print:bg-transparent print:border-none print:w-auto print:h-auto print:font-bold"
                                            title="1er par ancienneté">
                                            🥇 1
                                        </span>
                                        <span v-else-if="item.rang === 2"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-300 text-slate-800 font-extrabold text-xs shadow-2xs border border-slate-400 print:bg-transparent print:border-none print:w-auto print:h-auto print:font-bold"
                                            title="2e par ancienneté">
                                            🥈 2
                                        </span>
                                        <span v-else-if="item.rang === 3"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-700/20 text-amber-900 font-extrabold text-xs shadow-2xs border border-amber-700/40 print:bg-transparent print:border-none print:w-auto print:h-auto print:font-bold"
                                            title="3e par ancienneté">
                                            🥉 3
                                        </span>
                                        <span v-else
                                            class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-100 text-slate-600 font-bold text-xs border border-slate-200 print:bg-transparent print:border-none print:w-auto print:h-auto">
                                            {{ item.rang }}
                                        </span>
                                    </td>

                                    <!-- Militaire -->
                                    <td class="py-2.5 px-3">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-full bg-emerald-100 border border-emerald-300 overflow-hidden shrink-0 flex items-center justify-center text-emerald-800 font-bold text-[11px] print:hidden">
                                                <img v-if="item.photo_url" :src="item.photo_url" class="w-full h-full object-cover" />
                                                <span v-else>{{ (item.nom?.[0] || 'M') }}</span>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-extrabold text-slate-900 text-xs uppercase leading-tight truncate">
                                                    {{ item.nom }} {{ item.prenom }}
                                                </div>
                                                <div class="text-[10px] text-slate-500 font-mono">
                                                    Mle: <span class="font-bold text-slate-700">{{ item.matricule }}</span>
                                                    <span v-if="item.mle_grm" class="ml-1 text-slate-400 font-sans">({{ item.mle_grm }})</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Grade Actuel ➔ Cible -->
                                    <td class="py-2.5 px-2">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-800 border border-slate-200 truncate">
                                                {{ item.grade_actuel }}
                                            </span>
                                            <span v-if="item.grade_cible" class="text-[10px] text-emerald-800 font-bold truncate">
                                                ➔ {{ item.grade_cible }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Date Nomination Grade -->
                                    <td class="py-2.5 px-2 text-center">
                                        <span class="font-bold text-slate-800 font-mono text-xs">
                                            {{ item.date_derniere_promotion }}
                                        </span>
                                    </td>

                                    <!-- Ancienneté Grade -->
                                    <td class="py-2.5 px-2 text-center">
                                        <span class="inline-block px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200 font-extrabold text-xs">
                                            {{ item.anciennete_grade_texte }}
                                        </span>
                                    </td>

                                    <!-- Date Incorporation -->
                                    <td class="py-2.5 px-2 text-center">
                                        <span class="font-medium text-slate-700 font-mono text-xs">
                                            {{ item.date_entree_service }}
                                        </span>
                                    </td>

                                    <!-- Ancienneté Service -->
                                    <td class="py-2.5 px-2 text-center">
                                        <span class="text-xs font-semibold text-slate-700">
                                            {{ item.anciennete_service_texte }}
                                        </span>
                                    </td>

                                    <!-- Diplômes / Certificats -->
                                    <td class="py-2.5 px-2 text-center">
                                        <div v-if="item.certificats_texte" class="inline-block px-2 py-0.5 rounded bg-sky-50 text-sky-800 border border-sky-200 font-bold text-[11px] truncate max-w-[150px]" :title="item.certificats_texte">
                                            🎖️ {{ item.certificats_texte }}
                                        </div>
                                        <div v-else-if="item.diplomes_texte" class="text-[10px] text-slate-500 truncate max-w-[150px]" :title="item.diplomes_texte">
                                            🎓 {{ item.diplomes_texte }}
                                        </div>
                                        <span v-else class="text-slate-400 text-xs">-</span>
                                    </td>

                                    <!-- Statut Éligibilité -->
                                    <td class="py-2.5 px-2 text-center">
                                        <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            {{ item.message_eligibilite }}
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Sous-Officiers -->
                <div class="p-3 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-600 gap-3 print:hidden">
                    <div>
                        Affichage de <strong>{{ paginationInfo.from }}</strong> à <strong>{{ paginationInfo.to }}</strong> sur <strong>{{ paginationInfo.total }}</strong> sous-officier(s).
                    </div>

                    <div v-if="totalPages > 1" class="flex items-center gap-1">
                        <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1"
                            class="px-2.5 py-1 border border-slate-300 rounded bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs cursor-pointer">
                            ← Précédent
                        </button>

                        <button v-for="p in visiblePages" :key="p" @click="currentPage = p"
                            :class="[
                                'px-2.5 py-1 border rounded text-xs font-bold transition-colors cursor-pointer',
                                currentPage === p ? 'bg-emerald-800 text-white border-emerald-800' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'
                            ]">
                            {{ p }}
                        </button>

                        <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages"
                            class="px-2.5 py-1 border border-slate-300 rounded bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs cursor-pointer">
                            Suivant →
                        </button>
                    </div>

                    <div class="text-[11px] text-slate-500 font-medium">
                        ⚖️ Classement officiel par ancienneté statutaire de grade conformément aux règles d'éligibilité.
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL DE CONFIRMATION D'IMPORTATION EXCEL -->
        <div v-if="showImportConfirmModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-200">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full border border-slate-200 overflow-hidden transform transition-all">
                <div class="px-5 py-4 bg-gradient-to-r from-sky-800 via-sky-700 to-sky-800 text-white flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-white/15 flex items-center justify-center">
                            <i class="pi pi-file-import text-white text-sm"></i>
                        </div>
                        <h3 class="font-bold text-sm tracking-wide">Confirmation d'importation</h3>
                    </div>
                    <button @click="cancelImport" class="text-white/80 hover:text-white transition-colors cursor-pointer">
                        <i class="pi pi-times text-sm"></i>
                    </button>
                </div>

                <div class="p-5">
                    <p class="text-xs text-slate-600 mb-3 leading-relaxed">
                        Voulez-vous importer ce fichier pour mettre à jour les notes et recalculer les classements des officiers pour la <strong>session {{ currentAnnee }}</strong> ?
                    </p>

                    <div v-if="pendingImportFile"
                        class="bg-slate-50 border border-slate-200 rounded-xl p-3 flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-lg bg-sky-100 text-sky-800 flex items-center justify-center shrink-0">
                            <i class="pi pi-file-excel text-lg"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-bold text-slate-800 truncate" :title="pendingImportFile.name">
                                {{ pendingImportFile.name }}
                            </div>
                            <div class="text-[11px] text-slate-400">
                                {{ (pendingImportFile.size / 1024).toFixed(1) }} Ko
                            </div>
                        </div>
                    </div>

                    <div class="p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-[11px] leading-tight flex items-start gap-2">
                        <i class="pi pi-info-circle text-amber-600 shrink-0 mt-0.5"></i>
                        <span>Les notes des officiers présents dans le fichier seront mises à jour avec les valeurs importées.</span>
                    </div>
                </div>

                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button @click="cancelImport" :disabled="importing"
                        class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-lg text-xs transition-colors cursor-pointer">
                        Annuler
                    </button>

                    <button @click="confirmAndExecuteImport" :disabled="importing"
                        class="px-4 py-2 bg-gradient-to-r from-sky-800 via-sky-700 to-sky-800 hover:from-sky-900 hover:to-sky-900 text-white font-bold rounded-lg text-xs shadow-xs flex items-center gap-2 transition-all cursor-pointer disabled:opacity-50">
                        <i :class="importing ? 'pi pi-spin pi-spinner' : 'pi pi-check'"></i>
                        <span>{{ importing ? 'Import en cours...' : 'Confirmer l\'importation' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL DE NOTIFICATION (SUCCÈS / ERREUR) -->
        <div v-if="notification.show"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-200">
            <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full border border-slate-200 overflow-hidden p-6 text-center">
                <div v-if="notification.type === 'success'"
                    class="w-14 h-14 rounded-full bg-sky-100 text-sky-800 flex items-center justify-center mx-auto mb-3 shadow-inner">
                    <i class="pi pi-check text-2xl font-bold"></i>
                </div>
                <div v-else
                    class="w-14 h-14 rounded-full bg-red-100 text-red-700 flex items-center justify-center mx-auto mb-3 shadow-inner">
                    <i class="pi pi-exclamation-triangle text-2xl font-bold"></i>
                </div>

                <h3 class="text-base font-extrabold text-slate-900 mb-1">
                    {{ notification.title }}
                </h3>

                <p class="text-xs text-slate-600 mb-5 leading-relaxed">
                    {{ notification.message }}
                </p>

                <button @click="closeNotification"
                    class="w-full py-2.5 bg-sky-800 hover:bg-sky-900 text-white font-bold rounded-xl text-xs shadow-xs transition-all cursor-pointer">
                    Continuer
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    candidates: { type: Array, default: () => [] },
    gradesOfficiers: { type: Array, default: () => [] },
    gradesSousOfficiers: { type: Array, default: () => [] },
    selectedAnnee: { type: Number, default: () => new Date().getFullYear() },
    selectedGrade: { type: String, default: 'all' },
    initialTab: { type: String, default: 'officiers' },
    search: { type: String, default: '' },
    stats: {
        type: Object,
        default: () => ({
            total_candidats: 0,
            total_officiers: 0,
            total_sous_officiers: 0,
            total_grades_officiers: 0,
            total_grades_sous_officiers: 0,
            total_enregistres: 0
        })
    }
});

// Onglet actif : 'officiers' ou 'sous_officiers'
const activeTab = ref(props.initialTab || 'officiers');
const currentAnnee = ref(props.selectedAnnee);
const currentGrade = ref(props.selectedGrade || 'all');
const searchQuery = ref(props.search);
const saving = ref(false);
const hasUnsavedChanges = ref(false);

// Pagination
const currentPage = ref(1);
const perPage = ref(25);

const dateImpression = new Date().toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
});

// Données locales réactives
const localCandidates = ref(JSON.parse(JSON.stringify(props.candidates)));

watch(() => props.candidates, (newVal) => {
    localCandidates.value = JSON.parse(JSON.stringify(newVal));
    hasUnsavedChanges.value = false;
});

/**
 * Grades disponibles selon l'onglet actif
 */
const activeAvailableGrades = computed(() => {
    return activeTab.value === 'officiers' ? props.gradesOfficiers : props.gradesSousOfficiers;
});

/**
 * Changement d'onglet
 */
const switchTab = (tab) => {
    activeTab.value = tab;
    currentGrade.value = 'all';
    currentPage.value = 1;
};

/**
 * Calcul instantané de la ligne Officier
 */
const handleInputChange = (item) => {
    // 1. Calcul du malus punition (-0.1 par jour)
    const jours = Math.max(0, parseInt(item.jours_punition, 10) || 0);
    item.jours_punition = jours;
    item.malus_punition = Math.round(jours * 0.1 * 100) / 100;

    // 2. Règle limite d'âge
    if (item.annees_restantes_retraite !== null && item.annees_restantes_retraite !== undefined) {
        if (item.annees_restantes_retraite <= 1) {
            item.malus_limite_age = 2.0;
        } else if (item.annees_restantes_retraite === 2) {
            item.malus_limite_age = 1.0;
        } else {
            item.malus_limite_age = 0.0;
        }
    } else {
        item.malus_limite_age = parseFloat(item.malus_limite_age) || 0;
    }

    // 3. Normalisation des valeurs numériques
    const ptsFonction = parseFloat(item.points_fonction_actuelle) || 0;
    const rendement = parseFloat(item.note_rendement) || 0;
    const bonus = parseFloat(item.bonus_dernier_emploi) || 0;
    const diplomes = parseFloat(item.points_diplomes) || 0;
    const decos = parseFloat(item.points_decorations) || 0;
    const ancGrade = parseFloat(item.points_anciennete_grade) || 0;

    // 4. Total général
    const total = rendement + ptsFonction + bonus + diplomes + decos + ancGrade - item.malus_limite_age - item.malus_punition;
    item.total_points = Math.round(Math.max(0, total) * 100) / 100;

    hasUnsavedChanges.value = true;

    // 5. Recalculer instantanément les rangs par grade
    recalculerRangsOfficiers();
};

/**
 * Recalcule les rangs des Officiers par grade (par total_points décroissant)
 */
const recalculerRangsOfficiers = () => {
    const groups = {};
    localCandidates.value.filter(c => c.is_officier).forEach(c => {
        const g = c.grade_actuel || 'Non défini';
        if (!groups[g]) groups[g] = [];
        groups[g].push(c);
    });

    Object.keys(groups).forEach(grade => {
        groups[grade].sort((a, b) => {
            if (b.total_points !== a.total_points) {
                return b.total_points - a.total_points;
            }
            if (b.anciennete_grade_annees !== a.anciennete_grade_annees) {
                return b.anciennete_grade_annees - a.anciennete_grade_annees;
            }
            return (a.nom || '').localeCompare(b.nom || '');
        });

        groups[grade].forEach((candidat, index) => {
            candidat.rang = index + 1;
        });
    });
};

/**
 * Candidats filtrés par l'onglet actif, puis le grade et la recherche
 */
const tabCandidates = computed(() => {
    if (activeTab.value === 'officiers') {
        return localCandidates.value.filter(c => !!c.is_officier);
    } else {
        return localCandidates.value.filter(c => !c.is_officier);
    }
});

const filteredCandidates = computed(() => {
    let list = tabCandidates.value;

    if (currentGrade.value && currentGrade.value !== 'all') {
        list = list.filter(c => (c.grade_actuel || '') === currentGrade.value);
    }

    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(c =>
            (c.nom && c.nom.toLowerCase().includes(q)) ||
            (c.prenom && c.prenom.toLowerCase().includes(q)) ||
            (c.matricule && c.matricule.toLowerCase().includes(q)) ||
            (c.mle_grm && c.mle_grm.toLowerCase().includes(q))
        );
    }
    return list;
});

/**
 * Pagination
 */
const totalItems = computed(() => filteredCandidates.value.length);
const totalPages = computed(() => Math.ceil(totalItems.value / perPage.value) || 1);

const visiblePages = computed(() => {
    const pages = [];
    const max = 5;
    let start = Math.max(1, currentPage.value - Math.floor(max / 2));
    let end = Math.min(totalPages.value, start + max - 1);
    if (end - start + 1 < max) {
        start = Math.max(1, end - max + 1);
    }
    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    return pages;
});

const paginatedCandidates = computed(() => {
    if (perPage.value >= 9999) {
        return filteredCandidates.value;
    }
    const start = (currentPage.value - 1) * perPage.value;
    return filteredCandidates.value.slice(start, start + perPage.value);
});

const paginationInfo = computed(() => {
    const total = totalItems.value;
    if (total === 0) return { from: 0, to: 0, total: 0 };
    const from = (currentPage.value - 1) * perPage.value + 1;
    const to = Math.min(currentPage.value * perPage.value, total);
    return { from, to, total };
});

/**
 * Regroupement par Grade
 */
const paginatedGroupedByGrade = computed(() => {
    const map = {};
    paginatedCandidates.value.forEach(c => {
        const grade = c.grade_actuel || 'Non défini';
        if (!map[grade]) {
            map[grade] = [];
        }
        map[grade].push(c);
    });

    Object.keys(map).forEach(grade => {
        map[grade].sort((a, b) => a.rang - b.rang);
    });

    return map;
});

/**
 * Modals & Notifications
 */
const showImportConfirmModal = ref(false);
const pendingImportFile = ref(null);

const notification = ref({
    show: false,
    type: 'success',
    title: '',
    message: ''
});

const showNotification = (type, title, message) => {
    notification.value = {
        show: true,
        type,
        title,
        message
    };
};

const closeNotification = () => {
    notification.value.show = false;
};

/**
 * Sauvegarde des notes (Officiers)
 */
const saveNotes = async () => {
    saving.value = true;
    try {
        const itemsToSave = localCandidates.value.filter(c => c.is_officier).map(c => ({
            militaire_id: c.militaire_id,
            grade_actuel: c.grade_actuel,
            grade_cible: c.grade_cible,
            fonction_actuelle: c.fonction_nom,
            points_fonction_actuelle: c.points_fonction_actuelle,
            note_rendement: c.note_rendement,
            bonus_dernier_emploi: c.bonus_dernier_emploi,
            points_diplomes: c.points_diplomes,
            points_decorations: c.points_decorations,
            points_anciennete_grade: c.points_anciennete_grade,
            jours_punition: c.jours_punition,
            annees_restantes_retraite: c.annees_restantes_retraite,
            malus_limite_age: c.malus_limite_age
        }));

        const response = await axios.post('/avancements/save', {
            annee: currentAnnee.value,
            items: itemsToSave
        });

        if (response.data?.success) {
            hasUnsavedChanges.value = false;
            localCandidates.value.forEach(c => c.est_enregistre = true);
            showNotification('success', 'Enregistrement réussi', 'Les notes et classements des officiers ont été enregistrés avec succès.');
        } else {
            showNotification('error', 'Erreur d\'enregistrement', response.data?.message || 'Une erreur est survenue lors de l\'enregistrement.');
        }
    } catch (error) {
        console.error('Erreur sauvegarde avancements:', error);
        showNotification('error', 'Erreur d\'enregistrement', 'Impossible d\'enregistrer les notes. Veuillez vérifier les valeurs saisies.');
    } finally {
        saving.value = false;
    }
};

const exporting = ref(false);
const exportExcel = async () => {
    try {
        exporting.value = true;
        const params = new URLSearchParams({
            annee: currentAnnee.value,
            grade: currentGrade.value,
            tab: activeTab.value
        });
        const url = `/avancements/export?${params.toString()}`;
        const response = await fetch(url);
        if (!response.ok) throw new Error('Erreur lors du téléchargement');
        const blob = await response.blob();
        const blobUrl = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = blobUrl;
        const tabName = activeTab.value === 'officiers' ? 'Officiers' : 'Sous_Officiers';
        a.download = `Tableau_Avancement_${tabName}_${currentAnnee.value}.xlsx`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(blobUrl);
    } catch (e) {
        console.error(e);
        const params = new URLSearchParams({
            annee: currentAnnee.value,
            grade: currentGrade.value,
            tab: activeTab.value
        });
        window.open(`/avancements/export?${params.toString()}`, '_blank');
    } finally {
        exporting.value = false;
    }
};

/**
 * Import Excel via Modal de Confirmation (Officiers)
 */
const fileInputRef = ref(null);
const importing = ref(false);

const triggerFileInput = () => {
    fileInputRef.value?.click();
};

const onFileSelected = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    pendingImportFile.value = file;
    showImportConfirmModal.value = true;
};

const cancelImport = () => {
    showImportConfirmModal.value = false;
    pendingImportFile.value = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const confirmAndExecuteImport = async () => {
    if (!pendingImportFile.value) return;

    const formData = new FormData();
    formData.append('file', pendingImportFile.value);
    formData.append('annee', currentAnnee.value);

    importing.value = true;
    try {
        const response = await axios.post('/avancements/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        showImportConfirmModal.value = false;
        pendingImportFile.value = null;
        if (fileInputRef.value) fileInputRef.value.value = '';

        if (response.data?.success) {
            showNotification('success', 'Importation terminée', response.data.message || 'Notes et classements mis à jour avec succès.');
            router.reload({ preserveScroll: true });
        } else {
            showNotification('error', 'Erreur d\'importation', response.data?.message || 'Erreur lors de l\'importation.');
        }
    } catch (err) {
        showImportConfirmModal.value = false;
        console.error('Erreur import avancements:', err);
        const msg = err.response?.data?.message || 'Erreur lors de l\'importation du fichier Excel.';
        showNotification('error', 'Échec de l\'importation', msg);
    } finally {
        importing.value = false;
    }
};

/**
 * Impression officielle paysage plein écran sans coupure de colonnes
 */
const printTable = () => {
    const prevPerPage = perPage.value;
    perPage.value = 9999; // Affiche tous les candidats sans pagination
    setTimeout(() => {
        window.print();
        setTimeout(() => {
            perPage.value = prevPerPage;
        }, 1000);
    }, 300);
};
</script>

<style scoped>
@media print {
    @page {
        size: landscape;
        margin: 5mm 4mm !important;
    }

    :deep(aside),
    :deep(header),
    :deep(nav),
    .print\:hidden {
        display: none !important;
    }

    body, html {
        background: white !important;
        color: #0f172a !important;
        font-size: 7.5pt !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }

    /* Tableau 100% largeur avec colonnes fixes proportionnelles */
    .print-table {
        width: 100% !important;
        max-width: 100% !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
    }

    .print-table th,
    .print-table td {
        padding: 2.5px 2px !important;
        font-size: 7.5pt !important;
        line-height: 1.15 !important;
        border: 0.5pt solid #cbd5e1 !important;
        min-width: 0 !important;
        max-width: none !important;
        word-break: break-word !important;
        overflow: hidden !important;
    }

    .print-table th {
        background-color: #0f172a !important;
        color: white !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .print-table .grade-banner td {
        background-color: #e0f2fe !important;
        color: #0c4a6e !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .print-table input {
        border: none !important;
        background: transparent !important;
        padding: 0 !important;
        text-align: center !important;
        font-weight: bold !important;
        font-size: 7.5pt !important;
        width: 100% !important;
        box-shadow: none !important;
    }

    thead {
        display: table-header-group !important;
    }

    tr {
        page-break-inside: avoid !important;
    }
}
</style>
