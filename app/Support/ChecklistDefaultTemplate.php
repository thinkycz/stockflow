<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ChecklistShiftEnum;
use App\Enums\ChecklistTemplateScopeEnum;

final class ChecklistDefaultTemplate
{
    /**
     * @return list<array{scope: string, weekday: int|null, shift: string, text: string, position: int}>
     */
    public static function tasks(): array
    {
        $groups = [
            [ChecklistTemplateScopeEnum::Daily, null, ChecklistShiftEnum::Morning, [
                'Rozsvítit světla a reklamy',
                'Zapnout TV, kasu, horkou vodu, delivery + zapojit',
                'Odkrýt toppingy, vyleštit a doplnit',
                'Uvařit čaje, cukr, doplnit ovoce, kelímky, brčka…',
                'Uvařit tapioku (700 g)',
                'Otřít skla, plochy, kasu, lednice, celý stánek',
                'Spočítat kasu (depozit 1 500 Kč nebo 3 000 Kč)',
                'Vystavit + doplnit hračky',
            ]],
            [ChecklistTemplateScopeEnum::Daily, null, ChecklistShiftEnum::Afternoon, [
                'Umýt shakery, matcha space',
                'Uklidit dřez a vše kolem',
                'Umýt naběračky a nádobu na tapioku',
                'Přikrýt toppingy',
                'Zamést a vytřít',
                'Vynést všechen odpad',
                'Otřít všechny plochy',
                'Spočítat tržbu, zapsat a vyfotit',
                'Vypnout delivery, kasu, TV, horkou vodu, lisovač',
                'Vše znovu zkontrolovat – zhasnout a zamknout',
            ]],
            [ChecklistTemplateScopeEnum::Weekly, 1, ChecklistShiftEnum::Morning, ['Lednice – odledovat, vytřít do sucha, zorganizovat a doplnit', 'Otřít všechny plochy, poličky a dveře']],
            [ChecklistTemplateScopeEnum::Weekly, 1, ChecklistShiftEnum::Afternoon, ['Vyleštit všechna skla', 'Vyvařit hadry a nechat usušit']],
            [ChecklistTemplateScopeEnum::Weekly, 2, ChecklistShiftEnum::Morning, ['Dřezy – vydrhnout jedlou sodou, zalít horkou vodou + odkapávače', 'Nádoby na čaje a odměrky – pořádně přemýt', 'Otřít všechny plochy, poličky a dveře']],
            [ChecklistTemplateScopeEnum::Weekly, 2, ChecklistShiftEnum::Afternoon, ['Vyleštit všechna skla', 'Umýt koš']],
            [ChecklistTemplateScopeEnum::Weekly, 3, ChecklistShiftEnum::Morning, ['Umýt všechna víčka od toppingů', 'Ledovač a mrazák – vytřít zevnitř i zvenku', 'Otřít všechny plochy, poličky a dveře']],
            [ChecklistTemplateScopeEnum::Weekly, 3, ChecklistShiftEnum::Afternoon, ['Vyleštit všechna skla', 'Vyvařit hadry a nechat usušit']],
            [ChecklistTemplateScopeEnum::Weekly, 4, ChecklistShiftEnum::Morning, ['Skříňky – vytřít, zorganizovat + kontrola stavu', 'Konvice a odkapávač – odvápnit kyselinou citronovou', 'Otřít všechny plochy, poličky a dveře']],
            [ChecklistTemplateScopeEnum::Weekly, 4, ChecklistShiftEnum::Afternoon, ['Vyleštit všechna skla', 'WC']],
            [ChecklistTemplateScopeEnum::Weekly, 5, ChecklistShiftEnum::Morning, ['Skleněné nádoby – pořádně vymýt a vysušit', 'Umýt víčka od sirupů', 'Otřít všechny plochy, poličky a dveře']],
            [ChecklistTemplateScopeEnum::Weekly, 5, ChecklistShiftEnum::Afternoon, ['Vyleštit všechna skla', 'Vyvařit hadry a nechat usušit']],
            [ChecklistTemplateScopeEnum::Weekly, 6, ChecklistShiftEnum::Morning, ['Otřít mřížky, televizi, cukrovar, stroj na horkou vodu', 'Shakery – pořádně umýt víčka', 'Otřít všechny plochy, poličky a dveře']],
            [ChecklistTemplateScopeEnum::Weekly, 6, ChecklistShiftEnum::Afternoon, ['Vyleštit všechna skla', 'Pořádně vytřít pod skříňkami']],
            [ChecklistTemplateScopeEnum::Weekly, 7, ChecklistShiftEnum::Morning, ['Lisovač', 'Sklad – zorganizovat', 'Hrnce – vydrbat drátěnkou', 'Otřít všechny plochy, poličky a dveře']],
            [ChecklistTemplateScopeEnum::Weekly, 7, ChecklistShiftEnum::Afternoon, ['Vyleštit všechna skla', 'WC']],
        ];

        $tasks = [];
        foreach ($groups as [$scope, $weekday, $shift, $texts]) {
            foreach ($texts as $position => $text) {
                $tasks[] = [
                    'scope' => $scope->value,
                    'weekday' => $weekday,
                    'shift' => $shift->value,
                    'text' => $text,
                    'position' => $position + 1,
                ];
            }
        }

        return $tasks;
    }
}
