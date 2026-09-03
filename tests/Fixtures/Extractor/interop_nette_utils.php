<?php

namespace App\Interop;

use Nette\Utils\Strings;

final class NetteUtils
{
    public function run(string $subject): void
    {
        Strings::match($subject, '/second-argument/');
        Strings::replace($subject, ['/replace-key/' => 'x']);
    }
}
