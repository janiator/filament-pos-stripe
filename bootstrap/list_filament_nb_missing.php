<?php

declare(strict_types=1);

$pattern = "/__\\('((?:[^'\\\\\\\\]|\\\\\\\\.)*)'\\)/";
$strings = [];
$scanDirs = [
    __DIR__.'/../app/Filament',
    __DIR__.'/../app/Providers/Filament',
];
foreach ($scanDirs as $dir) {
    if (! is_dir($dir)) {
        continue;
    }
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($rii as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $c = file_get_contents($file->getPathname());
        if (! preg_match_all($pattern, $c, $m)) {
            continue;
        }
        foreach ($m[1] as $raw) {
            $s = stripcslashes($raw);
            if ($s === '' || is_numeric($s) || str_starts_with($s, 'filament.') || str_contains($s, '::')) {
                continue;
            }
            $strings[$s] = true;
        }
    }
}
$map = array_merge(
    require __DIR__.'/filament_nb_exact_ui.php',
    require __DIR__.'/filament_nb_extra_ui.php',
);
$missing = array_values(array_diff(array_keys($strings), array_keys($map)));
sort($missing);
file_put_contents(__DIR__.'/../storage/app/filament_nb_missing.txt', implode("\n", $missing));
echo count($missing)." missing\n";
