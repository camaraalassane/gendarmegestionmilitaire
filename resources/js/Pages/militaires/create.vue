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
    organismes_detachement: { type: Array, default: () => [] },
});

const localRegions = ref([...(props.regions || [])]);
const localUnites = ref([...(props.unites || [])]);
const localPositions = ref([...(props.positions || [])]);
const localOrganismes = ref([...(props.organismes_detachement || [])]);

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
        // Validation situation de détachement vs affectations territoriales
        if (isDetache.value) {
            if (!form.organisme_detachement_id) {
                isValid = false;
                toast.add({
                    severity: 'warn',
                    summary: 'Organisme de détachement requis',
                    detail: 'Veuillez sélectionner l\'Organisme de Détachement (SPA) d\'accueil.',
                    life: 5000
                });
            }
        } else if (!isPositionSpeciale.value && form.affectations && form.affectations.length > 0) {
            // Vérifier que chaque affectation ayant une région possède obligatoirement son unité (uniquement pour les militaires réguliers en unité/poste)
            for (let i = 0; i < form.affectations.length; i++) {
                const aff = form.affectations[i];
                if (aff.region_id && !aff.unite_id) {
                    isValid = false;
                    toast.add({
                        severity: 'warn',
                        summary: 'Unité requise pour la région',
                        detail: `Veuillez sélectionner une unité d'affectation pour la région choisie (affectation n°${i + 1}).`,
                        life: 5000
                    });
                    break;
                }
            }
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
    { label: 'Actif (En service régulier)', value: 'actif' },
    { label: 'Procédure en cours', value: 'procédure en cours' },
    { label: 'Déserteur', value: 'déserteur' },
    { label: 'Retraité', value: 'retraité' },
    { label: 'Radié', value: 'radié' },
    { label: 'Décédé', value: 'décédé' }
];

const organismesDetachementOptions = computed(() => [
    { label: '-- Sélectionner un organisme (ex: DOCS, ANSE, MJGS...) --', value: null },
    ...localOrganismes.value.map(o => ({
        label: `[${o.code}] ${o.nom}`,
        value: o.id,
        code: o.code,
        nom: o.nom
    }))
]);

const getOrganismeCode = (id) => {
    const org = localOrganismes.value.find(o => o.id === id);
    return org ? org.code : '';
};

const getOrganismeNom = (id) => {
    const org = localOrganismes.value.find(o => o.id === id);
    return org ? org.nom : '';
};

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

const defaultStatusSanitaire = [
    'Apte',
    'Blessé de guerre',
    'Inapte temporaire',
    'Inapte définitif',
    'Apte avec restrictions',
    'En convalescence',
    'Réformé',
    'Exempté de service'
];

const sanitizeStatusLabel = (str) => {
    if (!str) return '';
    const clean = String(str).replace(/\s+/g, ' ').trim();
    if (clean.length > 55) {
        return clean.substring(0, 52) + '...';
    }
    return clean;
};

const loadStatusSanitaireList = () => {
    try {
        const saved = localStorage.getItem('status_sanitaire_options_v4');
        if (saved) {
            const parsed = JSON.parse(saved);
            if (Array.isArray(parsed) && parsed.length > 0) {
                const cleaned = parsed.map(s => String(s).replace(/\s+/g, ' ').trim()).filter(Boolean);
                return Array.from(new Set(cleaned));
            }
        }
    } catch (e) {}
    return defaultStatusSanitaire;
};

const saveStatusSanitaireList = (list) => {
    try {
        localStorage.setItem('status_sanitaire_options_v4', JSON.stringify(list));
    } catch (e) {}
};

const statusSanitaireOptions = ref(
    loadStatusSanitaireList().map(s => ({
        label: sanitizeStatusLabel(s),
        value: String(s).replace(/\s+/g, ' ').trim(),
        fullLabel: String(s).replace(/\s+/g, ' ').trim()
    }))
);

const showAddStatus = ref(false);
const newStatusInput = ref('');

const toggleAddStatus = () => {
    showAddStatus.value = !showAddStatus.value;
    newStatusInput.value = '';
};

const addNewStatus = () => {
    const trimmed = (newStatusInput.value || '').replace(/\s+/g, ' ').trim();
    if (!trimmed) return;

    const existing = statusSanitaireOptions.value.find(o => o.value.toLowerCase() === trimmed.toLowerCase());
    if (existing) {
        form.status_sanitaire = existing.value;
    } else {
        const newOpt = {
            label: sanitizeStatusLabel(trimmed),
            value: trimmed,
            fullLabel: trimmed,
            custom: true
        };
        statusSanitaireOptions.value.push(newOpt);
        form.status_sanitaire = trimmed;
        saveStatusSanitaireList(statusSanitaireOptions.value.map(o => o.value));
    }

    newStatusInput.value = '';
    showAddStatus.value = false;
};

const removeStatusOption = (valToRemove) => {
    if (!valToRemove) return;
    statusSanitaireOptions.value = statusSanitaireOptions.value.filter(o => o.value !== valToRemove);
    if (form.status_sanitaire === valToRemove) {
        form.status_sanitaire = '';
    }
    saveStatusSanitaireList(statusSanitaireOptions.value.map(o => o.value));
};

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

// =========================================================================
// ENGAGEMENTS OPS / THÉÂTRES D'OPÉRATIONS DYNAMIQUES
// =========================================================================
const defaultTheatresOps = [
    'Opération Maliko (Théâtre Est)',
    'Opération Maliko (Théâtre Centre)',
    'Opération Maliko (Théâtre Sud)',
    'Opération Dambé',
    'Détachement Sokolo',
    'Détachement Dialoumbé',
    'Détachement Timissa',
    'Détachement Toguéré-Coumbé',
    'Détachement Saye',
    'Détachement Goma-Coura',
    'GARSI (Groupement d\'Action Rapide de Surveillance et d\'Intervention)',
    'PSIRC (Peloton Spécial d\'Intervention de la Région Centre)',
    'Prévôté Kidal / Ménaka / Gao',
    'Force Conjointe G-5 Sahel',
    'Mission Internationale (MISMA / MINUSMA)',
    'GIGM / FTS Opérationnelle'
];

const loadTheatresOpsList = () => {
    try {
        const saved = localStorage.getItem('theatres_ops_options_v1');
        if (saved) {
            const parsed = JSON.parse(saved);
            if (Array.isArray(parsed) && parsed.length > 0) return parsed;
        }
    } catch (e) {}
    return defaultTheatresOps;
};

const saveTheatresOpsList = (list) => {
    try {
        localStorage.setItem('theatres_ops_options_v1', JSON.stringify(list));
    } catch (e) {}
};

const theatresOpsOptions = ref(
    loadTheatresOpsList().map(s => ({ label: s, value: s }))
);

const engagementsOpsList = ref([]);

const syncEngagementOpsField = () => {
    const valid = engagementsOpsList.value
        .map(i => i.theatre ? i.theatre.trim() : '')
        .filter(Boolean);
    if (valid.length === 0) {
        form.engagement_ops = '0 OPS';
    } else {
        form.engagement_ops = `${valid.join(', ')} - ${valid.length} OPS`;
    }
};

const addEngagementOpsRow = () => {
    engagementsOpsList.value.push({ theatre: '' });
    syncEngagementOpsField();
};

const removeEngagementOpsRow = (idx) => {
    engagementsOpsList.value.splice(idx, 1);
    syncEngagementOpsField();
};

const showAddTheatreOption = ref(false);
const newTheatreInput = ref('');

const toggleAddTheatreOption = () => {
    showAddTheatreOption.value = !showAddTheatreOption.value;
    newTheatreInput.value = '';
};

const saveNewTheatreOption = () => {
    const trimmed = (newTheatreInput.value || '').trim();
    if (!trimmed) return;
    const existing = theatresOpsOptions.value.find(o => o.value.toLowerCase() === trimmed.toLowerCase());
    if (!existing) {
        theatresOpsOptions.value.push({ label: trimmed, value: trimmed });
        saveTheatresOpsList(theatresOpsOptions.value.map(o => o.value));
    }
    if (engagementsOpsList.value.length > 0 && !engagementsOpsList.value[engagementsOpsList.value.length - 1].theatre) {
        engagementsOpsList.value[engagementsOpsList.value.length - 1].theatre = trimmed;
    } else {
        engagementsOpsList.value.push({ theatre: trimmed });
    }
    syncEngagementOpsField();
    newTheatreInput.value = '';
    showAddTheatreOption.value = false;
};

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
    position_id: null,
    position_actuelle: '',
    unite_id: null,
    unite: '',
    region_id: null,
    region: '',
    date_mutation: null,
    affectations: [
        { region_id: null, unite_id: null, unite: '', region: '', date_mutation: null }
    ],
    fonction_passee: '',
    fonction_actuelle: '',
    telephone: '',
    statut: 'actif',
    organisme_detachement_id: null,
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
        region_id: null,
        unite_id: null,
        unite: '',
        region: '',
        date_mutation: null
    });
};

const removeAffectation = (index) => {
    if (form.affectations.length > 1) {
        form.affectations.splice(index, 1);
    } else {
        form.affectations[0] = { region_id: null, unite_id: null, unite: '', region: '', date_mutation: null };
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

const quickOrganismeVisible = ref(false);
const quickOrganismeLoading = ref(false);
const quickOrganismeForm = ref({ code: '', nom: '' });

const currentQuickAffIndex = ref(null);

const isDetache = computed(() => {
    const pos = localPositions.value.find(p => p.id === form.position_id);
    const nom = pos?.nom || form.position_actuelle || '';
    return nom.toLowerCase().includes('détachement') || nom.toLowerCase().includes('detachement');
});

const isPositionSpeciale = computed(() => {
    const pos = localPositions.value.find(p => p.id === form.position_id);
    const nom = (pos?.nom || form.position_actuelle || '').toLowerCase();
    return nom.includes('détachement') || nom.includes('detachement')
        || nom.includes('stage') || nom.includes('formation')
        || nom.includes('mission') || nom.includes('otage');
});

const setDefaultPositionUnite = () => {
    const posUnite = localPositions.value.find(p => p.id === 21 || p.nom?.toLowerCase().includes('unité') || p.nom?.toLowerCase().includes('poste'));
    if (posUnite) {
        form.position_id = posUnite.id;
        form.position_actuelle = posUnite.nom;
    }
    form.organisme_detachement_id = null;
};

const getUnitesForRow = (idx) => {
    const selectedRegionId = form.affectations[idx]?.region_id;
    if (!selectedRegionId) {
        return localUnites.value;
    }
    return localUnites.value.filter(u => u.region_id === selectedRegionId);
};

const onRegionSelect = (idx) => {
    const selectedRegionId = form.affectations[idx]?.region_id;
    const regionObj = localRegions.value.find(r => r.id === selectedRegionId);
    if (regionObj) {
        form.affectations[idx].region = regionObj.nom;
    } else {
        form.affectations[idx].region = '';
    }

    const currentUniteId = form.affectations[idx]?.unite_id;
    if (selectedRegionId && currentUniteId) {
        const uniteObj = localUnites.value.find(u => u.id === currentUniteId);
        if (uniteObj && uniteObj.region_id !== selectedRegionId) {
            form.affectations[idx].unite_id = null;
            form.affectations[idx].unite = '';
        }
    }
};

const onUniteSelect = (idx) => {
    const selectedUniteId = form.affectations[idx]?.unite_id;
    if (!selectedUniteId) return;
    const uniteObj = localUnites.value.find(u => u.id === selectedUniteId);
    if (uniteObj) {
        form.affectations[idx].unite = uniteObj.nom;
        if (uniteObj.region_id) {
            form.affectations[idx].region_id = uniteObj.region_id;
            const regionObj = localRegions.value.find(r => r.id === uniteObj.region_id);
            form.affectations[idx].region = regionObj?.nom || uniteObj.region?.nom || '';
        }
        if (idx === 0) {
            setDefaultPositionUnite();
        }
    }
};

const onPositionSelect = () => {
    const posObj = localPositions.value.find(p => p.id === form.position_id);
    if (posObj) {
        form.position_actuelle = posObj.nom;
    }
    if (isDetache.value) {
        if (form.affectations && form.affectations[0]) {
            form.affectations[0].region_id = null;
            form.affectations[0].unite_id = null;
            form.affectations[0].region = '';
        }
    } else {
        form.organisme_detachement_id = null;
    }
};

const onOrganismeSelect = () => {
    const org = localOrganismes.value.find(o => o.id === form.organisme_detachement_id);
    if (org) {
        form.unite = org.nom;
        if (form.affectations && form.affectations[0]) {
            form.affectations[0].unite = org.nom;
            form.affectations[0].region_id = null;
            form.affectations[0].unite_id = null;
            form.affectations[0].region = '';
        }
    }
};

const openQuickAddOrganisme = () => {
    quickOrganismeForm.value = { code: '', nom: '' };
    quickOrganismeVisible.value = true;
};

const saveQuickOrganisme = async () => {
    if (!quickOrganismeForm.value.code || !quickOrganismeForm.value.nom) {
        toast.add({ severity: 'warn', summary: 'Champs requis', detail: 'Le code/sigle et le nom officiel sont obligatoires.', life: 3000 });
        return;
    }
    quickOrganismeLoading.value = true;
    try {
        const response = await axios.post(route('prise-armes.organismes.store'), {
            code: quickOrganismeForm.value.code.toUpperCase().trim(),
            nom: quickOrganismeForm.value.nom.trim(),
            est_actif: true,
            est_organique: false
        });
        if (response.data && response.data.organisme) {
            const org = response.data.organisme;
            localOrganismes.value.push(org);
            form.organisme_detachement_id = org.id;
            form.unite = org.nom;
            if (form.affectations && form.affectations[0]) {
                form.affectations[0].unite = org.nom;
                form.affectations[0].region_id = null;
                form.affectations[0].unite_id = null;
                form.affectations[0].region = '';
            }
            quickOrganismeVisible.value = false;
            toast.add({ severity: 'success', summary: 'Succès', detail: `Organisme [${org.code}] créé et sélectionné !`, life: 3000 });
        }
    } catch (e) {
        const msg = e.response?.data?.errors?.code?.[0] || e.response?.data?.errors?.nom?.[0] || e.response?.data?.message || 'Erreur lors de la création.';
        toast.add({ severity: 'error', summary: 'Erreur', detail: msg, life: 4000 });
    } finally {
        quickOrganismeLoading.value = false;
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
                form.affectations[currentQuickAffIndex.value].region_id = response.data.region.id;
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
    let preselectedRegionId = form.affectations[idx]?.region_id;
    if (!preselectedRegionId) {
        const currentRegionNom = form.affectations[idx]?.region;
        if (currentRegionNom) {
            const rObj = localRegions.value.find(r => r.nom === currentRegionNom || r.code === currentRegionNom);
            if (rObj) preselectedRegionId = rObj.id;
        }
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
                form.affectations[currentQuickAffIndex.value].unite_id = response.data.unite.id;
                form.affectations[currentQuickAffIndex.value].unite = response.data.unite.nom;
                if (response.data.unite.region_id) {
                    form.affectations[currentQuickAffIndex.value].region_id = response.data.unite.region_id;
                }
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
            form.position_id = response.data.position.id;
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
    const file = event.target?.files?.[0] || (event.files && event.files[0]);
    if (file) {
        form.certificats[certifId].document = file;
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
    // Valider toutes les étapes avant de soumettre
    if (!validateStep(0) || !validateStep(1) || !validateStep(2)) return;

    syncEngagementOpsField();

    saving.value = true;
    errors.value = {};

    // Synchroniser l'affectation la plus récente (par date de mutation) dans les champs principaux
    if (form.affectations && form.affectations.length > 0) {
        const sorted = [...form.affectations].filter(a => a.unite || a.region || a.unite_id || a.region_id || a.date_mutation);
        sorted.sort((a, b) => {
            const dateA = a.date_mutation ? new Date(a.date_mutation).getTime() : 0;
            const dateB = b.date_mutation ? new Date(b.date_mutation).getTime() : 0;
            return dateB - dateA;
        });
        const mostRecent = sorted[0] || form.affectations[0];
        form.unite_id = mostRecent.unite_id || null;
        form.region_id = mostRecent.region_id || null;
        form.unite = mostRecent.unite || '';
        form.region = mostRecent.region || '';
        form.date_mutation = mostRecent.date_mutation || null;
    }

    const formData = new FormData();

    if (form.affectations && form.affectations.length > 0) {
        form.affectations.forEach((aff, idx) => {
            formData.append(`affectations[${idx}][region_id]`, aff.region_id || '');
            formData.append(`affectations[${idx}][unite_id]`, aff.unite_id || '');
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
        'position_id', 'position_actuelle', 'unite_id', 'unite', 'region_id', 'region', 'fonction_passee', 'fonction_actuelle',
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

        <div class="py-4 sm:py-5 bg-slate-50 min-h-[calc(100vh-140px)]">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- ========================================================================================= -->
                <!-- BANDEAU DE PROGRESSION / STEPPER MULTI-ÉTAPES (COMPACT & FIXE AU SCROLL)                   -->
                <!-- ========================================================================================= -->
                <div class="sticky top-2 z-30 bg-white/95 backdrop-blur-md rounded-xl shadow-md border border-slate-200 px-3 sm:px-4 py-2 sm:py-2.5 mb-3 transition-all">
                    <!-- Barre de progression continue -->
                    <div class="flex items-center justify-between text-xs font-bold text-slate-600 mb-1">
                        <span class="text-sky-900 font-extrabold uppercase tracking-wider flex items-center gap-1.5 text-[11px] sm:text-xs">
                            <span class="inline-block w-2 h-2 rounded-full bg-sky-600 animate-ping"></span>
                            Étape {{ currentStep + 1 }} sur {{ steps.length }} : <span class="text-sky-700 font-bold ml-1 truncate max-w-[200px] sm:max-w-none">{{ steps[currentStep].label }}</span>
                        </span>
                        <span class="px-2 py-0.5 rounded-full bg-sky-100 text-sky-800 border border-sky-200 font-extrabold text-[10px] tracking-wide">
                            {{ Math.round(progress) }}% complété
                        </span>
                    </div>

                    <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-2 shadow-inner relative">
                        <div class="h-full bg-gradient-to-r from-sky-800 via-sky-600 to-amber-500 rounded-full transition-all duration-500 ease-out"
                             :style="{ width: `${progress}%` }"></div>
                    </div>

                    <!-- Onglets interactifs des étapes (3 étapes au total) -->
                    <div class="grid grid-cols-3 gap-1.5 sm:gap-2">
                        <button v-for="(s, idx) in steps" :key="idx"
                                type="button"
                                @click="goToStep(idx)"
                                :disabled="idx > maxStepVisited"
                                :class="[
                                    'group flex items-center gap-1.5 sm:gap-2 p-1.5 sm:p-2 rounded-lg border transition-all text-left min-w-0 overflow-hidden',
                                    idx === currentStep
                                        ? 'bg-sky-50 border-sky-500 shadow-xs ring-1 ring-sky-300 cursor-default'
                                        : idx < currentStep
                                            ? 'bg-emerald-50/70 border-emerald-300 hover:bg-emerald-100/60 cursor-pointer'
                                            : idx <= maxStepVisited
                                                ? 'bg-slate-50 border-slate-300 hover:bg-slate-100 cursor-pointer'
                                                : 'bg-slate-50/40 border-slate-200 opacity-50 cursor-not-allowed'
                                ]">
                            <!-- Pastille numéro / icône -->
                            <div :class="[
                                'w-6 h-6 sm:w-7 sm:h-7 rounded-md flex items-center justify-center font-bold text-[11px] shrink-0 transition-all shadow-2xs',
                                idx === currentStep
                                    ? 'bg-sky-800 text-white shadow-sky-200'
                                    : idx < currentStep
                                        ? 'bg-emerald-600 text-white'
                                        : 'bg-white border border-slate-300 text-slate-600'
                            ]">
                                <i v-if="idx < currentStep" class="pi pi-check text-[10px] font-bold"></i>
                                <i v-else :class="s.icon" class="text-xs"></i>
                            </div>

                            <div class="min-w-0 flex-1 overflow-hidden">
                                <span class="block text-[8px] sm:text-[9px] font-semibold uppercase tracking-wider text-slate-400 leading-none truncate">
                                    Étape {{ idx + 1 }}
                                </span>
                                <span :class="[
                                    'block text-[10px] sm:text-xs font-bold truncate leading-tight mt-0.5',
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
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
                    <!-- En-tête de section -->
                    <div class="px-5 py-3 bg-gradient-to-r from-sky-900 via-sky-800 to-sky-900 text-white flex items-center justify-between border-b border-sky-950/20 rounded-t-2xl">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-white/15 flex items-center justify-center text-white text-base shadow-inner">
                                <i :class="steps[currentStep].icon"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-extrabold text-white tracking-wide">{{ steps[currentStep].title }}</h3>
                                <p class="text-[11px] text-sky-200">{{ steps[currentStep].subtitle }}</p>
                            </div>
                        </div>
                        <span class="text-xs font-extrabold bg-white/20 px-2.5 py-0.5 rounded-full text-white">
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
                                            <label for="matricule" class="block text-sm font-bold text-gray-700 mb-1 truncate whitespace-nowrap">
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
                                            <label for="mle_grm" class="block text-sm font-semibold text-gray-700 mb-1 truncate whitespace-nowrap">
                                                Matricule Gendarmerie
                                            </label>
                                            <InputText id="mle_grm" v-model="form.mle_grm" class="w-full" placeholder="Ex: GRM9272M006871" />
                                        </div>

                                        <!-- N° Info -->
                                        <div class="field">
                                            <label for="numero_info" class="block text-sm font-semibold text-gray-700 mb-1 truncate whitespace-nowrap">
                                                N° Informatique / Solde
                                            </label>
                                            <InputText id="numero_info" v-model="form.numero_info" class="w-full" placeholder="Ex: 1668" />
                                        </div>

                                        <!-- N° Nina -->
                                        <div class="field">
                                            <label for="numero_nina" class="block text-sm font-semibold text-gray-700 mb-1 truncate whitespace-nowrap">
                                                N° NINA / Identification
                                            </label>
                                            <InputText id="numero_nina" v-model="form.numero_nina" class="w-full" placeholder="Numéro national d'identification" />
                                        </div>

                                        <!-- Grade actuel -->
                                        <div class="field">
                                            <label for="grade_actuel" class="block text-sm font-bold text-gray-700 mb-1 truncate whitespace-nowrap">
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
                                            <label for="categorie" class="block text-sm font-semibold text-gray-700 mb-1 truncate whitespace-nowrap">
                                                Catégorie statutaire
                                            </label>
                                            <Select v-model="form.categorie" :options="categorieOptions" optionLabel="label" optionValue="value"
                                                    placeholder="Sélectionner la catégorie" class="w-full" showClear />
                                            <small class="text-[11px] text-sky-600 block mt-1">
                                                <i class="pi pi-info-circle mr-1"></i>Renseignée automatiquement selon le grade.
                                            </small>
                                        </div>

                                                                                <!-- Statut administratif - Mis en valeur bien visible -->
                                        <div class="field sm:col-span-2 lg:col-span-3 min-w-0 bg-gradient-to-r from-sky-50 via-slate-50 to-blue-50/60 p-3 sm:p-4 rounded-xl border-2 border-sky-200 shadow-2xs">
                                            <div class="flex flex-wrap items-center justify-between mb-2 gap-2">
                                                <label for="statut" class="block text-sm font-bold text-sky-950 flex items-center gap-2 min-w-0">
                                                    <i class="pi pi-id-card text-sky-700 text-base shrink-0"></i>
                                                    <span class="truncate">Statut Administratif</span> <span class="text-red-500">*</span>
                                                </label>
                                                <span v-if="form.statut" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider shrink-0"
                                                      :class="form.statut === 'actif' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-amber-100 text-amber-800 border border-amber-300'">
                                                    <span class="w-2 h-2 rounded-full" :class="form.statut === 'actif' ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                                    {{ form.statut }}
                                                </span>
                                            </div>
                                            <Select v-model="form.statut" :options="statutOptions" optionLabel="label" optionValue="value"
                                                    placeholder="Sélectionner le statut administratif (Actif, Déserteur, Retraité...)" class="w-full"
                                                    :class="{ 'p-invalid': errors.statut || (stepErrors && stepErrors[0] && stepErrors[0].statut) }" />
                                            <small v-if="errors.statut || (stepErrors && stepErrors[0] && stepErrors[0].statut)" class="text-red-500 font-medium block mt-1">
                                                {{ errors.statut || (stepErrors && stepErrors[0] && stepErrors[0].statut) }}
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
                        <!-- ================================================================================= -->
                        <!-- ÉTAPE 2 : CARRIÈRE & AFFECTATION                                                  -->
                        <!-- ================================================================================= -->
                        <div v-show="currentStep === 1" class="space-y-6">

                            <!-- CARD 1 : Dates clés et Fonctions de Carrière -->
                            <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 shadow-2xs">
                                <div class="flex items-center gap-2 text-sky-900 border-b border-slate-200 pb-3 mb-4 text-sm font-extrabold uppercase tracking-wide">
                                    <i class="pi pi-briefcase text-sky-800 text-base"></i>
                                    <span>Card 1 — Dates clés et Fonctions de Carrière</span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Date d'entrée en service -->
                                    <div class="field">
                                        <label for="date_entree_service" class="block text-sm font-bold text-gray-700 mb-1 truncate whitespace-nowrap">
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
                                        <label for="date_derniere_promotion" class="block text-sm font-semibold text-gray-700 mb-1 truncate whitespace-nowrap">
                                            Date dernière promotion
                                        </label>
                                        <DatePicker id="date_derniere_promotion" v-model="form.date_derniere_promotion" dateFormat="dd/mm/yy" showIcon class="w-full" />
                                    </div>

                                    <!-- Origine de formation -->
                                    <div class="field">
                                        <label for="origine_formation" class="block text-sm font-semibold text-gray-700 mb-1 truncate whitespace-nowrap">
                                            Origine / École de formation
                                        </label>
                                        <InputText id="origine_formation" v-model="form.origine_formation" class="w-full" placeholder="Ex: EMIA, Rang, ESOA..." />
                                    </div>

                                    <!-- Fonction actuelle -->
                                    <div class="field">
                                        <label for="fonction_actuelle" class="block text-sm font-semibold text-gray-700 mb-1 truncate whitespace-nowrap">
                                            Fonction actuelle
                                        </label>
                                        <InputText id="fonction_actuelle" v-model="form.fonction_actuelle" class="w-full" placeholder="Ex: C.B.T, Commandant d'unité..." />
                                    </div>

                                    <!-- Fonction passée -->
                                    <div class="field">
                                        <label for="fonction_passee" class="block text-sm font-semibold text-gray-700 mb-1 truncate whitespace-nowrap">
                                            Fonction passée
                                        </label>
                                        <InputText id="fonction_passee" v-model="form.fonction_passee" class="w-full" />
                                    </div>

                                    <!-- Spécialité -->
                                    <div class="field">
                                        <label for="specialite" class="block text-sm font-semibold text-gray-700 mb-1 truncate whitespace-nowrap">
                                            Spécialité
                                        </label>
                                        <InputText id="specialite" v-model="form.specialite" class="w-full" placeholder="Ex: Motocycliste, OPJ, Télécom..." />
                                    </div>
                                </div>
                            </div>

                            <!-- CARD 2 : Situation de Service & Position Actuelle -->
                            <div class="p-5 rounded-2xl border border-slate-200 bg-white shadow-2xs">
                                <div class="flex items-center justify-between border-b border-slate-200 pb-3 mb-4">
                                    <div class="flex items-center gap-2 text-sky-900 text-sm font-extrabold uppercase tracking-wide">
                                        <i class="pi pi-compass text-sky-800 text-base"></i>
                                        <span>Card 2 — Position Actuelle & Situation de Service</span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <!-- Position actuelle -->
                                    <div class="field">
                                        <div class="h-6 flex items-center justify-between mb-1 gap-1">
                                            <label for="position_actuelle" class="block text-xs font-semibold text-gray-700 truncate whitespace-nowrap">
                                                Position actuelle <span class="text-red-500">*</span>
                                            </label>
                                            <button type="button" @click="openQuickAddPosition"
                                                    class="w-6 h-6 rounded-md bg-sky-50 border border-sky-200 text-sky-700 hover:bg-sky-100 hover:text-sky-900 flex items-center justify-center shrink-0 cursor-pointer transition-colors shadow-2xs"
                                                    title="Ajouter une nouvelle position">
                                                <i class="pi pi-plus text-xs font-bold"></i>
                                            </button>
                                        </div>
                                        <Select id="position_actuelle"
                                                v-model="form.position_id"
                                                :options="localPositions"
                                                optionLabel="nom"
                                                optionValue="id"
                                                filter
                                                placeholder="Sélectionner la position (À l'Unité, En détachement...)"
                                                class="w-full text-xs"
                                                @change="onPositionSelect">
                                            <template #option="slotProps">
                                                <div class="flex items-center justify-between w-full py-0.5">
                                                    <span class="text-xs font-medium">{{ slotProps.option.nom }}</span>
                                                    <span class="text-[10px] px-1.5 py-0.2 rounded font-semibold bg-slate-100 text-slate-700">
                                                        {{ slotProps.option.type === 'operationnelle' ? 'Théâtre / Déploiement' : 'Poste' }}
                                                    </span>
                                                </div>
                                            </template>
                                        </Select>
                                    </div>

                                    <!-- Observations particulières / Mention officielle -->
                                    <div class="field">
                                        <div class="h-6 flex items-center justify-between mb-1 gap-2">
                                            <label for="observations" class="block text-xs font-semibold text-gray-700 truncate whitespace-nowrap">
                                                Observations particulières / Mention officielle
                                            </label>
                                            <span class="text-[10px] text-slate-400 font-normal italic truncate">Remarques, otage, etc.</span>
                                        </div>
                                        <InputText id="observations"
                                                   v-model="form.observations"
                                                   class="w-full text-xs"
                                                   placeholder="Ex: Otage devant partir à la Retraite depuis 2020..." />
                                    </div>
                                </div>

                                <!-- ZONE CONDITIONNELLE : ORGANISME DE DÉTACHEMENT (Affiché uniquement si en détachement) -->
                                <div v-if="isDetache" class="mt-4 p-4 bg-sky-50/80 border border-sky-200 rounded-xl">
                                    <div class="flex items-center justify-between mb-1.5 gap-1">
                                        <label class="block text-xs font-bold text-sky-950 flex items-center gap-1.5">
                                            <i class="pi pi-briefcase text-sky-700"></i>
                                            <span>Organisme de Détachement (SPA)</span>
                                            <span class="text-red-500">*</span>
                                        </label>
                                        <button type="button" @click="openQuickAddOrganisme"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-white border border-sky-300 text-sky-800 hover:bg-sky-100 text-xs font-bold shrink-0 cursor-pointer transition-colors shadow-2xs"
                                                title="Ajouter un nouvel organisme de détachement">
                                            <i class="pi pi-plus text-xs"></i>
                                            <span>Ajouter un organisme</span>
                                        </button>
                                    </div>
                                    <Select v-model="form.organisme_detachement_id"
                                            :options="organismesDetachementOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            placeholder="Sélectionner l'organisme (ex: DOCS, ANSE, MJGS, PRM, PM...)"
                                            class="w-full text-xs"
                                            filter
                                            filterPlaceholder="Rechercher par sigle ou nom..."
                                            @change="onOrganismeSelect">
                                        <template #value="slotProps">
                                            <div v-if="slotProps.value" class="flex items-center gap-2">
                                                <span class="font-mono text-[11px] font-extrabold text-sky-800 bg-sky-100 border border-sky-300 px-1.5 py-0.5 rounded shrink-0">
                                                    {{ getOrganismeCode(slotProps.value) }}
                                                </span>
                                                <span class="text-xs text-slate-900 font-semibold truncate">{{ getOrganismeNom(slotProps.value) }}</span>
                                            </div>
                                            <span v-else class="text-slate-400 text-xs">
                                                Sélectionner l'organisme (ex: DOCS, ANSE, MJGS, PRM, PM...)
                                            </span>
                                        </template>
                                        <template #option="slotProps">
                                            <div v-if="slotProps.option.value" class="flex items-center gap-2 py-0.5">
                                                <span class="font-mono text-[11px] font-extrabold text-sky-800 bg-sky-50 border border-sky-200 px-1.5 py-0.5 rounded shrink-0">
                                                    {{ slotProps.option.code }}
                                                </span>
                                                <span class="text-xs text-slate-800 font-medium truncate">{{ slotProps.option.nom }}</span>
                                            </div>
                                            <span v-else class="text-xs text-slate-400 italic">{{ slotProps.option.label }}</span>
                                        </template>
                                    </Select>
                                </div>
                            </div>

                            <!-- CARD 3 : AFFECTATION TERRITORIALE (GENDARMERIE NATIONALE) -->
                            <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/50 shadow-2xs">
                                <div class="flex items-center justify-between border-b border-slate-200 pb-3 mb-4">
                                    <div class="flex items-center gap-2 text-sky-900 text-sm font-extrabold uppercase tracking-wide">
                                        <i class="pi pi-building text-sky-800 text-base"></i>
                                        <span>Card 3 — Affectation Territoriale (Unités de Gendarmerie)</span>
                                    </div>
                                    <Button v-if="!isDetache" type="button" label="Ajouter une affectation" icon="pi pi-plus"
                                            class="p-button-sm !bg-white hover:!bg-sky-50 !text-sky-800 !border !border-sky-300 font-bold shadow-2xs cursor-pointer"
                                            @click="addAffectation" />
                                </div>

                                <!-- Si en détachement : message explicatif et masque les champs de brigade territoriale -->
                                <div v-if="isDetache" class="p-4 rounded-xl bg-white border border-sky-200 shadow-2xs flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-sky-100 text-sky-700 flex items-center justify-center shrink-0">
                                        <i class="pi pi-shield text-lg"></i>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-sky-950 uppercase">Personnel en position de Détachement</div>
                                        <div class="text-xs text-slate-600 mt-0.5">
                                            Ce militaire est placé en détachement auprès de : <strong>{{ form.unite || 'Organisme de détachement externe' }}</strong>.
                                        </div>
                                    </div>
                                </div>

                                <!-- Si géré par la Gendarmerie : formulaire des affectations territoriales -->
                                <div v-else class="space-y-4">
                                    <div v-for="(aff, idx) in form.affectations" :key="idx"
                                         class="p-4 rounded-xl border border-slate-200 bg-white relative transition-all shadow-2xs">
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

                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                            <!-- Région de Gendarmerie -->
                                            <div class="field">
                                                <div class="h-6 flex items-center justify-between mb-1 gap-1">
                                                    <label :for="'region_' + idx" class="block text-xs font-semibold text-gray-700 truncate whitespace-nowrap">
                                                        Région de Gendarmerie
                                                    </label>
                                                    <button type="button" @click="openQuickAddRegion(idx)"
                                                            class="w-6 h-6 rounded-md bg-sky-50 border border-sky-200 text-sky-700 hover:bg-sky-100 hover:text-sky-900 flex items-center justify-center shrink-0 cursor-pointer transition-colors shadow-2xs"
                                                            title="Ajouter une nouvelle région">
                                                        <i class="pi pi-plus text-xs font-bold"></i>
                                                    </button>
                                                </div>
                                                <Select :id="'region_' + idx"
                                                        v-model="aff.region_id"
                                                        :options="localRegions"
                                                        optionLabel="nom"
                                                        optionValue="id"
                                                        filter
                                                        placeholder="Choisir une région..."
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
                                                <div class="h-6 flex items-center justify-between mb-1 gap-1">
                                                    <label :for="'unite_' + idx" class="block text-xs font-semibold text-gray-700 truncate whitespace-nowrap">
                                                        Unité / Brigade / Escadron
                                                    </label>
                                                    <button type="button" @click="openQuickAddUnite(idx)"
                                                            class="w-6 h-6 rounded-md bg-sky-50 border border-sky-200 text-sky-700 hover:bg-sky-100 hover:text-sky-900 flex items-center justify-center shrink-0 cursor-pointer transition-colors shadow-2xs"
                                                            title="Ajouter une nouvelle unité">
                                                        <i class="pi pi-plus text-xs font-bold"></i>
                                                    </button>
                                                </div>
                                                <Select :id="'unite_' + idx"
                                                        v-model="aff.unite_id"
                                                        :options="getUnitesForRow(idx)"
                                                        optionLabel="nom"
                                                        optionValue="id"
                                                        filter
                                                        placeholder="Choisir une unité..."
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
                                                <small v-if="!isPositionSpeciale && aff.region_id && !aff.unite_id" class="text-[10px] text-amber-700 font-bold bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200 inline-block mt-1">
                                                    ⚠️ Unité de cette région requise
                                                </small>
                                            </div>

                                            <!-- Date Mutation -->
                                            <div class="field">
                                                <div class="h-6 flex items-center mb-1">
                                                    <label :for="'date_mutation_' + idx" class="block text-xs font-semibold text-gray-700 truncate whitespace-nowrap">
                                                        Date de mutation
                                                    </label>
                                                </div>
                                                <DatePicker :id="'date_mutation_' + idx" v-model="aff.date_mutation"
                                                            dateFormat="dd/mm/yy" showIcon class="w-full" size="small" />
                                            </div>
                                        </div>
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
                                        <div class="h-6 flex items-center mb-1">
                                            <label for="groupe_sanguin" class="block text-xs font-semibold text-gray-700 truncate whitespace-nowrap">
                                                Groupe sanguin
                                            </label>
                                        </div>
                                        <Select v-model="form.groupe_sanguin" :options="groupeSanguinOptions" optionLabel="label" optionValue="value"
                                                placeholder="Sélectionner" class="w-full" showClear />
                                    </div>

                                    <!-- Taille -->
                                    <div class="field">
                                        <div class="h-6 flex items-center mb-1">
                                            <label for="taille" class="block text-xs font-semibold text-gray-700 truncate whitespace-nowrap">
                                                Taille (stature)
                                            </label>
                                        </div>
                                        <InputText id="taille" v-model="form.taille" class="w-full" placeholder="Ex: 1.78 m ou 178 cm" />
                                    </div>

                                    <!-- Statut sanitaire -->
                                    <div class="field">
                                        <div class="h-6 flex items-center justify-between mb-1 gap-1">
                                            <label for="status_sanitaire" class="block text-xs font-semibold text-gray-700 truncate whitespace-nowrap">
                                                Statut sanitaire / Aptitude
                                            </label>
                                            <button type="button" 
                                                    @click="toggleAddStatus" 
                                                    class="w-6 h-6 rounded-md bg-sky-50 border border-sky-200 text-sky-700 hover:bg-sky-100 hover:text-sky-900 flex items-center justify-center shrink-0 cursor-pointer transition-colors shadow-2xs"
                                                    :title="showAddStatus ? 'Fermer' : 'Ajouter un nouveau statut sanitaire'">
                                                <i :class="showAddStatus ? 'pi pi-times' : 'pi pi-plus'" class="text-xs font-bold"></i>
                                            </button>
                                        </div>

                                        <!-- Bloc d'ajout rapide d'un nouveau statut -->
                                        <div v-if="showAddStatus" class="mb-2 p-2.5 bg-sky-50 rounded-xl border border-sky-200 flex items-center gap-2 animate-fade-in">
                                            <InputText v-model="newStatusInput" 
                                                       placeholder="Saisir un statut (Ex: Blessé en service, Apte groupe 2...)" 
                                                       class="w-full text-xs" 
                                                       @keydown.enter.prevent="addNewStatus" />
                                            <Button type="button" 
                                                    icon="pi pi-check" 
                                                    size="small" 
                                                    class="!bg-sky-800 hover:!bg-sky-900 text-white font-semibold text-xs px-3 py-2 rounded-lg shrink-0 cursor-pointer"
                                                    @click="addNewStatus"
                                                    title="Ajouter et sélectionner" />
                                            <Button type="button" 
                                                    icon="pi pi-times" 
                                                    severity="secondary" 
                                                    outlined 
                                                    size="small" 
                                                    class="shrink-0 text-xs p-2"
                                                    @click="toggleAddStatus" />
                                        </div>

                                        <!-- Select avec recherche intégrée (pleine largeur, sans bouton débordant) -->
                                        <Select id="status_sanitaire" 
                                                v-model="form.status_sanitaire" 
                                                :options="statusSanitaireOptions"
                                                optionLabel="label" 
                                                optionValue="value" 
                                                :filter="true" 
                                                filterPlaceholder="Rechercher un statut..."
                                                placeholder="Sélectionner ou rechercher un statut" 
                                                class="w-full"
                                                panelClass="!max-w-[420px] !w-full overflow-hidden shadow-2xl rounded-xl border border-slate-200"
                                                :panelStyle="{ maxWidth: '420px', width: '100%' }"
                                                showClear>
                                            <template #value="slotProps">
                                                <div v-if="slotProps.value" class="truncate max-w-[280px] text-xs font-semibold text-slate-800" :title="slotProps.value">
                                                    {{ sanitizeStatusLabel(slotProps.value) }}
                                                </div>
                                                <span v-else class="text-slate-400 text-xs">
                                                    {{ slotProps.placeholder }}
                                                </span>
                                            </template>
                                            <template #option="slotProps">
                                                <div class="flex items-center justify-between w-full py-1 gap-2 max-w-full overflow-hidden">
                                                    <span class="text-xs truncate flex-1 text-slate-800 font-medium" :title="slotProps.option.fullLabel || slotProps.option.label">
                                                        {{ slotProps.option.label }}
                                                    </span>
                                                    <!-- Permet de retirer n'importe quelle option (existante ou personnalisée) -->
                                                    <button type="button" 
                                                            @click.stop="removeStatusOption(slotProps.option.value)"
                                                            class="text-slate-400 hover:text-rose-600 p-1 rounded hover:bg-rose-50 transition-colors shrink-0 cursor-pointer"
                                                            title="Retirer cette option de la liste">
                                                        <i class="pi pi-trash text-[11px]"></i>
                                                    </button>
                                                </div>
                                            </template>
                                        </Select>
                                    </div>

                                    <!-- Engagement OPS Dynamique (Multi-sélection & Comptage OPS) -->
                                    <div class="field sm:col-span-2 lg:col-span-3 p-4 rounded-xl border border-amber-200 bg-amber-50/40 shadow-2xs">
                                        <div class="flex items-center justify-between mb-3 border-b border-amber-200/80 pb-2 flex-wrap gap-2">
                                            <div class="flex items-center gap-2">
                                                <i class="pi pi-shield text-amber-700 text-sm"></i>
                                                <span class="text-xs font-extrabold uppercase tracking-wide text-amber-950">
                                                    Engagement OPS / Théâtres d'opérations
                                                </span>
                                                <span class="px-2 py-0.5 rounded-full text-[11px] font-black bg-amber-600 text-white shadow-2xs">
                                                    {{ engagementsOpsList.length }} OPS
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <button
                                                    type="button"
                                                    @click="toggleAddTheatreOption"
                                                    class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-white text-amber-800 border border-amber-300 hover:bg-amber-100 flex items-center gap-1 transition-colors cursor-pointer shadow-2xs"
                                                    title="Créer un nouveau théâtre d'opération dans la liste"
                                                >
                                                    <i :class="showAddTheatreOption ? 'pi pi-times' : 'pi pi-plus'" class="text-[10px]"></i>
                                                    <span>{{ showAddTheatreOption ? 'Fermer' : 'Nouveau théâtre' }}</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="addEngagementOpsRow"
                                                    class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-amber-700 text-white hover:bg-amber-800 flex items-center gap-1 transition-colors cursor-pointer shadow-2xs"
                                                >
                                                    <i class="pi pi-plus text-[10px]"></i>
                                                    <span>Ajouter un théâtre</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Formulaire d'ajout rapide d'un nouveau nom de théâtre dans la liste déroulante -->
                                        <div v-if="showAddTheatreOption" class="mb-3 p-3 bg-white rounded-lg border border-amber-300 shadow-xs flex items-center gap-2">
                                            <InputText
                                                v-model="newTheatreInput"
                                                placeholder="Nom du théâtre (ex: Opération Tilé Koun, Théâtre Nord, Sokolo...)"
                                                class="flex-1 !text-xs !p-2"
                                                @keydown.enter.prevent="saveNewTheatreOption"
                                            />
                                            <Button label="Ajouter à la liste" size="small" severity="warning" @click="saveNewTheatreOption" class="!text-xs" />
                                            <Button icon="pi pi-times" text severity="secondary" size="small" @click="showAddTheatreOption = false" />
                                        </div>

                                        <!-- Liste des théâtres d'opérations sélectionnés -->
                                        <div v-if="engagementsOpsList.length > 0" class="space-y-2.5">
                                            <div
                                                v-for="(opsRow, idx) in engagementsOpsList"
                                                :key="idx"
                                                class="flex items-center gap-2 p-2 bg-white rounded-lg border border-amber-200/90 shadow-2xs"
                                            >
                                                <div class="w-6 h-6 rounded-full bg-amber-600 text-white flex items-center justify-center font-bold text-[10px] shrink-0">
                                                    {{ idx + 1 }}
                                                </div>
                                                <span class="text-xs font-black text-amber-800 shrink-0">
                                                    OPS {{ idx + 1 }} :
                                                </span>
                                                <div class="flex-1 min-w-0">
                                                    <Select
                                                        v-model="opsRow.theatre"
                                                        :options="theatresOpsOptions"
                                                        optionLabel="label"
                                                        optionValue="value"
                                                        filter
                                                        placeholder="Sélectionner ou chercher un théâtre / opération..."
                                                        class="w-full !text-xs"
                                                        panelClass="!max-w-[420px] !w-full overflow-hidden shadow-xl rounded-xl border border-slate-200"
                                                        :panelStyle="{ maxWidth: '420px', width: '100%' }"
                                                        @change="syncEngagementOpsField"
                                                    />
                                                </div>
                                                <button
                                                    type="button"
                                                    @click="removeEngagementOpsRow(idx)"
                                                    class="w-7 h-7 rounded text-rose-600 hover:bg-rose-50 flex items-center justify-center shrink-0 transition-colors"
                                                    title="Supprimer ce théâtre"
                                                >
                                                    <i class="pi pi-trash text-xs"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div v-else class="p-3 bg-white/80 rounded-lg border border-dashed border-amber-300 text-center text-xs text-amber-800">
                                            <span>Aucun théâtre d'opération renseigné (<strong>0 OPS</strong>). Cliquez sur « Ajouter un théâtre » pour en attribuer.</span>
                                        </div>
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

                                            <div v-if="form.certificats[certificat.id].obtenu" class="mt-2.5 pl-6 pt-2 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                <div>
                                                    <label :for="'date_certif_' + certificat.id" class="block text-[11px] font-semibold text-gray-600 mb-1">
                                                        Date d'obtention <span class="text-red-500">*</span>
                                                    </label>
                                                    <DatePicker :id="'date_certif_' + certificat.id"
                                                                v-model="form.certificats[certificat.id].date_obtention"
                                                                dateFormat="dd/mm/yy" showIcon class="w-full text-xs" size="small"
                                                                :class="{ 'p-invalid': dateErrors[certificat.id] }" />
                                                    <small v-if="dateErrors[certificat.id]" class="text-red-500 text-[10px] font-medium block mt-0.5">
                                                        Date d'obtention obligatoire
                                                    </small>
                                                </div>

                                                <div>
                                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Document justificatif (optionnel)</label>
                                                    <div class="flex items-center gap-1.5 flex-wrap">
                                                        <label :for="'doc_' + certificat.id"
                                                               class="cursor-pointer inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-sky-800 hover:bg-sky-900 text-white transition-colors shadow-2xs shrink-0">
                                                            <i class="pi pi-paperclip text-[11px]"></i>
                                                            <span>{{ form.certificats[certificat.id].document ? 'Changer' : 'Joindre' }}</span>
                                                            <input :id="'doc_' + certificat.id"
                                                                   type="file"
                                                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                                                   class="hidden"
                                                                   @change="(e) => onFileSelect(certificat.id, e)" />
                                                        </label>
                                                        <div v-if="form.certificats[certificat.id].document"
                                                             class="flex items-center gap-1 min-w-0 bg-sky-50 px-2 py-1 rounded border border-sky-200 text-xs">
                                                            <span class="truncate text-sky-950 font-medium text-[11px] max-w-[100px]" :title="form.certificats[certificat.id].document.name || 'Fichier joint'">
                                                                {{ form.certificats[certificat.id].document.name }}
                                                            </span>
                                                            <button type="button" @click="removeDocument(certificat.id)"
                                                                    class="text-red-500 hover:text-red-700 cursor-pointer shrink-0 ml-0.5"
                                                                    title="Supprimer le document">
                                                                <i class="pi pi-times text-[10px]"></i>
                                                            </button>
                                                        </div>
                                                        <span v-else class="text-[11px] text-slate-400 italic">
                                                            Aucun fichier
                                                        </span>
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
                        <!-- BARRE DE NAVIGATION INFÉRIEURE DU STEPPER (COMPACTE & FIXE AU BAS DE L'ÉCRAN)      -->
                        <!-- ================================================================================= -->
                        <div class="sticky bottom-2 z-30 mt-6 bg-white/95 backdrop-blur-md px-3 sm:px-4 py-2.5 rounded-xl border border-slate-200 shadow-lg flex flex-wrap items-center justify-between gap-2">
                            <!-- Bouton Précédent -->
                            <Button v-if="currentStep > 0"
                                    type="button"
                                    label="Précédent"
                                    icon="pi pi-arrow-left"
                                    size="small"
                                    class="p-button-outlined p-button-secondary font-bold text-xs cursor-pointer flex-1 sm:flex-none justify-center"
                                    @click="prevStep" />
                            <div v-else class="hidden sm:block"></div>

                            <!-- Actions Droite -->
                            <div class="flex items-center gap-2 sm:gap-3 flex-1 sm:flex-none justify-end">
                                <Button type="button"
                                        label="Annuler"
                                        icon="pi pi-times"
                                        size="small"
                                        class="p-button-text p-button-secondary text-xs cursor-pointer"
                                        @click="cancel" />

                                <Button v-if="currentStep < steps.length - 1"
                                        type="button"
                                        label="Suivant"
                                        icon="pi pi-arrow-right"
                                        iconPos="right"
                                        size="small"
                                        class="!bg-sky-800 hover:!bg-sky-900 text-white font-bold text-xs px-4 py-2 rounded-lg shadow-xs cursor-pointer flex items-center gap-1.5"
                                        @click="nextStep" />

                                <Button v-else
                                        type="submit"
                                        label="Enregistrer"
                                        icon="pi pi-check"
                                        size="small"
                                        :loading="saving"
                                        class="!bg-gradient-to-r !from-emerald-700 !to-teal-700 hover:!from-emerald-800 hover:!to-teal-800 text-white font-bold text-xs px-4 sm:px-5 py-2 rounded-lg shadow-md cursor-pointer flex items-center gap-1.5" />
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
        <Dialog v-model:visible="quickPositionVisible" header="Ajout Rapide : Nouvel Endroit / Théâtre de Déploiement" :modal="true" class="w-full max-w-md">
            <div class="space-y-3 pt-2 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nom de l'Endroit / Théâtre (Où il se trouve) <span class="text-red-500">*</span></label>
                    <InputText v-model="quickPositionForm.nom" class="w-full text-xs" placeholder="Ex: Escadron Badala, Base Koulouba, Camp 1..." />
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Type d'emplacement</label>
                    <Select v-model="quickPositionForm.type" :options="[{ label: 'Théâtre Opérationnel / Mission', value: 'operationnelle' }, { label: 'Poste / Emplacement Fixe', value: 'poste' }]" optionLabel="label" optionValue="value" class="w-full text-xs" />
                </div>
            </div>
            <template #footer>
                <Button label="Annuler" text severity="secondary" size="small" @click="quickPositionVisible = false" />
                <Button label="Créer & Sélectionner" icon="pi pi-check" size="small" class="bg-sky-700 hover:bg-sky-800 text-white border-0" :loading="quickPositionLoading" @click="saveQuickPosition" />
            </template>
        </Dialog>

        <!-- DIALOG AJOUT RAPIDE ORGANISME DE DÉTACHEMENT -->
        <Dialog v-model:visible="quickOrganismeVisible" header="Ajout Rapide : Nouvel Organisme de Détachement (SPA)" :modal="true" class="w-full max-w-md">
            <div class="space-y-3 pt-2 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Sigle / Code officiel (ex: DOCS, ANSE, MJGS...) <span class="text-red-500">*</span></label>
                    <InputText v-model="quickOrganismeForm.code" class="w-full text-xs font-mono uppercase" placeholder="Ex: ANSE" />
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nom complet officiel de l'organisme <span class="text-red-500">*</span></label>
                    <InputText v-model="quickOrganismeForm.nom" class="w-full text-xs" placeholder="Ex: Agence Nationale de la Sécurité d'Etat" />
                </div>
            </div>
            <template #footer>
                <Button label="Annuler" text severity="secondary" size="small" @click="quickOrganismeVisible = false" />
                <Button label="Créer & Sélectionner" icon="pi pi-check" size="small" class="bg-sky-700 hover:bg-sky-800 text-white border-0" :loading="quickOrganismeLoading" @click="saveQuickOrganisme" />
            </template>
        </Dialog>

        <Toast position="top-right" />
    </AuthenticatedLayout>
</template>
