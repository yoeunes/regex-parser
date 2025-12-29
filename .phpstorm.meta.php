<?php

namespace PHPSTORM_META;

registerArgumentsSet(
    'regex_config_keys',
    'max_pattern_length',
    'max_lookbehind_length',
    'cache',
    'redos_ignored_patterns',
    'runtime_pcre_validation',
    'max_recursion_depth',
    'php_version'
);

registerArgumentsSet(
    'optimization_flags',
    'digits',
    'word',
    'ranges',
    'possessive',
    'factorize'
);

registerArgumentsSet(
    'explanation_formats',
    'text',
    'html'
);

registerArgumentsSet(
    'highlight_formats',
    'console',
    'html',
    'ansi'
);

registerArgumentsSet(
    'redos_severities',
    'LOW',
    'MEDIUM',
    'HIGH',
    'CRITICAL'
);

// Static methods
expectedArguments(
    \RegexParser\Regex::create(),
    0,
    argumentsSet('regex_config_keys')
);

expectedArguments(
    \RegexParser\Regex::optimize(),
    1,
    argumentsSet('optimization_flags')
);

// Instance methods
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
    argumentsSet('redos_severities')
);
