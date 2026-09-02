<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Curated canonical recipe catalog.
 *
 * @phpstan-type InstructionRow array{type: string, text: string, action_key: string, quantity_value: float|int|null, quantity_text: string|null, unit: string|null, ingredient_name: string|null, target: string|null, icon_group: string, source_text: string|null, is_inferred: bool}
 * @phpstan-type VariantRow array{name: string|null, instructions: list<InstructionRow>}
 * @phpstan-type RecipeRow array{name: string, note: string|null, variants: list<VariantRow>}
 * @phpstan-type CategoryRow array{name: string, recipes: list<RecipeRow>}
 */
class RecipeDefaultCatalog
{
    /**
     * @return list<CategoryRow>
     */
    public static function categories(): array
    {
        return [
            self::category('MATCHA LATTE', self::matchaLatteRecipes()),
            self::category('MATCHA CLOUD', self::matchaCloudRecipes()),
            self::category('MATCHA SPECIALS', self::matchaSpecialRecipes()),
            self::category('SUMMER DRINKS', self::summerDrinkRecipes()),
            self::category('FRESH FRUIT TEA', self::freshFruitTeaRecipes()),
            self::category('MILK TEA', self::milkTeaRecipes()),
            self::category('CREAMY COCONUT', self::creamyCoconutRecipes()),
            self::category('PREPARATIONS', self::preparationRecipes()),
        ];
    }

    /**
     * @return list<RecipeRow>
     */
    private static function matchaLatteRecipes(): array
    {
        return [
            self::recipe('CLASSIC MATCHA LATTE', null, [
                ...self::drinkVariants('S', self::classicMatchaInstructions(100, 20, 50, 3.5), 'milk', 'serving cup', 5, 50),
                ...self::drinkVariants('M', self::classicMatchaInstructions(140, 25, 60, 4.5), 'milk', 'serving cup', 10, 100),
            ]),
            self::recipe('MANGO/STRAWBERRY MATCHA LATTE', null, [
                ...self::drinkVariants('Mango — S', self::fruitMatchaLatteInstructions('mango purée', 50, 100, 50, 3.5), 'milk', 'serving cup', 5),
                ...self::drinkVariants('Mango — M', self::fruitMatchaLatteInstructions('mango purée', 60, 140, 60, 4.5), 'milk', 'serving cup', 10),
                ...self::drinkVariants('Strawberry — S', self::fruitMatchaLatteInstructions('strawberry purée', 50, 100, 50, 3.5), 'milk', 'serving cup', 5),
                ...self::drinkVariants('Strawberry — M', self::fruitMatchaLatteInstructions('strawberry purée', 60, 140, 60, 4.5), 'milk', 'serving cup', 10),
            ]),
            self::recipe('JASMINE OAT LATTE', null, [
                ...self::drinkVariants('S', self::jasmineOatInstructions(70, 50, 25, 50, 3.5), 'oat milk', 'serving cup', 5),
                ...self::drinkVariants('M', self::jasmineOatInstructions(90, 70, 35, 60, 4.5), 'oat milk', 'serving cup', 10),
            ]),
            self::recipe('COLD WHISK OAT LATTE', null, [
                ...self::drinkVariants('S', self::coldWhiskInstructions(100, 20, 3.5), 'oat milk', 'serving cup', 5),
                ...self::drinkVariants('M', self::coldWhiskInstructions(140, 25, 4.5), 'oat milk', 'serving cup', 10),
            ]),
        ];
    }

    /**
     * @return list<RecipeRow>
     */
    private static function matchaCloudRecipes(): array
    {
        return [
            self::recipe('COCONUT CLOUD', null, [
                ...self::drinkVariants('S', self::matchaCloudInstructions('coconut water', 100, 'liquid sugar', 20, 40, 3.5, 'dried coconut', 2), 'coconut water', 'serving cup', 5, 50),
                ...self::drinkVariants('M', self::matchaCloudInstructions('coconut water', 140, 'liquid sugar', 25, 50, 4.5, 'dried coconut', 2), 'coconut water', 'serving cup', 10, 60),
            ]),
            self::recipe('MILKY MATCHA CLOUD', null, [
                ...self::drinkVariants('S', self::matchaCloudInstructions('milk', 100, 'sweetened condensed milk (Salko)', 20, 40, 3.5, 'matcha powder'), 'milk', 'serving cup', 5),
                ...self::drinkVariants('M', self::matchaCloudInstructions('milk', 140, 'sweetened condensed milk (Salko)', 30, 50, 4.5, 'matcha powder'), 'milk', 'serving cup', 10),
            ]),
            self::recipe('JASMINE TEA CLOUD', null, [
                ...self::drinkVariants('S', self::matchaCloudInstructions('jasmine tea', 100, 'liquid sugar', 25, 40, 3.5, 'matcha powder'), 'jasmine tea', 'serving cup', 5),
                ...self::drinkVariants('M', self::matchaCloudInstructions('jasmine tea', 140, 'liquid sugar', 35, 50, 4.5, 'matcha powder'), 'jasmine tea', 'serving cup', 10),
            ]),
            self::recipe('OREO MATCHA', null, [
                ...self::drinkVariants('S', self::crumbMatchaInstructions('Oreo crumbs', 9, 100, 20, 40, 3.5, '2–3'), 'milk', 'serving cup', 5),
                ...self::drinkVariants('M', self::crumbMatchaInstructions('Oreo crumbs', null, 140, 30, 50, 4.5, '2–3', '12–15'), 'milk', 'serving cup', 10),
            ]),
            self::recipe('LOTUS MATCHA', null, [
                ...self::drinkVariants('S', self::lotusMatchaInstructions(9, null, 100, 20, 10, 40, 3.5), 'milk', 'serving cup', 5),
                ...self::drinkVariants('M', self::lotusMatchaInstructions(null, '12–15', 140, 30, 15, 50, 4.5), 'milk', 'serving cup', 10),
            ]),
        ];
    }

    /**
     * @return list<RecipeRow>
     */
    private static function matchaSpecialRecipes(): array
    {
        return [
            self::recipe('STRAWBERRY CLOUD', null, [
                ...self::drinkVariants('S', self::strawberryCloudInstructions(100, 5, 50, 3.5, 30), 'milk', 'serving cup', 5),
                ...self::drinkVariants('M', self::strawberryCloudInstructions(140, 10, 50, 4.5, 40), 'milk', 'serving cup', 10),
            ]),
            self::recipe('BROWN SUGAR MATCHA', null, [
                ...self::drinkVariants('S', self::brownSugarMatchaInstructions(100, 40, 3.5), 'milk', 'serving cup', 5),
                ...self::drinkVariants('M', self::brownSugarMatchaInstructions(140, 50, 4.5), 'milk', 'serving cup', 10),
            ]),
            self::recipe('HOJICHA CLOUD', null, [
                ...self::drinkVariants('S', self::hojichaCloudInstructions(100, 20, 40, 3.5), 'milk', 'serving cup', 5),
                ...self::drinkVariants('M', self::hojichaCloudInstructions(140, 25, 50, 4.5), 'milk', 'serving cup', 10),
            ]),
            self::recipe('DOUBLE MATCHA', null, [
                ...self::drinkVariants('S', self::doubleMatchaInstructions(100, 20, 50, 3.5, 40), 'milk', 'serving cup', 5),
                ...self::drinkVariants('M', self::doubleMatchaInstructions(140, 25, 60, 4.5, 50), 'milk', 'serving cup', 10),
            ]),
            self::recipe('PISTACHIO MATCHA', null, [
                ...self::drinkVariants('S', self::pistachioMatchaInstructions(15, 100, 20, 40, 3.5), 'milk', 'serving cup', 5),
                ...self::drinkVariants('M', self::pistachioMatchaInstructions(25, 140, 30, 50, 4.5), 'milk', 'serving cup', 10),
            ]),
        ];
    }

    /**
     * @return list<RecipeRow>
     */
    private static function summerDrinkRecipes(): array
    {
        return [
            self::recipe('DOUBLE STRAWBERRY', 'Available in size M only.', [
                ...self::drinkVariants('M', self::doubleStrawberryInstructions(), 'milk', 'serving cup', 5),
            ]),
            self::recipe('EARL GREY MATCHA', null, [
                ...self::drinkVariants('S', self::simpleFlavouredMatchaInstructions('Earl Grey syrup', 20, 'milk', 100, 50, 3.5), 'milk', 'serving cup', 5),
                ...self::drinkVariants('M', self::simpleFlavouredMatchaInstructions('Earl Grey syrup', 25, 'milk', 140, 60, 4.5), 'milk', 'serving cup', 10),
            ]),
            self::recipe('PINA COLADA MATCHA', null, [
                ...self::drinkVariants('S', self::simpleFlavouredMatchaInstructions('pineapple purée', 50, 'coconut milk', 100, 50, 3.5), 'coconut milk', 'serving cup', 5),
                ...self::drinkVariants('M', self::simpleFlavouredMatchaInstructions('pineapple purée', 60, 'coconut milk', 140, 60, 4.5), 'coconut milk', 'serving cup', 10),
            ]),
        ];
    }

    /**
     * @return list<RecipeRow>
     */
    private static function freshFruitTeaRecipes(): array
    {
        return [
            self::recipe('LYCHEE TEA', null, [
                ...self::drinkVariants('M', self::lycheeTeaInstructions(1, 2, 1, 250, 20, 30), 'jasmine tea', 'shaker', 5),
                ...self::drinkVariants('L', self::lycheeTeaInstructions(2, 3, 2, 350, 30, 40), 'jasmine tea', 'shaker', 10),
            ]),
            self::recipe('STRAWBERRY TEA', null, [
                ...self::drinkVariants('M', self::fruitTeaInstructions([self::scoop(1, 'strawberry pieces', 'shaker')], 'jasmine tea', 250, 20, [['strawberry purée', 30]]), 'jasmine tea', 'shaker', 5),
                ...self::drinkVariants('L', self::fruitTeaInstructions([self::scoop(1.5, 'strawberry pieces', 'shaker')], 'jasmine tea', 350, 30, [['strawberry purée', 40]]), 'jasmine tea', 'shaker', 10),
            ]),
            self::recipe('LYCHEE RED BUTTERFLY TEA', null, [
                ...self::drinkVariants('M', self::butterflyFruitTeaInstructions(250, 20, 15, 15), 'jasmine tea', 'shaker', 5),
                ...self::drinkVariants('L', self::butterflyFruitTeaInstructions(350, 30, 20, 20), 'jasmine tea', 'shaker', 10),
            ]),
            self::recipe('PASSION FRUIT TEA', null, [
                ...self::drinkVariants('M', self::fruitTeaInstructions([self::scoop(1, 'passion fruit pulp', 'shaker')], 'jasmine tea', 250, 25, [['passion fruit syrup', 15], ['passion fruit purée', 10]]), 'jasmine tea', 'shaker', 5),
                ...self::drinkVariants('L', self::fruitTeaInstructions([self::scoop(2, 'passion fruit pulp', 'shaker')], 'jasmine tea', 350, 35, [['passion fruit syrup', 20], ['passion fruit purée', 15]]), 'jasmine tea', 'shaker', 10),
            ]),
            self::recipe('MANGO PASSION FRUIT TEA', null, [
                ...self::drinkVariants('M', self::fruitTeaInstructions([self::scoop(0.5, 'passion fruit pulp', 'shaker'), self::scoop(0.5, 'mango pieces', 'shaker')], 'jasmine tea', 250, 20, [['passion fruit syrup', 15], ['mango purée', 15]]), 'jasmine tea', 'shaker', 5),
                ...self::drinkVariants('L', self::fruitTeaInstructions([self::scoop(0.5, 'passion fruit pulp', 'shaker'), self::scoop(0.5, 'mango pieces', 'shaker')], 'jasmine tea', 350, 30, [['passion fruit syrup', 20], ['mango purée', 20]]), 'jasmine tea', 'shaker', 10),
            ]),
            self::recipe('MANGO LEMON TEA', null, [
                ...self::drinkVariants('M', self::smashedFruitTeaInstructions('mango slice', 1, 'lemon slice', 1, 'jasmine tea', 250, 20, 'mango purée', 30, 5), 'jasmine tea', 'shaker', 5),
                ...self::drinkVariants('L', self::smashedFruitTeaInstructions('mango slices', 2, 'lemon slices', 2, 'jasmine tea', 350, 30, 'mango purée', 40, 5), 'jasmine tea', 'shaker', 10),
            ]),
            self::recipe('LEMON ICE TEA', null, [
                ...self::drinkVariants('M', self::fruitTeaInstructions([self::ingredient(2, null, null, 'lemon slices', 'shaker')], 'Ceylon tea', 250, 25, [['lemon syrup', 25]]), 'Ceylon tea', 'shaker', 5),
                ...self::drinkVariants('L', self::fruitTeaInstructions([self::ingredient(3, null, null, 'lemon slices', 'shaker')], 'Ceylon tea', 350, 35, [['lemon syrup', 35]]), 'Ceylon tea', 'shaker', 10),
            ]),
            self::recipe('VIETNAMESE PEACH ICE TEA', null, [
                ...self::drinkVariants('M', self::vietnamesePeachInstructions(250, 20, 30), 'Ceylon tea', 'shaker', 5),
                ...self::drinkVariants('L', self::vietnamesePeachInstructions(350, 30, 40), 'Ceylon tea', 'shaker', 10),
            ]),
            self::recipe('DRAGON FRUIT PEACH TEA', null, [
                ...self::drinkVariants('M', self::dragonFruitTeaInstructions(200, 20, 'peach purée', 30, 1.5), 'jasmine tea', 'shaker', 5),
                ...self::drinkVariants('L', self::dragonFruitTeaInstructions(300, 30, 'peach purée', 40, 2), 'jasmine tea', 'shaker', 10),
            ]),
            self::recipe('DRAGON PASSION FRUIT TEA', null, [
                ...self::drinkVariants('M', self::dragonPassionFruitInstructions(200, 20, 30, 1, 1.5), 'jasmine tea', 'shaker', 5),
                ...self::drinkVariants('L', self::dragonPassionFruitInstructions(300, 30, 40, 1.5, 2), 'jasmine tea', 'shaker', 10),
            ]),
            self::recipe('PINEAPPLE TEA', null, [
                ...self::drinkVariants('M', self::pineappleTeaInstructions(1, 1, 250, 20, 30), 'jasmine tea', 'shaker', 5),
                ...self::drinkVariants('L', self::pineappleTeaInstructions(2, 2, 350, 30, 40), 'jasmine tea', 'shaker', 10),
            ]),
            self::recipe('PINEAPPLE BERRY TEA', null, [
                ...self::drinkVariants('M', self::pineappleBerryInstructions(1, 250, 20, 15), 'jasmine tea', 'shaker', 5),
                ...self::drinkVariants('L', self::pineappleBerryInstructions(1.5, 350, 30, 20), 'jasmine tea', 'shaker', 10),
            ]),
            self::recipe('JASMINE TEA', null, [
                ...self::drinkVariants('M', self::fruitTeaInstructions([], 'jasmine tea', 250, 40, []), 'jasmine tea', 'shaker', 5),
                ...self::drinkVariants('L', self::fruitTeaInstructions([], 'jasmine tea', 350, 50, []), 'jasmine tea', 'shaker', 10),
            ]),
        ];
    }

    /**
     * @return list<RecipeRow>
     */
    private static function milkTeaRecipes(): array
    {
        $plainVariants = [];
        foreach (['Ceylon milk tea', 'jasmine milk tea', 'oolong milk tea'] as $base) {
            $label = Str::headline(Str::before($base, ' milk tea'));
            $plainVariants = [...$plainVariants, ...self::drinkVariants($label . ' — M', self::plainMilkTeaInstructions($base, 250, 30), $base, 'shaker', 5), ...self::drinkVariants($label . ' — L', self::plainMilkTeaInstructions($base, 350, 40), $base, 'shaker', 10)];
        }

        $brownSugarVariants = [];
        foreach (['Ceylon milk tea', 'fresh milk'] as $base) {
            $label = Str::headline($base);
            $brownSugarVariants = [...$brownSugarVariants, ...self::drinkVariants($label . ' — M', self::brownSugarMilkInstructions($base, 250, 25), $base, 'shaker', 5), ...self::drinkVariants($label . ' — L', self::brownSugarMilkInstructions($base, 350, 35), $base, 'shaker', 10)];
        }

        return [
            self::recipe('CEYLON/JASMINE/OOLONG MILK TEA', null, $plainVariants),
            self::recipe('BROWN SUGAR MILK TEA/FRESH MILK', null, $brownSugarVariants),
            self::recipe('TARO MILK TEA', null, [
                ...self::drinkVariants('M', self::taroMilkTeaInstructions('milk', 2, 1.5, 20, 300), 'milk', 'shaker', 5),
                ...self::drinkVariants('L', self::taroMilkTeaInstructions('milk', 3, 2, 30, 400), 'milk', 'shaker', 10),
            ]),
            self::recipe('TARO COCO MILK TEA', null, [
                ...self::drinkVariants('M', self::taroMilkTeaInstructions('coconut milk', 2, 1.5, 20, 300), 'coconut milk', 'shaker', 5),
                ...self::drinkVariants('L', self::taroMilkTeaInstructions('coconut milk', 3, 2, 30, 400), 'coconut milk', 'shaker', 10),
            ]),
            self::recipe('MATCHA MILK TEA', null, [
                ...self::drinkVariants('M', self::matchaMilkTeaInstructions(200, 45, 50, 3.5), 'jasmine milk tea', 'shaker', 5),
                ...self::drinkVariants('L', self::matchaMilkTeaInstructions(300, 55, 60, 4.5), 'jasmine milk tea', 'shaker', 10),
            ]),
            self::recipe('STRAWBERRY MILK TEA', null, [
                ...self::drinkVariants('M', self::strawberryMilkTeaInstructions(250, 10, 30), 'jasmine milk tea', 'shaker', 5),
                ...self::drinkVariants('L', self::strawberryMilkTeaInstructions(350, 15, 35), 'jasmine milk tea', 'shaker', 10),
            ]),
        ];
    }

    /**
     * @return list<RecipeRow>
     */
    private static function creamyCoconutRecipes(): array
    {
        return [
            self::recipe('STRAWBERRY COCO', null, [...self::drinkVariants('S', self::creamyCoconutInstructions(self::scoop(1, 'strawberry pieces', 'serving cup'), 'strawberry syrup', 100, 30), 'coconut water', 'serving cup', 5), ...self::drinkVariants('M', self::creamyCoconutInstructions(self::scoop(1.5, 'strawberry pieces', 'serving cup'), 'strawberry syrup', 140, 40), 'coconut water', 'serving cup', 10)]),
            self::recipe('MANGO COCO', null, [...self::drinkVariants('S', self::creamyCoconutInstructions(self::ingredient(2, null, null, 'mango pieces', 'serving cup'), 'mango purée', 100, 30), 'coconut water', 'serving cup', 5), ...self::drinkVariants('M', self::creamyCoconutInstructions(self::ingredient(3, null, null, 'mango pieces', 'serving cup'), 'mango purée', 140, 40), 'coconut water', 'serving cup', 10)]),
            self::recipe('LYCHEE COCO', null, [...self::drinkVariants('S', self::creamyCoconutInstructions(self::ingredient(2, null, null, 'lychees', 'serving cup'), 'lychee purée', 100, 30), 'coconut water', 'serving cup', 5), ...self::drinkVariants('M', self::creamyCoconutInstructions(self::ingredient(3, null, null, 'lychees', 'serving cup'), 'lychee purée', 140, 40), 'coconut water', 'serving cup', 10)]),
        ];
    }

    /**
     * @return list<RecipeRow>
     */
    private static function preparationRecipes(): array
    {
        return [
            self::recipe('BLACK TAPIOCA', null, [self::variant('500 g batch', self::blackTapiocaInstructions(500, 150, 100, 30)), self::variant('700 g batch', self::blackTapiocaInstructions(700, 210, 150, 40)), self::variant('1 kg batch', self::blackTapiocaInstructions(1000, 300, 200, 50))]),
            self::recipe('SUGAR MACHINE', null, [self::variant(null, [self::ingredient(600, null, 'ml', 'hot water', 'pot'), self::ingredient(1, null, 'kg', 'granulated sugar', 'pot'), self::action('Bring to a boil on maximum heat, then turn off the heat immediately.', 'boil'), self::action('Allow the liquid sugar to cool slightly.', 'cool'), self::action('Pour the liquid sugar into the sugar machine.', 'pour')])]),
            self::recipe('JASMINE TEA PREPARATION', null, [self::variant(null, [self::ingredient(35, null, 'g', 'jasmine tea', 'tea bag'), self::ingredient(2.5, null, 'L', 'water at 90 °C', 'container'), self::action('Steep for 10 minutes.', 'steep'), self::action('Add ice until the total volume reaches 4 L.', 'ice')])]),
            self::recipe('JASMINE MILK TEA PREPARATION', null, [self::variant(null, [self::ingredient(30, null, 'g', 'jasmine tea', 'tea bag'), self::ingredient(2.5, null, 'L', 'water at 90 °C', 'container'), self::action('Steep for 10 minutes.', 'steep'), self::ingredient(600, null, 'g', 'powdered milk', 'container'), self::action('Stir until completely dissolved.', 'stir'), self::action('Add ice until the total volume reaches 3.5 L.', 'ice')])]),
            self::recipe('CEYLON TEA PREPARATION', null, [self::variant(null, [self::ingredient(60, null, 'g', 'Ceylon tea', 'pot'), self::ingredient(2.5, null, 'L', 'water', 'pot'), self::action('Bring to a boil, cover, and simmer for 10 minutes.', 'boil'), self::action('Add ice until the total volume reaches 3.5 L.', 'ice')])]),
            self::recipe('CEYLON MILK TEA PREPARATION', null, [self::variant('3.5 L batch', [self::ingredient(60, null, 'g', 'Ceylon tea', 'two tea bags'), self::ingredient(30, null, 'g', 'Yunnan tea', 'two tea bags'), self::ingredient(10, null, 'g', 'oolong tea', 'two tea bags'), self::ingredient(2.5, null, 'L', 'hot water', 'pot'), self::action('Cook the covered tea for 10 minutes.', 'cook'), self::ingredient(900, null, 'g', 'powdered milk', 'pot'), self::action('Stir until completely dissolved.', 'stir'), self::action('Add ice until the total volume reaches 3.5 L.', 'ice')])]),
            self::recipe('OOLONG MILK TEA PREPARATION', null, [self::variant('3.5 L batch', self::oolongMilkTeaInstructions(70, 30, 2.5, 900, 3.5, true)), self::variant('1.5 L batch', self::oolongMilkTeaInstructions(20, 10, 1, 300, 1.5, false))]),
            self::recipe('BUTTERFLY TEA', null, [self::variant(null, [self::ingredient(5, null, 'g', 'butterfly pea tea', 'container'), self::ingredient(300, null, 'ml', 'hot water', 'container'), self::action('Steep until the tea turns black.', 'steep'), self::action('Add ice until the total volume reaches 600 ml.', 'ice')])]),
            self::recipe('CREAM CHEESE', 'Use within 5 days.', [self::variant('Batch', [self::ingredient(250, null, 'ml', 'whipping cream', 'mixing bowl'), self::ingredient(100, null, 'ml', 'sweetened condensed milk (Salko)', 'mixing bowl'), self::ingredient(100, null, 'g', 'cream cheese', 'mixing bowl'), self::ingredient(90, null, 'ml', 'milk', 'mixing bowl'), self::action('Mix until thick.', 'mix')]), self::variant('1 portion', [self::ingredient(50, null, 'ml', 'whipping cream', 'mixing bowl'), self::ingredient(20, null, 'ml', 'sweetened condensed milk (Salko)', 'mixing bowl'), self::ingredient(20, null, 'ml', 'milk', 'mixing bowl'), self::ingredient(10, null, 'g', 'cream cheese', 'mixing bowl'), self::action('Whip until thick.', 'whip')])]),
            self::recipe('CREME BRULEE', 'Use within 5 days.', [self::variant(null, [self::ingredient(250, null, 'ml', 'whipping cream', 'mixing bowl'), self::ingredient(3, null, 'standard scoops', 'crème brûlée powder', 'mixing bowl'), self::ingredient(50, null, 'ml', 'milk', 'mixing bowl'), self::ingredient(50, null, 'ml', 'sweetened condensed milk (Salko)', 'mixing bowl'), self::action('Mix for 7–10 minutes, until thick.', 'mix')])]),
        ];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function classicMatchaInstructions(int $milk, int $sugar, int $water, float $matcha): array
    {
        return [self::ingredient($milk, null, 'ml', 'milk', 'serving cup'), self::ingredient($sugar, null, 'ml', 'liquid sugar', 'serving cup'), self::action('Stir until combined.', 'stir'), self::action('Fill the serving cup with ice.', 'ice'), ...self::whiskedMatchaInstructions($water, $matcha), self::action('Pour the matcha into the serving cup.', 'pour')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function fruitMatchaLatteInstructions(string $purée, int $puréeAmount, int $milk, int $water, float $matcha): array
    {
        return [self::ingredient($puréeAmount, null, 'ml', $purée, 'serving cup'), self::action('Fill the serving cup with ice.', 'ice'), self::ingredient($milk, null, 'ml', 'milk', 'serving cup'), ...self::whiskedMatchaInstructions($water, $matcha), self::action('Pour the matcha into the serving cup.', 'pour')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function jasmineOatInstructions(int $tea, int $milk, int $sugar, int $water, float $matcha): array
    {
        return [self::ingredient($tea, null, 'ml', 'jasmine tea', 'serving cup'), self::ingredient($milk, null, 'ml', 'oat milk', 'serving cup'), self::ingredient($sugar, null, 'ml', 'liquid sugar', 'serving cup'), self::action('Stir until combined.', 'stir'), self::action('Fill the serving cup with ice.', 'ice'), ...self::whiskedMatchaInstructions($water, $matcha), self::action('Pour the matcha into the serving cup.', 'pour')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function coldWhiskInstructions(int $milk, int $sugar, float $matcha): array
    {
        return [self::ingredient($milk, null, 'ml', 'oat milk', 'mixing bowl'), self::ingredient($sugar, null, 'ml', 'liquid sugar', 'mixing bowl'), self::ingredient($matcha, null, 'g', 'matcha', 'mixing bowl'), self::action('Whip until smooth.', 'whip'), self::action('Fill the serving cup with ice.', 'ice'), self::action('Pour the whipped latte into the serving cup.', 'pour')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function matchaCloudInstructions(string $base, int $baseAmount, string $sweetener, int $sweetenerAmount, int $cloudAmount, float $matcha, string $garnish, int|null $garnishAmount = null): array
    {
        return [self::ingredient($baseAmount, null, 'ml', $base, 'serving cup'), self::ingredient($sweetenerAmount, null, 'ml', $sweetener, 'serving cup'), self::action('Stir until combined.', 'stir'), self::action('Fill the serving cup with ice.', 'ice'), ...self::cloudInstructions($cloudAmount, $matcha, 'matcha'), self::action('Pour the matcha cloud into the serving cup.', 'pour'), self::garnish($garnish, $garnishAmount, $garnishAmount === null ? 'as needed' : null)];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function crumbMatchaInstructions(string $crumbs, int|null $crumbAmount, int $milk, int $salko, int $cloudAmount, float $matcha, string $toppingAmount, string|null $crumbAmountText = null): array
    {
        return [self::ingredient($crumbAmount, $crumbAmountText, 'g', $crumbs, 'serving cup'), self::ingredient($milk, null, 'ml', 'milk', 'serving cup'), self::ingredient($salko, null, 'ml', 'sweetened condensed milk (Salko)', 'serving cup'), self::action('Stir until combined.', 'stir'), self::action('Fill the serving cup with ice.', 'ice'), ...self::cloudInstructions($cloudAmount, $matcha, 'matcha'), self::action('Pour the matcha cloud into the serving cup.', 'pour'), self::garnish($crumbs, null, $toppingAmount . ' g')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function lotusMatchaInstructions(int|null $crumbAmount, string|null $crumbAmountText, int $milk, int $salko, int $gingerbread, int $cloudAmount, float $matcha): array
    {
        return [self::ingredient($crumbAmount, $crumbAmountText, 'g', 'Lotus biscuit crumbs', 'serving cup'), self::ingredient($milk, null, 'ml', 'milk', 'serving cup'), self::ingredient($salko, null, 'ml', 'sweetened condensed milk (Salko)', 'serving cup'), self::ingredient($gingerbread, null, 'ml', 'gingerbread syrup', 'serving cup'), self::action('Stir until combined.', 'stir'), self::action('Fill the serving cup with ice.', 'ice'), ...self::cloudInstructions($cloudAmount, $matcha, 'matcha'), self::action('Pour the matcha cloud into the serving cup.', 'pour'), self::garnish('Lotus biscuit crumbs', null, '2–3 g')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function strawberryCloudInstructions(int $milk, int $sugar, int $water, float $matcha, int $cloudAmount): array
    {
        return [self::ingredient($milk, null, 'ml', 'milk', 'serving cup'), self::ingredient($sugar, null, 'ml', 'liquid sugar', 'serving cup'), self::action('Stir until combined.', 'stir'), self::action('Fill the serving cup with ice.', 'ice'), ...self::whiskedMatchaInstructions($water, $matcha), self::action('Pour the matcha into the serving cup.', 'pour'), self::ingredient($cloudAmount, null, 'ml', 'whipping cream', 'mixing bowl'), self::ingredient($cloudAmount, null, 'ml', 'milk', 'mixing bowl'), self::ingredient($cloudAmount, null, 'ml', 'strawberry syrup', 'mixing bowl'), self::action('Whip until thick.', 'whip'), self::action('Pour the strawberry cloud into the serving cup.', 'pour'), self::garnish('dried strawberries', 1)];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function brownSugarMatchaInstructions(int $milk, int $cloudAmount, float $matcha): array
    {
        return [self::garnish('brown sugar syrup', null, 'as needed', 'Coat the inside rim of the serving cup with brown sugar syrup.'), self::ingredient($milk, null, 'ml', 'milk', 'serving cup'), self::action('Fill the serving cup with ice.', 'ice'), ...self::cloudInstructions($cloudAmount, $matcha, 'matcha'), self::action('Pour the matcha cloud into the serving cup.', 'pour'), self::garnish('matcha powder')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function hojichaCloudInstructions(int $milk, int $sugar, int $cloudAmount, float $hojicha): array
    {
        return [self::ingredient($milk, null, 'ml', 'milk', 'serving cup'), self::ingredient($sugar, null, 'ml', 'liquid sugar', 'serving cup'), self::action('Stir until combined.', 'stir'), self::action('Fill the serving cup with ice.', 'ice'), self::ingredient($cloudAmount, null, 'ml', 'whipping cream', 'mixing bowl'), self::ingredient($cloudAmount, null, 'ml', 'milk', 'mixing bowl'), self::ingredient($hojicha, null, 'g', 'hojicha', 'mixing bowl', 'Add ' . self::number($hojicha) . ' g hojicha prepared with water at 70–80 °C to the mixing bowl.'), self::action('Whip until thick.', 'whip'), self::action('Pour the hojicha cloud into the serving cup.', 'pour'), self::garnish('hojicha powder')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function doubleMatchaInstructions(int $milk, int $sugar, int $water, float $matcha, int $cloudAmount): array
    {
        return [self::ingredient($milk, null, 'ml', 'milk', 'serving cup'), self::ingredient($sugar, null, 'ml', 'liquid sugar', 'serving cup'), self::action('Stir until combined.', 'stir'), self::action('Fill the serving cup with ice.', 'ice'), ...self::whiskedMatchaInstructions($water, $matcha), self::action('Pour the matcha into the serving cup.', 'pour'), ...self::cloudInstructions($cloudAmount, $matcha, 'matcha'), self::action('Pour the matcha cloud into the serving cup.', 'pour'), self::garnish('matcha powder')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function pistachioMatchaInstructions(int $paste, int $milk, int $salko, int $cloudAmount, float $matcha): array
    {
        return [self::ingredient($paste, null, 'g', 'pistachio paste', 'serving cup', 'Spread ' . $paste . ' g pistachio paste inside the serving cup.'), self::ingredient($milk, null, 'ml', 'milk', 'serving cup'), self::ingredient($salko, null, 'ml', 'sweetened condensed milk (Salko)', 'serving cup'), self::action('Stir until combined.', 'stir'), self::action('Fill the serving cup with ice.', 'ice'), ...self::cloudInstructions($cloudAmount, $matcha, 'matcha'), self::action('Pour the matcha cloud into the serving cup.', 'pour'), self::garnish('chopped pistachios')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function doubleStrawberryInstructions(): array
    {
        return [self::ingredient(30, null, 'ml', 'strawberry purée', 'serving cup'), self::action('Fill the serving cup with ice.', 'ice'), self::ingredient(140, null, 'ml', 'milk', 'serving cup'), ...self::whiskedMatchaInstructions(50, 4.5), self::action('Pour the matcha into the serving cup.', 'pour'), self::ingredient(40, null, 'ml', 'whipping cream', 'mixing bowl'), self::ingredient(40, null, 'ml', 'milk', 'mixing bowl'), self::ingredient(40, null, 'ml', 'strawberry syrup', 'mixing bowl'), self::action('Whip until thick.', 'whip'), self::action('Pour the strawberry cloud into the serving cup.', 'pour'), self::garnish('dried strawberries')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function simpleFlavouredMatchaInstructions(string $flavour, int $flavourAmount, string $base, int $baseAmount, int $water, float $matcha): array
    {
        return [self::ingredient($flavourAmount, null, 'ml', $flavour, 'serving cup'), self::action('Fill the serving cup with ice.', 'ice'), self::ingredient($baseAmount, null, 'ml', $base, 'serving cup'), ...self::whiskedMatchaInstructions($water, $matcha), self::action('Pour the matcha into the serving cup.', 'pour')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function lycheeTeaInstructions(int $regularLychees, int $smallLychees, int $lemonSlices, int $tea, int $sugar, int $purée): array
    {
        return self::fruitTeaInstructions([self::ingredient(null, $regularLychees . ' regular or ' . $smallLychees . ' small', null, 'lychees', 'shaker'), self::ingredient($lemonSlices, null, null, $lemonSlices === 1 ? 'lemon slice' : 'lemon slices', 'shaker'), self::action('Smash the fruit.', 'smash')], 'jasmine tea', $tea, $sugar, [['lychee purée', $purée], ['lemon syrup', 5]]);
    }

    /**
     * @param list<InstructionRow> $opening
     * @param list<array{0: string, 1: int}> $flavours
     *
     * @return list<InstructionRow>
     */
    private static function fruitTeaInstructions(array $opening, string $teaName, int $tea, int $sugar, array $flavours): array
    {
        $instructions = [...$opening, self::ingredient($tea, null, 'ml', $teaName, 'shaker'), self::ingredient($sugar, null, 'ml', 'liquid sugar', 'shaker')];
        foreach ($flavours as [$name, $amount]) {
            $instructions[] = self::ingredient($amount, null, 'ml', $name, 'shaker');
        }

        return [...$instructions, self::action('Fill the shaker with ice.', 'ice'), self::action('Shake well.', 'shake'), self::action('Pour into the serving cup.', 'pour')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function butterflyFruitTeaInstructions(int $tea, int $sugar, int $lycheePurée, int $strawberryPurée): array
    {
        return [...self::fruitTeaInstructions([self::ingredient(null, 'a few', null, 'strawberry pieces', 'shaker'), self::ingredient(1, null, null, 'lychee', 'shaker')], 'jasmine tea', $tea, $sugar, [['lychee purée', $lycheePurée], ['strawberry purée', $strawberryPurée]]), self::garnish('butterfly pea tea', null, 'as needed', 'Top with butterfly pea tea.')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function smashedFruitTeaInstructions(string $firstName, int $firstAmount, string $secondName, int $secondAmount, string $teaName, int $tea, int $sugar, string $puréeName, int $purée, int $lemonSyrup): array
    {
        return self::fruitTeaInstructions([self::ingredient($firstAmount, null, null, $firstName, 'shaker'), self::ingredient($secondAmount, null, null, $secondName, 'shaker'), self::action('Smash the fruit.', 'smash')], $teaName, $tea, $sugar, [[$puréeName, $purée], ['lemon syrup', $lemonSyrup]]);
    }

    /**
     * @return list<InstructionRow>
     */
    private static function vietnamesePeachInstructions(int $tea, int $sugar, int $purée): array
    {
        return self::fruitTeaInstructions([self::ingredient(1, null, null, 'peach slice', 'shaker'), self::ingredient(1, null, null, 'orange slice', 'shaker'), self::ingredient(null, 'as needed', null, 'lemongrass', 'shaker'), self::action('Smash the fruit and lemongrass.', 'smash')], 'Ceylon tea', $tea, $sugar, [['peach purée', $purée], ['lemon syrup', 5]]);
    }

    /**
     * @return list<InstructionRow>
     */
    private static function dragonFruitTeaInstructions(int $tea, int $sugar, string $flavourName, int $flavour, float $dragonFruit): array
    {
        return [self::ingredient($tea, null, 'ml', 'jasmine tea', 'shaker'), self::ingredient($sugar, null, 'ml', 'liquid sugar', 'shaker'), self::ingredient($flavour, null, 'ml', $flavourName, 'shaker'), self::action('Fill the shaker with ice.', 'ice'), self::action('Shake well.', 'shake'), self::scoop($dragonFruit, 'dragon fruit pieces', 'serving cup'), self::action('Smash the dragon fruit in the serving cup.', 'smash'), self::action('Pour the shaken tea over the dragon fruit.', 'pour')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function dragonPassionFruitInstructions(int $tea, int $sugar, int $syrup, float $passionFruit, float $dragonFruit): array
    {
        return [self::scoop($passionFruit, 'passion fruit pulp', 'shaker'), ...self::dragonFruitTeaInstructions($tea, $sugar, 'passion fruit syrup', $syrup, $dragonFruit)];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function pineappleTeaInstructions(float $pineapple, int $lemonSlices, int $tea, int $sugar, int $purée): array
    {
        return self::fruitTeaInstructions([self::scoop($pineapple, 'pineapple pieces', 'shaker'), self::ingredient($lemonSlices, null, null, $lemonSlices === 1 ? 'lemon slice' : 'lemon slices', 'shaker')], 'jasmine tea', $tea, $sugar, [['pineapple purée', $purée], ['lemon syrup', 5]]);
    }

    /**
     * @return list<InstructionRow>
     */
    private static function pineappleBerryInstructions(float $scoops, int $tea, int $sugar, int $purée): array
    {
        return self::fruitTeaInstructions([self::scoop($scoops, 'pineapple pieces', 'shaker'), self::scoop($scoops, 'strawberry pieces', 'shaker')], 'jasmine tea', $tea, $sugar, [['pineapple purée', $purée], ['strawberry purée', $purée]]);
    }

    /**
     * @return list<InstructionRow>
     */
    private static function plainMilkTeaInstructions(string $base, int $amount, int $sugar): array
    {
        return [self::ingredient($amount, null, 'ml', $base, 'shaker'), self::ingredient($sugar, null, 'ml', 'liquid sugar', 'shaker'), self::action('Fill the shaker with ice.', 'ice'), self::action('Shake well.', 'shake'), self::action('Pour into the serving cup.', 'pour')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function brownSugarMilkInstructions(string $base, int $amount, int $syrup): array
    {
        return [self::garnish('brown sugar syrup', null, 'as needed', 'Coat the inside rim of the serving cup with brown sugar syrup.'), self::ingredient($amount, null, 'ml', $base, 'shaker'), self::ingredient(5, null, 'ml', 'liquid sugar', 'shaker'), self::ingredient($syrup, null, 'ml', 'brown sugar syrup', 'shaker'), self::action('Fill the shaker with ice.', 'ice'), self::action('Shake well.', 'shake'), self::action('Pour into the prepared serving cup.', 'pour')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function taroMilkTeaInstructions(string $milk, int $powderedMilkScoops, float $taroScoops, int $sugar, int $mixtureVolume): array
    {
        return [self::ingredient(100, null, 'ml', 'hot water', 'shaker'), self::ingredient($powderedMilkScoops, null, 'standard scoops', 'powdered milk', 'shaker'), self::ingredient($taroScoops, null, 'standard scoops', 'taro powder', 'shaker'), self::ingredient($sugar, null, 'ml', 'liquid sugar', 'shaker'), self::action('Mix until completely dissolved.', 'mix'), self::action('Add ' . $milk . ' until the mixture reaches ' . $mixtureVolume . ' ml.', 'add'), self::action('Fill the shaker with ice.', 'ice'), self::action('Shake well.', 'shake'), self::action('Pour into the serving cup.', 'pour')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function matchaMilkTeaInstructions(int $tea, int $sugar, int $water, float $matcha): array
    {
        return [self::ingredient($tea, null, 'ml', 'jasmine milk tea', 'shaker'), self::ingredient($sugar, null, 'ml', 'liquid sugar', 'shaker'), ...self::whiskedMatchaInstructions($water, $matcha), self::action('Pour the matcha into the shaker.', 'pour'), self::action('Fill the shaker with ice.', 'ice'), self::action('Shake well.', 'shake'), self::action('Pour into the serving cup.', 'pour')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function strawberryMilkTeaInstructions(int $tea, int $sugar, int $syrup): array
    {
        return [self::ingredient(null, 'as needed', null, 'strawberry pieces', 'shaker'), self::action('Smash the strawberries.', 'smash'), self::ingredient($tea, null, 'ml', 'jasmine milk tea', 'shaker'), self::ingredient($sugar, null, 'ml', 'liquid sugar', 'shaker'), self::ingredient($syrup, null, 'ml', 'milk tea syrup', 'shaker'), self::action('Fill the shaker with ice.', 'ice'), self::action('Shake well.', 'shake'), self::action('Pour into the serving cup.', 'pour')];
    }

    /**
     * @param InstructionRow $fruit
     *
     * @return list<InstructionRow>
     */
    private static function creamyCoconutInstructions(array $fruit, string $flavour, int $coconutWater, int $cloudAmount): array
    {
        return [$fruit, self::action('Fill the serving cup with ice.', 'ice'), self::ingredient($coconutWater, null, 'ml', 'coconut water', 'serving cup'), self::ingredient($cloudAmount, null, 'ml', 'whipping cream', 'mixing bowl'), self::ingredient($cloudAmount, null, 'ml', 'milk', 'mixing bowl'), self::ingredient($cloudAmount, null, 'ml', $flavour, 'mixing bowl'), self::action('Whip until thick.', 'whip'), self::action('Pour the cloud into the serving cup.', 'pour'), self::garnish('coconut flakes')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function blackTapiocaInstructions(int $tapioca, int $sugar, int $water, int $syrup): array
    {
        return [self::ingredient(4, null, 'L', 'water', 'pot'), self::action('Bring the water to a boil.', 'boil'), self::ingredient($tapioca >= 1000 ? 1 : $tapioca, null, $tapioca >= 1000 ? 'kg' : 'g', 'black tapioca', 'pot'), self::action('Stir thoroughly and reduce to approximately heat setting 1500.', 'stir'), self::action('Simmer for 40 minutes, stirring occasionally.', 'timer'), self::action('Cover and rest for 40 minutes.', 'cover'), self::action('Rinse the cooked tapioca with warm water.', 'wash'), self::ingredient($sugar, null, 'g', 'granulated sugar', 'pot'), self::ingredient($water, null, 'ml', 'water', 'pot'), self::ingredient($syrup, null, 'ml', 'brown sugar syrup', 'pot'), self::action('Cook the rinsed tapioca in the syrup mixture for a few minutes.', 'cook')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function oolongMilkTeaInstructions(int $oolong, int $ceylon, float $water, int $powderedMilk, float $finalVolume, bool $twoTeaBags): array
    {
        $target = $twoTeaBags ? 'two tea bags' : 'tea bag';

        return [self::ingredient($oolong, null, 'g', 'oolong tea', $target), self::ingredient($ceylon, null, 'g', 'Ceylon tea', $target), self::ingredient($water, null, 'L', 'water at 90 °C', 'container'), self::action('Steep for 10 minutes.', 'steep'), self::ingredient($powderedMilk, null, 'g', 'powdered milk', 'container'), self::action('Stir until completely dissolved.', 'stir'), self::action('Add ice until the total volume reaches ' . self::number($finalVolume) . ' L.', 'ice')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function whiskedMatchaInstructions(int $water, float $matcha): array
    {
        return [self::ingredient($water, null, 'ml', 'water at 70–80 °C', 'matcha bowl'), self::ingredient($matcha, null, 'g', 'matcha', 'matcha bowl'), self::action('Whisk until smooth.', 'whisk')];
    }

    /**
     * @return list<InstructionRow>
     */
    private static function cloudInstructions(int $amount, float $powder, string $powderName): array
    {
        return [self::ingredient($amount, null, 'ml', 'whipping cream', 'mixing bowl'), self::ingredient($amount, null, 'ml', 'milk', 'mixing bowl'), self::ingredient($powder, null, 'g', $powderName, 'mixing bowl'), self::action('Whip until thick.', 'whip')];
    }

    /**
     * @param list<InstructionRow> $instructions
     *
     * @return list<VariantRow>
     */
    private static function drinkVariants(string $label, array $instructions, string $mainLiquid, string $iceTarget, int $sugarIncrease, int|null $exactLiquidIncrease = null): array
    {
        $noIce = [];
        $mainLiquidAdjusted = false;
        $sugarAdjusted = false;
        $mainLiquidPosition = null;
        $icePosition = null;
        foreach ($instructions as $instruction) {
            if ($instruction['type'] === 'ingredient' && $instruction['ingredient_name'] === 'liquid sugar' && $instruction['quantity_value'] !== null) {
                $instruction = self::withQuantity($instruction, $instruction['quantity_value'] + $sugarIncrease);
                $sugarAdjusted = true;
            }
            if (!$mainLiquidAdjusted && $exactLiquidIncrease !== null && $instruction['type'] === 'ingredient' && $instruction['ingredient_name'] === $mainLiquid && $instruction['quantity_value'] !== null) {
                $instruction = self::withQuantity($instruction, $instruction['quantity_value'] + $exactLiquidIncrease);
                $mainLiquidAdjusted = true;
            }
            if ($instruction['type'] === 'action' && $instruction['action_key'] === 'ice') {
                $noIce[] = self::ingredient(null, '2–3', null, 'ice cubes', $iceTarget, 'Add 2–3 ice cubes to the ' . $iceTarget . ' for chilling.');
                $icePosition = \array_key_last($noIce);

                continue;
            }
            $noIce[] = $instruction;
            if ($instruction['type'] === 'ingredient' && $instruction['ingredient_name'] === $mainLiquid && $instruction['target'] === $iceTarget) {
                $mainLiquidPosition = \array_key_last($noIce);
            }
        }
        $adjustmentPosition = $mainLiquidPosition === null
            ? \count($noIce)
            : \max($icePosition ?? -1, $mainLiquidPosition) + 1;
        if (!$sugarAdjusted) {
            $sugarInstructions = [self::ingredient($sugarIncrease, null, 'ml', 'liquid sugar', $iceTarget)];
            if (!\collect($instructions)->contains(static fn(array $instruction): bool => $instruction['type'] === 'action' && $instruction['action_key'] === 'stir')) {
                $sugarInstructions[] = self::action('Stir until combined.', 'stir');
            }
            \array_splice($noIce, $adjustmentPosition, 0, $sugarInstructions);
            $adjustmentPosition += \count($sugarInstructions);
        }
        if ($exactLiquidIncrease === null) {
            \array_splice($noIce, $adjustmentPosition, 0, [self::action('Top up with ' . $mainLiquid . ' to the standard serving line.', 'add')]);
        }

        return [self::variant($label . ' — With ice', $instructions), self::variant($label . ' — No ice', $noIce)];
    }

    /**
     * @param InstructionRow $instruction
     *
     * @return InstructionRow
     */
    private static function withQuantity(array $instruction, float|int $quantity): array
    {
        $instruction['quantity_value'] = $quantity;
        $instruction['quantity_text'] = null;
        $instruction['text'] = self::ingredientText($quantity, null, $instruction['unit'], $instruction['ingredient_name'] ?? '', $instruction['target']);
        $instruction['source_text'] = $instruction['text'];

        return $instruction;
    }

    /**
     * @return InstructionRow
     */
    private static function scoop(float|int $quantity, string $name, string $target): array
    {
        return self::ingredient($quantity, null, $quantity === 1 ? 'standard scoop' : 'standard scoops', $name, $target);
    }

    /**
     * @return InstructionRow
     */
    private static function garnish(string $name, int|null $quantity = null, string|null $quantityText = 'as needed', string|null $text = null): array
    {
        $unit = null;
        if ($quantity !== null) {
            $quantityText = null;
            $unit = 'g';
        } elseif ($quantityText !== null && \str_ends_with($quantityText, ' g')) {
            $quantityText = \mb_substr($quantityText, 0, -2);
            $unit = 'g';
        }
        $amount = $quantity !== null ? self::number($quantity) : $quantityText;
        $display = $text ?? 'Garnish with ' . ($amount !== null ? $amount . ($unit !== null ? ' ' . $unit : '') . ' ' : '') . $name . '.';

        return ['type' => 'ingredient', 'text' => $display, 'action_key' => 'garnish', 'quantity_value' => $quantity, 'quantity_text' => $quantityText, 'unit' => $unit, 'ingredient_name' => $name, 'target' => 'serving cup', 'icon_group' => 'topping_garnish', 'source_text' => $display, 'is_inferred' => false];
    }

    /**
     * @return InstructionRow
     */
    private static function ingredient(float|int|null $quantity, string|null $quantityText, string|null $unit, string $name, string|null $target, string|null $text = null): array
    {
        $display = $text ?? self::ingredientText($quantity, $quantityText, $unit, $name, $target);

        return ['type' => 'ingredient', 'text' => $display, 'action_key' => 'add', 'quantity_value' => $quantity, 'quantity_text' => $quantityText, 'unit' => $unit, 'ingredient_name' => $name, 'target' => $target, 'icon_group' => self::iconGroup($name), 'source_text' => $display, 'is_inferred' => false];
    }

    /**
     * @return InstructionRow
     */
    private static function action(string $text, string $actionKey): array
    {
        return ['type' => 'action', 'text' => $text, 'action_key' => $actionKey, 'quantity_value' => null, 'quantity_text' => null, 'unit' => null, 'ingredient_name' => null, 'target' => null, 'icon_group' => 'neutral', 'source_text' => $text, 'is_inferred' => false];
    }

    /**
     * Build readable text from structured ingredient metadata.
     */
    private static function ingredientText(float|int|null $quantity, string|null $quantityText, string|null $unit, string $name, string|null $target): string
    {
        $amount = $quantityText ?? ($quantity !== null ? self::number($quantity) : null);
        $amountWithUnit = \mb_trim(\implode(' ', \array_filter([$amount, $unit], static fn(string|null $value): bool => $value !== null && $value !== '')));

        return 'Add ' . ($amountWithUnit !== '' ? $amountWithUnit . ' ' : '') . $name . ($target !== null ? ' to ' . $target : '') . '.';
    }

    /**
     * Resolve the curated icon group for an ingredient.
     */
    private static function iconGroup(string $name): string
    {
        $value = Str::lower($name);
        if (Str::contains($value, 'ice')) {
            return 'ice';
        }
        if (Str::contains($value, ['sugar', 'syrup'])) {
            return 'syrup_sweetener';
        }
        if (Str::contains($value, ['tea', 'matcha', 'hojicha'])) {
            return 'tea_matcha';
        }
        if (Str::contains($value, ['mango', 'strawber', 'lychee', 'lemon', 'pineapple', 'peach', 'orange', 'passion fruit', 'dragon fruit'])) {
            return 'fruit';
        }
        if (Str::contains($value, ['whipping cream', 'condensed milk', 'cream cheese'])) {
            return 'milk_foam';
        }
        if (Str::contains($value, ['milk', 'water'])) {
            return 'water_milk';
        }
        if (Str::contains($value, ['powder', 'tapioca', 'oreo', 'lotus', 'pistachio'])) {
            return 'powder';
        }
        if (Str::contains($value, ['crumb', 'flake', 'dried'])) {
            return 'topping_garnish';
        }

        return 'neutral';
    }

    /**
     * Format a canonical decimal without trailing zeroes.
     */
    private static function number(float|int $number): string
    {
        return \rtrim(\rtrim(\number_format((float) $number, 3, '.', ''), '0'), '.');
    }

    /**
     * @param list<RecipeRow> $recipes
     *
     * @return CategoryRow
     */
    private static function category(string $name, array $recipes): array
    {
        return ['name' => $name, 'recipes' => $recipes];
    }

    /**
     * @param list<VariantRow> $variants
     *
     * @return RecipeRow
     */
    private static function recipe(string $name, string|null $note, array $variants): array
    {
        return ['name' => $name, 'note' => $note, 'variants' => $variants];
    }

    /**
     * @param list<InstructionRow> $instructions
     *
     * @return VariantRow
     */
    private static function variant(string|null $name, array $instructions): array
    {
        return ['name' => $name, 'instructions' => $instructions];
    }
}
