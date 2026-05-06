<?php

declare(strict_types=1);

/**
 * Merges Norwegian UI strings into lang/nb.json for JSON translations used by __() in Filament.
 */
$scanDirs = [
    __DIR__.'/../app/Filament',
    __DIR__.'/../app/Providers/Filament',
];
$pattern = "/__\\('((?:[^'\\\\\\\\]|\\\\\\\\.)*)'\\)/";

$strings = [];
foreach ($scanDirs as $filamentDir) {
    if (! is_dir($filamentDir)) {
        continue;
    }
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($filamentDir, FilesystemIterator::SKIP_DOTS)
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
            if ($s === '' || is_numeric($s)) {
                continue;
            }
            if (str_starts_with($s, 'filament.')) {
                continue;
            }
            if (str_contains($s, '::')) {
                continue;
            }
            $strings[$s] = true;
        }
    }
}

/** @var array<string, string> $map */
$map = array_merge(
    require __DIR__.'/filament_nb_exact_ui.php',
    require __DIR__.'/filament_nb_extra_ui.php',
);

$nbJsonPath = __DIR__.'/../lang/nb.json';
$existing = json_decode(file_get_contents($nbJsonPath), true, flags: JSON_THROW_ON_ERROR);

$added = 0;
foreach (array_keys($strings) as $en) {
    if (! array_key_exists($en, $map)) {
        continue;
    }
    $nb = $map[$en];
    if (! array_key_exists($en, $existing) || $existing[$en] !== $nb) {
        $existing[$en] = $nb;
        $added++;
    }
}

ksort($existing, SORT_STRING);
file_put_contents(
    $nbJsonPath,
    json_encode($existing, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
);

$missing = array_diff(array_keys($strings), array_keys($map));

echo 'Unique __() literals in Filament paths: '.count($strings).PHP_EOL;
echo 'Entries merged from exact map: '.$added.PHP_EOL;
echo 'Strings still without nb mapping: '.count($missing).PHP_EOL;
