<?php

declare(strict_types=1);

defined('TYPO3') or die();

use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/** @var Environment $environment */
$environment = GeneralUtility::makeInstance(Environment::class);
$level = $environment->getContext()->isDevelopment() ? LogLevel::DEBUG : LogLevel::WARNING;

$GLOBALS['TYPO3_CONF_VARS']['LOG']['Lochmueller']['IndexKeSearch']['writerConfiguration'] = [
    $level => [
        FileWriter::class => [
            'logFileInfix' => 'index_ke_search',
        ],
    ],
];
