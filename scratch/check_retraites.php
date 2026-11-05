<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- GRADES EN BASE ---\n";
foreach (App\Models\Grade::all() as $g) {
    echo "Grade: '{$g->nom_grade}' (code: '{$g->code_grade}') -> Retraite: {$g->retraite_obligatoire} ans\n";
}

echo "\n--- 10 PREMIERS MILITAIRES ---\n";
foreach (App\Models\Militaire::take(10)->get() as $m) {
    $ageRetraite = App\Models\Grade::getRetraiteForGrade($m->grade_actuel);
    $dateRetraite = $m->calculerDateRetraite();
    echo "{$m->matricule} | {$m->nom} {$m->prenom} | Grade: '{$m->grade_actuel}' | Naiss: " . ($m->date_naissance?->format('Y-m-d') ?? 'NUL') . " | Age: {$m->age} | Limite: " . ($ageRetraite ?? 'AUCUNE') . " | DateRetraite: " . ($dateRetraite?->format('Y-m-d') ?? 'AUCUNE') . " | Statut: {$m->statut}\n";
}

$oldest = App\Models\Militaire::where('statut', 'actif')->orderBy('date_naissance', 'asc')->take(10)->get();
echo "\n--- LES PLUS AGES EN STATUT ACTIF ---\n";
foreach ($oldest as $m) {
    $ageRetraite = App\Models\Grade::getRetraiteForGrade($m->grade_actuel);
    $dateRetraite = $m->calculerDateRetraite();
    $estDepasse = $m->estRetraiteDepassee() ? 'OUI' : 'NON';
    echo "{$m->matricule} | {$m->nom} | Grade: '{$m->grade_actuel}' | Naiss: " . ($m->date_naissance?->format('Y-m-d') ?? 'NUL') . " | Age: {$m->age} | Limite: " . ($ageRetraite ?? 'AUCUNE') . " | DateRetraite: " . ($dateRetraite?->format('Y-m-d') ?? 'AUCUNE') . " | Depasse: {$estDepasse}\n";
}
