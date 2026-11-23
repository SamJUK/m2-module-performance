<?php
declare(strict_types=1);
// phpcs:disable Magento2.Functions.DiscouragedFunction -- build or shutdown time, no app to inject

namespace SamJUK\Performance\Plugin;

use Magento\Framework\App\Http;

// On only while preload:capture has created the directory: one stat per request otherwise.
class CapturePreloadClasses
{
    public const DIR = 'var/preload-capture';

    public function beforeLaunch(Http $subject): void
    {
        $dir = BP . '/' . self::DIR;
        if (!is_dir($dir)) {
            return;
        }
        register_shutdown_function(static function () use ($dir): void {
            $names = array_merge(get_declared_interfaces(), get_declared_traits(), get_declared_classes());
            $user = array_filter(
                $names,
                static fn ($n) => !str_contains($n, '@anonymous') && !(new \ReflectionClass($n))->isInternal()
            );
            // phpcs:ignore Generic.PHP.NoSilencedErrors -- capture must never break a request; a lost sample is fine
            @file_put_contents("$dir/" . getmypid() . '-' . hrtime(true) . '.txt', implode("\n", $user));
        });
    }
}
