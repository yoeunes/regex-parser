<?php

declare(strict_types=1);

return 1 === preg_match('{^application/(?:\w+\++)*json$}i', 'application/json');
