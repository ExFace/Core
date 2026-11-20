<?php

namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\App;
use exface\Core\Facades\AbstractAjaxFacade\Tours\DriverJsTourDriver;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Widgets\Input;
use exface\Core\Widgets\Parts\Tours\Tour;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Covers the final-step button label for continued and completed walkthroughs.
 */
class DriverJsTourButtonLabelTest extends TestCase
{
    /**
     * A continuation overrides the step's final-button text with translated "Next".
     */
    public function testContinuationUsesNextInsteadOfDone() : void
    {
        [$js, $nextLabel, $doneLabel] = $this->buildTour(['autostart_tour_id' => 'next-tour']);

        self::assertStringContainsString('doneBtnText: ' . json_encode($nextLabel) . ',', $js);
        self::assertSame(2, substr_count($js, 'doneBtnText:'));
        self::assertStringContainsString("doneBtnText: '" . $doneLabel . "'", $js);
    }

    /**
     * A tour without a continuation retains the normal global final-button label.
     */
    public function testFinalStepWithoutContinuationKeepsDone() : void
    {
        [$js, , $doneLabel] = $this->buildTour([]);

        self::assertSame(1, substr_count($js, 'doneBtnText:'));
        self::assertStringContainsString("doneBtnText: '" . $doneLabel . "'", $js);
    }

    /**
     * Generates a real one-step tour using a real, unstarted Workbench for translations.
     *
     * @param array $nextStepFunction
     * @return array
     */
    private function buildTour(array $nextStepFunction) : array
    {
        $workbench = new Workbench([
            'SERVER.INSTALLATION_NAME' => 'TourButtonLabelTest',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
        $widget = $this->getMockBuilder(Input::class)->disableOriginalConstructor()
            ->onlyMethods(['getWorkbench'])->getMock();
        $widget->method('getWorkbench')->willReturn($workbench);
        $tour = new Tour($widget, new UxonObject([
            'steps' => [
                ['title' => 'Final step', 'on_next_step' => $nextStepFunction]
            ]
        ]));
        $facade = $this->createMock(HttpFacadeInterface::class);
        $facade->method('getWorkbench')->willReturn($workbench);
        $app = $workbench->getCoreApp();
        $translator = $app->getTranslator('de_DE');
        // Supply a real translator explicitly, without resolving the locale from a session.
        (new ReflectionProperty(App::class, 'translator'))->setValue($app, $translator);

        return [
            (new DriverJsTourDriver($facade))->buildJsStartTour($tour),
            $translator->translate('TOUR.STEP.ACTION.NEXT'),
            $translator->translate('TOUR.STEP.ACTION.DONE')
        ];
    }
}
