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

namespace RegexParser\Node;

/**
 * Registry of all AST Node types with metadata and PCRE feature mappings.
 *
 * This registry provides a central source of truth for:
 * - All available Node types in the library
 * - PCRE features each Node represents
 * - Node hierarchy and relationships
 * - Usage examples and documentation
 */
class NodeRegistry
{
    /**
     * Complete list of all AST Node classes with metadata.
     *
     * @return array<string, array{
     *     class: class-string,
     *     pcre_feature: string,
     *     description: string,
     *     examples: array<string>,
     *     parent: class-string|null,
     *     children: array<string>
     * }>
     */
    public static function getAllNodes(): array
    {
        return [
            'regex' => [
                'class' => RegexNode::class,
                'pcre_feature' => 'Root Pattern',
                'description' => 'Root node representing the entire regex pattern with flags',
                'examples' => ['/test/i', '/\d+/u', '/pattern/xms'],
                'parent' => null,
                'children' => ['pattern' => 'SequenceNode|AlternationNode|...'],
            ],

            'alternation' => [
                'class' => AlternationNode::class,
                'pcre_feature' => 'Alternation',
                'description' => 'Represents alternation (OR) between multiple alternatives',
                'examples' => ['foo|bar', 'test|testing', 'a|b|c'],
                'parent' => AbstractNode::class,
                'children' => ['alternatives' => 'array<NodeInterface>'],
            ],

            'sequence' => [
                'class' => SequenceNode::class,
                'pcre_feature' => 'Sequence/Concatenation',
                'description' => 'Represents a sequence of nodes that match in order',
                'examples' => ['abc', 'test123', '\d+\w+'],
                'parent' => AbstractNode::class,
                'children' => ['children' => 'array<NodeInterface>'],
            ],

            'group' => [
                'class' => GroupNode::class,
                'pcre_feature' => 'Groups (All Types)',
                'description' => 'Represents grouping constructs - capturing, non-capturing, atomic, lookarounds, etc.',
                'examples' => [
                    '(test)' => 'Capturing',
                    '(?:test)' => 'Non-capturing',
                    '(?>test)' => 'Atomic',
                    '(?=test)' => 'Lookahead',
                    '(?<=test)' => 'Lookbehind',
                    '(?<name>test)' => 'Named',
                    '(?|a|b)' => 'Branch reset',
                ],
                'parent' => AbstractNode::class,
                'children' => ['child' => 'NodeInterface', 'type' => 'GroupType'],
            ],

            'quantifier' => [
                'class' => QuantifierNode::class,
                'pcre_feature' => 'Quantifiers',
                'description' => 'Represents repetition quantifiers - greedy, lazy, and possessive',
                'examples' => [
                    '*' => 'Greedy 0+',
                    '+' => 'Greedy 1+',
                    '?' => 'Greedy 0-1',
                    '*?' => 'Lazy 0+',
                    '++' => 'Possessive 1+',
                    '{2,5}' => 'Range',
                    '{3}+' => 'Possessive exact',
                ],
                'parent' => AbstractNode::class,
                'children' => ['node' => 'NodeInterface', 'type' => 'QuantifierType'],
            ],

            'literal' => [
                'class' => LiteralNode::class,
                'pcre_feature' => 'Literal Characters',
                'description' => 'Represents literal characters that match themselves',
                'examples' => ['a', 'test', '123', '\*', '\.'],
                'parent' => AbstractNode::class,
                'children' => [],
            ],

            'char_class' => [
                'class' => CharClassNode::class,
                'pcre_feature' => 'Character Classes',
                'description' => 'Represents character classes (positive or negated)',
                'examples' => ['[abc]', '[^0-9]', '[a-zA-Z]', '[\d\w]'],
                'parent' => AbstractNode::class,
                'children' => ['ranges' => 'array<NodeInterface>'],
            ],

            'range' => [
                'class' => RangeNode::class,
                'pcre_feature' => 'Character Ranges',
                'description' => 'Represents a range within a character class',
                'examples' => ['a-z', '0-9', 'A-F'],
                'parent' => AbstractNode::class,
                'children' => [],
            ],

            'char_type' => [
                'class' => CharTypeNode::class,
                'pcre_feature' => 'Character Types/Escapes',
                'description' => 'Represents escaped character classes',
                'examples' => ['\d', '\D', '\w', '\W', '\s', '\S', '\h', '\H', '\v', '\V', '\R'],
                'parent' => AbstractNode::class,
                'children' => [],
            ],

            'dot' => [
                'class' => DotNode::class,
                'pcre_feature' => 'Dot (Wildcard)',
                'description' => 'Represents the dot metacharacter matching any character',
                'examples' => ['.'],
                'parent' => AbstractNode::class,
                'children' => [],
            ],

            'anchor' => [
                'class' => AnchorNode::class,
                'pcre_feature' => 'Anchors',
                'description' => 'Represents position anchors',
                'examples' => ['^', '$', '\A', '\Z', '\z'],
                'parent' => AbstractNode::class,
                'children' => [],
            ],

            'assertion' => [
                'class' => AssertionNode::class,
                'pcre_feature' => 'Assertions',
                'description' => 'Represents zero-width assertions',
                'examples' => ['\b', '\B', '\G'],
                'parent' => AbstractNode::class,
                'children' => [],
            ],

            'backref' => [
                'class' => BackrefNode::class,
                'pcre_feature' => 'Backreferences',
                'description' => 'Represents backreferences to captured groups',
                'examples' => ['\1', '\2', '\k<name>', '(?P=name)'],
                'parent' => AbstractNode::class,
                'children' => [],
            ],

            'subroutine' => [
                'class' => SubroutineNode::class,
                'pcre_feature' => 'Subroutines/Recursion',
                'description' => 'Represents subroutine calls and recursion',
                'examples' => ['(?R)', '(?1)', '(?&name)', '(?P>name)'],
                'parent' => AbstractNode::class,
                'children' => [],
            ],

            'conditional' => [
                'class' => ConditionalNode::class,
                'pcre_feature' => 'Conditional Patterns',
                'description' => 'Represents conditional matching based on conditions',
                'examples' => ['(?(1)yes|no)', '(?(?=test)a|b)', '(?(DEFINE)...)'],
                'parent' => AbstractNode::class,
                'children' => ['condition' => 'NodeInterface|string|int', 'yes' => 'NodeInterface', 'no' => 'NodeInterface|null'],
            ],

            'unicode' => [
                'class' => UnicodeNode::class,
                'pcre_feature' => 'Unicode Escapes',
                'description' => 'Represents Unicode character escapes',
                'examples' => ['\x{1234}', '\u{10FFFF}', '\x41'],
                'parent' => AbstractNode::class,
                'children' => [],
            ],

            'unicode_prop' => [
                'class' => UnicodePropNode::class,
                'pcre_feature' => 'Unicode Properties',
                'description' => 'Represents Unicode property escapes',
                'examples' => ['\p{L}', '\p{Nd}', '\P{Sc}', '\p{Greek}', '\p{Lu}'],
                'parent' => AbstractNode::class,
                'children' => [],
            ],

            'posix_class' => [
                'class' => PosixClassNode::class,
                'pcre_feature' => 'POSIX Character Classes',
                'description' => 'Represents POSIX character classes',
                'examples' => ['[:alpha:]', '[:digit:]', '[:alnum:]', '[:space:]'],
                'parent' => AbstractNode::class,
                'children' => [],
            ],

            'comment' => [
                'class' => CommentNode::class,
                'pcre_feature' => 'Comments',
                'description' => 'Represents inline comments in patterns',
                'examples' => ['(?#comment)', '(?#this is ignored)'],
                'parent' => AbstractNode::class,
                'children' => [],
            ],

            'pcre_verb' => [
                'class' => PcreVerbNode::class,
                'pcre_feature' => 'PCRE Verbs',
                'description' => 'Represents PCRE control verbs',
                'examples' => ['(*FAIL)', '(*ACCEPT)', '(*COMMIT)', '(*SKIP)', '(*PRUNE)', '(*THEN)', '(*MARK:label)', '(*UTF8)', '(*UCP)'],
                'parent' => AbstractNode::class,
                'children' => [],
            ],

            'keep' => [
                'class' => KeepNode::class,
                'pcre_feature' => 'Keep Assertion',
                'description' => 'Represents the \K keep assertion (reset match start)',
                'examples' => ['\K'],
                'parent' => AbstractNode::class,
                'children' => [],
            ],

            'octal' => [
                'class' => OctalNode::class,
                'pcre_feature' => 'Octal Escapes',
                'description' => 'Represents octal character escapes',
                'examples' => ['\o{377}', '\101'],
                'parent' => AbstractNode::class,
                'children' => [],
            ],

            'octal_legacy' => [
                'class' => OctalLegacyNode::class,
                'pcre_feature' => 'Legacy Octal Escapes',
                'description' => 'Represents legacy octal escapes',
                'examples' => ['\0', '\012', '\177'],
                'parent' => AbstractNode::class,
                'children' => [],
            ],
        ];
    }

    /**
     * Get all Node classes grouped by PCRE feature category.
     *
     * @return array<string, array<string>>
     */
    public static function getNodesByFeature(): array
    {
        return [
            'Basic Matching' => [
                LiteralNode::class,
                DotNode::class,
                CharTypeNode::class,
            ],
            'Character Classes' => [
                CharClassNode::class,
                RangeNode::class,
                PosixClassNode::class,
            ],
            'Unicode Support' => [
                UnicodeNode::class,
                UnicodePropNode::class,
            ],
            'Quantifiers' => [
                QuantifierNode::class,
            ],
            'Grouping' => [
                GroupNode::class,
            ],
            'Structure' => [
                SequenceNode::class,
                AlternationNode::class,
            ],
            'Anchors & Assertions' => [
                AnchorNode::class,
                AssertionNode::class,
                KeepNode::class,
            ],
            'References' => [
                BackrefNode::class,
                SubroutineNode::class,
                ConditionalNode::class,
            ],
            'Advanced Features' => [
                CommentNode::class,
                PcreVerbNode::class,
            ],
            'Numeric Escapes' => [
                OctalNode::class,
                OctalLegacyNode::class,
            ],
            'Root' => [
                RegexNode::class,
            ],
        ];
    }

    /**
     * Get total count of Node types.
     */
    public static function getNodeCount(): int
    {
        return \count(self::getAllNodes());
    }

    /**
     * Get Node metadata by class name.
     *
     * @param class-string $className
     *
     * @return array{class: class-string, pcre_feature: string, description: string, examples: array<int|string, string>, parent: class-string|null, children: array<int|string, string>}|null
     */
    public static function getNodeMetadata(string $className): ?array
    {
        foreach (self::getAllNodes() as $metadata) {
            if ($metadata['class'] === $className) {
                return $metadata;
            }
        }

        return null;
    }

    /**
     * Get all PCRE features covered by the AST.
     *
     * @return array<string>
     */
    public static function getCoveredFeatures(): array
    {
        $features = [];
        foreach (self::getAllNodes() as $metadata) {
            $features[] = $metadata['pcre_feature'];
        }

        return array_unique($features);
    }
}
