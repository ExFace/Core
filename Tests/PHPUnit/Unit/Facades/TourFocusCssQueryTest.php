<?php

namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\AjaxFacadeElementInterface;
use exface\Core\Facades\AbstractAjaxFacade\Tours\DriverJsTourDriver;
use exface\Core\Widgets\DataTable;
use exface\Core\Widgets\Parts\Tours\Tour;
use exface\Core\Widgets\Parts\Tours\TourStoryStep;
use exface\Core\Widgets\Parts\Tours\TourWaypointStep;
use PHPUnit\Framework\TestCase;

/**
 * Covers CSS-query UXON and tour target generation without a persisted metamodel.
 */
class TourFocusCssQueryTest extends TestCase
{
    /**
     * Both step types import queries through the real UXON setters.
     */
    public function testUxonImportSupportsStoryAndWaypointSteps() : void
    {
        $widget = $this->createWidget();
        $uxon = new UxonObject(['focus_css_query' => ' .sapFDynamicPageHeader ']);
        $story = new TourStoryStep(new Tour($widget, new UxonObject()), $uxon);
        $waypoint = new TourWaypointStep($widget, $uxon);

        self::assertSame('.sapFDynamicPageHeader', $story->getFocusCssQuery());
        self::assertSame('.sapFDynamicPageHeader', $waypoint->getFocusCssQuery());
    }

    /**
     * Missing or blank queries keep the previous whole-widget target expression.
     */
    public function testMissingAndBlankQueriesKeepWholeWidgetFocus() : void
    {
        $widget = $this->createWidget();
        foreach ([[], ['focus_css_query' => ''], ['focus_css_query' => " \t\n "]] as $uxon) {
            $step = new TourWaypointStep($widget, new UxonObject($uxon));
            self::assertNull($step->getFocusCssQuery());
            self::assertSame('getWidgetWrapper()', $this->createDriver()->buildJsStepElementGetter($step));
        }
    }

    /**
     * Native querySelector selects the first descendant and falls back to its root.
     */
    public function testQueryUsesScopedFirstMatchWithWidgetFallback() : void
    {
        $step = new TourWaypointStep($this->createWidget(), new UxonObject([
            'focus_css_query' => '.target'
        ]));

        self::assertSame(
            '((element) => element ? (element.querySelector(".target") || element) : null)(getWidgetWrapper())',
            $this->createDriver()->buildJsStepElementGetter($step)
        );
    }

    /**
     * Quotes and backslashes remain a CSS string, not executable JavaScript.
     */
    public function testQueryIsEscapedAsJavaScriptString() : void
    {
        $query = '[data-label="a\\\\b"]';
        $step = new TourWaypointStep($this->createWidget(), new UxonObject([
            'focus_css_query' => $query
        ]));

        self::assertSame(
            '((element) => element ? (element.querySelector('
                . json_encode($query, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                . ') || element) : null)(getWidgetWrapper())',
            $this->createDriver()->buildJsStepElementGetter($step)
        );
    }

    /**
     * A CSS query does not turn a centered story step into a global DOM search.
     */
    public function testStoryWithoutWidgetRemainsCentered() : void
    {
        $widget = $this->createWidget();
        $step = new TourStoryStep(new Tour($widget, new UxonObject()), new UxonObject([
            'focus_css_query' => '.target'
        ]));

        self::assertSame('null', $this->createDriver()->buildJsStepElementGetter($step));
    }

    /**
     * Creates a widget boundary backed by a real, unstarted Workbench.
     *
     * @return DataTable
     */
    private function createWidget() : DataTable
    {
        $workbench = new Workbench([
            'SERVER.INSTALLATION_NAME' => 'TourFocusCssQueryTest',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
        $widget = $this->getMockBuilder(DataTable::class)->disableOriginalConstructor()
            ->onlyMethods(['getWorkbench'])->getMock();
        $widget->method('getWorkbench')->willReturn($workbench);
        return $widget;
    }

    /**
     * Replaces only the facade rendering boundary while retaining the real driver.
     *
     * @return DriverJsTourDriver
     */
    private function createDriver() : DriverJsTourDriver
    {
        $element = $this->createMock(AjaxFacadeElementInterface::class);
        $element->method('buildJsDomElementGetter')->willReturn('getWidgetWrapper()');
        $facade = $this->getMockBuilder(AbstractAjaxFacade::class)->disableOriginalConstructor()
            ->onlyMethods(['getElement', 'buildResponseData', 'getPageTemplateFilePathDefault'])->getMock();
        $facade->method('getElement')->willReturn($element);
        return new DriverJsTourDriver($facade);
    }
}
