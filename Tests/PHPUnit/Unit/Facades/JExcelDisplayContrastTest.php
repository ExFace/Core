<?php

namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JExcelTrait;
use exface\Core\Interfaces\Widgets\WidgetPropertyScaleInterface;
use exface\Core\Widgets\DataColumn;
use exface\Core\Widgets\ProgressBar;
use PHPUnit\Framework\TestCase;

/**
 * Verifies shared contrast configuration without a Workbench or persisted model.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class JExcelDisplayContrastTest extends TestCase
{
    /**
     * @dataProvider preferences
     */
    public function testContrastPreferenceAndHelperRegistration(?float $preference, float $expected): void
    {
        require_once dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'CommonLogic' . DIRECTORY_SEPARATOR . 'Constants.php';
        $reporting = error_reporting();
        try {
            // The trait has a pre-existing optional-before-required parameter deprecation.
            error_reporting($reporting & ~E_DEPRECATED);
            trait_exists(JExcelTrait::class);
        } finally {
            error_reporting($reporting);
        }
        $widget = $this->createStub(ProgressBar::class);
        $widget->method('getMin')->willReturn(0);
        $widget->method('getMax')->willReturn(100);
        $widget->method('getDisableFormatting')->willReturn(true);
        $widget->method('getValueDataType')->willReturn($this->createStub(StringDataType::class));
        $widget->method('getHintScale')->willReturn($this->createStub(WidgetPropertyScaleInterface::class));
        $column = $this->getMockBuilder(DataColumn::class)->disableOriginalConstructor()
            ->onlyMethods(['getCellWidget', 'getAlign'])->getMock();
        $column->method('getCellWidget')->willReturn($widget);
        $column->method('getAlign')->willReturn('left');

        $element = new class($preference) {
            use JExcelTrait {
                buildJsJExcelDisplayEditorOptions as public;
                buildHtmlHeadTagsForJExcel as public;
            }

            /** @param float|null $preference */
            public function __construct(private ?float $preference)
            {
            }

            /** @return object */
            public function getFacade(): object
            {
                return $this;
            }

            /** @return object */
            public function getConfig(): object
            {
                return $this;
            }

            /** @return array */
            public function getSemanticColors(): array
            {
                return [];
            }

            /** @return object */
            public function getDataTypeFormatter($type): object
            {
                return new \stdClass();
            }

            /** @return bool */
            public function hasOption(string $option): bool
            {
                return $option === 'WIDGET.OBJECT_STATUS.TEXT_COLOR_PREFERENCE' && $this->preference !== null;
            }

            /** @return float|null */
            public function getOption(string $option): ?float
            {
                return $this->preference;
            }

            /** @return string */
            public function buildUrlToVendorFile(string $path): string
            {
                return $path;
            }

            /** @return string */
            public function buildUrlToSource(string $option): string
            {
                return $option;
            }

            /** @return string */
            public function escapeString($value): string
            {
                return json_encode($value, JSON_THROW_ON_ERROR);
            }

            /** @return string */
            protected function buildJsJqueryElement(): string
            {
                return 'jQuery("#sheet")';
            }
        };
        $options = $element->buildJsJExcelDisplayEditorOptions($column);
        self::assertSame(1, preg_match('/Object\.assign\((\{.*\}), \{format:/', $options, $matches));
        $config = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($expected, (float)$config['textColorPreference']);
        $includes = implode("\n", $element->buildHtmlHeadTagsForJExcel());
        self::assertSame(1, substr_count($includes, 'exfColorTools.js'));
        self::assertLessThan(strpos($includes, 'exfJExcelDisplayRenderers.js'), strpos($includes, 'exfColorTools.js'));
    }

    /**
     * @return array
     */
    public function preferences(): array
    {
        return [
            'shared default' => [null, 0.5],
            'UI5 preference' => [0.35, 0.35],
            'always white' => [0.0, 0.0],
            'always black' => [1.0, 1.0]
        ];
    }
}
