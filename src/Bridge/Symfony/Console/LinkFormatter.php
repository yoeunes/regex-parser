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

namespace RegexParser\Bridge\Symfony\Console;

use Symfony\Component\Console\Formatter\OutputFormatter;

final class LinkFormatter
{
    /**
     * @var array<string, string>
     */
    private const IDE_LINK_FORMATS = [
        'textmate' => 'txmt://open?url=file://%f&line=%l',
        'macvim' => 'mvim://open?url=file://%f&line=%l',
        'emacs' => 'emacs://open?url=file://%f&line=%l',
        'sublime' => 'subl://open?url=file://%f&line=%l',
        'phpstorm' => 'phpstorm://open?file=%f&line=%l',
        'atom' => 'atom://core/open/file?filename=%f&line=%l',
        'vscode' => 'vscode://file/%f:%l',
    ];

    private bool $supportsHyperlinks;

    public function __construct(
        private readonly ?string $editorUrlTemplate,
        private readonly RelativePathHelper $relativePathHelper,
    ) {
        $this->supportsHyperlinks = $this->detectHyperlinkSupport();
    }

    public function format(string $file, ?int $line, string $label, int $column = 1, ?string $fallbackLabel = null): string
    {
        if (null === $line) {
            return $label;
        }

        $url = $this->buildEditorUrl($file, $line, $column);
        if (null === $url) {
            return $label;
        }

        if (!$this->supportsHyperlinks) {
            if (null !== $fallbackLabel) {
                return $fallbackLabel;
            }

            return $this->relativePathHelper->getRelativePath($file).':'.$line;
        }

        return sprintf('<href=%s>%s</>', OutputFormatter::escape($url), $label);
    }

    public function getRelativePath(string $file): string
    {
        return $this->relativePathHelper->getRelativePath($file);
    }

    private function buildEditorUrl(string $file, int $line, int $column): ?string
    {
        if (null === $this->editorUrlTemplate || '' === $this->editorUrlTemplate) {
            return null;
        }

        $template = self::IDE_LINK_FORMATS[$this->editorUrlTemplate] ?? $this->editorUrlTemplate;

        return strtr($template, [
            '%f' => $file,
            '%file%' => $file,
            '%l' => (string) $line,
            '%line%' => (string) $line,
            '%c' => (string) $column,
            '%column%' => (string) $column,
            '%relFile%' => $this->relativePathHelper->getRelativePath($file),
        ]);
    }

    private function detectHyperlinkSupport(): bool
    {
        if ('JetBrains-JediTerm' === getenv('TERMINAL_EMULATOR')) {
            return false;
        }

        $konsoleVersion = getenv('KONSOLE_VERSION');
        if ($konsoleVersion && (int) $konsoleVersion <= 201100) {
            return false;
        }

        if (isset($_SERVER['IDEA_INITIAL_DIRECTORY'])) {
            return false;
        }

        return true;
    }
}
