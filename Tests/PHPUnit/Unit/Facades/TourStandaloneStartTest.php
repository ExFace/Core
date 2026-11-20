<?php

namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\CommonLogic\Model\App;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\AjaxFacadeElementInterface;
use exface\Core\Facades\AbstractAjaxFacade\Tours\DriverJsTourDriver;
use exface\Core\Widgets\Input;
use exface\Core\Widgets\Parts\Tours\Tour;
use exface\Core\Widgets\Parts\Tours\TourWaypointStep;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Covers per-action standalone configuration and per-run continuation behavior.
 */
class TourStandaloneStartTest extends TestCase
{
    /**
     * The UXON property defaults to false and accepts explicit true and false values.
     */
    public function testStandalonePropertyImportsThroughUxon() : void
    {
        $workbench = new Workbench([
            'SERVER.INSTALLATION_NAME' => 'TourStandalonePropertyTest',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
        $widget = $this->getMockBuilder(Input::class)->disableOriginalConstructor()
            ->onlyMethods(['getWorkbench'])->getMock();
        $widget->method('getWorkbench')->willReturn($workbench);
        $default = new TourWaypointStep($widget, new UxonObject());
        self::assertFalse($default->getOnNextStep()->getSkipIfStandalone());
        foreach ([true, false] as $standalone) {
            $step = new TourWaypointStep($widget, new UxonObject([
                'on_next_step' => new UxonObject(['skip_if_standalone' => $standalone])
            ]));
            self::assertSame($standalone, $step->getOnNextStep()->getSkipIfStandalone());
        }
    }

    /**
     * Standalone runs skip a final press leading to another tour and end with "Done".
     */
    public function testStandaloneRunSkipsFinalPressLeadingToFollowUpTour() : void
    {
        [$driver, $tour] = $this->createTour();
        $js = $driver->buildJsStartTour($tour, true);

        self::assertStringNotContainsString('widgetFunction();', $js);
        self::assertStringNotContainsString('onNextClick:', $js);
        self::assertStringNotContainsString('setPendingTour(', $js);
        self::assertSame(1, substr_count($js, 'doneBtnText:'));
        self::assertStringContainsString("doneBtnText: 'Fertig'", $js);
    }

    /**
     * Marked actions on earlier steps are skipped without hiding those steps.
     */
    public function testStandaloneRunSkipsMarkedActionOnEarlierStep() : void
    {
        [$driver, $tour] = $this->createTour('press', 'next-tour', true);
        $js = $driver->buildJsStartTour($tour, true);

        self::assertStringNotContainsString('widgetFunction();', $js);
        self::assertStringNotContainsString('onNextClick:', $js);
        self::assertStringNotContainsString('setPendingTour(', $js);
        self::assertStringContainsString('title: "Close"', $js);
        self::assertStringContainsString('title: "Final step"', $js);
    }

    /**
     * Marked functions are skipped even without a follow-up tour.
     */
    public function testStandaloneRunSkipsMarkedPressWithoutFollowUpTour() : void
    {
        [$driver, $tour] = $this->createTour('press', null);
        $js = $driver->buildJsStartTour($tour, true);

        self::assertStringNotContainsString('widgetFunction();', $js);
        self::assertStringNotContainsString('onNextClick:', $js);
        self::assertStringNotContainsString('setPendingTour(', $js);
    }

    /**
     * Marked functions other than press are also skipped.
     */
    public function testStandaloneRunSkipsOtherMarkedFunctionWithFollowUpTour() : void
    {
        [$driver, $tour] = $this->createTour('refresh');
        $js = $driver->buildJsStartTour($tour, true);

        self::assertStringNotContainsString('widgetFunction();', $js);
        self::assertStringNotContainsString('onNextClick:', $js);
        self::assertStringNotContainsString('setPendingTour(', $js);
    }

    /**
     * Automatic starts and subsequent runs are not affected by a standalone menu run.
     */
    public function testSameTourCanStillContinueAsPartOfWalkthrough() : void
    {
        [$driver, $tour] = $this->createTour();
        $driver->buildJsStartTour($tour, true);
        $js = $driver->buildJsStartTour($tour);

        self::assertStringContainsString('targetTourId: "next-tour"', $js);
        self::assertStringContainsString('widgetFunction();', $js);
        self::assertStringContainsString('doneBtnText: "Weiter"', $js);
        self::assertLessThan(strpos($js, 'widgetFunction();'), strpos($js, 'setPendingTour('));
    }

    /**
     * Omitted and explicit false flags keep widget functions and continuations enabled.
     */
    public function testStandaloneRunKeepsUnmarkedActions() : void
    {
        foreach ([null, false] as $skip) {
            [$driver, $tour] = $this->createTour('press', 'next-tour', false, $skip);
            $js = $driver->buildJsStartTour($tour, true);

            self::assertStringContainsString('widgetFunction();', $js);
            self::assertStringContainsString('targetTourId: "next-tour"', $js);
            self::assertStringContainsString('doneBtnText: "Weiter"', $js);
        }
    }

    /**
     * Skipping one action must not suppress an unmarked action in the same tour.
     */
    public function testStandaloneSkipIsLimitedToItsOwnStep() : void
    {
        [$driver, $tour, $step] = $this->createTour();
        $driver->registerWaypointStep(new TourWaypointStep($step->getWidget(), new UxonObject([
            'title' => 'Continue',
            'on_next_step' => [
                'widget_function' => 'press',
                'autostart_tour_id' => 'unmarked-tour'
            ]
        ])));
        $js = $driver->buildJsStartTour($tour, true);

        self::assertSame(1, substr_count($js, 'widgetFunction();'));
        self::assertStringNotContainsString('targetTourId: "next-tour"', $js);
        self::assertStringContainsString('targetTourId: "unmarked-tour"', $js);
        self::assertStringContainsString('doneBtnText: "Weiter"', $js);
    }

    /**
     * Creates real tour parts and translations, replacing only the facade rendering boundary.
     *
     * @param string $functionName Function called by the configured step.
     * @param string|null $autostartTourId Optional follow-up tour.
     * @param bool $appendFinalStep Add a final step after the configured function.
     * @param bool|null $skipIfStandalone Null omits the property to test its default.
     * @return array
     */
    private function createTour(string $functionName = 'press', ?string $autostartTourId = 'next-tour', bool $appendFinalStep = false, ?bool $skipIfStandalone = true) : array
    {
        $workbench = new Workbench([
            'SERVER.INSTALLATION_NAME' => 'TourStandaloneStartTest',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
        $app = $workbench->getCoreApp();
        (new ReflectionProperty(App::class, 'translator'))->setValue($app, $app->getTranslator('de_DE'));
        $widget = $this->getMockBuilder(Input::class)->disableOriginalConstructor()
            ->onlyMethods(['getWorkbench'])->getMock();
        $widget->method('getWorkbench')->willReturn($workbench);
        $tour = new Tour($widget, new UxonObject([
            'title' => 'Partial tour',
            'steps' => []
        ]));
        $onNextStep = ['widget_function' => $functionName];
        if ($skipIfStandalone !== null) {
            $onNextStep['skip_if_standalone'] = $skipIfStandalone;
        }
        if ($autostartTourId !== null) {
            $onNextStep['autostart_tour_id'] = $autostartTourId;
        }
        $step = new TourWaypointStep($widget, new UxonObject([
            'title' => 'Close',
            'on_next_step' => $onNextStep
        ]));
        $element = $this->createMock(AjaxFacadeElementInterface::class);
        $element->method('buildJsDomElementGetter')->willReturn('getHost()');
        $element->method('buildJsCallFunction')->with($functionName)->willReturn('widgetFunction();');
        $facade = $this->getMockBuilder(AbstractAjaxFacade::class)->disableOriginalConstructor()
            ->onlyMethods(['getElement', 'getWorkbench', 'buildResponseData', 'getPageTemplateFilePathDefault'])
            ->getMock();
        $facade->method('getElement')->with($widget)->willReturn($element);
        $facade->method('getWorkbench')->willReturn($workbench);
        $driver = new DriverJsTourDriver($facade);
        $driver->registerWaypointStep($step);
        if ($appendFinalStep) {
            $driver->registerWaypointStep(new TourWaypointStep($widget, new UxonObject(['title' => 'Final step'])));
        }
        return [$driver, $tour, $step];
    }
}
