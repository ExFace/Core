<?php

namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\CommonLogic\WidgetDimension;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Facades\AbstractAjaxFacade\Elements\HtmlColorIndicatorTrait;
use exface\Core\Facades\AbstractAjaxFacade\Elements\HtmlProgressBarTrait;
use exface\Core\Widgets\ColorIndicator;
use exface\Core\Widgets\ProgressBar;
use PHPUnit\Framework\TestCase;

/**
 * Covers the HTML decorators used by EasyUI without a Workbench or database.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class HtmlDisplayContrastTest extends TestCase
{
    /**
     * @dataProvider preferences
     */
    public function testDecoratorsUseConfiguredContrast(string $trait, ?float $preference, string $expected) : void
    {
        require_once dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'CommonLogic' . DIRECTORY_SEPARATOR . 'Constants.php';
        $progress = $trait === HtmlProgressBarTrait::class;
        $widget = $this->createStub($progress ? ProgressBar::class : ColorIndicator::class);
        $dimension = $this->createStub(WidgetDimension::class);
        $dimension->method('isUndefined')->willReturn(true);
        $widget->method('getWidth')->willReturn($dimension);
        $widget->method('getHeight')->willReturn($dimension);
        $widget->method('getValueDataType')->willReturn($this->createStub(NumberDataType::class));
        $widget->method('getColorScale')->willReturn([50 => 'black', 100 => 'white']);
        if ($progress) {
            $widget->method('getMin')->willReturn(0);
            $widget->method('getMax')->willReturn(100);
            $widget->method('getAlign')->willReturn('left');
        } else {
            $widget->method('getFill')->willReturn(true);
            $widget->method('hasColorScale')->willReturn(true);
            $widget->method('isColorScaleRangeBased')->willReturn(true);
        }
        $element = $this->getMockBuilder($trait)->addMethods([
            'getWidget', 'getFacade', 'getConfig', 'hasOption', 'getOption',
            'getDataTypeFormatter', 'getJsEmptyText', 'buildJsValueFormatter',
            'getId', 'getWidth', 'getHeight', 'escapeString'
        ])->getMockForTrait();
        $element->method('getWidget')->willReturn($widget);
        $element->method('getFacade')->willReturn($element);
        $element->method('getConfig')->willReturn($element);
        $element->method('hasOption')->willReturn($preference !== null);
        $element->method('getOption')->willReturn($preference);
        $element->method('getDataTypeFormatter')->willReturn($element);
        $element->method('getJsEmptyText')->willReturn('null');
        $element->method('buildJsValueFormatter')->willReturnArgument(0);
        $element->method('getId')->willReturn('fixture');
        $element->method('getWidth')->willReturn('100%');
        $element->method('getHeight')->willReturn('20px');
        $element->method('escapeString')->willReturnCallback(static function ($text) {
            return '"' . htmlspecialchars($text, ENT_QUOTES) . '"';
        });
        $javascript = $element->buildJsValueDecorator('value');
        $colorVariable = $progress ? 'color' : 'sColor';
        self::assertStringContainsString(
            "exfColorTools.pickTextColorForBackgroundColor({$colorVariable}, {$expected})",
            $javascript
        );
        if ($progress) {
            self::assertStringContainsString(
                "exfColorTools.pickTextColorForBackgroundColor('transparent', {$expected})",
                $javascript
            );
            self::assertStringContainsString('clip-path: inset(0 calc(100% - exfph-progress%)', $javascript);
            self::assertStringContainsString('aria-hidden=', $javascript);
            self::assertStringContainsString('.replace(/exfph-progress/g,', $javascript);
        } else {
            self::assertStringContainsString('color: exfph-foreground !important', $javascript);
            self::assertStringContainsString('var sColor =', $javascript);
        }
    }

    /**
     * @return array
     */
    public function preferences() : array
    {
        $cases = [];
        foreach ([HtmlColorIndicatorTrait::class, HtmlProgressBarTrait::class] as $trait) {
            foreach (['fallback' => [null, '0.5'], 'configured' => [0.35, '0.35'],
                'white' => [0.0, '0'], 'black' => [1.0, '1']] as $name => $values) {
                $cases[$trait . ':' . $name] = [$trait, $values[0], $values[1]];
            }
        }
        return $cases;
    }
}
