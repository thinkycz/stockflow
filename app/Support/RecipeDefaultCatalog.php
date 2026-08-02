<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Structured transcription of TEACHA-recipes.pdf.
 *
 * @phpstan-type VariantRow array{name: string|null, steps: list<string>}
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
            self::category('MATCHA LATTE', [
                self::recipe('CLASSIC MATCHA LATTE', 'no ice: S+50ml / M+100ml + 2-3 ice cubes', [
                    self::variant('S', ['100g milk + 20g sugar - stir', 'ice', '50g water (70-80 degrees) + 3,5g matcha']),
                    self::variant('M', ['140g milk + 25g sugar - stir', 'ice', '60g water + 4,5g matcha']),
                ]),
                self::recipe('MANGO/STRAWBERRY MATCHA LATTE', null, [
                    self::variant('S', ['50g mango/strawberry puree', 'ice', '100g milk', '50g water + 3,5g matcha']),
                    self::variant('M', ['60g mango/strawberry puree', 'ice', '140g milk', '60g water + 4,5g matcha']),
                ]),
                self::recipe('JASMINE OAT LATTE', null, [
                    self::variant('S', ['70g jasmine tea + 50g oat milk + 25g sugar - stir', 'ice', '50g water + 3,5g matcha']),
                    self::variant('M', ['90g jasmine tea + 70g oat milk + 35g sugar - stir', 'ice', '60g water + 4,5g matcha']),
                ]),
                self::recipe('COLD WHISK OAT LATTE', null, [
                    self::variant('S', ['100g oat milk + 20g sugar + 3,5g matcha - whip', 'pour on ice']),
                    self::variant('M', ['140g oat milk + 25g sugar + 4,5g matcha - whip', 'pour on ice']),
                ]),
            ]),
            self::category('MATCHA CLOUD', [
                self::recipe('COCONUT CLOUD', 'no ice: S+50ml / M+60ml + 2-3 ice cubes', [
                    self::variant('S', ['100g coconut water + 20g sugar - stir', 'ice', '40g smetana + 40g milk + 3,5g matcha - whip up', 'top with dried coconut (2g)']),
                    self::variant('M', ['140g coconut water + 25g sugar - stir', 'ice', '50g smetana + 50g milk + 4,5g matcha - whip up', 'top with dried coconut (2g)']),
                ]),
                self::recipe('MILKY MATCHA CLOUD', null, [
                    self::variant('S', ['100g milk + 20g salko - mix', 'ice', '40g smetana + 40g milk + 3,5g matcha - whip', 'top with matcha powder']),
                    self::variant('M', ['140g milk + 30g salko - mix', 'ice', '50g smetana + 50g milk + 4,5g matcha - whip', 'top with matcha powder']),
                ]),
                self::recipe('JASMINE TEA CLOUD', null, [
                    self::variant('S', ['100g jasmine tea + 25g sugar - stir', 'ice', '40g smetana + 40g milk + 3,5g matcha - whip', 'top with matcha powder']),
                    self::variant('M', ['140g jasmine tea + 35g sugar - stir', 'ice', '50g smetana + 50g milk + 4,5g matcha - whip', 'top with matcha powder']),
                ]),
                self::recipe('OREO MATCHA', null, [
                    self::variant('S', ['1 spoon oreo (9g)', '100g milk + 20g salko - mix', 'ice', '40g smetana + 40g milk + 3,5g matcha - whip', 'top with oreo (2-3g)']),
                    self::variant('M', ['1.5 spoons oreo (12-15g)', '140g milk + 30g salko - mix', 'ice', '50g smetana + 50g milk + 4,5g matcha - whip', 'top with oreo (2-3g)']),
                ]),
                self::recipe('LOTUS MATCHA', null, [
                    self::variant('S', ['1 spoon lotus (9g)', '100g milk + 20g salko + 10g gingerbread - mix', 'ice', '40g smetana + 40g milk + 3,5g matcha - whip', 'top with lotus (2-3g)']),
                    self::variant('M', ['1.5 spoons lotus (12-15g)', '140g milk + 30g salko + 15g gingerbread - mix', 'ice', '50g smetana + 50g milk + 4,5g matcha - whip', 'top with lotus (2-3g)']),
                ]),
            ]),
            self::category('MATCHA SPECIALS', [
                self::recipe('STRAWBERRY CLOUD', null, [
                    self::variant('S', ['100g milk + 5g sugar - stir', 'ice', '50g water + 3,5g matcha', '30g smetana + 30g milk + 30g syrup - whip up', 'top with dried strawberries (1g)']),
                    self::variant('M', ['140g milk + 10g sugar - stir', 'ice', '50g water + 4,5g matcha', '40g smetana + 40g milk + 40g syrup - whip up', 'top with dried strawberries (1g)']),
                ]),
                self::recipe('BROWN SUGAR MATCHA', null, [
                    self::variant('S', ['BS around the rim', '100g milk', 'ice', '40g smetana + 40g milk + 3,5g matcha - whip', 'top with matcha powder']),
                    self::variant('M', ['BS around the rim', '140g milk', 'ice', '50g smetana + 50g milk + 4,5g matcha - whip', 'top with matcha powder']),
                ]),
                self::recipe('HOJICHA CLOUD', null, [
                    self::variant('S', ['100g milk + 20g sugar - mix', 'ice', '40g smetana + 40g milk + 3,5g hojicha - whip', 'top with hojicha powder']),
                    self::variant('M', ['140g milk + 25g sugar - mix', 'ice', '50g smetana + 50g milk + 4,5g hojicha - whip', 'top with hojicha powder']),
                ]),
                self::recipe('DOUBLE MATCHA', null, [
                    self::variant('S', ['100g milk + 20g sugar - stir', 'ice', '50g water (70-80 degrees) + 3,5g matcha - pour on ice', '40g smetana + 40g milk + 3,5g matcha - whip', 'top with matcha powder']),
                    self::variant('M', ['140g milk + 25g sugar - stir', 'ice', '60g water + 4,5g matcha - pour on ice', '50g smetana + 50g milk + 4,5g matcha - whip', 'top with matcha powder']),
                ]),
                self::recipe('PISTACHIO MATCHA', null, [
                    self::variant('S', ['15g pistachio paste on the cup', '100g milk + 20g salko - stir', 'ice', '40g smetana + 40g milk + 3,5g matcha - whip', 'top with pistachio']),
                    self::variant('M', ['25g pistachio paste on the cup', '140g milk + 30g salko - stir', 'ice', '50g smetana + 50g milk + 4,5g matcha - whip', 'top with pistachio']),
                ]),
            ]),
            self::category('SUMMER DRINKS', [
                self::recipe('DOUBLE STRAWBERRY', null, [self::variant('M', ['30g strawberry puree - ice', '140g milk', '50g water + 4,5g matcha - whisk and pour', '40g smetana + 40g milk + 40g strawberry syrup - whip into a cloud and pour', 'top with dried strawberries'])]),
                self::recipe('EARL GREY MATCHA', null, [
                    self::variant('S', ['20g earl grey syrup - ice', '100g milk', '50g water + 3,5g matcha - whisk and pour']),
                    self::variant('M', ['25g earl grey syrup - ice', '140g milk', '60g water + 4,5g matcha - whisk and pour']),
                ]),
                self::recipe('PINA COLADA MATCHA', null, [
                    self::variant('S', ['50g pineapple puree - ice', '100g coconut milk', '50g water + 3,5g matcha - whisk and pour']),
                    self::variant('M', ['60g pineapple puree - ice', '140g coconut milk', '60g water + 4,5g matcha - whisk and pour']),
                ]),
            ]),
            self::category('FRESH FRUIT TEA', [
                self::recipe('LYCHEE TEA', 'M no ice +5ml sugar, L no ice +10ml sugar', [
                    self::variant('M', ['smash 1 lychee (2 small) + 1 lemon', '250ml jasmine tea', '20ml sugar + 30ml lychee puree + 5ml lemon syrup', 'fill up with ice - shake']),
                    self::variant('L', ['smash 2 lychees (3 small) + 2 lemons', '350ml jasmine tea', '30ml sugar + 40ml lychee puree + 5 lemon syrup', 'fill up with ice - shake']),
                ]),
                self::recipe('STRAWBERRY TEA', null, [
                    self::variant('M', ['1 spoon of strawberries', '250ml jasmine tea', '20ml sugar + 30ml strawberry puree', 'fill up with ice - shake']),
                    self::variant('L', ['1,5 spoons of strawberries', '350ml jasmine tea', '30ml sugar + 40ml strawberry puree', 'fill up with ice - shake']),
                ]),
                self::recipe('LYCHEE RED BUTTERFLY TEA', null, [
                    self::variant('M', ['A few strawberries + 1 lychee', '250ml jasmine tea', '20ml sugar + 30ml (15ml lychee puree + 15ml strawberry puree)', 'fill up with ice - shake', 'top with butterfly tea']),
                    self::variant('L', ['A few strawberries + 1 lychee', '350ml jasmine tea', '30ml sugar + 40ml (20ml lychee puree + 20ml strawberry puree)', 'fill up with ice - shake', 'top with butterfly tea']),
                ]),
                self::recipe('PASSION FRUIT TEA', null, [
                    self::variant('M', ['1 spoon of PF', '250ml jasmine tea', '25ml sugar + 15ml PF syrup + 10ml PF puree', 'fill up with ice - shake']),
                    self::variant('L', ['2 spoons of PF', '350ml jasmine tea', '35ml sugar + 20ml syrup + 15ml PF puree', 'fill up with ice - shake']),
                ]),
                self::recipe('MANGO PASSION FRUIT TEA', null, [
                    self::variant('M', ['half PF/mango', '250ml jasmine tea', '20ml sugar + 30ml PF (15ml PF syrup + 15ml mango puree)', 'fill up with ice - shake']),
                    self::variant('L', ['half PF/mango', '350ml jasmine tea', '30ml sugar + 40ml (20ml PF syrup + 20ml mango puree)', 'fill up with ice - shake']),
                ]),
                self::recipe('MANGO LEMON TEA', null, [
                    self::variant('M', ['smash 1 mango slice, 1 lemon', '250ml jasmine tea', '20ml sugar + 30ml mango puree + 5ml lemon', 'fill up with ice - shake']),
                    self::variant('L', ['smash 2 mango slices, 2 lemons', '350ml jasmine tea', '30ml sugar + 40ml mango puree + 5ml lemon', 'fill up with ice - shake']),
                ]),
                self::recipe('LEMON ICE TEA', null, [
                    self::variant('M', ['2 lemon slices', '250ml ceylon tea', '25ml sugar + 25ml lemon syrup', 'fill up with ice - shake']),
                    self::variant('L', ['3 lemon slices', '350ml ceylon tea', '35ml sugar + 35ml lemon syrup', 'fill up with ice - shake']),
                ]),
                self::recipe('VIETNAMESE PEACH ICE TEA', null, [
                    self::variant('M', ['smash 1 peach slice, 1 orange, lemongrass', '250ml ceylon tea', '20ml sugar + 30ml peach puree + 5ml lemon syrup', 'fill up with ice - shake']),
                    self::variant('L', ['smash 1 peach slice, 1 orange, lemongrass', '350ml ceylon tea', '30ml sugar + 40ml peach puree + 5ml lemon syrup', 'fill up with ice - shake']),
                ]),
                self::recipe('DRAGON FRUIT PEACH TEA', null, [
                    self::variant('M', ['200ml jasmine tea', '20ml sugar + 30ml peach puree', 'fill up with ice - shake', '1.5 sp DF - smash - pour on ice']),
                    self::variant('L', ['300ml jasmine tea', '30ml sugar + 40ml peach puree', 'fill up with ice - shake', '2 sp DF - smash - pour on ice']),
                ]),
                self::recipe('DRAGON PASSION FRUIT TEA', null, [
                    self::variant('M', ['1 sp PF', '200ml jasmine tea', '20ml sugar + 30ml PF syrup', 'fill up with ice - shake', '1.5 sp DF - smash - pour on ice']),
                    self::variant('L', ['1.5 sp PF', '300ml jasmine tea', '30ml sugar + 40ml PF syrup', 'fill up with ice - shake', '2 sp DF - smash - pour on ice']),
                ]),
                self::recipe('PINEAPPLE TEA', null, [
                    self::variant('M', ['1 spoon pineapple, 1 lemon', '250ml jasmine tea', '20ml sugar + 30ml pineapple puree + 5ml lemon syrup', 'fill up with ice - shake']),
                    self::variant('L', ['2 spoons pineapple, 2 lemons', '350ml jasmine tea', '30ml sugar + 40ml pineapple puree + 5ml lemon syrup', 'fill up with ice - shake']),
                ]),
                self::recipe('PINEAPPLE BERRY TEA', null, [
                    self::variant('M', ['1 spoon pineapple, 1sp strawberry', '250ml jasmine tea', '20ml sugar + 15ml pineapple puree + 15ml strawberry puree', 'fill up with ice - shake']),
                    self::variant('L', ['1.5 spoons pineapple, 1.5sp strawberry', '350ml jasmine tea', '30ml sugar + 20ml pineapple puree + 20ml strawberry puree', 'fill up with ice - shake']),
                ]),
                self::recipe('JASMINE TEA', '-10', [
                    self::variant('M', ['250ml jasmine tea', '40ml sugar', 'fill up with ice - shake']),
                    self::variant('L', ['350ml jasmine tea', '50ml sugar', 'fill up with ice - shake']),
                ]),
            ]),
            self::category('MILK TEA', [
                self::recipe('CEYLON/JASMINE/OOLONG MILK TEA', null, [
                    self::variant('M (-5)', ['250ml milk tea', '30ml sugar', 'fill up with ice - shake']),
                    self::variant('L (-10)', ['350ml milk tea', '40ml sugar', 'fill up with ice - shake']),
                ]),
                self::recipe('BROWN SUGAR MILK TEA/FRESH MILK', null, [
                    self::variant('M', ['250ml ceylon milk tea/fresh milk', '5ml sugar + 25ml BS syrup', 'fill up with ice - shake', 'syrup around the rim']),
                    self::variant('L', ['350ml ceylon milk tea/fresh milk', '5ml sugar + 35ml BS syrup', 'fill up with ice - shake', 'syrup around the rim']),
                ]),
                self::recipe('TARO MILK TEA', null, [
                    self::variant('M', ['100ml hot water + 2sp powdered milk + 1.5sp taro', '20ml sugar - mix', 'milk to 300ml and fill up with ice - shake']),
                    self::variant('L', ['100ml hot water + 3sp powdered milk + 2sp taro', '30ml sugar - mix', 'milk to 400ml and fill up with ice - shake']),
                ]),
                self::recipe('TARO COCO MILK TEA', null, [
                    self::variant('M', ['100ml hot water + 2sp powdered milk + 1.5sp taro', '20ml sugar - mix', 'coconut milk to 300ml and fill up with ice - shake']),
                    self::variant('L', ['100ml hot water + 3sp powdered milk + 2sp taro', '30ml sugar - mix', 'coconut milk to 400ml and fill up with ice - shake']),
                ]),
                self::recipe('MATCHA MILK TEA', '-10', [
                    self::variant('M', ['200ml jasmine milk tea', '45ml sugar', '3.5g matcha + 50g water - whisk', 'fill up with ice - shake']),
                    self::variant('L', ['300ml jasmine milk tea', '55ml sugar', '4.5g matcha + 60g water - whisk', 'fill up with ice - shake']),
                ]),
                self::recipe('STRAWBERRY MILK TEA', null, [
                    self::variant('M', ['strawberries - smash', '250ml jasmine milk tea', '10ml sugar + 30ml syrup (milk tea syrup)', 'fill up with ice - shake']),
                    self::variant('L', ['strawberries - smash', '350ml jasmine milk tea', '15ml sugar + 35ml syrup (milk tea syrup)', 'fill up with ice - shake']),
                ]),
            ]),
            self::category('CREAMY COCONUT', [
                self::recipe('STRAWBERRY COCO', null, [
                    self::variant('S', ['1sp of strawberries', 'ice', '100g coconut water', 'Cloud: 30g smetana + 30g milk + 30g syrup', 'Coconut flakes on top']),
                    self::variant('M', ['1,5sp of strawberries', 'ice', '140g coconut water', 'Cloud: 40g smetana + 40g milk + 40g syrup', 'Coconut flakes on top']),
                ]),
                self::recipe('MANGO COCO', null, [
                    self::variant('S', ['2 mango', 'ice', '100g coconut water', 'Cloud: 30g smetana + 30g milk + 30g puree', 'Coconut flakes on top']),
                    self::variant('M', ['3 mango', 'ice', '140g coconut water', 'Cloud: 40g smetana + 40g milk + 40g puree', 'Coconut flakes on top']),
                ]),
                self::recipe('LYCHEE COCO', null, [
                    self::variant('S', ['2 lychee', 'ice', '100g coconut water', 'Cloud: 30g smetana + 30g milk + 30g puree', 'Coconut flakes on top']),
                    self::variant('M', ['3 lychee', 'ice', '140g coconut water', 'Cloud: 40g smetana + 40g milk + 40g puree', 'Coconut flakes on top']),
                ]),
            ]),
            self::category('PREPARATIONS', [
                self::recipe('BLACK TAPIOCA', "500g - 150g sugar - 100ml water - 30ml BS syrup\n700g - 210g sugar - 150ml water - 40ml BS syrup", [
                    self::variant(null, ['4l hot water in a pot - bring to a boil', '1kg of tapioca into boiling water', 'stir well and reduce to simmer (cca 1500)', 'set a timer for 40min - stir occasionaly', 'cover for 40min', 'wash in warm water', 'cook in 300g of sugar + 200ml water + 50ml BS syrup for a few minutes']),
                ]),
                self::recipe('SUGAR machine', null, [self::variant(null, ['1kg sugar + 600ml hot water into a pot (water first)', 'bring to a boil on max then stop', 'let cool a bit before pouring in'])]),
                self::recipe('JASMINE TEA (steep)', null, [self::variant(null, ['35g tea + 2.5l 90 degree water', 'set a timer for 10min', 'ice up to 4l'])]),
                self::recipe('JASMINE MILK TEA (steep)', null, [self::variant(null, ['30g tea + 2.5l 90 degree water', 'set a timer for 10min', '600g powdered milk - stir well', 'ice up to 3.5l'])]),
                self::recipe('CEYLON TEA (cook)', null, [self::variant(null, ['60g tea + 2.5l water', 'Boil and simmer 10min - cover with a lid', 'Ice up to 3.5l'])]),
                self::recipe('CEYLON MILK TEA (3.5l) (cook)', '60g ceylon + 30g yunnan + 10g oolong (into 2 teabags)', [self::variant(null, ['2.5l hot water + 100g tea (cook 10min - cover with a lid)', '+ 900g powdered milk', '+ ice up to 3.5l'])]),
                self::recipe('OOLONG MILK TEA (3.5l) (steep)', "70g oolong + 30g ceylon (into 2 teabags)\n(1l - 20g oolong + 10g ceylon + 1l water + 300g powdered milk + ice up to 1.5l)", [self::variant(null, ['2.5l water (90 degrees) + 100g tea (let steep for 10min)', '+ 900g powdered milk', '+ ice up to 3.5l'])]),
                self::recipe('BUTTERFLY TEA', null, [self::variant(null, ['5g tea + 300ml hot water - steep till black', 'Ice up to 600ml'])]),
                self::recipe('Cream Cheese', 'use up in 5 days', [
                    self::variant('Batch', ['250ml smetana', '100g salko', '100g cream cheese', '90ml milk', 'Mix till thick']),
                    self::variant('1 portion', ['50g smetana + 20g salko + 20g milk + 10g cheese', 'whip up']),
                ]),
                self::recipe('Creme Brulee', 'use up in 5 days', [self::variant(null, ['250ml smetana', '3 spoons of creme brulee powder', '50ml milk', '50g salko', 'mix 7-10min until thick'])]),
            ]),
        ];
    }

    /**
     * @param list<RecipeRow> $recipes
     *
     * @return CategoryRow
     */
    private static function category(string $name, array $recipes): array { return ['name' => $name, 'recipes' => $recipes]; }

    /**
     * @param list<VariantRow> $variants
     *
     * @return RecipeRow
     */
    private static function recipe(string $name, string|null $note, array $variants): array { return ['name' => $name, 'note' => $note, 'variants' => $variants]; }

    /**
     * @param list<string> $steps
     *
     * @return VariantRow
     */
    private static function variant(string|null $name, array $steps): array { return ['name' => $name, 'steps' => $steps]; }
}
