<?php
        $fs->dumpFile($file, preg_replace('/QUICK_CHECK = .*;/m', "QUICK_CHECK = {$quickCheck};", (string) $fs->readFile($file)));
        preg_match('{^(?<codePoints>[\w ]+) +; [\w-]+ +# (?<emoji>.+) E\d+\.\d+ ?(?<name>.+)$}Uu', (string) $line, $matches);
