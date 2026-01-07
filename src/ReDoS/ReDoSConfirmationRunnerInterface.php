<?php

declare(strict_types=1);

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\ReDoS;

interface ReDoSConfirmationRunnerInterface
{
    public function confirm(string $regex, ReDoSAnalysis $analysis, ?ReDoSConfirmOptions $options = null): ReDoSConfirmation;
}
