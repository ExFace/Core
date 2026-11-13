<?php

namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JExcelTrait;
use exface\Core\Interfaces\Widgets\WidgetPropertyBindingInterface;
use exface\Core\Interfaces\Widgets\WidgetPropertyScaleInterface;
use exface\Core\Widgets\ColorIndicator;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\Display;
use exface\Core\Widgets\ProgressBar;
use PHPUnit\Framework\TestCase;

/**
 * Exercises display-column configuration without a Workbench or persisted metamodel.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class JExcelDisplayRenderersTest extends TestCase
{
    /**
     * Loads the same framework constants as a real Workbench in an isolated process.
     *
     * {@inheritDoc}
     * @see TestCase::setUp()
     */
    protected function setUp() : void
    {
        parent::setUp();
        require_once dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'CommonLogic' . DIRECTORY_SEPARATOR . 'Constants.php';
        // Exclude the existing parameter-order deprecation only while loading this trait.
        $errorReporting = error_reporting();
        try {
            error_reporting($errorReporting & ~E_DEPRECATED);
            trait_exists(JExcelTrait::class);
        } finally {
            error_reporting($errorReporting);
        }
    }

    /**
     * Numeric displays must bypass both numeric masking and ingestion formatting.
     */
    public function testProgressUsesCustomEditorAndIdentityIngestion() : void
    {
        $widget = $this->createDisplay(ProgressBar::class);
        $widget->method('getMin')->willReturn(10);
        $widget->method('getMax')->willReturn(110);
        $widget->method('getColor')->willReturn('steelblue');
        $widget->method('getText')->willReturn('Static <label>');
        $widget->method('getTextScale')->willReturn([60 => 'Half', 110 => 'Done']);
        $column = $this->createColumn($widget);
        $element = $this->createElement();
        $options = $element->buildJsJExcelColumnEditorOptions($column);
        $config = $this->readConfig($options);

        self::assertStringContainsString('createProgressBar(', $options);
        self::assertStringContainsString('type: "numeric"', $options);
        self::assertStringNotContainsString('mask:', $options);
        self::assertStringContainsString('function() { return jQuery("#sheet")[0].exfWidget; }', $options);
        self::assertFalse($element->needsDataFormatting($column));
        self::assertSame(10, $config['min']);
        self::assertSame(110, $config['max']);
        self::assertSame('Static <label>', $config['text']);
        self::assertSame([[60, 'Half'], [110, 'Done']], $config['textScale']);
        self::assertSame('steelblue', $config['color']);
        self::assertSame([[60, '~ERROR'], [110, '~OK']], $config['colorScale']);
        self::assertSame('red', $config['semanticColors']['~ERROR']);
    }

    /**
     * Bound and calculated colors use their prepared column name, not facade controls.
     */
    public function testColorBindingAndDisplayFlagsAreEmitted() : void
    {
        $widget = $this->createDisplay(ColorIndicator::class);
        $binding = $this->createStub(WidgetPropertyBindingInterface::class);
        $binding->method('isBoundToDataColumn')->willReturn(true);
        $binding->method('getDataColumnName')->willReturn('_calculated_color');
        $binding->method('getDataType')->willReturn($this->createStub(NumberDataType::class));
        $widget->method('getColorBinding')->willReturn($binding);
        $widget->method('getFill')->willReturn(false);
        $widget->method('getColorOnly')->willReturn(true);
        $config = $this->readConfig($this->createElement()->buildJsJExcelColumnEditorOptions(
            $this->createColumn($widget)
        ));

        self::assertSame('_calculated_color', $config['colorColumn']);
        self::assertTrue($config['colorNumeric']);
        self::assertNull($config['color']);
        self::assertFalse($config['fill']);
        self::assertTrue($config['colorOnly']);
        self::assertSame([[60, 'Low "hint"'], [110, 'High']], $config['hintScale']);
        self::assertSame('Literal <hint>', $config['hint']);
    }

    /**
     * Fixed colors remain literal strings, including quotes and backslashes.
     */
    public function testStaticColorAndDisabledFormatting() : void
    {
        $widget = $this->createDisplay(ColorIndicator::class, true);
        $binding = $this->createStub(WidgetPropertyBindingInterface::class);
        $binding->method('isBoundToDataColumn')->willReturn(false);
        $binding->method('hasValue')->willReturn(true);
        $binding->method('getValue')->willReturn('var(--color-"quoted"\\name)');
        $binding->method('getDataType')->willReturn($this->createStub(StringDataType::class));
        $widget->method('getColorBinding')->willReturn($binding);
        $options = $this->createElement()->buildJsJExcelColumnEditorOptions($this->createColumn($widget));

        self::assertSame('var(--color-"quoted"\\name)', $this->readConfig($options)['color']);
        self::assertStringContainsString('format: function(value) { return value; }', $options);
    }

    /**
     * Hidden columns retain the library's hidden type even for supported widgets.
     */
    public function testHiddenDisplayDoesNotCreateAnEditor() : void
    {
        $column = $this->createColumn($this->createDisplay(ColorIndicator::class), false, true);
        $options = $this->createElement()->buildJsJExcelColumnEditorOptions($column);

        self::assertStringContainsString('type: "hidden"', $options);
        self::assertStringNotContainsString('editor:', $options);
    }

    /**
     * Editable display widgets retain their existing built-in numeric editor.
     */
    public function testEditableDisplayRetainsExistingMapping() : void
    {
        $type = $this->createStub(NumberDataType::class);
        $type->method('getBase')->willReturn(10);
        $widget = $this->getMockBuilder(ProgressBar::class)->disableOriginalConstructor()
            ->onlyMethods(['getValueDataType'])->getMock();
        $widget->method('getValueDataType')->willReturn($type);
        $options = $this->createElement()->buildJsJExcelColumnEditorOptions($this->createColumn($widget, true));

        self::assertStringContainsString('type: "numeric"', $options);
        self::assertStringNotContainsString('editor:', $options);
    }

    /**
     * Ordinary boolean displays must not enter the custom rendering path.
     */
    public function testUnsupportedDisplayRetainsCheckboxMapping() : void
    {
        $widget = $this->getMockBuilder(Display::class)->disableOriginalConstructor()
            ->onlyMethods(['getValueDataType'])->getMock();
        $widget->method('getValueDataType')->willReturn($this->createStub(BooleanDataType::class));
        $options = $this->createElement()->buildJsJExcelColumnEditorOptions($this->createColumn($widget));

        self::assertStringContainsString('type: "checkbox"', $options);
        self::assertStringNotContainsString('editor:', $options);
    }

    /**
     * Invalid progress configuration is rejected before emitting JavaScript.
     */
    public function testInvalidRangeRaisesConfigurationError() : void
    {
        $widget = $this->createDisplay(ProgressBar::class);
        $widget->method('getMin')->willReturn(100);
        $widget->method('getMax')->willReturn(100);
        $this->expectException(WidgetConfigurationError::class);
        $this->expectExceptionMessage('finite min < max');
        $this->createElement()->buildJsJExcelColumnEditorOptions($this->createColumn($widget));
    }

    /**
     * Each shared include list contains one renderer script and stylesheet.
     */
    public function testSharedResourcesAreRegisteredOnce() : void
    {
        $includes = implode("\n", $this->createElement()->buildHtmlHeadTagsForJExcel());
        self::assertSame(1, substr_count($includes, 'exfJExcelDisplayRenderers.js'));
        self::assertSame(1, substr_count($includes, 'exfJExcelDisplayRenderers.css'));
    }

    /**
     * @param string $class
     * @param bool $disableFormatting
     * @return ColorIndicator|ProgressBar
     */
    private function createDisplay(string $class, bool $disableFormatting = false) : Display
    {
        $widget = $this->getMockBuilder($class)->disableOriginalConstructor()->getMock();
        $type = $this->createStub(NumberDataType::class);
        $type->method('getDecimalSeparator')->willReturn(',');
        $type->method('getGroupSeparator')->willReturn('.');
        $widget->method('getValueDataType')->willReturn($type);
        $widget->method('getDisableFormatting')->willReturn($disableFormatting);
        $widget->method('getColorScale')->willReturn([60 => '~ERROR', 110 => '~OK']);
        $widget->method('isColorScaleRangeBased')->willReturn(true);
        $widget->method('getHint')->willReturn('Literal <hint>');
        $scale = $this->createStub(WidgetPropertyScaleInterface::class);
        $scale->method('getScaleValues')->willReturn([60 => 'Low "hint"', 110 => 'High']);
        $scale->method('isRangeBased')->willReturn(true);
        $widget->method('getHintScale')->willReturn($scale);
        return $widget;
    }

    /**
     * @param Display $widget
     * @param bool $editable
     * @param bool $hidden
     * @return DataColumn
     */
    private function createColumn(Display $widget, bool $editable = false, bool $hidden = false) : DataColumn
    {
        $column = $this->getMockBuilder(DataColumn::class)->disableOriginalConstructor()
            ->onlyMethods(['getCellWidget', 'isHidden', 'isEditable', 'getAlign'])->getMock();
        $column->method('getCellWidget')->willReturn($widget);
        $column->method('isHidden')->willReturn($hidden);
        $column->method('isEditable')->willReturn($editable);
        $column->method('getAlign')->willReturn('left');
        return $column;
    }

    /**
     * @param string $options
     * @return array
     */
    private function readConfig(string $options) : array
    {
        self::assertSame(1, preg_match('/Object\.assign\((\{.*\}), \{format:/', $options, $matches));
        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Supplies only the facade services used by configuration generation.
     *
     * @return object
     */
    private function createElement() : object
    {
        return new class {
            use JExcelTrait {
                buildJsJExcelColumnEditorOptions as public;
                needsDataFormatting as public;
                buildHtmlHeadTagsForJExcel as public;
            }

            /** @return object */
            public function getWidget() : object
            {
                return new \stdClass();
            }

            /** @return object */
            public function getFacade() : object
            {
                return $this;
            }

            /** @return array */
            public function getSemanticColors() : array
            {
                return ['~ERROR' => 'red', '~OK' => 'green'];
            }

            /** @return object */
            public function getDataTypeFormatter($type) : object
            {
                return new class {
                    /** @return string */
                    public function buildJsFormatter(string $value) : string
                    {
                        return 'String(' . $value . ')';
                    }
                };
            }

            /** @return object */
            public function getElement($widget) : object
            {
                return $this;
            }

            /** @return string */
            public function buildCssTextAlignValue($align) : string
            {
                return 'right';
            }

            /** @return string */
            public function escapeString($value) : string
            {
                return json_encode($value, JSON_THROW_ON_ERROR);
            }

            /** @return string */
            protected function buildJsJqueryElement() : string
            {
                return 'jQuery("#sheet")';
            }

            /** @return string */
            public function buildUrlToVendorFile(string $path) : string
            {
                return $path;
            }

            /** @return string */
            public function buildUrlToSource(string $option) : string
            {
                return $option;
            }

            /** @return object */
            public function getConfig() : object
            {
                return $this;
            }

            /** @return bool */
            public function hasOption(string $option) : bool
            {
                return false;
            }
        };
    }
}
