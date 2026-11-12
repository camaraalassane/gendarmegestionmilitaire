<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="font-extrabold text-xl text-white flex items-center gap-2.5">
                        <i class="pi pi-user-plus text-amber-300"></i>
                        <span>Enrôlement Militaire - Gendarmerie Nationale</span>
                    </h2>
                    <p class="text-xs text-sky-100 mt-0.5">Formulaire officiel d'incorporation et de gestion administrative des effectifs</p>
                </div>
                <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-xs px-3 py-1.5 rounded-lg border border-white/20 text-white text-xs font-semibold self-start sm:self-auto">
                    <i class="pi pi-shield text-amber-300"></i>
                    <span>Division des Ressources Humaines</span>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50 min-h-[calc(100vh-140px)]">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- ========================================================================================= -->
                <!-- BANDEAU DE PROGRESSION / STEPPER MULTI-ÉTAPES (3 ÉTAPES)                                  -->
                <!-- ========================================================================================= -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-6 mb-6">
                    <!-- Barre de progression continue -->
                    <div class="flex items-center justify-between text-xs font-bold text-slate-600 mb-2">
                        <span class="text-sky-900 font-extrabold uppercase tracking-wider flex items-center gap-1.5">
                            <span class="inline-block w-2 h-2 rounded-full bg-sky-600 animate-ping"></span>
                            Étape {{ currentStep + 1 }} sur {{ steps.length }} : {{ steps[currentStep].label }}
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full bg-sky-100 text-sky-800 border border-sky-200 font-extrabold text-[11px]">
                            {{ Math.round(progress) }}% complété
                        </span>
                    </div>

                    <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden mb-6 shadow-inner relative">
                        <div class="h-full bg-gradient-to-r from-sky-800 via-sky-600 to-amber-500 rounded-full transition-all duration-500 ease-out"
                             :style="{ width: `${progress}%` }"></div>
                    </div>

                    <!-- Onglets interactifs des étapes (3 étapes au total) -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <button v-for="(s, idx) in steps" :key="idx"
                                type="button"
                                @click="goToStep(idx)"
                                :disabled="idx > maxStepVisited"
                                :class="[
                                    'group flex items-center gap-3 p-3 rounded-xl border transition-all text-left',
                                    idx === currentStep
                                        ? 'bg-sky-50 border-sky-500 shadow-xs ring-2 ring-sky-200 cursor-default'
                                        : idx < currentStep
                                            ? 'bg-emerald-50/70 border-emerald-300 hover:bg-emerald-100/60 cursor-pointer'
                                            : idx <= maxStepVisited
                                                ? 'bg-slate-50 border-slate-300 hover:bg-slate-100 cursor-pointer'
                                                : 'bg-slate-50/40 border-slate-200 opacity-50 cursor-not-allowed'
                                ]">
                            <!-- Pastille numéro / icône -->
                            <div :class="[
                                'w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs shrink-0 transition-all shadow-2xs',
                                idx === currentStep
                                    ? 'bg-sky-800 text-white shadow-sky-200'
                                    : idx < currentStep
                                        ? 'bg-emerald-600 text-white'
                                        : 'bg-white border border-slate-300 text-slate-600'
                            ]">
                                <i v-if="idx < currentStep" class="pi pi-check text-xs font-bold"></i>
                                <i v-else :class="s.icon" class="text-sm"></i>
                            </div>

                            <div class="min-w-0 flex-1">
                                <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                                    Étape {{ idx + 1 }}
                                </span>
                                <span :class="[
                                    'block text-xs font-bold truncate',
                                    idx === currentStep ? 'text-sky-950' : idx < currentStep ? 'text-emerald-900' : 'text-slate-700'
                                ]">
                                    {{ s.label }}
                                </span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- ========================================================================================= -->
                <!-- CONTENEUR DU FORMULAIRE DE L'ÉTAPE ACTIVE                                                 -->
                <!-- ========================================================================================= -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <!-- En-tête de section -->
                    <div class="px-6 py-4 bg-gradient-to-r from-sky-900 via-sky-800 to-sky-900 text-white flex items-center justify-between border-b border-sky-950/20">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center text-white text-lg shadow-inner">
                                <i :class="steps[currentStep].icon"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-white tracking-wide">{{ steps[currentStep].title }}</h3>
                                <p class="text-xs text-sky-200">{{ steps[currentStep].subtitle }}</p>
                            </div>
                        </div>
                        <span class="text-xs font-extrabold bg-white/20 px-3 py-1 rounded-full text-white">
                            {{ currentStep + 1 }} / {{ steps.length }}
                        </span>
                    </div>

                    <!-- Bannière de résumé d'erreurs serveur -->
                    <div v-if="errors && Object.keys(errors).length > 0"
                         class="m-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-xl shadow-xs">
                        <div class="flex items-center gap-2 mb-2 text-red-700 font-bold text-sm">
                            <i class="pi pi-exclamation-triangle text-base"></i>
                            <span>Des erreurs ont été relevées lors de la vérification :</span>
                        </div>
                        <ul class="list-disc list-inside text-xs text-red-600 space-y-1">
                            <li v-for="(err, field) in errors" :key="field">
                                <strong class="capitalize">{{ field }}</strong> : {{ Array.isArray(err) ? err.join(', ') : err }}
                            </li>
                        </ul>
                    </div>

                    <form @submit.prevent="submitForm" enctype="multipart/form-data" class="p-6">

                        <!-- ================================================================================= -->
                        <!-- ÉTAPE 0 : IDENTITÉ & ÉTAT CIVIL (AVEC 2 CARDS DISTINCTES)                         -->
                        <!-- ================================================================================= -->
                        <div v-show="currentStep === 0" class="space-y-6">

                            <!-- CARD 1 : Photo & Identifiants Militaires -->
                            <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 shadow-2xs">
                                <div class="flex items-center gap-2 text-sky-900 border-b border-slate-200 pb-3 mb-4 text-sm font-extrabold uppercase tracking-wide">
                                    <i class="pi pi-id-card text-sky-800 text-base"></i>
                                    <span>Card 1 — Photo & Identifiants Militaires</span>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                                    <!-- Photo d'identité -->
                                    <div class="md:col-span-3 flex flex-col items-center justify-center p-4 border rounded-xl bg-white text-center shadow-xs">
                                        <div class="w-36 h-44 bg-slate-50 border-2 border-dashed border-sky-300 rounded-lg overflow-hidden flex items-center justify-center mb-3 shadow-inner relative group">
                                            <img v-if="photoPreview" :src="photoPreview" alt="Photo d'identité" class="w-full h-full object-cover" />
                                            <div v-else class="flex flex-col items-center text-slate-400 p-2">
                                                <i class="pi pi-user text-5xl mb-1 text-slate-300"></i>
                                                <span class="text-xs font-semibold text-slate-500">Photo d'identité</span>
                                                <span class="text-[10px] text-slate-400">Format 3:4</span>
                                            </div>
                                        </div>

                                        <div class="flex gap-2">
                                            <label class="cursor-pointer px-3 py-1.5 bg-sky-900 hover:bg-sky-950 text-white rounded-lg text-xs font-bold flex items-center gap-1.5 shadow-xs transition">
                                                <i class="pi pi-camera"></i>
                                                <span>{{ photoPreview ? 'Changer' : 'Ajouter photo' }}</span>
                                                <input type="file" accept="image/jpeg,image/png,image/jpg,image/webp" class="hidden" @change="onPhotoSelected" />
                                            </label>
                                            <button v-if="photoPreview" type="button" @click="removePhoto"
                                                    class="px-2.5 py-1.5 bg-red-100 hover:bg-red-200 text-red-600 rounded-lg text-xs transition cursor-pointer" title="Supprimer la photo">
                                                <i class="pi pi-trash"></i>
                                            </button>
                                        </div>
                                        <span class="text-[10px] text-slate-500 mt-2">JPG, PNG, WEBP (Max 5Mo)</span>
                                        <small v-if="errors.photo" class="text-red-500 text-xs mt-1">{{ errors.photo }}</small>
                                    </div>

                                    <!-- Champs identifiants -->
                                    <div class="md:col-span-9 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                        <!-- Matricule Armée -->
                                        <div class="field">
                                            <label for="matricule" class="block text-sm font-bold text-gray-700 mb-1">
                                                Matricule Armée <span class="text-red-500">*</span>
                                            </label>
                                            <InputText id="matricule" v-model="form.matricule" class="w-full" placeholder="Ex: 16276"
                                                       :class="{ 'p-invalid': errors.matricule || stepErrors[0].matricule }" />
                                            <small v-if="errors.matricule || stepErrors[0].matricule" class="text-red-500 font-medium">
                                                {{ errors.matricule || stepErrors[0].matricule }}
                                            </small>
                                        </div>

                                        <!-- MLE GRM -->
                                        <div class="field">
                                            <label for="mle_grm" class="block text-sm font-semibold text-gray-700 mb-1">
                                                Matricule Gendarmerie (GRM MLE)
                                            </label>
                                            <InputText id="mle_grm" v-model="form.mle_grm" class="w-full" placeholder="Ex: GRM9272M006871" />
                                        </div>

                                        <!-- N° Info -->
                                        <div class="field">
                                            <label for="numero_info" class="block text-sm font-semibold text-gray-700 mb-1">
                                                N° Informatique / Solde
                                            </label>
                                            <InputText id="numero_info" v-model="form.numero_info" class="w-full" placeholder="Ex: 1668" />
                                        </div>

                                        <!-- N° Nina -->
                                        <div class="field">
                                            <label for="numero_nina" class="block text-sm font-semibold text-gray-700 mb-1">
                                                N° NINA / Identification
                                            </label>
                                            <InputText id="numero_nina" v-model="form.numero_nina" class="w-full" placeholder="Numéro national d'identification" />
                                        </div>

                                        <!-- Grade actuel -->
                                        <div class="field">
                                            <label for="grade_actuel" class="block text-sm font-bold text-gray-700 mb-1">
                                                Grade actuel <span class="text-red-500">*</span>
                                            </label>
                                            <Select v-model="form.grade_actuel" :options="grades" optionLabel="nom_grade" optionValue="nom_grade"
                                                    placeholder="Sélectionner un grade" class="w-full"
                                                    :class="{ 'p-invalid': errors.grade_actuel || stepErrors[0].grade_actuel }" />
                                            <small v-if="errors.grade_actuel || stepErrors[0].grade_actuel" class="text-red-500 font-medium">
                                                {{ errors.grade_actuel || stepErrors[0].grade_actuel }}
                                            </small>
                                        </div>

                                        <!-- Catégorie statutaire -->
                                        <div class="field">
                                            <label for="categorie" class="block text-sm font-semibold text-gray-700 mb-1">
                                                Catégorie statutaire
                                            </label>
                                            <Select v-model="form.categorie" :options="categorieOptions" optionLabel="label" optionValue="value"
                                                    placeholder="Sélectionner la catégorie" class="w-full" showClear />
                                            <small class="text-[11px] text-sky-600 block mt-1">
                                                <i class="pi pi-info-circle mr-1"></i>Renseignée automatiquement selon le grade.
                                            </small>
                                        </div>

                                        <!-- Statut administratif -->
                                        <div class="field sm:col-span-2 lg:col-span-3">
                                            <label for="statut" class="block text-sm font-bold text-gray-700 mb-1">
                                                Statut Administratif <span class="text-red-500">*</span>
                                            </label>
                                            <Select v-model="form.statut" :options="statutOptions" optionLabel="label" optionValue="value"
                                                    placeholder="Sélectionner un statut" class="w-full max-w-md"
                                                    :class="{ 'p-invalid': errors.statut || stepErrors[0].statut }" />
                                            <small v-if="errors.statut || stepErrors[0].statut" class="text-red-500 font-medium">
                                                {{ errors.statut || stepErrors[0].statut }}
                                            </small>

                                            <div v-if="isRetraiteDepassee" class="mt-2.5 p-3 bg-amber-50 border border-amber-300 rounded-xl text-xs text-amber-900 flex items-center gap-2">
                                                <i class="pi pi-exclamation-triangle text-amber-600 text-base shrink-0"></i>
                                                <span>Limite d'âge de retraite atteinte/dépassée ({{ ageRetraiteMax }} ans pour ce grade). Le statut a été positionné automatiquement sur <strong>Retraité</strong>.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Champs conditionnels selon le statut -->
                                <div v-if="form.statut === 'décédé'" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 p-4 bg-red-50 border border-red-200 rounded-xl">
                                    <div>
                                        <label class="block text-sm font-bold text-red-800 mb-1">Date du décès</label>
                                        <DatePicker v-model="form.date_deces" dateFormat="dd/mm/yy" showIcon class="w-full" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-red-800 mb-1">Circonstances du décès</label>
                                        <InputText v-model="form.circonstances_deces" class="w-full" placeholder="Ex: Théâtre d'opération, Attaque, Maladie..." />
                                    </div>
                                </div>

                                <div v-if="form.statut === 'déserteur'" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 p-4 bg-orange-50 border border-orange-200 rounded-xl">
                                    <div>
                                        <label class="block text-sm font-bold text-orange-800 mb-1">Motif de radiation</label>
                                        <InputText v-model="form.motif_radiation" class="w-full" placeholder="Ex: Désertion, Retrait d'emploi..." />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-orange-800 mb-1">Référence décision ministérielle</label>
                                        <InputText v-model="form.reference_decision" class="w-full" placeholder="Ex: N° Décret / Arrêté ministériel..." />
                                    </div>
                                </div>
                            </div>

                            <!-- CARD 2 : État Civil & Coordonnées -->
                            <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 shadow-2xs">
                                <div class="flex items-center gap-2 text-sky-900 border-b border-slate-200 pb-3 mb-4 text-sm font-extrabold uppercase tracking-wide">
                                    <i class="pi pi-user text-sky-800 text-base"></i>
                                    <span>Card 2 — État Civil & Coordonnées Personnelles</span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Nom -->
                                    <div class="field">
                                        <label for="nom" class="block text-sm font-bold text-gray-700 mb-1">
                                            Nom de famille <span class="text-red-500">*</span>
                                        </label>
                                        <InputText id="nom" v-model="form.nom" class="w-full uppercase" placeholder="Ex: TRAORÉ"
                                                   :class="{ 'p-invalid': errors.nom || stepErrors[0].nom }" />
                                        <small v-if="errors.nom || stepErrors[0].nom" class="text-red-500 font-medium">
                                            {{ errors.nom || stepErrors[0].nom }}
                                        </small>
                                    </div>

                                    <!-- Prénom -->
                                    <div class="field">
                                        <label for="prenom" class="block text-sm font-bold text-gray-700 mb-1">
                                            Prénom(s) <span class="text-red-500">*</span>
                                        </label>
                                        <InputText id="prenom" v-model="form.prenom" class="w-full" placeholder="Ex: Amadou"
                                                   :class="{ 'p-invalid': errors.prenom || stepErrors[0].prenom }" />
                                        <small v-if="errors.prenom || stepErrors[0].prenom" class="text-red-500 font-medium">
                                            {{ errors.prenom || stepErrors[0].prenom }}
                                        </small>
                                    </div>

                                    <!-- Date de naissance -->
                                    <div class="field">
                                        <label for="date_naissance" class="block text-sm font-bold text-gray-700 mb-1">
                                            Date de naissance <span class="text-red-500">*</span>
                                        </label>
                                        <DatePicker id="date_naissance" v-model="form.date_naissance" dateFormat="dd/mm/yy" showIcon class="w-full"
                                                    :class="{ 'p-invalid': errors.date_naissance || stepErrors[0].date_naissance }" />
                                        <small v-if="errors.date_naissance || stepErrors[0].date_naissance" class="text-red-500 font-medium">
                                            {{ errors.date_naissance || stepErrors[0].date_naissance }}
                                        </small>
                                    </div>

                                    <!-- Lieu de naissance -->
                                    <div class="field">
                                        <label for="lieu_naissance" class="block text-sm font-semibold text-gray-700 mb-1">
                                            Lieu de naissance
                                        </label>
                                        <InputText id="lieu_naissance" v-model="form.lieu_naissance" class="w-full" placeholder="Ex: Bamako, Ségou, Sikasso..." />
                                    </div>

                                    <!-- Sexe -->
                                    <div class="field">
                                        <label for="sexe" class="block text-sm font-semibold text-gray-700 mb-1">
                                            Sexe
                                        </label>
                                        <Select v-model="form.sexe" :options="sexeOptions" optionLabel="label" optionValue="value"
                                                placeholder="Sélectionner" class="w-full" showClear />
                                    </div>

                                    <!-- Téléphone principal -->
                                    <div class="field">
                                        <label for="telephone" class="block text-sm font-semibold text-gray-700 mb-1">
                                            Téléphone du militaire
                                        </label>
                                        <InputText id="telephone" v-model="form.telephone" class="w-full" placeholder="Ex: +223 70 00 00 00" />
                                    </div>

                                    <!-- Personne à contacter -->
                                    <div class="field sm:col-span-2">
                                        <label for="personne_a_contacter" class="block text-sm font-semibold text-gray-700 mb-1">
                                            Personne à contacter en cas d'urgence
                                        </label>
                                        <InputText id="personne_a_contacter" v-model="form.personne_a_contacter" class="w-full" placeholder="Nom et prénom de la personne de confiance" />
                                    </div>

                                    <!-- Téléphone contact urgence -->
                                    <div class="field">
                                        <label for="telephone_personne_contacter" class="block text-sm font-semibold text-gray-700 mb-1">
                                            Téléphone d'urgence
                                        </label>
                                        <InputText id="telephone_personne_contacter" v-model="form.telephone_personne_contacter" class="w-full" placeholder="Ex: +223 66 00 00 00" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ================================================================================= -->
                        <!-- ÉTAPE 1 : AFFECTATION & CARRIÈRE                                                 -->
                        <!-- ================================================================================= -->
                        <div v-show="currentStep === 1" class="space-y-6">
                            <!-- Affectations territoriales -->
                            <div>
                                <div class="flex items-center justify-between mb-3 border-b border-slate-200 pb-2">
                                    <label class="block text-sm font-bold text-slate-800">
                                        <i class="pi pi-building text-sky-800 mr-1.5"></i>
                                        Affectation & Maillage Territorial
                                    </label>
                                    <Button type="button" label="Ajouter une affectation" icon="pi pi-plus"
                                            class="p-button-sm !bg-white hover:!bg-sky-50 !text-sky-800 !border !border-sky-300 font-bold shadow-2xs cursor-pointer"
                                            @click="addAffectation" />
                                </div>

                                <div class="space-y-3">
                                    <div v-for="(aff, idx) in form.affectations" :key="idx"
                                         class="p-4 rounded-xl border border-slate-200 bg-slate-50/70 relative transition-all">
                                        <div class="flex items-center justify-between mb-3 border-b border-slate-200 pb-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-sky-800 text-white font-extrabold text-xs">
                                                    {{ idx + 1 }}
                                                </span>
                                                <span class="font-bold text-xs text-slate-800">
                                                    Affectation {{ idx === 0 ? '(Actuelle / Plus récente)' : 'n°' + (idx + 1) }}
                                                </span>
                                            </div>
                                            <Button v-if="form.affectations.length > 1" type="button" icon="pi pi-trash"
                                                    class="p-button-rounded p-button-text p-button-sm !text-red-500 hover:!text-red-700 hover:!bg-red-50 cursor-pointer"
                                                    title="Supprimer cette affectation"
                                                    @click="removeAffectation(idx)" />
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                            <!-- Région de Gendarmerie -->
                                            <div class="field">
                                                <div class="flex items-center justify-between mb-1">
                                                    <label :for="'region_' + idx" class="block text-xs font-semibold text-gray-700">
                                                        Région de Gendarmerie
                                                    </label>
                                                    <button type="button" @click="openQuickAddRegion(idx)"
                                                            class="text-sky-700 hover:text-sky-900 text-[11px] font-semibold flex items-center gap-1 cursor-pointer bg-sky-50 px-1.5 py-0.5 rounded border border-sky-200 hover:bg-sky-100 transition-colors"
                                                            title="Ajouter une nouvelle région">
                                                        <i class="pi pi-plus text-[9px]"></i>
                                                        <span>Ajouter</span>
                                                    </button>
                                                </div>
                                                <Select :id="'region_' + idx"
                                                        v-model="aff.region"
                                                        :options="localRegions"
                                                        optionLabel="nom"
                                                        optionValue="nom"
                                                        filter
                                                        placeholder="Choisir ou chercher une région..."
                                                        class="w-full text-xs"
                                                        @change="onRegionSelect(idx)">
                                                    <template #option="slotProps">
                                                        <div class="flex items-center gap-2">
                                                            <span class="font-mono text-[10px] font-bold text-sky-800">[{{ slotProps.option.code }}]</span>
                                                            <span class="text-xs">{{ slotProps.option.nom }}</span>
                                                        </div>
                                                    </template>
                                                </Select>
                                            </div>

                                            <!-- Unité / Brigade / Escadron -->
                                            <div class="field">
                                                <div class="flex items-center justify-between mb-1">
                                                    <label :for="'unite_' + idx" class="block text-xs font-semibold text-gray-700">
                                                        Unité / Brigade / Escadron
                                                    </label>
                                                    <button type="button" @click="openQuickAddUnite(idx)"
                                                            class="text-sky-700 hover:text-sky-900 text-[11px] font-semibold flex items-center gap-1 cursor-pointer bg-sky-50 px-1.5 py-0.5 rounded border border-sky-200 hover:bg-sky-100 transition-colors"
                                                            title="Ajouter une nouvelle unité">
                                                        <i class="pi pi-plus text-[9px]"></i>
                                                        <span>Ajouter</span>
                                                    </button>
                                                </div>
                                                <Select :id="'unite_' + idx"
                                                        v-model="aff.unite"
                                                        :options="getUnitesForRow(idx)"
                                                        optionLabel="nom"
                                                        optionValue="nom"
                                                        filter
                                                        placeholder="Choisir ou chercher une unité..."
                                                        class="w-full text-xs"
                                                        @change="onUniteSelect(idx)">
                                                    <template #option="slotProps">
                                                        <div class="flex flex-col py-0.5">
                                                            <span class="text-xs font-medium text-slate-900">{{ slotProps.option.nom }}</span>
                                                            <span class="text-[10px] text-slate-500">
                                                                {{ slotProps.option.region?.nom ? '[' + slotProps.option.region.nom + ']' : '' }}
                                                                {{ slotProps.option.type ? '• ' + slotProps.option.type : '' }}
                                                            </span>
                                                        </div>
                                                    </template>
                                                </Select>
                                            </div>

                                            <!-- Date Mutation -->
                                            <div class="field">
                                                <label :for="'date_mutation_' + idx" class="block text-xs font-semibold text-gray-700 mb-1">
                                                    Date de mutation
                                                </label>
                                                <DatePicker :id="'date_mutation_' + idx" v-model="aff.date_mutation"
                                                            dateFormat="dd/mm/yy" showIcon class="w-full" size="small" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Dates et fonctions de carrière -->
                            <div class="pt-4 border-t border-slate-200">
                                <label class="block text-sm font-bold text-slate-800 mb-3">
                                    <i class="pi pi-briefcase text-sky-800 mr-1.5"></i>
                                    Dates clés et Fonctions de Carrière
                                </label>

                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Date d'entrée en service -->
                                    <div class="field">
                                        <label for="date_entree_service" class="block text-sm font-bold text-gray-700 mb-1">
                                            Date d'entrée en service <span class="text-red-500">*</span>
                                        </label>
                                        <DatePicker id="date_entree_service" v-model="form.date_entree_service" dateFormat="dd/mm/yy" showIcon class="w-full"
                                                    :class="{ 'p-invalid': errors.date_entree_service || stepErrors[1].date_entree_service }" />
                                        <small v-if="errors.date_entree_service || stepErrors[1].date_entree_service" class="text-red-500 font-medium">
                                            {{ errors.date_entree_service || stepErrors[1].date_entree_service }}
                                        </small>
                                    </div>

                                    <!-- Date dernière promotion -->
                                    <div class="field">
                                        <label for="date_derniere_promotion" class="block text-sm font-semibold text-gray-700 mb-1">
                                            Date dernière promotion
                                        </label>
                                        <DatePicker id="date_derniere_promotion" v-model="form.date_derniere_promotion" dateFormat="dd/mm/yy" showIcon class="w-full" />
                                    </div>

                                    <!-- Origine de formation -->
                                    <div class="field">
                                        <label for="origine_formation" class="block text-sm font-semibold text-gray-700 mb-1">
                                            Origine / École de formation
                                        </label>
                                        <InputText id="origine_formation" v-model="form.origine_formation" class="w-full" placeholder="Ex: EMIA, Rang, ESOA..." />
                                    </div>

                                    <!-- Fonction actuelle -->
                                    <div class="field">
                                        <label for="fonction_actuelle" class="block text-sm font-semibold text-gray-700 mb-1">
                                            Fonction actuelle
                                        </label>
                                        <InputText id="fonction_actuelle" v-model="form.fonction_actuelle" class="w-full" placeholder="Ex: C.B.T, Commandant d'unité..." />
                                    </div>

                                    <!-- Fonction passée -->
                                    <div class="field">
                                        <label for="fonction_passee" class="block text-sm font-semibold text-gray-700 mb-1">
                                            Fonction passée
                                        </label>
                                        <InputText id="fonction_passee" v-model="form.fonction_passee" class="w-full" />
                                    </div>

                                    <!-- Spécialité -->
                                    <div class="field">
                                        <label for="specialite" class="block text-sm font-semibold text-gray-700 mb-1">
                                            Spécialité
                                        </label>
                                        <InputText id="specialite" v-model="form.specialite" class="w-full" placeholder="Ex: Motocycliste, OPJ, Télécom..." />
                                    </div>

                                    <!-- Position actuelle -->
                                    <div class="field sm:col-span-2 lg:col-span-3">
                                        <div class="flex items-center justify-between mb-1">
                                            <label for="position_actuelle" class="block text-sm font-semibold text-gray-700">
                                                Position administrative / Théâtre opérationnel
                                            </label>
                                            <button type="button" @click="openQuickAddPosition"
                                                    class="text-sky-700 hover:text-sky-900 text-xs font-semibold flex items-center gap-1 cursor-pointer bg-sky-50 px-2 py-0.5 rounded border border-sky-200 hover:bg-sky-100 transition-colors"
                                                    title="Ajouter une nouvelle position">
                                                <i class="pi pi-plus text-[10px]"></i>
                                                <span>Ajouter une position</span>
                                            </button>
                                        </div>
                                        <Select id="position_actuelle"
                                                v-model="form.position_actuelle"
                                                :options="localPositions"
                                                optionLabel="nom"
                                                optionValue="nom"
                                                filter
                                                placeholder="Sélectionner ou chercher une position (Ex: En activité, Détachement, Escadron Djiguiya...)"
                                                class="w-full">
                                            <template #option="slotProps">
                                                <div class="flex items-center justify-between w-full py-0.5">
                                                    <span class="text-xs font-medium">{{ slotProps.option.nom }}</span>
                                                    <span :class="[
                                                        'text-[10px] px-1.5 py-0.2 rounded font-semibold',
                                                        slotProps.option.type === 'operationnelle' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600'
                                                    ]">
                                                        {{ slotProps.option.type === 'operationnelle' ? 'Théâtre OPS' : 'Administrative' }}
                                                    </span>
                                                </div>
                                            </template>
                                        </Select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ================================================================================= -->
                        <!-- ÉTAPE 2 : APTITUDE, DISCIPLINE, FORMATIONS & SYNTHÈSE (FUSION ÉTAPES 3 & 4)         -->
                        <!-- ================================================================================= -->
                        <div v-show="currentStep === 2" class="space-y-6">

                            <!-- CARD 1 : Aptitude Médicale, OPEX & Discipline -->
                            <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 shadow-2xs">
                                <div class="flex items-center gap-2 text-sky-900 border-b border-slate-200 pb-3 mb-4 text-sm font-extrabold uppercase tracking-wide">
                                    <i class="pi pi-shield text-sky-800 text-base"></i>
                                    <span>Card 1 — Santé, Aptitude Médicale & Discipline</span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Groupe sanguin -->
                                    <div class="field">
                                        <label for="groupe_sanguin" class="block text-sm font-semibold text-gray-700 mb-1">
                                            Groupe sanguin
                                        </label>
                                        <Select v-model="form.groupe_sanguin" :options="groupeSanguinOptions" optionLabel="label" optionValue="value"
                                                placeholder="Sélectionner" class="w-full" showClear />
                                    </div>

                                    <!-- Taille -->
                                    <div class="field">
                                        <label for="taille" class="block text-sm font-semibold text-gray-700 mb-1">
                                            Taille (stature)
                                        </label>
                                        <InputText id="taille" v-model="form.taille" class="w-full" placeholder="Ex: 1.78 m ou 178 cm" />
                                    </div>

                                    <!-- Statut sanitaire -->
                                    <div class="field">
                                        <label for="status_sanitaire" class="block text-sm font-semibold text-gray-700 mb-1">
                                            Statut sanitaire / Aptitude
                                        </label>
                                        <InputText id="status_sanitaire" v-model="form.status_sanitaire" class="w-full" placeholder="Ex: Apte, Blessé de guerre..." />
                                    </div>

                                    <!-- Engagement OPS -->
                                    <div class="field sm:col-span-2 lg:col-span-3">
                                        <label for="engagement_ops" class="block text-sm font-semibold text-gray-700 mb-1">
                                            Engagement OPS / Théâtres d'opérations
                                        </label>
                                        <InputText id="engagement_ops" v-model="form.engagement_ops" class="w-full" placeholder="Ex: 2 OPS, Opération Maliko, Théâtre Est, Sokolo, Garsi..." />
                                    </div>
                                </div>

                                <!-- Cases à cocher permis / antécédents -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 mt-4 border-t border-slate-200">
                                    <div class="flex items-center gap-2.5 p-3.5 border rounded-xl bg-white hover:bg-slate-50 transition-colors">
                                        <Checkbox id="a_permis_conduire" v-model="form.a_permis_conduire" :binary="true" />
                                        <label for="a_permis_conduire" class="text-xs font-bold text-gray-800 cursor-pointer flex items-center gap-1.5">
                                            <i class="pi pi-car text-sky-700"></i>
                                            <span>Titulaire permis de conduire</span>
                                        </label>
                                    </div>

                                    <div class="flex items-center gap-2.5 p-3.5 border rounded-xl bg-white hover:bg-slate-50 transition-colors">
                                        <Checkbox id="antecedent_judiciaire" v-model="form.antecedent_judiciaire" :binary="true" />
                                        <label for="antecedent_judiciaire" class="text-xs font-bold text-gray-800 cursor-pointer flex items-center gap-1.5">
                                            <i class="pi pi-exclamation-triangle text-amber-600"></i>
                                            <span>Antécédent judiciaire</span>
                                        </label>
                                    </div>

                                    <div class="flex items-center gap-2.5 p-3.5 border rounded-xl bg-white hover:bg-slate-50 transition-colors">
                                        <Checkbox id="antecedent_disciplinaire" v-model="form.antecedent_disciplinaire" :binary="true" />
                                        <label for="antecedent_disciplinaire" class="text-xs font-bold text-gray-800 cursor-pointer flex items-center gap-1.5">
                                            <i class="pi pi-ban text-red-600"></i>
                                            <span>Antécédent disciplinaire</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- CARD 2 : Certificats, Décorations & Diplômes -->
                            <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 shadow-2xs">
                                <div class="flex items-center gap-2 text-sky-900 border-b border-slate-200 pb-3 mb-4 text-sm font-extrabold uppercase tracking-wide">
                                    <i class="pi pi-book text-sky-800 text-base"></i>
                                    <span>Card 2 — Certificats & Formations Militaires Obtenus</span>
                                </div>

                                <!-- Formations militaires -->
                                <div v-if="showFormationsSection">
                                    <div v-if="filteredCertificats.length === 0" class="text-center py-6 text-gray-500 bg-white rounded-xl border border-dashed border-slate-200">
                                        <i class="pi pi-info-circle text-2xl mb-1 text-slate-400"></i>
                                        <p class="text-xs">Aucune formation spécifique configurée pour ce grade.</p>
                                    </div>

                                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div v-for="certificat in filteredCertificats" :key="certificat.id"
                                             class="border rounded-xl p-3.5 transition-all bg-white hover:border-sky-300">
                                            <div class="flex items-start gap-2.5">
                                                <Checkbox :id="'certif_' + certificat.id"
                                                          v-model="form.certificats[certificat.id].obtenu"
                                                          :binary="true"
                                                          @change="onCertificatChange(certificat.id)" />
                                                <label :for="'certif_' + certificat.id" class="font-bold text-gray-800 text-xs cursor-pointer flex-1">
                                                    {{ certificat.nom_certificat }}
                                                    <Tag :value="certificat.niveau_certificat" :style="getNiveauStyle(certificat.niveau_certificat)" class="ml-1 text-[10px]" />
                                                </label>
                                            </div>

                                            <div v-if="form.certificats[certificat.id].obtenu" class="mt-3 pl-6 space-y-2">
                                                <div>
                                                    <label :for="'date_certif_' + certificat.id" class="block text-xs font-semibold text-gray-600 mb-1">
                                                        Date d'obtention <span class="text-red-500">*</span>
                                                    </label>
                                                    <DatePicker :id="'date_certif_' + certificat.id"
                                                                v-model="form.certificats[certificat.id].date_obtention"
                                                                dateFormat="dd/mm/yy" showIcon class="w-full" size="small"
                                                                :class="{ 'p-invalid': dateErrors[certificat.id] }" />
                                                    <small v-if="dateErrors[certificat.id]" class="text-red-500 text-xs font-medium">
                                                        Date d'obtention obligatoire
                                                    </small>
                                                </div>

                                                <div>
                                                    <label :for="'doc_' + certificat.id" class="block text-xs text-gray-600 mb-1">Document justificatif (optionnel)</label>
                                                    <div class="flex items-center gap-2">
                                                        <FileUpload :id="'doc_' + certificat.id" mode="basic"
                                                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                                                    chooseLabel="📎 Choisir" customUpload
                                                                    @select="(event) => onFileSelect(certificat.id, event)"
                                                                    class="w-full" />
                                                        <Button v-if="form.certificats[certificat.id].document"
                                                                icon="pi pi-times"
                                                                class="p-button-rounded p-button-danger p-button-text cursor-pointer"
                                                                size="small"
                                                                @click="removeDocument(certificat.id)"
                                                                title="Supprimer le document" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Décorations dynamiques -->
                                <div class="pt-4 mt-4 border-t border-slate-200">
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="block text-sm font-bold text-slate-800 flex items-center gap-1.5">
                                            <i class="pi pi-award text-amber-600"></i>
                                            <span>Décorations & Médailles</span>
                                        </label>
                                        <button type="button" @click="addDecoration"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-lg bg-amber-50 text-amber-800 border border-amber-300 hover:bg-amber-100 transition-colors cursor-pointer shadow-2xs">
                                            <i class="pi pi-plus text-[10px]"></i>
                                            <span>Ajouter une médaille</span>
                                        </button>
                                    </div>

                                    <div v-if="decorationsList.length > 0" class="space-y-2 mb-2">
                                        <div v-for="(dec, idx) in decorationsList" :key="idx" class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-amber-100 text-amber-800 flex items-center justify-center shrink-0 text-xs font-bold">
                                                {{ idx + 1 }}
                                            </div>
                                            <InputText v-model="decorationsList[idx]" class="w-full text-xs" placeholder="Ex: Médaille du Mérite Militaire, Croix de la Valeur..." />
                                            <button type="button" @click="removeDecoration(idx)"
                                                    class="p-2 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition-colors shrink-0 cursor-pointer"
                                                    title="Supprimer cette médaille">
                                                <i class="pi pi-trash text-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div v-else class="text-xs text-slate-400 italic p-3 border border-dashed border-slate-200 rounded-xl text-center bg-white">
                                        Aucune médaille ajoutée.
                                    </div>
                                </div>

                                <!-- Diplômes académiques civils -->
                                <div class="pt-4 mt-4 border-t border-slate-200">
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="block text-sm font-bold text-slate-800 flex items-center gap-1.5">
                                            <i class="pi pi-graduation-cap text-sky-700"></i>
                                            <span>Diplômes académiques (civils / universitaires)</span>
                                        </label>
                                        <button type="button" @click="addDiplome"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-lg bg-sky-50 text-sky-800 border border-sky-300 hover:bg-sky-100 transition-colors cursor-pointer shadow-2xs">
                                            <i class="pi pi-plus text-[10px]"></i>
                                            <span>Ajouter un diplôme</span>
                                        </button>
                                    </div>

                                    <div v-if="diplomesList.length > 0" class="space-y-2 mb-2">
                                        <div v-for="(dip, idx) in diplomesList" :key="idx" class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-sky-100 text-sky-800 flex items-center justify-center shrink-0 text-xs font-bold">
                                                {{ idx + 1 }}
                                            </div>
                                            <InputText v-model="diplomesList[idx]" class="w-full text-xs" placeholder="Ex: Baccalauréat, Licence en Droit, Master..." />
                                            <button type="button" @click="removeDiplome(idx)"
                                                    class="p-2 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition-colors shrink-0 cursor-pointer"
                                                    title="Supprimer ce diplôme">
                                                <i class="pi pi-trash text-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div v-else class="text-xs text-slate-400 italic p-3 border border-dashed border-slate-200 rounded-xl text-center bg-white">
                                        Aucun diplôme civil ajouté.
                                    </div>
                                </div>

                                <!-- Observations -->
                                <div class="pt-4 mt-4 border-t border-slate-200">
                                    <label for="observations" class="block text-sm font-bold text-gray-700 mb-1">
                                        Observations particulières
                                    </label>
                                    <InputText id="observations" v-model="form.observations" class="w-full" placeholder="Remarques administratives, situations particulières..." />
                                </div>
                            </div>

                            <!-- Fiche Récapitulative de Synthèse -->
                            <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-50 to-sky-50 border border-sky-200 shadow-xs">
                                <div class="flex items-center gap-2 text-sky-900 font-extrabold text-xs uppercase tracking-wider mb-3">
                                    <i class="pi pi-check-circle text-sky-700 text-base"></i>
                                    <span>Synthèse de contrôle avant enregistrement définitif</span>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                                    <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                                        <span class="text-slate-400 block text-[10px] font-semibold uppercase">Identité</span>
                                        <strong class="text-slate-900 truncate block text-sm">{{ form.nom || '—' }} {{ form.prenom || '—' }}</strong>
                                    </div>
                                    <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                                        <span class="text-slate-400 block text-[10px] font-semibold uppercase">Matricule</span>
                                        <strong class="text-slate-900 truncate block text-sm">{{ form.matricule || '—' }}</strong>
                                    </div>
                                    <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                                        <span class="text-slate-400 block text-[10px] font-semibold uppercase">Grade</span>
                                        <strong class="text-sky-800 truncate block text-sm">{{ form.grade_actuel || '—' }}</strong>
                                    </div>
                                    <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                                        <span class="text-slate-400 block text-[10px] font-semibold uppercase">Statut</span>
                                        <strong class="text-emerald-800 capitalize truncate block text-sm">{{ form.statut || '—' }}</strong>
                                    </div>
                                </div>
                                <p class="text-[11px] text-slate-600 mt-3 italic flex items-center gap-1.5">
                                    <i class="pi pi-info-circle text-sky-600"></i>
                                    <span>Vérifiez l'ensemble des informations saisies. Vous pourrez toujours modifier la fiche ultérieurement.</span>
                                </p>
                            </div>
                        </div>

                        <!-- ================================================================================= -->
                        <!-- BARRE DE NAVIGATION INFÉRIEURE DU STEPPER                                          -->
                        <!-- ================================================================================= -->
                        <div class="flex items-center justify-between pt-6 mt-8 border-t border-slate-200">
                            <!-- Bouton Précédent -->
                            <Button v-if="currentStep > 0"
                                    type="button"
                                    label="Étape précédente"
                                    icon="pi pi-arrow-left"
                                    class="p-button-outlined p-button-secondary font-bold text-xs cursor-pointer"
                                    @click="prevStep" />
                            <div v-else></div>

                            <!-- Actions Droite -->
                            <div class="flex items-center gap-3">
                                <Button type="button"
                                        label="Annuler"
                                        icon="pi pi-times"
                                        class="p-button-text p-button-secondary text-xs cursor-pointer"
                                        @click="cancel" />

                                <Button v-if="currentStep < steps.length - 1"
                                        type="button"
                                        label="Étape suivante"
                                        icon="pi pi-arrow-right"
                                        iconPos="right"
                                        class="!bg-sky-800 hover:!bg-sky-900 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-xs cursor-pointer flex items-center gap-1.5"
                                        @click="nextStep" />

                                <Button v-else
                                        type="submit"
                                        label="Enregistrer le militaire"
                                        icon="pi pi-check"
                                        :loading="saving"
                                        class="!bg-gradient-to-r !from-emerald-700 !to-teal-700 hover:!from-emerald-800 hover:!to-teal-800 text-white font-bold text-xs px-6 py-2.5 rounded-xl shadow-md cursor-pointer flex items-center gap-1.5" />
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- DIALOG AJOUT RAPIDE RÉGION -->
        <Dialog v-model:visible="quickRegionVisible" header="Ajout Rapide : Nouvelle Région" :modal="true" class="w-full max-w-md">
            <div class="space-y-3 pt-2 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Code officiel (ex: R1, R2, DGGN) <span class="text-red-500">*</span></label>
                    <InputText v-model="quickRegionForm.code" class="w-full text-xs font-mono" placeholder="Ex: R12, CMD-SPEC..." />
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nom complet de la Région <span class="text-red-500">*</span></label>
                    <InputText v-model="quickRegionForm.nom" class="w-full text-xs" placeholder="Ex: REGION N°12..." />
                </div>
            </div>
            <template #footer>
                <Button label="Annuler" text severity="secondary" size="small" @click="quickRegionVisible = false" />
                <Button label="Créer & Sélectionner" icon="pi pi-check" size="small" class="bg-sky-700 hover:bg-sky-800 text-white border-0" :loading="quickRegionLoading" @click="saveQuickRegion" />
            </template>
        </Dialog>

        <!-- DIALOG AJOUT RAPIDE UNITÉ -->
        <Dialog v-model:visible="quickUniteVisible" header="Ajout Rapide : Nouvelle Unité" :modal="true" class="w-full max-w-md">
            <div class="space-y-3 pt-2 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Région de Rattachement <span class="text-red-500">*</span></label>
                    <Select v-model="quickUniteForm.region_id" :options="localRegions" optionLabel="nom" optionValue="id" filter placeholder="Choisir la région parente" class="w-full text-xs" />
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nom de l'Unité / Brigade / Escadron <span class="text-red-500">*</span></label>
                    <InputText v-model="quickUniteForm.nom" class="w-full text-xs" placeholder="Ex: Brigade Territoriale de..." />
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Type d'unité</label>
                    <Select v-model="quickUniteForm.type" :options="['Brigade Territoriale', 'Brigade de Recherches', 'Escadron', 'Peloton PSIG', 'État-Major', 'Direction / Service', 'Autre']" class="w-full text-xs" />
                </div>
            </div>
            <template #footer>
                <Button label="Annuler" text severity="secondary" size="small" @click="quickUniteVisible = false" />
                <Button label="Créer & Sélectionner" icon="pi pi-check" size="small" class="bg-sky-700 hover:bg-sky-800 text-white border-0" :loading="quickUniteLoading" @click="saveQuickUnite" />
            </template>
        </Dialog>

        <!-- DIALOG AJOUT RAPIDE POSITION -->
        <Dialog v-model:visible="quickPositionVisible" header="Ajout Rapide : Nouvelle Position" :modal="true" class="w-full max-w-md">
            <div class="space-y-3 pt-2 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Libellé de la Position <span class="text-red-500">*</span></label>
                    <InputText v-model="quickPositionForm.nom" class="w-full text-xs" placeholder="Ex: Escadron Badala, En mission..." />
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Type de position</label>
                    <Select v-model="quickPositionForm.type" :options="[{ label: 'Administrative', value: 'administrative' }, { label: 'Théâtre Opérationnel / OPS', value: 'operationnelle' }]" optionLabel="label" optionValue="value" class="w-full text-xs" />
                </div>
            </div>
            <template #footer>
                <Button label="Annuler" text severity="secondary" size="small" @click="quickPositionVisible = false" />
                <Button label="Créer & Sélectionner" icon="pi pi-check" size="small" class="bg-sky-700 hover:bg-sky-800 text-white border-0" :loading="quickPositionLoading" @click="saveQuickPosition" />
            </template>
        </Dialog>

        <Toast position="top-right" />
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import Dialog from 'primevue/dialog';
import FileUpload from 'primevue/fileupload';
import { useToast } from 'primevue/usetoast';
import axios from 'axios';

const props = defineProps({
    grades: { type: Array, required: true },
    certificats: { type: Array, default: () => [] },
    regions: { type: Array, default: () => [] },
    unites: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
});

const localRegions = ref([...(props.regions || [])]);
const localUnites = ref([...(props.unites || [])]);
const localPositions = ref([...(props.positions || [])]);

const toast = useToast();
const saving = ref(false);
const errors = ref({});
const dateErrors = ref({});
const photoPreview = ref(null);
const photoFile = ref(null);

// =========================================================================================
// CONFIGURATION DU STEPPER (3 ÉTAPES)
// Étape 1 : Identité & État Civil (Card 1 Photo/Matricule + Card 2 État Civil/Contact)
// Étape 2 : Affectation & Carrière (Unités, Région, Dates, Fonctions)
// Étape 3 : Aptitude, Formations & Synthèse (Card 1 Santé/Discipline + Card 2 Certificats/Diplômes + Synthèse)
// =========================================================================================
const steps = [
    { label: 'Identité & État Civil', title: '1. Identité Militaire & État Civil', subtitle: 'Photo d\'identité, matricules officiels, grade et état civil complet.', icon: 'pi pi-id-card' },
    { label: 'Affectation & Carrière', title: '2. Affectations Territoriales & Parcours', subtitle: 'Historique des unités d\'affectation, dates clés et fonctions exercées.', icon: 'pi pi-briefcase' },
    { label: 'Aptitude, Formations & Synthèse', title: '3. Santé, Discipline, Formations & Synthèse', subtitle: 'Profil médical, antécédents, certificats, diplômes civils et validation.', icon: 'pi pi-check-circle' }
];

const currentStep = ref(0);
const maxStepVisited = ref(0);
const stepErrors = reactive({
    0: {},
    1: {},
    2: {}
});

const progress = computed(() => {
    return ((currentStep.value + 1) / steps.length) * 100;
});

const validateStep = (stepIndex) => {
    stepErrors[stepIndex] = {};
    let isValid = true;

    if (stepIndex === 0) {
        // Validation Card 1 : Identifiants & Grade
        if (!form.matricule || !form.matricule.trim()) {
            stepErrors[0].matricule = 'Le matricule est obligatoire';
            isValid = false;
        }
        if (!form.grade_actuel) {
            stepErrors[0].grade_actuel = 'Le grade actuel est obligatoire';
            isValid = false;
        }
        if (!form.statut) {
            stepErrors[0].statut = 'Le statut administratif est obligatoire';
            isValid = false;
        }
        // Validation Card 2 : État Civil
        if (!form.nom || !form.nom.trim()) {
            stepErrors[0].nom = 'Le nom de famille est obligatoire';
            isValid = false;
        }
        if (!form.prenom || !form.prenom.trim()) {
            stepErrors[0].prenom = 'Le prénom est obligatoire';
            isValid = false;
        }
        if (!form.date_naissance) {
            stepErrors[0].date_naissance = 'La date de naissance est obligatoire';
            isValid = false;
        }

        if (!isValid) {
            toast.add({
                severity: 'warn',
                summary: 'Champs obligatoires requis',
                detail: 'Veuillez renseigner le Matricule, le Grade, le Statut, le Nom, le Prénom et la Date de naissance.',
                life: 4500
            });
        }
    } else if (stepIndex === 1) {
        // Validation Carrière & Affectation
        if (!form.date_entree_service) {
            stepErrors[1].date_entree_service = 'La date d\'entrée en service est obligatoire';
            isValid = false;
            toast.add({
                severity: 'warn',
                summary: 'Date d\'engagement requise',
                detail: 'Veuillez renseigner la Date d\'entrée en service (engagement).',
                life: 4000
            });
        }
    } else if (stepIndex === 2) {
        // Validation des certificats cochés sans date
        dateErrors.value = {};
        let hasDateError = false;
        Object.keys(form.certificats).forEach(certifId => {
            if (form.certificats[certifId].obtenu && !form.certificats[certifId].date_obtention) {
                dateErrors.value[certifId] = true;
                hasDateError = true;
            }
        });
        if (hasDateError) {
            isValid = false;
            toast.add({
                severity: 'error',
                summary: 'Date d\'obtention requise',
                detail: 'Veuillez renseigner la date d\'obtention pour chaque certificat coché.',
                life: 5000
            });
        }
    }

    return isValid;
};

const nextStep = () => {
    if (validateStep(currentStep.value)) {
        if (currentStep.value < steps.length - 1) {
            currentStep.value++;
            if (currentStep.value > maxStepVisited.value) {
                maxStepVisited.value = currentStep.value;
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }
};

const prevStep = () => {
    if (currentStep.value > 0) {
        currentStep.value--;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

const goToStep = (targetIndex) => {
    if (targetIndex <= maxStepVisited.value) {
        currentStep.value = targetIndex;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

// =========================================================================================
// OPTIONS & VALEURS DU FORMULAIRE
// =========================================================================================
const statutOptions = [
    { label: 'Actif', value: 'actif' },
    { label: 'Retraité', value: 'retraité' },
    { label: 'Déserteur', value: 'déserteur' },
    { label: 'Décédé', value: 'décédé' },
    { label: 'Formation', value: 'formation' },
    { label: 'Stage', value: 'stage' }
];

const categorieOptions = [
    { label: 'Militaire du Rang', value: 'Militaire du Rang' },
    { label: 'Sous-officier Subalterne', value: 'Sous-officier Subalterne' },
    { label: 'Sous-officier Supérieur', value: 'Sous-officier Supérieur' },
    { label: 'Officier Subalterne', value: 'Officier Subalterne' },
    { label: 'Officier Supérieur', value: 'Officier Supérieur' },
    { label: 'Officier Général', value: 'Officier Général' }
];

const sexeOptions = [
    { label: 'Masculin (M)', value: 'M' },
    { label: 'Féminin (F)', value: 'F' }
];

const groupeSanguinOptions = [
    { label: 'A+', value: 'A+' },
    { label: 'A-', value: 'A-' },
    { label: 'B+', value: 'B+' },
    { label: 'B-', value: 'B-' },
    { label: 'AB+', value: 'AB+' },
    { label: 'AB-', value: 'AB-' },
    { label: 'O+', value: 'O+' },
    { label: 'O-', value: 'O-' }
];

const form = reactive({
    matricule: '',
    mle_grm: '',
    numero_info: '',
    numero_nina: '',
    grade_actuel: null,
    categorie: null,
    nom: '',
    prenom: '',
    date_naissance: null,
    lieu_naissance: '',
    date_entree_service: null,
    date_derniere_promotion: null,
    specialite: '',
    origine_formation: '',
    position_actuelle: '',
    unite: '',
    region: '',
    date_mutation: null,
    affectations: [
        { unite: '', region: '', date_mutation: null }
    ],
    fonction_passee: '',
    fonction_actuelle: '',
    telephone: '',
    statut: 'actif',
    sexe: null,
    groupe_sanguin: null,
    taille: '',
    status_sanitaire: '',
    engagement_ops: '',
    personne_a_contacter: '',
    telephone_personne_contacter: '',
    decorations: '',
    diplomes_academiques: '',
    observations: '',
    date_deces: null,
    circonstances_deces: '',
    motif_radiation: '',
    reference_decision: '',
    unite_transfert: '',
    motif_transfert: '',
    a_permis_conduire: false,
    antecedent_judiciaire: false,
    antecedent_disciplinaire: false,
    certificats: {}
});

if (props.certificats && Array.isArray(props.certificats)) {
    props.certificats.forEach(certificat => {
        form.certificats[certificat.id] = {
            obtenu: false,
            date_obtention: null,
            document: null
        };
    });
}

const selectedGradeInfo = computed(() => {
    return props.grades?.find(g => g.nom_grade === form.grade_actuel);
});

const ageRetraiteMax = computed(() => {
    return selectedGradeInfo.value?.retraite_obligatoire || null;
});

const isRetraiteDepassee = computed(() => {
    if (!form.date_naissance || !ageRetraiteMax.value) return false;
    const dob = new Date(form.date_naissance);
    if (isNaN(dob.getTime())) return false;
    const retirementDate = new Date(dob);
    retirementDate.setFullYear(retirementDate.getFullYear() + ageRetraiteMax.value);
    return retirementDate < new Date();
});

watch(isRetraiteDepassee, (isPast) => {
    if (isPast && form.statut === 'actif') {
        form.statut = 'retraité';
    }
});

const determinerCategorieStatutaire = (gradeNom) => {
    if (!gradeNom) return null;
    const clean = gradeNom.toLowerCase().trim();

    if (clean.includes('adjudant') || clean.includes('major') || ['acm', 'adc', 'adj', 'adm'].includes(clean)) {
        return 'Sous-officier Supérieur';
    }
    if (clean.includes('sergent') || clean.includes('maréchal') || clean.includes('marechal') || clean.includes('eleve sous officier') || clean.includes('élève sous officier') || ['sgt', 'sch', 'mdl', 'mdc', 'esoa'].includes(clean)) {
        return 'Sous-officier Subalterne';
    }
    if (clean.includes('général') || clean.includes('general') || ['gbr', 'gdb', 'gdv', 'gdd', 'gca', 'gar'].includes(clean)) {
        return 'Officier Général';
    }
    if (clean.includes('commandant') || clean.includes('chef d\'escadron') || clean.includes('colonel') || ['cdt', 'ces', 'lcl', 'col', 'clm'].includes(clean)) {
        return 'Officier Supérieur';
    }
    if (clean.includes('lieutenant') || clean.includes('capitaine') || clean.includes('aspirant') || clean.includes('eleve officier') || clean.includes('élève officier') || ['slt', 'ltn', 'cne', 'asp', 'eoa'].includes(clean)) {
        return 'Officier Subalterne';
    }
    if (clean.includes('soldat') || clean.includes('caporal') || clean.includes('gendarme adjoint') || clean.includes('sapeur') || ['sdt1', 'sdt2', 's1', 's2', 'cpl', 'ga', 'eg', 'sap1', '1°cl'].includes(clean)) {
        return 'Militaire du Rang';
    }
    return null;
};

watch(() => form.grade_actuel, (newGrade) => {
    if (!newGrade) return;
    const gradeInfo = props.grades?.find(g => g.nom_grade === newGrade || g.code_grade === newGrade);
    if (gradeInfo?.categorie_statutaire) {
        form.categorie = gradeInfo.categorie_statutaire;
    } else {
        const cat = determinerCategorieStatutaire(newGrade);
        if (cat) form.categorie = cat;
    }
});

const onPhotoSelected = (event) => {
    const file = event.target.files[0];
    if (file) {
        photoFile.value = file;
        photoPreview.value = URL.createObjectURL(file);
    }
};

const removePhoto = () => {
    photoFile.value = null;
    photoPreview.value = null;
};

const addAffectation = () => {
    form.affectations.push({
        unite: '',
        region: '',
        date_mutation: null
    });
};

const removeAffectation = (index) => {
    if (form.affectations.length > 1) {
        form.affectations.splice(index, 1);
    } else {
        form.affectations[0] = { unite: '', region: '', date_mutation: null };
    }
};

// --- Filtres et Ajout Rapide Régions / Unités / Positions ---
const quickRegionVisible = ref(false);
const quickRegionLoading = ref(false);
const quickRegionForm = ref({ code: '', nom: '' });

const quickUniteVisible = ref(false);
const quickUniteLoading = ref(false);
const quickUniteForm = ref({ region_id: null, nom: '', type: 'Brigade Territoriale', est_operationnelle: true });

const quickPositionVisible = ref(false);
const quickPositionLoading = ref(false);
const quickPositionForm = ref({ nom: '', type: 'administrative' });

const currentQuickAffIndex = ref(null);

const getUnitesForRow = (idx) => {
    const selectedRegion = form.affectations[idx]?.region;
    if (!selectedRegion) {
        return localUnites.value;
    }
    const regionObj = localRegions.value.find(r => r.nom === selectedRegion || r.code === selectedRegion);
    if (!regionObj) {
        return localUnites.value;
    }
    const filtered = localUnites.value.filter(u => u.region_id === regionObj.id);
    return filtered.length > 0 ? filtered : localUnites.value;
};

const onRegionSelect = (idx) => {
    const selectedRegion = form.affectations[idx]?.region;
    const currentUnite = form.affectations[idx]?.unite;
    if (selectedRegion && currentUnite) {
        const uniteObj = localUnites.value.find(u => u.nom === currentUnite);
        if (uniteObj && uniteObj.region && uniteObj.region.nom !== selectedRegion && uniteObj.region.code !== selectedRegion) {
            form.affectations[idx].unite = '';
        }
    }
};

const onUniteSelect = (idx) => {
    const selectedUnite = form.affectations[idx]?.unite;
    if (!selectedUnite) return;
    const uniteObj = localUnites.value.find(u => u.nom === selectedUnite);
    if (uniteObj && uniteObj.region) {
        form.affectations[idx].region = uniteObj.region.nom;
    }
};

const openQuickAddRegion = (idx) => {
    currentQuickAffIndex.value = idx;
    quickRegionForm.value = { code: '', nom: '' };
    quickRegionVisible.value = true;
};

const saveQuickRegion = async () => {
    if (!quickRegionForm.value.code || !quickRegionForm.value.nom) {
        toast.add({ severity: 'warn', summary: 'Champs requis', detail: 'Code et nom sont obligatoires.', life: 3000 });
        return;
    }
    quickRegionLoading.value = true;
    try {
        const response = await axios.post(route('configurations.regions.store'), quickRegionForm.value);
        if (response.data && response.data.region) {
            localRegions.value.push(response.data.region);
            if (currentQuickAffIndex.value !== null && form.affectations[currentQuickAffIndex.value]) {
                form.affectations[currentQuickAffIndex.value].region = response.data.region.nom;
            }
            quickRegionVisible.value = false;
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Nouvelle région ajoutée et sélectionnée !', life: 3000 });
        }
    } catch (e) {
        const msg = e.response?.data?.errors?.code?.[0] || e.response?.data?.message || 'Erreur lors de la création.';
        toast.add({ severity: 'error', summary: 'Erreur', detail: msg, life: 4000 });
    } finally {
        quickRegionLoading.value = false;
    }
};

const openQuickAddUnite = (idx) => {
    currentQuickAffIndex.value = idx;
    let preselectedRegionId = null;
    const currentRegionNom = form.affectations[idx]?.region;
    if (currentRegionNom) {
        const rObj = localRegions.value.find(r => r.nom === currentRegionNom || r.code === currentRegionNom);
        if (rObj) preselectedRegionId = rObj.id;
    }
    quickUniteForm.value = {
        region_id: preselectedRegionId || (localRegions.value[0]?.id ?? null),
        nom: '',
        type: 'Brigade Territoriale',
        est_operationnelle: true
    };
    quickUniteVisible.value = true;
};

const saveQuickUnite = async () => {
    if (!quickUniteForm.value.region_id || !quickUniteForm.value.nom) {
        toast.add({ severity: 'warn', summary: 'Champs requis', detail: 'Région et nom de l\'unité obligatoires.', life: 3000 });
        return;
    }
    quickUniteLoading.value = true;
    try {
        const response = await axios.post(route('configurations.unites.store'), quickUniteForm.value);
        if (response.data && response.data.unite) {
            localUnites.value.push(response.data.unite);
            if (currentQuickAffIndex.value !== null && form.affectations[currentQuickAffIndex.value]) {
                form.affectations[currentQuickAffIndex.value].unite = response.data.unite.nom;
                if (response.data.unite.region?.nom) {
                    form.affectations[currentQuickAffIndex.value].region = response.data.unite.region.nom;
                }
            }
            quickUniteVisible.value = false;
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Nouvelle unité ajoutée et sélectionnée !', life: 3000 });
        }
    } catch (e) {
        const msg = e.response?.data?.errors?.nom?.[0] || e.response?.data?.message || 'Erreur lors de la création.';
        toast.add({ severity: 'error', summary: 'Erreur', detail: msg, life: 4000 });
    } finally {
        quickUniteLoading.value = false;
    }
};

const openQuickAddPosition = () => {
    quickPositionForm.value = { nom: '', type: 'administrative' };
    quickPositionVisible.value = true;
};

const saveQuickPosition = async () => {
    if (!quickPositionForm.value.nom) {
        toast.add({ severity: 'warn', summary: 'Champ requis', detail: 'Le nom de la position est obligatoire.', life: 3000 });
        return;
    }
    quickPositionLoading.value = true;
    try {
        const response = await axios.post(route('configurations.positions.store'), quickPositionForm.value);
        if (response.data && response.data.position) {
            localPositions.value.push(response.data.position);
            form.position_actuelle = response.data.position.nom;
            quickPositionVisible.value = false;
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Nouvelle position ajoutée et sélectionnée !', life: 3000 });
        }
    } catch (e) {
        const msg = e.response?.data?.errors?.nom?.[0] || e.response?.data?.message || 'Erreur lors de la création.';
        toast.add({ severity: 'error', summary: 'Erreur', detail: msg, life: 4000 });
    } finally {
        quickPositionLoading.value = false;
    }
};

// --- Décorations & Diplômes dynamiques ---
const decorationsList = ref([]);
const diplomesList = ref([]);

const addDecoration = () => {
    decorationsList.value.push('');
};

const removeDecoration = (index) => {
    decorationsList.value.splice(index, 1);
};

const addDiplome = () => {
    diplomesList.value.push('');
};

const removeDiplome = (index) => {
    diplomesList.value.splice(index, 1);
};

const formationsOfficiers = [
    'APLI', 'CFCU', 'Certificat État-major', 'Certificat d\'état-major', 'École d\'État-Major', 'EEM',
    'Cours supérieur d\'état-major', 'École de guerre', 'Brevet Supérieur de Second Degré',
    'Cour d\'Application', 'Cour des Futurs Commandants d\'Unité'
];

const officierTypes = ['officier général', 'officier supérieur', 'officier subalterne'];

const estFormationOfficier = (certificat) => {
    const nom = certificat.nom_certificat;
    const niveau = certificat.niveau_certificat;
    if (formationsOfficiers.includes(nom) || formationsOfficiers.includes(niveau)) return true;
    for (const f of formationsOfficiers) {
        if (nom && (nom.includes(f) || f.includes(nom))) return true;
        if (niveau && (niveau.includes(f) || f.includes(niveau))) return true;
    }
    return false;
};

const showFormationsSection = computed(() => form.grade_actuel !== null && form.grade_actuel !== '');

const filteredCertificats = computed(() => {
    if (!form.grade_actuel) return [];
    const gradeInfo = props.grades.find(g => g.nom_grade === form.grade_actuel);
    if (!gradeInfo) return [];
    if (officierTypes.includes(gradeInfo.type_grade?.toLowerCase())) return props.certificats;
    return props.certificats.filter(cert => !estFormationOfficier(cert));
});

const getNiveauStyle = (niveau) => {
    const styles = {
        'BE':   { background: '#e0f2fe', color: '#0369a1' },
        'CAT1': { background: '#7dd3fc', color: '#0369a1' },
        'CAT2': { background: '#38bdf8', color: '#075985' },
        'CIA':  { background: '#22d3ee', color: '#0e7490' },
        'BA1':  { background: '#a7f3d0', color: '#047857' },
        'BA2':  { background: '#6ee7b7', color: '#065f46' },
        'BMP1': { background: '#fde68a', color: '#b45309' },
        'BMP2': { background: '#fcd34d', color: '#92400e' },
        'BS':   { background: '#fed7aa', color: '#c2410c' },
        'CT1':  { background: '#ddd6fe', color: '#6d28d9' },
        'CT2':  { background: '#c4b5fd', color: '#5b21b6' },
    };
    return styles[niveau] || { background: '#bae6fd', color: '#0369a1' };
};

const onCertificatChange = (certifId) => {
    if (!form.certificats[certifId].obtenu) {
        form.certificats[certifId].date_obtention = null;
        form.certificats[certifId].document = null;
    }
};

const onFileSelect = (certifId, event) => {
    if (event.files && event.files[0]) {
        form.certificats[certifId].document = event.files[0];
    }
};

const removeDocument = (certifId) => {
    form.certificats[certifId].document = null;
};

const formatDateForServer = (date) => {
    if (!date) return '';
    if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
        return date;
    }
    const d = new Date(date);
    if (isNaN(d.getTime())) return '';
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const submitForm = () => {
    // Valider la dernière étape (étape 2) avant de soumettre
    if (!validateStep(2)) return;

    saving.value = true;
    errors.value = {};

    // Synchroniser l'affectation la plus récente (par date de mutation) dans les champs principaux
    if (form.affectations && form.affectations.length > 0) {
        const sorted = [...form.affectations].filter(a => a.unite || a.region || a.date_mutation);
        sorted.sort((a, b) => {
            const dateA = a.date_mutation ? new Date(a.date_mutation).getTime() : 0;
            const dateB = b.date_mutation ? new Date(b.date_mutation).getTime() : 0;
            return dateB - dateA;
        });
        const mostRecent = sorted[0] || form.affectations[0];
        form.unite = mostRecent.unite || '';
        form.region = mostRecent.region || '';
        form.date_mutation = mostRecent.date_mutation || null;
    }

    const formData = new FormData();

    if (form.affectations && form.affectations.length > 0) {
        form.affectations.forEach((aff, idx) => {
            formData.append(`affectations[${idx}][unite]`, aff.unite || '');
            formData.append(`affectations[${idx}][region]`, aff.region || '');
            formData.append(`affectations[${idx}][date_mutation]`, aff.date_mutation ? formatDateForServer(aff.date_mutation) : '');
        });
    }

    // Sérialisation des listes dynamiques Décorations & Diplômes
    const cleanDecorations = decorationsList.value.map(s => s.trim()).filter(Boolean);
    const cleanDiplomes = diplomesList.value.map(s => s.trim()).filter(Boolean);
    form.decorations = cleanDecorations.length > 0 ? JSON.stringify(cleanDecorations) : '';
    form.diplomes_academiques = cleanDiplomes.length > 0 ? JSON.stringify(cleanDiplomes) : '';

    const textFields = [
        'matricule', 'mle_grm', 'numero_info', 'numero_nina', 'grade_actuel', 'categorie',
        'nom', 'prenom', 'lieu_naissance', 'specialite', 'origine_formation',
        'position_actuelle', 'unite', 'region', 'fonction_passee', 'fonction_actuelle',
        'telephone', 'statut', 'sexe', 'groupe_sanguin', 'taille', 'status_sanitaire', 'engagement_ops',
        'personne_a_contacter', 'telephone_personne_contacter',
        'decorations', 'diplomes_academiques', 'observations',
        'circonstances_deces', 'motif_radiation', 'reference_decision',
        'unite_transfert', 'motif_transfert'
    ];

    textFields.forEach(field => {
        formData.append(field, (form[field] !== null && form[field] !== undefined) ? form[field] : '');
    });

    const dateFields = ['date_naissance', 'date_entree_service', 'date_derniere_promotion', 'date_mutation', 'date_deces'];
    dateFields.forEach(field => {
        formData.append(field, form[field] ? formatDateForServer(form[field]) : '');
    });

    formData.append('a_permis_conduire', form.a_permis_conduire ? '1' : '0');
    formData.append('antecedent_judiciaire', form.antecedent_judiciaire ? '1' : '0');
    formData.append('antecedent_disciplinaire', form.antecedent_disciplinaire ? '1' : '0');

    if (photoFile.value) {
        formData.append('photo', photoFile.value);
    }

    Object.keys(form.certificats).forEach(certifId => {
        const certData = form.certificats[certifId];
        formData.append(`certificats[${certifId}][obtenu]`, certData.obtenu ? '1' : '0');

        if (certData.obtenu) {
            if (certData.date_obtention) {
                formData.append(`certificats[${certifId}][date_obtention]`, formatDateForServer(certData.date_obtention));
            }
            if (certData.document) {
                formData.append(`certificats[${certifId}][document]`, certData.document);
            }
        }
    });

    router.post(route('militaires.store'), formData, {
        forceFormData: true,
        onSuccess: () => {
            saving.value = false;
            toast.add({
                severity: 'success',
                summary: 'Succès',
                detail: 'Militaire enregistré avec succès',
                life: 3000
            });
            router.visit(route('militaires.index'));
        },
        onError: (err) => {
            saving.value = false;
            errors.value = err;
            toast.add({
                severity: 'error',
                summary: 'Erreur de validation',
                detail: 'Veuillez corriger les informations requises dans le formulaire',
                life: 5000
            });

            // Basculement automatique vers l'étape contenant la première erreur (sur 3 étapes)
            const errKeys = Object.keys(err);
            if (errKeys.some(k => ['matricule', 'mle_grm', 'numero_info', 'numero_nina', 'grade_actuel', 'categorie', 'statut', 'nom', 'prenom', 'date_naissance', 'lieu_naissance', 'sexe', 'telephone'].includes(k))) {
                currentStep.value = 0;
            } else if (errKeys.some(k => ['date_entree_service', 'date_derniere_promotion', 'unite', 'region', 'affectations'].includes(k))) {
                currentStep.value = 1;
            } else {
                currentStep.value = 2;
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
};

const cancel = () => {
    router.visit(route('militaires.index'));
};
</script>
