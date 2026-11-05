<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-white">
                Ajouter un militaire - Gendarmerie Nationale
            </h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submitForm" enctype="multipart/form-data">

                            <!-- Bannière de résumé d'erreurs de validation -->
                            <div v-if="errors && Object.keys(errors).length > 0" class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg shadow-sm">
                                <div class="flex items-center gap-2 mb-2 text-red-700 font-semibold">
                                    <i class="pi pi-exclamation-triangle text-lg"></i>
                                    <span>Erreurs détectées lors de la validation du formulaire :</span>
                                </div>
                                <ul class="list-disc list-inside text-sm text-red-600 space-y-1">
                                    <li v-for="(err, field) in errors" :key="field">
                                        <strong class="capitalize">{{ field }}</strong> : {{ Array.isArray(err) ? err.join(', ') : err }}
                                    </li>
                                </ul>
                            </div>

                            <!-- 1. IDENTIFICATION & PHOTO -->
                            <Card class="mb-6 shadow-sm border border-slate-100">
                                <template #title>
                                    <div class="flex items-center gap-2 text-sky-900 border-b pb-3 text-lg font-bold">
                                        <i class="pi pi-id-card text-sky-800"></i>
                                        <span>Photo & Identifiants Militaires</span>
                                    </div>
                                </template>

                                <template #content>
                                    <div class="grid grid-cols-1 md:grid-cols-12 gap-6 pt-2">
                                        <!-- Photo d'identité -->
                                        <div class="md:col-span-3 flex flex-col items-center justify-center p-4 border rounded-xl bg-slate-50 text-center">
                                            <div class="w-36 h-44 bg-white border-2 border-dashed border-sky-300 rounded-lg overflow-hidden flex items-center justify-center mb-3 shadow-inner relative group">
                                                <img v-if="photoPreview" :src="photoPreview" alt="Photo d'identité" class="w-full h-full object-cover" />
                                                <div v-else class="flex flex-col items-center text-slate-400 p-2">
                                                    <i class="pi pi-user text-5xl mb-1"></i>
                                                    <span class="text-xs font-medium">Format d'identité 3:4</span>
                                                </div>
                                            </div>

                                            <div class="flex gap-2">
                                                <label class="cursor-pointer px-3 py-1.5 bg-sky-900 hover:bg-sky-950 text-white rounded text-xs font-medium flex items-center gap-1 shadow-sm transition">
                                                    <i class="pi pi-camera"></i>
                                                    <span>{{ photoPreview ? 'Changer' : 'Ajouter photo' }}</span>
                                                    <input type="file" accept="image/jpeg,image/png,image/jpg,image/webp" class="hidden" @change="onPhotoSelected" />
                                                </label>
                                                <button v-if="photoPreview" type="button" @click="removePhoto" class="px-2 py-1.5 bg-red-100 hover:bg-red-200 text-red-600 rounded text-xs transition" title="Supprimer la photo">
                                                    <i class="pi pi-trash"></i>
                                                </button>
                                            </div>
                                            <span class="text-[10px] text-slate-500 mt-2">JPG, PNG, WEBP (Max 5Mo)</span>
                                            <small v-if="errors.photo" class="text-red-500 text-xs mt-1">{{ errors.photo }}</small>
                                        </div>

                                        <!-- Champs identifiants -->
                                        <div class="md:col-span-9 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                            <!-- Matricule -->
                                            <div class="field">
                                                <label for="matricule" class="block text-sm font-medium text-gray-700 mb-1">
                                                    Matricule Armée <span class="text-red-500">*</span>
                                                </label>
                                                <InputText id="matricule" v-model="form.matricule" class="w-full" placeholder="Ex: 16276" :class="{ 'p-invalid': errors.matricule }" />
                                                <small v-if="errors.matricule" class="text-red-500">{{ errors.matricule }}</small>
                                            </div>

                                            <!-- MLE GRM -->
                                            <div class="field">
                                                <label for="mle_grm" class="block text-sm font-medium text-gray-700 mb-1">
                                                    Matricule Gendarmerie (GRM MLE)
                                                </label>
                                                <InputText id="mle_grm" v-model="form.mle_grm" class="w-full" placeholder="Ex: GRM9272M006871" />
                                            </div>

                                            <!-- N° Info -->
                                            <div class="field">
                                                <label for="numero_info" class="block text-sm font-medium text-gray-700 mb-1">
                                                    N° Informatique / Solde
                                                </label>
                                                <InputText id="numero_info" v-model="form.numero_info" class="w-full" placeholder="Ex: 1668" />
                                            </div>

                                            <!-- N° Nina -->
                                            <div class="field">
                                                <label for="numero_nina" class="block text-sm font-medium text-gray-700 mb-1">
                                                    N° NINA
                                                </label>
                                                <InputText id="numero_nina" v-model="form.numero_nina" class="w-full" placeholder="Numéro national d'identification" />
                                            </div>

                                            <!-- Grade actuel -->
                                            <div class="field">
                                                <label for="grade_actuel" class="block text-sm font-medium text-gray-700 mb-1">
                                                    Grade actuel <span class="text-red-500">*</span>
                                                </label>
                                                <Select v-model="form.grade_actuel" :options="grades" optionLabel="nom_grade" optionValue="nom_grade" placeholder="Sélectionner un grade" class="w-full" :class="{ 'p-invalid': errors.grade_actuel }" />
                                                <small v-if="errors.grade_actuel" class="text-red-500">{{ errors.grade_actuel }}</small>
                                            </div>

                                            <!-- Catégorie statutaire -->
                                            <div class="field">
                                                <label for="categorie" class="block text-sm font-medium text-gray-700 mb-1">
                                                    Catégorie statutaire
                                                </label>
                                                <Select v-model="form.categorie" :options="categorieOptions" optionLabel="label" optionValue="value" placeholder="Sélectionner la catégorie" class="w-full" showClear />
                                                <small class="text-[11px] text-sky-600 block mt-1">
                                                    <i class="pi pi-info-circle mr-1"></i>Renseignée automatiquement selon le grade sélectionné.
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </Card>

                            <!-- 2. ÉTAT CIVIL & COORDONNÉES -->
                            <Card class="mb-6 shadow-sm border border-slate-100">
                                <template #title>
                                    <div class="flex items-center gap-2 text-sky-900 border-b pb-3 text-lg font-bold">
                                        <i class="pi pi-user text-sky-800"></i>
                                        <span>État Civil & Coordonnées</span>
                                    </div>
                                </template>

                                <template #content>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-2">
                                        <!-- Nom -->
                                        <div class="field">
                                            <label for="nom" class="block text-sm font-medium text-gray-700 mb-1">
                                                Nom <span class="text-red-500">*</span>
                                            </label>
                                            <InputText id="nom" v-model="form.nom" class="w-full uppercase" :class="{ 'p-invalid': errors.nom }" />
                                            <small v-if="errors.nom" class="text-red-500">{{ errors.nom }}</small>
                                        </div>

                                        <!-- Prénom -->
                                        <div class="field">
                                            <label for="prenom" class="block text-sm font-medium text-gray-700 mb-1">
                                                Prénom(s) <span class="text-red-500">*</span>
                                            </label>
                                            <InputText id="prenom" v-model="form.prenom" class="w-full" :class="{ 'p-invalid': errors.prenom }" />
                                            <small v-if="errors.prenom" class="text-red-500">{{ errors.prenom }}</small>
                                        </div>

                                        <!-- Date de naissance -->
                                        <div class="field">
                                            <label for="date_naissance" class="block text-sm font-medium text-gray-700 mb-1">
                                                Date de naissance <span class="text-red-500">*</span>
                                            </label>
                                            <DatePicker id="date_naissance" v-model="form.date_naissance" dateFormat="dd/mm/yy" showIcon class="w-full" :class="{ 'p-invalid': errors.date_naissance }" />
                                            <small v-if="errors.date_naissance" class="text-red-500">{{ errors.date_naissance }}</small>
                                        </div>

                                        <!-- Lieu de naissance -->
                                        <div class="field">
                                            <label for="lieu_naissance" class="block text-sm font-medium text-gray-700 mb-1">
                                                Lieu de naissance
                                            </label>
                                            <InputText id="lieu_naissance" v-model="form.lieu_naissance" class="w-full" />
                                        </div>

                                        <!-- Sexe -->
                                        <div class="field">
                                            <label for="sexe" class="block text-sm font-medium text-gray-700 mb-1">
                                                Sexe
                                            </label>
                                            <Select v-model="form.sexe" :options="sexeOptions" optionLabel="label" optionValue="value" placeholder="Sélectionner" class="w-full" showClear />
                                        </div>

                                        <!-- Groupe sanguin -->
                                        <div class="field">
                                            <label for="groupe_sanguin" class="block text-sm font-medium text-gray-700 mb-1">
                                                Groupe sanguin
                                            </label>
                                            <Select v-model="form.groupe_sanguin" :options="groupeSanguinOptions" optionLabel="label" optionValue="value" placeholder="Sélectionner" class="w-full" showClear />
                                        </div>

                                        <!-- Taille -->
                                        <div class="field">
                                            <label for="taille" class="block text-sm font-medium text-gray-700 mb-1">
                                                Taille
                                            </label>
                                            <InputText id="taille" v-model="form.taille" class="w-full" placeholder="Ex: 1.78 m ou 178 cm" />
                                        </div>

                                        <!-- Téléphone -->
                                        <div class="field">
                                            <label for="telephone" class="block text-sm font-medium text-gray-700 mb-1">
                                                Téléphone
                                            </label>
                                            <InputText id="telephone" v-model="form.telephone" class="w-full" placeholder="Ex: +223 76 XX XX XX" />
                                        </div>

                                        <!-- Personne à contacter -->
                                        <div class="field">
                                            <label for="personne_a_contacter" class="block text-sm font-medium text-gray-700 mb-1">
                                                Personne à contacter en cas d'urgence
                                            </label>
                                            <InputText id="personne_a_contacter" v-model="form.personne_a_contacter" class="w-full" placeholder="Nom complet" />
                                        </div>

                                        <!-- Téléphone contact -->
                                        <div class="field">
                                            <label for="telephone_personne_contacter" class="block text-sm font-medium text-gray-700 mb-1">
                                                Téléphone de la personne à contacter
                                            </label>
                                            <InputText id="telephone_personne_contacter" v-model="form.telephone_personne_contacter" class="w-full" placeholder="Ex: +223 66 XX XX XX" />
                                        </div>
                                    </div>
                                </template>
                            </Card>

                            <!-- 3. AFFECTATIONS & MAILLAGE TERRITORIAL -->
                            <Card class="mb-6 shadow-sm border border-slate-100">
                                <template #title>
                                    <div class="flex items-center gap-2 text-sky-900 border-b pb-3 text-lg font-bold">
                                        <i class="pi pi-building text-sky-800"></i>
                                        <span>Affectation & Maillage Territorial</span>
                                    </div>
                                </template>

                                <template #content>
                                    <div class="space-y-4 pt-2">
                                        <div v-for="(aff, idx) in form.affectations" :key="idx"
                                            class="p-4 rounded-xl border border-slate-200 bg-slate-50/60 relative">
                                            <div class="flex items-center justify-between mb-3 border-b border-slate-200 pb-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-sky-800 text-white font-bold text-xs">
                                                        {{ idx + 1 }}
                                                    </span>
                                                    <span class="font-bold text-sm text-slate-800">
                                                        Affectation {{ idx === 0 ? '(Actuelle / Récente)' : 'n°' + (idx + 1) }}
                                                    </span>
                                                </div>
                                                <Button v-if="form.affectations.length > 1" type="button" icon="pi pi-trash"
                                                    class="p-button-rounded p-button-text p-button-sm !text-red-500 hover:!text-red-700 hover:!bg-red-50"
                                                    title="Supprimer cette affectation"
                                                    @click="removeAffectation(idx)" />
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                                <!-- Unité -->
                                                <div class="field">
                                                    <label :for="'unite_' + idx" class="block text-sm font-medium text-gray-700 mb-1">
                                                        Unité / Brigade / Escadron
                                                    </label>
                                                    <InputText :id="'unite_' + idx" v-model="aff.unite" class="w-full"
                                                        placeholder="Ex: GSIGN, Brigade Ségou, Escadron Samory..." />
                                                </div>

                                                <!-- Région -->
                                                <div class="field">
                                                    <label :for="'region_' + idx" class="block text-sm font-medium text-gray-700 mb-1">
                                                        Région de Gendarmerie
                                                    </label>
                                                    <InputText :id="'region_' + idx" v-model="aff.region" class="w-full"
                                                        placeholder="Ex: REGION N°1, REGION N°5, DGGN..." />
                                                </div>

                                                <!-- Date Mutation -->
                                                <div class="field">
                                                    <label :for="'date_mutation_' + idx" class="block text-sm font-medium text-gray-700 mb-1">
                                                        Date de dernière mutation
                                                    </label>
                                                    <DatePicker :id="'date_mutation_' + idx" v-model="aff.date_mutation"
                                                        dateFormat="dd/mm/yy" showIcon class="w-full" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex justify-start pt-1">
                                            <Button type="button" label="Ajouter une affectation" icon="pi pi-plus"
                                                class="p-button-sm !bg-white hover:!bg-sky-50 !text-sky-800 !border !border-sky-300 font-semibold shadow-xs"
                                                @click="addAffectation" />
                                        </div>
                                    </div>
                                </template>
                            </Card>

                            <!-- 4. MAILLAGE & CARRIÈRE -->
                            <Card class="mb-6 shadow-sm border border-slate-100">
                                <template #title>
                                    <div class="flex items-center gap-2 text-sky-900 border-b pb-3 text-lg font-bold">
                                        <i class="pi pi-briefcase text-sky-800"></i>
                                        <span>Maillage & Carrière</span>
                                    </div>
                                </template>

                                <template #content>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-2">
                                        <!-- Date d'entrée en service -->
                                        <div class="field">
                                            <label for="date_entree_service" class="block text-sm font-medium text-gray-700 mb-1">
                                                Date d'entrée en service <span class="text-red-500">*</span>
                                            </label>
                                            <DatePicker id="date_entree_service" v-model="form.date_entree_service" dateFormat="dd/mm/yy" showIcon class="w-full" :class="{ 'p-invalid': errors.date_entree_service }" />
                                            <small v-if="errors.date_entree_service" class="text-red-500">{{ errors.date_entree_service }}</small>
                                        </div>

                                        <!-- Date dernière promotion -->
                                        <div class="field">
                                            <label for="date_derniere_promotion" class="block text-sm font-medium text-gray-700 mb-1">
                                                Date dernière promotion
                                            </label>
                                            <DatePicker id="date_derniere_promotion" v-model="form.date_derniere_promotion" dateFormat="dd/mm/yy" showIcon class="w-full" />
                                        </div>

                                        <!-- Origine de formation -->
                                        <div class="field">
                                            <label for="origine_formation" class="block text-sm font-medium text-gray-700 mb-1">
                                                Origine / École de formation
                                            </label>
                                            <InputText id="origine_formation" v-model="form.origine_formation" class="w-full" placeholder="Ex: EMIA, Rang, ESOA..." />
                                        </div>

                                        <!-- Fonction actuelle -->
                                        <div class="field">
                                            <label for="fonction_actuelle" class="block text-sm font-medium text-gray-700 mb-1">
                                                Fonction actuelle
                                            </label>
                                            <InputText id="fonction_actuelle" v-model="form.fonction_actuelle" class="w-full" placeholder="Ex: C.B.T, Commandant, Elément..." />
                                        </div>

                                        <!-- Fonction passée -->
                                        <div class="field">
                                            <label for="fonction_passee" class="block text-sm font-medium text-gray-700 mb-1">
                                                Fonction passée
                                            </label>
                                            <InputText id="fonction_passee" v-model="form.fonction_passee" class="w-full" />
                                        </div>

                                        <!-- Spécialité -->
                                        <div class="field">
                                            <label for="specialite" class="block text-sm font-medium text-gray-700 mb-1">
                                                Spécialité
                                            </label>
                                            <InputText id="specialite" v-model="form.specialite" class="w-full" placeholder="Ex: Motocycliste, OPJ, Télécom..." />
                                        </div>

                                        <!-- Position actuelle -->
                                        <div class="field lg:col-span-3">
                                            <label for="position_actuelle" class="block text-sm font-medium text-gray-700 mb-1">
                                                Position administrative
                                            </label>
                                            <InputText id="position_actuelle" v-model="form.position_actuelle" class="w-full" placeholder="Ex: En activité, Détachement, Stage, En mission..." />
                                        </div>
                                    </div>
                                </template>
                            </Card>

                            <!-- 4. SANTÉ, OPÉRATIONS & DISCIPLINE -->
                            <Card class="mb-6 shadow-sm border border-slate-100">
                                <template #title>
                                    <div class="flex items-center gap-2 text-sky-900 border-b pb-3 text-lg font-bold">
                                        <i class="pi pi-shield text-sky-800"></i>
                                        <span>Santé, Théâtres d'Opérations & Discipline</span>
                                    </div>
                                </template>

                                <template #content>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                                        <!-- Statut sanitaire -->
                                        <div class="field">
                                            <label for="status_sanitaire" class="block text-sm font-medium text-gray-700 mb-1">
                                                Statut sanitaire / Aptitude médicale
                                            </label>
                                            <InputText id="status_sanitaire" v-model="form.status_sanitaire" class="w-full" placeholder="Ex: Apte, Blessé de guerre, Inapte port d'armes..." />
                                        </div>

                                        <!-- Engagement OPS -->
                                        <div class="field">
                                            <label for="engagement_ops" class="block text-sm font-medium text-gray-700 mb-1">
                                                Engagement OPS / Théâtres d'opérations
                                            </label>
                                            <InputText id="engagement_ops" v-model="form.engagement_ops" class="w-full" placeholder="Ex: 2 OPS, Mission Sokolo, Garsi 2023, Prévôté Kidal..." />
                                        </div>
                                    </div>

                                    <!-- Cases à cocher permise/antécédents -->
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                        <div class="flex items-center gap-2 p-3 border rounded-lg bg-gray-50">
                                            <Checkbox id="a_permis_conduire" v-model="form.a_permis_conduire" :binary="true" />
                                            <label for="a_permis_conduire" class="text-sm font-medium text-gray-700 cursor-pointer">
                                                <i class="pi pi-car text-sky-600 mr-1"></i> Titulaire permis de conduire
                                            </label>
                                        </div>

                                        <div class="flex items-center gap-2 p-3 border rounded-lg bg-gray-50">
                                            <Checkbox id="antecedent_judiciaire" v-model="form.antecedent_judiciaire" :binary="true" />
                                            <label for="antecedent_judiciaire" class="text-sm font-medium text-gray-700 cursor-pointer">
                                                <i class="pi pi-exclamation-triangle text-amber-500 mr-1"></i> Antécédent judiciaire
                                            </label>
                                        </div>

                                        <div class="flex items-center gap-2 p-3 border rounded-lg bg-gray-50">
                                            <Checkbox id="antecedent_disciplinaire" v-model="form.antecedent_disciplinaire" :binary="true" />
                                            <label for="antecedent_disciplinaire" class="text-sm font-medium text-gray-700 cursor-pointer">
                                                <i class="pi pi-ban text-red-500 mr-1"></i> Antécédent disciplinaire
                                            </label>
                                        </div>
                                    </div>
                                </template>
                            </Card>

                            <!-- 5. DÉCORATIONS & DIPLÔMES ACADÉMIQUES -->
                            <Card class="mb-6 shadow-sm border border-slate-100">
                                <template #title>
                                    <div class="flex items-center gap-2 text-sky-900 border-b pb-3 text-lg font-bold">
                                        <i class="pi pi-star text-sky-800"></i>
                                        <span>Décorations, Diplômes Académiques & Observations</span>
                                    </div>
                                </template>

                                <template #content>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                                        <!-- Décorations -->
                                        <div class="field">
                                            <label for="decorations" class="block text-sm font-medium text-gray-700 mb-1">
                                                Décorations & Médailles
                                            </label>
                                            <InputText id="decorations" v-model="form.decorations" class="w-full" placeholder="Ex: Médaille Commémorative, MMM, CVN..." />
                                        </div>

                                        <!-- Diplômes académiques civils -->
                                        <div class="field">
                                            <label for="diplomes_academiques" class="block text-sm font-medium text-gray-700 mb-1">
                                                Diplômes académiques (civils / universitaires)
                                            </label>
                                            <InputText id="diplomes_academiques" v-model="form.diplomes_academiques" class="w-full" placeholder="Ex: Baccalauréat, DEUG, Licence, Maîtrise, Technicien..." />
                                        </div>

                                        <!-- Observations -->
                                        <div class="field md:col-span-2">
                                            <label for="observations" class="block text-sm font-medium text-gray-700 mb-1">
                                                Observations particulières
                                            </label>
                                            <InputText id="observations" v-model="form.observations" class="w-full" placeholder="Remarques administratives, situations particulières..." />
                                        </div>
                                    </div>
                                </template>
                            </Card>

                            <!-- 6. STATUT ADMINISTRATIF -->
                            <Card class="mb-6 shadow-sm border border-slate-100">
                                <template #title>
                                    <div class="flex items-center gap-2 text-sky-900 border-b pb-3 text-lg font-bold">
                                        <i class="pi pi-check-circle text-sky-800"></i>
                                        <span>Statut Administratif & Événements</span>
                                    </div>
                                </template>

                                <template #content>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                                        <!-- Statut -->
                                        <div class="field">
                                            <label for="statut" class="block text-sm font-medium text-gray-700 mb-1">
                                                Statut <span class="text-red-500">*</span>
                                            </label>
                                            <Select v-model="form.statut" :options="statutOptions" optionLabel="label" optionValue="value" placeholder="Sélectionner un statut" class="w-full" :class="{ 'p-invalid': errors.statut }" />
                                            <small v-if="errors.statut" class="text-red-500">{{ errors.statut }}</small>
                                            <div v-if="isRetraiteDepassee" class="mt-2 p-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-800 flex items-center gap-2">
                                                <i class="pi pi-exclamation-triangle text-amber-600"></i>
                                                <span>Limite d'âge de retraite atteinte/dépassée ({{ ageRetraiteMax }} ans pour ce grade). Statut automatiquement positionné sur <strong>Retraité</strong>.</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Champs conditionnels selon le statut -->
                                    <div v-if="form.statut === 'décédé'" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                                        <div>
                                            <label class="block text-sm font-medium text-red-800 mb-1">Date du décès</label>
                                            <DatePicker v-model="form.date_deces" dateFormat="dd/mm/yy" showIcon class="w-full" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-red-800 mb-1">Circonstances du décès</label>
                                            <InputText v-model="form.circonstances_deces" class="w-full" placeholder="Ex: Attaque camp, Maladie, Théâtre d'opération..." />
                                        </div>
                                    </div>

                                    <div v-if="form.statut === 'déserteur'" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 p-4 bg-orange-50 border border-orange-200 rounded-lg">
                                        <div>
                                            <label class="block text-sm font-medium text-orange-800 mb-1">Motif de radiation</label>
                                            <InputText v-model="form.motif_radiation" class="w-full" placeholder="Ex: Désertion, Retrait d'emploi..." />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-orange-800 mb-1">Référence décision ministérielle</label>
                                            <InputText v-model="form.reference_decision" class="w-full" placeholder="Ex: N° Décret / Arrêté ministériel..." />
                                        </div>
                                    </div>
                                </template>
                            </Card>

                            <!-- 7. CERTIFICATS MILITAIRES -->
                            <Card v-if="showFormationsSection" class="mt-6 shadow-sm border border-slate-100">
                                <template #title>
                                    <div class="flex items-center gap-2 text-sky-900 border-b pb-3 text-lg font-bold">
                                        <i class="pi pi-book text-sky-800"></i>
                                        <span>Certificats & Formations Militaires Obtenus</span>
                                    </div>
                                </template>

                                <template #content>
                                    <div v-if="filteredCertificats.length === 0" class="text-center py-4 text-gray-500">
                                        <i class="pi pi-info-circle text-2xl mb-2"></i>
                                        <p>Aucune formation répertoriée pour ce grade.</p>
                                    </div>

                                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                                        <div v-for="certificat in filteredCertificats" :key="certificat.id" class="border rounded-lg p-3 hover:border-sky-300 transition-all bg-slate-50">
                                            <div class="flex items-start gap-2">
                                                <Checkbox :id="'certif_' + certificat.id" v-model="form.certificats[certificat.id].obtenu" :binary="true" @change="onCertificatChange(certificat.id)" />
                                                <label :for="'certif_' + certificat.id" class="font-medium text-gray-700 text-sm cursor-pointer">
                                                    {{ certificat.nom_certificat }}
                                                    <Tag :value="certificat.niveau_certificat" :style="getNiveauStyle(certificat.niveau_certificat)" class="ml-1 text-xs" />
                                                </label>
                                            </div>

                                            <div class="mt-2">
                                                <label :for="'date_certif_' + certificat.id" class="block text-xs text-gray-600 mb-1">
                                                    Date d'obtention <span v-if="form.certificats[certificat.id].obtenu" class="text-red-500">*</span>
                                                </label>
                                                <DatePicker :id="'date_certif_' + certificat.id" v-model="form.certificats[certificat.id].date_obtention" dateFormat="dd/mm/yy" showIcon class="w-full" size="small" :disabled="!form.certificats[certificat.id].obtenu" :class="{ 'p-invalid': dateErrors[certificat.id] && form.certificats[certificat.id].obtenu && !form.certificats[certificat.id].date_obtention }" />
                                                <small v-if="dateErrors[certificat.id] && form.certificats[certificat.id].obtenu && !form.certificats[certificat.id].date_obtention" class="text-red-500 text-xs">
                                                    Date obligatoire
                                                </small>
                                            </div>

                                            <div class="mt-2">
                                                <label :for="'doc_' + certificat.id" class="block text-xs text-gray-600 mb-1">Document justificatif (optionnel)</label>
                                                <div class="flex items-center gap-2">
                                                    <FileUpload :id="'doc_' + certificat.id" mode="basic" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" chooseLabel="📎 Choisir" customUpload @select="(event) => onFileSelect(certificat.id, event)" :disabled="!form.certificats[certificat.id].obtenu" class="w-full" />
                                                    <Button v-if="form.certificats[certificat.id].document" icon="pi pi-times" class="p-button-rounded p-button-danger p-button-text" size="small" @click="removeDocument(certificat.id)" title="Supprimer le document" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </Card>

                            <!-- BOUTONS D'ACTION -->
                            <div class="flex justify-end gap-3 mt-8">
                                <Button label="Annuler" icon="pi pi-times" class="p-button-outlined p-button-secondary" @click="cancel" />
                                <Button type="submit" label="Enregistrer le militaire" icon="pi pi-check" :loading="saving" class="!bg-gradient-to-r !from-sky-800 !to-sky-700 hover:!from-sky-900 hover:!to-sky-800 border-0 text-white shadow-xs font-semibold px-4 py-2" />
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <Toast position="top-right" />
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import FileUpload from 'primevue/fileupload';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    grades: { type: Array, required: true },
    certificats: { type: Array, default: () => [] }
});

const toast = useToast();
const saving = ref(false);
const errors = ref({});
const dateErrors = ref({});
const docErrors = ref({});
const photoPreview = ref(null);
const photoFile = ref(null);

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
    dateErrors.value = {};
    let hasDateError = false;

    Object.keys(form.certificats).forEach(certifId => {
        if (form.certificats[certifId].obtenu && !form.certificats[certifId].date_obtention) {
            dateErrors.value[certifId] = true;
            hasDateError = true;
        }
    });

    if (hasDateError) {
        toast.add({
            severity: 'error',
            summary: 'Erreur',
            detail: 'Veuillez renseigner les dates d\'obtention pour les certificats cochés',
            life: 5000
        });
        return;
    }

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
                detail: 'Militaire ajouté avec succès',
                life: 3000
            });
            router.visit(route('militaires.index'));
        },
        onError: (err) => {
            saving.value = false;
            errors.value = err;
            toast.add({
                severity: 'error',
                summary: 'Erreur',
                detail: 'Veuillez corriger les erreurs du formulaire',
                life: 5000
            });
            const firstError = document.querySelector('.p-invalid');
            if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
};

const cancel = () => {
    router.visit(route('militaires.index'));
};
</script>
