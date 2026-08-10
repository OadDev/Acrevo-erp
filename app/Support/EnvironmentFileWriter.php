<?php

namespace App\Support;

/**
 * Reads and rewrites .env key/value pairs in place, for the web installer.
 * Preserves every line it doesn't touch; replaces an existing KEY=... line
 * or appends a new one otherwise.
 */
class EnvironmentFileWriter
{
    public static function path(): string
    {
        return base_path('.env');
    }

    public static function ensureExists(): void
    {
        if (! file_exists(self::path())) {
            copy(base_path('.env.example'), self::path());
        }
    }

    /**
     * @param  array<string, string>  $values
     */
    public static function set(array $values): void
    {
        self::ensureExists();

        $content = file_get_contents(self::path());

        foreach ($values as $key => $value) {
            $line = $key.'='.self::escape($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';

            $content = preg_match($pattern, $content)
                ? preg_replace($pattern, $line, $content)
                : rtrim($content)."\n".$line."\n";
        }

        file_put_contents(self::path(), $content);
    }

    private static function escape(string $value): string
    {
        if ($value === '' || preg_match('/[\s#"\'\$]/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
