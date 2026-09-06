<?php

/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Index to ke_search Bridge',
    'description' => 'Bridge between EXT:index and EXT:ke_search - writes the indexing events of EXT:index into the ke_search index',
    'version' => '1.0.0',
    'category' => 'be',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.15-14.4.99',
            'index' => '2.3.0-2.99.99',
            'ke_search' => '6.0.0-7.99.99',
            'php' => '8.3.0-8.99.99',
        ],
    ],
    'state' => 'stable',
    'author' => 'Tim Lochmüller',
    'author_email' => 'tim@fruit-lab.de',
    'author_company' => 'HDNET GmbH & Co. KG',
    'autoload' => [
        'psr-4' => [
            'Lochmueller\\IndexKeSearch\\' => 'Classes',
        ],
    ],
];
