<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reassign all products currently under "Baking Ingredients" (cat 8) to specific subcategories.
 *
 * Groups:
 *  11 = Leavening Agents        — bread improvers, preservatives, stabilizers (gelatine)
 *  14 = Frosting & Fillings     — dairy (milk, condensed, evaporated, cream cheese), fats/oils,
 *                                  savory filling ingredients, sago/tapioca
 *  15 = Buttercream             — butter, margarine, lard, shortening, chiffon oil (primary fat ingredients)
 *  17 = Fruit Fillings          — jelly products, nata de coco
 *  36 = Fondant & Gum Paste     — fondant improver
 *  37 = Edible Decorations      — cheese toppings, nuts, seeds, floss, nori, rice paper,
 *                                  desiccated coconut, crumbs, biscoff, corn/mushroom toppings
 *  61 = Whipping Cream          — all purpose cream, full cream milk
 *   9 = Flour                   — waffle mix (dry mix/flour-based)
 */
return new class extends Migration
{
    public function up(): void
    {
        $mappings = [
            // Fondant & Gum Paste (36)
            17  => 36,  // Gummix Fondant Improver 20g

            // Leavening Agents (11) — baking additives, preservatives, stabilizers
            46  => 11,  // Gelatine 50g
            110 => 11,  // Super Anti Amag 1kg (bread improver)
            111 => 11,  // Superpan 1kg (dough conditioner)
            112 => 11,  // Unisoft 1kg (bread softener)
            313 => 11,  // Nether Mold Inhibitor

            // Buttercream (15) — butter, margarine, lard, oils used as fat base
            23  => 15,  // Butter Blend Cook n' Bake 1kg
            41  => 15,  // Chiffon Oil 800g
            308 => 15,  // Buttercup 200g
            317 => 15,  // Margarine 1kg
            406 => 15,  // Lard (shortening) 1kg
            407 => 15,  // Lard (shortening) 1/2kg
            408 => 15,  // Dari Creme (classic)

            // Frosting & Fillings (14) — dairy, condensed/evaporated milk, cream cheese, savory fillings
            81  => 14,  // Dairymont Cream Cheese 2kg
            281 => 14,  // Milkboy (milk powder) 1kl
            286 => 14,  // Milkboy (milk powder) 1/2kl
            291 => 14,  // Creamer 1kl
            292 => 14,  // Creamer 1/2kl
            321 => 14,  // Nestle Cucumber 140g
            322 => 14,  // Nestle Blue lemonade 140g
            323 => 14,  // Angel Evaporated milk 410ml
            324 => 14,  // Angel Condensed 380g
            326 => 14,  // Jersey evaporated milk 370ml
            327 => 14,  // Jersey Condensed 1kl
            328 => 14,  // Jersey Condensed 390g
            329 => 14,  // Jersey Condense (Buko Pandan) 390g
            330 => 14,  // Jersey Condense (Mango) 390g
            331 => 14,  // Jersey Condensed (Chocolate) 390g
            332 => 14,  // Jersey Condensed (Melon) 390g
            343 => 14,  // Maggi Oyster Sauce 2l (savory filling ingredient)
            445 => 14,  // Cream Cheese Magnolia
            447 => 14,  // Cream Cheese (Anchor) 1kg
            460 => 14,  // Sago Straw
            488 => 14,  // Tapioca Pearl 1kg

            // Fruit Fillings (17) — jelly, nata de coco
            344 => 17,  // Rainbow Fruit Jelly 2.5kg
            345 => 17,  // Nata Jelly 2.5kg
            346 => 17,  // WanWan Syrup Nata De Coco 3.8kg

            // Whipping Cream (61) — cream/full cream products
            320 => 61,  // All Purpose Cream 250g (NESTLE)
            461 => 61,  // Jersey Full Cream 1liter

            // Flour (9) — dry baking mixes
            352 => 9,   // Waffle Mix 1kg

            // Edible Decorations (37) — toppings, seeds, nuts, cheese, floss, nori, rice paper
            37  => 37,  // Pork Floss 1kg
            38  => 37,  // Chicken Floss 1kg
            39  => 37,  // Pork Floss 200g
            40  => 37,  // Chicken Floss 200g
            274 => 37,  // Desiccated Coconut
            289 => 37,  // Sesame seeds 1/2kl
            290 => 37,  // Sesame seeds 1/4kl
            316 => 37,  // Daily Quezo 160g
            333 => 37,  // Jolly Mushroom (Whole) 400g
            334 => 37,  // Jolly Young Corn (Whole) 425g
            335 => 37,  // Jolly Cream Corn (Sweet) 425g
            336 => 37,  // Jolly Whole Corn (Kernel) 425g
            337 => 37,  // Eden Cheese 160g
            338 => 37,  // Eden Cheese 430g
            339 => 37,  // Magnolia Cheezee 160g
            340 => 37,  // Magnolia Daily Quezo 160g
            341 => 37,  // Magnolia Daily Quezo 430g
            342 => 37,  // Jersey Cheese 165g
            351 => 37,  // Nacho Chips 500g
            355 => 37,  // Slice Almond 250g
            403 => 37,  // Crushed Graham (M.Y San) 1kg
            421 => 37,  // Parmesan Cheese (MAMA FRANCESCA) 226g
            425 => 37,  // Mozzarella Cheese Great Food 1kg
            437 => 37,  // Biscoff (Crunchy)
            462 => 37,  // Nori Sheet (10 Sheets) 27g
            463 => 37,  // Rice Paper (30-33 Sheets)
            467 => 37,  // Rice Paper 9x12 (250g)
            469 => 37,  // Parmesan Cheese 100g (Perfect Italiano)
            472 => 37,  // Bread Crumbs 250g
            477 => 37,  // Mozzarella (great food)
            480 => 37,  // Sesame Seeds (sack)
            483 => 37,  // Cashew Nuts (1kl)
            493 => 37,  // Mozzarella Cheese Great Food (1blocked)
        ];

        foreach ($mappings as $productId => $categoryId) {
            DB::table('products')
                ->where('id', $productId)
                ->update(['category_id' => $categoryId]);
        }
    }

    public function down(): void
    {
        $ids = [
            17, 23, 37, 38, 39, 40, 41, 46, 81, 110, 111, 112,
            274, 281, 286, 289, 290, 291, 292, 308, 313, 316, 317,
            320, 321, 322, 323, 324, 326, 327, 328, 329, 330, 331, 332,
            333, 334, 335, 336, 337, 338, 339, 340, 341, 342, 343, 344,
            345, 346, 351, 352, 355, 403, 406, 407, 408, 421, 425, 437,
            445, 447, 460, 461, 462, 463, 467, 469, 472, 477, 480, 483,
            488, 493,
        ];

        DB::table('products')->whereIn('id', $ids)->update(['category_id' => 8]);
    }
};
