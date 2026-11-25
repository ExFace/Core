<?php

namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\CommonLogic\Model\App;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\AjaxFacadeElementInterface;
use exface\Core\Facades\AbstractAjaxFacade\Tours\DriverJsTourDriver;
use exface\Core\Widgets\Input;
use exface\Core\Widgets\Parts\Tours\Tour;
use exface\Core\Widgets\Parts\Tours\TourWaypointStep;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Covers the `browser_click` property of `on_next_step`.
 */
class TourBrowserClickTest extends TestCase
{
    public function testImportsValidValuesAndDefaultsToNull() : void
    {
        [, , $widget] = $this->createParts();
        self::assertNull((new TourWaypointStep($widget, new UxonObject()))->getOnNextStep()->getBrowserClick());
        foreach (['left_click', 'right_click', 'double_click'] as $type) {
            $step = new TourWaypointStep($widget, new UxonObject(['on_next_step' => ['browser_click' => $type]]));
            self::assertSame($type, $step->getOnNextStep()->getBrowserClick());
        }
    }

    public function testInvalidValueThrows() : void
    {
        [, , $widget] = $this->createParts();
        $this->expectException(InvalidArgumentException::class);
        (new TourWaypointStep($widget, new UxonObject(['on_next_step' => ['browser_click' => 'middle_click']])))->getOnNextStep();
    }

    public function testGeneratesEventPerClickType() : void
    {
        $expected = [
            'left_click' => '["mousedown","mouseup","click"]',
            'right_click' => '["mousedown","mouseup","contextmenu"]',
            'double_click' => '"click","dblclick"]'
        ];
        foreach ($expected as $type => $needle) {
            $js = $this->buildJs(['browser_click' => $type]);
            self::assertStringContainsString('onNextClick:', $js);
            self::assertStringContainsString($needle, $js);
            self::assertStringNotContainsString('widgetFunction();', $js);
        }
    }

    public function testBrowserClickWinsOverWidgetFunction() : void
    {
        $js = $this->buildJs(['browser_click' => 'left_click', 'widget_function' => 'press']);
        self::assertStringContainsString('["mousedown","mouseup","click"]', $js);
        self::assertStringNotContainsString('widgetFunction();', $js);
    }

    public function testPendingTourIsRegisteredBeforeClick() : void
    {
        $js = $this->buildJs(['browser_click' => 'left_click', 'autostart_tour_id' => 'next']);
        self::assertStringContainsString('targetTourId: "next"', $js);
        self::assertLessThan(strpos($js, 'new MouseEvent(sType,'), strpos($js, 'setPendingTour('));
    }

    public function testSkippedInStandaloneRun() : void
    {
        $js = $this->buildJs(['browser_click' => 'left_click', 'skip_if_standalone' => true], true);
        self::assertStringNotContainsString('onNextClick:', $js);
        self::assertStringNotContainsString('new MouseEvent(', $js);
    }

    /**
     * A normal click includes the mouse-down/up pair and fires only one click sequence.
     */
    public function testLeftClickIncludesMouseSequence() : void
    {
        $js = $this->buildJs(['browser_click' => 'left_click']);

        self::assertStringContainsString("if (!element.matches(':disabled'))", $js);
        self::assertStringContainsString('["mousedown","mouseup","click"].forEach', $js);
        self::assertStringContainsString("button: 0, buttons: sType === 'mousedown' ? 1 : 0", $js);
        self::assertStringContainsString("if (sType === 'mousedown') iClick++;", $js);
        self::assertStringNotContainsString('dblclick', $js);
    }

    /**
     * Right clicks use the right mouse button without triggering a normal button action.
     */
    public function testRightClickDoesNotGenerateLeftClick() : void
    {
        $js = $this->buildJs(['browser_click' => 'right_click']);

        self::assertStringContainsString('["mousedown","mouseup","contextmenu"].forEach', $js);
        self::assertStringContainsString("button: 2, buttons: sType === 'mousedown' ? 2 : 0", $js);
        self::assertStringNotContainsString('"click"', $js);
        self::assertStringNotContainsString('dblclick', $js);
    }

    /**
     * Double clicks emit two complete left-click sequences before the double-click event.
     */
    public function testDoubleClickIncludesBothClickSequences() : void
    {
        $js = $this->buildJs(['browser_click' => 'double_click']);

        self::assertStringContainsString('["mousedown","mouseup","click","mousedown","mouseup","click","dblclick"].forEach', $js);
        self::assertStringContainsString("button: 0, buttons: sType === 'mousedown' ? 1 : 0", $js);
        self::assertLessThan(strpos($js, 'driver.moveNext()'), strpos($js, 'dblclick'));
    }

    private function buildJs(array $onNextStep, bool $standalone = false) : string
    {
        [$driver, $tour] = $this->createParts($onNextStep);
        return $driver->buildJsStartTour($tour, $standalone);
    }

    private function createParts(array $onNextStep = []) : array
    {
        $workbench = new Workbench([
            'SERVER.INSTALLATION_NAME' => 'TourBrowserClickTest',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
        $app = $workbench->getCoreApp();
        (new ReflectionProperty(App::class, 'translator'))->setValue($app, $app->getTranslator('de_DE'));
        $widget = $this->getMockBuilder(Input::class)->disableOriginalConstructor()
            ->onlyMethods(['getWorkbench'])->getMock();
        $widget->method('getWorkbench')->willReturn($workbench);
        $tour = new Tour($widget, new UxonObject(['title' => 'Tour', 'steps' => []]));
        $element = $this->createMock(AjaxFacadeElementInterface::class);
        $element->method('buildJsDomElementGetter')->willReturn('getHost()');
        $element->method('buildJsCallFunction')->willReturn('widgetFunction();');
        $facade = $this->getMockBuilder(AbstractAjaxFacade::class)->disableOriginalConstructor()
            ->onlyMethods(['getElement', 'getWorkbench', 'buildResponseData', 'getPageTemplateFilePathDefault'])
            ->getMock();
        $facade->method('getElement')->willReturn($element);
        $facade->method('getWorkbench')->willReturn($workbench);
        $driver = new DriverJsTourDriver($facade);
        $driver->registerWaypointStep(new TourWaypointStep($widget, new UxonObject([
            'title' => 'Step',
            'on_next_step' => $onNextStep
        ])));
        return [$driver, $tour, $widget];
    }
}
