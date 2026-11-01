<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Facades\AbstractAjaxFacade\Elements\MarkianTrait;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Widgets\InputCode;
use exface\Core\Widgets\InputMarkdown;
use PHPUnit\Framework\TestCase;

/**
 * Protects compatible widget settings without requiring a persisted metamodel or a mocked Workbench.
 */
class CodeFormatterCompatibilityTest extends TestCase
{
    /**
     * Keeps Markdown fence-language selection independent of a widget-wide language default.
     *
     * @return void
     */
    public function testMarkdownDefaultsRetainOneOwnedFormatter() : void
    {
        /**
         * @var InputMarkdown $widget
         */
        $widget = WidgetFactory::create(UiPageFactory::createEmpty(new Workbench()), 'InputMarkdown');
        $formatter = $widget->getCodeFormatter();
        $this->assertNull($formatter->getLanguage());
        $this->assertTrue($formatter->getColorize());
        $this->assertSame($widget, $formatter->getWidget());
        $this->assertSame($formatter, $widget->getCodeFormatter());
    }

    /**
     * Preserves the existing code input's inferred SQL formatter defaults.
     *
     * @return void
     */
    public function testCodeInputRetainsSqlDefaults() : void
    {
        /**
         * @var InputCode $widget
         */
        $widget = WidgetFactory::create(UiPageFactory::createEmpty(new Workbench()), 'InputCode');
        $widget->setLanguage('sql');
        $this->assertSame('sql', $widget->getCodeFormatter()->getLanguage());
        $this->assertSame('sql', $widget->getCodeFormatter()->getDialect());
        $this->assertTrue($widget->getCodeFormatter()->getPrettify());
    }

    /**
     * Ensures designers can apply the same UXON to either widget and replace cached settings.
     *
     * @return void
     */
    public function testBothWidgetsAcceptTheSameConfiguration() : void
    {
        foreach (['InputCode', 'InputMarkdown'] as $type) {
            /**
             * @var InputCode|InputMarkdown $widget
             */
            $widget = WidgetFactory::create(UiPageFactory::createEmpty(new Workbench()), $type);
            $widget->importUxonObject(new UxonObject(['code_formatter' => [
                'language' => 'sql', 'dialect' => 't-sql', 'colorize' => false, 'prettify' => false
            ]]));
            $formatter = $widget->getCodeFormatter();
            $this->assertSame('sql', $formatter->getLanguage());
            $this->assertSame('tsql', $formatter->getDialect());
            $this->assertFalse($formatter->getColorize());
            $this->assertFalse($formatter->getPrettify());
            $this->assertSame($widget, $formatter->getWidget());
            $widget->importUxonObject(new UxonObject(['code_formatter' => ['language' => 'json']]));
            $this->assertNotSame($formatter, $widget->getCodeFormatter());
            $this->assertSame('json', $widget->getCodeFormatter()->getLanguage());
        }
    }

    /**
     * Exercises PHP-to-JavaScript serialization of actual widget settings and resolved asset URLs.
     *
     * @return void
     */
    public function testMarkianReceivesOnlyApplicableHighlightingSettings() : void
    {
        /**
         * @var InputMarkdown $widget
         */
        $widget = WidgetFactory::create(UiPageFactory::createEmpty(new Workbench()), 'InputMarkdown');
        $widget->importUxonObject(new UxonObject(['code_formatter' => [
            'language' => 'js', 'colorize' => false, 'prettify' => true
        ]]));
        $element = new class($widget) {
            use MarkianTrait {
                buildJsMarkdownCodeSettings as public codeSettings;
            }

            /**
             * Keeps the real widget under test while replacing only URL resolution.
             *
             * @param \exface\Core\Interfaces\WidgetInterface $widget
             */
            public function __construct(private \exface\Core\Interfaces\WidgetInterface $widget)
            {
            }

            /**
             * Supplies the configured widget without a facade lifecycle.
             *
             * @return \exface\Core\Interfaces\WidgetInterface
             */
            public function getWidget() : \exface\Core\Interfaces\WidgetInterface
            {
                return $this->widget;
            }

            /**
             * Isolates the host URL boundary from installation configuration.
             *
             * @return object
             */
            public function getFacade() : object
            {
                return new class {
                    /**
                     * Provides a versioned subdirectory URL to exercise asset serialization.
                     *
                     * @param string $key
                     * @return string
                     */
                    public function buildUrlToSource(string $key) : string
                    {
                        return '/assets/' . $key . '/?v=fixture';
                    }
                };
            }
        };
        $this->assertSame([
            'language' => 'js', 'colorize' => false,
            'languageBase' => '/assets/LIBS.MARKIAN.CODE.LANGUAGES/?v=fixture'
        ], json_decode($element->codeSettings(), true, 512, JSON_THROW_ON_ERROR));
    }
}