<template>
    <AuthenticatedLayout>
        <!-- Header épuré : aucun bouton encombrant ici -->
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-xl text-white flex items-center gap-2">
                        <i class="pi pi-sort-amount-down text-sky-200"></i>
                        Tableau d'Avancement
                    </h2>
                    <p class="text-xs text-sky-100 mt-0.5">
                        Notation, calcul automatique des points et classement dynamique par grade
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
            <!-- Bandeau de synthèse KPI & Règles métier -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 mb-5 print:hidden">
                <!-- Total Candidats -->
                <div class="bg-white rounded-xl p-3.5 border border-slate-200 shadow-xs flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-sky-100 text-sky-800 flex items-center justify-center shrink-0">
                            <i class="pi pi-users text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium">Candidats éligibles</p>
                            <h3 class="text-xl font-extrabold text-slate-800">{{ stats.total_candidats }}</h3>
                        </div>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded bg-sky-50 text-sky-800 border border-sky-200 font-bold">
                        Année {{ currentAnnee }}
                    </span>
                </div>

                <!-- Grades concernés -->
                <div class="bg-white rounded-xl p-3.5 border border-slate-200 shadow-xs flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-sky-100 text-sky-800 flex items-center justify-center shrink-0">
                            <i class="pi pi-star text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium">Grades représentés</p>
                            <h3 class="text-xl font-extrabold text-slate-800">{{ stats.total_grades }}</h3>
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

            <!-- DEUX CARTES ALIGNÉES : 1) Filtres & Affichage  2) Actions & Sauvegarde -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-5 print:hidden">
                <!-- Carte 1 : Filtres & Recherche (7 colonnes sur grand écran) -->
                <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200 shadow-xs p-3.5 flex flex-col justify-between">
                    <div class="flex items-center gap-2 mb-2.5 pb-2 border-b border-slate-100">
                        <i class="pi pi-filter text-sky-800 text-xs font-bold"></i>
                        <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Filtres & Affichage</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Filtre par Grade -->
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-bold text-slate-600 uppercase">Grade :</label>
                            <select v-model="currentGrade" @change="handleGradeChange"
                                class="px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-bold text-slate-700 focus:ring-2 focus:ring-sky-500">
                                <option value="all">Tous les grades ({{ grades.length }})</option>
                                <option v-for="g in grades" :key="g" :value="g">
                                    {{ g }}
                                </option>
                            </select>
                        </div>

                        <!-- Recherche -->
                        <div class="relative flex-1 min-w-[190px]">
                            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" v-model="searchQuery" placeholder="Rechercher nom, matricule..."
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

                <!-- Carte 2 : Actions & Sauvegarde (5 colonnes sur grand écran) -->
                <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200 shadow-xs p-3.5 flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-2.5 pb-2 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-cog text-sky-800 text-xs font-bold"></i>
                            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Actions</span>
                        </div>
                        <!-- Badge modifications non enregistrées -->
                        <span v-if="hasUnsavedChanges"
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300 animate-pulse">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            Non enregistré
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Input fichier masqué pour l'import Excel -->
                        <input type="file" ref="fileInputRef" accept=".xlsx,.xls,.csv" class="hidden" @change="onFileSelected" />

                        <!-- Sauvegarder les notes (Bleu officiel projet) -->
                        <button @click="saveNotes" :disabled="saving"
                            class="flex-1 min-w-[135px] px-3.5 py-1.5 bg-gradient-to-r from-sky-800 via-sky-700 to-sky-800 hover:from-sky-900 hover:to-sky-900 text-white font-bold rounded-lg shadow-xs flex items-center justify-center gap-2 transition-all disabled:opacity-50 text-xs">
                            <i :class="saving ? 'pi pi-spin pi-spinner' : 'pi pi-save'"></i>
                            <span>{{ saving ? 'Enregistrement...' : 'Sauvegarder les notes' }}</span>
                        </button>

                        <!-- Exporter Excel (Bleu projet Sky-700, icône sky claire) -->
                        <button @click="exportExcel" :disabled="exporting"
                            class="px-3.5 py-1.5 bg-sky-700 hover:bg-sky-800 text-white font-bold rounded-lg shadow-xs flex items-center gap-1.5 transition-all text-xs disabled:opacity-50">
                            <i :class="exporting ? 'pi pi-spin pi-spinner' : 'pi pi-file-excel text-sky-200'"></i>
                            <span>{{ exporting ? 'Export en cours...' : 'Exporter Excel' }}</span>
                        </button>

                        <!-- Importer Excel -->
                        <button @click="triggerFileInput" :disabled="importing"
                            class="px-3.5 py-1.5 bg-sky-900 hover:bg-sky-950 text-white font-bold rounded-lg shadow-xs flex items-center gap-1.5 transition-all text-xs disabled:opacity-50"
                            title="Importer un fichier Excel complété avec les notes et punitions">
                            <i :class="importing ? 'pi pi-spin pi-spinner' : 'pi pi-upload text-sky-300'"></i>
                            <span>{{ importing ? 'Importation...' : 'Importer Excel' }}</span>
                        </button>

                        <!-- Imprimer (Slate-700) -->
                        <button @click="printTable"
                            class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white font-bold rounded-lg shadow-xs flex items-center gap-1.5 transition-all text-xs">
                            <i class="pi pi-print text-slate-200"></i>
                            <span>Imprimer</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- En-tête officiel pour impression -->
            <div class="hidden print:block mb-4 text-center border-b pb-2">
                <h1 class="text-xl font-bold uppercase tracking-wider text-slate-900">
                    TABLEAU D'AVANCEMENT - SESSION {{ currentAnnee }}
                </h1>
                <p class="text-[11px] text-slate-600">
                    Filtre : {{ currentGrade === 'all' ? 'Tous les grades' : currentGrade }} | Date : {{ dateImpression }}
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

            <!-- Tableau d'avancement réactif -->
            <div v-else class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gradient-to-r from-sky-800 via-sky-700 to-sky-800 text-white font-bold border-b border-sky-900">
                                <th class="py-2.5 px-2 text-center w-12">Rang</th>
                                <th class="py-2.5 px-3 min-w-[190px]">Militaire</th>
                                <th class="py-2.5 px-2 min-w-[130px]">Grade Actuel ➔ Cible</th>
                                <th class="py-2.5 px-2 text-center min-w-[110px]" title="Points attribués à la fonction actuelle">
                                    Fonction Actuelle
                                </th>
                                <th class="py-2.5 px-2 text-center min-w-[90px]" title="Note de rendement">
                                    Rendement
                                </th>
                                <th class="py-2.5 px-2 text-center min-w-[90px]" title="Bonus dernier emploi tenu">
                                    Bonus Emploi
                                </th>
                                <th class="py-2.5 px-2 text-center min-w-[110px]" title="Points pour les diplômes académiques">
                                    Diplômes
                                </th>
                                <th class="py-2.5 px-2 text-center min-w-[110px]" title="Points pour les décorations et médailles">
                                    Décorations
                                </th>
                                <th class="py-2.5 px-2 text-center min-w-[110px]" title="Points d'ancienneté dans le grade">
                                    Anc. Grade
                                </th>
                                <th class="py-2.5 px-2 text-center min-w-[125px] bg-red-900/40" title="Jours de punition (-0.1 pt/jour)">
                                    Punitions (jours)
                                </th>
                                <th class="py-2.5 px-2 text-center min-w-[125px] bg-amber-900/30" title="Règle Limite d'âge : -1 si reste 2 ans, -2 si reste 1 an">
                                    Limite d'Âge
                                </th>
                                <th class="py-2.5 px-3 text-center min-w-[105px] bg-sky-900 text-white font-extrabold text-sm">
                                    TOTAL
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <template v-for="(group, gradeName) in paginatedGroupedByGrade" :key="gradeName">
                                <!-- En-tête séparateur de Grade (classé du plus élevé au moins élevé) -->
                                <tr class="bg-sky-50/90 border-y-2 border-sky-200 font-bold text-sky-900">
                                    <td colspan="12" class="py-2 px-3">
                                        <div class="flex items-center justify-between">
                                            <span class="flex items-center gap-2 text-xs tracking-wide">
                                                <i class="pi pi-shield text-sky-700"></i>
                                                GRADE : <span class="uppercase font-extrabold text-sky-950">{{ gradeName }}</span>
                                            </span>
                                            <span class="text-[11px] bg-sky-200/70 text-sky-900 px-2 py-0.5 rounded-full font-semibold">
                                                {{ group.length }} candidat(s)
                                            </span>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Candidats -->
                                <tr v-for="item in group" :key="item.militaire_id"
                                    :class="[
                                        'transition-colors hover:bg-slate-50/80',
                                        item.rang === 1 ? 'bg-amber-50/40 font-medium' : ''
                                    ]">
                                    <!-- Rang dans le grade -->
                                    <td class="py-2 px-2 text-center whitespace-nowrap">
                                        <span v-if="item.rang === 1"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-400 text-amber-950 font-extrabold text-xs shadow-2xs border border-amber-500"
                                            title="1er du grade">
                                            🥇 1
                                        </span>
                                        <span v-else-if="item.rang === 2"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-300 text-slate-800 font-extrabold text-xs shadow-2xs border border-slate-400"
                                            title="2e du grade">
                                            🥈 2
                                        </span>
                                        <span v-else-if="item.rang === 3"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-700/20 text-amber-900 font-extrabold text-xs shadow-2xs border border-amber-700/40"
                                            title="3e du grade">
                                            🥉 3
                                        </span>
                                        <span v-else
                                            class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-100 text-slate-600 font-bold text-xs border border-slate-200">
                                            {{ item.rang }}
                                        </span>
                                    </td>

                                    <!-- Militaire -->
                                    <td class="py-2 px-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-sky-100 border border-sky-300 overflow-hidden shrink-0 flex items-center justify-center text-sky-800 font-bold text-[11px] print:hidden">
                                                <img v-if="item.photo_url" :src="item.photo_url" class="w-full h-full object-cover" />
                                                <span v-else>{{ (item.nom?.[0] || 'M') }}</span>
                                            </div>
                                            <div>
                                                <div class="font-extrabold text-slate-900 text-xs uppercase leading-tight">
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
                                    <td class="py-2 px-2">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                                {{ item.grade_actuel }}
                                            </span>
                                            <span v-if="item.grade_cible" class="text-[10px] text-sky-800 font-bold flex items-center gap-1">
                                                ➔ {{ item.grade_cible }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Fonction actuelle (SAISIE DES POINTS + libellé dessous) -->
                                    <td class="py-2 px-1.5 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.points_fonction_actuelle"
                                            @input="handleInputChange(item)"
                                            class="w-14 px-1 py-0.5 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-1 focus:ring-sky-500 text-xs" />
                                        <div class="text-[10px] text-slate-500 truncate max-w-[110px] mt-0.5 mx-auto" :title="item.fonction_nom">
                                            {{ item.fonction_nom || '-' }}
                                        </div>
                                    </td>

                                    <!-- Rendement (input) -->
                                    <td class="py-2 px-1.5 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.note_rendement"
                                            @input="handleInputChange(item)"
                                            class="w-14 px-1 py-0.5 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-1 focus:ring-sky-500 text-xs" />
                                    </td>

                                    <!-- Bonus Emploi (input) -->
                                    <td class="py-2 px-1.5 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.bonus_dernier_emploi"
                                            @input="handleInputChange(item)"
                                            class="w-14 px-1 py-0.5 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-1 focus:ring-sky-500 text-xs" />
                                    </td>

                                    <!-- Diplômes (input + texte) -->
                                    <td class="py-2 px-1.5 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.points_diplomes"
                                            @input="handleInputChange(item)"
                                            class="w-14 px-1 py-0.5 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-1 focus:ring-sky-500 text-xs" />
                                        <div v-if="item.diplomes_texte" class="text-[10px] text-slate-500 truncate max-w-[110px] mt-0.5 mx-auto" :title="item.diplomes_texte">
                                            🎓 {{ item.diplomes_texte }}
                                        </div>
                                    </td>

                                    <!-- Décorations (input + texte) -->
                                    <td class="py-2 px-1.5 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.points_decorations"
                                            @input="handleInputChange(item)"
                                            class="w-14 px-1 py-0.5 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-1 focus:ring-sky-500 text-xs" />
                                        <div v-if="item.decorations_texte" class="text-[10px] text-amber-700 truncate max-w-[110px] mt-0.5 mx-auto" :title="item.decorations_texte">
                                            🎖️ {{ item.decorations_texte }}
                                        </div>
                                    </td>

                                    <!-- Ancienneté dans le grade (input + années réelles) -->
                                    <td class="py-2 px-1.5 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.points_anciennete_grade"
                                            @input="handleInputChange(item)"
                                            class="w-14 px-1 py-0.5 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-1 focus:ring-sky-500 text-xs" />
                                        <div class="text-[10px] text-slate-500 mt-0.5">
                                            {{ item.anciennete_grade_annees }} an(s)
                                        </div>
                                    </td>

                                    <!-- Punitions (Nombre de jours x -0.1 point) -->
                                    <td class="py-2 px-1.5 text-center bg-red-50/40 border-x border-red-100">
                                        <div class="flex items-center justify-center gap-0.5">
                                            <input type="number" step="1" min="0" max="365"
                                                v-model.number="item.jours_punition"
                                                @input="handleInputChange(item)"
                                                placeholder="0 j"
                                                class="w-14 px-1 py-0.5 text-center font-bold text-red-700 bg-white border border-red-300 rounded focus:ring-1 focus:ring-red-500 text-xs" />
                                            <span class="text-[10px] text-slate-500 font-semibold">j</span>
                                        </div>
                                        <!-- Affichage du malus calculé (-0.1/j) -->
                                        <div class="mt-0.5">
                                            <span v-if="item.jours_punition > 0"
                                                class="inline-block px-1.5 py-0.2 rounded text-[10px] font-extrabold bg-red-100 text-red-800 border border-red-200">
                                                -{{ item.malus_punition }} pt
                                            </span>
                                            <span v-else class="text-[10px] text-slate-500 font-bold">
                                                0 pt
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Limite d'âge (-1 pt si 2 ans restants, -2 pts si 1 an restant) -->
                                    <td class="py-2 px-1.5 text-center bg-amber-50/30 border-x border-amber-100">
                                        <div class="flex items-center justify-center">
                                            <span v-if="item.malus_limite_age > 0"
                                                class="inline-block px-2 py-0.5 rounded text-xs font-extrabold bg-red-100 text-red-800 border border-red-200">
                                                -{{ item.malus_limite_age }} pt{{ item.malus_limite_age > 1 ? 's' : '' }}
                                            </span>
                                            <span v-else class="inline-block px-1.5 py-0.5 rounded text-[11px] font-bold text-slate-600 bg-slate-100 border border-slate-200">
                                                0 pt
                                            </span>
                                        </div>
                                        <div class="text-[10px] text-slate-500 mt-0.5">
                                            <span v-if="item.annees_restantes_retraite !== null">
                                                Reste : <b>{{ item.annees_restantes_retraite }} an(s)</b>
                                            </span>
                                            <span v-else>Âge : {{ item.age }} ans</span>
                                        </div>
                                    </td>

                                    <!-- TOTAL GÉNÉRAL -->
                                    <td class="py-2 px-3 text-center bg-sky-50/70 border-x border-sky-200">
                                        <span class="inline-block px-2.5 py-1 rounded-lg text-xs font-extrabold shadow-2xs"
                                            :class="item.total_points > 0 ? 'bg-sky-800 text-white' : 'bg-slate-200 text-slate-700'">
                                            {{ Number(item.total_points).toFixed(2) }}
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination et statistiques en bas du tableau -->
                <div class="p-3 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-600 gap-3 print:hidden">
                    <div>
                        Affichage de <strong>{{ paginationInfo.from }}</strong> à <strong>{{ paginationInfo.to }}</strong> sur <strong>{{ paginationInfo.total }}</strong> candidat(s).
                    </div>

                    <!-- Contrôles de pagination -->
                    <div v-if="totalPages > 1" class="flex items-center gap-1">
                        <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1"
                            class="px-2.5 py-1 border border-slate-300 rounded bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs">
                            ← Précédent
                        </button>

                        <button v-for="p in visiblePages" :key="p" @click="currentPage = p"
                            :class="[
                                'px-2.5 py-1 border rounded text-xs font-bold transition-colors',
                                currentPage === p ? 'bg-sky-800 text-white border-sky-800' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'
                            ]">
                            {{ p }}
                        </button>

                        <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages"
                            class="px-2.5 py-1 border border-slate-300 rounded bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs">
                            Suivant →
                        </button>
                    </div>

                    <div class="text-[11px] text-slate-500 font-medium">
                        💡 Classement dynamique en temps réel par ordre hiérarchique de grade.
                    </div>
                </div>
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
    grades: { type: Array, default: () => [] },
    selectedAnnee: { type: Number, default: () => new Date().getFullYear() },
    selectedGrade: { type: String, default: 'all' },
    search: { type: String, default: '' },
    stats: {
        type: Object,
        default: () => ({
            total_candidats: 0,
            total_grades: 0,
            total_enregistres: 0
        })
    }
});

const currentAnnee = ref(props.selectedAnnee);
const currentGrade = ref(props.selectedGrade);
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
 * Calcul instantané de la ligne :
 * - Fonction actuelle (points)
 * - Malus punitions = jours * 0.1
 * - Malus limite d'âge = si reste <= 1 an : -2 pts, si reste 2 ans : -1 pt, sinon 0
 * - Total = somme des critères - punitions - limite d'âge
 * - Recalcul du classement par grade
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
    recalculerRangsDynamiques();
};

/**
 * Recalcule les rangs au sein de chaque grade
 */
const recalculerRangsDynamiques = () => {
    const groups = {};
    localCandidates.value.forEach(c => {
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
 * Filtrage par recherche
 */
const filteredCandidates = computed(() => {
    let list = localCandidates.value;
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
 * Regroupement par Grade (du grade le plus élevé au plus bas)
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

const handleGradeChange = () => {
    currentPage.value = 1;
    router.get('/avancements', {
        annee: currentAnnee.value,
        grade: currentGrade.value,
        search: searchQuery.value || undefined
    }, {
        preserveState: true,
        preserveScroll: true
    });
};

/**
 * Sauvegarde
 */
const saveNotes = async () => {
    saving.value = true;
    try {
        const itemsToSave = localCandidates.value.map(c => ({
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
            alert('✅ Notes et classements enregistrés avec succès !');
        }
    } catch (error) {
        console.error('Erreur sauvegarde avancements:', error);
        alert('❌ Erreur lors de la sauvegarde. Veuillez vérifier les valeurs.');
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
            grade: currentGrade.value
        });
        const url = `/avancements/export?${params.toString()}`;
        const response = await fetch(url);
        if (!response.ok) throw new Error('Erreur lors du téléchargement');
        const blob = await response.blob();
        const blobUrl = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = `Tableau_Avancement_${currentAnnee.value}.xlsx`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(blobUrl);
    } catch (e) {
        console.error(e);
        const params = new URLSearchParams({
            annee: currentAnnee.value,
            grade: currentGrade.value
        });
        window.open(`/avancements/export?${params.toString()}`, '_blank');
    } finally {
        exporting.value = false;
    }
};

/**
 * Import Excel
 */
const fileInputRef = ref(null);
const importing = ref(false);

const triggerFileInput = () => {
    fileInputRef.value?.click();
};

const onFileSelected = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!confirm(`Voulez-vous importer le fichier "${file.name}" pour mettre à jour les notes de la session ${currentAnnee.value} ?`)) {
        e.target.value = '';
        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('annee', currentAnnee.value);

    importing.value = true;
    try {
        const response = await axios.post('/avancements/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        if (response.data?.success) {
            alert('✅ ' + (response.data.message || 'Importation terminée avec succès.'));
            router.reload();
        } else {
            alert('⚠️ ' + (response.data?.message || 'Erreur lors de l\'importation.'));
        }
    } catch (err) {
        console.error('Erreur import avancements:', err);
        const msg = err.response?.data?.message || 'Erreur lors de l\'importation du fichier Excel.';
        alert('❌ ' + msg);
    } finally {
        importing.value = false;
        e.target.value = '';
    }
};

const printTable = () => {
    window.print();
};
</script>

<style scoped>
@media print {
    body {
        background: white !important;
        font-size: 9px !important;
    }
    input {
        border: none !important;
        background: transparent !important;
        padding: 0 !important;
    }
}
</style>
