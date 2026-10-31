<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Widgets\Parts\TextMention;
use PHPUnit\Framework\TestCase;

/**
 * Protects framework-neutral mention settings without a database or mocked Workbench.
 */
class TextMentionTest extends TestCase
{
    /**
     * Keeps prefix, body pattern and suggestion fields independent during UXON import.
     * 
     * @return void
     */
    public function testSeparateMentionPropertiesSurviveImport() : void
    {
        $page = UiPageFactory::createEmpty(new Workbench());
        $widget = WidgetFactory::create($page, 'InputMarkdown');
        $mention = new TextMention($widget, new UxonObject([
            'tag_prefix' => '#',
            'tag_text_regex' => '\\d*',
            'tag_color' => 'lightblue',
            'autosuggest' => [
                'object_alias' => 'example.App.RECORD',
                'filter_attribute' => 'SEARCH_TEXT',
                'tag_attribute' => 'NUMBER',
                'suggestion_attribute' => 'LABEL',
                'max_number_of_rows' => 10
            ]
        ]));
        $this->assertSame('#', $mention->getTagPrefix());
        $this->assertSame('\\d*', $mention->getTagTextRegex());
        $this->assertSame('lightblue', $mention->getTagColor());
        $this->assertSame('NUMBER', $mention->getAutosuggest()->getTagAttribute());
        $this->assertSame('SEARCH_TEXT', $mention->getAutosuggest()->getFilterAttribute());
        $this->assertSame('LABEL', $mention->getAutosuggest()->getSuggestionAttribute());
        $this->assertSame(10, $mention->getAutosuggest()->getMaxNumberOfRows());
    }

    /**
     * Keeps suggestion-object attribute aliases intact when exporting imported settings.
     *
     * @return void
     */
    public function testAutosuggestAttributesSurviveExport() : void
    {
        $page = UiPageFactory::createEmpty(new Workbench());
        $widget = WidgetFactory::create($page, 'InputMarkdown');
        $config = new UxonObject([
            'object_alias' => 'example.App.RECORD',
            'filter_attribute' => 'SEARCH_TEXT',
            'tag_attribute' => 'PARENT__NUMBER',
            'suggestion_attribute' => 'PARENT__LABEL'
        ]);
        $mention = new TextMention($widget, new UxonObject(['tag_prefix' => '#', 'autosuggest' => $config->toArray()]));
        $autosuggest = $mention->getAutosuggest();
        $this->assertSame('PARENT__NUMBER', $autosuggest->getTagAttribute());
        $this->assertSame('PARENT__LABEL', $autosuggest->getSuggestionAttribute());
        $this->assertSame($config->toArray(), $autosuggest->exportUxonObject()->toArray());
    }

    /**
     * Allows existing prefix-only models to suggest text without a missing-property type error.
     * 
     * @return void
     */
    public function testMissingBodyPatternDefaultsToAnyText() : void
    {
        $page = UiPageFactory::createEmpty(new Workbench());
        $widget = WidgetFactory::create($page, 'InputMarkdown');
        $mention = new TextMention($widget, new UxonObject(['tag_prefix' => '@']));
        $this->assertSame('.*', $mention->getTagTextRegex());
    }
}