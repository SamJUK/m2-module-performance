<?php
declare(strict_types=1);
// phpcs:disable Magento2.Functions.DiscouragedFunction,Magento2.Functions.StaticFunction -- build or shutdown time, no app to inject

namespace SamJUK\Performance\Build;

// Compiled DI metadata (~8.5MB per area) as class constants that preload.php links once at FPM start.
// <area>.php becomes a stub that returns the constant when it was preloaded, and the full file otherwise.
class DiConstants
{
    public const AREAS = ['global', 'frontend', 'webapi_rest'];
    private const MARKER = '// samjuk/m2-module-performance stub';

    public static function className(string $area): string
    {
        return 'SamJUKPerformanceDi_' . $area;
    }

    /** @return list<string> areas written */
    public function write(string $meta): array
    {
        $done = [];
        foreach (self::AREAS as $area) {
            $file = "$meta/$area.php";
            $full = "$meta/$area.full.php";
            if (!is_file($file)) {
                continue;
            }
            if (!str_contains(file_get_contents($file, length: 200), self::MARKER)) {
                rename($file, $full);
            }
            $source = file_get_contents($full);
            if (!str_starts_with($source, '<?php return ') || !str_ends_with($source, ';')) {
                throw new \RuntimeException("$full is not in the setup:di:compile format");
            }
            $class = self::className($area);
            file_put_contents("$meta/$area.const.php", "<?php\nfinal class $class\n{\n    public const D = "
                . substr($source, 13, -1) . ";\n}\n");
            $stub = "return class_exists('$class', false) ? $class::D : include __DIR__ . '/$area.full.php';";
            file_put_contents($file, "<?php\n" . self::MARKER . "\n" . $stub . "\n");
            $done[] = $area;
        }
        return $done;
    }
}
