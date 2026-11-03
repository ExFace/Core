<?php
namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JExcelDisplayTrait;
use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsBooleanFormatter;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface;
use exface\Core\Interfaces\Widgets\WidgetPropertyScaleInterface;
use exface\Core\Widgets\ColorIndicator;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\DataSpreadSheet;
use exface\Core\Widgets\Display;
use exface\Core\Widgets\ProgressBar;
use PHPUnit\Framework\TestCase;

/**
 * Local generator checks; widget doubles supply configuration without a Workbench.
 * Browser regression tests exercise the emitted renderer with real DOM elements.
 */
class JExcelDisplayTest extends TestCase
{
    public function testSemanticColorsAreJsonNotEscapedStrings() : void
    {
        $colors = ['~OK' => 'lightgreen', '~WARNING' => 'yellow', '~ERROR' => 'orangered', '~INFO' => 'steelblue',
            '~CUSTOM' => '</script>"&'];
        $facade = $this->createMock(AbstractAjaxFacade::class);
        $facade->method('getSemanticColors')->willReturn($colors);
        $generator = $this->generator();
        $generator->facade = $facade;
        // This generator deliberately has no escapeString(): structured JS data
        // must not pass through a facade's HTML/string escaping implementation.
        $js = $generator->semanticColors();
        $this->assertSame($colors, json_decode($js, true));
        $this->assertStringNotContainsString('</script>', $js);
    }

    public function testRelatedBooleanSelfBindingUsesCurrentCellWithoutAuxiliaryColumn() : void
    {
        $alias = 'Massnahme__Baufreiheitsrelevant';
        foreach ([$alias, 'CUSTOM_BF_COLUMN'] as $name) {
            $binding = $this->createMock(WidgetPropertyBindingInterface::class);
            $binding->method('getDataColumnName')->willReturn($alias);
            $binding->method('isBoundToAttribute')->willReturn(true);
            $binding->method('getAttributeAlias')->willReturn($alias);
            $cell = $this->createMock(ColorIndicator::class);
            $cell->method('getColorBinding')->willReturn($binding);
            $cell->method('isBoundToAttribute')->willReturn(true);
            $cell->method('getAttributeAlias')->willReturn($alias);
            $cell->method('getColorScale')->willReturn(['lightsteelblue', 'steelblue']);
            $cell->method('getColorOnly')->willReturn(false);
            $cell->method('getFill')->willReturn(true);
            $cell->method('getDisableFormatting')->willReturn(true);
            $cell->method('getHintScale')->willReturn($this->createMock(WidgetPropertyScaleInterface::class));
            $column = $this->createMock(DataColumn::class);
            $column->method('getCellWidget')->willReturn($cell);
            $column->method('getDataColumnName')->willReturn($name);
            $widget = $this->createMock(DataSpreadSheet::class);
            $widget->method('getColumns')->willReturn([$column]);
            $widget->expects($this->never())->method('createColumnFromUxon');
            $generator = $this->generator();
            $generator->widget = $widget;
            $generator->prepareColumns();
            $js = $generator->options($column);
            $this->assertStringContainsString('"colorColumn":null', $js);
            $this->assertStringContainsString('"colorScale":["lightsteelblue","steelblue"]', $js);
            $this->assertStringContainsString('"fill":true', $js);
        }
    }

    /**
     * Ordinary and hidden cells must keep the grid's existing rendering.
     *
     * @return void
     */
    public function testLeavesOrdinaryAndHiddenCellsAlone() : void
    {
        $column = $this->createMock(DataColumn::class);
        $column->method('getCellWidget')->willReturn($this->createMock(Display::class));
        $this->assertSame('null', $this->generator()->options($column));

        $hidden = $this->createMock(DataColumn::class);
        $hidden->method('isHidden')->willReturn(true);
        $hidden->method('getCellWidget')->willReturn($this->createMock(ColorIndicator::class));
        $this->assertSame('null', $this->generator()->options($hidden));
    }

    /**
     * Color binding names and display settings must be passed through safely.
     *
     * @return void
     */
    public function testBuildsColorBindingConfiguration() : void
    {
        $binding = $this->createMock(WidgetPropertyBindingInterface::class);
        $binding->method('getDataColumnName')->willReturn('STATUS__COLOR');
        $cell = $this->createMock(ColorIndicator::class);
        $cell->method('getColorBinding')->willReturn($binding);
        $cell->method('getColorScale')->willReturn(['urgent' => '~ERROR']);
        $cell->method('getFill')->willReturn(false);
        $cell->method('getColorOnly')->willReturn(true);
        $cell->method('getDisableFormatting')->willReturn(true);
        $cell->method('getHint')->willReturn('</script>"quoted"');
        $cell->method('getHintScale')->willReturn($this->createMock(WidgetPropertyScaleInterface::class));
        $column = $this->createMock(DataColumn::class);
        $column->method('getCellWidget')->willReturn($cell);

        $js = $this->generator()->options($column);
        $this->assertStringContainsString('"colorColumn":"STATUS__COLOR"', $js);
        $this->assertStringContainsString('"colorOnly":true', $js);
        $this->assertStringContainsString('"fill":false', $js);
        $this->assertStringContainsString('"urgent":"~ERROR"', $js);
        $this->assertStringNotContainsString('</script>', $js);
        $this->assertStringContainsString('function(mValue){return mValue;}', $js);
    }

    /**
     * Progress bounds and even an explicit zero label must survive generation.
     *
     * @return void
     */
    public function testBuildsProgressConfiguration() : void
    {
        $cell = $this->createMock(ProgressBar::class);
        $cell->method('getMin')->willReturn(20);
        $cell->method('getMax')->willReturn(80);
        $cell->method('getText')->willReturn('0');
        $cell->method('getColor')->willReturn('~OK');
        $cell->method('getDisableFormatting')->willReturn(true);
        $cell->method('getHintScale')->willReturn($this->createMock(WidgetPropertyScaleInterface::class));
        $column = $this->createMock(DataColumn::class);
        $column->method('getCellWidget')->willReturn($cell);
        $js = $this->generator()->options($column);
        $this->assertStringContainsString('"type":"progress"', $js);
        $this->assertStringContainsString('"min":20,"max":80', $js);
        $this->assertStringContainsString('"text":"0"', $js);
        $this->assertStringContainsString('"color":"~OK"', $js);
    }

    public function testBooleanDisplayLabelsUseLocalizedTextInsteadOfHtml() : void
    {
        $contexts = [false];
        if (class_exists(\exface\UI5Facade\Facades\Formatters\UI5BooleanFormatter::class)) {
            $contexts[] = true;
        }
        foreach ($contexts as $ui5) {
            $datatype = $this->createMock(BooleanDataType::class);
            $datatype->method('format')->willReturnCallback(function ($value) {
                return $value ? 'Ja' : 'Nein';
            });
            $formatter = $this->getMockBuilder(JsBooleanFormatter::class)->disableOriginalConstructor()
                ->onlyMethods(['getDataType', 'getTruthyValues', 'getFalsyValues', 'getJsEmptyText'])->getMock();
            $formatter->method('getDataType')->willReturn($datatype);
            $formatter->method('getTruthyValues')->willReturn(['val === 1', "val === '1'"]);
            $formatter->method('getFalsyValues')->willReturn(['val === 0', "val === '0'"]);
            $formatter->method('getJsEmptyText')->willReturn('val');
            if ($ui5) {
                new \exface\UI5Facade\Facades\Formatters\UI5BooleanFormatter($formatter);
            }
            $facade = $this->createMock(AbstractAjaxFacade::class);
            $facade->method('getDataTypeFormatter')->willReturn($formatter);
            $generator = $this->generator();
            $generator->facade = $facade;
            foreach ([ColorIndicator::class, ProgressBar::class] as $cellClass) {
                $cell = $this->createMock($cellClass);
                $cell->method('getValueDataType')->willReturn($datatype);
                $cell->method('getHintScale')->willReturn($this->createMock(WidgetPropertyScaleInterface::class));
                if ($cell instanceof ColorIndicator) {
                    $cell->method('getColorBinding')->willReturn($this->createMock(WidgetPropertyBindingInterface::class));
                } else {
                    $attribute = $this->createMock(MetaAttributeInterface::class);
                    $attribute->method('getDataType')->willReturn($datatype);
                    $cell->method('isTextBoundToAttribute')->willReturn(true);
                    $cell->method('getTextAttribute')->willReturn($attribute);
                }
                $column = $this->createMock(DataColumn::class);
                $column->method('getCellWidget')->willReturn($cell);
                $js = $generator->options($column);
                $this->assertStringContainsString('(mValue, "Ja", "Nein")', $js);
                $this->assertStringNotContainsString('<i ', $js);
                $this->assertStringNotContainsString('<span', $js);
                $this->assertStringNotContainsString('&#', $js);
                $this->assertStringNotContainsString('sap-icon://', $js);
            }
        }
    }

    /**
     * Color-only defaults remain overridable independently of the shared renderer.
     *
     * @return void
     */
    public function testSharedDefaultAndRendererAreAvailable() : void
    {
        $generator = $this->generator();
        $this->assertFalse($generator->colorOnlyDefault());
        $this->assertStringContainsString('renderDisplayCell: function', $generator->renderer());
        $this->assertStringContainsString('resolveDisplayScale: function', $generator->renderer());
    }

    /**
     * Expose the protected generator surface without constructing a facade or Workbench.
     *
     * @return object
     */
    private function generator() : object
    {
        return new class {
            public $facade;
            public $widget;

            public function getWidget()
            {
                return $this->widget;
            }

            public function getFacade()
            {
                return $this->facade;
            }

            use JExcelDisplayTrait {
                buildJsJExcelDisplayOptions as public options;
                buildJsJExcelDisplayMethods as public renderer;
                buildJsJExcelSemanticColors as public semanticColors;
                prepareJExcelDisplayColumns as public prepareColumns;
                getJExcelColorOnlyDefault as public colorOnlyDefault;
            }
        };
    }
}


