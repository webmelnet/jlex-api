<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix product category assignments.
 *
 * Most products (IDs 9-114) were incorrectly assigned to category 1 (Chocolate & Cocoa).
 * Products 115-157 were under the generic "Bakeware" (cat 2) instead of specific pan subcategories.
 * Products 158-266 were under the generic "Cake Packaging" (cat 3) instead of "Cake Boxes" (cat 4).
 * Products 267+ had no category assigned.
 *
 * Categories reference:
 *  1  = Chocolate & Cocoa          8  = Baking Ingredients       9  = Flour
 * 10  = Sugar & Sweeteners        11  = Leavening Agents         12  = Flavorings & Extracts
 * 13  = Food Coloring              14  = Frosting & Fillings      15  = Buttercream
 * 17  = Fruit Fillings             18  = Caramel & Chocolate Fillings
 * 19  = Baking Tools & Equipment   21  = Whisks & Spatulas        22  = Measuring Tools
 * 24  = Baking Mats & Cooling Racks
 * 25  = Baking Pans & Molds        26  = Cake Pans                27  = Cupcake & Muffin Pans
 * 28  = Bread & Loaf Pans          33  = Piping Bags              34  = Piping Tips
 * 35  = Cake Scrapers & Smoothers  36  = Fondant & Gum Paste      37  = Edible Decorations
 * 38  = Cake Toppers                3  = Cake Packaging             4  = Cake Boxes
 *  5  = Cupcake Boxes               6  = Cake Boards & Drums      51  = Goodie Bags
 * 61  = Whipping Cream
 */
return new class extends Migration
{
    public function up(): void
    {
        // Each entry: product_id => correct_category_id
        $mappings = [
            // --- Products incorrectly set to cat 1 (Chocolate & Cocoa) ---

            // Fondant & Gum Paste (36)
            9   => 36,  // Ready to Roll Fondant 1kg

            // Sugar & Sweeteners (10)
            10  => 10,  // High Fructose Corn Syrup 1L
            11  => 10,  // High Fructose Corn Syrup 500ml
            14  => 10,  // Glucose Syrup 750g
            16  => 10,  // Pure Molasses 1L
            18  => 10,  // Caster Sugar 500g

            // Food Coloring (13)
            12  => 13,  // Food Color Gel (Red) 100g
            13  => 13,  // Food Color Gel 25g
            58  => 13,  // Pink Liquid Food Color 30ml
            59  => 13,  // Egg Yellow Liquid Food Color 30ml
            60  => 13,  // Red Liquid Food Color 30ml
            61  => 13,  // Blue Liquid Food Color 30ml
            62  => 13,  // Green Liquid Food Color 30ml
            63  => 13,  // Violet Liquid Food Color 30ml
            64  => 13,  // Purple Liquid Food Color 30ml
            86  => 13,  // Lemon Yellow Food Color Powder 500g
            87  => 13,  // Egg Yellow Food Color Powder 500g
            88  => 13,  // Strawberry Red Food Color Powder 500g
            89  => 13,  // Chocolate Brown Food Color Powder 500g
            90  => 13,  // Violet Ube Food Color Powder 500g
            91  => 13,  // Orange Food Color Powder 500g
            92  => 13,  // Violet Ube Food Color Powder 125g
            93  => 13,  // Apple Green Food Color Powder 125g
            94  => 13,  // Strawberry Red Food Color Powder 125g
            95  => 13,  // Scarlet Red Food Color Powder 125g
            96  => 13,  // Egg Yellow Food Color Powder 125g
            109 => 13,  // Liquid Food Color 60ml

            // Flavorings & Extracts (12)
            15  => 12,  // Pure Glycerine 100ml
            27  => 12,  // Ube Flavorade 700g
            36  => 12,  // Ube Powder 1kg
            47  => 12,  // Chocolate Flavocol 1kg
            48  => 12,  // Mocha Flavocol 1kg
            49  => 12,  // Ube Flavocol 1kg
            50  => 12,  // Strawberry Flavocol 1kg
            51  => 12,  // Chocolate Flavocol 500ml
            52  => 12,  // Mocha Flavocol 500ml
            53  => 12,  // Ube Flavocol 500ml
            54  => 12,  // Strawberry Flavocol 500ml
            55  => 12,  // Ube Flavocol Bottle 30ml
            56  => 12,  // Strawberry Flavocol Bottle 30ml
            57  => 12,  // Pandan Flavocol Bottle 30ml
            65  => 12,  // Pandan Flavocol 500ml
            66  => 12,  // Chocolate Flavor & Color 1kg
            67  => 12,  // Mocha Flavor & Color 1kg
            68  => 12,  // Strawberry Flavor & Color 1kg
            69  => 12,  // Ube Flavor & Color 1kg
            70  => 12,  // Buko Pandan Flavor & Color 1kg
            71  => 12,  // Chocolate Flavor & Color 500ml
            72  => 12,  // Mocha Flavor & Color 500ml
            73  => 12,  // Strawberry Flavor & Color 500ml
            74  => 12,  // Ube Flavor & Color 500ml
            75  => 12,  // Buko Pandan Flavor & Color 500ml
            97  => 12,  // Pineapple Oil 454g
            98  => 12,  // Vanilla Oil XB 500g
            99  => 12,  // Sweet Orange Oil 454g
            100 => 12,  // Vanilla Imitation Flavor 120ml
            101 => 12,  // Vanilla Imitation Flavor 500ml
            102 => 12,  // Vanilla Imitation Flavor 1L
            103 => 12,  // Vanilla Imitation Flavor 1 Gallon
            104 => 12,  // Pineapple Flavor 120ml
            105 => 12,  // Almond Flavor 120ml
            106 => 12,  // Anise Flavor 120ml
            107 => 12,  // Strawberry Flavor 120ml
            108 => 12,  // Ube Flavor 120ml

            // Caramel & Chocolate Fillings (18)
            19  => 18,  // Fudge It Caramel 1kg
            21  => 18,  // Fudge It Chocolate 1kg
            24  => 18,  // Caramel Syrup 700g
            28  => 18,  // Choco Syrup 700g
            32  => 18,  // Chocolate Syrup 1kg
            33  => 18,  // Caramel Syrup 1kg

            // Frosting & Fillings - general (14)
            20  => 14,  // Fudge It Yema 1kg

            // Fruit Fillings (17)
            22  => 17,  // Fudge It Ube 1kg
            34  => 17,  // Strawberry Syrup 1kg
            76  => 17,  // Ube Paste 1kg
            77  => 17,  // Ube Paste 5kg
            80  => 17,  // Red Bean Paste 500g

            // Whipping Cream (61)
            25  => 61,  // Whippit Whipping Creme 1kg
            31  => 61,  // Acc Whipping Creme 1kg
            82  => 61,  // Easy Whip Whipping Cream 1L

            // Buttercream (15)
            26  => 15,  // Whippit Buttercream 1kg

            // Baking Ingredients - general (8)
            17  => 8,   // Gummix Fondant Improver 20g
            23  => 8,   // Butter Blend Cook n' Bake 1kg
            37  => 8,   // Pork Floss 1kg
            38  => 8,   // Chicken Floss 1kg
            39  => 8,   // Pork Floss 200g
            40  => 8,   // Chicken Floss 200g
            41  => 8,   // Chiffon Oil 800g
            46  => 8,   // Gelatine 50g
            81  => 8,   // Dairymont Cream Cheese 2kg
            110 => 8,   // Super Anti Amag 1kg
            111 => 8,   // Superpan 1kg
            112 => 8,   // Unisoft 1kg

            // Leavening Agents (11)
            42  => 11,  // Cream of Tartar 1kg
            43  => 11,  // Cream of Tartar 125g
            44  => 11,  // Baking Soda 500g
            45  => 11,  // Baking Soda 250g
            79  => 11,  // Angel Instant Yeast 500g
            83  => 11,  // Angel Instant Yeast 100g
            84  => 11,  // Angel Instant Yeast 11g
            85  => 11,  // Angel Instant Yeast Box (11g x 5)

            // --- Products under Bakeware (cat 2) → specific pan categories ---

            // Cake Pans (26): round, square, rectangular, heart, pie
            115 => 26,  // Round Pan 5x3
            116 => 26,  // Round Pan 6x3
            117 => 26,  // Round Pan 7x3
            118 => 26,  // Round Pan 8x3
            119 => 26,  // Round Pan 9x3
            120 => 26,  // Round Pan 10x3
            121 => 26,  // Round Pan 12x3
            122 => 26,  // Round Pan 14x3
            123 => 26,  // Round Pan 4x4
            124 => 26,  // Round Pan 7x4
            125 => 26,  // Round Pan 8x4
            126 => 26,  // Round Pan 9x4
            127 => 26,  // Round Pan 9x2
            128 => 26,  // Round Pan 10x2
            129 => 26,  // Square Pan 4x4x3
            130 => 26,  // Square Pan 5x5x3
            131 => 26,  // Square Pan 6x4x3
            132 => 26,  // Square Pan 7x7x3
            133 => 26,  // Square Pan 10x10x4
            134 => 26,  // Square Pan 12x12x4
            135 => 26,  // Square Pan 8x8x2
            136 => 26,  // Square Pan 9x9x2
            137 => 26,  // Rectangular Pan 5x9x3
            138 => 26,  // Rectangular Pan 6x10x3
            139 => 26,  // Rectangular Pan 11x7x3
            140 => 26,  // Rectangular Pan 8x12x3
            141 => 26,  // Rectangular Pan 9x13x3
            142 => 26,  // Rectangular Pan 12x16x3
            143 => 26,  // Rectangular Pan 14x18x2
            144 => 26,  // Rectangular Pan 14x18x4
            145 => 26,  // Heart Shape Pan 4x4
            146 => 26,  // Heart Shape Pan 5x4
            147 => 26,  // Heart Shape Pan 6x3
            148 => 26,  // Heart Shape Pan 8x3
            149 => 26,  // Heart Shape Pan 9x3
            154 => 26,  // Pie Plate Size #7
            155 => 26,  // Pie Plate Size #8
            156 => 26,  // Pie Plate Size #9
            157 => 26,  // Pie Plate Size #10

            // Bread & Loaf Pans (28)
            150 => 28,  // Loaf Pan 3x6x2
            151 => 28,  // Loaf Pan 7x3x2
            152 => 28,  // Loaf Pan 8x2x2
            153 => 28,  // Loaf Pan 9x2x2

            // --- Products under Cake Packaging (cat 3) → Cake Boxes (cat 4) ---
            158 => 4,  159 => 4,  160 => 4,  161 => 4,  162 => 4,
            163 => 4,  164 => 4,  165 => 4,  166 => 4,  167 => 4,
            168 => 4,  169 => 4,  170 => 4,  171 => 4,  172 => 4,
            173 => 4,  174 => 4,  175 => 4,  176 => 4,  177 => 4,
            178 => 4,  179 => 4,  180 => 4,  181 => 4,  182 => 4,
            183 => 4,  184 => 4,  185 => 4,  186 => 4,  187 => 4,
            188 => 4,  189 => 4,  190 => 4,  191 => 4,  192 => 4,
            193 => 4,  194 => 4,  195 => 4,  196 => 4,  197 => 4,
            198 => 4,  199 => 4,  200 => 4,  201 => 4,  202 => 4,
            203 => 4,  204 => 4,  205 => 4,  206 => 4,  207 => 4,
            208 => 4,  209 => 4,  210 => 4,  211 => 4,  212 => 4,
            213 => 4,  214 => 4,  215 => 4,  216 => 4,  217 => 4,
            218 => 4,  219 => 4,  220 => 4,  221 => 4,  222 => 4,
            223 => 4,  224 => 4,  225 => 4,  226 => 4,  227 => 4,
            228 => 4,  229 => 4,  230 => 4,  231 => 4,  232 => 4,
            233 => 4,  234 => 4,  235 => 4,  236 => 4,  237 => 4,
            238 => 4,  239 => 4,  240 => 4,  241 => 4,  242 => 4,
            243 => 4,  244 => 4,  245 => 4,  246 => 4,  247 => 4,
            248 => 4,  249 => 4,  250 => 4,  251 => 4,  252 => 4,
            253 => 4,  254 => 4,  255 => 4,  256 => 4,  257 => 4,
            258 => 4,  259 => 4,  260 => 4,  261 => 4,  262 => 4,
            263 => 4,  264 => 4,  265 => 4,  266 => 4,

            // --- Products with no category (null) ---

            // Baking Tools & Equipment (19)
            267 => 19,  // Hairnet
            347 => 19,  // Cake Post
            350 => 19,  // Macaroons Cup liner
            361 => 19,  // Polvoron Moulder
            366 => 19,  // Cupcake liner 1oz
            376 => 19,  // Muffin Liner w/ Design
            378 => 19,  // Dowell (5 pcs)
            427 => 19,  // Dowell Per Pack (Bamboo Skewer)
            430 => 19,  // Cupcake Liner Pack (Brown)
            431 => 19,  // Cupcake Liner Pack (White)
            432 => 19,  // Topper Post
            434 => 19,  // Microwave Oven Safe Container
            441 => 19,  // Dummy Cake Styro (4x4)
            454 => 19,  // Cupcake Liner 3oz(white)

            // Flour (9)
            268 => 9,   // All Purpose Flour 1kg
            272 => 9,   // Cake Flour 1kg
            275 => 9,   // Corn starch 1kg
            277 => 9,   // Cassava Flour 1kg
            278 => 9,   // Cassava Flour 1/2Kl
            279 => 9,   // Corn starch 1/2Kl
            405 => 9,   // Bread Flour (First Class) 1kg

            // Sugar & Sweeteners (10)
            269 => 10,  // Refined Sugar (White) 1kg
            271 => 10,  // Refined Sugar (White) 1/2kl
            280 => 10,  // Washed Sugar 1Kl
            284 => 10,  // Washed Sugar 1/2kl

            // Cake Boards & Drums (6)
            270 => 6,   // Cake Board (Square) 12x12
            285 => 6,   // Cake board 10'' (White)
            287 => 6,   // Cake Board 12'' (White)
            298 => 6,   // Cake Board 10'' (Silver)
            299 => 6,   // Cake board 8'' (Gold)
            314 => 6,   // Cake Drum 12"
            349 => 6,   // Cake Board 12x16 (White)
            354 => 6,   // Cake Board 12'' Silver
            414 => 6,   // Cake Drum 14''
            420 => 6,   // Cake drum 10''
            443 => 6,   // Cake Board 9'' White
            478 => 6,   // Cake Board 6'' (gold)
            479 => 6,   // Cake Board 6'' (White)
            481 => 6,   // Cake Board 8'' (silver)

            // Cake Toppers (38)
            273 => 38,  // Fan Topper
            288 => 38,  // Happy 40th-90th Topper (Big)
            353 => 38,  // Candle Assorted
            374 => 38,  // Birthday Topper (small)
            379 => 38,  // Number Candle (gold)
            380 => 38,  // Hello 21&30 Topper
            417 => 38,  // Candle Assorted
            419 => 38,  // Number 50th-90th Topper
            436 => 38,  // Candle number Gold (small)
            440 => 38,  // Balls Topper
            458 => 38,  // Acrylic Letters & Numbers
            459 => 38,  // Candle
            470 => 38,  // Candle big (1box)
            476 => 38,  // Happy Birthday Topper (TX)
            492 => 38,  // Candle Gold

            // Baking Ingredients - general (8)
            274 => 8,   // Desiccated Coconut
            281 => 8,   // Milkboy (milk powder) 1kl
            286 => 8,   // Milkboy (milk powder) 1/2kl
            289 => 8,   // Sesame seeds 1/2kl
            290 => 8,   // Sesame seeds 1/4kl
            291 => 8,   // Creamer 1kl
            292 => 8,   // Creamer 1/2kl
            308 => 8,   // Buttercup 200g
            313 => 8,   // Nether Mold Inhibitor
            316 => 8,   // Daily Quezo 160g
            317 => 8,   // Margarine 1kg
            320 => 8,   // All Purpose Cream 250g (NESTLE)
            321 => 8,   // Nestle Cucumber 140g
            322 => 8,   // Nestle Blue lemonade 140g
            323 => 8,   // Angel Evaporated milk 410ml
            324 => 8,   // Angel Condensed 380g
            326 => 8,   // Jersey evaporated milk 370ml
            327 => 8,   // Jersey Condensed 1kl
            328 => 8,   // Jersey Condensed 390g
            329 => 8,   // Jersey Condense (Buko Pandan) 390g
            330 => 8,   // Jersey Condense (Mango) 390g
            331 => 8,   // Jersey Condensed (Chocolate) 390g
            332 => 8,   // Jersey Condensed (Melon) 390g
            333 => 8,   // Jolly Mushroom (Whole) 400g
            334 => 8,   // Jolly Young Corn (Whole) 425g
            335 => 8,   // Jolly Cream Corn (Sweet) 425g
            336 => 8,   // Jolly Whole Corn (Kernel) 425g
            337 => 8,   // Eden Cheese 160g
            338 => 8,   // Eden Cheese 430g
            339 => 8,   // Magnolia Cheezee 160g
            340 => 8,   // Magnolia Daily Quezo 160g
            341 => 8,   // Magnolia Daily Quezo 430g
            342 => 8,   // Jersey Cheese 165g
            343 => 8,   // Maggi Oyster Sauce 2l
            344 => 8,   // Rainbow Fruit Jelly 2.5kg
            345 => 8,   // Nata Jelly 2.5kg
            346 => 8,   // WanWan Syrup Nata De Coco 3.8kg
            351 => 8,   // Nacho Chips 500g
            352 => 8,   // Waffle Mix 1kg
            355 => 8,   // Slice Almond 250g
            403 => 8,   // Crushed Graham (M.Y San) 1kg
            406 => 8,   // Lard (shortening) 1kg
            407 => 8,   // Lard (shortening) 1/2kg
            408 => 8,   // Dari Creme (classic)
            421 => 8,   // Parmesan Cheese (MAMA FRANCESCA) 226g
            425 => 8,   // Mozzarella Cheese Great Food 1kg
            437 => 8,   // Biscoff (Crunchy)
            445 => 8,   // Cream Cheese Magnolia
            447 => 8,   // Cream Cheese (Anchor) 1kg
            460 => 8,   // Sago Straw
            461 => 8,   // Jersey Full Cream 1liter
            462 => 8,   // Nori Sheet (10 Sheets) 27g
            463 => 8,   // Rice Paper (30-33 Sheets)
            467 => 8,   // Rice Paper 9x12 (250g)
            469 => 8,   // Parmesan Cheese 100g (Perfect Italiano)
            472 => 8,   // Bread Crumbs 250g
            477 => 8,   // Mozzarella (great food)
            480 => 8,   // Sesame Seeds (sack)
            483 => 8,   // Cashew Nuts (1kl)
            488 => 8,   // Tapioca Pearl 1kg
            493 => 8,   // Mozzarella Cheese Great Food (1 blocked)

            // Cake Boxes (4)
            282 => 4,   // Laminated Box
            297 => 4,   // 10x10x5 clear cover
            310 => 4,   // pizza box 10''
            311 => 4,   // pizza box 10''
            357 => 4,   // Clamshell C35
            362 => 4,   // Slice Cake Container (Black)
            484 => 4,   // Clamshell (ops-C14)

            // Cake Packaging - general (3)
            312 => 3,   // Thank you sticker
            325 => 3,   // Sealer Tape
            356 => 3,   // Empty Bottle Jar 100ml
            360 => 3,   // Floral Seal
            411 => 3,   // Whole roll base
            412 => 3,   // Whole roll box
            428 => 3,   // Cello Sheet (9x13)
            456 => 3,   // Plastic Packaging (Handmade)
            457 => 3,   // Twist Tie
            464 => 3,   // Plastic wrap 9.3m
            465 => 3,   // Empty Bottle Jar 150ml
            466 => 3,   // Empty Bottle Jar 300ml
            490 => 3,   // Plastic Bottle (500ml)

            // Whipping Cream (61)
            293 => 61,  // Bunge Whipping cream 1L
            294 => 61,  // Ever-Whip Whipping Cream 1030g
            295 => 61,  // VIVO Whipping Cream
            296 => 61,  // Monna Lisa DELUXE whipping cream

            // Food Coloring (13)
            300 => 13,  // Chefmaster food color gel (leaf green)
            301 => 13,  // Chefmaster Food color gel (sunset orange)
            302 => 13,  // Chefmaster Food color gel (Whitener)
            303 => 13,  // Chefmaster food color gel (buckeye brown)
            304 => 13,  // Chefmaster food color gel (lemon yellow)
            305 => 13,  // Chefmaster food color gel (coal black)
            306 => 13,  // Chefmaster food color gel (Royal blue)
            307 => 13,  // Chefmaster Food color gel (Red red)
            370 => 13,  // Fuchsia Liquid Food Color 30ml
            371 => 13,  // Black Liquid Food Color 30ml
            389 => 13,  // Food Color Gel (Brown) 25g
            390 => 13,  // Food Color Gel (Yellow) 25g
            391 => 13,  // Food Color Gel (Blue) 25g
            392 => 13,  // Food Color Gel (Green) 25g
            393 => 13,  // Food Color Gel (Red) 25g
            394 => 13,  // Food Color Gel (Black) 25g
            396 => 13,  // Food Color Gel (Black) 100g
            397 => 13,  // Food Color Gel (Yellow) 100g
            398 => 13,  // Food Color Gel (Green) 100g
            399 => 13,  // Food Color Gel (White) 100g
            452 => 13,  // Choco Brown Liquid Food Color 30ml
            471 => 13,  // Food Color Gel (Blue) 100g

            // Edible Decorations (37)
            309 => 37,  // Sprinkles (Rainbow) 1kl
            375 => 37,  // Flower Icing Assorted (small Daisy)
            400 => 37,  // Marshmallow Mini Assorted
            415 => 37,  // Gold Dust
            416 => 37,  // Pearl Dragees
            429 => 37,  // Gold Dust (small)
            438 => 37,  // Bling bling
            439 => 37,  // Bling bling (Rhinestone cake Ribbon) rose gold
            473 => 37,  // flower candy mini
            475 => 37,  // Dragees Gold
            485 => 37,  // Sprinkles Assorted 45g
            486 => 37,  // Sprinkles (cups)
            489 => 37,  // Sprinkles Assorted (cup)
            491 => 37,  // Dragees Silver

            // Whisks & Spatulas (21)
            318 => 21,  // Wire whisk (large)
            319 => 21,  // Wire whisk (medium)
            363 => 21,  // Rubber Spatula Color purple
            372 => 21,  // Pastry Brush set (Black) 2pcs
            373 => 21,  // Pastry Brush set 4pcs (Yellow)

            // Measuring Tools (22)
            315 => 22,  // Electronic Kitchen scale
            433 => 22,  // Measuring Cups (Assorted Color)

            // Baking Mats & Cooling Racks (24)
            276 => 24,  // Aluminum Foil 5m
            410 => 24,  // Baking Paper 5m

            // Chocolate & Cocoa (1)
            358 => 1,   // Vanhouten White Compound
            359 => 1,   // Slice Chocolate Bar
            364 => 1,   // Devonte (Dark Compound) 1kg
            365 => 1,   // Devonte (White Compound) 1kg
            401 => 1,   // Vanhouten Chocolate Chips (Semi Sweet) 1kg
            402 => 1,   // Beryl's Chocolate Bar (Dark Compound) 1kg
            444 => 1,   // Sevona Dark Compound 1kg
            468 => 1,   // Vanhouten Milk Compound 1kg

            // Flavorings & Extracts (12)
            283 => 12,  // Cinnamon 25g
            348 => 12,  // Ube Powder 200g
            367 => 12,  // Chocolate liquid Flavocol 30ml
            369 => 12,  // Mocha Liquid Flavocol 30ml
            404 => 12,  // Cinnamon Powder 200g
            426 => 12,  // Pandan Primera 1Kg

            // Leavening Agents (11)
            409 => 11,  // Calumet 50g (baking powder)
            482 => 11,  // Calumet 1kg

            // Baking Pans & Molds (25) - aluminum trays / other molds
            381 => 25,  // Aluminum Tray (B4-3016.9x12.2x2.5)
            382 => 25,  // Aluminum Tray (RE370)
            383 => 25,  // Aluminum Tray (RE324)
            384 => 25,  // Aluminum Tray (RE320)
            385 => 25,  // Aluminum Tray (39x32x52cm)
            386 => 25,  // Aluminum Tray (SQ204)
            387 => 25,  // Aluminum Tray (32x22x32cm)
            388 => 25,  // Aluminum Tray (8x2 with cover)
            424 => 25,  // Aluminum Tray (SJ-1711)
            442 => 25,  // Aluminum Tray (RE315)
            449 => 25,  // Torta Molder(s)
            455 => 25,  // Torta Molder (L)

            // Cupcake & Muffin Pans (27)
            448 => 27,  // Cupcake Molder (s)1oz
            450 => 27,  // Cupcake Molder (M)
            451 => 27,  // Cupcake Molder (l)3oz

            // Piping Bags (33)
            413 => 33,  // Pipping Bag (Medium)

            // Piping Tips (34)
            418 => 34,  // Pipping Tip Set

            // Cake Scrapers & Smoothers (35)
            487 => 35,  // Scrapper (s)

            // Fondant & Gum Paste (36)
            446 => 36,  // Flower Icing gum paste (peony)
            453 => 36,  // Tylose Powder 100g

            // Fruit Fillings (17)
            422 => 17,  // Glaze Fruit (imported) 250g
            423 => 17,  // Glaze Fruit (Local) 500g
            435 => 17,  // Cherries (Hosen Quality) 737g

            // Cupcake Boxes (5)
            377 => 5,   // Cupcake Holder (12 Holes)

            // Goodie Bags (51)
            474 => 51,  // PARTY BAG
        ];

        foreach ($mappings as $productId => $categoryId) {
            DB::table('products')
                ->where('id', $productId)
                ->update(['category_id' => $categoryId]);
        }
    }

    public function down(): void
    {
        // Revert: products 9-114 that were moved away from category 1
        $revertToChocolate = [
            9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,
            31,32,33,34,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,
            51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,
            70,71,72,73,74,75,76,77,79,80,81,82,83,84,85,86,87,88,89,
            90,91,92,93,94,95,96,97,98,99,100,101,102,103,104,105,106,
            107,108,109,110,111,112,
        ];
        DB::table('products')->whereIn('id', $revertToChocolate)->update(['category_id' => 1]);

        // Revert pans to Bakeware (cat 2)
        $revertToBakeware = range(115, 157);
        DB::table('products')->whereIn('id', $revertToBakeware)->update(['category_id' => 2]);

        // Revert boxes to Cake Packaging (cat 3)
        $revertToPackaging = range(158, 266);
        DB::table('products')->whereIn('id', $revertToPackaging)->update(['category_id' => 3]);

        // Revert products that had no category back to null
        $revertToNull = array_merge(
            range(267, 400),
            [403,404,405,406,407,408,409,410,411,412,413,414,415,416,417,
             418,419,420,421,422,423,424,425,426,427,428,429,430,431,432,
             433,434,435,436,437,438,439,440,441,442,443,444,445,446,447,
             448,449,450,451,452,453,454,455,456,457,458,459,460,461,462,
             463,464,465,466,467,468,469,470,471,472,473,474,475,476,477,
             478,479,480,481,482,483,484,485,486,487,488,489,490,491,492,493],
        );
        // Exclude IDs that already had categories (396 is a gap, 401-402 were null)
        DB::table('products')->whereIn('id', $revertToNull)->update(['category_id' => null]);
    }
};
