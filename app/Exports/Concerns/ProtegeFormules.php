<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

trait ProtegeFormules
{
    /**
     * Protège contre l'injection de formules (Formula Injection / CSV Injection)
     * en forçant le type chaîne littérale explicite pour toute valeur commençant par =, +, - ou @.
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) && !is_numeric($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
