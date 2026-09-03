<?php

namespace App\Interop;

use function Safe\preg_match as safeMatch;

safeMatch('/aliased-drop-in/', $subject);
\Safe\preg_split('/qualified-drop-in/', $subject);
