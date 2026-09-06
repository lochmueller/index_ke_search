<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\Configuration;

use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;

class ConfigurationLoader
{
    /**
     * @var array<string, Configuration>
     */
    private array $runtimeCache = [];

    public function loadBySite(SiteInterface $site): Configuration
    {
        $identifier = $site->getIdentifier();
        if (isset($this->runtimeCache[$identifier])) {
            return $this->runtimeCache[$identifier];
        }

        $configuration = $site instanceof Site
            ? Configuration::createBySettings($this->extractBridgeSettings($site->getSettings()))
            : new Configuration(enable: false);

        return $this->runtimeCache[$identifier] = $configuration;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractBridgeSettings(SiteSettings $settings): array
    {
        $bridgeSettings = [];
        foreach (Configuration::SETTING_IDENTIFIERS as $identifier) {
            if ($settings->has($identifier)) {
                $bridgeSettings[$identifier] = $settings->get($identifier);
            }
        }

        return $bridgeSettings;
    }
}
