<?php

namespace App\Interop;

/**
 * A project class named Preg without the composer/pcre import: its argument
 * is not a pattern and must be left alone.
 */
final class ShadowedPreg
{
    public function run(string $subject): void
    {
        Preg::match('not a pattern at all', $subject);
    }
}
