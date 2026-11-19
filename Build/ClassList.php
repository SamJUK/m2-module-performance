<?php
declare(strict_types=1);
// phpcs:disable Magento2.Functions.DiscouragedFunction -- build or shutdown time, no app to inject

namespace SamJUK\Performance\Build;

use Magento\Framework\Autoload\AutoloaderRegistry;

// Filters the captured list down to classes that exist in this build and are safe to preload. A file
// with top-level code (Braintree's `Configuration::reset();`) would run it once in the preload process,
// and every request would see the class without it.
class ClassList
{
    private const SKIP = [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML, T_CLOSE_TAG];
    private const DECLARATIONS = [T_NAMESPACE, T_USE, T_DECLARE, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_FUNCTION,
        T_ABSTRACT, T_FINAL, T_READONLY, T_ATTRIBUTE, T_CONST, T_IF, T_RETURN];

    public function write(string $source, string $target): array
    {
        $loader = AutoloaderRegistry::getAutoloader();
        $kept = $dropped = $verdict = [];
        foreach (file($source, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $name) {
            $file = $loader->findFile($name);
            if (!$file) {
                $dropped[$name] = 'not found';
                continue;
            }
            $file = realpath($file);
            $verdict[$file] ??= $this->hasTopLevelCode($file);
            if ($verdict[$file]) {
                $dropped[$name] = 'top-level code';
                continue;
            }
            $kept[] = $name;
        }
        file_put_contents($target, '<?php return ' . var_export($kept, true) . ';');
        return [count($kept), $dropped];
    }

    private function hasTopLevelCode(string $file): bool
    {
        $depth = 0;
        $statement = null;
        foreach (token_get_all(file_get_contents($file)) as $token) {
            $id = is_array($token) ? $token[0] : $token;
            if ($id === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
            } elseif ($id === '}') {
                $depth--;
            }
            if ($depth > 0) {
                continue;
            }
            if ($statement === null && !in_array($id, self::SKIP, true) && $id !== ';' && $id !== '}') {
                $statement = $id;
            }
            if ($id === ';' || $id === '}') {
                if ($statement !== null && !in_array($statement, self::DECLARATIONS, true)) {
                    return true;
                }
                $statement = null;
            }
        }
        return false;
    }
}
