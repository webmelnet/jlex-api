<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$images = DB::table('images')->where('url', 'like', '%amazonaws.com%')->get();

echo "Found " . count($images) . " images to update.\n";

foreach ($images as $img) {
    $newUrl = 'https://dnsalvacion.sgp1.digitaloceanspaces.com/' . $img->path;
    DB::table('images')->where('id', $img->id)->update(['url' => $newUrl]);
    echo "Updated ID {$img->id}: {$newUrl}\n";
}

echo "Done.\n";
