<?php
/** Copyright © 2026 Saint Vertex. Original branding dictionary refresh helper. */

require __DIR__ . '/app/vendor/autoload.php';
$app = require __DIR__ . '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$folder = __DIR__ . '/app/modules/SaintVertex/Public/lang';
if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}
foreach (array_keys(config('nexopos.languages')) as $locale) {
    file_put_contents($folder . '/' . $locale . '.json', json_encode(Modules\SaintVertex\Services\BrandingText::translations($locale), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}
echo "Vertex POS static display dictionaries updated.\n";
