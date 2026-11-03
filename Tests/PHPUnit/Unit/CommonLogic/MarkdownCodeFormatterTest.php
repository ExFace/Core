<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Widgets\DisplayMarkdown;
use exface\Core\Widgets\Markdown;
use PHPUnit\Framework\TestCase;

/**
 * Protects library-independent code highlighting configuration for rendering-only widgets.
 */
class MarkdownCodeFormatterTest extends TestCase
{
    /**
     * Enables mixed-language display by default without needing an input widget or database.
     *
     * @return void
     */
    public function testDisplayWidgetsDefaultToFenceLanguages() : void
    {
        foreach (['Markdown', 'DisplayMarkdown'] as $type) {
            /**
             * @var Markdown|DisplayMarkdown $widget
             */
            $widget = WidgetFactory::create(UiPageFactory::createEmpty(new Workbench()), $type);
            $this->assertTrue($widget->getCodeFormatter()->getColorize());
            $this->assertNull($widget->getCodeFormatter()->getLanguage());
            $this->assertSame($widget, $widget->getCodeFormatter()->getWidget());
        }
    }

    /**
     * Allows display widgets to use the same explicit language and colorize settings as inputs.
     *
     * @return void
     */
    public function testDisplayWidgetsAcceptCompatibleFormatterUxon() : void
    {
        foreach (['Markdown', 'DisplayMarkdown'] as $type) {
            /**
             * @var Markdown|DisplayMarkdown $widget
             */
            $widget = WidgetFactory::create(UiPageFactory::createEmpty(new Workbench()), $type);
            $widget->importUxonObject(new UxonObject(['code_formatter' => [
                'language' => 'php', 'colorize' => false, 'prettify' => false
            ]]));
            $this->assertSame('php', $widget->getCodeFormatter()->getLanguage());
            $this->assertFalse($widget->getCodeFormatter()->getColorize());
            $this->assertFalse($widget->getCodeFormatter()->getPrettify());
        }
    }
}