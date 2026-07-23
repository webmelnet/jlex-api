<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Aws\S3\S3Client;

$client = new S3Client([
    'version'  => 'latest',
    'region'   => 'sgp1',
    'endpoint' => 'https://sgp1.digitaloceanspaces.com',
    'use_path_style_endpoint' => false,
    'credentials' => [
        'key'    => 'DO00K7LVWB389PXJD8PA',
        'secret' => 'kJCuC8b2ROCYv0+F54w6YU2g9MeCNN8HZ5oBApjRNoQ',
    ],
]);

$images = DB::table('images')->get(['id', 'path']);
echo "Found " . count($images) . " images.\n";

foreach ($images as $img) {
    try {
        $client->putObjectAcl([
            'Bucket' => 'dnsalvacion',
            'Key'    => $img->path,
            'ACL'    => 'public-read',
        ]);
        echo "Set public: {$img->path}\n";
    } catch (Exception $e) {
        echo "Failed {$img->path}: " . $e->getMessage() . "\n";
    }
}

echo "Donesss.\n";
