<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Tours;

use exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\AjaxFacadeElementTourSubrouteInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Widgets\Button;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Interfaces\Tours\TourDriverInterface;
use exface\Core\Interfaces\Tours\TourInterface;
use exface\Core\Interfaces\Tours\TourStepInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Filter;
use exface\Core\Widgets\Parts\Tours\TourStoryStep;
use exface\Core\Widgets\Parts\Tours\TourSubrouteStep;

/**
 * This class is a tour driver that uses the driver.js library to create interactive tours on the UI.
 * 
 * The definition of the tour is provided by the Tour.php class 
 * and all the corresponding steps are provided by the TourStep.php class.
 * 
 * @author: Sergej Riel
 */
class DriverJsTourDriver implements TourDriverInterface
{
    private HttpFacadeInterface $facade;
    private array $waypointSteps = [];
    
    public function __construct(HttpFacadeInterface $httpFacade)
    {
        $this->facade = $httpFacade;
    }

    /**
     * {@inheritDoc}
     * @see TourDriverInterface::getFacade()
     */
    public function getFacade(): HttpFacadeInterface
    {
        return $this->facade;
    }

    /**
     * Registers a tour waypoint step.
     * 
     * {@inheritDoc}
     * @see TourDriverInterface::registerWaypointStep()
     */
    public function registerWaypointStep(TourStepInterface $step) : TourDriverInterface
    {
        $this->waypointSteps[] = $step;
        return $this;
    }

    /**
     * Gets the steps (TourWaypointStep and TourStoryStep) for the given tour, filtered by the tour's waypoint route and sorted by their order.
     * 
     * {@inheritDoc}
     * @see TourDriverInterface::getTourSteps()
     */
    public function getTourSteps(TourInterface $tour): array
    {
        $steps = [];
        $tourWaypoints = explode("&", $tour->getWaypointsRoute());
        $takeAllWaypoints = in_array("~all", $tourWaypoints);
        
        // * TourWaypointSteps:
        foreach ($this->waypointSteps as $step) {
            
            // Filter only steps, that have matching waypoints
            $stepWaypoints = $step->getWaypoints();
            
            if ($takeAllWaypoints || !empty(array_intersect($tourWaypoints, $stepWaypoints))) {
                $steps[] = $step;
            }
        }
        
        // Sorts the steps based on the order of the waypoints in the tour's waypoint route
        // and then by their position_in_tour property.
        usort($steps, function ($a, $b) use ($tourWaypoints, $takeAllWaypoints) {
            $aWaypoints = $a->getWaypoints();
            $bWaypoints = $b->getWaypoints();

            if ($takeAllWaypoints) {
                $aGroup = $this->getStepWaypointSortValue($aWaypoints);
                $bGroup = $this->getStepWaypointSortValue($bWaypoints);
            } else {
                $aGroup = $this->getFirstMatchingWaypointIndex($aWaypoints, $tourWaypoints);
                $bGroup = $this->getFirstMatchingWaypointIndex($bWaypoints, $tourWaypoints);
            }

            if ($aGroup !== $bGroup) {
                return $aGroup <=> $bGroup;
            }

            $aOrder = $a->getPositionInTour();
            $bOrder = $b->getPositionInTour();

            if (!$aOrder && !$bOrder) return 0;
            if (!$aOrder) return 1;
            if (!$bOrder) return -1;

            return $aOrder <=> $bOrder;
        });

        // * TourStorySteps:
        // Adds the tour stroy steps after the waypoint steps.
        if (null !== $tourStorySteps = $tour->getTourStorySteps()) {
            $steps = array_merge($steps, $tourStorySteps);
        }
        
        return $this->addSubrouteSteps($steps);
    }

    /**
     * Inserts the steps of tour subroutes right after the steps, that configure them.
     * 
     * The subroute steps are provided by the facade element of the subroute widget because
     * they depend on its rendered structure.
     * 
     * @param TourStepInterface[] $steps
     * @return TourStepInterface[]
     */
    protected function addSubrouteSteps(array $steps) : array
    {
        $result = [];
        foreach ($steps as $step) {
            $result[] = $step;
            if (! $step->hasSubroute()) {
                continue;
            }
            $subroute = $step->getSubroute();
            $element = $this->getFacade()->getElement($subroute->getWidget());
            if ($element instanceof AjaxFacadeElementTourSubrouteInterface) {
                foreach ($element->getTourSubrouteSteps($subroute) as $subrouteStep) {
                    $result[] = $subrouteStep;
                }
            }
        }
        return $result;
    }

    /**
     * Gets the index of the first matching waypoint in the tour's waypoint route for the given step waypoints.
     * 
     * @param array $stepWaypoints
     * @param array $tourWaypoints
     * @return int
     */
    private function getFirstMatchingWaypointIndex(array $stepWaypoints, array $tourWaypoints): int
    {
        foreach ($tourWaypoints as $index => $tourWaypoint) {
            if (in_array($tourWaypoint, $stepWaypoints, true)) {
                return $index;
            }
        }

        return PHP_INT_MAX;
    }

    /**
     * Gets a string value for sorting the steps that have the same waypoints, 
     * by sorting their waypoints and concatenating them.
     * 
     * @param array $stepWaypoints
     * @return string
     */
    private function getStepWaypointSortValue(array $stepWaypoints): string
    {
        sort($stepWaypoints, SORT_NATURAL | SORT_FLAG_CASE);
        return implode('|', $stepWaypoints);
    }

    /**
     * @inheritDoc
     */
    public function getWorkbench()
    {
        return $this->facade->getWorkbench();
    }

    /**
     * Builds the JavaScript code to start the tour using driver.js library, 
     * based on the steps of the given tour.
     * 
     * @param TourInterface $tour
     * @param bool $isStandalone True for a manual menu start; skips actions marked skip_if_standalone.
     * @param string|null $startIndexJs JS expression with the index of the first step to show
     * @return string
     */
    public function buildJsStartTour(TourInterface $tour, bool $isStandalone = false, ?string $startIndexJs = null) : string
    {
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $aStepsJs = '';
        
        $steps = $this->getTourSteps($tour);
        if (empty ($steps))  {
            $aStepsJs .= <<<JS
                {
                    popover: {
                      title: {$this->escapeString('This tour is empty')}
                    }
                },
JS;
        } else {
            foreach ($steps as $step) {
                // A step that continues with another tour is not the end of the user's walkthrough.
                // Skipped actions do not continue, so their final button keeps the "Done" label.
                $onNextStep = $step->getOnNextStep();
                $continuesTour = ! ($isStandalone && $onNextStep->getSkipIfStandalone()) && $onNextStep->getAutostartTourId();
                $continuesTour = $continuesTour || ($step instanceof TourSubrouteStep && $step->isReturningToTour());
                $doneBtnTextJs = $continuesTour
                    ? 'doneBtnText: ' . $this->escapeString($translator->translate('TOUR.STEP.ACTION.NEXT')) . ','
                    : '';
                $aStepsJs .= <<<JS
                {
                    element: () => {$this->buildJsStepElementGetter($step)},
                    // Custom flag (ignored by driver.js): only steps with a widget have an element to wait for
                    exfExpectsElement: {$this->escapeBool($this->getFocusedWidget($step) !== null)},
                    popover: {
                      title: {$this->escapeString($step->getTitle())},
                      description: {$this->escapeString($step->getBody())},
                      side: '{$step->getSide()}',
                      align: '{$step->getAlign()}',
                      {$doneBtnTextJs}
                      {$this->buildJsOnNextClick($step, $isStandalone, $tour)}
                    }
                },
JS;
            }
        }
        $aStepsJs = '[' . $aStepsJs . ']';
        
        $driverJs = <<<JS

            const driverObj = driver.js.driver({
                showProgress: '{$tour->getShowProgress()}',
                disableActiveInteraction: '{$tour->getDisableActiveInteraction()}',
                nextBtnText: '{$translator->translate('TOUR.STEP.ACTION.NEXT')}',
                prevBtnText: '{$translator->translate('TOUR.STEP.ACTION.PREVIOUS')}',
                doneBtnText: '{$translator->translate('TOUR.STEP.ACTION.DONE')}',
                steps: {$aStepsJs}
            });
    
            driverObj.drive({$startIndexJs});
            
JS;

     return $driverJs;
    }

    /**
     * Builds the JavaScript code for the onNextClick event of a tour step, 
     * which will be executed when the user clicks the "Next" button in the popover of that step.
     * 
     * @param TourStepInterface $step
     * @param bool $isStandalone True for a manual menu start.
     * @param TourInterface|null $tour The tour containing the step
     * @return string
     */
    protected function buildJsOnNextClick(TourStepInterface $step, bool $isStandalone = false, ?TourInterface $tour = null) :string
    {
        $onNextStep = $step->getOnNextStep();
        // Skip the entire action, not the step: the default handler still advances or ends the tour.
        if ($isStandalone && $onNextStep->getSkipIfStandalone()) {
            return '';
        }
        $browserClick = $onNextStep->getBrowserClick();
        $functionName = $onNextStep->getWidgetFunction();
        if ($browserClick === null && $functionName === null) {
            return '';
        }
        
        if ($browserClick !== null) {
            $callFunctionJs = $this->buildJsBrowserClick($browserClick);
        } else {
            $callFunctionJs = $this->buildJsWidgetFunctionCall($step, $functionName);
        }
        
        // Register the intent before acting: a cached dialog may open synchronously.
        $pendingDialogTourJs = '';
        $autostartTourId = $onNextStep->getAutostartTourId();
        
        if ($autostartTourId) {
            $pendingDialogTourJs = <<<JS
                window.exfTourContext.setPendingTour({
                    targetTourId: {$this->escapeString($autostartTourId)}
                });
JS;
        }
        
        // Subroutes leaving the tour remember where to continue it. The step returning
        // to the tour turns this return point into a pending tour start.
        $returnPointJs = '';
        $endsTour = (bool) $autostartTourId;
        if ($step instanceof TourSubrouteStep) {
            if ($step->isLeavingTour() && $tour !== null && $tour->getId() !== null) {
                $returnPointJs = <<<JS
                window.exfTourContext.setReturnPoint({
                    tourId: {$this->escapeString($tour->getId())},
                    stepIndex: driver.getActiveIndex() + 1
                });
JS;
            }
            if ($step->isReturningToTour()) {
                $returnPointJs = 'window.exfTourContext.resumeReturnPoint();';
                $endsTour = true;
            }
        }
        
        // If another tour is started, end this one instead of moving on in the background.
        $moveJs = $endsTour ? 'if (element) { driver.destroy(); } else { driver.moveNext(); }' : 'driver.moveNext();';
        
        // Pressing a button with an action may navigate asynchronously (e.g. a wizard step).
        // In this case the tour must not move on right away.
        if ($browserClick === null && ! $endsTour && $this->isPressOfActionButton($step, $functionName)) {
            return $this->buildJsOnNextClickAfterAction($step, $callFunctionJs, $returnPointJs, $pendingDialogTourJs);
        }
        
        return <<<JS

          onNextClick: (element, step, { driver }) => {
              if (element) {
                  {$returnPointJs}
                  {$pendingDialogTourJs}

                  {$callFunctionJs}
              }
              {$moveJs}
          }
JS;
    }

    /**
     * Builds the onNextClick handler for steps, that press a button with an action.
     *
     * Problem: a button like "Next" in a wizard validates its step, performs its action and only then
     * switches to the next wizard step. The tour must not move to the next popover before that happened:
     * the element of the next tour step would still be hidden and driver.js would show the popover
     * in the top left corner of the screen.
     *
     * Solution: the handler works in three phases:
     *
     * 1. Listen for the `actionperformed` event of the pressed button. It is fired only if the action
     * succeeded. If the validation or the action fails, no event comes and the tour stays on this step.
     * 2. Once the event arrives, wait until the element of the next step is visible (the wizard
     * needs a moment to show its next step).
     * 3. Move to the next step. If the element does not show up within 3 seconds, move on anyway.
     *
     * @param TourStepInterface $step
     * @param string $callFunctionJs JS that presses the button
     * @param string $returnPointJs JS to remember or resume the return point of subroute steps
     * @param string $pendingDialogTourJs JS to register a tour to be started in the next dialog
     * @return string
     */
    protected function buildJsOnNextClickAfterAction(
        TourStepInterface $step,
        string $callFunctionJs,
        string $returnPointJs,
        string $pendingDialogTourJs
    ) : string {
        $triggerWidget = $this->getFocusedWidget($step);
        $pageAliasJs = $this->escapeString($triggerWidget->getPage()->getAliasWithNamespace());
        $widgetIdJs = $this->escapeString($triggerWidget->getId());
        $actionperformed = AbstractJqueryElement::EVENT_NAME_ACTIONPERFORMED;

        return <<<JS

          onNextClick: (element, step, { driver }) => {
              // Nothing to press if the step has no element - just continue
              if (! element) {
                  driver.moveNext();
                  return;
              }
              {$returnPointJs}
              {$pendingDialogTourJs}

              var iStepIdx = driver.getActiveIndex();
              // Fixed event namespace: a listener left from a previous click is replaced, not duplicated
              var sEvent = "{$actionperformed}.exfTourNextStep";
              
              // Phase 1: wait for the action of the button to succeed
              $(document).off(sEvent).on(sEvent, function(oEvent, oParams) {
                  // Ignore actions of other buttons
                  if (! oParams || oParams.page_alias !== {$pageAliasJs} || oParams.trigger_widget_id !== {$widgetIdJs}) {
                      return;
                  }
                  $(document).off(sEvent);
                  
                  // Move on only if the user did not close or change the tour in the meantime
                  var fnMove = function() {
                      if (driver.isActive() && driver.getActiveIndex() === iStepIdx) {
                          driver.moveNext();
                      }
                  };
                  
                  // Next step without a widget to focus: there is no element to wait for
                  var oNextStep = driver.getConfig().steps[iStepIdx + 1];
                  if (! oNextStep || ! oNextStep.exfExpectsElement || typeof oNextStep.element !== 'function') {
                      fnMove();
                      return;
                  }
                  
                  // Phase 2: poll every 100 ms until the next element is rendered and visible
                  var iWaited = 0;
                  var iTimer = setInterval(function() {
                      var oNextEl = null;
                      try {
                          oNextEl = oNextStep.element();
                      } catch (e) {
                          // Element is not rendered yet - try again on the next tick
                      }
                      iWaited += 100;
                      // Phase 3: continue once visible or after 3 seconds at the latest
                      if ((oNextEl && oNextEl.getClientRects().length > 0) || iWaited >= 3000) {
                          clearInterval(iTimer);
                          fnMove();
                      }
                  }, 100);
              });

              {$callFunctionJs}
          }
JS;
    }

    /**
     * Returns TRUE if the step presses a button, that performs an action and thus fires the action-performed event
     *
     * @param TourStepInterface $step
     * @param string $functionName
     * @return bool
     */
    protected function isPressOfActionButton(TourStepInterface $step, string $functionName) : bool
    {
        $widget = $this->getFocusedWidget($step);
        return $functionName === Button::FUNCTION_PRESS
            && $widget instanceof iTriggerAction
            && $widget->hasAction();
    }

    /**
     * Builds JS dispatching the mouse event(s) on the highlighted `element` variable.
     *
     * @param string $browserClick
     * @return string
     */
    protected function buildJsBrowserClick(string $browserClick) : string
    {
        // Events in the same order a real browser fires them.
        // UI5 needs mouse-down and mouse-up to recognize a button press.
        $leftClick = ['mousedown', 'mouseup', 'click'];
        $events = [
            'left_click' => $leftClick,
            'right_click' => ['mousedown', 'mouseup', 'contextmenu'],
            'double_click' => array_merge($leftClick, $leftClick, ['dblclick'])
        ][$browserClick];
        $eventsJs = json_encode($events);
        $button = $browserClick === 'right_click' ? 2 : 0;
        $buttons = $browserClick === 'right_click' ? 2 : 1;

        return <<<JS
                  if (!element.matches(':disabled')) {
                      var iClick = 0;
                      {$eventsJs}.forEach(function(sType) {
                          // Every mouse-down starts a new click: detail is the click count.
                          if (sType === 'mousedown') iClick++;
                          element.dispatchEvent(new MouseEvent(sType, {
                              bubbles: true, cancelable: true, view: window,
                              button: {$button}, buttons: sType === 'mousedown' ? {$buttons} : 0,
                              detail: iClick
                          }));
                      });
                  }
JS;
    }

    /**
     * Builds JS calling the given function on the focused widget.
     *
     * @param TourStepInterface $step
     * @param string $functionName
     * @return string
     */
    protected function buildJsWidgetFunctionCall(TourStepInterface $step, string $functionName) : string
    {
        $focusedWidget = $this->getFocusedWidget($step);
        
        try {
            //TODO: If this is in a dialog, it currently gets one step from the called page and tries to click on it.
            // At this case the controller of the called page is not found and we getting an error here.
            // The Quick fix is to catch the missing controller error.
            // A propper Fix must be implemented! For that, start at the point where the "registerWaypointStep()" is called.
            $callFunctionJs = $this->getFacade()?->getElement($focusedWidget)?->buildJsCallFunction($functionName);
        } catch (\Exception $e) {
            $callFunctionJs = 'console.warn(' . $this->escapeString($e->getMessage()) . ');';
        }
        
        return (string) $callFunctionJs;
    }
    
    protected function escapeString($value) : string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param bool $value
     * @return string
     */
    protected function escapeBool(bool $value) : string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * Resolves the first CSS match within the target widget, falling back to the widget.
     *
     * @param TourStepInterface $step
     * @return string
     */
    public function buildJsStepElementGetter(TourStepInterface $step) : string
    {
        $widget = $this->getFocusedWidget($step);
        if ($widget === null) {
            return 'null';
        }
        $elementJs = $this->getFacade()->getElement($widget)->buildJsDomElementGetter();
        $query = $step->getFocusCssQuery();
        if ($query === null) {
            return $elementJs;
        }
        return '((element) => element ? (element.querySelector(' . $this->escapeString($query)
            . ') || element) : null)(' . $elementJs . ')';
    }

    /**
     * This method returns the ID of the DOM element widget that this step will highlight.
     * 
     * The popover for this step will be displayed next to this element.
     * 
     * @param TourStepInterface $step
     * @return string
     */
    public function getStepHighlightedElementId(TourStepInterface $step): string
    {
        // If there is no widget to focus, we return a dummy string,
        // which means that the popover will be displayed in the center of the screen
        // without highlighting any element.
        if ( null === $focusedWidget = $this->getFocusedWidget($step)) {
            return 'exf-tour-step-no-highlight';
        } else {
            return $this->getFacade()->getElement($focusedWidget)->getId();
        }
    }

    /**
     * Returns the widget that should be focused (highlighted) in the given step.
     * 
     * @param TourStepInterface $step
     * @return WidgetInterface|null
     */
    protected function getFocusedWidget(TourStepInterface $step) : ?WidgetInterface
    {
        if ($step instanceof TourStoryStep) {
            $widget = $step->getWidgetToFocus() ? $step->getWidgetToFocus()->getTargetWidget() : null;
        } else {
            $widget = $step->getWidget();
        }

        // Filters will mostly not have any id - they just render their input_widget, so we take that
        if ($widget instanceof Filter) {
            $widget = $widget->getInputWidget();
        }
        return $widget;
    }
}