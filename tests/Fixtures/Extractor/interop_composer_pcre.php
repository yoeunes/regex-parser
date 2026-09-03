<?php

namespace App\Interop;

use Composer\Pcre\Preg;
use Composer\Pcre\Regex as Rx;

final class ComposerPcre
{
    public function run(string $subject): void
    {
        Preg::match('/imported/', $subject);
        Preg::isMatch('/is-match/', $subject);
        Rx::matchAll('/aliased/', $subject);
        \Composer\Pcre\Preg::split('/fully-qualified/', $subject);
        Preg::replaceCallbackArray(['/callback-a/' => 'a', '/callback-b/' => 'b'], $subject);
    }
}
