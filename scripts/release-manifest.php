<?php

/*
| Writes bootstrap/release-manifest.json into a built release folder: the
| SHA-256 of every file the release ships. The one-click updater compares a
| site's files against it to see which ones the owner changed, and which old
| files a newer release no longer has.
|
| Usage: php scripts/release-manifest.php <build-folder> <version>
*/

[$script, $root, $version] = $argv + [null, null, null];

if (! $root || ! $version || ! is_dir($root)) {
    fwrite(STDERR, "Usage: php scripts/release-manifest.php <build-folder> <version>\n");
    exit(1);
}

$root = rtrim(realpath($root), '/');
$target = 'bootstrap/release-manifest.json';
$files = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

foreach ($iterator as $file) {
    if (! $file->isFile() || $file->isLink()) {
        continue;
    }

    $path = substr($file->getPathname(), strlen($root) + 1);

    // A site's own data is never part of a release.
    if ($path === $target || $path === '.env' || str_starts_with($path, 'storage/')) {
        continue;
    }

    $files[$path] = hash_file('sha256', $file->getPathname());
}

ksort($files, SORT_STRING);

file_put_contents($root.'/'.$target, json_encode(['version' => $version, 'files' => $files], JSON_UNESCAPED_SLASHES));
printf("  %d files listed in %s\n", count($files), $target);
