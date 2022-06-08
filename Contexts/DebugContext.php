<?php
namespace exface\Core\Contexts;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\Contexts\AbstractContext;
use exface\Core\CommonLogic\Constants\Colors;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Container;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Actions\ShowContextPopup;
use exface\Core\Actions\ContextApi;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Events\Action\OnBeforeActionPerformedEvent;
use exface\Core\CommonLogic\Tracer;
use exface\Core\Events\Action\OnActionPerformedEvent;
use exface\Core\Interfaces\Events\ActionEventInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\DataTypes\WidgetVisibilityDataType;

/**
 * This context offers usefull debugging tools right in the GUI.
 * 
 * It's main feature is tracing: while it's enabled, the profiler is run for
 * every request and trace log files are created. These can be opened right
 * from the context popup. Thus, you can easily see which data source queries
 * a page produces, how long they take, etc.
 * 
 * NOTE: tracing produces a lot of files and causes performance overhead, so
 * don't leave it on for long!
 *
 * @author Andrej Kabachnik
 *        
 */
class DebugContext extends AbstractContext
{
    const OPERATION_START_DEBUGGING = 'startDebugging';
    
    const OPERATION_STOP_DEBUGGING = 'stopDebugging';
    
    private $is_debugging = false;
    
    private $tracer = null;
    
    /**
     * Returns TRUE if the debugger is active and FALSE otherwise
     * 
     * @return boolean
     */
    public function isDebugging()
    {
        return $this->is_debugging || ($this->tracer !== null && ! $this->tracer->isDisabled());
    }
    
    /**
     * Starts the debugger for the current context scope
     * 
     * @return DebugContext
     */
    public function startDebugging()
    {
        $this->is_debugging = true;
        $this->getWorkbench()->getConfig()->setOption('DEBUG.TRACE', true, AppInterface::CONFIG_SCOPE_SYSTEM);
        $this->excludeDebugContextFromTrace();
        return $this;
    }
    
    /**
     * @return void
     */
    protected function excludeDebugContextFromTrace()
    {
        // Make sure the tracer is disabled for all actions dealing with this context, so
        // we don't caption stop-tracing and debug-menu actions.
        $this->getWorkbench()->eventManager()->addListener(OnBeforeActionPerformedEvent::getEventName(), array(
            $this,
            'onContextActionDisableTracer'
        ));
        $this->getWorkbench()->eventManager()->addListener(OnActionPerformedEvent::getEventName(), array(
            $this,
            'onContextActionDisableTracer'
        ));
    }
    
    /**
     * 
     * @param ActionEventInterface $e
     */
    public function onContextActionDisableTracer(ActionEventInterface $e)
    {
        $action = $e->getAction();
        if ((($action instanceof ShowContextPopup) && $action->getContext() === $this)
        || $action instanceof ContextApi && $action->getContext() === $this){
            if (null !== $tracer = $this->getTracer()) {
                $tracer->disable();
            }
        }
    }
    
    /**
     * Stops the debugger for the current context scope
     * 
     * @return \exface\Core\Contexts\DebugContext
     */
    public function stopDebugging()
    {
        $this->is_debugging = false;
        $this->getWorkbench()->getConfig()->setOption('DEBUG.TRACE', false, AppInterface::CONFIG_SCOPE_SYSTEM);
        return $this;
    }

    /**
     * The favorites context resides in the user scope.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Contexts\ObjectBasketContext::getDefaultScope()
     */
    public function getDefaultScope()
    {
        return $this->getWorkbench()->getContext()->getScopeWindow();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIcon()
     */
    public function getIcon()
    {
        return Icons::BUG;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getName()
     */
    public function getName()
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.DEBUG.NAME');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        if ($this->isDebugging()){
            $uxon->setProperty('debugging', true);
        } else {
            $uxon->unsetProperty('debugging');
        }
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon){
        if ($uxon->hasProperty('debugging')){
            $this->is_debugging = $uxon->getProperty('debugging');
            if ($this->is_debugging === true) {
                $this->excludeDebugContextFromTrace();
            }
        }
        return;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIndicator()
     */
    public function getIndicator()
    {
        if ($this->isDebugging()){
            return 'ON';
        }
        return 'OFF';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getColor()
     */
    public function getColor()
    {
        if ($this->isDebugging()){
            return Colors::RED;
        }
        return Colors::DEFAULT_COLOR;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getVisibility()
     */
    public function getVisibility(){
        if ($this->isDebugging()){
            return ContextInterface::CONTEXT_BAR_EMPHASIZED;
        }
        return parent::getVisibility();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getContextBarPopup()
     */
    public function getContextBarPopup(Container $container)
    {
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $menu = WidgetFactory::createFromUxonInParent($container, new UxonObject([
            'widget_type' => 'Menu',
            'caption' => $this->getName(),
            'buttons' => [
                [
                    'caption' => $translator->translate('CONTEXT.DEBUG.START'),
                    'action' => [
                        'alias' => 'exface.Core.ContextApi',
                        'context_scope' => $this->getScope()->getName(),
                        'context_alias' => $this->getAliasWithNamespace(),
                        'operation' => static::OPERATION_START_DEBUGGING,
                        'icon' => Icons::BUG
                    ]
                ], [
                    'caption' => $translator->translate('CONTEXT.DEBUG.STOP'),
                    'visibility' => ($this->isDebugging() ? WidgetVisibilityDataType::PROMOTED : WidgetVisibilityDataType::NORMAL),
                    'action' => [
                        'alias' => 'exface.Core.ContextApi',
                        'context_scope' => $this->getScope()->getName(),
                        'context_alias' => $this->getAliasWithNamespace(),
                        'operation' => static::OPERATION_STOP_DEBUGGING,
                        'icon' => Icons::PAUSE
                    ]
                ], [
                    'caption' => $translator->translate('CONTEXT.DEBUG.TRACES_LIST_BUTTON'),
                    'action' => [
                        'alias' => 'exface.Core.ShowDialog',
                        'dialog' => [
                            'maximized' => false,
                            'widgets' => [
                                [
                                    'widget_type' => 'DataTableResponsive',
                                    'object_alias' => 'exface.Core.TRACE_LOG',
                                    'caption' => $translator->translate('CONTEXT.DEBUG.TRACES_LIST_CAPTION'),
                                    'multi_select' => true,
                                    'filters' => [
                                        [
                                            'attribute_alias' => 'NAME'
                                        ]
                                    ],
                                    'columns' => [
                                        [
                                            'attribute_alias' => 'NAME',
                                            'caption' => $translator->translate('CONTEXT.DEBUG.TRACE_NAME')
                                        ],
                                        [
                                            'attribute_alias' => 'URL'
                                        ],
                                        [
                                            'attribute_alias' => 'ACTION'
                                        ],
                                        [
                                            'attribute_alias' => 'PAGE'
                                        ]
                                    ],
                                    'sorters' => [
                                        [
                                            'attribute_alias' => 'NAME',
                                            'direction' => SortingDirectionsDataType::DESC
                                        ]
                                    ],
                                    'buttons' => [
                                        [
                                            'action' => [
                                                'alias' => 'exface.Core.ShowObjectInfoDialog',
                                                'disable_buttons' => false
                                            ],
                                            'bind_to_double_click' => true
                                        ], [
                                            'action_alias' => 'exface.Core.DeleteObject'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ], [
                    'action_alias' => 'exface.Core.ClearCache'
                ]
            ]
        ]));
        
        $container->addWidget($menu);
        return $container;
    }
    
    /**
     * 
     * @param Tracer $tracer
     * @return DebugContext
     */
    public function setTracer(Tracer $tracer) : DebugContext
    {
        $this->tracer = $tracer;
        return $this;
    }
    
    /**
     * 
     * @return Tracer|NULL
     */
    protected function getTracer() : ?Tracer
    {
        return $this->tracer;
    }
}