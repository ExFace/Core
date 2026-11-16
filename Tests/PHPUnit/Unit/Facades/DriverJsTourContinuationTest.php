<?php

namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\AjaxFacadeElementInterface;
use exface\Core\Facades\AbstractAjaxFacade\Tours\DriverJsTourDriver;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Widgets\Button;
use exface\Core\Widgets\Input;
use exface\Core\Widgets\Parts\Tours\TourWaypointStep;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Covers continuation ordering without starting Workbench or loading a metamodel.
 */
class DriverJsTourContinuationTest extends TestCase
{
    /**
     * The pending intent must exist before a widget can open its target dialog.
     */
    public function testPendingIntentPrecedesWidgetFunction() : void
    {
        $js = $this->buildHandler(['widget_function' => 'press', 'autostart_tour_id' => 'dialog-tour']);

        self::assertStringContainsString('targetTourId: "dialog-tour"', $js);
        self::assertLessThan(strpos($js, 'openDialog()'), strpos($js, 'setPendingTour('));
        self::assertLessThan(strpos($js, 'driver.moveNext()'), strpos($js, 'openDialog()'));
    }

    /**
     * Ordinary widget functions do not create a pending intent.
     */
    public function testFunctionWithoutContinuationKeepsNormalFlow() : void
    {
        $js = $this->buildHandler(['widget_function' => 'press']);

        self::assertStringNotContainsString('setPendingTour(', $js);
        self::assertStringContainsString('openDialog()', $js);
        self::assertStringContainsString('driver.moveNext()', $js);
    }

    /**
     * Pressing a button with an action must wait for its success event before moving on.
     */
    public function testActionButtonWaitsForActionPerformed() : void
    {
        $js = $this->buildHandler(['widget_function' => 'press'], true);

        self::assertStringContainsString('actionperformed.exfTourNextStep', $js);
        self::assertLessThan(strpos($js, 'openDialog()'), strpos($js, '.on(sEvent'));
        self::assertGreaterThan(strpos($js, '.on(sEvent'), strrpos($js, 'driver.moveNext()'));
    }

    /**
     * Builds the real handler while substituting only the widget rendering boundary.
     *
     * @param array $configuration
     * @param bool $actionButton
     * @return string
     */
    private function buildHandler(array $configuration, bool $actionButton = false) : string
    {
        $workbench = new Workbench([
            'SERVER.INSTALLATION_NAME' => 'TourContinuationTest',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
        $widget = $this->getMockBuilder($actionButton ? Button::class : Input::class)->disableOriginalConstructor()
            ->onlyMethods($actionButton ? ['getWorkbench', 'hasAction', 'getPage', 'getId'] : ['getWorkbench'])->getMock();
        $widget->method('getWorkbench')->willReturn($workbench);
        if ($actionButton) {
            $page = $this->createMock(UiPageInterface::class);
            $page->method('getAliasWithNamespace')->willReturn('test.Page');
            $widget->method('hasAction')->willReturn(true);
            $widget->method('getPage')->willReturn($page);
            $widget->method('getId')->willReturn('TestButton');
        }
        $step = new TourWaypointStep($widget, new UxonObject(['on_next_step' => $configuration]));
        $element = $this->createMock(AjaxFacadeElementInterface::class);
        $element->method('buildJsCallFunction')->with('press')->willReturn('openDialog();');
        $facade = $this->getMockBuilder(AbstractAjaxFacade::class)->disableOriginalConstructor()
            ->onlyMethods(['getElement', 'buildResponseData', 'getPageTemplateFilePathDefault'])->getMock();
        $facade->method('getElement')->with($widget)->willReturn($element);
        $method = new ReflectionMethod(DriverJsTourDriver::class, 'buildJsOnNextClick');
        return $method->invoke(new DriverJsTourDriver($facade), $step);
    }
}
