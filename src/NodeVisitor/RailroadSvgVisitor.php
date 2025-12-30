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

namespace RegexParser\NodeVisitor;

use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CalloutNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\ClassOperationNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\ControlCharNode;
use RegexParser\Node\DefineNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LimitMatchNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\ScriptRunNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\Node\VersionConditionNode;

/**
 * Generates a basic railroad-style SVG diagram of the regex AST.
 */
final class RailroadSvgVisitor extends AbstractNodeVisitor
{
    private const FONT_SIZE = 12;
    private const CHAR_WIDTH = 7;
    private const NODE_HEIGHT = 28;
    private const NODE_RADIUS = 6;
    private const NODE_PADDING_X = 10;
    private const MIN_NODE_WIDTH = 24;
    private const H_GAP = 20;
    private const V_GAP = 18;
    private const BRANCH_GAP = 20;
    private const SIDE_PADDING = 12;
    private const LOOP_HEIGHT = 18;
    private const BYPASS_HEIGHT = 12;
    private const LABEL_HEIGHT = 14;
    private const LABEL_GAP = 6;
    private const CANVAS_PADDING = 20;

    #[\Override]
    public function visitRegex(RegexNode $node): string
    {
        $label = 'Regex';
        if ('' !== $node->flags) {
            $label .= ' (flags: '.$node->flags.')';
        }

        $labelLayout = $this->createNodeLayout($label, 'node');
        $patternLayout = $node->pattern->accept($this);
        $layout = $this->layoutSequence([$labelLayout, $patternLayout]);

        return $this->renderSvg($layout);
    }

    #[\Override]
    public function visitAlternation(AlternationNode $node)
    {
        $layouts = [];
        foreach ($node->alternatives as $child) {
            $layouts[] = $child->accept($this);
        }

        return $this->layoutAlternation($layouts);
    }

    #[\Override]
    public function visitSequence(SequenceNode $node)
    {
        $layouts = [];
        foreach ($node->children as $child) {
            $layouts[] = $child->accept($this);
        }

        return $this->layoutSequence($layouts);
    }

    #[\Override]
    public function visitGroup(GroupNode $node)
    {
        $label = 'Group ('.$this->describeGroupType($node).')';
        if (GroupType::T_GROUP_NAMED === $node->type && null !== $node->name) {
            $label .= ' name="'.$node->name.'"';
        }
        if (GroupType::T_GROUP_INLINE_FLAGS === $node->type && null !== $node->flags && '' !== $node->flags) {
            $label .= ' flags="'.$node->flags.'"';
        }

        return $this->layoutWithLabel($label, [$node->child->accept($this)]);
    }

    #[\Override]
    public function visitQuantifier(QuantifierNode $node)
    {
        return $this->layoutQuantifier($node);
    }

    #[\Override]
    public function visitLiteral(LiteralNode $node)
    {
        $value = addcslashes($node->value, "\0..\37\177..\377");
        if ('' === $value) {
            $value = '(empty)';
        }

        return $this->createNodeLayout("'".$value."'", 'node literal');
    }

    #[\Override]
    public function visitCharLiteral(CharLiteralNode $node)
    {
        return $this->createNodeLayout($node->originalRepresentation, 'node literal');
    }

    #[\Override]
    public function visitCharType(CharTypeNode $node)
    {
        return $this->createNodeLayout('\\'.$node->value, 'node control');
    }

    #[\Override]
    public function visitUnicode(UnicodeNode $node)
    {
        return $this->createNodeLayout('\\x'.$node->code, 'node control');
    }

    #[\Override]
    public function visitDot(DotNode $node)
    {
        return $this->createNodeLayout('.', 'node');
    }

    #[\Override]
    public function visitAnchor(AnchorNode $node)
    {
        return $this->createNodeLayout($node->value, 'node anchor');
    }

    #[\Override]
    public function visitAssertion(AssertionNode $node)
    {
        return $this->createNodeLayout('\\'.$node->value, 'node');
    }

    #[\Override]
    public function visitKeep(KeepNode $node)
    {
        return $this->createNodeLayout('\\K', 'node');
    }

    #[\Override]
    public function visitCharClass(CharClassNode $node)
    {
        $label = $node->isNegated ? 'CharClass (negated)' : 'CharClass';

        return $this->layoutWithLabel($label, [$node->expression->accept($this)]);
    }

    #[\Override]
    public function visitRange(RangeNode $node)
    {
        return $this->layoutWithLabel('Range', [
            $node->start->accept($this),
            $node->end->accept($this),
        ]);
    }

    #[\Override]
    public function visitBackref(BackrefNode $node)
    {
        $ref = $node->ref;
        $display = str_starts_with($ref, '\\') ? $ref : '\\'.$ref;

        return $this->createNodeLayout('Backref '.$display, 'node');
    }

    #[\Override]
    public function visitClassOperation(ClassOperationNode $node)
    {
        return $this->layoutWithLabel('ClassOp ('.$node->type->value.')', [
            $node->left->accept($this),
            $node->right->accept($this),
        ]);
    }

    #[\Override]
    public function visitControlChar(ControlCharNode $node)
    {
        return $this->createNodeLayout('\\c'.$node->char, 'node control');
    }

    #[\Override]
    public function visitScriptRun(ScriptRunNode $node)
    {
        return $this->createNodeLayout('script_run:'.$node->script, 'node');
    }

    #[\Override]
    public function visitVersionCondition(VersionConditionNode $node)
    {
        return $this->createNodeLayout('VERSION '.$node->operator.' '.$node->version, 'node');
    }

    #[\Override]
    public function visitUnicodeProp(UnicodePropNode $node)
    {
        $inner = $node->hasBraces ? trim($node->prop, '{}') : $node->prop;

        return $this->createNodeLayout('\\p{'.$inner.'}', 'node');
    }

    #[\Override]
    public function visitPosixClass(PosixClassNode $node)
    {
        return $this->createNodeLayout('[:'.$node->class.':]', 'node');
    }

    #[\Override]
    public function visitComment(CommentNode $node)
    {
        return $this->createNodeLayout('Comment', 'node');
    }

    #[\Override]
    public function visitConditional(ConditionalNode $node)
    {
        $branches = [
            $node->yes->accept($this),
            $node->no->accept($this),
        ];

        $branchLayout = $this->layoutAlternation($branches);

        return $this->layoutWithLabel('Conditional', [
            $node->condition->accept($this),
            $branchLayout,
        ]);
    }

    #[\Override]
    public function visitSubroutine(SubroutineNode $node)
    {
        return $this->createNodeLayout('Subroutine '.$node->reference, 'node');
    }

    #[\Override]
    public function visitPcreVerb(PcreVerbNode $node)
    {
        return $this->createNodeLayout('*'.$node->verb, 'node');
    }

    #[\Override]
    public function visitDefine(DefineNode $node)
    {
        return $this->layoutWithLabel('Define', [$node->content->accept($this)]);
    }

    #[\Override]
    public function visitLimitMatch(LimitMatchNode $node)
    {
        return $this->createNodeLayout('*LIMIT_MATCH='.$node->limit, 'node');
    }

    #[\Override]
    public function visitCallout(CalloutNode $node)
    {
        if (null === $node->identifier) {
            $label = '?C';
        } elseif ($node->isStringIdentifier) {
            $label = '?C="'.$node->identifier.'"';
        } else {
            $label = '?C'.$node->identifier;
        }

        return $this->createNodeLayout($label, 'node');
    }

    /**
     * @param array<int, array<string, mixed>> $layouts
     *
     * @return array<string, mixed>
     */
    private function layoutSequence(array $layouts): array
    {
        if (0 === \count($layouts)) {
            return $this->createNodeLayout('Empty', 'node');
        }

        if (1 === \count($layouts)) {
            return $layouts[0];
        }

        $height = 0;
        $width = 0;
        foreach ($layouts as $layout) {
            $height = max($height, (int) $layout['height']);
            $width += (int) $layout['width'];
        }
        $width += self::H_GAP * (\count($layouts) - 1);

        $nodes = [];
        $paths = [];
        $texts = [];
        $x = 0;
        $prevLayout = null;
        $prevOffsetX = 0;
        foreach ($layouts as $index => $layout) {
            $offsetY = (int) floor(($height - (int) $layout['height']) / 2);
            $offsetLayout = $this->offsetLayout($layout, $x, $offsetY);
            $nodes = array_merge($nodes, $offsetLayout['nodes']);
            $paths = array_merge($paths, $offsetLayout['paths']);
            $texts = array_merge($texts, $offsetLayout['texts']);

            if (null !== $prevLayout) {
                $midY = (int) floor($height / 2);
                $prevExitX = $prevOffsetX + (int) $prevLayout['exitX'];
                $currEntryX = $x + (int) $layout['entryX'];
                $paths[] = $this->line([$prevExitX, $midY], [$currEntryX, $midY]);
            }

            $prevLayout = $layout;
            $prevOffsetX = $x;
            $x += (int) $layout['width'] + self::H_GAP;
        }

        return [
            'width' => $width,
            'height' => $height,
            'entryX' => 0,
            'entryY' => (int) floor($height / 2),
            'exitX' => $width,
            'exitY' => (int) floor($height / 2),
            'nodes' => $nodes,
            'paths' => $paths,
            'texts' => $texts,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $layouts
     *
     * @return array<string, mixed>
     */
    private function layoutAlternation(array $layouts): array
    {
        if (0 === \count($layouts)) {
            return $this->createNodeLayout('Empty', 'node');
        }

        if (1 === \count($layouts)) {
            return $layouts[0];
        }

        $maxWidth = 0;
        $height = 0;
        foreach ($layouts as $layout) {
            $maxWidth = max($maxWidth, (int) $layout['width']);
            $height += (int) $layout['height'];
        }
        $height += self::V_GAP * (\count($layouts) - 1);

        $width = $maxWidth + (self::BRANCH_GAP * 2);
        $nodes = [];
        $paths = [];
        $texts = [];
        $y = 0;
        $topY = null;
        $bottomY = null;
        foreach ($layouts as $layout) {
            $offsetLayout = $this->offsetLayout($layout, self::BRANCH_GAP, $y);
            $nodes = array_merge($nodes, $offsetLayout['nodes']);
            $paths = array_merge($paths, $offsetLayout['paths']);
            $texts = array_merge($texts, $offsetLayout['texts']);

            $centerY = $y + (int) $layout['entryY'];
            $topY ??= $centerY;
            $bottomY = $centerY;

            $paths[] = $this->line([0, $centerY], [self::BRANCH_GAP + (int) $layout['entryX'], $centerY]);
            $paths[] = $this->line([self::BRANCH_GAP + (int) $layout['exitX'], $centerY], [$width, $centerY]);

            $y += (int) $layout['height'] + self::V_GAP;
        }

        $paths[] = $this->line([0, (int) $topY], [0, (int) $bottomY]);
        $paths[] = $this->line([$width, (int) $topY], [$width, (int) $bottomY]);

        return [
            'width' => $width,
            'height' => $height,
            'entryX' => 0,
            'entryY' => (int) floor($height / 2),
            'exitX' => $width,
            'exitY' => (int) floor($height / 2),
            'nodes' => $nodes,
            'paths' => $paths,
            'texts' => $texts,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $childLayouts
     *
     * @return array<string, mixed>
     */
    private function layoutWithLabel(string $label, array $childLayouts): array
    {
        $labelLayout = $this->createNodeLayout($label, 'node');

        return $this->layoutSequence(array_merge([$labelLayout], $childLayouts));
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutQuantifier(QuantifierNode $node): array
    {
        $childLayout = $node->node->accept($this);
        $needsLoop = $this->isLoopQuantifier($node->quantifier);
        $needsBypass = $this->isOptionalQuantifier($node->quantifier);
        $topExtra = max(self::LABEL_HEIGHT, $needsLoop ? self::LOOP_HEIGHT + self::LABEL_GAP : 0, $needsBypass ? self::BYPASS_HEIGHT + self::LABEL_GAP : 0);

        $width = (int) $childLayout['width'] + (self::SIDE_PADDING * 2);
        $height = (int) $childLayout['height'] + $topExtra;
        $childOffsetX = self::SIDE_PADDING;
        $childOffsetY = $topExtra;

        $offsetLayout = $this->offsetLayout($childLayout, $childOffsetX, $childOffsetY);
        $nodes = $offsetLayout['nodes'];
        $paths = $offsetLayout['paths'];
        $texts = $offsetLayout['texts'];

        $midY = $childOffsetY + (int) $childLayout['entryY'];
        $paths[] = $this->line([0, $midY], [$childOffsetX + (int) $childLayout['entryX'], $midY]);
        $paths[] = $this->line([$childOffsetX + (int) $childLayout['exitX'], $midY], [$width, $midY]);

        if ($needsLoop) {
            $loopTopY = $childOffsetY - self::LOOP_HEIGHT;
            $paths[] = $this->polyline([
                [$childOffsetX + (int) $childLayout['exitX'], $midY],
                [$childOffsetX + (int) $childLayout['exitX'], $loopTopY],
                [$childOffsetX + (int) $childLayout['entryX'], $loopTopY],
                [$childOffsetX + (int) $childLayout['entryX'], $midY],
            ]);
        }

        if ($needsBypass) {
            $bypassY = $childOffsetY - self::BYPASS_HEIGHT;
            $paths[] = $this->polyline([
                [0, $midY],
                [0, $bypassY],
                [$width, $bypassY],
                [$width, $midY],
            ]);
        }

        $labelX = $childOffsetX + (int) floor((int) $childLayout['width'] / 2);
        $labelY = max(8, $childOffsetY - 4);
        $texts[] = [
            'x' => $labelX,
            'y' => $labelY,
            'text' => $node->quantifier,
            'class' => 'quantifier',
        ];

        return [
            'width' => $width,
            'height' => $height,
            'entryX' => 0,
            'entryY' => $midY,
            'exitX' => $width,
            'exitY' => $midY,
            'nodes' => $nodes,
            'paths' => $paths,
            'texts' => $texts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createNodeLayout(string $label, string $class): array
    {
        $textWidth = max(self::MIN_NODE_WIDTH, $this->measureTextWidth($label));
        $width = $textWidth + (self::NODE_PADDING_X * 2);
        $height = self::NODE_HEIGHT;

        return [
            'width' => $width,
            'height' => $height,
            'entryX' => 0,
            'entryY' => (int) floor($height / 2),
            'exitX' => $width,
            'exitY' => (int) floor($height / 2),
            'nodes' => [[
                'x' => 0,
                'y' => 0,
                'width' => $width,
                'height' => $height,
                'label' => $label,
                'class' => $class,
            ]],
            'paths' => [],
            'texts' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function offsetLayout(array $layout, int $dx, int $dy): array
    {
        $nodes = [];
        foreach ($layout['nodes'] as $node) {
            $node['x'] += $dx;
            $node['y'] += $dy;
            $nodes[] = $node;
        }

        $paths = [];
        foreach ($layout['paths'] as $path) {
            $points = [];
            foreach ($path['points'] as $point) {
                $points[] = [$point[0] + $dx, $point[1] + $dy];
            }
            $paths[] = ['points' => $points];
        }

        $texts = [];
        foreach ($layout['texts'] as $text) {
            $text['x'] += $dx;
            $text['y'] += $dy;
            $texts[] = $text;
        }

        return [
            'width' => $layout['width'],
            'height' => $layout['height'],
            'entryX' => $layout['entryX'] + $dx,
            'entryY' => $layout['entryY'] + $dy,
            'exitX' => $layout['exitX'] + $dx,
            'exitY' => $layout['exitY'] + $dy,
            'nodes' => $nodes,
            'paths' => $paths,
            'texts' => $texts,
        ];
    }

    /**
     * @param array<int, int> $from
     * @param array<int, int> $to
     *
     * @return array<string, array<int, array<int, int>>>
     */
    private function line(array $from, array $to): array
    {
        return ['points' => [$from, $to]];
    }

    /**
     * @param array<int, array<int, int>> $points
     *
     * @return array<string, array<int, array<int, int>>>
     */
    private function polyline(array $points): array
    {
        return ['points' => $points];
    }

    private function measureTextWidth(string $text): int
    {
        return (int) (\strlen($text) * self::CHAR_WIDTH);
    }

    private function renderSvg(array $layout): string
    {
        $offsetLayout = $this->offsetLayout($layout, self::CANVAS_PADDING, self::CANVAS_PADDING);
        $width = (int) $layout['width'] + (self::CANVAS_PADDING * 2);
        $height = (int) $layout['height'] + (self::CANVAS_PADDING * 2);

        $svg = [];
        $svg[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $svg[] = \sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">',
            $width,
            $height,
            $width,
            $height
        );
        $svg[] = '<defs>';
        $svg[] = '<style>';
        $svg[] = '  .path { stroke: #999999; stroke-width: 2; fill: none; stroke-linecap: round; stroke-linejoin: round; }';
        $svg[] = '  .node { fill: #ffffff; stroke: #b5b5b5; stroke-width: 2; }';
        $svg[] = '  .node.literal { fill: #f0f0f0; }';
        $svg[] = '  .node.anchor { fill: #daf5da; stroke: #79b879; }';
        $svg[] = '  .node.control { fill: #ffd9e1; stroke: #e49ab0; }';
        $svg[] = '  .label { font-family: monospace; font-size: '.self::FONT_SIZE.'px; fill: #333333; text-anchor: middle; dominant-baseline: middle; }';
        $svg[] = '  .quantifier { font-family: monospace; font-size: 11px; fill: #666666; text-anchor: middle; dominant-baseline: middle; }';
        $svg[] = '</style>';
        $svg[] = '</defs>';
        $svg[] = \sprintf('<rect width="%d" height="%d" fill="#ffffff"/>', $width, $height);

        foreach ($offsetLayout['paths'] as $path) {
            $svg[] = \sprintf('<path class="path" d="%s"/>', $this->renderPath($path['points']));
        }

        foreach ($offsetLayout['nodes'] as $node) {
            $svg[] = \sprintf(
                '<rect class="%s" x="%d" y="%d" width="%d" height="%d" rx="%d" ry="%d"/>',
                $this->escapeAttribute($node['class']),
                $node['x'],
                $node['y'],
                $node['width'],
                $node['height'],
                self::NODE_RADIUS,
                self::NODE_RADIUS
            );
            $svg[] = \sprintf(
                '<text class="label" x="%d" y="%d">%s</text>',
                $node['x'] + (int) floor($node['width'] / 2),
                $node['y'] + (int) floor($node['height'] / 2),
                $this->escapeText($node['label'])
            );
        }

        foreach ($offsetLayout['texts'] as $text) {
            $svg[] = \sprintf(
                '<text class="%s" x="%d" y="%d">%s</text>',
                $this->escapeAttribute($text['class']),
                $text['x'],
                $text['y'],
                $this->escapeText($text['text'])
            );
        }

        $svg[] = '</svg>';

        return implode("\n", $svg);
    }

    /**
     * @param array<int, array<int, int>> $points
     */
    private function renderPath(array $points): string
    {
        if (0 === \count($points)) {
            return '';
        }

        $commands = ['M '.$points[0][0].' '.$points[0][1]];
        for ($i = 1; $i < \count($points); $i++) {
            $commands[] = 'L '.$points[$i][0].' '.$points[$i][1];
        }

        return implode(' ', $commands);
    }

    private function escapeText(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    private function describeGroupType(GroupNode $node): string
    {
        return match ($node->type) {
            GroupType::T_GROUP_CAPTURING => 'capturing',
            GroupType::T_GROUP_NON_CAPTURING => 'non-capturing',
            GroupType::T_GROUP_NAMED => 'named',
            GroupType::T_GROUP_LOOKAHEAD_POSITIVE => 'positive lookahead',
            GroupType::T_GROUP_LOOKAHEAD_NEGATIVE => 'negative lookahead',
            GroupType::T_GROUP_LOOKBEHIND_POSITIVE => 'positive lookbehind',
            GroupType::T_GROUP_LOOKBEHIND_NEGATIVE => 'negative lookbehind',
            GroupType::T_GROUP_INLINE_FLAGS => 'inline flags',
            GroupType::T_GROUP_ATOMIC => 'atomic',
            GroupType::T_GROUP_BRANCH_RESET => 'branch reset',
        };
    }

    private function isLoopQuantifier(string $quantifier): bool
    {
        if ('*' === $quantifier || '+' === $quantifier) {
            return true;
        }

        $range = $this->parseRangeQuantifier($quantifier);
        if (null === $range) {
            return false;
        }

        [$min, $max] = $range;

        return null === $max;
    }

    private function isOptionalQuantifier(string $quantifier): bool
    {
        if ('?' === $quantifier) {
            return true;
        }

        $range = $this->parseRangeQuantifier($quantifier);
        if (null === $range) {
            return false;
        }

        [$min, $max] = $range;

        return 0 === $min && 1 === $max;
    }

    /**
     * @return array{0: int, 1: int|null}|null
     */
    private function parseRangeQuantifier(string $quantifier): ?array
    {
        if (preg_match('/^\{(\d+)\}$/', $quantifier, $matches)) {
            $value = (int) $matches[1];

            return [$value, $value];
        }

        if (preg_match('/^\{(\d+),\}$/', $quantifier, $matches)) {
            return [(int) $matches[1], null];
        }

        if (preg_match('/^\{,(\d+)\}$/', $quantifier, $matches)) {
            return [0, (int) $matches[1]];
        }

        if (preg_match('/^\{(\d+),(\d+)\}$/', $quantifier, $matches)) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        return null;
    }
}
