<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic;

use exface\Core\Facades\AbstractAjaxFacade\Elements\MarkianTrait;
use PHPUnit\Framework\TestCase;

/**
 * Selects installed dictionaries without requesting missing locale assets.
 */
class MarkianTranslationsTest extends TestCase
{
    /**
     * Regional locales select only their language, with embedded English fallback.
     *
     * @return void
     */
    public function testOnlyInstalledSessionLanguageDictionaryIsSelected() : void
    {
        $folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'markian-translations-' . bin2hex(random_bytes(8));
        mkdir($folder);
        $files = ['en.json', 'de.json', 'de-at.json'];
        try {
            foreach ($files as $filename) {
                file_put_contents($folder . DIRECTORY_SEPARATOR . $filename, '{}');
            }
            $element = new class {
                use MarkianTrait {
                    findMarkdownTranslationFiles as public translations;
                }
            };
            self::assertSame(['de.json'], $element->translations('de_DE', $folder));
            self::assertSame(['de.json'], $element->translations('de-AT', $folder));
            self::assertSame(['de.json'], $element->translations('de', $folder));
            self::assertSame([], $element->translations('en_US', $folder));
            self::assertSame([], $element->translations('fr_FR', $folder));
            self::assertSame([], $element->translations('../de', $folder));
        } finally {
            foreach ($files as $filename) {
                $path = $folder . DIRECTORY_SEPARATOR . $filename;
                if (is_file($path)) {
                    unlink($path);
                }
            }
            rmdir($folder);
        }
    }
}
