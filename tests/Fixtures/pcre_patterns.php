<?php

return array (
  0 => '@@',
  1 => '<>',
  2 => '@\\@\\@@',
  3 => '//z',
  4 => '/(((?(?C)0?=))(?!()0|.(?0)0)())/',
  5 => '/./',
  6 => '/((?:(?:unsigned|struct)\\s+)?\\w+)(?:\\s*(\\*+)\\s+|\\s+(\\**))(\\w+(?:\\[\\s*\\w*\\s*\\])?)\\s*(?:(=)[^,;]+)?((?:\\s*,\\s*\\**\\s*\\w+(?:\\[\\s*\\w*\\s*\\])?\\s*(?:=[^,;]+)?)*)\\s*;/S',
  7 => '/(?:\\([^)]+\\))?(&?)([\\w>.()-]+(?:\\[\\w+\\])?)\\s*,?((?:\\)*\\s*=)?)/S',
  8 => '/zend_parse_parameters(?:_ex\\s*\\([^,]+,[^,]+|\\s*\\([^,]+),\\s*"([^"]*)"\\s*,\\s*([^{;]*)/S',
  9 => '/PHP_(?:NAMED_)?(?:FUNCTION|METHOD)\\s*\\((\\w+(?:,\\s*\\w+)?)\\)/S',
  10 => '{(?(DEFINE)
       (?<number>   -? (?= [1-9]|0(?!\\d) ) \\d+ (\\.\\d+)? ([eE] [+-]? \\d+)? )
       (?<boolean>   true | false | null )
       (?<string>    " ([^"\\\\\\\\]* | \\\\\\\\ ["\\\\\\\\bfnrt\\/] | \\\\\\\\ u [0-9a-f]{4} )* " )
       (?<array>     \\[  (?:  (?&json) \\s* (?: , (?&json) \\s* )*  )?  \\s* \\] )
       (?<pair>      \\s* (?&string) \\s* : (?&json) \\s* )
       (?<object>    \\{  (?:  (?&pair)  (?: , (?&pair)  )*  )?  \\s* \\} )
       (?<json>   \\s* (?: (?&number) | (?&boolean) | (?&string) | (?&array) | (?&object) ) )
    )
^(?P<start>\\s*\\{\\s*(?:(?&string)\\s*:\\s*(?&json)\\s*,\\s*)*?)
(?P<property>',
  11 => '{
    "config": {
        "cache-files-ttl": 0,
        "discard-changes": true
    },
    "minimum-stability": "stable",
    "prefer-stable": false,
    "provide": {
        "heroku-sys/cedar": "14.2016.03.22"
    },
    "repositories": [
        {
            "packagist.org": false
        },
        {
            "type": "package",
            "package": [
                {
                    "type": "metapackage",
                    "name": "anthonymartin/geo-location",
                    "version": "v1.0.0",
                    "require": {
                        "heroku-sys/php": ">=5.3.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "aws/aws-sdk-php",
                    "version": "3.9.4",
                    "require": {
                        "heroku-sys/php": ">=5.5"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "cloudinary/cloudinary_php",
                    "version": "dev-master",
                    "require": {
                        "heroku-sys/ext-curl": "*",
                        "heroku-sys/ext-json": "*",
                        "heroku-sys/php": ">=5.3.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "doctrine/annotations",
                    "version": "v1.2.7",
                    "require": {
                        "heroku-sys/php": ">=5.3.2"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "doctrine/cache",
                    "version": "v1.6.0",
                    "require": {
                        "heroku-sys/php": "~5.5|~7.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "doctrine/collections",
                    "version": "v1.3.0",
                    "require": {
                        "heroku-sys/php": ">=5.3.2"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "doctrine/common",
                    "version": "v2.6.1",
                    "require": {
                        "heroku-sys/php": "~5.5|~7.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "doctrine/inflector",
                    "version": "v1.1.0",
                    "require": {
                        "heroku-sys/php": ">=5.3.2"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "doctrine/lexer",
                    "version": "v1.0.1",
                    "require": {
                        "heroku-sys/php": ">=5.3.2"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "geoip/geoip",
                    "version": "v1.16",
                    "require": [],
                    "replace": [],
                    "provide": [],
                    "conflict": {
                        "heroku-sys/ext-geoip": "*"
                    }
                },
                {
                    "type": "metapackage",
                    "name": "giggsey/libphonenumber-for-php",
                    "version": "7.2.5",
                    "require": {
                        "heroku-sys/ext-mbstring": "*"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "guzzlehttp/guzzle",
                    "version": "5.3.0",
                    "require": {
                        "heroku-sys/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "guzzlehttp/promises",
                    "version": "1.0.3",
                    "require": {
                        "heroku-sys/php": ">=5.5.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "guzzlehttp/psr7",
                    "version": "1.2.3",
                    "require": {
                        "heroku-sys/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "guzzlehttp/ringphp",
                    "version": "1.1.0",
                    "require": {
                        "heroku-sys/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "guzzlehttp/streams",
                    "version": "3.0.0",
                    "require": {
                        "heroku-sys/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "hipchat/hipchat-php",
                    "version": "v1.4",
                    "require": {
                        "heroku-sys/php": ">=5.3.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "kriswallsmith/buzz",
                    "version": "v0.15",
                    "require": {
                        "heroku-sys/php": ">=5.3.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "league/csv",
                    "version": "8.0.0",
                    "require": {
                        "heroku-sys/ext-mbstring": "*",
                        "heroku-sys/php": ">=5.5.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "league/fractal",
                    "version": "0.13.0",
                    "require": {
                        "heroku-sys/php": ">=5.4"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "mashape/unirest-php",
                    "version": "1.2.1",
                    "require": {
                        "heroku-sys/ext-curl": "*",
                        "heroku-sys/ext-json": "*",
                        "heroku-sys/php": ">=5.3.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "mtdowling/jmespath.php",
                    "version": "2.3.0",
                    "require": {
                        "heroku-sys/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "palex/phpstructureddata",
                    "version": "v2.0.1",
                    "require": {
                        "heroku-sys/php": ">=5.3.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "psr/http-message",
                    "version": "1.0",
                    "require": {
                        "heroku-sys/php": ">=5.3.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "react/promise",
                    "version": "v2.2.1",
                    "require": {
                        "heroku-sys/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "rollbar/rollbar",
                    "version": "v0.15.0",
                    "require": {
                        "heroku-sys/ext-curl": "*"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "ronanguilloux/isocodes",
                    "version": "1.2.0",
                    "require": {
                        "heroku-sys/ext-bcmath": "*",
                        "heroku-sys/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "sendgrid/sendgrid",
                    "version": "2.1.1",
                    "require": {
                        "heroku-sys/php": ">=5.3"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "sendgrid/smtpapi",
                    "version": "0.0.1",
                    "require": {
                        "heroku-sys/php": ">=5.3"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony/css-selector",
                    "version": "v2.8.2",
                    "require": {
                        "heroku-sys/php": ">=5.3.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony/http-foundation",
                    "version": "v2.8.2",
                    "require": {
                        "heroku-sys/php": ">=5.3.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony/polyfill-php54",
                    "version": "v1.1.0",
                    "require": {
                        "heroku-sys/php": ">=5.3.3"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony/polyfill-php55",
                    "version": "v1.1.0",
                    "require": {
                        "heroku-sys/php": ">=5.3.3"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "thepixeldeveloper/sitemap",
                    "version": "3.0.0",
                    "require": {
                        "heroku-sys/php": ">=5.3.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "tijsverkoyen/css-to-inline-styles",
                    "version": "1.5.5",
                    "require": {
                        "heroku-sys/php": ">=5.3.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "yiisoft/yii",
                    "version": "1.1.17",
                    "require": {
                        "heroku-sys/php": ">=5.1.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "composer.json/composer.lock",
                    "version": "dev-597511d6d51b96e4a8afeba2c79982e5",
                    "require": {
                        "heroku-sys/php": "~5.6.0",
                        "heroku-sys/ext-newrelic": "*",
                        "heroku-sys/ext-gd": "*",
                        "heroku-sys/ext-redis": "*"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                }
            ]
        }
    ],
    "require": {
        "composer.json/composer.lock": "dev-597511d6d51b96e4a8afeba2c79982e5",
        "anthonymartin/geo-location": "v1.0.0",
        "aws/aws-sdk-php": "3.9.4",
        "cloudinary/cloudinary_php": "dev-master",
        "doctrine/annotations": "v1.2.7",
        "doctrine/cache": "v1.6.0",
        "doctrine/collections": "v1.3.0",
        "doctrine/common": "v2.6.1",
        "doctrine/inflector": "v1.1.0",
        "doctrine/lexer": "v1.0.1",
        "geoip/geoip": "v1.16",
        "giggsey/libphonenumber-for-php": "7.2.5",
        "guzzlehttp/guzzle": "5.3.0",
        "guzzlehttp/promises": "1.0.3",
        "guzzlehttp/psr7": "1.2.3",
        "guzzlehttp/ringphp": "1.1.0",
        "guzzlehttp/streams": "3.0.0",
        "hipchat/hipchat-php": "v1.4",
        "kriswallsmith/buzz": "v0.15",
        "league/csv": "8.0.0",
        "league/fractal": "0.13.0",
        "mashape/unirest-php": "1.2.1",
        "mtdowling/jmespath.php": "2.3.0",
        "palex/phpstructureddata": "v2.0.1",
        "psr/http-message": "1.0",
        "react/promise": "v2.2.1",
        "rollbar/rollbar": "v0.15.0",
        "ronanguilloux/isocodes": "1.2.0",
        "sendgrid/sendgrid": "2.1.1",
        "sendgrid/smtpapi": "0.0.1",
        "symfony/css-selector": "v2.8.2",
        "symfony/http-foundation": "v2.8.2",
        "symfony/polyfill-php54": "v1.1.0",
        "symfony/polyfill-php55": "v1.1.0",
        "thepixeldeveloper/sitemap": "3.0.0",
        "tijsverkoyen/css-to-inline-styles": "1.5.5",
        "yiisoft/yii": "1.1.17",
        "heroku-sys/apache": "^2.4.10",
        "heroku-sys/nginx": "~1.8.0"
    }
}',
  12 => '#(&\\#x*)([0-9A-F]+);*#iu',
  13 => '/(?:\\D+|<\\d+>)*[!?]/',
  14 => '/\\PN+/',
  15 => '/\\P{N}+/A',
  16 => '/^\\P{N}+/',
  17 => '/^\\P{N}+/A',
  18 => '/[0-35-9]/',
  19 => '/[tT]his is a(.*?)\\./',
  20 => '@\\. \\\\\\(.*).@',
  21 => '/\\d{2}$/',
  22 => '/(This is a ){2}(.*)\\stest/',
  23 => '/test/',
  24 => '/world/',
  25 => '/[0-9]/',
  26 => '/(\\d)+/',
  27 => '~[^\\p{Han}\\p{Z}]~u',
  28 => '/<(\\w+)[\\s\\w\\-]+ id="S44_i89ew">/',
  29 => '`a+`',
  30 => '/[\\-\\+]?[0-9\\.]*/',
  31 => '/+/',
  32 => '@^[a-z]+@',
  33 => '#\\d#u',
  34 => '/pattern/',
  35 => '/(4)?(2)?\\d/',
  36 => '/\\p{Ll}(\\p{L}((\\p{Ll}\\p{Ll})))/',
  37 => '/\\p{Ll}\\p{L}\\p{Ll}\\p{Ll}/',
  38 => '/[a\\-c]+/',
  39 => '/a\\-{2,}/',
  40 => '/a\\-{1,}/',
  41 => '/\\b/',
  42 => '(#11/19/2002#)',
  43 => '/(\\d*)/',
  44 => '#\\[indent]((?:[^[]|\\[(?!/?indent])|(?R))+)\\[/indent]#',
  45 => '<div style="margin-left: 10px">\'.$input[1].\'</div>',
  46 => '~(?: |\\G)\\d\\B\\K~',
  47 => '<- This is a string$>',
  48 => '<[0-35-9]>',
  49 => '<\\b[hH]\\w{2,4}>',
  50 => '<(\\w)\\s*-\\s*(\\w)>',
  51 => '<(^[a-z]\\w+)@(\\w+)\\.(\\w+)\\.([a-z]{2,}$)>',
  52 => '/*/',
  53 => '/[\\s, ]+/',
  54 => '/\\d*/',
  55 => '{^(\\\\s*\\\\{\\\\s*(?:"(?:[^\\\\0-\\\\x09\\\\x0a-\\\\x1f\\\\\\\\"]+|\\\\\\\\["bfnrt/\\\\\\\\]|\\\\\\\\u[a-fA-F0-9]{4})*"\\\\s*:\\\\s*(?:[0-9.]+|null|true|false|"(?:[^\\\\0-\\\\x09\\\\x0a-\\\\x1f\\\\\\\\"]+|\\\\\\\\["bfnrt/\\\\\\\\]|\\\\\\\\u[a-fA-F0-9]{4})*"|\\\\[(?:[^\\\\]]*|\\\\[(?:[^\\\\]]*|\\\\[(?:[^\\\\]]*|\\\\[(?:[^\\\\]]*|\\\\[[^\\\\]]*\\\\])*\\\\])*\\\\])*\\\\]|(?:[^{}]*|\\\\{(?:[^{}]*|\\\\{(?:[^{}]*|\\\\{(?:[^{}]*|\\\\{[^{}]*\\\\})*\\\\})*\\\\})*\\\\})*)*\\\\]|\\\\{(?:[^{}]*|\\\\{(?:[^{}]*|\\\\{(?:[^{}]*|\\\\{(?:[^{}]*|\\\\{[^{}]*\\\\})*\\\\})*\\\\})*\\\\})*\\\\})\\\\s*,\\\\s*)*?)("require"\\\\s*:\\\\s*)((?:[0-9.]+|null|true|false|"(?:[^\\\\0-\\\\x09\\\\x0a-\\\\x1f\\\\\\\\"]+|\\\\\\\\["bfnrt/\\\\\\\\]|\\\\\\\\u[a-fA-F0-9]{4})*"|\\\\[(?:[^\\\\]]*|\\\\[(?:[^\\\\]]*|\\\\[(?:[^\\\\]]*|\\\\[(?:[^\\\\]]*|\\\\[[^\\\\]]*\\\\])*\\\\])*\\\\])*\\\\]|(?:[^{}]*|\\\\{(?:[^{}]*|\\\\{(?:[^{}]*|\\\\{(?:[^{}]*|\\\\{[^{}]*\\\\})*\\\\})*\\\\})*\\\\})*)*\\\\]|\\\\{(?:[^{}]*|\\\\{(?:[^{}]*|\\\\{(?:[^{}]*|\\\\{(?:[^{}]*|\\\\{[^{}]*\\\\})*\\\\})*\\\\})*\\\\})*\\\\}))(.*)}s',
  56 => '{
    "config": {
        "cache-files-ttl": 0,
        "discard-changes": true
    },
    "minimum-stability": "stable",
    "prefer-stable": false,
    "provide": {
        "heroku-sys\\\\/cedar": "14.2016.03.12"
    },
    "repositories": [
        {
            "packagist": false
        },
        {
            "type": "path",
            "url": "\\\\/tmp\\\\/buildpacktUY7k\\\\/support\\\\/installer\\\\/",
            "options": {
                "symlink": false
            }
        },
        {
            "type": "composer",
            "url": "https:\\\\/\\\\/lang-php.s3.amazonaws.com\\\\/dist-cedar-14-stable\\\\/"
        },
        {
            "type": "package",
            "package": [
                {
                    "type": "metapackage",
                    "name": "algolia\\\\/algoliasearch-client-php",
                    "version": "1.8.1",
                    "require": {
                        "heroku-sys\\\\/ext-mbstring": "*",
                        "heroku-sys\\\\/php": ">=5.4"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "algolia\\\\/algoliasearch-laravel",
                    "version": "1.0.10",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.5.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "asm89\\\\/stack-cors",
                    "version": "0.2.1",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.2"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "aws\\\\/aws-sdk-php",
                    "version": "3.15.7",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.5"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "aws\\\\/aws-sdk-php-laravel",
                    "version": "3.1.0",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.5.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "barryvdh\\\\/laravel-cors",
                    "version": "v0.7.3",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "bookingsync\\\\/oauth2-bookingsync-php",
                    "version": "0.1.3",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "classpreloader\\\\/classpreloader",
                    "version": "3.0.0",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.5.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "danielstjules\\\\/stringy",
                    "version": "1.10.0",
                    "require": {
                        "heroku-sys\\\\/ext-mbstring": "*",
                        "heroku-sys\\\\/php": ">=5.3.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "dnoegel\\\\/php-xdg-base-dir",
                    "version": "0.1",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.2"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "doctrine\\\\/annotations",
                    "version": "v1.2.7",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.2"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "doctrine\\\\/cache",
                    "version": "v1.6.0",
                    "require": {
                        "heroku-sys\\\\/php": "~5.5|~7.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "doctrine\\\\/collections",
                    "version": "v1.3.0",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.2"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "doctrine\\\\/common",
                    "version": "v2.6.1",
                    "require": {
                        "heroku-sys\\\\/php": "~5.5|~7.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "doctrine\\\\/dbal",
                    "version": "v2.5.4",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.2"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "doctrine\\\\/inflector",
                    "version": "v1.1.0",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.2"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "doctrine\\\\/lexer",
                    "version": "v1.0.1",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.2"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "fabpot\\\\/goutte",
                    "version": "v3.1.2",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.5.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "graham-campbell\\\\/manager",
                    "version": "v2.3.1",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.5.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "guzzle\\\\/guzzle",
                    "version": "v3.9.3",
                    "require": {
                        "heroku-sys\\\\/ext-curl": "*",
                        "heroku-sys\\\\/php": ">=5.3.3"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "guzzlehttp\\\\/guzzle",
                    "version": "6.1.1",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.5.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "guzzlehttp\\\\/promises",
                    "version": "1.1.0",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.5.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "guzzlehttp\\\\/psr7",
                    "version": "1.2.3",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "intercom\\\\/intercom-php",
                    "version": "v1.4.0",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "intervention\\\\/image",
                    "version": "2.3.6",
                    "require": {
                        "heroku-sys\\\\/ext-fileinfo": "*",
                        "heroku-sys\\\\/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "jakub-onderka\\\\/php-console-color",
                    "version": "0.1",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.2"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "jakub-onderka\\\\/php-console-highlighter",
                    "version": "v0.3.2",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "jeremeamia\\\\/SuperClosure",
                    "version": "2.2.0",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.4"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "jlapp\\\\/swaggervel",
                    "version": "dev-master",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "laravel\\\\/framework",
                    "version": "v5.1.31",
                    "require": {
                        "heroku-sys\\\\/ext-mbstring": "*",
                        "heroku-sys\\\\/ext-openssl": "*",
                        "heroku-sys\\\\/php": ">=5.5.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "laravelcollective\\\\/html",
                    "version": "v5.1.9",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.5.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "league\\\\/flysystem",
                    "version": "1.0.18",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "league\\\\/flysystem-aws-s3-v3",
                    "version": "1.0.9",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.5.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "league\\\\/fractal",
                    "version": "0.13.0",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.4"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "league\\\\/glide",
                    "version": "1.0.0",
                    "require": {
                        "heroku-sys\\\\/php": "^5.4 | ^7.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "league\\\\/oauth2-client",
                    "version": "0.12.1",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "mindscape\\\\/raygun4php",
                    "version": "dev-master",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "monolog\\\\/monolog",
                    "version": "1.18.0",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "mtdowling\\\\/cron-expression",
                    "version": "v1.1.0",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.2"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "mtdowling\\\\/jmespath.php",
                    "version": "2.3.0",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "namshi\\\\/jose",
                    "version": "5.0.2",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.3"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "nesbot\\\\/carbon",
                    "version": "1.21.0",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "nikic\\\\/php-parser",
                    "version": "v2.0.1",
                    "require": {
                        "heroku-sys\\\\/ext-tokenizer": "*",
                        "heroku-sys\\\\/php": ">=5.4"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "orangehill\\\\/iseed",
                    "version": "dev-master",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "paragonie\\\\/random_compat",
                    "version": "v1.2.1",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.2.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "phpseclib\\\\/phpseclib",
                    "version": "0.3.10",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.0.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "predis\\\\/predis",
                    "version": "v1.0.3",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.2"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "psr\\\\/http-message",
                    "version": "1.0",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "psy\\\\/psysh",
                    "version": "v0.7.1",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "swiftmailer\\\\/swiftmailer",
                    "version": "v5.4.1",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.3"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony\\\\/browser-kit",
                    "version": "v2.8.3",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony\\\\/console",
                    "version": "v2.7.10",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony\\\\/css-selector",
                    "version": "v2.7.10",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony\\\\/debug",
                    "version": "v2.7.10",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony\\\\/dom-crawler",
                    "version": "v2.7.10",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony\\\\/event-dispatcher",
                    "version": "v2.8.3",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony\\\\/finder",
                    "version": "v2.7.10",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony\\\\/http-foundation",
                    "version": "v2.7.10",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony\\\\/http-kernel",
                    "version": "v2.7.10",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony\\\\/polyfill-php56",
                    "version": "v1.1.0",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.3"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony\\\\/polyfill-util",
                    "version": "v1.1.0",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.3"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony\\\\/process",
                    "version": "v2.7.10",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony\\\\/routing",
                    "version": "v2.7.10",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony\\\\/translation",
                    "version": "v2.7.10",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "symfony\\\\/var-dumper",
                    "version": "v2.7.10",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.9"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "twilio\\\\/sdk",
                    "version": "4.10.0",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.2.1"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "tymon\\\\/jwt-auth",
                    "version": "0.5.9",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "vinkla\\\\/algolia",
                    "version": "2.2.1",
                    "require": {
                        "heroku-sys\\\\/php": "^5.5.9 || ^7.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "vlucas\\\\/phpdotenv",
                    "version": "v1.1.1",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.3.2"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "zircote\\\\/swagger-php",
                    "version": "2.0.6",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.4.0"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                },
                {
                    "type": "metapackage",
                    "name": "composer.json\\\\/composer.lock",
                    "version": "dev-a923f6cdbbc9439cabb74aa9003f6d51",
                    "require": {
                        "heroku-sys\\\\/php": ">=5.5.9",
                        "heroku-sys\\\\/ext-gd": "*",
                        "heroku-sys\\\\/ext-exif": "*",
                        "heroku-sys\\\\/ext-fileinfo": "*"
                    },
                    "replace": [],
                    "provide": [],
                    "conflict": []
                }
            ]
        }
    ],
    "require": {
        "composer.json\\\\/composer.lock": "dev-a923f6cdbbc9439cabb74aa9003f6d51",
        "algolia\\\\/algoliasearch-client-php": "1.8.1",
        "algolia\\\\/algoliasearch-laravel": "1.0.10",
        "asm89\\\\/stack-cors": "0.2.1",
        "aws\\\\/aws-sdk-php": "3.15.7",
        "aws\\\\/aws-sdk-php-laravel": "3.1.0",
        "barryvdh\\\\/laravel-cors": "v0.7.3",
        "bookingsync\\\\/oauth2-bookingsync-php": "0.1.3",
        "classpreloader\\\\/classpreloader": "3.0.0",
        "danielstjules\\\\/stringy": "1.10.0",
        "dnoegel\\\\/php-xdg-base-dir": "0.1",
        "doctrine\\\\/annotations": "v1.2.7",
        "doctrine\\\\/cache": "v1.6.0",
        "doctrine\\\\/collections": "v1.3.0",
        "doctrine\\\\/common": "v2.6.1",
        "doctrine\\\\/dbal": "v2.5.4",
        "doctrine\\\\/inflector": "v1.1.0",
        "doctrine\\\\/lexer": "v1.0.1",
        "fabpot\\\\/goutte": "v3.1.2",
        "graham-campbell\\\\/manager": "v2.3.1",
        "guzzle\\\\/guzzle": "v3.9.3",
        "guzzlehttp\\\\/guzzle": "6.1.1",
        "guzzlehttp\\\\/promises": "1.1.0",
        "guzzlehttp\\\\/psr7": "1.2.3",
        "intercom\\\\/intercom-php": "v1.4.0",
        "intervention\\\\/image": "2.3.6",
        "jakub-onderka\\\\/php-console-color": "0.1",
        "jakub-onderka\\\\/php-console-highlighter": "v0.3.2",
        "jeremeamia\\\\/SuperClosure": "2.2.0",
        "jlapp\\\\/swaggervel": "dev-master",
        "laravel\\\\/framework": "v5.1.31",
        "laravelcollective\\\\/html": "v5.1.9",
        "league\\\\/flysystem": "1.0.18",
        "league\\\\/flysystem-aws-s3-v3": "1.0.9",
        "league\\\\/fractal": "0.13.0",
        "league\\\\/glide": "1.0.0",
        "league\\\\/oauth2-client": "0.12.1",
        "mindscape\\\\/raygun4php": "dev-master",
        "monolog\\\\/monolog": "1.18.0",
        "mtdowling\\\\/cron-expression": "v1.1.0",
        "mtdowling\\\\/jmespath.php": "2.3.0",
        "namshi\\\\/jose": "5.0.2",
        "nesbot\\\\/carbon": "1.21.0",
        "nikic\\\\/php-parser": "v2.0.1",
        "orangehill\\\\/iseed": "dev-master",
        "paragonie\\\\/random_compat": "v1.2.1",
        "phpseclib\\\\/phpseclib": "0.3.10",
        "predis\\\\/predis": "v1.0.3",
        "psr\\\\/http-message": "1.0",
        "psy\\\\/psysh": "v0.7.1",
        "swiftmailer\\\\/swiftmailer": "v5.4.1",
        "symfony\\\\/browser-kit": "v2.8.3",
        "symfony\\\\/console": "v2.7.10",
        "symfony\\\\/css-selector": "v2.7.10",
        "symfony\\\\/debug": "v2.7.10",
        "symfony\\\\/dom-crawler": "v2.7.10",
        "symfony\\\\/event-dispatcher": "v2.8.3",
        "symfony\\\\/finder": "v2.7.10",
        "symfony\\\\/http-foundation": "v2.7.10",
        "symfony\\\\/http-kernel": "v2.7.10",
        "symfony\\\\/polyfill-php56": "v1.1.0",
        "symfony\\\\/polyfill-util": "v1.1.0",
        "symfony\\\\/process": "v2.7.10",
        "symfony\\\\/routing": "v2.7.10",
        "symfony\\\\/translation": "v2.7.10",
        "symfony\\\\/var-dumper": "v2.7.10",
        "twilio\\\\/sdk": "4.10.0",
        "tymon\\\\/jwt-auth": "0.5.9",
        "vinkla\\\\/algolia": "2.2.1",
        "vlucas\\\\/phpdotenv": "v1.1.1",
        "zircote\\\\/swagger-php": "2.0.6",
        "heroku-sys\\\\/apache": "^2.4.10",
        "heroku-sys\\\\/nginx": "~1.8.0"
    }
}',
  57 => '/(ab)(cd)(e)/',
  58 => '/abc/',
  59 => '/(?<!\\w)(0x[\\p{N}]+[lL]?|[\\p{Nd}]+(e[\\p{Nd}]*)?[lLdDfF]?)(?!\\w)/',
  60 => '@^ <br />@S',
  61 => '<br><br>',
  62 => '/<.*>/',
  63 => '/<.*>/U',
  64 => '/(?U)<.*>/',
  65 => '/[:,;\\(\\)]/',
  66 => '/:\\s*(\\w*,*\\s*)+;/',
  67 => '/(\\(|\\))/',
  68 => '/NAME/i',
  69 => '/\\w/',
  70 => '/^|\\d{1,2}$/',
  71 => '@\\b\\w{1,2}\\b@',
  72 => '~\\A.~',
  73 => '/a/u',
  74 => '~.{3}\\K~',
  75 => '~.*~u',
  76 => '/a/',
  77 => '//u',
  78 => '/a e i o u/',
  79 => '/a e i o u/x',
  80 => '/a e\\ni\\to\\ru/x',
  81 => '//',
  82 => '/(.)/',
  83 => '/.++\\d*+[/',
  84 => '/(.)/e',
  85 => '/broken/',
  86 => '/\\w/u',
  87 => '/^(foo)+$/',
  88 => '/((?1)?z)/',
  89 => '/\\y/',
  90 => '/\\y/X',
  91 => '/^.{2,3}$/',
  92 => '/^.{2,3}$/m',
  93 => '/(*NO_JIT)^(A{1,2}B)+$$/',
  94 => '~(*NO_JIT)(a)*~',
  95 => '/\\s([\\w_\\.\\/]+)(?:=([\\\'"]?(?:[\\w\\d\\s\\?=\\(\\)\\.,\'_#\\/\\\\:;&-]|(?:\\\\\\\\"|\\\\\\\')?)+[\\\'"]?))?/',
  96 => '<simpletag an_attribute="simpleValueInside">',
  97 => '!{$str_quoted}!',
  98 => '/\\S\\S/u',
  99 => '/\\S{2}/u',
  100 => '/\\W\\W/u',
  101 => '/\\W{2}/u',
  102 => '/^\\S+.+$/',
  103 => '/^\\S+.+$/D',
  104 => '/^\\S+\\s$/D',
  105 => '/\\d/',
  106 => '/^[\\x{0100}-\\x{017f}]{1,63}$/iu',
  107 => '/(*NO_JIT)^[\\x{0100}-\\x{017f}]{1,63}$/iu',
  108 => '/(insert|drop|create|select|delete|update)([^;\']*(\'."(\'[^\']*\')+".\')?)*(;|$)/i',
  109 => '/.*\\p{N}/',
  110 => '/\\p{Nd}/',
  111 => '/(a)|(b)/',
  112 => '/(?<!ක)/u',
  113 => '/(?<!k)/u',
  114 => '/[a-zA-Z]/',
  115 => '/(([0-9a-z]+)-([0-9]+))-(([0-9]+)-([0-9]+))/',
  116 => '/([a-z]+)/',
  117 => '~((V(I|1)(4|A)GR(4|A))|(V(I|1)C(0|O)D(I|1)(N|\\/\\\\\\/)))~i',
  118 => '/([a-z]+_[a-z]+_*[a-z]+)_?(\\d+)?/',
  119 => '@^(/([a-z]*))*$@',
  120 => '@^(/(?:[a-z]*))*$@',
  121 => '@^(/([a-z]+))+$@',
  122 => '@^(/(?:[a-z]+))+$@',
  123 => '/[a-z]/',
  124 => '/^[hH]ello,\\s/',
  125 => '/l^o,\\s\\w{5}/',
  126 => '/\\[\\*\\],\\s(.*)/',
  127 => '@\\w{4}\\s\\w{2}\\s\\\\\\(?:\\s.*)@',
  128 => '/hello world/',
  129 => '#dummy#',
  130 => '/\\s/',
  131 => '{{\\D+}}',
  132 => '/(ab)(c)(d)(e)(f)(g)(h)(i)(j)(k)/',
  133 => '/x(.)/',
  134 => '/(.)x/',
  135 => '/(?P<capt1>.)(x)(?P<letsmix>\\S+)/',
  136 => '/(?P<size>\\d+)m|M/',
  137 => '/do not match/',
  138 => '/(?=xyz\\K)/',
  139 => '/(a(?=xyz\\K))/',
  140 => '/\\d+/',
  141 => '/(?P<3>)/',
  142 => '/(a)?([a-z]*)(\\d*)/',
  143 => '/^(\\d|.\\d)$/',
  144 => '/(?<a>4)?(?<b>2)?\\d/',
  145 => '/(?J)(?<chr>[ac])(?<num>\\d)|(?<chr>[b])/',
  146 => '/(?<chr>[ac])(?<num>\\d)|(?<chr>[b])/J',
  147 => '@^HTTP(.*?)\\w{2,}$@i',
  148 => '@(/\\w+\\.*/*)+@',
  149 => '@^http://[^w]{3}.*$@i',
  150 => '@.*?\\.co\\.uk$@i',
  151 => '/$re/',
  152 => '/\\b/u',
  153 => '/\\0/i',
  154 => '/\\\\\\0/i',
  155 => '[\\0]i',
  156 => '[]\\0i',
  157 => '[]i\\0',
  158 => '[\\\\\\0]i',
  159 => '/abc\\0def/',
  160 => '[abc\\0def]',
  161 => '/./u',
  162 => '/1/',
  163 => '/(?J)(?:(?<g>foo)|(?<g>bar))/',
  164 => '/(?J)(?:(?<g>foo)|(?<g>bar))(?<h>baz)/',
  165 => '/.(.)./n',
  166 => '/.(?P<test>.)./n',
  167 => '/\\S+/',
  168 => '/\\\\\\\\([^\\\\\\\\]+)\\s*$/',
  169 => '#\\[(right)\\](((?R)|[^[]+?|\\[)*)\\[/\\\\1\\]#siU',
  170 => '[CODE]&lt;td align=&quot;$stylevar[right]&quot;&gt;[/CODE]',
  171 => '/^\\w{6}$/',
  172 => '/\\G\\w/u',
  173 => '/asdf/',
  174 => '/(?P<word>the)/',
  175 => '/\' . implode(\'|\', $pattern) . \'/uix',
  176 => '/M(.*)/',
  177 => '/x/',
  178 => '/(?:(?:(?:(?:(?:(.))))))/',
  179 => '/(?>..)((?:(?>.)|.|.|.|u))/S',
  180 => '/^aeiou$/S',
  181 => '/aeiou/S',
  182 => '/([A-Z]|[a-z]|[0-9]| |Ñ|ñ|!|&quot;|%|&amp;|\'|´|-|:|;|>|=|&lt;|@|_|,|\\{|\\}|`|~|á|é|í|ó|ú|Á|É|Í|Ó|Ú|ü|Ü){1,300}/',
  183 => '/([0-9]+)/i',
  184 => '{CCM:CID_2}',
  185 => '/AskZ/iur',
  186 => '/AskZ/iu',
  187 => '/k/iu',
  188 => '/k/iur',
  189 => '/A\\x{17f}\\x{212a}Z/iu',
  190 => '/A\\x{17f}\\x{212a}Z/iur',
  191 => '/[AskZ]+/iur',
  192 => '/[AskZ]+/iu',
  193 => '/[\\x{17f}\\x{212a}]+/iur',
  194 => '/[\\x{17f}\\x{212a}]+/iu',
  195 => '/[^s]+/iur',
  196 => '/[^s]+/iu',
  197 => '/[^k]+/iur',
  198 => '/[^k]+/iu',
  199 => '/[^sk]+/iur',
  200 => '/[^sk]+/iu',
  201 => '/[^\\x{17f}]+/iur',
  202 => '/[^\\x{17f}]+/iu',
  203 => '/s(?r)s(?-r)s(?r:s)s/iu',
  204 => '/k(?^i)k/iur',
  205 => '#.#u',
  206 => '/[^\\pL\\pM]*/iu',
  207 => '/[\\p{L}\\p{Arabic}]/',
  208 => '/[^\\p{L}\\p{Arabic}]/',
  209 => '/a{1,3}b/U',
  210 => '/\\bwasser/iu',
  211 => '/[^\\w]wasser/iu',
);
