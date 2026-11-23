<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Chart from 'primevue/chart';
import Button from 'primevue/button';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { downloadExport } from '@/Utils/exportHelper';

const toast = useToast();
const exportingStats = ref(false);

const exportStatistiques = async () => {
    await downloadExport(route('dashboard.export-statistiques'), {
        defaultFilename: `statistiques_dashboard_${new Date().toISOString().slice(0, 10)}.xlsx`,
        toast,
        onStart: () => { exportingStats.value = true; },
        onFinish: () => { exportingStats.value = false; },
        loadingMessage: 'Préparation du fichier des statistiques en cours... Veuillez patienter.',
        successMessage: 'Statistiques Excel téléchargées avec succès !'
    });
};

const props = defineProps({
    statistiques: {
        type: Object,
        default: () => ({
            annee: new Date().getFullYear(),
            total_militaires: 0,
            militaires_actifs: 0,
            proposables_n: 0,
            retraites_n: 0,
            decedes_cette_annee: 0,
            deserteurs: 0,
        })
    },
    chartsData: {
        type: Object,
        default: () => ({
            statuts: [],
            positions: [],
            regions: [],
            categories: [],
            grades: []
        })
    }
});

// État de rafraîchissement
const isRefreshing = ref(false);
const refreshDashboard = () => {
    isRefreshing.value = true;
    router.reload({
        preserveScroll: true,
        onFinish: () => {
            isRefreshing.value = false;
        }
    });
};

const navigateToPosition = (label) => {
    if (!label) return;
    if (label.includes('Non assigné')) {
        router.visit(route('militaires.index', { position: 'Non assigné / En attente' }));
    } else {
        router.visit(route('militaires.index', { search: label, position: label }));
    }
};

// =========================================================================
// PLUGIN CHART.JS POUR DESSINER LES NOMBRES DIRECTEMENT SUR LES BARRES
// =========================================================================
const chartNumbersPlugin = {
    id: 'chartNumbersPlugin',
    afterDatasetsDraw(chart) {
        const { ctx, config } = chart;
        const isHorizontal = config.options?.indexAxis === 'y';

        chart.data.datasets.forEach((dataset, datasetIdx) => {
            const meta = chart.getDatasetMeta(datasetIdx);
            if (meta.hidden) return;

            meta.data.forEach((element, index) => {
                const val = Number(dataset.data[index]) || 0;
                // Ne dessiner le nombre QUE si val > 0 (évite la colonne de zéros superposés)
                if (val <= 0) return;

                ctx.save();
                ctx.font = 'bold 11px Inter, sans-serif';

                if (isHorizontal) {
                    ctx.textAlign = 'left';
                    ctx.textBaseline = 'middle';
                    ctx.fillStyle = '#0f172a';
                    ctx.fillText(` ${val.toLocaleString('fr-FR')}`, element.x + 4, element.y);
                } else {
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';
                    ctx.fillStyle = '#0f172a';
                    const yPos = Math.max(element.y - 3, 14);
                    ctx.fillText(val.toLocaleString('fr-FR'), element.x, yPos);
                }
                ctx.restore();
            });
        });
    }
};

// =========================================================================
// 1. GRAPHIQUE PAR STATUT (Situation Administrative)
// =========================================================================
const totalTousStatuts = computed(() => {
    const list = props.chartsData?.statuts || [];
    return list.reduce((acc, curr) => acc + (Number(curr.value) || 0), 0);
});

const statutsAffiches = computed(() =>
    (props.chartsData?.statuts || []).filter(s => s.value > 0).slice().sort((a, b) => b.value - a.value)
);

const statutChartData = computed(() => {
    const data = statutsAffiches.value;
    return {
        labels: data.map(s => s.label),
        datasets: [
            {
                label: 'Effectif',
                data: data.map(s => s.value),
                backgroundColor: data.map(s => s.color || '#0284c7'),
                borderRadius: 6,
                borderWidth: 1,
                borderColor: '#ffffff',
            }
        ]
    };
});

const statutChartOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    layout: { padding: { right: 36 } },
    plugins: {
        legend: { display: false },
        tooltip: {
            padding: 10,
            cornerRadius: 8,
            callbacks: {
                label: (ctx) => {
                    const total = totalTousStatuts.value || 1;
                    const pct = ((Number(ctx.raw) / total) * 100).toFixed(1).replace('.', ',');
                    return ` ${Number(ctx.raw).toLocaleString('fr-FR')} personnel(s) · ${pct} %`;
                }
            }
        }
    },
    scales: {
        x: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { precision: 0, font: { size: 10 } } },
        y: { grid: { display: false }, ticks: { autoSkip: false, font: { size: 11 } } }
    }
};

// =========================================================================
// 2. GRAPHIQUE PAR POSITION (Déploiement / Localisation Terrain)
// =========================================================================
const positionViewMode = ref('top'); // 'top' | 'actives' | 'all'

const activePositionsList = computed(() => {
    return (props.chartsData?.positions || []).filter(p => p.value > 0);
});

const activePositionsCount = computed(() => activePositionsList.value.length);

const positionsDisplayList = computed(() => {
    const all = props.chartsData?.positions || [];
    if (positionViewMode.value === 'top') {
        return activePositionsList.value.slice(0, 8);
    }
    if (positionViewMode.value === 'actives') {
        return activePositionsList.value;
    }
    return all;
});

const positionChartContainerHeight = computed(() => {
    if (positionViewMode.value === 'top') {
        return '256px';
    }
    const count = positionsDisplayList.value.length;
    return `${Math.max(256, count * 32)}px`;
});

const positionChartData = computed(() => {
    const data = positionsDisplayList.value;
    return {
        labels: data.map(p => p.label),
        datasets: [
            {
                label: 'Militaires déployés',
                data: data.map(p => p.value),
                backgroundColor: data.map(p => p.value > 0 ? '#0284c7' : '#cbd5e1'),
                borderRadius: 6,
                borderWidth: 1,
                borderColor: '#ffffff',
            }
        ]
    };
});

const positionChartOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    layout: {
        padding: { right: 30 }
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            padding: 10,
            cornerRadius: 8,
            callbacks: {
                label: (ctx) => ` Déployés : ${ctx.raw} personnel(s)`
            }
        }
    },
    scales: {
        x: {
            beginAtZero: true,
            grid: { color: '#f1f5f9' },
            ticks: { precision: 0, font: { size: 10 } }
        },
        y: {
            grid: { display: false },
            ticks: {
                autoSkip: false, // Empêche Chart.js de sauter des labels et d'aligner la mauvaise barre
                font: { size: 10 },
                callback: function(val) {
                    const label = this.getLabelForValue(val);
                    return label && label.length > 28 ? label.substr(0, 28) + '…' : label;
                }
            }
        }
    }
};

// =========================================================================
// 3. GRAPHIQUE PAR RÉGION & COMMANDEMENT (Dynamique)
// =========================================================================
const showOnlyActiveRegions = ref(false);

const allRegionsList = computed(() => props.chartsData?.regions || []);

const activeRegionsCount = computed(() => {
    return allRegionsList.value.filter(r => r.value > 0).length;
});

const totalActifsRegions = computed(() => {
    return allRegionsList.value.reduce((acc, curr) => acc + (Number(curr.value) || 0), 0);
});

const regionsDisplayList = computed(() => {
    if (showOnlyActiveRegions.value) {
        return allRegionsList.value.filter(r => r.value > 0);
    }
    return allRegionsList.value;
});

const regionChartData = computed(() => {
    const data = regionsDisplayList.value;
    return {
        labels: data.map(r => r.label.replace(/^(Région de |Région du |District de )/, '')),
        datasets: [
            {
                label: 'Effectifs actifs',
                data: data.map(r => r.value),
                backgroundColor: data.map(r => r.value > 0 ? '#4f46e5' : '#cbd5e1'), // Indigo vs gris
                borderColor: data.map(r => r.value > 0 ? '#4338ca' : '#94a3b8'),
                borderWidth: 1,
                borderRadius: 6,
            }
        ]
    };
});

const regionChartHeight = computed(() => `${Math.max(320, regionsDisplayList.value.length * 26)}px`);

const regionChartOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    layout: { padding: { right: 36 } },
    onClick: (evt, elements) => {
        if (!elements.length) return;
        const item = regionsDisplayList.value[elements[0].index];
        if (item && item.code && item.code !== 'N/A') router.visit(route('carte.index', { region: item.code }));
    },
    onHover: (evt, elements) => {
        const el = evt.native?.target;
        if (el) el.style.cursor = elements.length ? 'pointer' : 'default';
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            padding: 10,
            cornerRadius: 8,
            callbacks: {
                title: (items) => {
                    if (!items.length) return '';
                    const item = regionsDisplayList.value[items[0].dataIndex];
                    return item ? `${item.code ? '[' + item.code + '] ' : ''}${item.label}` : '';
                },
                label: (ctx) => {
                    const total = totalActifsRegions.value || 1;
                    const pct = ((Number(ctx.raw) / total) * 100).toFixed(1).replace('.', ',');
                    return ` ${Number(ctx.raw).toLocaleString('fr-FR')} actifs · ${pct} %  (clic : ouvrir la carte)`;
                }
            }
        }
    },
    scales: {
        x: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { precision: 0, font: { size: 10 } } },
        y: { grid: { display: false }, ticks: { autoSkip: false, font: { size: 11 } } }
    }
};

// =========================================================================
// 4. PYRAMIDE DES CATÉGORIES (Doughnut)
// =========================================================================
const hasCategoriesData = computed(() => (props.chartsData?.categories || []).some(c => c.value > 0));

const categoryChartData = computed(() => {
    const data = props.chartsData?.categories || [];
    return {
        labels: data.map(c => c.label),
        datasets: [
            {
                data: data.map(c => c.value),
                backgroundColor: [
                    '#7c3aed', // Purple
                    '#4f46e5', // Indigo
                    '#0284c7', // Sky
                    '#059669', // Emerald
                    '#d97706', // Amber
                    '#64748b', // Slate
                ],
                borderWidth: 1,
                borderRadius: 6,
                borderColor: '#ffffff',
            }
        ]
    };
});

const categoryChartOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    layout: { padding: { right: 36 } },
    plugins: {
        legend: { display: false },
        tooltip: {
            padding: 10,
            cornerRadius: 8,
            callbacks: {
                label: (ctx) => {
                    const total = (props.chartsData?.categories || []).reduce((a, c) => a + (Number(c.value) || 0), 0) || 1;
                    const pct = ((Number(ctx.raw) / total) * 100).toFixed(1).replace('.', ',');
                    return ` ${Number(ctx.raw).toLocaleString('fr-FR')} personnel(s) · ${pct} %`;
                }
            }
        }
    },
    scales: {
        x: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { precision: 0, font: { size: 10 } } },
        y: { grid: { display: false }, ticks: { autoSkip: false, font: { size: 11 } } }
    }
};

// =========================================================================
// 5. GRAPHIQUE DES GRADES (Barres Horizontales)
// =========================================================================
const hasGradesData = computed(() => (props.chartsData?.grades || []).length > 0);

const gradesChartData = computed(() => {
    const data = props.chartsData?.grades || [];
    return {
        labels: data.map(g => g.label),
        datasets: [
            {
                label: 'Militaires actifs',
                data: data.map(g => g.value),
                backgroundColor: '#0d9488', // Teal 600
                borderColor: '#0f766e',
                borderWidth: 1,
                borderRadius: 4,
            }
        ]
    };
});

const gradesChartHeight = computed(() => `${Math.max(256, (props.chartsData?.grades || []).length * 26)}px`);

const horizontalBarOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    layout: {
        padding: { right: 25 }
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            padding: 10,
            cornerRadius: 8,
            callbacks: {
                label: (ctx) => ` Effectif : ${ctx.raw} personnel(s)`
            }
        }
    },
    scales: {
        x: {
            beginAtZero: true,
            grid: { color: '#f1f5f9' },
            ticks: { precision: 0, font: { size: 10 } }
        },
        y: {
            grid: { display: false },
            ticks: { font: { size: 10 } }
        }
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-3">
                <div class="min-w-0">
                    <h2 class="font-bold text-base sm:text-xl text-white leading-tight flex items-center gap-2 truncate">
                        <i class="pi pi-chart-line text-sky-300 shrink-0"></i>
                        <span>Tableau de bord — Situation des Effectifs & Déploiements</span>
                    </h2>
                    <p class="text-xs text-sky-100 mt-0.5 flex items-center gap-2 flex-wrap">
                        <span>Année : <strong>{{ statistiques?.annee || new Date().getFullYear() }}</strong></span>
                        <span class="text-sky-300">|</span>
                        <span class="flex items-center gap-1.5">
                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            Données dynamiques en temps réel
                        </span>
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0 self-end sm:self-auto">
                    <button type="button"
                       @click="exportStatistiques"
                       :disabled="exportingStats"
                       class="inline-flex items-center gap-1.5 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-500 border border-emerald-400/50 px-3 py-1.5 rounded-md shadow-sm whitespace-nowrap transition-colors cursor-pointer disabled:opacity-50"
                       title="Exporter les statistiques du tableau de bord en Excel">
                        <i :class="exportingStats ? 'pi pi-spin pi-spinner' : 'pi pi-file-excel text-xs'"></i>
                        <span>{{ exportingStats ? 'Exportation...' : 'Exporter Excel' }}</span>
                    </button>
                    <Button icon="pi pi-refresh"
                            label="Rafraîchir"
                            size="small"
                            :loading="isRefreshing"
                            @click="refreshDashboard"
                            class="bg-white/10 hover:bg-white/20 text-white border-white/20 text-xs backdrop-blur-xs" />
                </div>
            </div>
        </template>

        <Toast />

        <div class="py-4 sm:py-8 max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 space-y-6 sm:space-y-8 min-w-0">
            <!-- ========================================================================= -->
            <!-- 1. AXE A : EFFECTIF OPÉRATIONNEL EN ACTIVITÉ (COMPTABILISÉS SÉPARÉMENT)    -->
            <!-- ========================================================================= -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-sky-700"></span>
                            <span>Effectif Opérationnel en Activité</span>
                        </h2>
                        <p class="text-[11px] text-slate-500">
                            Comptabilisation séparée : Actifs au poste, Détachés, En mission et En stage (Somme = Effectif Total)
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <!-- 1. Total Effectif en Activité -->
                    <div @click="router.visit(route('militaires.index', { statut: 'all' }))"
                         class="bg-gradient-to-br from-sky-900 to-slate-900 text-white rounded-2xl shadow-xs p-4 border border-sky-800 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all group"
                         title="Consulter l'ensemble de l'effectif en activité">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] uppercase tracking-wider font-semibold text-sky-200">Total en Activité</span>
                            <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-sky-200 group-hover:bg-white/20 transition-colors">
                                <i class="pi pi-shield text-base"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-black text-white">{{ statistiques.total_militaires }}</div>
                        <div class="text-[11px] text-sky-300 mt-1 flex items-center gap-1 font-medium">
                            <span>Effectif sous les drapeaux</span> <i class="pi pi-arrow-right text-[9px]"></i>
                        </div>
                    </div>

                    <!-- 2. Actifs au Poste (En service régulier) -->
                    <div @click="router.visit(route('militaires.index', { statut: 'actif' }))"
                         class="bg-white rounded-2xl shadow-xs p-4 border border-slate-200 border-l-4 border-emerald-600 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all group"
                         title="Consulter les militaires actifs au poste / aux unités">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-500">Actifs au Poste</span>
                            <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-700 group-hover:bg-emerald-100 transition-colors">
                                <i class="pi pi-users text-base"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-black text-emerald-900">{{ statistiques.actifs_stricts }}</div>
                        <div class="text-[11px] text-emerald-700 mt-1 flex items-center gap-1 font-medium">
                            <span>En service régulier</span> <i class="pi pi-arrow-right text-[9px]"></i>
                        </div>
                    </div>

                    <!-- 3. Détachés -->
                    <div @click="router.visit(route('militaires.index', { statut: 'détaché' }))"
                         class="bg-white rounded-2xl shadow-xs p-4 border border-slate-200 border-l-4 border-sky-500 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all group"
                         title="Consulter les militaires en détachement (ministères, institutions...)">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-500">Détachés</span>
                            <div class="w-8 h-8 rounded-full bg-sky-50 flex items-center justify-center text-sky-600 group-hover:bg-sky-100 transition-colors">
                                <i class="pi pi-external-link text-base"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-black text-sky-900">{{ statistiques.detaches }}</div>
                        <div class="text-[11px] text-sky-700 mt-1 flex items-center gap-1 font-medium">
                            <span>Organismes extérieurs</span> <i class="pi pi-arrow-right text-[9px]"></i>
                        </div>
                    </div>

                    <!-- 4. En Mission -->
                    <div @click="router.visit(route('militaires.index', { statut: 'mission' }))"
                         class="bg-white rounded-2xl shadow-xs p-4 border border-slate-200 border-l-4 border-purple-500 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all group"
                         title="Consulter les militaires déployés en mission opérationnelle">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-500">En Mission</span>
                            <div class="w-8 h-8 rounded-full bg-purple-50 flex items-center justify-center text-purple-600 group-hover:bg-purple-100 transition-colors">
                                <i class="pi pi-send text-base"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-black text-purple-900">{{ statistiques.missions }}</div>
                        <div class="text-[11px] text-purple-700 mt-1 flex items-center gap-1 font-medium">
                            <span>Théâtres d'opération</span> <i class="pi pi-arrow-right text-[9px]"></i>
                        </div>
                    </div>

                    <!-- 5. En Stage -->
                    <div @click="router.visit(route('militaires.index', { statut: 'stage' }))"
                         class="bg-white rounded-2xl shadow-xs p-4 border border-slate-200 border-l-4 border-amber-500 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all group"
                         title="Consulter les militaires en stage / formation">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-500">En Stage</span>
                            <div class="w-8 h-8 rounded-full bg-amber-50 flex items-center justify-center text-amber-600 group-hover:bg-amber-100 transition-colors">
                                <i class="pi pi-book text-base"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-black text-amber-900">{{ statistiques.stages }}</div>
                        <div class="text-[11px] text-amber-700 mt-1 flex items-center gap-1 font-medium">
                            <span>Écoles & formation</span> <i class="pi pi-arrow-right text-[9px]"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. AXE B : HORS EFFECTIF & SUIVI STATUTAIRE (COMPTABILISÉS À PART)        -->
            <!-- ========================================================================= -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span>
                            <span>Hors Effectif & Situations Spécifiques</span>
                        </h2>
                        <p class="text-[11px] text-slate-500">
                            Comptabilisés strictement à part (JAMAIS additionnés à l'effectif actif)
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <!-- 1. Radiés des cadres (Hors effectif) -->
                    <div @click="router.visit(route('militaires.index', { statut: 'radié' }))"
                         class="bg-white rounded-2xl shadow-xs p-4 border border-slate-200 border-l-4 border-slate-500 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all group"
                         title="Consulter les militaires radiés des cadres (hors effectif)">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-500">Radiés</span>
                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 group-hover:bg-slate-200 transition-colors">
                                <i class="pi pi-user-minus text-base"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-black text-slate-800">{{ statistiques.radies }}</div>
                        <div class="text-[11px] text-slate-600 mt-1 flex items-center gap-1 font-medium">
                            <span>Rayés des cadres (à part)</span> <i class="pi pi-arrow-right text-[9px]"></i>
                        </div>
                    </div>

                    <!-- 2. Retraites Prévues (Année N) -->
                    <div @click="router.visit(route('militaires.index', { statut: 'retraites_n' }))"
                         class="bg-white rounded-2xl shadow-xs p-4 border border-slate-200 border-l-4 border-purple-600 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all group"
                         title="Consulter les départs en retraite de l'année en cours">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-500">Retraites {{ statistiques.annee }}</span>
                            <div class="w-8 h-8 rounded-full bg-purple-50 flex items-center justify-center text-purple-600 group-hover:bg-purple-100 transition-colors">
                                <i class="pi pi-calendar text-base"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-black text-purple-700">{{ statistiques.retraites_n }}</div>
                        <div class="text-[11px] text-purple-700 mt-1 flex items-center gap-1 font-medium">
                            <span>Départs prévus {{ statistiques.annee }}</span> <i class="pi pi-arrow-right text-[9px]"></i>
                        </div>
                    </div>

                    <!-- 3. Déserteurs -->
                    <div @click="router.visit(route('militaires.index', { statut: 'deserteur' }))"
                         class="bg-white rounded-2xl shadow-xs p-4 border border-slate-200 border-l-4 border-orange-500 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all group"
                         title="Consulter les militaires en situation de désertion">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-500">Déserteurs</span>
                            <div class="w-8 h-8 rounded-full bg-orange-50 flex items-center justify-center text-orange-600 group-hover:bg-orange-100 transition-colors">
                                <i class="pi pi-exclamation-triangle text-base"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-black text-orange-700">{{ statistiques.deserteurs }}</div>
                        <div class="text-[11px] text-orange-700 mt-1 flex items-center gap-1 font-medium">
                            <span>Militaires déserteurs</span> <i class="pi pi-arrow-right text-[9px]"></i>
                        </div>
                    </div>

                    <!-- 4. Décédés cette année -->
                    <div @click="router.visit(route('militaires.index', { statut: 'decedes_annee_n' }))"
                         class="bg-white rounded-2xl shadow-xs p-4 border border-slate-200 border-l-4 border-rose-600 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all group"
                         title="Consulter les militaires décédés cette année">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-500">Décédés en {{ statistiques.annee }}</span>
                            <div class="w-8 h-8 rounded-full bg-rose-50 flex items-center justify-center text-rose-600 group-hover:bg-rose-100 transition-colors">
                                <i class="pi pi-heart text-base"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-black text-rose-700">{{ statistiques.decedes_cette_annee }}</div>
                        <div class="text-[11px] text-rose-700 mt-1 flex items-center gap-1 font-medium">
                            <span>Consulter liste décès</span> <i class="pi pi-arrow-right text-[9px]"></i>
                        </div>
                    </div>

                    <!-- 5. Proposables Avancement (Année N) -->
                    <div @click="router.visit(route('avancements.index'))"
                         class="bg-white rounded-2xl shadow-xs p-4 border border-slate-200 border-l-4 border-amber-500 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all group"
                         title="Consulter le tableau d'avancement de l'année en cours">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-500">Proposables {{ statistiques.annee }}</span>
                            <div class="w-8 h-8 rounded-full bg-amber-50 flex items-center justify-center text-amber-600 group-hover:bg-amber-100 transition-colors">
                                <i class="pi pi-star text-base"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-black text-amber-700">{{ statistiques.proposables_n }}</div>
                        <div class="text-[11px] text-amber-700 mt-1 flex items-center gap-1 font-medium">
                            <span>Tableau d'avancement</span> <i class="pi pi-arrow-right text-[9px]"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 3. AXE C : REGISTRES OPÉRATIONNELS & SANTÉ MILITAIRE                      -->
            <!-- ========================================================================= -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                            <span>Registres Opérationnels & Santé Militaire</span>
                        </h2>
                        <p class="text-[11px] text-slate-500">
                            Suivi des blessés de guerre, otages en captivité et inaptitudes médicales (Prise d'Armes & Registres)
                        </p>
                    </div>
                    <button type="button" @click="router.visit('/prise-armes')"
                            class="text-xs font-bold text-sky-700 hover:text-sky-900 flex items-center gap-1 transition-colors cursor-pointer">
                        <span>Consulter les registres complets</span>
                        <i class="pi pi-arrow-right text-[10px]"></i>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <!-- 1. Blessés de Guerre -->
                    <div @click="router.visit('/prise-armes?tab=blesses')"
                         class="bg-white rounded-2xl shadow-xs p-4 border border-slate-200 border-l-4 border-amber-500 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all group"
                         title="Consulter le registre des blessés de guerre">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-500">Blessés de Guerre</span>
                            <div class="w-8 h-8 rounded-full bg-amber-50 flex items-center justify-center text-amber-600 group-hover:bg-amber-100 transition-colors">
                                <i class="pi pi-heart-fill text-base"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-black text-amber-700">{{ statistiques.total_blesses || 0 }}</div>
                        <div class="text-[11px] text-amber-700 mt-1 flex items-center gap-1 font-medium">
                            <span>OPEX & Missions commandées</span> <i class="pi pi-arrow-right text-[9px]"></i>
                        </div>
                    </div>

                    <!-- 2. Portés Disparus & Otages -->
                    <div @click="router.visit('/prise-armes?tab=disparus')"
                         class="bg-white rounded-2xl shadow-xs p-4 border border-slate-200 border-l-4 border-rose-500 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all group"
                         title="Consulter le registre des disparus et otages">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-500">Disparus / Otages</span>
                            <div class="w-8 h-8 rounded-full bg-rose-50 flex items-center justify-center text-rose-600 group-hover:bg-rose-100 transition-colors">
                                <i class="pi pi-shield text-base"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-black text-rose-700">{{ statistiques.total_disparus || 0 }}</div>
                        <div class="text-[11px] text-rose-700 mt-1 flex items-center gap-1 font-medium">
                            <span>Maintenus au fichier actif</span> <i class="pi pi-arrow-right text-[9px]"></i>
                        </div>
                    </div>

                    <!-- 3. Grands Malades & Inaptes Sédentaires -->
                    <div @click="router.visit('/prise-armes?tab=malades')"
                         class="bg-white rounded-2xl shadow-xs p-4 border border-slate-200 border-l-4 border-cyan-500 cursor-pointer hover:shadow-md hover:scale-[1.01] transition-all group"
                         title="Consulter le registre des grands malades et inaptes">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-500">Grands Malades / Inaptes</span>
                            <div class="w-8 h-8 rounded-full bg-cyan-50 flex items-center justify-center text-cyan-600 group-hover:bg-cyan-100 transition-colors">
                                <i class="pi pi-user-plus text-base"></i>
                            </div>
                        </div>
                        <div class="text-3xl font-black text-cyan-800">{{ statistiques.total_grands_malades || 0 }}</div>
                        <div class="text-[11px] text-cyan-700 mt-1 flex items-center gap-1 font-medium">
                            <span>Inaptes terrain / Sédentaires</span> <i class="pi pi-arrow-right text-[9px]"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. SECTION CLEF : STATUT vs POSITION (DISTINCTION IMPORTANTE)             -->
            <!-- ========================================================================= -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- --------------------------------------------------------------------- -->
                <!-- GRAPHIQUE A : PAR STATUT (SITUATION ADMINISTRATIVE DE L'AGENT)         -->
                <!-- --------------------------------------------------------------------- -->
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col">
                    <div class="flex items-start justify-between mb-3 border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center text-base font-bold">
                                <i class="pi pi-id-card"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm text-slate-900 flex items-center gap-2">
                                    <span>Situation Administrative (Par Statut)</span>
                                    <span class="text-[10px] bg-slate-100 text-slate-600 font-mono px-2 py-0.5 rounded-full font-medium">
                                        Total : {{ totalTousStatuts }}
                                    </span>
                                </h3>
                                <p class="text-[11px] text-slate-500">
                                    <strong>Statut</strong> = Situation juridique de l'agent (Actif, Détaché, Stage, Mission, Procédure, Déserteur, Retraité...)
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Graphique Statuts -->
                    <div class="h-64 w-full relative">
                        <Chart type="bar"
                               :data="statutChartData"
                               :options="statutChartOptions"
                               :plugins="[chartNumbersPlugin]"
                               class="h-full w-full" />
                    </div>

                    <!-- Grille de tous les points de statuts avec effectif -->
                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <div class="text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-2 flex items-center justify-between">
                            <span>Détail par statut (cliquer pour filtrer) :</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            <div v-for="st in props.chartsData?.statuts || []"
                                 :key="st.code"
                                 @click="router.visit(route('militaires.index', { statut: st.code }))"
                                 class="p-2 rounded-xl border transition-all cursor-pointer hover:shadow-xs flex items-center justify-between gap-1 group"
                                 :class="st.value > 0 ? (st.bg || 'bg-slate-50 text-slate-800 border-slate-200') : 'bg-slate-50/50 text-slate-400 border-slate-100 hover:bg-slate-50'">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: st.color }"></span>
                                    <span class="text-xs font-medium truncate" :title="st.label">{{ st.label }}</span>
                                </div>
                                <span class="text-xs font-black px-1.5 py-0.5 rounded-md shrink-0 font-mono"
                                      :class="st.value > 0 ? 'bg-white shadow-xs font-bold' : 'text-slate-400'">
                                    {{ st.value }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- --------------------------------------------------------------------- -->
                <!-- GRAPHIQUE B : PAR POSITION (EMPLACEMENT PHYSIQUE / DÉPLOIEMENT TERRAIN)-->
                <!-- --------------------------------------------------------------------- -->
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col">
                    <div class="flex items-start justify-between mb-3 border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-sky-100 text-sky-800 flex items-center justify-center text-base font-bold">
                                <i class="pi pi-compass"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm text-slate-900 flex items-center gap-2">
                                    <span>Déploiement Physique (Par Position)</span>
                                    <span class="text-[10px] bg-slate-100 text-slate-600 font-mono px-2 py-0.5 rounded-full font-medium">
                                        {{ positionsDisplayList.length }} positions
                                    </span>
                                </h3>
                                <p class="text-[11px] text-slate-500">
                                    <strong>Position</strong> = Lieu réel / Théâtre d'opération où se trouve l'agent (À l'Unité, Escadrons, GIGM, Koulouba...)
                                </p>
                            </div>
                        </div>

                        <!-- Filtre positions avec effectif vs toutes -->
                        <div class="flex items-center bg-slate-100 p-0.5 rounded-lg text-[10px] font-medium shrink-0">
                            <button type="button"
                                    @click="positionViewMode = 'top'"
                                    class="px-2 py-1 rounded-md transition-colors"
                                    :class="positionViewMode === 'top' ? 'bg-white text-sky-900 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                                Top 8
                            </button>
                            <button type="button"
                                    @click="positionViewMode = 'actives'"
                                    class="px-2 py-1 rounded-md transition-colors"
                                    :class="positionViewMode === 'actives' ? 'bg-white text-sky-900 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                                Effectif > 0 ({{ activePositionsCount }})
                            </button>
                            <button type="button"
                                    @click="positionViewMode = 'all'"
                                    class="px-2 py-1 rounded-md transition-colors"
                                    :class="positionViewMode === 'all' ? 'bg-white text-sky-900 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                                Toutes
                            </button>
                        </div>
                    </div>

                    <!-- Graphique Positions (Barres horizontales avec nombres à droite) -->
                    <div class="w-full relative transition-all overflow-y-auto max-h-72 custom-scrollbar" :style="{ minHeight: '220px', height: positionChartContainerHeight }">
                        <Chart v-if="positionsDisplayList.length > 0"
                               type="bar"
                               :data="positionChartData"
                               :options="positionChartOptions"
                               :plugins="[chartNumbersPlugin]"
                               class="h-full w-full" />
                        <div v-else class="h-full min-h-[200px] flex flex-col items-center justify-center text-slate-400">
                            <i class="pi pi-map-marker text-3xl mb-1 opacity-40"></i>
                            <p class="text-xs">Aucune position avec effectif actif pour le moment.</p>
                            <Button label="Voir toutes les positions"
                                    size="small"
                                    text
                                    class="text-xs mt-2 text-sky-600"
                                    @click="showOnlyActivePositions = false" />
                        </div>
                    </div>

                    <!-- Liste déroulante / badges des positions avec effectifs -->
                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <div class="text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-2 flex items-center justify-between">
                            <span>Points de déploiement répertoriés :</span>
                            <span class="text-[10px] text-slate-400 font-normal">Cliquer pour chercher les militaires</span>
                        </div>
                        <div class="max-h-28 overflow-y-auto space-y-1 pr-1 custom-scrollbar">
                            <div v-for="pos in (props.chartsData?.positions || [])"
                                 :key="pos.label"
                                 @click="navigateToPosition(pos.label)"
                                 class="flex items-center justify-between px-2.5 py-1.5 rounded-lg border text-xs cursor-pointer transition-colors"
                                 :class="pos.value > 0 ? 'bg-sky-50/70 border-sky-200 text-sky-900 font-medium hover:bg-sky-100' : 'bg-slate-50/50 border-slate-100 text-slate-500 hover:bg-slate-100'">
                                <div class="flex items-center gap-2 truncate">
                                    <i class="pi pi-map-marker text-[10px]" :class="pos.value > 0 ? 'text-sky-600' : 'text-slate-400'"></i>
                                    <span class="truncate" :title="pos.label">{{ pos.label }}</span>
                                </div>
                                <span class="px-2 py-0.5 rounded-full font-mono text-[11px] font-bold"
                                      :class="pos.value > 0 ? 'bg-sky-700 text-white shadow-2xs' : 'bg-slate-200 text-slate-600'">
                                    {{ pos.value }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 3. SECTION RÉGIONS & COMMANDEMENTS : DYNAMIQUE ET ÉTENDUE                 -->
            <!-- ========================================================================= -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-indigo-100 text-indigo-800 flex items-center justify-center text-base font-bold">
                            <i class="pi pi-map"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-slate-900 flex items-center gap-2">
                                <span>Effectifs Actifs par Région & Commandement</span>
                                <span class="text-[10px] bg-indigo-50 text-indigo-700 border border-indigo-200 font-mono px-2 py-0.5 rounded-full font-semibold">
                                    {{ totalActifsRegions }} actifs affectés
                                </span>
                            </h3>
                            <p class="text-[11px] text-slate-500">
                                11 Régions Militaires territoriales + DGGN + GSIGN + Commandements Spécialisés
                            </p>
                        </div>
                    </div>

                    <!-- Filtre Toutes Régions vs Régions occupées -->
                    <div class="flex items-center bg-slate-100 p-0.5 rounded-lg text-[10px] font-medium shrink-0 self-start sm:self-auto">
                        <button type="button"
                                @click="showOnlyActiveRegions = false"
                                class="px-2.5 py-1 rounded-md transition-colors"
                                :class="!showOnlyActiveRegions ? 'bg-white text-indigo-900 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                            Toutes les Régions ({{ allRegionsList.length }})
                        </button>
                        <button type="button"
                                @click="showOnlyActiveRegions = true"
                                class="px-2.5 py-1 rounded-md transition-colors"
                                :class="showOnlyActiveRegions ? 'bg-white text-indigo-900 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                            Avec Effectif > 0 ({{ activeRegionsCount }})
                        </button>
                    </div>
                </div>

                <!-- Graphique Régions (Barres verticales avec nombres au-dessus de chaque barre) -->
                <div class="w-full relative" :style="{ height: regionChartHeight }">
                    <Chart v-if="regionsDisplayList.length > 0"
                           type="bar"
                           :data="regionChartData"
                           :options="regionChartOptions"
                           :plugins="[chartNumbersPlugin]"
                           class="h-full w-full" />
                    <div v-else class="h-full flex flex-col items-center justify-center text-slate-400">
                        <i class="pi pi-map text-3xl mb-1 opacity-40"></i>
                        <p class="text-xs">Aucune région avec effectif actif pour le moment.</p>
                        <Button label="Voir toutes les régions"
                                size="small"
                                text
                                class="text-xs mt-2 text-indigo-600"
                                @click="showOnlyActiveRegions = false" />
                    </div>
                </div>

                <!-- Grille synthétique de toutes les régions avec leur effectif exact -->
                <div class="mt-4 pt-3 border-t border-slate-100">
                    <div class="text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-2 flex items-center justify-between">
                        <span>Synthèse de tous les commandements régionaux (cliquer pour filtrer) :</span>
                        <span class="text-[10px] text-slate-400 font-normal">{{ allRegionsList.length }} commandements configurés</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-2">
                        <div v-for="reg in allRegionsList"
                             :key="reg.code || reg.label"
                             @click="router.visit(route('militaires.index', { region: reg.label }))"
                             class="p-2 rounded-xl border transition-all cursor-pointer hover:shadow-xs flex flex-col justify-between"
                             :class="reg.value > 0 ? 'bg-indigo-50/80 border-indigo-200 text-indigo-950 font-medium' : 'bg-slate-50/40 border-slate-100 text-slate-400 hover:bg-slate-100'">
                            <div class="flex items-center justify-between gap-1 mb-1">
                                <span class="text-[10px] font-mono uppercase px-1.5 py-0.2 rounded"
                                      :class="reg.value > 0 ? 'bg-indigo-200 text-indigo-900 font-bold' : 'bg-slate-200 text-slate-600'">
                                    {{ reg.code || 'REG' }}
                                </span>
                                <span class="text-xs font-black font-mono"
                                      :class="reg.value > 0 ? 'text-indigo-900 text-sm' : 'text-slate-400'">
                                    {{ reg.value }}
                                </span>
                            </div>
                            <span class="text-[11px] truncate leading-tight" :title="reg.label">{{ reg.label }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 4. CATÉGORIES STATUTAIRES & GRADES                                        -->
            <!-- ========================================================================= -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- GRAPHIQUE C : Catégories Statutaires -->
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col">
                    <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-purple-100 text-purple-800 flex items-center justify-center text-base font-bold">
                                <i class="pi pi-shield"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm text-slate-900">Catégories Statutaires</h3>
                                <p class="text-[11px] text-slate-500">Officiers, Sous-Officiers et Militaires du Rang en activité</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 min-h-[260px] flex items-center justify-center">
                        <div v-if="hasCategoriesData" class="w-full h-64 flex items-center justify-center">
                            <Chart type="bar"
                                   :data="categoryChartData"
                                   :options="categoryChartOptions"
                                   :plugins="[chartNumbersPlugin]"
                                   class="h-full w-full" />
                        </div>
                        <div v-else class="text-center py-10 text-slate-400">
                            <i class="pi pi-chart-pie text-3xl mb-1 opacity-40"></i>
                            <p class="text-xs">Aucune donnée de catégorie disponible pour le moment.</p>
                        </div>
                    </div>

                    <!-- Liste détaillée des catégories avec effectif -->
                    <div class="mt-3 pt-3 border-t border-slate-100 grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <div v-for="cat in (props.chartsData?.categories || [])"
                             :key="cat.label"
                             class="p-2 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-between gap-1 text-xs">
                            <span class="text-slate-700 truncate" :title="cat.label">{{ cat.label }}</span>
                            <span class="font-mono font-bold px-1.5 py-0.5 rounded-md"
                                  :class="cat.value > 0 ? 'bg-purple-100 text-purple-900' : 'text-slate-400'">
                                {{ cat.value }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- GRAPHIQUE D : Grades en Service Actif -->
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex flex-col">
                    <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-teal-100 text-teal-800 flex items-center justify-center text-base font-bold">
                                <i class="pi pi-star"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm text-slate-900">Grades les Plus Représentés (Actifs)</h3>
                                <p class="text-[11px] text-slate-500">Répartition par grade des effectifs en service</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 min-h-[260px] flex items-center justify-center">
                        <div v-if="hasGradesData" class="w-full" :style="{ height: gradesChartHeight }">
                            <Chart type="bar"
                                   :data="gradesChartData"
                                   :options="horizontalBarOptions"
                                   :plugins="[chartNumbersPlugin]"
                                   class="h-full w-full" />
                        </div>
                        <div v-else class="text-center py-10 text-slate-400">
                            <i class="pi pi-chart-bar text-3xl mb-1 opacity-40"></i>
                            <p class="text-xs">Aucune donnée de grade disponible.</p>
                        </div>
                    </div>

                    <!-- Résumé grades -->
                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                        <span>Grades avec effectifs enregistrés : <strong>{{ (props.chartsData?.grades || []).length }}</strong></span>
                        <button type="button"
                                @click="router.visit(route('militaires.index'))"
                                class="text-teal-700 hover:text-teal-900 font-medium flex items-center gap-1">
                            <span>Consulter tous les grades</span>
                            <i class="pi pi-arrow-right text-[10px]"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
