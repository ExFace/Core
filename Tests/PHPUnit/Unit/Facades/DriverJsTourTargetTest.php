<?php

namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\AjaxFacadeElementInterface;
use exface\Core\Facades\AbstractAjaxFacade\Tours\DriverJsTourDriver;
use exface\Core\Interfaces\Tours\TourInterface;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;
use exface\Core\Widgets\DataTable;
use exface\Core\Widgets\Filter;
use exface\Core\Widgets\Input;
use exface\Core\Widgets\Parts\Tours\TourStoryStep;
use exface\Core\Widgets\Parts\Tours\TourWaypointStep;
use PHPUnit\Framework\TestCase;

/**
 * Covers tour DOM-target resolution without a Workbench or persisted metamodel.
 */
class DriverJsTourTargetTest extends TestCase
{
    /**
     * A story step uses the facade DOM getter, not the inner control ID.
     */
    public function testStoryStepUsesCompleteWidgetDomGetter() : void
    {
        $widget = $this->getMockBuilder(DataTable::class)->disableOriginalConstructor()->getMock();
        $link = $this->createMock(WidgetLinkInterface::class);
        $link->method('getTargetWidget')->willReturn($widget);
        $step = $this->getMockBuilder(TourStoryStep::class)->disableOriginalConstructor()
            ->onlyMethods(['getWidgetToFocus'])->getMock();
        $step->method('getWidgetToFocus')->willReturn($link);

        $element = $this->createMock(AjaxFacadeElementInterface::class);
        $element->expects(self::once())->method('buildJsDomElementGetter')->willReturn('getWidgetWrapper()');
        $element->expects(self::never())->method('getId');
        $facade = $this->getMockBuilder(AbstractAjaxFacade::class)->disableOriginalConstructor()
            ->onlyMethods(['getElement', 'buildResponseData', 'getPageTemplateFilePathDefault'])->getMock();
        $facade->expects(self::once())->method('getElement')->with($widget)->willReturn($element);

        self::assertSame('getWidgetWrapper()', (new DriverJsTourDriver($facade))->buildJsStepElementGetter($step));
    }

    /**
     * A story step without a target retains its centered popover.
     */
    public function testStoryStepWithoutFocusReturnsNull() : void
    {
        $step = new TourStoryStep($this->createMock(TourInterface::class));
        $facade = $this->getMockBuilder(AbstractAjaxFacade::class)->disableOriginalConstructor()
            ->onlyMethods(['getElement', 'buildResponseData', 'getPageTemplateFilePathDefault'])->getMock();
        $facade->expects(self::never())->method('getElement');

        self::assertSame('null', (new DriverJsTourDriver($facade))->buildJsStepElementGetter($step));
    }

    /**
     * Waypoint steps on filters still highlight the rendered input.
     */
    public function testFilterWaypointUsesInputDomGetter() : void
    {
        $input = $this->getMockBuilder(Input::class)->disableOriginalConstructor()->getMock();
        $filter = $this->getMockBuilder(Filter::class)->disableOriginalConstructor()
            ->onlyMethods(['getInputWidget'])->getMock();
        $filter->method('getInputWidget')->willReturn($input);
        $step = $this->getMockBuilder(TourWaypointStep::class)->disableOriginalConstructor()
            ->onlyMethods(['getWidget'])->getMock();
        $step->method('getWidget')->willReturn($filter);
        $element = $this->createMock(AjaxFacadeElementInterface::class);
        $element->method('buildJsDomElementGetter')->willReturn('getFilterInput()');
        $facade = $this->getMockBuilder(AbstractAjaxFacade::class)->disableOriginalConstructor()
            ->onlyMethods(['getElement', 'buildResponseData', 'getPageTemplateFilePathDefault'])->getMock();
        $facade->expects(self::once())->method('getElement')->with($input)->willReturn($element);

        self::assertSame('getFilterInput()', (new DriverJsTourDriver($facade))->buildJsStepElementGetter($step));
    }

    /**
     * A `focus_area` takes precedence over `widget_to_focus`: the facade getter of the area is used and
     * the DOM getter of the area widget serves as fallback. The area is resolved relative to the tour widget.
     */
    public function testFocusAreaTakesPrecedenceOverWidgetToFocus() : void
    {
        $tourWidget = $this->getMockBuilder(DataTable::class)->disableOriginalConstructor()->getMock();
        $areaWidget = $this->getMockBuilder(DataTable::class)->disableOriginalConstructor()->getMock();
        $step = $this->getMockBuilder(TourStoryStep::class)->disableOriginalConstructor()
            ->onlyMethods(['getFocusArea', 'getWidget', 'getWidgetToFocus'])->getMock();
        $step->method('getFocusArea')->willReturn('myTable~column.COL$2');
        $step->method('getWidget')->willReturn($tourWidget);
        $step->expects(self::never())->method('getWidgetToFocus');

        $element = $this->createMock(AjaxFacadeElementInterface::class);
        $element->method('buildJsDomElementGetter')->willReturn('getTableWrapper()');
        $facade = $this->getMockBuilder(AbstractAjaxFacade::class)->disableOriginalConstructor()
            ->onlyMethods(['getElement', 'buildResponseData', 'getPageTemplateFilePathDefault', 'getFocusAreaWidget', 'getFocusAreaSelector', 'buildJsFocusAreaPreparer'])
            ->getMock();
        $facade->method('getFocusAreaWidget')->with('myTable~column.COL$2', $tourWidget)->willReturn($areaWidget);
        $facade->method('getFocusAreaSelector')->with('myTable~column.COL$2', $tourWidget)->willReturn('#myTable td[data-x="1"]');
        $facade->expects(self::once())->method('buildJsFocusAreaPreparer')->with('myTable~column.COL$2', $tourWidget)->willReturn('scrollMyTable();');
        $facade->method('getElement')->with($areaWidget)->willReturn($element);

        $js = (new DriverJsTourDriver($facade))->buildJsStepElementGetter($step);

        self::assertStringContainsString('scrollMyTable();', $js);
        self::assertStringContainsString('document.querySelectorAll(' . json_encode('#myTable td[data-x="1"]') . ')', $js);
        self::assertStringContainsString('return getTableWrapper();', $js);
        // Multiple matches stay clickable through the driver.js overlay
        self::assertStringContainsString("domEl.classList.add('exf-tour-focus-area-target')", $js);
        self::assertStringContainsString('.exf-tour-focus-area.driver-active-element:not(.driver-no-interaction)', $js);
    }

    /**
     * A `focus_css_query` is still applied within the element resolved from the `focus_area`.
     */
    public function testFocusCssQueryIsAppliedWithinFocusArea() : void
    {
        $widget = $this->getMockBuilder(DataTable::class)->disableOriginalConstructor()->getMock();
        $step = $this->getMockBuilder(TourStoryStep::class)->disableOriginalConstructor()
            ->onlyMethods(['getFocusArea', 'getWidget', 'getFocusCssQuery'])->getMock();
        $step->method('getFocusArea')->willReturn('myTable~header');
        $step->method('getWidget')->willReturn($widget);
        $step->method('getFocusCssQuery')->willReturn('.target');

        $element = $this->createMock(AjaxFacadeElementInterface::class);
        $element->method('buildJsDomElementGetter')->willReturn('getTableWrapper()');
        $facade = $this->getMockBuilder(AbstractAjaxFacade::class)->disableOriginalConstructor()
            ->onlyMethods(['getElement', 'buildResponseData', 'getPageTemplateFilePathDefault', 'getFocusAreaWidget', 'getFocusAreaSelector', 'buildJsFocusAreaPreparer'])
            ->getMock();
        $facade->method('getFocusAreaWidget')->willReturn($widget);
        $facade->method('getFocusAreaSelector')->willReturn('#myTable .header');
        $facade->method('buildJsFocusAreaPreparer')->willReturn('');
        $facade->method('getElement')->willReturn($element);

        $js = (new DriverJsTourDriver($facade))->buildJsStepElementGetter($step);

        self::assertMatchesRegularExpression('/^' . preg_quote('((element) => element ? (element.querySelector(".target") || element) : null)(', '/') . '\s*\(function\(\) \{/', $js);
        self::assertStringContainsString('document.querySelectorAll(' . json_encode('#myTable .header') . ')', $js);
    }
}
