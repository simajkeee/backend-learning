<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,

        'single_line_throw' => false,

        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],

        'trailing_comma_in_multiline' => [
            'elements' => [
                'arguments',
                'parameters',
                'arrays',
            ],
        ],
    ])
    ->setFinder($finder)
;
