<?php
preg_replace('/QUICK_CHECK = .*;/m', "QUICK_CHECK = {$quickCheck};", (string) $fs->readFile($file));
