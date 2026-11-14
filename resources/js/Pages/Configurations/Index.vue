<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-bold text-xl text-white flex items-center gap-2">
                        <i class="pi pi-cog text-sky-300"></i>
                        Configuration du Maillage Territorial & des Positions
                    </h2>
                    <p class="text-xs text-sky-100 mt-0.5">
                        Gestion des Régions, Unités rattachées (Brigades, Escadrons, Directions) et Positions administratives/opérationnelles
                    </p>
                </div>
            </div>
        </template>

        <Toast />

        <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Statistiques de synthèse -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-200 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-sky-100 text-sky-800 flex items-center justify-center text-xl shrink-0">
                        <i class="pi pi-map"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-slate-900">{{ stats.total_regions }}</div>
                        <div class="text-xs text-slate-500 font-medium">Régions & Commandements</div>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-200 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center text-xl shrink-0">
                        <i class="pi pi-shield"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-slate-900">{{ stats.total_unites }}</div>
                        <div class="text-xs text-slate-500 font-medium">Total Unités répertoriées</div>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-200 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-teal-100 text-teal-800 flex items-center justify-center text-xl shrink-0">
                        <i class="pi pi-check-circle"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-teal-700">{{ stats.unites_operationnelles }}</div>
                        <div class="text-xs text-slate-500 font-medium">Unités Opérationnelles</div>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-200 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center text-xl shrink-0">
                        <i class="pi pi-compass"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-slate-900">{{ stats.total_positions }}</div>
                        <div class="text-xs text-slate-500 font-medium">Positions administratives / OPS</div>
                    </div>
                </div>
            </div>

            <!-- Navigation par Onglets -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="flex border-b border-slate-200 bg-slate-50/70 px-4 pt-3 gap-2">
                    <button
                        @click="activeTab = 'unites'"
                        :class="[
                            'px-4 py-2.5 font-semibold text-sm rounded-t-lg transition-all flex items-center gap-2 border-b-2',
                            activeTab === 'unites'
                                ? 'bg-white text-sky-800 border-sky-600 shadow-sm'
                                : 'text-slate-600 border-transparent hover:text-slate-900 hover:bg-slate-100/60'
                        ]"
                    >
                        <i class="pi pi-shield"></i>
                        <span>Unités & Écoles / Directions ({{ unites.length }})</span>
                    </button>

                    <button
                        @click="activeTab = 'regions'"
                        :class="[
                            'px-4 py-2.5 font-semibold text-sm rounded-t-lg transition-all flex items-center gap-2 border-b-2',
                            activeTab === 'regions'
                                ? 'bg-white text-sky-800 border-sky-600 shadow-sm'
                                : 'text-slate-600 border-transparent hover:text-slate-900 hover:bg-slate-100/60'
                        ]"
                    >
                        <i class="pi pi-map"></i>
                        <span>Régions & Commandements ({{ regions.length }})</span>
                    </button>

                    <button
                        @click="activeTab = 'positions'"
                        :class="[
                            'px-4 py-2.5 font-semibold text-sm rounded-t-lg transition-all flex items-center gap-2 border-b-2',
                            activeTab === 'positions'
                                ? 'bg-white text-sky-800 border-sky-600 shadow-sm'
                                : 'text-slate-600 border-transparent hover:text-slate-900 hover:bg-slate-100/60'
                        ]"
                    >
                        <i class="pi pi-compass"></i>
                        <span>Positions (Où se trouvent les personnels) ({{ positions.length }})</span>
                    </button>
                </div>

                <!-- CONTENU DE L'ONGLET 1 : UNITÉS -->
                <div v-if="activeTab === 'unites'" class="p-5 space-y-4">
                    <!-- Barre d'outils -->
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <div class="flex flex-wrap items-center gap-3 flex-1">
                            <div class="relative w-full sm:w-64">
                                <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <InputText
                                    v-model="searchUnite"
                                    placeholder="Rechercher une unité, brigade, escadron..."
                                    class="w-full pl-9 text-xs"
                                />
                            </div>

                            <Select
                                v-model="filterRegion"
                                :options="regionOptionsWithAll"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Filtrer par Région"
                                class="w-full sm:w-56 text-xs"
                            />

                            <Select
                                v-model="filterOperationnel"
                                :options="operationnelOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Statut opérationnel"
                                class="w-full sm:w-48 text-xs"
                            />
                        </div>

                        <Button
                            label="Nouvelle Unité"
                            icon="pi pi-plus"
                            class="bg-sky-700 hover:bg-sky-800 text-white text-xs px-3 py-2 shrink-0 border-0"
                            @click="openCreateUniteDialog"
                        />
                    </div>

                    <!-- Tableau des unités -->
                    <div class="overflow-x-auto border border-slate-200 rounded-lg">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-100 text-slate-700 font-semibold border-b border-slate-200">
                                <tr>
                                    <th class="py-2.5 px-3">Nom de l'Unité / Brigade / Direction</th>
                                    <th class="py-2.5 px-3">Région de Rattachement</th>
                                    <th class="py-2.5 px-3">Type</th>
                                    <th class="py-2.5 px-3 text-center">Statut</th>
                                    <th class="py-2.5 px-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr v-for="u in paginatedUnites" :key="u.id" class="hover:bg-slate-50 transition-colors">
                                    <td class="py-2.5 px-3">
                                        <div class="font-medium text-slate-900">{{ u.nom }}</div>
                                        <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-500">
                                            <span v-if="u.ville" class="inline-flex items-center gap-1">
                                                <i class="pi pi-building text-[10px] text-slate-400"></i>{{ u.ville }}
                                            </span>
                                            <span
                                                v-if="u.latitude !== null && u.longitude !== null"
                                                class="inline-flex items-center gap-1 font-mono text-[10px] text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200"
                                                :title="'GPS : ' + u.latitude + ', ' + u.longitude"
                                            >
                                                <i class="pi pi-map-marker text-[9px] text-emerald-600"></i>
                                                {{ Number(u.latitude).toFixed(4) }}, {{ Number(u.longitude).toFixed(4) }}
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1 text-[10px] text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200"
                                            >
                                                <i class="pi pi-exclamation-triangle text-[9px] text-amber-500"></i>
                                                Sans GPS
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold bg-sky-50 text-sky-800 border border-sky-200">
                                            <span class="font-mono text-[10px] text-sky-600 font-bold">{{ u.region?.code }}</span>
                                            <span>{{ u.region?.nom }}</span>
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-600">
                                        <span class="inline-block px-2 py-0.5 rounded text-[11px] bg-slate-100 text-slate-700">
                                            {{ u.type || 'Autre' }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span
                                            v-if="u.est_operationnelle"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800"
                                        >
                                            <i class="pi pi-check text-[9px]"></i> Opérationnelle
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-800"
                                        >
                                            <i class="pi pi-clock text-[9px]"></i> En projet (0 effectif)
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-right space-x-1 whitespace-nowrap">
                                        <Button
                                            icon="pi pi-pencil"
                                            text
                                            rounded
                                            severity="secondary"
                                            class="!w-7 !h-7 text-xs text-sky-700 hover:bg-sky-50"
                                            title="Modifier"
                                            @click="editUnite(u)"
                                        />
                                        <Button
                                            icon="pi pi-trash"
                                            text
                                            rounded
                                            severity="danger"
                                            class="!w-7 !h-7 text-xs text-red-600 hover:bg-red-50"
                                            title="Supprimer"
                                            @click="confirmDeleteUnite(u)"
                                        />
                                    </td>
                                </tr>
                                <tr v-if="filteredUnites.length === 0">
                                    <td colspan="5" class="py-8 text-center text-slate-400 italic">
                                        Aucune unité ne correspond aux critères de recherche.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination locale pour les 300+ unités -->
                    <div class="flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-2 pt-2">
                        <div>
                            Affichage de {{ unitesStartIndex + 1 }} à {{ Math.min(unitesEndIndex, filteredUnites.length) }} sur {{ filteredUnites.length }} unités
                        </div>
                        <div class="flex items-center gap-1">
                            <Button
                                icon="pi pi-chevron-left"
                                text
                                :disabled="unitesCurrentPage === 1"
                                class="!w-8 !h-8"
                                @click="unitesCurrentPage--"
                            />
                            <span class="px-2 font-medium">Page {{ unitesCurrentPage }} / {{ unitesTotalPages || 1 }}</span>
                            <Button
                                icon="pi pi-chevron-right"
                                text
                                :disabled="unitesCurrentPage >= unitesTotalPages"
                                class="!w-8 !h-8"
                                @click="unitesCurrentPage++"
                            />
                        </div>
                    </div>
                </div>

                <!-- CONTENU DE L'ONGLET 2 : RÉGIONS -->
                <div v-if="activeTab === 'regions'" class="p-5 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <div class="relative w-full sm:w-72">
                            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <InputText
                                v-model="searchRegion"
                                placeholder="Rechercher par code ou nom..."
                                class="w-full pl-9 text-xs"
                            />
                        </div>

                        <Button
                            label="Nouvelle Région"
                            icon="pi pi-plus"
                            class="bg-sky-700 hover:bg-sky-800 text-white text-xs px-3 py-2 shrink-0 border-0"
                            @click="openCreateRegionDialog"
                        />
                    </div>

                    <div class="overflow-x-auto border border-slate-200 rounded-lg">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-100 text-slate-700 font-semibold border-b border-slate-200">
                                <tr>
                                    <th class="py-2.5 px-3">Code Officiel</th>
                                    <th class="py-2.5 px-3">Nom de la Région / Commandement</th>
                                    <th class="py-2.5 px-3">Description</th>
                                    <th class="py-2.5 px-3 text-center">Unités Rattachées</th>
                                    <th class="py-2.5 px-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr v-for="r in filteredRegions" :key="r.id" class="hover:bg-slate-50 transition-colors">
                                    <td class="py-2.5 px-3 font-mono font-bold text-sky-800">
                                        <span class="px-2 py-0.5 rounded bg-sky-100 text-sky-900 border border-sky-300">
                                            {{ r.code }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <div class="font-semibold text-slate-900">{{ r.nom }}</div>
                                        <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-500">
                                            <span v-if="r.chef_lieu" class="inline-flex items-center gap-1">
                                                <i class="pi pi-compass text-[10px] text-slate-400"></i>Chef-lieu : {{ r.chef_lieu }}
                                            </span>
                                            <span
                                                v-if="r.latitude !== null && r.longitude !== null"
                                                class="inline-flex items-center gap-1 font-mono text-[10px] text-sky-700 bg-sky-50 px-1.5 py-0.5 rounded border border-sky-200"
                                            >
                                                <i class="pi pi-map-marker text-[9px]"></i>
                                                {{ Number(r.latitude).toFixed(4) }}, {{ Number(r.longitude).toFixed(4) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-500">
                                        {{ r.description || '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-bold text-slate-700">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-100 text-slate-800 text-xs">
                                            {{ r.unites_count ?? 0 }} unité(s)
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-right space-x-1 whitespace-nowrap">
                                        <Button
                                            icon="pi pi-pencil"
                                            text
                                            rounded
                                            severity="secondary"
                                            class="!w-7 !h-7 text-xs text-sky-700 hover:bg-sky-50"
                                            title="Modifier"
                                            @click="editRegion(r)"
                                        />
                                        <Button
                                            icon="pi pi-trash"
                                            text
                                            rounded
                                            severity="danger"
                                            class="!w-7 !h-7 text-xs text-red-600 hover:bg-red-50"
                                            title="Supprimer"
                                            @click="confirmDeleteRegion(r)"
                                        />
                                    </td>
                                </tr>
                                <tr v-if="filteredRegions.length === 0">
                                    <td colspan="5" class="py-8 text-center text-slate-400 italic">
                                        Aucune région trouvée.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- CONTENU DE L'ONGLET 3 : POSITIONS -->
                <div v-if="activeTab === 'positions'" class="p-5 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <div class="relative w-full sm:w-72">
                            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <InputText
                                v-model="searchPosition"
                                placeholder="Rechercher une position..."
                                class="w-full pl-9 text-xs"
                            />
                        </div>

                        <Button
                            label="Nouvelle Position"
                            icon="pi pi-plus"
                            class="bg-sky-700 hover:bg-sky-800 text-white text-xs px-3 py-2 shrink-0 border-0"
                            @click="openCreatePositionDialog"
                        />
                    </div>

                    <div class="overflow-x-auto border border-slate-200 rounded-lg">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-100 text-slate-700 font-semibold border-b border-slate-200">
                                <tr>
                                    <th class="py-2.5 px-3">Libellé de la Position</th>
                                    <th class="py-2.5 px-3">Type</th>
                                    <th class="py-2.5 px-3">Description / Précisions</th>
                                    <th class="py-2.5 px-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr v-for="p in filteredPositions" :key="p.id" class="hover:bg-slate-50 transition-colors">
                                    <td class="py-2.5 px-3 font-semibold text-slate-900">
                                        {{ p.nom }}
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded text-[11px] font-semibold',
                                                p.type === 'operationnelle'
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-indigo-100 text-indigo-800'
                                            ]"
                                        >
                                            {{ p.type === 'operationnelle' ? 'Théâtre OPS' : 'Administrative' }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-500">
                                        {{ p.description || '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right space-x-1 whitespace-nowrap">
                                        <Button
                                            icon="pi pi-pencil"
                                            text
                                            rounded
                                            severity="secondary"
                                            class="!w-7 !h-7 text-xs text-sky-700 hover:bg-sky-50"
                                            title="Modifier"
                                            @click="editPosition(p)"
                                        />
                                        <Button
                                            icon="pi pi-trash"
                                            text
                                            rounded
                                            severity="danger"
                                            class="!w-7 !h-7 text-xs text-red-600 hover:bg-red-50"
                                            title="Supprimer"
                                            @click="confirmDeletePosition(p)"
                                        />
                                    </td>
                                </tr>
                                <tr v-if="filteredPositions.length === 0">
                                    <td colspan="4" class="py-8 text-center text-slate-400 italic">
                                        Aucune position trouvée.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- MODALS / DIALOGS                                               -->
        <!-- ============================================================== -->

        <!-- MODAL RÉGION -->
        <Dialog
            v-model:visible="regionDialogVisible"
            :header="regionEditMode ? 'Modifier la Région' : 'Ajouter une Région'"
            :modal="true"
            class="w-full max-w-lg"
        >
            <div class="space-y-4 pt-2">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Code Officiel <span class="text-red-500">*</span>
                        </label>
                        <InputText
                            v-model="regionForm.code"
                            placeholder="Ex: R1, R2, DGGN, GSIGN..."
                            class="w-full text-xs font-mono"
                        />
                        <small class="text-[10px] text-slate-500">Le code court reconnu pour la région.</small>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Chef-lieu
                        </label>
                        <InputText
                            v-model="regionForm.chef_lieu"
                            placeholder="Ex: Bamako, Kayes, Gao..."
                            class="w-full text-xs"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Nom complet de la Région <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        v-model="regionForm.nom"
                        placeholder="Ex: REGION N°1, Région de Kayes..."
                        class="w-full text-xs"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            <i class="pi pi-map-marker text-sky-600 mr-1"></i>Latitude GPS (WGS84)
                        </label>
                        <InputText
                            v-model="regionForm.latitude"
                            type="number"
                            step="0.000001"
                            placeholder="Ex: 14.4469"
                            class="w-full text-xs font-mono"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            <i class="pi pi-map-marker text-sky-600 mr-1"></i>Longitude GPS (WGS84)
                        </label>
                        <InputText
                            v-model="regionForm.longitude"
                            type="number"
                            step="0.000001"
                            placeholder="Ex: -11.4445"
                            class="w-full text-xs font-mono"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Description / Notes</label>
                    <InputText
                        v-model="regionForm.description"
                        placeholder="Ex: District de Bamako et périphérie..."
                        class="w-full text-xs"
                    />
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-between w-full">
                    <div>
                        <Button
                            v-if="regionEditMode"
                            label="Supprimer cette région"
                            icon="pi pi-trash"
                            severity="danger"
                            text
                            class="!text-xs text-red-600 hover:bg-red-50"
                            @click="triggerDelete('region', regionForm)"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <Button label="Annuler" text severity="secondary" @click="regionDialogVisible = false" />
                        <Button
                            :label="regionEditMode ? 'Enregistrer les modifications' : 'Créer la région'"
                            icon="pi pi-check"
                            class="bg-sky-700 hover:bg-sky-800 text-white border-0 text-xs"
                            :loading="savingRegion"
                            @click="saveRegion"
                        />
                    </div>
                </div>
            </template>
        </Dialog>

        <!-- MODAL UNITÉ -->
        <Dialog
            v-model:visible="uniteDialogVisible"
            :header="uniteEditMode ? 'Modifier l\'Unité' : 'Ajouter une Unité / Brigade / Escadron'"
            :modal="true"
            class="w-full max-w-xl"
        >
            <div class="space-y-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Région de Rattachement <span class="text-red-500">*</span>
                    </label>
                    <Select
                        v-model="uniteForm.region_id"
                        :options="regions"
                        optionLabel="nom"
                        optionValue="id"
                        placeholder="Sélectionner la région parente"
                        filter
                        class="w-full text-xs"
                    >
                        <template #option="slotProps">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-bold text-sky-800">[{{ slotProps.option.code }}]</span>
                                <span>{{ slotProps.option.nom }}</span>
                            </div>
                        </template>
                    </Select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Nom officiel de l'Unité <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        v-model="uniteForm.nom"
                        placeholder="Ex: Brigade Territoriale de Sénou, Escadron 2/1 de Kayes..."
                        class="w-full text-xs"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Type d'Unité</label>
                        <Select
                            v-model="uniteForm.type"
                            :options="typeUniteOptions"
                            placeholder="Choisir le type"
                            class="w-full text-xs"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Code abrégé</label>
                        <InputText
                            v-model="uniteForm.code"
                            placeholder="Ex: BT-SENOU, ESC-2/1"
                            class="w-full text-xs"
                        />
                    </div>
                </div>

                <!-- Ville et Coordonnées GPS -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            <i class="pi pi-building text-slate-400 mr-1"></i>Ville / Localité
                        </label>
                        <InputText
                            v-model="uniteForm.ville"
                            placeholder="Ex: Kayes, Sénou, Gao..."
                            class="w-full text-xs"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            <i class="pi pi-map-marker text-sky-600 mr-1"></i>Latitude GPS (WGS84)
                        </label>
                        <InputText
                            v-model="uniteForm.latitude"
                            type="number"
                            step="0.000001"
                            placeholder="Ex: 14.4469"
                            class="w-full text-xs font-mono"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            <i class="pi pi-map-marker text-sky-600 mr-1"></i>Longitude GPS (WGS84)
                        </label>
                        <InputText
                            v-model="uniteForm.longitude"
                            type="number"
                            step="0.000001"
                            placeholder="Ex: -11.4445"
                            class="w-full text-xs font-mono"
                        />
                    </div>
                </div>

                <div class="bg-sky-50 border border-sky-200 rounded-lg p-2.5 text-[11px] text-sky-900 flex items-start gap-2">
                    <i class="pi pi-info-circle text-sky-600 mt-0.5 shrink-0"></i>
                    <span>
                        <strong>Point sur la Carte Opérationnelle :</strong> Ces coordonnées (format décimal WGS84, ex: <code>14.4469</code> / <code>-11.4445</code>) déterminent l'emplacement exact du point de l'unité sur la carte. Modifiez-les pour ajuster la position de l'unité.
                    </span>
                </div>

                <div class="pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="uniteForm.est_operationnelle"
                            class="rounded text-sky-700 focus:ring-sky-500 h-4 w-4"
                        />
                        <span class="text-xs font-medium text-slate-700">
                            Unité Opérationnelle (actuellement pourvue en personnel)
                        </span>
                    </label>
                    <small class="block text-[11px] text-slate-500 ml-6">
                        Décocher si c'est une unité créée par décret mais ayant un effectif de 0 pour l'instant.
                    </small>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-between w-full">
                    <div>
                        <Button
                            v-if="uniteEditMode"
                            label="Supprimer cette unité"
                            icon="pi pi-trash"
                            severity="danger"
                            text
                            class="!text-xs text-red-600 hover:bg-red-50"
                            @click="triggerDelete('unite', uniteForm)"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <Button label="Annuler" text severity="secondary" @click="uniteDialogVisible = false" />
                        <Button
                            :label="uniteEditMode ? 'Enregistrer les modifications' : 'Créer l\'unité'"
                            icon="pi pi-check"
                            class="bg-sky-700 hover:bg-sky-800 text-white border-0 text-xs"
                            :loading="savingUnite"
                            @click="saveUnite"
                        />
                    </div>
                </div>
            </template>
        </Dialog>

        <!-- MODAL POSITION -->
        <Dialog
            v-model:visible="positionDialogVisible"
            :header="positionEditMode ? 'Modifier la Position / Emplacement' : 'Ajouter une Position / Emplacement (Où il se trouve)'"
            :modal="true"
            class="w-full max-w-md"
        >
            <div class="space-y-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Nom de la Position / Emplacement <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        v-model="positionForm.nom"
                        placeholder="Ex: Escadron Djiguiya, GIGM, Koulouba, Camp 1..."
                        class="w-full text-xs"
                    />
                    <small class="text-[10px] text-slate-500">L'endroit, le poste ou le théâtre opérationnel où le personnel se trouve.</small>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Type de Déploiement</label>
                    <Select
                        v-model="positionForm.type"
                        :options="[
                            { label: 'Théâtre Opérationnel / Mission (Escadron, Force...)', value: 'operationnelle' },
                            { label: 'Poste / Emplacement Fixe (Base, Présidence, Camp...)', value: 'poste' }
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full text-xs"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Description / Précisions</label>
                    <InputText
                        v-model="positionForm.description"
                        placeholder="Ex: Zone géographique, camp ou base d'affectation..."
                        class="w-full text-xs"
                    />
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-between w-full">
                    <div>
                        <Button
                            v-if="positionEditMode"
                            label="Supprimer cette position"
                            icon="pi pi-trash"
                            severity="danger"
                            text
                            class="!text-xs text-red-600 hover:bg-red-50"
                            @click="triggerDelete('position', positionForm)"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <Button label="Annuler" text severity="secondary" @click="positionDialogVisible = false" />
                        <Button
                            :label="positionEditMode ? 'Enregistrer' : 'Créer'"
                            icon="pi pi-check"
                            class="bg-sky-700 hover:bg-sky-800 text-white border-0 text-xs"
                            :loading="savingPosition"
                            @click="savePosition"
                        />
                    </div>
                </div>
            </template>
        </Dialog>

        <!-- DIALOG DE CONFIRMATION DE SUPPRESSION CENTRALISÉ -->
        <Dialog
            v-model:visible="deleteDialogVisible"
            header="Confirmation de suppression"
            :modal="true"
            class="w-full max-w-md"
        >
            <div class="flex items-start gap-3 pt-2">
                <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0 text-lg">
                    <i class="pi pi-exclamation-triangle"></i>
                </div>
                <div class="space-y-1">
                    <p class="text-sm font-semibold text-slate-900">
                        {{ deleteDialogTitle }}
                    </p>
                    <p class="text-xs text-slate-600">
                        {{ deleteDialogMessage }}
                    </p>
                    <div v-if="deleteWarningCascade" class="text-xs font-medium text-red-700 bg-red-50 p-2.5 rounded-lg border border-red-200 mt-2 flex items-start gap-2">
                        <i class="pi pi-shield text-red-600 mt-0.5 shrink-0"></i>
                        <span>Attention : La suppression de cette région entraînera la suppression en cascade de toutes les unités qui lui sont rattachées.</span>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end gap-2 w-full">
                    <Button label="Annuler" text severity="secondary" @click="deleteDialogVisible = false" />
                    <Button
                        label="Confirmer la suppression"
                        icon="pi pi-trash"
                        severity="danger"
                        class="bg-red-600 hover:bg-red-700 text-white border-0 text-xs font-semibold"
                        :loading="deletingItem"
                        @click="executeDelete"
                    />
                </div>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    regions: { type: Array, default: () => [] },
    unites: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const toast = useToast();
const activeTab = ref('unites');

// ==========================================
// FILTRES ET RECHERCHE
// ==========================================
const searchUnite = ref('');
const filterRegion = ref(null);
const filterOperationnel = ref(null);

const searchRegion = ref('');
const searchPosition = ref('');

const regionOptionsWithAll = computed(() => [
    { label: 'Toutes les régions', value: null },
    ...props.regions.map(r => ({ label: `[${r.code}] ${r.nom}`, value: r.id }))
]);

const operationnelOptions = [
    { label: 'Tous les statuts', value: null },
    { label: 'Opérationnelles (Actives)', value: true },
    { label: 'Non-opérationnelles (En projet)', value: false },
];

const typeUniteOptions = [
    'Brigade Territoriale',
    'Brigade de Recherches',
    'Brigade des Transports Aériens',
    'Brigade Fluviale',
    'Brigade de Sécurité Routière',
    'Escadron',
    'Peloton PSIG',
    'État-Major',
    'Groupement',
    'Compagnie',
    'Direction / Service',
    'École / Formation',
    'Détachement / Ministère',
    'Autre',
];

// Filtrage Unités
const filteredUnites = computed(() => {
    return props.unites.filter(u => {
        const matchesSearch = !searchUnite.value ||
            u.nom.toLowerCase().includes(searchUnite.value.toLowerCase()) ||
            (u.type && u.type.toLowerCase().includes(searchUnite.value.toLowerCase()));

        const matchesRegion = filterRegion.value === null || filterRegion.value === undefined ||
            u.region_id === filterRegion.value;

        const matchesOp = filterOperationnel.value === null || filterOperationnel.value === undefined ||
            u.est_operationnelle === filterOperationnel.value;

        return matchesSearch && matchesRegion && matchesOp;
    });
});

// Pagination Unités
const unitesPerPage = 25;
const unitesCurrentPage = ref(1);
const unitesTotalPages = computed(() => Math.ceil(filteredUnites.value.length / unitesPerPage));
const unitesStartIndex = computed(() => (unitesCurrentPage.value - 1) * unitesPerPage);
const unitesEndIndex = computed(() => unitesStartIndex.value + unitesPerPage);
const paginatedUnites = computed(() => filteredUnites.value.slice(unitesStartIndex.value, unitesEndIndex.value));

// Filtrage Régions
const filteredRegions = computed(() => {
    if (!searchRegion.value) return props.regions;
    const s = searchRegion.value.toLowerCase();
    return props.regions.filter(r =>
        r.code.toLowerCase().includes(s) ||
        r.nom.toLowerCase().includes(s) ||
        (r.description && r.description.toLowerCase().includes(s))
    );
});

// Filtrage Positions
const filteredPositions = computed(() => {
    if (!searchPosition.value) return props.positions;
    const s = searchPosition.value.toLowerCase();
    return props.positions.filter(p =>
        p.nom.toLowerCase().includes(s) ||
        (p.type && p.type.toLowerCase().includes(s)) ||
        (p.description && p.description.toLowerCase().includes(s))
    );
});

// ==========================================
// SUPPRESSION CENTRALISÉE AVEC MODAL DE CONFIRMATION
// ==========================================
const deleteDialogVisible = ref(false);
const deletingItem = ref(false);
const deleteType = ref(null); // 'unite', 'region', 'position'
const deleteItem = ref(null);
const deleteDialogTitle = ref('');
const deleteDialogMessage = ref('');
const deleteWarningCascade = ref(false);

const triggerDelete = (type, item) => {
    deleteType.value = type;
    deleteItem.value = item;
    if (type === 'unite') {
        deleteDialogTitle.value = `Supprimer l'unité "${item.nom}" ?`;
        deleteDialogMessage.value = `Êtes-vous sûr de vouloir supprimer définitivement cette unité du maillage territorial ? Les militaires qui y étaient rattachés ne seront plus affectés à cette unité.`;
        deleteWarningCascade.value = false;
    } else if (type === 'region') {
        deleteDialogTitle.value = `Supprimer la région "${item.nom}" ?`;
        deleteDialogMessage.value = `Êtes-vous sûr de vouloir supprimer cette région ?`;
        deleteWarningCascade.value = true;
    } else if (type === 'position') {
        deleteDialogTitle.value = `Supprimer la position "${item.nom}" ?`;
        deleteDialogMessage.value = `Êtes-vous sûr de vouloir supprimer cette position / emplacement ?`;
        deleteWarningCascade.value = false;
    }
    deleteDialogVisible.value = true;
};

const executeDelete = () => {
    if (!deleteItem.value || !deleteType.value) return;

    deletingItem.value = true;
    let url = '';
    if (deleteType.value === 'unite') {
        url = route('configurations.unites.destroy', deleteItem.value.id);
    } else if (deleteType.value === 'region') {
        url = route('configurations.regions.destroy', deleteItem.value.id);
    } else if (deleteType.value === 'position') {
        url = route('configurations.positions.destroy', deleteItem.value.id);
    }

    router.delete(url, {
        onSuccess: () => {
            deleteDialogVisible.value = false;
            uniteDialogVisible.value = false;
            regionDialogVisible.value = false;
            positionDialogVisible.value = false;
            toast.add({
                severity: 'success',
                summary: 'Suppression réussie',
                detail: 'L\'élément a été supprimé avec succès.',
                life: 3000
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Erreur',
                detail: 'Impossible de supprimer cet élément.',
                life: 4000
            });
        },
        onFinish: () => {
            deletingItem.value = false;
        }
    });
};

const confirmDeleteUnite = (u) => triggerDelete('unite', u);
const confirmDeleteRegion = (r) => triggerDelete('region', r);
const confirmDeletePosition = (p) => triggerDelete('position', p);

// ==========================================
// GESTION RÉGIONS (MODAL & ACTIONS)
// ==========================================
const regionDialogVisible = ref(false);
const regionEditMode = ref(false);
const savingRegion = ref(false);
const regionForm = ref({
    id: null,
    code: '',
    nom: '',
    description: '',
    chef_lieu: '',
    latitude: null,
    longitude: null,
});

const openCreateRegionDialog = () => {
    regionEditMode.value = false;
    regionForm.value = {
        id: null,
        code: '',
        nom: '',
        description: '',
        chef_lieu: '',
        latitude: null,
        longitude: null,
    };
    regionDialogVisible.value = true;
};

const editRegion = (r) => {
    regionEditMode.value = true;
    regionForm.value = {
        id: r.id,
        code: r.code,
        nom: r.nom,
        description: r.description || '',
        chef_lieu: r.chef_lieu || '',
        latitude: r.latitude !== null && r.latitude !== undefined ? r.latitude : null,
        longitude: r.longitude !== null && r.longitude !== undefined ? r.longitude : null,
    };
    regionDialogVisible.value = true;
};

const saveRegion = () => {
    if (!regionForm.value.code || !regionForm.value.nom) {
        toast.add({ severity: 'warn', summary: 'Champs obligatoires', detail: 'Code et nom sont requis.', life: 3000 });
        return;
    }

    savingRegion.value = true;
    if (regionEditMode.value) {
        router.put(route('configurations.regions.update', regionForm.value.id), regionForm.value, {
            onSuccess: () => {
                regionDialogVisible.value = false;
                toast.add({ severity: 'success', summary: 'Succès', detail: 'Région modifiée avec succès.', life: 3000 });
            },
            onFinish: () => savingRegion.value = false,
        });
    } else {
        router.post(route('configurations.regions.store'), regionForm.value, {
            onSuccess: () => {
                regionDialogVisible.value = false;
                toast.add({ severity: 'success', summary: 'Succès', detail: 'Région ajoutée avec succès.', life: 3000 });
            },
            onFinish: () => savingRegion.value = false,
        });
    }
};

// ==========================================
// GESTION UNITÉS (MODAL & ACTIONS)
// ==========================================
const uniteDialogVisible = ref(false);
const uniteEditMode = ref(false);
const savingUnite = ref(false);
const uniteForm = ref({
    id: null,
    region_id: null,
    nom: '',
    code: '',
    type: 'Brigade Territoriale',
    ville: '',
    latitude: null,
    longitude: null,
    est_operationnelle: true,
});

const openCreateUniteDialog = () => {
    uniteEditMode.value = false;
    uniteForm.value = {
        id: null,
        region_id: filterRegion.value || (props.regions[0] ? props.regions[0].id : null),
        nom: '',
        code: '',
        type: 'Brigade Territoriale',
        ville: '',
        latitude: null,
        longitude: null,
        est_operationnelle: true,
    };
    uniteDialogVisible.value = true;
};

const editUnite = (u) => {
    uniteEditMode.value = true;
    uniteForm.value = {
        id: u.id,
        region_id: u.region_id,
        nom: u.nom,
        code: u.code || '',
        type: u.type || 'Autre',
        ville: u.ville || '',
        latitude: u.latitude !== null && u.latitude !== undefined ? u.latitude : null,
        longitude: u.longitude !== null && u.longitude !== undefined ? u.longitude : null,
        est_operationnelle: !!u.est_operationnelle,
    };
    uniteDialogVisible.value = true;
};

const saveUnite = () => {
    if (!uniteForm.value.region_id || !uniteForm.value.nom) {
        toast.add({ severity: 'warn', summary: 'Champs obligatoires', detail: 'Veuillez sélectionner la région et renseigner le nom.', life: 3000 });
        return;
    }

    savingUnite.value = true;
    if (uniteEditMode.value) {
        router.put(route('configurations.unites.update', uniteForm.value.id), uniteForm.value, {
            onSuccess: () => {
                uniteDialogVisible.value = false;
                toast.add({ severity: 'success', summary: 'Succès', detail: 'Unité modifiée avec succès.', life: 3000 });
            },
            onFinish: () => savingUnite.value = false,
        });
    } else {
        router.post(route('configurations.unites.store'), uniteForm.value, {
            onSuccess: () => {
                uniteDialogVisible.value = false;
                toast.add({ severity: 'success', summary: 'Succès', detail: 'Unité enregistrée avec succès.', life: 3000 });
            },
            onFinish: () => savingUnite.value = false,
        });
    }
};

// ==========================================
// GESTION POSITIONS (MODAL & ACTIONS)
// ==========================================
const positionDialogVisible = ref(false);
const positionEditMode = ref(false);
const savingPosition = ref(false);
const positionForm = ref({
    id: null,
    nom: '',
    type: 'administrative',
    description: '',
});

const openCreatePositionDialog = () => {
    positionEditMode.value = false;
    positionForm.value = { id: null, nom: '', type: 'administrative', description: '' };
    positionDialogVisible.value = true;
};

const editPosition = (p) => {
    positionEditMode.value = true;
    positionForm.value = { id: p.id, nom: p.nom, type: p.type || 'administrative', description: p.description || '' };
    positionDialogVisible.value = true;
};

const savePosition = () => {
    if (!positionForm.value.nom) {
        toast.add({ severity: 'warn', summary: 'Champ obligatoire', detail: 'Le libellé de la position est requis.', life: 3000 });
        return;
    }

    savingPosition.value = true;
    if (positionEditMode.value) {
        router.put(route('configurations.positions.update', positionForm.value.id), positionForm.value, {
            onSuccess: () => {
                positionDialogVisible.value = false;
                toast.add({ severity: 'success', summary: 'Succès', detail: 'Position modifiée avec succès.', life: 3000 });
            },
            onFinish: () => savingPosition.value = false,
        });
    } else {
        router.post(route('configurations.positions.store'), positionForm.value, {
            onSuccess: () => {
                positionDialogVisible.value = false;
                toast.add({ severity: 'success', summary: 'Succès', detail: 'Position enregistrée avec succès.', life: 3000 });
            },
            onFinish: () => savingPosition.value = false,
        });
    }
};
</script>
