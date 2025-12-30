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
    private const NODE_HEIGHT = 26;
    private const NODE_RADIUS = 7;
    private const NODE_PADDING_X = 12;
    private const MIN_NODE_WIDTH = 26;
    private const H_GAP = 16;
    private const V_GAP = 14;
    private const BRANCH_GAP = 18;
    private const SIDE_PADDING = 10;
    private const LOOP_HEIGHT = 16;
    private const BYPASS_HEIGHT = 12;
    private const LABEL_HEIGHT = 12;
    private const LABEL_GAP = 6;
    private const CANVAS_PADDING = 18;
    private const TERMINAL_RADIUS = 7;
    private const TERMINAL_GAP = 12;
    private const GROUP_PADDING_X = 16;
    private const GROUP_PADDING_Y = 12;
    private const GROUP_LABEL_HEIGHT = 12;
    private const META_HEIGHT = 12;

    private int $groupCounter = 0;

    private int $charClassDepth = 0;

    private int $negatedCharClassDepth = 0;

    private int $groupDepth = 0;

    #[\Override]
    public function visitRegex(RegexNode $node): string
    {
        $this->groupCounter = 0;
        $this->charClassDepth = 0;
        $this->negatedCharClassDepth = 0;
        $this->groupDepth = 0;

        $patternLayout = $node->pattern->accept($this);
        $flags = '' !== $node->flags ? $node->flags : null;
        $layout = $this->wrapWithTerminals($patternLayout, $flags);

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
        $this->groupDepth++;
        $label = $this->groupLabel($node);
        $result = $this->layoutGroupBox($node->child->accept($this), $label);
        $this->groupDepth--;

        return $result;
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

        return $this->createNodeLayout("'".$value."'", $this->literalClass());
    }

    #[\Override]
    public function visitCharLiteral(CharLiteralNode $node)
    {
        return $this->createNodeLayout($node->originalRepresentation, $this->literalClass());
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
        return $this->createNodeLayout('.', 'node anychar');
    }

    #[\Override]
    public function visitAnchor(AnchorNode $node)
    {
        $class = ($this->groupDepth > 0 || $this->charClassDepth > 0) ? 'node literal' : 'node anchor';

        return $this->createNodeLayout($node->value, $class);
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
        $this->charClassDepth++;
        if ($node->isNegated) {
            $this->negatedCharClassDepth++;
        }

        $expression = $node->expression->accept($this);

        if ($node->isNegated) {
            $this->negatedCharClassDepth--;
        }
        $this->charClassDepth--;

        $label = $node->isNegated ? 'None of:' : 'Any of:';

        return $this->layoutLabelAbove($label, $expression, 'class-label');
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

        $maxAscent = 0;
        $maxDescent = 0;

        foreach ($layouts as $layout) {
            $ascent = (int) $layout['entryY'];
            $descent = (int) $layout['height'] - (int) $layout['entryY'];

            $maxAscent = max($maxAscent, $ascent);
            $maxDescent = max($maxDescent, $descent);
        }

        $height = $maxAscent + $maxDescent;
        $baselineY = $maxAscent;

        $width = 0;
        foreach ($layouts as $layout) {
            $width += (int) $layout['width'];
        }
        $width += self::H_GAP * (\count($layouts) - 1);

        $nodes = [];
        $paths = [];
        $texts = [];
        $boxes = [];
        $markers = [];

        $x = 0;
        $prevLayout = null;
        $prevExitXAbs = 0;

        foreach ($layouts as $index => $layout) {
            $offsetY = $baselineY - (int) $layout['entryY'];

            $offsetLayout = $this->offsetLayout($layout, $x, $offsetY);
            $nodes = array_merge($nodes, $offsetLayout['nodes']);
            $paths = array_merge($paths, $offsetLayout['paths']);
            $texts = array_merge($texts, $offsetLayout['texts']);
            $boxes = array_merge($boxes, $offsetLayout['boxes']);
            $markers = array_merge($markers, $offsetLayout['markers']);

            if (null !== $prevLayout) {
                $currEntryXAbs = $x + (int) $layout['entryX'];
                $paths[] = $this->line([$prevExitXAbs, $baselineY], [$currEntryXAbs, $baselineY]);
            }

            $prevLayout = $layout;
            $prevExitXAbs = $x + (int) $layout['exitX'];

            $x += (int) $layout['width'] + self::H_GAP;
        }

        return [
            'width' => $width,
            'height' => $height,
            'entryX' => 0,
            'entryY' => $baselineY,
            'exitX' => $width,
            'exitY' => $baselineY,
            'nodes' => $nodes,
            'paths' => $paths,
            'texts' => $texts,
            'boxes' => $boxes,
            'markers' => $markers,
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
        $boxes = [];
        $markers = [];
        $y = 0;
        $topY = null;
        $bottomY = null;
        foreach ($layouts as $layout) {
            $offsetLayout = $this->offsetLayout($layout, self::BRANCH_GAP, $y);
            $nodes = array_merge($nodes, $offsetLayout['nodes']);
            $paths = array_merge($paths, $offsetLayout['paths']);
            $texts = array_merge($texts, $offsetLayout['texts']);
            $boxes = array_merge($boxes, $offsetLayout['boxes']);
            $markers = array_merge($markers, $offsetLayout['markers']);

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
            'boxes' => $boxes,
            'markers' => $markers,
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
    private function layoutGroupBox(array $childLayout, string $label): array
    {
        $labelWidth = $this->measureTextWidth($label);
        $width = (int) max(
            $childLayout['width'] + (self::GROUP_PADDING_X * 2),
            $labelWidth + (self::GROUP_PADDING_X * 2),
        );
        $boxTop = self::GROUP_LABEL_HEIGHT + self::LABEL_GAP;
        $height = $boxTop + (int) $childLayout['height'] + (self::GROUP_PADDING_Y * 2);

        $childOffsetX = (int) floor(($width - (int) $childLayout['width']) / 2);
        $childOffsetY = $boxTop + self::GROUP_PADDING_Y;

        $offsetLayout = $this->offsetLayout($childLayout, $childOffsetX, $childOffsetY);
        $nodes = $offsetLayout['nodes'];
        $paths = $offsetLayout['paths'];
        $texts = $offsetLayout['texts'];
        $boxes = $offsetLayout['boxes'];
        $markers = $offsetLayout['markers'];

        $boxes[] = [
            'x' => 0,
            'y' => $boxTop,
            'width' => $width,
            'height' => $height - $boxTop,
            'rx' => 10,
            'ry' => 10,
            'class' => 'group-box',
        ];

        $texts[] = [
            'x' => (int) floor($width / 2),
            'y' => (int) floor(self::GROUP_LABEL_HEIGHT / 2),
            'text' => $label,
            'class' => 'group-label',
        ];

        return [
            'width' => $width,
            'height' => $height,
            'entryX' => $childOffsetX + (int) $childLayout['entryX'],
            'entryY' => $childOffsetY + (int) $childLayout['entryY'],
            'exitX' => $childOffsetX + (int) $childLayout['exitX'],
            'exitY' => $childOffsetY + (int) $childLayout['exitY'],
            'nodes' => $nodes,
            'paths' => $paths,
            'texts' => $texts,
            'boxes' => $boxes,
            'markers' => $markers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutLabelAbove(string $label, array $childLayout, string $class): array
    {
        $labelWidth = $this->measureTextWidth($label);
        $width = (int) max((int) $childLayout['width'], $labelWidth);
        $height = self::LABEL_HEIGHT + self::LABEL_GAP + (int) $childLayout['height'];

        $childOffsetX = (int) floor(($width - (int) $childLayout['width']) / 2);
        $childOffsetY = self::LABEL_HEIGHT + self::LABEL_GAP;

        $offsetLayout = $this->offsetLayout($childLayout, $childOffsetX, $childOffsetY);
        $nodes = $offsetLayout['nodes'];
        $paths = $offsetLayout['paths'];
        $texts = $offsetLayout['texts'];
        $boxes = $offsetLayout['boxes'];
        $markers = $offsetLayout['markers'];

        $texts[] = [
            'x' => (int) floor($width / 2),
            'y' => (int) floor(self::LABEL_HEIGHT / 2),
            'text' => $label,
            'class' => $class,
        ];

        return [
            'width' => $width,
            'height' => $height,
            'entryX' => $childOffsetX + (int) $childLayout['entryX'],
            'entryY' => $childOffsetY + (int) $childLayout['entryY'],
            'exitX' => $childOffsetX + (int) $childLayout['exitX'],
            'exitY' => $childOffsetY + (int) $childLayout['exitY'],
            'nodes' => $nodes,
            'paths' => $paths,
            'texts' => $texts,
            'boxes' => $boxes,
            'markers' => $markers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function wrapWithTerminals(array $layout, ?string $flags): array
    {
        $metaOffset = null !== $flags ? self::META_HEIGHT + self::LABEL_GAP : 0;
        $minHeight = max((int) $layout['height'], self::TERMINAL_RADIUS * 2);
        $centerOffset = (int) floor(($minHeight - (int) $layout['height']) / 2);
        $topOffset = $metaOffset + $centerOffset;

        $leftPad = (self::TERMINAL_RADIUS * 2) + self::TERMINAL_GAP;
        $rightPad = (self::TERMINAL_RADIUS * 2) + self::TERMINAL_GAP;
        $width = (int) $layout['width'] + $leftPad + $rightPad;
        $height = $minHeight + $metaOffset;

        $offsetLayout = $this->offsetLayout($layout, $leftPad, $topOffset);
        $nodes = $offsetLayout['nodes'];
        $paths = $offsetLayout['paths'];
        $texts = $offsetLayout['texts'];
        $boxes = $offsetLayout['boxes'];
        $markers = $offsetLayout['markers'];

        $trackY = $topOffset + (int) $layout['entryY'];
        $paths[] = $this->line([self::TERMINAL_RADIUS * 2, $trackY], [$leftPad + (int) $layout['entryX'], $trackY]);
        $paths[] = $this->line([$leftPad + (int) $layout['exitX'], $trackY], [$width - (self::TERMINAL_RADIUS * 2), $trackY]);

        $markers[] = [
            'cx' => self::TERMINAL_RADIUS,
            'cy' => $trackY,
            'r' => self::TERMINAL_RADIUS,
            'class' => 'terminal start',
        ];
        $markers[] = [
            'cx' => $width - self::TERMINAL_RADIUS,
            'cy' => $trackY,
            'r' => self::TERMINAL_RADIUS,
            'class' => 'terminal end',
        ];

        if (null !== $flags) {
            $texts[] = [
                'x' => $leftPad,
                'y' => (int) floor(self::META_HEIGHT / 2),
                'text' => 'flags: '.$flags,
                'class' => 'meta',
            ];
        }

        return [
            'width' => $width,
            'height' => $height,
            'entryX' => self::TERMINAL_RADIUS * 2,
            'entryY' => $trackY,
            'exitX' => $width - (self::TERMINAL_RADIUS * 2),
            'exitY' => $trackY,
            'nodes' => $nodes,
            'paths' => $paths,
            'texts' => $texts,
            'boxes' => $boxes,
            'markers' => $markers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutQuantifier(QuantifierNode $node): array
    {
        $childLayout = $node->node->accept($this);
        $needsLoop = $this->isLoopQuantifier($node->quantifier);
        $needsBypass = $this->isOptionalQuantifier($node->quantifier);
        $topExtra = max($needsLoop ? self::LOOP_HEIGHT + self::LABEL_GAP : 0, $needsBypass ? self::BYPASS_HEIGHT + self::LABEL_GAP : 0);
        $labelText = $this->describeQuantifier($node->quantifier);
        $bottomExtra = '' !== $labelText ? self::LABEL_HEIGHT + self::LABEL_GAP : 0;

        $width = (int) $childLayout['width'] + (self::SIDE_PADDING * 2);
        $height = (int) $childLayout['height'] + $topExtra + $bottomExtra;
        $childOffsetX = self::SIDE_PADDING;
        $childOffsetY = $topExtra;

        $offsetLayout = $this->offsetLayout($childLayout, $childOffsetX, $childOffsetY);
        $nodes = $offsetLayout['nodes'];
        $paths = $offsetLayout['paths'];
        $texts = $offsetLayout['texts'];
        $boxes = $offsetLayout['boxes'];
        $markers = $offsetLayout['markers'];

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
            ], 'path loop');
        }

        if ($needsBypass) {
            $bypassY = $childOffsetY - self::BYPASS_HEIGHT;
            $paths[] = $this->polyline([
                [0, $midY],
                [0, $bypassY],
                [$width, $bypassY],
                [$width, $midY],
            ], 'path bypass');
        }

        if ('' !== $labelText) {
            $labelX = (int) floor($width / 2);
            $labelY = $childOffsetY + (int) $childLayout['height'] + self::LABEL_GAP + (int) floor(self::LABEL_HEIGHT / 2);
            $texts[] = [
                'x' => $labelX,
                'y' => $labelY,
                'text' => $labelText,
                'class' => 'quantifier-label',
            ];
        }

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
            'boxes' => $boxes,
            'markers' => $markers,
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
            'boxes' => [],
            'markers' => [],
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
            $paths[] = ['points' => $points, 'class' => $path['class'] ?? 'path'];
        }

        $texts = [];
        foreach ($layout['texts'] as $text) {
            $text['x'] += $dx;
            $text['y'] += $dy;
            $texts[] = $text;
        }

        $boxes = [];
        foreach ($layout['boxes'] as $box) {
            $box['x'] += $dx;
            $box['y'] += $dy;
            $boxes[] = $box;
        }

        $markers = [];
        foreach ($layout['markers'] as $marker) {
            $marker['cx'] += $dx;
            $marker['cy'] += $dy;
            $markers[] = $marker;
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
            'boxes' => $boxes,
            'markers' => $markers,
        ];
    }

    /**
     * @param array<int, int> $from
     * @param array<int, int> $to
     *
     * @return array<string, array<int, array<int, int>>>
     */
    private function line(array $from, array $to, string $class = 'path'): array
    {
        return ['points' => [$from, $to], 'class' => $class];
    }

    /**
     * @param array<int, array<int, int>> $points
     *
     * @return array<string, array<int, array<int, int>>>
     */
    private function polyline(array $points, string $class = 'path'): array
    {
        return ['points' => $points, 'class' => $class];
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
            $height,
        );
        $svg[] = '<defs>';
        $svg[] = '<style>';
        $svg[] = '  .path { stroke: #7b8794; stroke-width: 2; fill: none; stroke-linecap: round; stroke-linejoin: round; }';
        $svg[] = '  .path.loop, .path.bypass { stroke-dasharray: 4 4; }';
        $svg[] = '  .node { fill: #e1e7ef; stroke: #a3aebd; stroke-width: 1.6; }';
        $svg[] = '  .node.literal { fill: #dbe2ea; }';
        $svg[] = '  .node.anchor { fill: #b7e8c9; stroke: #5fb184; }';
        $svg[] = '  .node.control { fill: #f6c7d6; stroke: #dd8da7; }';
        $svg[] = '  .node.anychar { fill: #cfe9c8; stroke: #79b879; }';
        $svg[] = '  .node.class-negated { fill: #f6b6b6; stroke: #e07e7e; }';
        $svg[] = '  .node.class-positive { fill: #f4d3a1; stroke: #d7a46b; }';
        $svg[] = '  .label { font-family: monospace; font-size: '.self::FONT_SIZE.'px; fill: #2f3b4c; text-anchor: middle; dominant-baseline: middle; }';
        $svg[] = '  .group-box { fill: none; stroke: #c2ccd7; stroke-width: 1.5; stroke-dasharray: 3 3; }';
        $svg[] = '  .group-label { font-family: monospace; font-size: 11px; fill: #6b7785; text-anchor: middle; dominant-baseline: middle; }';
        $svg[] = '  .class-label { font-family: monospace; font-size: 11px; fill: #6b7785; text-anchor: middle; dominant-baseline: middle; }';
        $svg[] = '  .quantifier-label { font-family: monospace; font-size: 11px; fill: #6b7785; text-anchor: middle; dominant-baseline: middle; }';
        $svg[] = '  .meta { font-family: monospace; font-size: 11px; fill: #6b7785; text-anchor: start; dominant-baseline: middle; }';
        $svg[] = '  .terminal.start { fill: #62d28c; stroke: #4fb173; stroke-width: 2; }';
        $svg[] = '  .terminal.end { fill: #6aa3ff; stroke: #4a83df; stroke-width: 2; }';
        $svg[] = '</style>';
        $svg[] = '</defs>';
        $svg[] = \sprintf('<rect width="%d" height="%d" fill="#f4f7f8"/>', $width, $height);

        foreach ($offsetLayout['boxes'] as $box) {
            $svg[] = \sprintf(
                '<rect class="%s" x="%d" y="%d" width="%d" height="%d" rx="%d" ry="%d"/>',
                $this->escapeAttribute($box['class']),
                $box['x'],
                $box['y'],
                $box['width'],
                $box['height'],
                $box['rx'],
                $box['ry'],
            );
        }

        foreach ($offsetLayout['paths'] as $path) {
            $svg[] = \sprintf(
                '<path class="%s" d="%s"/>',
                $this->escapeAttribute($path['class'] ?? 'path'),
                $this->renderPath($path['points']),
            );
        }

        foreach ($offsetLayout['markers'] as $marker) {
            $svg[] = \sprintf(
                '<circle class="%s" cx="%d" cy="%d" r="%d"/>',
                $this->escapeAttribute($marker['class']),
                $marker['cx'],
                $marker['cy'],
                $marker['r'],
            );
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
                self::NODE_RADIUS,
            );
            $svg[] = \sprintf(
                '<text class="label" x="%d" y="%d">%s</text>',
                $node['x'] + (int) floor($node['width'] / 2),
                $node['y'] + (int) floor($node['height'] / 2),
                $this->escapeText($node['label']),
            );
        }

        foreach ($offsetLayout['texts'] as $text) {
            $svg[] = \sprintf(
                '<text class="%s" x="%d" y="%d">%s</text>',
                $this->escapeAttribute($text['class']),
                $text['x'],
                $text['y'],
                $this->escapeText($text['text']),
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
        return htmlspecialchars($text, \ENT_XML1 | \ENT_SUBSTITUTE, 'UTF-8');
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

    private function groupLabel(GroupNode $node): string
    {
        if (\in_array($node->type, [GroupType::T_GROUP_CAPTURING, GroupType::T_GROUP_NAMED], true)) {
            $this->groupCounter++;
            $label = 'Group #'.$this->groupCounter;
            if (GroupType::T_GROUP_NAMED === $node->type && null !== $node->name) {
                $label .= ' ('.$node->name.')';
            }

            return $label;
        }

        $label = 'Group ('.$this->describeGroupType($node).')';
        if (GroupType::T_GROUP_INLINE_FLAGS === $node->type && null !== $node->flags && '' !== $node->flags) {
            $label .= ' flags: '.$node->flags;
        }

        return $label;
    }

    private function literalClass(): string
    {
        if ($this->negatedCharClassDepth > 0) {
            return 'node class-negated';
        }

        if ($this->charClassDepth > 0) {
            return 'node class-positive';
        }

        return 'node literal';
    }

    private function describeQuantifier(string $quantifier): string
    {
        if ('*' === $quantifier) {
            return '0 or more times';
        }

        if ('+' === $quantifier) {
            return '1 or more times';
        }

        if ('?' === $quantifier) {
            return '0 or 1 time';
        }

        $range = $this->parseRangeQuantifier($quantifier);
        if (null === $range) {
            return $quantifier;
        }

        [$min, $max] = $range;
        if (null === $max) {
            return $min.' or more times';
        }

        if (0 === $min) {
            return '0 to '.$max.' times';
        }

        if ($min === $max) {
            return $min.' times';
        }

        return $min.' to '.$max.' times';
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
