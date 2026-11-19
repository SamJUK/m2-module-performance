<?php
// phpcs:ignoreFile -- runs before Magento boots, so it must require, include and exit directly.
// opcache.preload entry, pointed at by the ini preload:build --ini writes. Fails open: missing build
// output means less (or nothing) preloaded, never a dead FPM.
$bp = __DIR__;
while (!is_file("$bp/app/etc/config.php") && $bp !== dirname($bp)) {
    $bp = dirname($bp);
}
$meta = "$bp/generated/metadata";
if (!is_file("$bp/app/autoload.php") || !is_file("$meta/global.php")) {
    error_log('preload: no compiled Magento found, skipping');
    return;
}
require "$bp/app/autoload.php";

if (!is_file("$bp/vendor/composer/autoload_registrations.php")) {
    error_log('preload: registration manifest missing, run preload:build after the last composer dump-autoload');
}

$classes = is_file("$meta/preload-classes.php") ? include "$meta/preload-classes.php" : [];
$linked = $failed = 0;
foreach ($classes as $name) {
    try {
        (class_exists($name) || interface_exists($name) || trait_exists($name) || enum_exists($name)) ? $linked++ : $failed++;
    } catch (\Throwable) {
        $failed++;
    }
}

$areas = [];
foreach (glob("$meta/*.const.php") as $file) {
    require $file;
    $areas[] = basename($file, '.const.php');
}
error_log("preload: $linked classes linked, $failed failed, DI constants for " . (implode(', ', $areas) ?: 'no areas'));
