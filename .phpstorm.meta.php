<?php

namespace PHPSTORM_META;

registerArgumentsSet(
    'regex_option_keys',
    'max_pattern_length',
    'max_lookbehind_length',
    'cache',
    'redos_ignored_patterns',
    'runtime_pcre_validation',
    'max_recursion_depth',
    'php_version'
);

registerArgumentsSet(
    'optimize_option_keys',
    'digits',
    'word',
    'ranges',
    'autoPossessify',
    'allowAlternationFactorization',
    'minQuantifierCount'
);

registerArgumentsSet(
    'regex_flags',
    'i',
    'm',
    's',
    'x',
    'u',
    'A',
    'D',
    'S',
    'U',
    'X',
    'J',
    'r'
);

registerArgumentsSet(
    'regex_delimiters',
    '/',
    '#',
    '~',
    '%',
    '@',
    '!',
    '`'
);

registerArgumentsSet(
    'explanation_formats',
    'text',
    'html'
);

registerArgumentsSet(
    'highlight_formats',
    'console',
    'html'
);

registerArgumentsSet(
    'redos_thresholds',
    null,
    \RegexParser\ReDoS\ReDoSSeverity::SAFE,
    \RegexParser\ReDoS\ReDoSSeverity::LOW,
    \RegexParser\ReDoS\ReDoSSeverity::MEDIUM,
    \RegexParser\ReDoS\ReDoSSeverity::HIGH,
    \RegexParser\ReDoS\ReDoSSeverity::CRITICAL,
    \RegexParser\ReDoS\ReDoSSeverity::UNKNOWN
);

expectedArguments(
    \RegexParser\Regex::create(),
    0,
    argumentsSet('regex_option_keys')
);

expectedArguments(
    \RegexParser\Regex::new(),
    0,
    argumentsSet('regex_option_keys')
);

expectedArguments(
    \RegexParser\RegexOptions::fromArray(),
    0,
    argumentsSet('regex_option_keys')
);

expectedArguments(
    \RegexParser\Regex::optimize(),
    1,
    argumentsSet('optimize_option_keys')
);

expectedArguments(
    \RegexParser\Regex::explain(),
    1,
    argumentsSet('explanation_formats')
);

expectedArguments(
    \RegexParser\Regex::highlight(),
    1,
    argumentsSet('highlight_formats')
);

expectedArguments(
    \RegexParser\Regex::redos(),
    1,
    argumentsSet('redos_thresholds')
);

expectedArguments(
    \RegexParser\Regex::parsePattern(),
    1,
    argumentsSet('regex_flags')
);

expectedArguments(
    \RegexParser\Regex::parsePattern(),
    2,
    argumentsSet('regex_delimiters')
);

override(
    \RegexParser\Regex::parse(1),
    map([
        true => \RegexParser\TolerantParseResult::class,
        false => \RegexParser\Node\RegexNode::class,
    ])
);
