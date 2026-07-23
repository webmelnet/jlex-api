<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Brand;
use App\Models\Category;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductSeeder extends Seeder
{
    private array $supplierCache = [];
    private array $brandCache = [];
    private array $categoryCache = [];
    private array $skuCounters = [];

    /**
     * Known brand name prefixes, matched against the start of the article
     * name. Multi-word brands are listed before their shorter/ambiguous
     * single-word counterparts so they win the match. This is a best-effort
     * guess from the article text alone (no external lookups) — anything
     * not recognized here is left blank for manual review.
     */
    private const BRAND_PATTERNS = [
        '/^GREAT\s+STAR/i' => 'Great Star',
        '/^BABY\s*FLO/i' => 'Baby Flo',
        '/^NATURE\s+SPRING/i' => 'Nature Spring',
        '/^GREEN\s*CROSS/i' => 'Green Cross',
        '/^HEAD\s*&?\s*SHOULDERS?/i' => 'Head & Shoulders',
        '/^DEL\s+MONTE/i' => 'Del Monte',
        '/^TAI\s+CHI/i' => 'Tai Chi',
        '/^LIFE\s+EXTENSION/i' => 'Life Extension',
        '/^SUPER\s+TWINS/i' => 'Super Twins',
        '/^LITTLE\s+PALS/i' => 'Little Pals',
        '/^(LEWIS\s*&\s*PEARL|L&P)\b/i' => 'Lewis & Pearl',
        '/^DR\.?\s*ALVIN/i' => 'Dr. Alvin',
        '/^KOOL\s+FEVER/i' => 'Kool Fever',
        '/^PH\s*CARE/i' => 'PH Care',
        '/^CLOSE\s*UP/i' => 'Close Up',
        '/^TIKI\s*TIKI/i' => 'Tiki Tiki',
        '/^FLAWLESSLY\s*U/i' => 'Flawlessly U',
        '/^CLINICA\s*DTX/i' => 'Clinica DTX',
        '/^CHOCO\s*HERO/i' => 'Choco Hero',

        '/^JOHNSON\'?S?\b/i' => "Johnson's",
        '/^BEARBRAND\b/i' => 'Bear Brand',
        '/^BENCH\b/i' => 'Bench',
        '/^LAMPEIN\b/i' => 'Lampein',
        '/^SILKA\b/i' => 'Silka',
        '/^REXONA\b/i' => 'Rexona',
        '/^EFFICASCENT\b/i' => 'Efficascent',
        '/^EQ\b/i' => 'EQ',
        '/^LACTUM\b/i' => 'Lactum',
        '/^SKINWHITE\b/i' => 'SkinWhite',
        '/^CASINO\b/i' => 'Casino',
        '/^SELECTA\b/i' => 'Selecta',
        '/^PAMPERS\b/i' => 'Pampers',
        '/^MYRA\b/i' => 'Myra',
        '/^RDL\b/i' => 'RDL',
        '/^PONDS\b/i' => "Pond's",
        '/^BIODERM\b/i' => 'Bioderm',
        '/^CEELIN\b/i' => 'Ceelin',
        '/^TC\b/i' => 'TC',
        '/^RHEA\b/i' => 'Rhea',
        '/^VICKS\b/i' => 'Vicks',
        '/^DOVE\b/i' => 'Dove',
        '/^NESTOGEN\b/i' => 'Nestogen',
        '/^PENS\b/i' => 'PENS',
        '/^CLEENE\b/i' => 'Cleene',
        '/^BIRCHTREE\b/i' => 'Birch Tree',
        '/^COLGATE\b/i' => 'Colgate',
        '/^AXE\b/i' => 'Axe',
        '/^NIVEA\b/i' => 'Nivea',
        '/^CHERIFER\b/i' => 'Cherifer',
        '/^SISTERS\b/i' => 'Sisters',
        '/^ALCO\+?\b/i' => 'Alco+',
        '/^NIDO\b/i' => 'Nido',
        '/^OMEGA\b/i' => 'Omega',
        '/^ABSOLUTE\b/i' => 'Absolute',
        '/^APOLLO\b/i' => 'Apollo',
        '/^SOLMUX\b/i' => 'Solmux',
        '/^SAFEGUARD\b/i' => 'Safeguard',
        '/^VITRESS\b/i' => 'Vitress',
        '/^OFF\b/i' => 'Off',
        '/^PAU\b/i' => 'Pau',
        '/^GRAND\b/i' => 'Grand',
        '/^BONNA\b/i' => 'Bonna',
        '/^SANICARE\b/i' => 'Sanicare',
        '/^XANTHONE\b/i' => 'Xanthone',
        '/^TEMPRA\b/i' => 'Tempra',
        '/^IPI\b/i' => 'IPI',
        '/^LISTERINE\b/i' => 'Listerine',
        '/^ANLENE\b/i' => 'Anlene',
        '/^ALASKA\b/i' => 'Alaska',
        '/^GLEAM\b/i' => 'Gleam',
        '/^DOMEX\b/i' => 'Domex',
        '/^ASCOF\b/i' => 'Ascof',
        '/^ZETADONE\b/i' => 'Zetadone',
        '/^APPEBON\b/i' => 'Appebon',
        '/^ENERVON\b/i' => 'Enervon',
        '/^CAREFREE\b/i' => 'Carefree',
        '/^POTENCEE\b/i' => 'PotenCee',
        '/^ROBITUSSIN\b/i' => 'Robitussin',
        '/^PEDIASURE\b/i' => 'PediaSure',
        '/^CHERUB\b/i' => 'Cherub',
        '/^STREPSILS\b/i' => 'Strepsils',
        '/^BACTIDOL\b/i' => 'Bactidol',
        '/^DEOPLUS\b/i' => 'DeoPlus',
        '/^EVEREADY\b/i' => 'Eveready',
        '/^YAKULT\b/i' => 'Yakult',
        '/^LUYAN\b/i' => 'Luyan',
        '/^OLAY\b/i' => 'Olay',
        '/^PLEMEX\b/i' => 'Plemex',
        '/^PROPAN\b/i' => 'Propan',
        '/^ENSURE\b/i' => 'Ensure',
        '/^SALONPAS\b/i' => 'Salonpas',
        '/^ALINGATONG\b/i' => 'Alingatong',
        '/^BIOGESIC\b/i' => 'Biogesic',
        '/^NEOZEP\b/i' => 'Neozep',
        '/^MODESS\b/i' => 'Modess',
        '/^PEDIALYTE\b/i' => 'Pedialyte',
        '/^BONAKID\b/i' => 'Bonakid',
        '/^QUAKER\b/i' => 'Quaker',
        '/^JIMMS\b/i' => 'Jimms',
        '/^KOOL\b/i' => 'Kool',
        '/^MX3\b/i' => 'MX3',
        '/^NANZ\b/i' => 'Nanz',
        '/^PANYAWAN\b/i' => 'Panyawan',
        '/^HERBALAYA\b/i' => 'Herbalaya',
        '/^BETADINE\b/i' => 'Betadine',
        '/^BIGEN\b/i' => 'Bigen',
        '/^CENTRUM\b/i' => 'Centrum',
        '/^KATINKO\b/i' => 'Katinko',
        '/^GAVISCON\b/i' => 'Gaviscon',
        '/^DOLFENAL\b/i' => 'Dolfenal',
        '/^MEDICOL\b/i' => 'Medicol',
        '/^NUTRILLIN\b/i' => 'Nutrillin',
        '/^FIONA\b/i' => 'Fiona',
        '/^HYCLENS\b/i' => 'Hyclens',
        '/^JUICY\b/i' => 'Juicy',
        '/^MILO\b/i' => 'Milo',
        '/^ENCHANTEUR\b/i' => 'Enchanteur',
        '/^STYLEX\b/i' => 'Stylex',
        '/^FEMME\b/i' => 'Femme',
        '/^BAYGON\b/i' => 'Baygon',
        '/^KOJIESAN\b/i' => 'Kojiesan',
        '/^EYEBERRY\b/i' => 'Eyeberry',
        '/^KOI\b/i' => 'Koi',
        '/^CREAMSILK\b/i' => 'Creamsilk',
        '/^VASELINE\b/i' => 'Vaseline',
        '/^LACTACYD\b/i' => 'Lactacyd',
        '/^ZONROX\b/i' => 'Zonrox',
        '/^HERBYCIN\b/i' => 'Herbycin',
        '/^MEGASCENT\b/i' => 'Megascent',
        '/^HYPANTS\b/i' => 'Hypants',
        '/^ALLERKID\b/i' => 'Allerkid',
        '/^ALNIX\b/i' => 'Alnix',
        '/^BIOFLU\b/i' => 'Bioflu',
        '/^DECOLGEN\b/i' => 'Decolgen',
        '/^DIATABS\b/i' => 'Diatabs',
        '/^GLUMET\b/i' => 'Glumet',
        '/^RESTIME\b/i' => 'Restime',
        '/^TUSERAN\b/i' => 'Tuseran',
        '/^ULTRAXIME\b/i' => 'Ultraxime',
        '/^GROWEE\b/i' => 'Growee',
        '/^NUTROPLEX\b/i' => 'Nutroplex',
        '/^PHAREX\b/i' => 'Pharex',
        '/^REVICON\b/i' => 'Revicon',
        '/^WILKINS\b/i' => 'Wilkins',
        '/^CERELAC\b/i' => 'Cerelac',
        '/^ANMUM\b/i' => 'Anmum',
        '/^TICTAC\b/i' => 'Tic Tac',
        '/^GILLETE?\b/i' => 'Gillette',
        '/^WHISPER\b/i' => 'Whisper',
        '/^VITAMILK\b/i' => 'Vitamilk',
        '/^ALBATROSS\b/i' => 'Albatross',
        '/^LICEALIZ\b/i' => 'LiceAliz',
        '/^WINGS\b/i' => 'Wings',
        '/^DRIVEMAX\b/i' => 'Drivemax',
        '/^MIGHTYCEE\b/i' => 'MightyCee',
        '/^ROBUST\b/i' => 'Robust',
        '/^SARIDON\b/i' => 'Saridon',
        '/^SURF\b/i' => 'Surf',
        '/^CALTRATE\b/i' => 'Caltrate',
        '/^DORCO\b/i' => 'Dorco',
        '/^SANGOBION\b/i' => 'Sangobion',
        '/^SWEET&FIT\b/i' => 'Sweet & Fit',
        '/^TOLAK\b/i' => 'Tolak',
        '/^CANESTEN\b/i' => 'Canesten',
        '/^DAKTARIN\b/i' => 'Daktarin',
        '/^LYSOL\b/i' => 'Lysol',
        '/^TROSYD\b/i' => 'Trosyd',
        '/^MAMAWHIZ\b/i' => 'MamaWhiz',
        '/^CHARMEE\b/i' => 'Charmee',
        '/^FRESCO\b/i' => 'Fresco',
        '/^ALAXAN\b/i' => 'Alaxan',
        '/^ASPILET\b/i' => 'Aspilet',
        '/^DISUDRIN\b/i' => 'Disudrin',
        '/^DOLAN\b/i' => 'Dolan',
        '/^DRENEX\b/i' => 'Drenex',
        '/^E-ZINC\b/i' => 'E-Zinc',
        '/^EXPEL\b/i' => 'Expel',
        '/^HIMOX\b/i' => 'Himox',
        '/^HYDRITE\b/i' => 'Hydrite',
        '/^KREMIL\b/i' => 'Kremil-S',
        '/^RELESTAL\b/i' => 'Relestal',
        '/^CONZACE\b/i' => 'Conzace',
        '/^FERLIN\b/i' => 'Ferlin',
        '/^GRIPS\b/i' => 'Grips',
        '/^PALMOLIVE\b/i' => 'Palmolive',
        '/^BIGUERLAI\b/i' => 'Biguerlai',
        '/^BIOFITEA\b/i' => 'Biofitea',
        '/^JADE\b/i' => 'Jade',
        '/^LIPTON\b/i' => 'Lipton',
        '/^GATORADE\b/i' => 'Gatorade',
        '/^POCARI\s*SWEAT\b/i' => 'Pocari Sweat',
        '/^DUREX\b/i' => 'Durex',
        '/^ENFAMIL\b/i' => 'Enfamil',
        '/^ENFAMAMA\b/i' => 'Enfamama',
        '/^CARESS\b/i' => 'Caress',
        '/^SC[E]{1,2}D\b/i' => 'SCED',
        '/^CHUCKIE\b/i' => 'Chuckie',
        '/^NESCAFE\b/i' => 'Nescafe',
        '/^RM\b/i' => 'RiteMed',
    ];

    /**
     * Category keyword rules applied against the full article text, in
     * priority order (first match wins). Also best-effort — products that
     * don't match anything are left uncategorized for manual review.
     */
    private const CATEGORY_PATTERNS = [
        '/\badult\s*(diaper|pants)\b|\bhypants\b/i' => 'Adult Care',
        '/\b(bandage|gauze|micropore|thermometer|face\s*mask|facemask|surgical|glove|nebuliz|stethoscope|spygmo|sling|binder|catheter|syringe|dextrose|pnss|cotton\s*(ball|bud)|absorbent\s*cotton|alcohol|sanitizer|hydrogen\s*peroxide|povidone|isopropyl|suppository)\b/i' => 'Medical Supplies',
        '/\bcondom|contraceptive|\blady\s*pills\b|\btrust\s*pills\b|\bdaphne\s*pills\b/i' => 'Family Planning',
        '/\bnapkin|panty\s*liner|feminine\s*wash|\bsanitary\b|\bwhisper\b|\bcarefree\b|\bmodess\b/i' => 'Feminine Care',
        '/\bdiaper|baby|\bpants\b|pull[\s-]?up|\bwipes\b|feeding\s*bottle|\bpacifier|\bteether|\binfant\b|\bnipple\b|breast\s*pump|nasal\s*aspirator|\benfamil\b|\benfamama\b|\bpromil\b|\bbonamil\b/i' => 'Baby Care',
        '/\bvitamin|multivitamin|\bascorbic|b-?complex|\bferrous\b|iron\s*\+|zinc\s*\+|\bsupplement|\bensure\b|pediasure|\bglucerna\b|\bcentrum\b|stresstabs|sangobion|caltrate|\biroplus\b|\bconzace\b/i' => 'Vitamins & Supplements',
        '/tooth\s*paste|toothpaste|tooth\s*brush|toothbrush|\bmouthwash\b|\blisterine\b|\bbactidol\b/i' => 'Oral Care',
        '/\d\s?(mg|mcg|iu)\b|\btablets?\b|\btabs?\b|\bcapsules?\b|\bcaps?\b|\bsyrup\b|\bdrops\b|\bsolution\b|\bsuspension\b|\bnebule\b|\bointment\b|\boint\b|\binhaler\b|\blozenge|\bvaporub\b/i' => 'Medicines',
        '/\bshampoo|conditioner|hair\s*(color|dye|blackening|clay|serum)|styling\s*gel|\bcreamsilk\b/i' => 'Hair Care',
        '/\bsoap\b|\blotion\b|\bcologne\b|\bperfume\b|body\s*spray|\bdeodorant\b|\btalc\b|whitening\s*(cream|lotion|soap)|facial\s*(cleanser|wash)|petroleum\s*jelly|sunblock|sunscreen|\bkojic\b|\bacetone\b|\bcuticle\b/i' => 'Personal Care',
        '/\bmilk\b|\blactum\b|\bbearbrand\b|bear\s*brand|\bnido\b|\bnestogen\b|\bbonakid\b|\banlene\b|\banmum\b|birch\s*tree|\bbirchtree\b|\balaska\b|\bvitamilk\b|\bcerelac\b/i' => 'Dairy & Milk',
        '/\bjuice\b|purified\s*water|distilled\s*water|\bcoffee\b|\btea\b|sports\s*drink|\bgatorade\b|pocari\s*sweat|\bwilkins\b|\byakult\b|\bnescafe\b|\blipton\b/i' => 'Beverages',
        '/\bcandy\b|chocolate|\bbiscuit|\boatmeal\b|\bcereal\b|ice\s*cream|\bmilo\b/i' => 'Snacks & Food',
        '/\bbleach\b|\bzonrox\b|muriatic|\bdetergent\b|\bsurf\b|insecticide|\bbaygon\b|dishwashing|\blysol\b/i' => 'Household & Cleaning',
    ];

    public function run(): void
    {
        $path = __DIR__ . '/JLEX INVENTORY TEMPLATE.xlsx';

        if (!file_exists($path)) {
            $this->command->warn("Inventory file not found, skipping: {$path}");
            return;
        }

        $spreadsheet = IOFactory::load($path);

        $created = 0;
        $updated = 0;

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            [$headerRow, $columns] = $this->findHeader($sheet);

            if ($headerRow === null) {
                continue;
            }

            $supplier = $this->getOrCreateSupplier($sheet->getTitle());
            $highestRow = $sheet->getHighestDataRow();

            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $name = trim((string) $sheet->getCellByColumnAndRow(1, $row)->getValue());

                if ($name === '') {
                    continue;
                }

                $cost = $this->cleanNumericValue($columns['cost'] ? $sheet->getCellByColumnAndRow($columns['cost'], $row)->getValue() : null);
                $price = $this->cleanNumericValue($columns['price'] ? $sheet->getCellByColumnAndRow($columns['price'], $row)->getValue() : null);
                $quantity = (int) $this->cleanNumericValue($columns['qty'] ? $sheet->getCellByColumnAndRow($columns['qty'], $row)->getValue() : null);

                $product = Product::firstOrNew([
                    'name' => $name,
                    'supplier_id' => $supplier->id,
                ]);

                $brandName = $this->resolveBrand($name);

                if (!$product->exists) {
                    $product->sku = $this->generateSku($brandName);
                    $created++;
                } else {
                    $updated++;
                }

                $product->cost = $cost;
                $product->price = $price;
                $product->stock_quantity = $quantity;
                $product->reorder_level = $product->reorder_level ?: 0;
                $product->unit = $product->unit ?: 'pcs';
                $product->track_inventory = true;
                $product->is_active = true;

                if (!$product->brand_id) {
                    $product->brand_id = $this->getOrCreateBrand($brandName)?->id;
                }

                if (!$product->category_id) {
                    $product->category_id = $this->getOrCreateCategory($this->resolveCategory($name))?->id;
                }

                $product->save();
            }
        }

        $this->command->info("Products seeded from inventory file: {$created} created, {$updated} updated.");
    }

    /**
     * Locate the header row (identified by an "SRP" cell) and map the
     * relevant columns. Sheets have an inconsistent number of title/sub-header
     * rows above the real column headers, so we scan for the row containing "SRP".
     */
    private function findHeader(Worksheet $sheet): array
    {
        $highestColumn = $sheet->getHighestColumn();
        $highestRow = min($sheet->getHighestRow(), 6);

        for ($row = 1; $row <= $highestRow; $row++) {
            $cells = $sheet->rangeToArray("A{$row}:{$highestColumn}{$row}", null, false, false)[0];

            $hasSrp = false;
            foreach ($cells as $value) {
                if (is_string($value) && strtolower(trim($value)) === 'srp') {
                    $hasSrp = true;
                    break;
                }
            }

            if (!$hasSrp) {
                continue;
            }

            $columns = ['cost' => null, 'price' => null, 'qty' => null];
            foreach ($cells as $colIndex => $value) {
                if (!is_string($value)) {
                    continue;
                }
                $label = strtolower(trim($value));
                $colNumber = $colIndex + 1;

                if ($label === 'srp') {
                    $columns['price'] = $colNumber;
                } elseif (str_contains($label, 'purchase')) {
                    $columns['cost'] = $colNumber;
                } elseif (str_contains($label, 'quantity')) {
                    $columns['qty'] = $colNumber;
                }
            }

            return [$row, $columns];
        }

        return [null, []];
    }

    private function getOrCreateSupplier(string $sheetTitle): Supplier
    {
        $name = trim($sheetTitle);

        if (isset($this->supplierCache[$name])) {
            return $this->supplierCache[$name];
        }

        $supplier = Supplier::firstOrCreate(
            ['name' => $name],
            ['is_active' => true]
        );

        $this->supplierCache[$name] = $supplier;

        return $supplier;
    }

    /**
     * Guess the brand from the article name. Trade-name drugs are written
     * as "BRAND (Generic Name) dosage" in this catalog (e.g. "CARDIPRES
     * (Carvedilol) 12.5 mg"), so a leading all-caps phrase followed by a
     * parenthesis is treated as the brand. Otherwise falls back to a
     * dictionary of recognized brand prefixes. Returns null when neither
     * matches, rather than guessing.
     */
    private function resolveBrand(string $name): ?string
    {
        if (preg_match('/^([A-Z][A-Z0-9\-\+]*(?:\s[A-Z][A-Z0-9\-\+]*)*)\s*\(/', $name, $matches)) {
            $candidate = trim($matches[1]);
            if (!in_array(strtoupper($candidate), ['GENERIC', 'OTHERS'], true)) {
                return ucwords(strtolower($candidate), " -");
            }
        }

        foreach (self::BRAND_PATTERNS as $pattern => $brand) {
            if (preg_match($pattern, $name)) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * Guess the category from keywords in the article name (dosage forms,
     * product types, well-known brand names implying a category). Returns
     * null when nothing matches, rather than guessing.
     */
    private function resolveCategory(string $name): ?string
    {
        foreach (self::CATEGORY_PATTERNS as $pattern => $category) {
            if (preg_match($pattern, $name)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Build a SKU using a brand-derived prefix (e.g. "BIO" for Biogesic),
     * with a per-prefix running counter so each brand gets its own sequence.
     * Falls back to "JLX" when the brand couldn't be resolved.
     */
    private function generateSku(?string $brand): string
    {
        $prefix = $this->brandSkuPrefix($brand);
        $this->skuCounters[$prefix] = ($this->skuCounters[$prefix] ?? 0) + 1;

        return $prefix . '-' . str_pad((string) $this->skuCounters[$prefix], 6, '0', STR_PAD_LEFT);
    }

    private function brandSkuPrefix(?string $brand): string
    {
        if ($brand === null) {
            return 'JLX';
        }

        $letters = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $brand));

        return $letters === '' ? 'JLX' : substr($letters, 0, 3);
    }

    private function getOrCreateBrand(?string $name): ?Brand
    {
        if ($name === null) {
            return null;
        }

        if (isset($this->brandCache[$name])) {
            return $this->brandCache[$name];
        }

        $brand = Brand::firstOrCreate(
            ['name' => $name],
            ['is_active' => true]
        );

        return $this->brandCache[$name] = $brand;
    }

    private function getOrCreateCategory(?string $name): ?Category
    {
        if ($name === null) {
            return null;
        }

        if (isset($this->categoryCache[$name])) {
            return $this->categoryCache[$name];
        }

        $category = Category::firstOrCreate(
            ['name' => $name],
            ['is_active' => true]
        );

        return $this->categoryCache[$name] = $category;
    }

    /**
     * Clean numeric value, stripping currency prefixes (e.g. "Php 20"),
     * commas, and placeholder dashes. Defaults to 0 when unparseable.
     */
    private function cleanNumericValue($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return 0.0;
        }

        $cleaned = preg_replace('/[^0-9.]/', '', $value);

        return $cleaned === '' ? 0.0 : (float) $cleaned;
    }
}
