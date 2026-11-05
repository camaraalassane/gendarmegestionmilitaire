<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$grades = App\Models\Grade::orderBy('ordre')->get();
foreach ($grades as $g) {
    echo sprintf("%-4d %-25s %-20s %s ans\n", $g->ordre, $g->nom_grade, $g->type_grade, $g->retraite_obligatoire ?? 'NON DEFINI');
}
