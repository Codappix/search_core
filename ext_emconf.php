<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Search Core',
    'description' => 'Search core for implementing various search types.',
    'category' => 'be',
    'clearCacheOnLoad' => 1,
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-9.5.99',
            'php' => '7.0.0-7.2.99'
        ],
        'conflicts' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'Codappix\\SearchCore\\' => 'Classes',
        ],
    ],
    'state' => 'beta',
    'version' => '0.0.7',
    'author' => 'Daniel Siepmann',
    'author_email' => 'coding@daniel-siepmann.de',
];
