<?php

preg_replace(['/array-a/', '/array-b/'], ['a', 'b'], $subject);
preg_replace_callback_array(['/keys-a/' => 'a', '/keys-b/' => 'b'], $subject);
