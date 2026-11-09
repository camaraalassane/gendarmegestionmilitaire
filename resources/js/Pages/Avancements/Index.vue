<template>
    <AuthenticatedLayout>
        <!-- Header -->
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="font-bold text-2xl text-white flex items-center gap-2">
                        <i class="pi pi-sort-amount-down text-sky-200"></i>
                        Tableau d'Avancement
                    </h2>
                    <p class="text-xs text-sky-100 mt-0.5">
                        Notation, calcul automatique des points et classement dynamique par grade
                    </p>
                </div>

                <!-- Boutons d'action -->
                <div class="flex flex-wrap items-center gap-2 print:hidden">
                    <button @click="saveNotes" :disabled="saving"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg shadow-sm flex items-center gap-2 transition-all disabled:opacity-50 text-sm">
                        <i :class="saving ? 'pi pi-spin pi-spinner' : 'pi pi-save'"></i>
                        <span>{{ saving ? 'Enregistrement...' : 'Sauvegarder les notes' }}</span>
                    </button>

                    <button @click="exportExcel"
                        class="px-3.5 py-2 bg-white/20 hover:bg-white/30 text-white border border-white/40 font-semibold rounded-lg shadow-sm flex items-center gap-2 transition-all text-sm">
                        <i class="pi pi-file-excel text-emerald-300"></i>
                        <span>Exporter Excel</span>
                    </button>

                    <button @click="printTable"
                        class="px-3.5 py-2 bg-white/20 hover:bg-white/30 text-white border border-white/40 font-semibold rounded-lg shadow-sm flex items-center gap-2 transition-all text-sm">
                        <i class="pi pi-print"></i>
                        <span>Imprimer</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-[98%] mx-auto">
            <!-- Bandeau de synthèse KPI -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 print:hidden">
                <!-- Total Candidats -->
                <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-lg bg-sky-100 text-sky-800 flex items-center justify-center">
                            <i class="pi pi-users text-xl"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium">Candidats éligibles</p>
                            <h3 class="text-2xl font-bold text-slate-800">{{ stats.total_candidats }}</h3>
                        </div>
                    </div>
                    <span class="text-xs px-2 py-1 rounded bg-sky-50 text-sky-700 border border-sky-200 font-semibold">
                        Année {{ currentAnnee }}
                    </span>
                </div>

                <!-- Grades concernés -->
                <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-lg bg-indigo-100 text-indigo-800 flex items-center justify-center">
                            <i class="pi pi-star text-xl"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium">Grades représentés</p>
                            <h3 class="text-2xl font-bold text-slate-800">{{ stats.total_grades }}</h3>
                        </div>
                    </div>
                    <span class="text-xs text-slate-400">Classés par grade</span>
                </div>

                <!-- Notes enregistrées -->
                <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-xs flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center">
                            <i class="pi pi-check-circle text-xl"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium">Fiches enregistrées</p>
                            <h3 class="text-2xl font-bold text-slate-800">
                                {{ stats.total_enregistres }} <span class="text-sm font-normal text-slate-400">/ {{ stats.total_candidats }}</span>
                            </h3>
                        </div>
                    </div>
                    <span v-if="stats.total_enregistres === stats.total_candidats && stats.total_candidats > 0"
                        class="text-xs px-2 py-1 rounded bg-emerald-50 text-emerald-700 font-semibold">
                        Complet
                    </span>
                </div>

                <!-- Règle Punitions -->
                <div class="bg-gradient-to-r from-amber-50 to-orange-50 rounded-xl p-4 border border-amber-200 shadow-xs flex items-center gap-3">
                    <div class="w-11 h-11 rounded-lg bg-amber-200 text-amber-900 flex items-center justify-center shrink-0">
                        <i class="pi pi-exclamation-triangle text-xl"></i>
                    </div>
                    <div>
                        <p class="text-xs text-amber-900 font-bold uppercase tracking-wider">Règle des punitions</p>
                        <p class="text-xs text-amber-800 mt-0.5">
                            Chaque jour de punition = <strong class="text-red-600">-0,1 point</strong> soustrait du total global.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Barre de filtres & recherche -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-4 mb-6 print:hidden">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <!-- Filtres Année et Grade -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Sélecteur d'année -->
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-bold text-slate-600 uppercase">Session :</label>
                            <select v-model="currentAnnee" @change="applyFilters"
                                class="px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 focus:ring-2 focus:ring-sky-500">
                                <option v-for="a in anneesDisponibles" :key="a" :value="a">
                                    Session {{ a }}
                                </option>
                            </select>
                        </div>

                        <!-- Filtre par Grade -->
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-bold text-slate-600 uppercase">Grade :</label>
                            <select v-model="currentGrade" @change="applyFilters"
                                class="px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 focus:ring-2 focus:ring-sky-500">
                                <option value="all">Tous les grades ({{ grades.length }})</option>
                                <option v-for="g in grades" :key="g" :value="g">
                                    {{ g }}
                                </option>
                            </select>
                        </div>

                        <!-- Indicateur de modifications non sauvegardées -->
                        <span v-if="hasUnsavedChanges"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-300 animate-pulse">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            Modifications non sauvegardées
                        </span>
                    </div>

                    <!-- Recherche -->
                    <div class="flex items-center gap-2">
                        <div class="relative w-full sm:w-64">
                            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" v-model="searchQuery" placeholder="Rechercher nom, matricule..."
                                class="w-full pl-9 pr-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- En-tête d'impression uniquement -->
            <div class="hidden print:block mb-4 text-center border-b pb-3">
                <h1 class="text-2xl font-bold uppercase tracking-wider text-slate-900">
                    GENDARMERIE NATIONALE - TABLEAU D'AVANCEMENT {{ currentAnnee }}
                </h1>
                <p class="text-xs text-slate-600">
                    Filtre : {{ currentGrade === 'all' ? 'Tous les grades' : currentGrade }} | Date : {{ dateImpression }}
                </p>
            </div>

            <!-- Liste vide -->
            <div v-if="filteredCandidates.length === 0"
                class="bg-white rounded-xl border border-slate-200 p-12 text-center shadow-xs">
                <i class="pi pi-inbox text-5xl text-slate-300 mb-3"></i>
                <h3 class="text-base font-semibold text-slate-700">Aucun candidat trouvé pour cette sélection</h3>
                <p class="text-sm text-slate-400 mt-1">
                    Vérifiez le filtre de grade ou les propositions d'avancement de la rubrique Promotions.
                </p>
            </div>

            <!-- Tableau principal d'avancement -->
            <div v-else class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gradient-to-r from-sky-800 via-sky-700 to-sky-800 text-white font-bold border-b border-sky-900">
                                <th class="py-3 px-2 text-center w-14">Rang</th>
                                <th class="py-3 px-3 min-w-[200px]">Militaire</th>
                                <th class="py-3 px-2 min-w-[130px]">Grade Actuel ➔ Cible</th>
                                <th class="py-3 px-2 min-w-[140px]">Fonction actuelle</th>
                                <th class="py-3 px-2 text-center min-w-[90px]" title="Note de rendement">
                                    Rendement
                                </th>
                                <th class="py-3 px-2 text-center min-w-[90px]" title="Dernier emploi tenu (Bonus)">
                                    Bonus Emploi
                                </th>
                                <th class="py-3 px-2 text-center min-w-[110px]" title="Points pour les diplômes académiques">
                                    Diplômes
                                </th>
                                <th class="py-3 px-2 text-center min-w-[110px]" title="Points pour les décorations et médailles">
                                    Décorations
                                </th>
                                <th class="py-3 px-2 text-center min-w-[110px]" title="Points d'ancienneté dans le grade">
                                    Anc. Grade
                                </th>
                                <th class="py-3 px-2 text-center min-w-[130px] bg-red-900/40" title="Nombre de jours de punition (-0.1 pt/jour)">
                                    Punitions (jours)
                                </th>
                                <th class="py-3 px-2 text-center min-w-[90px]" title="Points limite d'âge">
                                    Limite d'Âge
                                </th>
                                <th class="py-3 px-3 text-center min-w-[110px] bg-sky-900 text-white font-extrabold text-sm">
                                    TOTAL
                                </th>
                                <th class="py-3 px-2 min-w-[150px] print:hidden">Observations</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <!-- Boucle avec regroupement par Grade -->
                            <template v-for="(group, gradeName) in groupedByGrade" :key="gradeName">
                                <!-- En-tête séparateur de Grade -->
                                <tr class="bg-sky-50/90 border-y-2 border-sky-200 font-bold text-sky-900">
                                    <td colspan="13" class="py-2.5 px-4">
                                        <div class="flex items-center justify-between">
                                            <span class="flex items-center gap-2 text-sm tracking-wide">
                                                <i class="pi pi-shield text-sky-700"></i>
                                                GRADE : <span class="uppercase font-extrabold text-sky-950">{{ gradeName }}</span>
                                            </span>
                                            <span class="text-xs bg-sky-200/70 text-sky-900 px-2.5 py-0.5 rounded-full font-semibold">
                                                {{ group.length }} candidat(s) en compétition
                                            </span>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Lignes des militaires au sein de ce grade -->
                                <tr v-for="item in group" :key="item.militaire_id"
                                    :class="[
                                        'transition-colors hover:bg-slate-50/80',
                                        item.rang === 1 ? 'bg-amber-50/40 font-medium' : ''
                                    ]">
                                    <!-- Rang dans le grade -->
                                    <td class="py-2.5 px-2 text-center whitespace-nowrap">
                                        <span v-if="item.rang === 1"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-400 text-amber-950 font-extrabold text-xs shadow-xs border border-amber-500"
                                            title="1er du grade">
                                            🥇 1
                                        </span>
                                        <span v-else-if="item.rang === 2"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-300 text-slate-800 font-extrabold text-xs shadow-xs border border-slate-400"
                                            title="2e du grade">
                                            🥈 2
                                        </span>
                                        <span v-else-if="item.rang === 3"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-700/20 text-amber-900 font-extrabold text-xs shadow-xs border border-amber-700/40"
                                            title="3e du grade">
                                            🥉 3
                                        </span>
                                        <span v-else
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-100 text-slate-600 font-bold text-xs border border-slate-200">
                                            {{ item.rang }}
                                        </span>
                                    </td>

                                    <!-- Militaire -->
                                    <td class="py-2.5 px-3">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-full bg-sky-100 border border-sky-300 overflow-hidden shrink-0 flex items-center justify-center text-sky-800 font-bold text-xs print:hidden">
                                                <img v-if="item.photo_url" :src="item.photo_url" class="w-full h-full object-cover" />
                                                <span v-else>{{ (item.nom?.[0] || 'M') }}</span>
                                            </div>
                                            <div>
                                                <div class="font-extrabold text-slate-900 text-xs uppercase leading-tight">
                                                    {{ item.nom }} {{ item.prenom }}
                                                </div>
                                                <div class="text-[11px] text-slate-500 font-mono mt-0.5">
                                                    Mle: <span class="font-bold text-slate-700">{{ item.matricule }}</span>
                                                    <span v-if="item.mle_grm" class="ml-1 text-slate-400 font-sans">({{ item.mle_grm }})</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Grade Actuel ➔ Cible -->
                                    <td class="py-2.5 px-2">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                                {{ item.grade_actuel }}
                                            </span>
                                            <span v-if="item.grade_cible" class="text-[10px] text-emerald-700 font-bold flex items-center gap-1">
                                                ➔ {{ item.grade_cible }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Fonction actuelle -->
                                    <td class="py-2.5 px-2 text-slate-700">
                                        <span class="line-clamp-2" :title="item.fonction_actuelle">
                                            {{ item.fonction_actuelle || '-' }}
                                        </span>
                                    </td>

                                    <!-- Rendement (input) -->
                                    <td class="py-2.5 px-1.5 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.note_rendement"
                                            @input="handleInputChange(item)"
                                            class="w-16 px-1.5 py-1 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-2 focus:ring-sky-500 focus:border-sky-500" />
                                    </td>

                                    <!-- Bonus Emploi (input) -->
                                    <td class="py-2.5 px-1.5 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.bonus_dernier_emploi"
                                            @input="handleInputChange(item)"
                                            class="w-16 px-1.5 py-1 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-2 focus:ring-sky-500 focus:border-sky-500" />
                                    </td>

                                    <!-- Diplômes (input + texte) -->
                                    <td class="py-2.5 px-1.5 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.points_diplomes"
                                            @input="handleInputChange(item)"
                                            class="w-16 px-1.5 py-1 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-2 focus:ring-sky-500 focus:border-sky-500" />
                                        <div v-if="item.diplomes_texte" class="text-[10px] text-slate-500 truncate max-w-[110px] mt-0.5 mx-auto" :title="item.diplomes_texte">
                                            🎓 {{ item.diplomes_texte }}
                                        </div>
                                    </td>

                                    <!-- Décorations (input + texte) -->
                                    <td class="py-2.5 px-1.5 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.points_decorations"
                                            @input="handleInputChange(item)"
                                            class="w-16 px-1.5 py-1 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-2 focus:ring-sky-500 focus:border-sky-500" />
                                        <div v-if="item.decorations_texte" class="text-[10px] text-amber-700 truncate max-w-[110px] mt-0.5 mx-auto" :title="item.decorations_texte">
                                            🎖️ {{ item.decorations_texte }}
                                        </div>
                                    </td>

                                    <!-- Ancienneté dans le grade (input + années réelles) -->
                                    <td class="py-2.5 px-1.5 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.points_anciennete_grade"
                                            @input="handleInputChange(item)"
                                            class="w-16 px-1.5 py-1 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-2 focus:ring-sky-500 focus:border-sky-500" />
                                        <div class="text-[10px] text-slate-500 mt-0.5">
                                            {{ item.anciennete_grade_annees }} an(s)
                                        </div>
                                    </td>

                                    <!-- Punitions (Nombre de jours x -0.1 point) -->
                                    <td class="py-2.5 px-1.5 text-center bg-red-50/40 border-x border-red-100">
                                        <div class="flex items-center justify-center gap-1">
                                            <input type="number" step="1" min="0" max="365"
                                                v-model.number="item.jours_punition"
                                                @input="handleInputChange(item)"
                                                placeholder="0 j"
                                                class="w-16 px-1.5 py-1 text-center font-bold text-red-700 bg-white border border-red-300 rounded focus:ring-2 focus:ring-red-500 focus:border-red-500" />
                                            <span class="text-[11px] text-slate-500 font-semibold">j</span>
                                        </div>
                                        <!-- Affichage du malus calculé -->
                                        <div class="mt-1">
                                            <span v-if="item.jours_punition > 0"
                                                class="inline-block px-1.5 py-0.2 rounded text-[10px] font-extrabold bg-red-100 text-red-800 border border-red-200">
                                                -{{ item.malus_punition }} pt
                                            </span>
                                            <span v-else class="text-[10px] text-emerald-600 font-bold">
                                                0 pt
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Limite d'âge (input + âge réel) -->
                                    <td class="py-2.5 px-1.5 text-center">
                                        <input type="number" step="0.5" min="0" max="100"
                                            v-model.number="item.points_limite_age"
                                            @input="handleInputChange(item)"
                                            class="w-16 px-1.5 py-1 text-center font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded focus:bg-white focus:ring-2 focus:ring-sky-500 focus:border-sky-500" />
                                        <div class="text-[10px] text-slate-500 mt-0.5">
                                            Âge : {{ item.age }} ans
                                        </div>
                                    </td>

                                    <!-- TOTAL GÉNÉRAL CALCULÉ AUTOMATIQUEMENT -->
                                    <td class="py-2.5 px-3 text-center bg-sky-50/70 border-x border-sky-200">
                                        <span class="inline-block px-2.5 py-1 rounded-lg text-sm font-extrabold shadow-2xs"
                                            :class="item.total_points > 0 ? 'bg-sky-800 text-white' : 'bg-slate-200 text-slate-700'">
                                            {{ Number(item.total_points).toFixed(2) }}
                                        </span>
                                    </td>

                                    <!-- Observations (print hidden) -->
                                    <td class="py-2.5 px-2 print:hidden">
                                        <input type="text" v-model="item.observations"
                                            @change="handleInputChange(item)"
                                            placeholder="Remarque..."
                                            class="w-full px-2 py-1 text-xs border border-slate-300 rounded bg-slate-50 focus:bg-white focus:ring-1 focus:ring-sky-500" />
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Footer du tableau -->
                <div class="p-3 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-2 print:hidden">
                    <div>
                        Affichage de <strong>{{ filteredCandidates.length }}</strong> candidat(s) réparti(s) sur <strong>{{ Object.keys(groupedByGrade).length }}</strong> grade(s).
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span>Classement dynamique mis à jour en temps réel à chaque saisie.</span>
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
    anneesDisponibles: { type: Array, default: () => [] },
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

const dateImpression = new Date().toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
});

// Données locales réactives éditables
const localCandidates = ref(JSON.parse(JSON.stringify(props.candidates)));

// Synchroniser si les props changent (ex: changement d'année ou de filtre serveur)
watch(() => props.candidates, (newVal) => {
    localCandidates.value = JSON.parse(JSON.stringify(newVal));
    hasUnsavedChanges.value = false;
});

/**
 * Calcul instantané de la ligne :
 * - Malus punitions = jours * 0.1
 * - Total = somme des critères - malus
 * - Recalcul du classement par grade
 */
const handleInputChange = (item) => {
    // 1. Calcul du malus punition (-0.1 par jour de punition)
    const jours = Math.max(0, parseInt(item.jours_punition, 10) || 0);
    item.jours_punition = jours;
    item.malus_punition = Math.round(jours * 0.1 * 100) / 100;

    // 2. Normalisation des valeurs numériques
    const rendement = parseFloat(item.note_rendement) || 0;
    const bonus = parseFloat(item.bonus_dernier_emploi) || 0;
    const diplomes = parseFloat(item.points_diplomes) || 0;
    const decos = parseFloat(item.points_decorations) || 0;
    const ancGrade = parseFloat(item.points_anciennete_grade) || 0;
    const limiteAge = parseFloat(item.points_limite_age) || 0;

    // 3. Total général
    const total = rendement + bonus + diplomes + decos + ancGrade + limiteAge - item.malus_punition;
    item.total_points = Math.round(Math.max(0, total) * 100) / 100;

    hasUnsavedChanges.value = true;

    // 4. Recalculer instantanément les rangs par grade
    recalculerRangsDynamiques();
};

/**
 * Recalcule les rangs 1, 2, 3... au sein de chaque grade en mémoire vive
 */
const recalculerRangsDynamiques = () => {
    // Regrouper par grade actuel
    const groups = {};
    localCandidates.value.forEach(c => {
        const g = c.grade_actuel || 'Non défini';
        if (!groups[g]) groups[g] = [];
        groups[g].push(c);
    });

    // Pour chaque grade, trier par total_points décroissant, puis ancienneté
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

        // Réassigner le rang
        groups[grade].forEach((candidat, index) => {
            candidat.rang = index + 1;
        });
    });
};

/**
 * Candidats filtrés par recherche texte
 */
const filteredCandidates = computed(() => {
    if (!searchQuery.value) {
        return localCandidates.value;
    }
    const q = searchQuery.value.toLowerCase().trim();
    return localCandidates.value.filter(c =>
        (c.nom && c.nom.toLowerCase().includes(q)) ||
        (c.prenom && c.prenom.toLowerCase().includes(q)) ||
        (c.matricule && c.matricule.toLowerCase().includes(q)) ||
        (c.mle_grm && c.mle_grm.toLowerCase().includes(q))
    );
});

/**
 * Regroupement des candidats filtrés par Grade
 */
const groupedByGrade = computed(() => {
    const map = {};
    filteredCandidates.value.forEach(c => {
        const grade = c.grade_actuel || 'Non défini';
        if (!map[grade]) {
            map[grade] = [];
        }
        map[grade].push(c);
    });

    // S'assurer que dans chaque groupe les éléments sont bien triés par rang croissant (1, 2, 3...)
    Object.keys(map).forEach(grade => {
        map[grade].sort((a, b) => a.rang - b.rang);
    });

    return map;
});

/**
 * Applique les filtres en rechargeant la page via Inertia
 */
const applyFilters = () => {
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
 * Sauvegarde toutes les notes en base de données
 */
const saveNotes = async () => {
    saving.value = true;
    try {
        const itemsToSave = localCandidates.value.map(c => ({
            militaire_id: c.militaire_id,
            grade_actuel: c.grade_actuel,
            grade_cible: c.grade_cible,
            fonction_actuelle: c.fonction_actuelle,
            note_rendement: c.note_rendement,
            bonus_dernier_emploi: c.bonus_dernier_emploi,
            points_diplomes: c.points_diplomes,
            points_decorations: c.points_decorations,
            points_anciennete_grade: c.points_anciennete_grade,
            jours_punition: c.jours_punition,
            points_limite_age: c.points_limite_age,
            observations: c.observations
        }));

        const response = await axios.post('/avancements/save', {
            annee: currentAnnee.value,
            items: itemsToSave
        });

        if (response.data?.success) {
            hasUnsavedChanges.value = false;
            // Marquer comme enregistré localement
            localCandidates.value.forEach(c => c.est_enregistre = true);
            alert('✅ Notes et classements enregistrés avec succès !');
        }
    } catch (error) {
        console.error('Erreur sauvegarde avancements:', error);
        alert('❌ Erreur lors de la sauvegarde. Veuillez vérifier les valeurs saisies.');
    } finally {
        saving.value = false;
    }
};

/**
 * Exporte vers Excel
 */
const exportExcel = () => {
    const params = new URLSearchParams({
        annee: currentAnnee.value,
        grade: currentGrade.value
    });
    window.location.href = `/avancements/export?${params.toString()}`;
};

/**
 * Lance l'impression
 */
const printTable = () => {
    window.print();
};
</script>

<style scoped>
@media print {
    body {
        background: white !important;
        font-size: 10px !important;
    }
    input {
        border: none !important;
        background: transparent !important;
        padding: 0 !important;
    }
}
</style>
