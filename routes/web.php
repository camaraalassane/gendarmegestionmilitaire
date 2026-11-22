<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CertificatController;
use App\Http\Controllers\MilitaireController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\AlerteController;
use App\Http\Controllers\EligibiliteController;
use App\Http\Controllers\ContratController;
use App\Http\Controllers\AvancementController;
use App\Http\Controllers\CertificatDocumentController;
use App\Http\Controllers\MilitairesImportController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\CarteController;
use App\Http\Controllers\PriseArmeController;
use App\Http\Controllers\MilitairePhotoController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Rediriger la racine vers la page de login
Route::get('/', function () {
    return redirect()->route('login');
});

// Route du dashboard : Redirection intelligente selon le rôle
Route::get('/dashboard', function () {
    if (auth()->user()->isSuperAdmin()) {
        return redirect()->route('users.index');
    }
    if (!auth()->user()->isAdmin()) {
        auth()->logout();
        return redirect()->route('login')->with('error', 'Votre compte est en attente d\'activation par un administrateur.');
    }
    return app(DashboardController::class)->index();
})->middleware(['auth'])->name('dashboard');

// Routes réservées à l'Admin (Gestionnaire)
Route::middleware(['auth', 'admin'])->group(function () {
    // Dashboard actions
    Route::get('/dashboard/section', [DashboardController::class, 'section'])->name('dashboard.section');
    Route::get('/dashboard/export-proposables-annee-n', [DashboardController::class, 'exportProposablesAnneeN'])->name('dashboard.export-proposables-annee-n');
    Route::get('/dashboard/export-proposables-annee-n1', [DashboardController::class, 'exportProposablesAnneeN1'])->name('dashboard.export-proposables-annee-n1');
    Route::get('/dashboard/export-retraites-annee-n', [DashboardController::class, 'exportRetraitesAnneeN'])->name('dashboard.export-retraites-annee-n');
    Route::get('/dashboard/export-retraites-annee-n1', [DashboardController::class, 'exportRetraitesAnneeN1'])->name('dashboard.export-retraites-annee-n1');

    // Carte opérationnelle & Déploiement
    Route::get('/carte', [CarteController::class, 'index'])->name('carte.index');
    Route::get('/carte/militaires-statut', [CarteController::class, 'militairesStatut'])->name('carte.militaires-statut');
    Route::get('/carte/theatres/{theatre}/militaires', [CarteController::class, 'theatreMilitaires'])->name('carte.theatres.militaires');
    Route::patch('/carte/unites/{unite}/coordonnees', [CarteController::class, 'updateCoordinates'])->name('carte.unites.coordinates');
    Route::patch('/carte/theatres/{theatre}/coordonnees', [CarteController::class, 'updateTheatreCoordinates'])->name('carte.theatres.coordinates');
    Route::get('/carte/export', [\App\Http\Controllers\StatistiquesExportController::class, 'carte'])->name('carte.export');
    Route::get('/carte/export-statut', [\App\Http\Controllers\StatistiquesExportController::class, 'exportStatut'])->name('carte.export-statut');
    Route::get('/dashboard/export-statistiques', [\App\Http\Controllers\StatistiquesExportController::class, 'dashboard'])->name('dashboard.export-statistiques');

    // Alertes
    Route::get('/alertes', [AlerteController::class, 'index'])->name('alertes.index');
    Route::post('/alertes/{alerte}/marquer-vue', [AlerteController::class, 'marquerVue'])->name('alertes.marquer-vue');
    Route::post('/alertes/marquer-tout-vue', [AlerteController::class, 'marquerToutVue'])->name('alertes.marquer-tout-vue');
    Route::post('/alertes/check-renouvellements', [AlerteController::class, 'checkRenouvellements'])->name('alertes.check-renouvellements');
    Route::post('/alertes/check-mutations', [AlerteController::class, 'checkMutations'])->name('alertes.check-mutations');
    Route::post('/alertes/generer', [AlerteController::class, 'genererAlertes'])->name('alertes.generer');

    // Contrats
    Route::get('/contrats', [ContratController::class, 'index'])->name('contrats.index');
    Route::post('/contrats', [ContratController::class, 'store'])->name('contrats.store');
    Route::get('/contrats/stats', [ContratController::class, 'stats'])->name('contrats.stats');
    Route::get('/contrats/historique/{militaire}', [ContratController::class, 'historique'])->name('contrats.historique');
    Route::post('/contrats/check-alerts', [ContratController::class, 'checkAndGenerateAlerts'])->name('contrats.check-alerts');
    Route::get('/contrats/{contrat}', [ContratController::class, 'show'])->whereNumber('contrat')->name('contrats.show');
    Route::post('/contrats/annuler-renouvellement', [ContratController::class, 'annulerRenouvellement'])->name('contrats.annuler-renouvellement');

    // Grades
    Route::get('/grades', [GradeController::class, 'index'])->name('grades.index');
    Route::get('/grades/{grade}', [GradeController::class, 'show'])->name('grades.show');
    Route::post('/grades', [GradeController::class, 'store'])->name('grades.store');
    Route::put('/grades/{grade}', [GradeController::class, 'update'])->name('grades.update');

    // Certificats
    Route::get('/certificats', [CertificatController::class, 'index'])->name('certificats.index');
    Route::get('/certificats/{certificat}', [CertificatController::class, 'show'])->name('certificats.show');
    Route::get('/certificats/document/{id}/download', [CertificatDocumentController::class, 'download'])
        ->name('certificats.document.download');

    // Militaires
    Route::get('/militaires/{militaire}/photo', [MilitairePhotoController::class, 'show'])->name('militaires.photo');
    Route::get('/militaires/export', [MilitaireController::class, 'export'])->name('militaires.export');
    Route::get('/militaires/export/template', [MilitaireController::class, 'exportTemplate'])->name('militaires.export.template');
    Route::get('/militaires/search-selection', [MilitaireController::class, 'searchSelection'])->name('militaires.search-selection');
    Route::post('/militaires/affectation-collective', [MilitaireController::class, 'bulkAssign'])->name('militaires.bulk-assign');
    Route::get('/militaires/import/form', [MilitairesImportController::class, 'showForm'])->name('militaires.import');
    Route::post('/militaires/import/process', [MilitairesImportController::class, 'process'])->name('militaires.import.process');
    Route::get('/militaires/import/template/xlsx', [MilitairesImportController::class, 'downloadTemplateXlsx'])->name('militaires.import.template.xlsx');
    Route::get('/militaires/import/template/csv', [MilitairesImportController::class, 'downloadTemplateCsv'])->name('militaires.import.template.csv');
    Route::get('/militaires', [MilitaireController::class, 'index'])->name('militaires.index');
    Route::get('/militaires/create', [MilitaireController::class, 'create'])->name('militaires.create');
    Route::post('/militaires', [MilitaireController::class, 'store'])->name('militaires.store');
    Route::get('/militaires/{militaire}', [MilitaireController::class, 'show'])->name('militaires.show');
    Route::get('/militaires/{militaire}/edit', [MilitaireController::class, 'edit'])->name('militaires.edit');
    Route::put('/militaires/{militaire}', [MilitaireController::class, 'update'])->name('militaires.update');
    Route::delete('/militaires/{militaire}', [MilitaireController::class, 'destroy'])->name('militaires.destroy');

    // Eligibilites
    Route::get('/eligibilites', [EligibiliteController::class, 'index'])->name('eligibilites.index');
    Route::get('/eligibilites/filtered', [EligibiliteController::class, 'getFiltered'])->name('eligibilites.filtered');
    Route::get('/eligibilites/export', [EligibiliteController::class, 'export'])->name('eligibilites.export');

    // Tableau d'avancement
    Route::get('/avancements', [AvancementController::class, 'index'])->name('avancements.index');
    Route::post('/avancements/save', [AvancementController::class, 'save'])->name('avancements.save');
    Route::get('/avancements/export', [AvancementController::class, 'export'])->name('avancements.export');
    Route::post('/avancements/import', [AvancementController::class, 'import'])->name('avancements.import');

    // Configurations (Régions, Unités, Positions)
    Route::get('/configurations', [ConfigurationController::class, 'index'])->name('configurations.index');
    Route::post('/configurations/regions', [ConfigurationController::class, 'storeRegion'])->name('configurations.regions.store');
    Route::put('/configurations/regions/{region}', [ConfigurationController::class, 'updateRegion'])->name('configurations.regions.update');
    Route::delete('/configurations/regions/{region}', [ConfigurationController::class, 'destroyRegion'])->name('configurations.regions.destroy');

    Route::post('/configurations/unites', [ConfigurationController::class, 'storeUnite'])->name('configurations.unites.store');
    Route::put('/configurations/unites/{unite}', [ConfigurationController::class, 'updateUnite'])->name('configurations.unites.update');
    Route::delete('/configurations/unites/{unite}', [ConfigurationController::class, 'destroyUnite'])->name('configurations.unites.destroy');

    Route::post('/configurations/positions', [ConfigurationController::class, 'storePosition'])->name('configurations.positions.store');
    Route::put('/configurations/positions/{position}', [ConfigurationController::class, 'updatePosition'])->name('configurations.positions.update');
    Route::delete('/configurations/positions/{position}', [ConfigurationController::class, 'destroyPosition'])->name('configurations.positions.destroy');

    Route::post('/configurations/theatres', [ConfigurationController::class, 'storeTheatre'])->name('configurations.theatres.store');
    Route::put('/configurations/theatres/{theatre}', [ConfigurationController::class, 'updateTheatre'])->name('configurations.theatres.update');
    Route::delete('/configurations/theatres/{theatre}', [ConfigurationController::class, 'destroyTheatre'])->name('configurations.theatres.destroy');

    // Situation de Prise d'Armes (SPA) & Registres Opérationnels (100% Base de données)
    Route::get('/prise-armes', [PriseArmeController::class, 'index'])->name('prise-armes.index');
    Route::get('/prise-armes/export', [PriseArmeController::class, 'export'])->name('prise-armes.export');
    Route::get('/prise-armes/lookup-militaire', [PriseArmeController::class, 'lookupMilitaire'])->name('prise-armes.lookup-militaire');
    Route::post('/prise-armes/registres', [PriseArmeController::class, 'storeRegistre'])->name('prise-armes.registres.store');
    Route::put('/prise-armes/registres/{registre}', [PriseArmeController::class, 'updateRegistre'])->name('prise-armes.registres.update');
    Route::delete('/prise-armes/registres/{registre}', [PriseArmeController::class, 'destroyRegistre'])->name('prise-armes.registres.destroy');
    Route::post('/prise-armes/organismes', [PriseArmeController::class, 'storeOrganisme'])->name('prise-armes.organismes.store');
    Route::put('/prise-armes/organismes/{organisme}', [PriseArmeController::class, 'updateOrganisme'])->name('prise-armes.organismes.update');
    Route::delete('/prise-armes/organismes/{organisme}', [PriseArmeController::class, 'destroyOrganisme'])->name('prise-armes.organismes.destroy');
});

// Routes réservées au Super Administrateur
Route::middleware(['auth', 'super_admin'])->group(function () {
    Route::resource('users', App\Http\Controllers\UserController::class)->except(['show']);
    Route::get('/activity-logs', [App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-logs.index');
});

// Routes profil
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
