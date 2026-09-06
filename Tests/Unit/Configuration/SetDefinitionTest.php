<?php

declare(strict_types=1);

namespace Lochmueller\IndexKeSearch\Tests\Unit\Configuration;

use Lochmueller\IndexKeSearch\Configuration\Configuration;
use Lochmueller\IndexKeSearch\Tests\Unit\AbstractTest;
use Symfony\Component\Yaml\Yaml;

/**
 * The site set is the only place the settings are defined, so it has to stay in sync with the
 * identifiers the Configuration reads and with the labels of the settings editor.
 */
class SetDefinitionTest extends AbstractTest
{
    private const SET_PATH = __DIR__ . '/../../../Configuration/Sets/IndexKeSearch/';

    public function testSetDefinesEveryKnownSetting(): void
    {
        /** @var array{settings: array<string, mixed>} $definitions */
        $definitions = Yaml::parseFile(self::SET_PATH . 'settings.definitions.yaml');

        self::assertSame(
            array_values(Configuration::SETTING_IDENTIFIERS),
            array_keys($definitions['settings']),
        );
    }

    public function testEverySettingHasALabelAndADescription(): void
    {
        $labels = $this->getLabelIdentifiers();

        foreach (Configuration::SETTING_IDENTIFIERS as $identifier) {
            self::assertContains('settings.' . $identifier, $labels);
            self::assertContains('settings.description.' . $identifier, $labels);
        }
    }

    /**
     * @return list<string>
     */
    private function getLabelIdentifiers(): array
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(self::SET_PATH . 'labels.xlf'));

        $identifiers = [];
        foreach ($document->getElementsByTagName('trans-unit') as $transUnit) {
            $identifiers[] = (string) $transUnit->getAttribute('id');
        }

        return $identifiers;
    }
}
