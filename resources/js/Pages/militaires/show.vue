<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center">
                <h2 class="font-bold text-lg md:text-xl text-white tracking-wide truncate">
                    Fiche Individuelle : {{ militaire.grade_actuel }} {{ militaire.nom }} {{ militaire.prenom }}
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- BARRE D'ACTIONS RESPONSIVE -->
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-3.5 bg-white p-3.5 sm:p-4 rounded-xl shadow-xs border border-slate-200">
                    <div class="min-w-0 flex-1">
                        <h3 class="font-bold text-slate-800 text-sm sm:text-base truncate">{{ militaire.grade_actuel }} {{ militaire.nom }} {{ militaire.prenom }}</h3>
                        <p class="text-xs text-slate-500 font-mono truncate">Matricule FAMA : {{ militaire.matricule }} <span v-if="militaire.mle_grm">| MLE GRM : {{ militaire.mle_grm }}</span></p>
                    </div>
                    <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap w-full lg:w-auto shrink-0">
                        <button type="button" @click="openCertificatPresenceModal"
                            class="flex-1 sm:flex-none justify-center px-3 py-2 rounded-lg text-xs font-semibold !text-white bg-gradient-to-r from-sky-800 to-sky-700 hover:from-sky-900 hover:to-sky-800 transition-all flex items-center gap-1.5 shadow-xs cursor-pointer whitespace-nowrap">
                            <i class="pi pi-file-check text-xs !text-white"></i> <span>Certificat de présence</span>
                        </button>
                        <button type="button" @click="editMilitaire"
                            class="flex-1 sm:flex-none justify-center px-3 py-2 rounded-lg text-xs font-semibold !text-white bg-gradient-to-r from-sky-800 to-sky-700 hover:from-sky-900 hover:to-sky-800 transition-all flex items-center gap-1.5 shadow-xs cursor-pointer whitespace-nowrap">
                            <i class="pi pi-pencil text-xs !text-white"></i> <span>Modifier</span>
                        </button>
                        <button type="button" @click="confirmDelete"
                            class="flex-1 sm:flex-none justify-center px-3 py-2 rounded-lg text-xs font-semibold !text-white bg-red-600 hover:bg-red-700 transition-colors flex items-center gap-1.5 shadow-xs cursor-pointer whitespace-nowrap">
                            <i class="pi pi-trash text-xs !text-white"></i> <span>Supprimer</span>
                        </button>
                        <button type="button" @click="goBack"
                            class="flex-1 sm:flex-none justify-center px-3 py-2 rounded-lg text-xs font-semibold !text-white bg-slate-700 hover:bg-slate-800 transition-colors flex items-center gap-1.5 shadow-xs cursor-pointer whitespace-nowrap">
                            <i class="pi pi-arrow-left text-xs !text-white"></i> <span>Retour</span>
                        </button>
                    </div>
                </div>

                <!-- BANNIÈRE SPÉCIALE : CAS D'OTAGE / CAPTIVITÉ -->
                <div v-if="isOtage" class="mb-6 p-4 sm:p-5 bg-gradient-to-r from-red-50 to-amber-50 border-2 border-red-500 rounded-xl shadow-xs text-red-950 flex flex-col sm:flex-row items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-red-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                        <i class="pi pi-shield text-2xl animate-pulse"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            <span class="px-2.5 py-0.5 rounded bg-red-600 text-white text-xs font-black uppercase tracking-wider">Signalement Spécial</span>
                            <span class="text-base font-extrabold text-red-950 uppercase tracking-wide">Militaire en Captivité / Cas d'Otage</span>
                        </div>
                        <p class="text-xs sm:text-sm text-red-900 leading-relaxed font-medium">
                            Ce militaire fait l'objet d'un cas de capture / prise d'otage. Conformément aux dispositions statutaires des Forces Armées et aux directives du commandement, 
                            <strong>le militaire en otage reste impérativement comptabilisé à l'effectif en activité et maintenu au fichier jusqu'à ce qu'il soit retrouvé</strong>. Aucune radiation ni bascule en retraite d'office n'est appliquée.
                        </p>
                        <div v-if="militaire.observations" class="mt-2.5 p-2.5 bg-white/90 rounded-lg border border-red-200 text-xs text-red-900 font-semibold flex items-center gap-2 shadow-2xs">
                            <i class="pi pi-info-circle text-red-600 text-sm shrink-0"></i>
                            <span><strong>Description / Mention officielle :</strong> {{ militaire.observations }}</span>
                        </div>
                    </div>
                </div>

                <!-- 1. HERO BANNER - CARTE D'IDENTITÉ MILITAIRE -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-6 bg-gradient-to-r from-sky-800 via-sky-700 to-sky-800 text-white">
                        <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                            
                            <!-- PHOTO D'IDENTITÉ (FORMAT 3:4) -->
                            <div class="flex-shrink-0">
                                <div class="w-36 h-48 md:w-40 md:h-52 bg-slate-800 rounded-xl overflow-hidden shadow-xl border-4 border-white/80 flex items-center justify-center relative group">
                                    <img v-if="militaire.photo_url" :src="militaire.photo_url" :alt="militaire.nom"
                                        class="w-full h-full object-cover" />
                                    <div v-else class="flex flex-col items-center justify-center text-slate-300 p-4 text-center">
                                        <i class="pi pi-user text-5xl mb-2 opacity-60"></i>
                                        <span class="text-xs font-medium uppercase tracking-wider">Sans photo</span>
                                    </div>
                                </div>
                            </div>

                            <!-- INFORMATIONS CLÉS DANS LE HERO BANNER -->
                            <div class="flex-1 text-center md:text-left">
                                <div class="flex flex-wrap items-center justify-center md:justify-start gap-2 mb-2">
                                    <Tag :value="militaire.statut" :style="getStatutStyle(militaire.statut)" class="uppercase font-bold tracking-wider text-xs px-3 py-1" />
                                    <Tag v-if="isOtage" value="CAS D'OTAGE" style="background: #dc2626; color: white;" class="uppercase font-black tracking-wider text-xs px-3 py-1 shadow-sm animate-pulse" />
                                    <Tag v-if="militaire.categorie" :value="militaire.categorie" style="background: rgba(255,255,255,0.2); color: white;" class="font-medium text-xs px-2.5 py-1" />
                                    <Tag v-if="militaire.est_eligible_retraite && !isOtage" value="Retraite imminente" style="background: #f97316; color: white;" class="font-medium text-xs px-2.5 py-1" />
                                </div>

                                <h1 class="text-2xl md:text-3xl font-extrabold text-white tracking-tight mb-1">
                                    {{ militaire.grade_actuel }} {{ militaire.nom }} {{ militaire.prenom }}
                                </h1>

                                <p class="text-sky-200 text-sm mb-4 font-mono">
                                    Matricule FAMA : <span class="font-bold text-white">{{ militaire.matricule }}</span>
                                    <span v-if="militaire.mle_grm" class="ml-3">| MLE GRM : <span class="font-bold text-white">{{ militaire.mle_grm }}</span></span>
                                    <span v-if="militaire.numero_info" class="ml-3">| N° Solde : <span class="font-bold text-white">{{ militaire.numero_info }}</span></span>
                                </p>

                                <!-- TILES STATISTIQUES HERO -->
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 border-t border-sky-700/50">
                                    <div class="bg-white/10 backdrop-blur-xs rounded-lg p-2.5 text-center">
                                        <div class="text-[11px] uppercase tracking-wider text-sky-200 font-medium">Unité / Escadron</div>
                                        <div class="text-sm font-bold text-white truncate" :title="currentAffectation?.unite || militaire.unite || '-'">
                                            {{ currentAffectation?.unite || militaire.unite || '-' }}
                                        </div>
                                    </div>
                                    <div class="bg-white/10 backdrop-blur-xs rounded-lg p-2.5 text-center">
                                        <div class="text-[11px] uppercase tracking-wider text-sky-200 font-medium">Région</div>
                                        <div class="text-sm font-bold text-white truncate" :title="currentAffectation?.region || militaire.region || '-'">
                                            {{ currentAffectation?.region || militaire.region || '-' }}
                                        </div>
                                    </div>
                                    <div class="bg-white/10 backdrop-blur-xs rounded-lg p-2.5 text-center">
                                        <div class="text-[11px] uppercase tracking-wider text-sky-200 font-medium">Âge</div>
                                        <div class="text-sm font-bold text-white">
                                            {{ militaire.age ? militaire.age + ' ans' : '-' }}
                                        </div>
                                    </div>
                                    <div class="bg-white/10 backdrop-blur-xs rounded-lg p-2.5 text-center">
                                        <div class="text-[11px] uppercase tracking-wider text-sky-200 font-medium">Ancienneté</div>
                                        <div class="text-sm font-bold text-white">
                                            {{ formatAnciennete(militaire.anciennete) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. GRILLE DES SECTIONS DÉTAILLÉES -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- CARTE 1 : IDENTIFIANTS & ÉTAT CIVIL -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <div class="flex items-center gap-2 pb-3 mb-4 border-b border-slate-100 text-sky-900 font-bold text-base">
                            <i class="pi pi-id-card text-sky-800"></i>
                            <span>Identifiants & État Civil</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Matricule FAMA</span>
                                <span class="text-sm font-bold text-slate-800">{{ militaire.matricule }}</span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Matricule Gendarmerie (GRM)</span>
                                <span class="text-sm font-bold text-slate-800">{{ militaire.mle_grm || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">N° Informatique (Solde)</span>
                                <span class="text-sm font-medium text-slate-800">{{ militaire.numero_info || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">N° NINA</span>
                                <span class="text-sm font-medium text-slate-800">{{ militaire.numero_nina || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Date de naissance</span>
                                <span class="text-sm font-medium text-slate-800">
                                    {{ militaire.date_naissance || '-' }}
                                    <span v-if="militaire.age" class="text-xs text-slate-500">({{ militaire.age }} ans)</span>
                                </span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Lieu de naissance</span>
                                <span class="text-sm font-medium text-slate-800">{{ militaire.lieu_naissance || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Sexe</span>
                                <span class="text-sm font-medium text-slate-800">
                                    {{ militaire.sexe === 'M' ? 'Masculin (M)' : militaire.sexe === 'F' ? 'Féminin (F)' : (militaire.sexe || '-') }}
                                </span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Groupe sanguin</span>
                                <Tag :value="militaire.groupe_sanguin || 'Non précisé'" style="background: #fef2f2; color: #b91c1c;" class="font-bold text-xs" />
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Taille</span>
                                <span class="text-sm font-bold text-slate-800">{{ militaire.taille || '-' }}</span>
                            </div>
                            <div class="sm:col-span-2">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Téléphone personnel</span>
                                <span class="text-sm font-semibold text-sky-700">{{ militaire.telephone || '-' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- CARTE 2A : AFFECTATION & MAILLAGE TERRITORIAL -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
                            <div class="flex items-center gap-2 text-sky-900 font-bold text-base">
                                <i class="pi pi-building text-sky-800"></i>
                                <span>Affectation & Maillage Territorial</span>
                            </div>
                            <span class="text-xs text-slate-500 font-medium">
                                {{ affectations && affectations.length ? affectations.length + ' affectation(s)' : '1 affectation' }}
                            </span>
                        </div>

                        <div v-if="sortedAffectations && sortedAffectations.length" class="space-y-3">
                            <div v-for="(aff, idx) in sortedAffectations" :key="idx"
                                class="p-4 rounded-xl border border-slate-200 bg-slate-50/70 hover:bg-slate-50 transition-colors">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-full bg-sky-800 text-white flex items-center justify-center font-bold text-xs shrink-0 mt-0.5 shadow-2xs">
                                        {{ idx + 1 }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <!-- Nom de l'unité + Badge Actuelle -->
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-bold text-slate-900 text-sm leading-snug break-words">
                                                {{ aff.unite || 'Unité non renseignée' }}
                                            </span>
                                            <Tag v-if="idx === 0" value="Actuelle" style="background: #0284c7; color: white;" class="text-[10px] font-semibold shrink-0" />
                                        </div>

                                        <!-- Métadonnées : Région & Date de mutation -->
                                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-2.5 pt-2 border-t border-slate-200/60 text-xs">
                                            <div class="flex items-center gap-1.5 text-slate-600">
                                                <i class="pi pi-map-marker text-sky-600 text-xs shrink-0"></i>
                                                <span class="text-slate-500">Région :</span>
                                                <span class="font-semibold text-slate-800">{{ aff.region || '-' }}</span>
                                            </div>
                                            <div class="flex items-center gap-1.5 text-slate-600 bg-white px-2.5 py-1 rounded-md border border-slate-200/80 shadow-2xs">
                                                <i class="pi pi-calendar text-sky-600 text-xs shrink-0"></i>
                                                <span class="text-slate-500 text-[11px]">Dernière mutation :</span>
                                                <span class="font-bold text-slate-800 text-[11px]">{{ aff.date_mutation || '-' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div class="min-w-0">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-1">Unité / Brigade / Escadron</span>
                                <span class="text-sm font-bold text-slate-800 break-words">{{ militaire.unite || '-' }}</span>
                            </div>
                            <div class="min-w-0">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-1">Région de Gendarmerie</span>
                                <span class="text-sm font-medium text-slate-800 break-words">{{ militaire.region || '-' }}</span>
                            </div>
                            <div class="min-w-0">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-1">Date de dernière mutation</span>
                                <span class="text-sm font-medium text-slate-800">{{ militaire.date_mutation || '-' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- CARTE 2B : MAILLAGE & CARRIÈRE -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <div class="flex items-center gap-2 pb-3 mb-4 border-b border-slate-100 text-sky-900 font-bold text-base">
                            <i class="pi pi-briefcase text-sky-800"></i>
                            <span>Maillage & Carrière</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Date d'entrée en service</span>
                                <span class="text-sm font-medium text-slate-800">
                                    {{ militaire.date_entree_service || '-' }}
                                    <span v-if="militaire.anciennete" class="text-xs text-slate-500">({{ formatAnciennete(militaire.anciennete) }})</span>
                                </span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Date dernière promotion</span>
                                <span class="text-sm font-medium text-slate-800">
                                    {{ militaire.date_derniere_promotion || '-' }}
                                    <span v-if="militaire.anciennete_grade" class="text-xs text-slate-500">({{ formatAnciennete(militaire.anciennete_grade) }})</span>
                                </span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Origine / École de formation</span>
                                <span class="text-sm font-medium text-slate-800">{{ militaire.origine_formation || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Fonction actuelle</span>
                                <span class="text-sm font-medium text-slate-800">{{ militaire.fonction_actuelle || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Fonction passée</span>
                                <span class="text-sm font-medium text-slate-800">{{ militaire.fonction_passee || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Spécialité</span>
                                <span class="text-sm font-medium text-slate-800">{{ militaire.specialite || '-' }}</span>
                            </div>
                            <div class="sm:col-span-2">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Position administrative</span>
                                <span class="text-sm font-medium text-slate-800">{{ militaire.position_actuelle || '-' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- CARTE 3 : SANTÉ & ENGAGEMENT OPÉRATIONNEL (OPS) -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <div class="flex items-center gap-2 pb-3 mb-4 border-b border-slate-100 text-sky-900 font-bold text-base">
                            <i class="pi pi-shield text-sky-800"></i>
                            <span>Aptitude Médicale & Engagement OPS</span>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-1">Status Sanitaire / Aptitude</span>
                                <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-sm font-medium text-slate-800">
                                    <i class="pi pi-heart-fill text-rose-500 mr-2"></i>
                                    {{ militaire.status_sanitaire || 'Apte au service (par défaut)' }}
                                </div>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-1">Engagements Opérationnels (OPS)</span>
                                <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-sm font-medium text-slate-800">
                                    <i class="pi pi-flag-fill text-amber-500 mr-2"></i>
                                    {{ militaire.engagement_ops || 'Aucun engagement OPS renseigné' }}
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                <div>
                                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-1">Antécédent disciplinaire</span>
                                    <Tag :value="militaire.antecedent_disciplinaire ? 'Oui' : 'Non'"
                                        :style="militaire.antecedent_disciplinaire ? { background: '#fee2e2', color: '#991b1b' } : { background: '#dcfce7', color: '#166534' }" />
                                </div>
                                <div>
                                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-1">Antécédent judiciaire</span>
                                    <Tag :value="militaire.antecedent_judiciaire ? 'Oui' : 'Non'"
                                        :style="militaire.antecedent_judiciaire ? { background: '#fee2e2', color: '#991b1b' } : { background: '#dcfce7', color: '#166534' }" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CARTE 4 : DÉCORATIONS & DIPLÔMES ACADÉMIQUES -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <div class="flex items-center gap-2 pb-3 mb-4 border-b border-slate-100 text-sky-900 font-bold text-base">
                            <i class="pi pi-star text-sky-800"></i>
                            <span>Décorations & Qualifications Académiques</span>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-1.5">Décorations Militaires</span>
                                <div v-if="decorationsList.length > 0" class="flex flex-wrap gap-2">
                                    <span v-for="(dec, idx) in decorationsList" :key="idx"
                                          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-50 text-amber-900 border border-amber-200/80 text-xs font-semibold shadow-2xs">
                                        <i class="pi pi-award text-amber-600 text-sm"></i>
                                        <span>{{ dec }}</span>
                                    </span>
                                </div>
                                <div v-else class="p-3 bg-amber-50/40 rounded-lg border border-amber-100 text-xs text-slate-500 italic">
                                    Aucune décoration enregistrée
                                </div>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-1.5">Diplômes Académiques (Civils / Universitaires)</span>
                                <div v-if="diplomesList.length > 0" class="flex flex-wrap gap-2">
                                    <span v-for="(dip, idx) in diplomesList" :key="idx"
                                          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-sky-50 text-sky-900 border border-sky-200/80 text-xs font-semibold shadow-2xs">
                                        <i class="pi pi-graduation-cap text-sky-700 text-sm"></i>
                                        <span>{{ dip }}</span>
                                    </span>
                                </div>
                                <div v-else class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs text-slate-500 italic">
                                    Aucun diplôme civil renseigné
                                </div>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-1">Observations générales</span>
                                <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-sm text-slate-700 italic">
                                    {{ militaire.observations || 'Aucune observation enregistrée' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CARTE 5 : CONTACT D'URGENCE -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <div class="flex items-center gap-2 pb-3 mb-4 border-b border-slate-100 text-sky-900 font-bold text-base">
                            <i class="pi pi-users text-sky-800"></i>
                            <span>Personne à Contacter en Cas d'Urgence</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Nom complet</span>
                                <span class="text-sm font-bold text-slate-800">{{ militaire.personne_a_contacter || 'Non renseigné' }}</span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Numéro de téléphone</span>
                                <span class="text-sm font-bold text-sky-800">{{ militaire.telephone_personne_contacter || 'Non renseigné' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- CARTE 6 : SITUATION EXCEPTIONNELLE (SI APPLICABLE) -->
                    <div v-if="militaire.date_deces || militaire.motif_radiation || militaire.unite_transfert" 
                         class="bg-white rounded-xl shadow-sm border border-red-200 p-5">
                        <div class="flex items-center gap-2 pb-3 mb-4 border-b border-red-100 text-red-700 font-bold text-base">
                            <i class="pi pi-exclamation-circle text-red-600"></i>
                            <span>Événements Majeurs / Radiation / Décès</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <div v-if="militaire.date_deces">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Date du décès</span>
                                <span class="font-bold text-red-600">{{ militaire.date_deces }}</span>
                            </div>
                            <div v-if="militaire.circonstances_deces">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Circonstances du décès</span>
                                <span class="text-slate-800">{{ militaire.circonstances_deces }}</span>
                            </div>
                            <div v-if="militaire.motif_radiation">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Motif de radiation</span>
                                <span class="font-bold text-red-700">{{ militaire.motif_radiation }}</span>
                            </div>
                            <div v-if="militaire.reference_decision">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Réf. décision</span>
                                <span class="text-slate-800 font-mono">{{ militaire.reference_decision }}</span>
                            </div>
                            <div v-if="militaire.unite_transfert">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Unité de transfert</span>
                                <span class="text-slate-800">{{ militaire.unite_transfert }}</span>
                            </div>
                            <div v-if="militaire.motif_transfert">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide block">Motif transfert</span>
                                <span class="text-slate-800">{{ militaire.motif_transfert }}</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- 3. CERTIFICATS MILITAIRES -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-5 border-b border-slate-200 bg-slate-50/60 flex items-center justify-between">
                        <div class="flex items-center gap-2 text-sky-900 font-bold text-base">
                            <i class="pi pi-verified text-sky-800"></i>
                            <span>Certificats & Brevets Militaires Obtenus</span>
                        </div>
                        <Tag :value="certificats.length + ' certificat(s)'" style="background: #e0f2fe; color: #075985;" class="text-xs font-medium" />
                    </div>

                    <div class="p-5">
                        <div v-if="certificats.length === 0" class="text-center py-8 text-gray-500">
                            <i class="pi pi-file-edit text-3xl mb-2 text-slate-400"></i>
                            <p class="text-sm">Aucun certificat enregistré pour ce militaire.</p>
                        </div>

                        <DataTable v-else :value="certificats" stripedRows responsiveLayout="scroll" class="p-datatable-sm">
                            <Column field="nom_certificat" header="Certificat" style="min-width: 200px;">
                                <template #body="slotProps">
                                    <div class="font-bold text-slate-800">{{ slotProps.data.nom_certificat }}</div>
                                </template>
                            </Column>
                            <Column field="niveau_certificat" header="Niveau" style="min-width: 120px;">
                                <template #body="slotProps">
                                    <Tag :value="slotProps.data.niveau_certificat || '-'" style="background: #bae6fd; color: #0369a1;" />
                                </template>
                            </Column>
                            <Column field="date_obtention" header="Date d'obtention" style="min-width: 140px;">
                                <template #body="slotProps">
                                    <span class="text-sm">{{ slotProps.data.date_obtention ?? '-' }}</span>
                                </template>
                            </Column>
                            <Column header="Document" style="min-width: 160px;">
                                <template #body="slotProps">
                                    <div v-if="slotProps.data.document_id">
                                        <a :href="route('certificats.document.download', slotProps.data.document_id)"
                                            target="_blank"
                                            class="text-sky-600 hover:text-sky-800 inline-flex items-center gap-2 px-3 py-1.5 bg-sky-50 rounded-lg hover:bg-sky-100 transition-all text-xs font-semibold">
                                            <i class="pi pi-file-pdf text-red-500 text-sm"></i>
                                            <span class="truncate max-w-[140px]">{{ slotProps.data.document_nom || 'Télécharger' }}</span>
                                            <i class="pi pi-download ml-auto text-xs"></i>
                                        </a>
                                    </div>
                                    <span v-else class="text-gray-400 text-xs italic">Aucune pièce jointe</span>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>

                <!-- 4. ALERTES ASSOCIÉES -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-5 border-b border-slate-200 bg-slate-50/60 flex items-center justify-between">
                        <div class="flex items-center gap-2 text-amber-700 font-bold text-base">
                            <i class="pi pi-bell text-amber-500"></i>
                            <span>Alertes & Échéances Associées</span>
                        </div>
                        <Tag :value="alertes.length + ' alerte(s)'" :style="alertes.length > 0 ? { background: '#ffedd5', color: '#c2410c' } : { background: '#f1f5f9', color: '#64748b' }" class="text-xs font-medium" />
                    </div>

                    <div class="p-5">
                        <div v-if="alertes.length === 0" class="text-center py-6 text-gray-500">
                            <i class="pi pi-check-circle text-3xl mb-2 text-emerald-500"></i>
                            <p class="text-sm">Aucune alerte active pour ce militaire.</p>
                        </div>

                        <DataTable v-else :value="alertes" stripedRows responsiveLayout="scroll" class="p-datatable-sm">
                            <Column field="type_alerte" header="Type" style="min-width: 120px;">
                                <template #body="slotProps">
                                    <Tag :value="getTypeLabel(slotProps.data.type_alerte)"
                                        :style="getAlerteStyle(slotProps.data.type_alerte)" />
                                </template>
                            </Column>
                            <Column field="message" header="Message" style="min-width: 250px;">
                                <template #body="slotProps">
                                    <span class="text-sm text-slate-800">{{ slotProps.data.message }}</span>
                                </template>
                            </Column>
                            <Column field="date_echeance" header="Échéance" style="min-width: 130px;">
                                <template #body="slotProps">
                                    <span class="text-sm font-medium">{{ slotProps.data.date_echeance ?? '-' }}</span>
                                </template>
                            </Column>
                            <Column field="est_vue" header="Statut" style="min-width: 110px;">
                                <template #body="slotProps">
                                    <Tag :value="slotProps.data.est_vue ? 'Vue' : 'Non vue'"
                                        :style="slotProps.data.est_vue ? { background: '#e0f2fe', color: '#0369a1' } : { background: '#fed7aa', color: '#c2410c' }" />
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>

            </div>
        </div>

        <!-- Dialog confirmation suppression -->
        <Dialog v-model:visible="deleteDialogVisible" header="Confirmer la suppression" :modal="true"
            :style="{ width: '90%', maxWidth: '420px' }" class="p-fluid">
            <div class="flex items-center gap-3 mb-4">
                <i class="pi pi-exclamation-triangle text-3xl text-amber-500"></i>
                <p class="text-gray-700 text-sm">
                    Êtes-vous sûr de vouloir supprimer définitivement le militaire 
                    <strong>{{ militaire.nom }} {{ militaire.prenom }}</strong> (Matricule : {{ militaire.matricule }}) ?
                </p>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Annuler" icon="pi pi-times" class="p-button-text text-gray-500 hover:text-gray-700"
                        @click="deleteDialogVisible = false" />
                    <Button label="Supprimer" icon="pi pi-trash"
                        style="background-color: #ef4444; border-color: #ef4444; color: white;"
                        @click="deleteMilitaire" />
                </div>
            </template>
        </Dialog>

        <!-- DIALOGUE OFFICIEL : CERTIFICAT DE PRESENCE AU CORPS -->
        <Dialog v-model:visible="certificatModalVisible" modal header="Certificat de Présence au Corps (Gendarmerie Nationale)" 
            :style="{ width: '850px' }" :breakpoints="{ '960px': '95vw' }" class="p-fluid">
            
            <!-- Contrôles d'édition du certificat (Personnalisation du signataire et du motif) -->
            <div class="no-print bg-slate-50 p-4 rounded-xl mb-4 border border-slate-200 text-xs space-y-3">
                <div class="font-bold text-sky-950 uppercase tracking-wide flex items-center gap-1.5 pb-2 border-b border-slate-200">
                    <i class="pi pi-user-edit text-sky-700"></i>
                    <span>Personnalisation du signataire & des références</span>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Autorité signataire (Nom & Grade) :</label>
                        <input type="text" v-model="autoriteSignataire" 
                            placeholder="Ex: Colonel Moussa Ousmane DAO"
                            class="w-full text-xs p-2 border border-slate-300 rounded-lg bg-white text-slate-800 font-semibold focus:border-sky-500" />
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Titre / Fonction signataire :</label>
                        <input type="text" v-model="titreSignataire" 
                            placeholder="Ex: Sous-directeur des Ressources Humaines"
                            class="w-full text-xs p-2 border border-slate-300 rounded-lg bg-white text-slate-800 focus:border-sky-500" />
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Décoration / Distinction :</label>
                        <input type="text" v-model="decorationSignataire" 
                            placeholder="Ex: Chevalier de l'Ordre National"
                            class="w-full text-xs p-2 border border-slate-300 rounded-lg bg-white text-slate-800 focus:border-sky-500" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                    <div class="sm:col-span-2">
                        <label class="font-bold text-slate-700 block mb-1">Motif de délivrance :</label>
                        <input type="text" v-model="motifCertificat" 
                            class="w-full text-xs p-2 border border-slate-300 rounded-lg bg-white text-slate-800 focus:border-sky-500" />
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Réf. Numéro officiel :</label>
                        <input type="text" v-model="numeroCertificat" 
                            class="w-full text-xs p-2 border border-slate-300 rounded-lg bg-white font-mono text-slate-800 focus:border-sky-500" />
                    </div>
                </div>
            </div>

            <!-- DOCUMENT IMPRIMABLE A4 (FORMAT OFFICIEL IMAGE 3 - STRICTEMENT 1 PAGE) -->
            <div id="certificat-presence-corps" 
                class="bg-white p-8 md:p-12 text-black font-serif text-sm leading-relaxed border border-slate-300 shadow-sm rounded-sm max-w-[210mm] mx-auto">
                
                <!-- ENTÊTE GOUVERNEMENTALE -->
                <div class="flex justify-between items-start mb-6 text-xs md:text-sm">
                    <!-- GAUCHE -->
                    <div class="text-center font-bold tracking-tight uppercase space-y-0.5">
                        <div>Ministère de la Défense et<br>des Anciens Combattants</div>
                        <div class="tracking-widest text-[10px]">***************</div>
                        <div>Gendarmerie Nationale</div>
                        <div class="tracking-widest text-[10px]">***********</div>
                        <div>Direction Générale</div>
                        <div class="pt-4 text-left font-mono font-bold text-xs">
                            N° {{ numeroCertificat }}
                        </div>
                    </div>

                    <!-- DROITE -->
                    <div class="text-center font-bold uppercase space-y-0.5">
                        <div class="tracking-wide">République du Mali</div>
                        <div class="text-xs normal-case italic font-semibold">Un Peuple – Un But – Une Foi</div>
                        <div class="tracking-widest text-[10px]">***************</div>
                        <div class="pt-3 font-normal normal-case text-right text-xs">
                            Bamako, le <span class="font-semibold">{{ dateCertificat }}</span>
                        </div>
                    </div>
                </div>

                <!-- GRAND TITRE DU CERTIFICAT -->
                <div class="text-center my-6 md:my-8">
                    <h1 class="text-xl md:text-2xl font-black uppercase tracking-wider underline underline-offset-8 decoration-2 inline-block">
                        Certificat de Présence au Corps
                    </h1>
                </div>

                <!-- CORPS DU TEXTE OFFICIEL -->
                <div class="text-justify my-6 md:my-8 leading-loose text-base indent-8">
                    Nous, soussignés, <strong class="font-bold">{{ autoriteSignataire }}</strong>, 
                    {{ titreSignataire }} de la Gendarmerie Nationale, certifions que l'<strong>{{ militaire.grade_actuel }} {{ militaire.prenom }} {{ militaire.nom }}</strong> 
                    matricule <strong class="font-bold font-mono">{{ militaire.matricule }}</strong><span v-if="militaire.mle_grm"> (MLE GRM : {{ militaire.mle_grm }})</span>, 
                    est en service à la Gendarmerie nationale.
                </div>

                <!-- MOTIF -->
                <div class="my-6 md:my-8 text-sm md:text-base">
                    <strong class="underline font-bold">MOTIF</strong> : {{ motifCertificat }}
                </div>

                <!-- BLOC SIGNATURE & CACHET EN BAS À DROITE (SIGNATURE ET CACHET MANUELS) -->
                <div class="mt-8 md:mt-12 flex justify-end">
                    <div class="text-center w-72 space-y-1">
                        <div class="font-bold text-sm">
                            Le {{ titreSignataire }}
                        </div>

                        <!-- ESPACE RESERVE POUR SIGNATURE MANUELLE ET CACHET OFFICIEL -->
                        <div class="h-28 flex items-center justify-center my-1">
                            <span class="text-[11px] text-slate-300 italic no-print select-none">
                                (Emplacement Signature manuelle & Cachet)
                            </span>
                        </div>

                        <div class="font-bold text-sm text-slate-900 underline decoration-1 underline-offset-2">
                            {{ autoriteSignataire }}
                        </div>
                        <div class="text-xs italic text-slate-700">
                            {{ decorationSignataire }}
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-between items-center w-full no-print pt-3 border-t">
                    <Button label="Fermer" icon="pi pi-times" class="p-button-text p-button-sm text-slate-600"
                        @click="certificatModalVisible = false" />
                    <Button label="Imprimer le certificat (1 page)" icon="pi pi-print"
                        class="p-button-sm !bg-gradient-to-r !from-sky-800 !to-sky-700 hover:!from-sky-900 hover:!to-sky-800 !text-white !border-0 font-semibold shadow-xs"
                        @click="printCertificat" />
                </div>
            </template>
        </Dialog>

        <Toast position="top-right" />
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    militaire: { type: Object, required: true },
    affectations: { type: Array, default: () => [] },
    certificats: { type: Array, default: () => [] },
    alertes: { type: Array, default: () => [] },
    contratActif: { type: Object, default: null }
});

// Helper pour parser les listes JSON ou texte avec dédoublonnage strict
const parseList = (val) => {
    if (!val) return [];
    if (Array.isArray(val)) {
        const seen = new Set();
        return val.map(s => String(s).trim()).filter(s => {
            if (!s || s === '#N/A' || s === 'N/A') return false;
            const k = s.toLowerCase();
            if (seen.has(k)) return false;
            seen.add(k);
            return true;
        });
    }
    if (typeof val === 'string') {
        const trimmed = val.trim();
        if (trimmed === '#N/A' || trimmed === 'N/A' || trimmed === 'null' || trimmed === '' || trimmed === '-') return [];
        if (trimmed.startsWith('[') && trimmed.endsWith(']')) {
            try {
                const parsed = JSON.parse(trimmed);
                if (Array.isArray(parsed)) {
                    return parseList(parsed);
                }
            } catch (e) {}
        }
        // Découpage intelligent par point-virgule ou retours à la ligne
        const rawParts = trimmed.split(/[;\n]+/);
        const seen = new Set();
        const result = [];

        for (const p of rawParts) {
            const clean = p.replace(/\s+/g, ' ').replace(/^[-•*]\s*/, '').replace(/,+$/, '').trim();
            if (!clean || clean === '#N/A' || clean === 'N/A' || clean === '-') continue;
            const key = clean.toLowerCase();
            if (!seen.has(key)) {
                seen.add(key);
                result.push(clean);
            }
        }
        return result;
    }
    return [];
};

const decorationsList = computed(() => parseList(props.militaire.decorations));
const diplomesList = computed(() => parseList(props.militaire.diplomes_academiques));

const isOtage = computed(() => {
    if (props.militaire?.is_otage) return true;
    const pos = (props.militaire?.position_actuelle || '').toLowerCase();
    const obs = (props.militaire?.observations || '').toLowerCase();
    return pos.includes('otage') || obs.includes('otage');
});

// Trier les affectations de la plus récente à la plus ancienne (l'actuelle est toujours la plus récente)
const sortedAffectations = computed(() => {
    if (!props.affectations || !props.affectations.length) return [];
    return [...props.affectations].sort((a, b) => {
        const dateA = a.date_mutation_raw ? new Date(a.date_mutation_raw).getTime() : 0;
        const dateB = b.date_mutation_raw ? new Date(b.date_mutation_raw).getTime() : 0;
        return dateB - dateA;
    });
});

const currentAffectation = computed(() => {
    return sortedAffectations.value[0] || null;
});

const toast = useToast();
const deleteDialogVisible = ref(false);
const certificatModalVisible = ref(false);

const motifCertificat = ref("Constitution d'un Dossier Administratif.");
const numeroCertificat = ref("3721/2 GRM-SDRH-DAGP.");
const dateCertificat = ref(new Date().toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' }));
const autoriteSignataire = ref("Colonel Moussa Ousmane DAO");
const titreSignataire = ref("Sous-directeur des Ressources Humaines");
const decorationSignataire = ref("Chevalier de l'Ordre National");

const escapeHtml = (val) => {
    if (val === null || val === undefined) return '';
    return String(val)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

const openCertificatPresenceModal = () => {
    certificatModalVisible.value = true;
};

const printCertificat = () => {
    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    document.body.appendChild(iframe);

    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(`
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="utf-8">
            <title>Certificat de Présence au Corps</title>
            <style>
                @page {
                    size: A4 portrait;
                    margin: 14mm 20mm 12mm 20mm;
                }
                * {
                    box-sizing: border-box;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                html, body {
                    margin: 0;
                    padding: 0;
                    background: #ffffff;
                    color: #000000;
                    font-family: 'Times New Roman', Times, serif;
                    font-size: 12pt;
                    line-height: 1.5;
                }
                .certificat-container {
                    width: 100%;
                    max-width: 175mm;
                    margin: 0 auto;
                    page-break-inside: avoid;
                    break-inside: avoid;
                    max-height: 265mm;
                }
                .header-flex {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    font-size: 10.5pt;
                    line-height: 1.35;
                    margin-bottom: 12mm;
                }
                .header-left {
                    text-align: center;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                .header-left .stars {
                    font-size: 9pt;
                    letter-spacing: 2px;
                    margin: 2px 0;
                }
                .header-left .num-ref {
                    margin-top: 10px;
                    text-align: left;
                    font-family: 'Courier New', monospace;
                    font-weight: bold;
                    font-size: 10pt;
                }
                .header-right {
                    text-align: center;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                .header-right .devise {
                    font-size: 9.5pt;
                    font-style: italic;
                    text-transform: none;
                    margin-top: 2px;
                }
                .header-right .stars {
                    font-size: 9pt;
                    letter-spacing: 2px;
                    margin: 2px 0;
                }
                .header-right .date-loc {
                    margin-top: 10px;
                    text-align: right;
                    font-weight: normal;
                    text-transform: none;
                    font-size: 10.5pt;
                }
                .cert-title {
                    text-align: center;
                    margin: 10mm 0 12mm 0;
                }
                .cert-title h1 {
                    font-size: 16pt;
                    font-weight: 900;
                    text-transform: uppercase;
                    text-decoration: underline;
                    letter-spacing: 0.8px;
                    margin: 0;
                }
                .cert-body {
                    text-align: justify;
                    font-size: 12pt;
                    line-height: 1.75;
                    text-indent: 10mm;
                    margin: 8mm 0 8mm 0;
                }
                .cert-motif {
                    font-size: 12pt;
                    line-height: 1.6;
                    margin: 8mm 0 10mm 0;
                }
                .cert-motif strong {
                    text-decoration: underline;
                }
                .sign-row {
                    display: flex;
                    justify-content: flex-end;
                    margin-top: 8mm;
                }
                .sign-box {
                    width: 80mm;
                    text-align: center;
                }
                .sign-title {
                    font-weight: bold;
                    font-size: 11pt;
                    margin-bottom: 20mm;
                }
                .sign-name {
                    font-weight: bold;
                    font-size: 11.5pt;
                    text-decoration: underline;
                    margin-bottom: 2mm;
                }
                .sign-distinction {
                    font-size: 10pt;
                    font-style: italic;
                    color: #222;
                }
                .no-print {
                    display: none !important;
                }
            </style>
        </head>
        <body>
            <div class="certificat-container">
                <div class="header-flex">
                    <div class="header-left">
                        <div>Ministère de la Défense et<br>des Anciens Combattants</div>
                        <div class="stars">***************</div>
                        <div>Gendarmerie Nationale</div>
                        <div class="stars">***********</div>
                        <div>Direction Générale</div>
                        <div class="num-ref">N° ${escapeHtml(numeroCertificat.value)}</div>
                    </div>
                    <div class="header-right">
                        <div>République du Mali</div>
                        <div class="devise">Un Peuple – Un But – Une Foi</div>
                        <div class="stars">***************</div>
                        <div class="date-loc">Bamako, le <strong>${escapeHtml(dateCertificat.value)}</strong></div>
                    </div>
                </div>

                <div class="cert-title">
                    <h1>Certificat de Présence au Corps</h1>
                </div>

                <div class="cert-body">
                    Nous, soussignés, <strong>${escapeHtml(autoriteSignataire.value)}</strong>, 
                    ${escapeHtml(titreSignataire.value)} de la Gendarmerie Nationale, certifions que l'<strong>${escapeHtml(props.militaire.grade_actuel || '')} ${escapeHtml(props.militaire.prenom || '')} ${escapeHtml(props.militaire.nom || '')}</strong> 
                    matricule <strong>${escapeHtml(props.militaire.matricule || '')}</strong>${props.militaire.mle_grm ? ' (MLE GRM : ' + escapeHtml(props.militaire.mle_grm) + ')' : ''}, 
                    est en service à la Gendarmerie nationale.
                </div>

                <div class="cert-motif">
                    <strong>MOTIF</strong> : ${escapeHtml(motifCertificat.value)}
                </div>

                <div class="sign-row">
                    <div class="sign-box">
                        <div class="sign-title">Le ${escapeHtml(titreSignataire.value)}</div>
                        <div class="sign-name">${escapeHtml(autoriteSignataire.value)}</div>
                        <div class="sign-distinction">${escapeHtml(decorationSignataire.value)}</div>
                    </div>
                </div>
            </div>
        </body>
        </html>
    `);
    doc.close();

    iframe.contentWindow.focus();
    setTimeout(() => {
        iframe.contentWindow.print();
        setTimeout(() => {
            if (document.body.contains(iframe)) {
                document.body.removeChild(iframe);
            }
        }, 1500);
    }, 400);
};

const getStatutStyle = (statut) => {
    const s = (statut || '').toLowerCase();
    if (s.includes('actif')) return { background: '#16a34a', color: '#ffffff' };
    if (s.includes('détach') || s.includes('detach')) return { background: '#0284c7', color: '#ffffff' };
    if (s.includes('stage') || s.includes('format')) return { background: '#d97706', color: '#ffffff' };
    if (s.includes('miss')) return { background: '#7c3aed', color: '#ffffff' };
    if (s.includes('retrait')) return { background: '#64748b', color: '#ffffff' };
    if (s.includes('d_ser') || s.includes('deser')) return { background: '#dc2626', color: '#ffffff' };
    if (s.includes('d_c_d') || s.includes('mort')) return { background: '#1e293b', color: '#ffffff' };
    return { background: '#64748b', color: '#ffffff' };
};

const getAlerteStyle = (type) => {
    const styles = {
        'promotion': { background: '#bae6fd', color: '#0369a1' },
        'formation': { background: '#fed7aa', color: '#c2410c' },
        'retraite': { background: '#fecaca', color: '#991b1b' },
        'contrat': { background: '#dbeafe', color: '#1e40af' }
    };
    return styles[type] || { background: '#e5e7eb', color: '#374151' };
};

const getTypeLabel = (type) => {
    const labels = {
        'promotion': 'Promotion',
        'formation': 'Formation',
        'retraite': 'Retraite',
        'contrat': 'Contrat'
    };
    return labels[type] || type;
};

const formatAnciennete = (annees) => {
    if (!annees && annees !== 0) return '0 an';
    return `${Math.floor(annees)} ans`;
};

const editMilitaire = () => {
    router.visit(route('militaires.edit', props.militaire.id));
};

const goBack = () => {
    router.visit(route('militaires.index'));
};

const confirmDelete = () => {
    deleteDialogVisible.value = true;
};

const deleteMilitaire = () => {
    router.delete(route('militaires.destroy', props.militaire.id), {
        onSuccess: () => {
            deleteDialogVisible.value = false;
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Militaire supprimé avec succès', life: 3000 });
            router.visit(route('militaires.index'));
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de supprimer le militaire', life: 3000 });
        }
    });
};
</script>

<style scoped>
:deep(.p-tag) {
    border-radius: 0.375rem;
    font-weight: 600;
}

:deep(.p-datatable-sm .p-datatable-thead > tr > th) {
    background-color: #f8fafc;
    color: #334155;
    font-weight: 600;
    padding: 0.65rem 0.75rem;
}

:deep(.p-datatable-sm .p-datatable-tbody > tr > td) {
    padding: 0.65rem 0.75rem;
}

@media print {
    .no-print {
        display: none !important;
    }
}
</style>
