<?php

declare(strict_types=1);

$header = <<<'EOF'
This file is part of the RegexParser package.
 
(c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 
For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF;

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude([
        '.cache/',
        'tools/',
        'vendor/',
    ])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRules([
        '@PSR12'                                 => true,
        '@PSR12:risky'                           => true,
        '@Symfony'                               => true,
        '@Symfony:risky'                         => true,
        '@PHPUnit10x0Migration:risky'            => true,
        'header_comment'                         => ['header' => $header],
        'declare_strict_types'                   => true,
        'ordered_class_elements'                 => true,
        'ordered_interfaces'                     => true,
        'ordered_traits'                         => true,
        'php_unit_construct'                     => true,
        'php_unit_dedicate_assert'               => true,
        'php_unit_dedicate_assert_internal_type' => true,
        'php_unit_mock'                          => true,
        'php_unit_mock_short_will_return'        => true,
        'php_unit_strict'                        => true,
        'php_unit_test_case_static_method_calls' => false,
        'phpdoc_add_missing_param_annotation'    => true,
        'phpdoc_line_span'                       => true,
        'phpdoc_order'                           => true,
        'phpdoc_scalar'                          => true,
        'phpdoc_single_line_var_spacing'         => true,
        'phpdoc_summary'                         => false,
        'phpdoc_var_annotation_correct_order'    => true,
        'phpdoc_var_without_name'                => true,
        'protected_to_private'                   => true,
        'semicolon_after_instruction'            => true,
        'single_line_throw'                      => false,
        'single_trait_insert_per_statement'      => true,
        'strict_comparison'                      => true,
        'strict_param'                           => true,
        'ternary_to_null_coalescing'             => true,
        'trailing_comma_in_multiline'            => ['elements' => ['arguments']],
        'unary_operator_spaces'                  => true,
        'phpdoc_to_comment'                      => true,
        'array_indentation'                      => true,
        'blank_line_after_namespace'             => true,
        'blank_line_after_opening_tag'           => true,
        'blank_line_before_statement'            => true,
        'blank_line_between_import_groups'       => true,
        'blank_lines_before_namespace'           => true,
        'cast_spaces'                            => true,
        'class_definition'                       => [
            'multi_line_extends_each_single_line' => true,
            'single_item_single_line'             => true,
            'single_line'                         => true,
        ],
        'clean_namespace'                        => true,
        'compact_nullable_type_declaration'      => true,
        'concat_space'                           => ['spacing' => 'none'],
        'constant_case' => ['case' => 'lower'],
        'control_structure_braces' => true,
        'control_structure_continuation_position' => ['position' => 'same_line'],
        'declare_equal_normalize' => true,
        'declare_parentheses' => true,
        'elseif' => true,
        'encoding' => true,
        'full_opening_tag' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile('.cache/php-cs-fixer/cache.json');
