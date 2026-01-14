<?php
namespace exface\Core\Contexts;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\Contexts\AbstractContext;
use exface\Core\CommonLogic\Constants\Colors;
use exface\Core\CommonLogic\Contexts\Scopes\InstallationContextScope;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Container;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Actions\ShowContextPopup;
use exface\Core\Actions\CallContext;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Events\Action\OnBeforeActionPerformedEvent;
use exface\Core\CommonLogic\Tracer;
use exface\Core\Events\Action\OnActionPerformedEvent;
use exface\Core\Interfaces\Events\ActionEventInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\CommonLogic\Debugger\CommunicationInterceptor;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;

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
    const OPERATION_START_TRACING = 'startTracing';
    const OPERATION_STOP_TRACING = 'stopTracing';
    const OPERATION_START_INTERCEPTING = 'startInterceptingCommunication';
    const OPERATION_STOP_INTERCEPTING = 'stopInterceptingCommunication';
    const OPERATION_START_TRACING_JS = 'startTracingJs';
    const OPERATION_STOP_TRACING_JS = 'stopTracingJs';
    const OPERATION_START_SHOW_HIDDEN = 'startShowingHidden';
    const OPERATION_STOP_SHOW_HIDDEN = 'stopShowingHidden';
    const OPERATION_START_MAINTENANCE = 'startMaintenance';
    const OPERATION_STOP_MAINTENANCE = 'stopMaintenance';
    
    const CFG_DEBUG_TRACE = 'DEBUG.TRACE';
    const CFG_MAINTENANCE_MODE = 'DEBUG.MAINTENANCE_MODE';
    
    const VAR_TRACE_STARTED = 'trace_started';
    const VAR_SHOW_HIDDEN = 'DEBUG.SHOW_HIDDEN';
    
    private $intercepting = false;
    
    private $tracer = null;
    
    private $interceptor = null;
    
    /**
     * @deprecated use startTracing() instead
     * 
     * @return DebugContext
     */
    public function startDebugging()
    {
        $this->startTracing();
        return $this;
    }
    
    /**
     * @deprecated use stopTracing() instead
     *
     * @return DebugContext
     */
    public function stopDebugging()
    {
        $this->stopTracing();
        return $this;
    }
    
    /**
     * Returns TRUE if the debugger is active and FALSE otherwise
     * 
     * @return boolean
     */
    public function isTracing() : bool
    {
        if($this->getWorkbench()->isStarted()) {
            $config = $this->getWorkbench()->getConfig();
            return $config->hasOption(self::CFG_DEBUG_TRACE) && 
                $config->getOption(self::CFG_DEBUG_TRACE);
        }
        
        return false;
    }

    /**
     * Starts the tracer for the current context scope
     *
     * @uxon-operation startTracing
     *
     * @param Tracer|null $tracer
     * @return string
     */
    public function startTracing(Tracer $tracer = null) : string
    {
        $this->tracer = $tracer;
        $config = $this->getWorkbench()->getConfig();
        
        if ($config->getOption(self::CFG_DEBUG_TRACE) === false) {
            $this->getScopeInstallation()->setVariable(
                self::VAR_TRACE_STARTED,
                time()
            );
            
            $config->setOption(
                self::CFG_DEBUG_TRACE,
                true,
                AppInterface::CONFIG_SCOPE_SYSTEM
            );
        }
        
        $this->excludeDebugContextFromTrace();
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.DEBUG.TRACE_STARTED');
    }
    
    /**
     * Stops the tracer for the current context scope
     * 
     * @uxon-operation stopTracing
     *
     * @return string
     */
    public function stopTracing() : string
    {
        $this->getScopeInstallation()->setVariable(
            self::VAR_TRACE_STARTED,
            null
        );
        
        $this->getWorkbench()->getConfig()->setOption(
            self::CFG_DEBUG_TRACE,
            false,
            AppInterface::CONFIG_SCOPE_SYSTEM
        );
        
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.DEBUG.TRACE_STOPPED');
    }

    /**
     * Returns TRUE if the debugger is active and FALSE otherwise
     *
     * @return boolean
     */
    public function isMaintenance() : bool
    {
        if($this->getWorkbench()->isStarted()) {
            $config = $this->getWorkbench()->getConfig();
            return $config->getOption(self::CFG_MAINTENANCE_MODE);
        }

        return false;
    }

    /**
     * Starts the tracer for the current context scope
     *
     * @uxon-operation startTracing
     *
     * @param Tracer|null $tracer
     * @return string
     */
    public function startMaintenance(Tracer $tracer = null) : string
    {
        $config = $this->getWorkbench()->getConfig();

        if ($config->getOption(self::CFG_MAINTENANCE_MODE) === false) {
            $config->setOption(
                self::CFG_MAINTENANCE_MODE,
                true,
                AppInterface::CONFIG_SCOPE_SYSTEM
            );
        }
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.DEBUG.MAINTENANCE_STARTED');
    }

    /**
     * Stops the tracer for the current context scope
     *
     * @uxon-operation stopTracing
     *
     * @return string
     */
    public function stopMaintenance() : string
    {        
        $this->getWorkbench()->getConfig()->setOption(
            self::CFG_MAINTENANCE_MODE,
            false,
            AppInterface::CONFIG_SCOPE_SYSTEM
        );

        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.DEBUG.MAINTENANCE_STOPPED');
    }
    
    /**
     * Starts the front-end tracer for the current context scope
     * 
     * @uxon-operation startTracingJs
     *
     * @return string
     */
    public function startTracingJs() : string
    {
        $this->getScope()->setVariable('DEBUG.TRACE_JS', true);
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.DEBUG.TRACE_JS_STARTED');
    }
    
    /**
     * Stops the front-end tracer for the current context scope
     * 
     * @uxon-operation stopTracingJs
     *
     * @return string
     */
    public function stopTracingJs() : string
    {
        $this->getScope()->unsetVariable('DEBUG.TRACE_JS');
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.DEBUG.TRACE_JS_STOPPED');
    }

    /**
     * Starts the front-end tracer for the current context scope
     *
     * @uxon-operation startTracingJs
     *
     * @return string
     */
    public function startShowingHidden() : string
    {
        $this->getWorkbench()->getContext()->getScopeUser()->setVariable(self::VAR_SHOW_HIDDEN, true);
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.DEBUG.SHOW_HIDDEN_STARTED');
    }

    /**
     * Stops the front-end tracer for the current context scope
     *
     * @uxon-operation stopTracingJs
     *
     * @return string
     */
    public function stopShowingHidden() : string
    {
        $this->getWorkbench()->getContext()->getScopeUser()->unsetVariable(self::VAR_SHOW_HIDDEN);
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.DEBUG.SHOW_HIDDEN_STOPPED');
    }

    public function isTracingJs() : bool
    {
        return $this->getScope()->getVariable('DEBUG.TRACE_JS') === true;
    }
    
    /**
     * 
     * @return bool
     */
    public function isInterceptingCommunication() : bool
    {
        return $this->intercepting;
    }
    
    /**
     * Starts the communication intercepter for the current context scope
     *
     * @uxon-operation startInterceptingCommunication
     * 
     * @return string
     */
    public function startInterceptingCommunication(CommunicationInterceptor $interceptor = null) : string
    {
        $this->intercepting = true;
        $this->interceptor = $interceptor;
        $config = $this->getWorkbench()->getConfig();
        if ($config->getOption('DEBUG.INTERCEPT_COMMUNICATION') === false) {
            $this->getWorkbench()->getConfig()->setOption('DEBUG.INTERCEPT_COMMUNICATION', true, AppInterface::CONFIG_SCOPE_SYSTEM);
        }
        $recipients = $config->getOption('DEBUG.INTERCEPT_AND_SEND_TO_USERS');
        $recipients .= ($recipients && $config->getOption('DEBUG.INTERCEPT_AND_SEND_TO_USER_ROLES') ? ',' : '') . $config->getOption('DEBUG.INTERCEPT_AND_SEND_TO_USER_ROLES');
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.DEBUG.INTERCEPT_STARTED', ['%recipients%' => $recipients]);
    }
    
    /**
     * Stops intercepting communication
     * 
     * @uxon-operation stopInterceptingCommunication
     * 
     * @return string
     */
    public function stopInterceptingCommunication() : string
    {
        $this->intercepting = false;
        $this->getWorkbench()->getConfig()->setOption('DEBUG.INTERCEPT_COMMUNICATION', false, AppInterface::CONFIG_SCOPE_SYSTEM);
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.DEBUG.INTERCEPT_STOPPED');
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
        || $action instanceof CallContext && $action->getContext() === $this){
            if (null !== $tracer = $this->getTracer()) {
                $tracer->disable();
            }
        }
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
    public function getIcon() : ?string
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
        if ($this->isTracing()){
            $uxon->setProperty('tracing', true);
        } else {
            $uxon->unsetProperty('tracing');
        }
        if ($this->isInterceptingCommunication()){
            $uxon->setProperty('intercepting', true);
        } else {
            $uxon->unsetProperty('intercepting');
        }
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon){
        if ($uxon->hasProperty('intercepting')) {
            $this->intercepting = $uxon->getProperty('intercepting');
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIndicator()
     */
    public function getIndicator()
    {
        $state = ($this->isTracing() ? 'T' : '') 
                . ($this->isTracingJs() ? 'F' : '')
                . ($this->isInterceptingCommunication() ? 'C' : ''); 
        return $state ? 'ON [' . $state . ']' : 'OFF';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getColor()
     */
    public function getColor()
    {
        if ($this->isTracing()){
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
        if ($this->isTracing()){
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
        $isTracing = $this->isTracing();
        $isShowingHidden = $this->getWorkbench()->getContext()->getScopeUser()->getVariable(self::VAR_SHOW_HIDDEN);
        $isMaintenanceMode = $this->isMaintenance();
        
        $menu = WidgetFactory::createFromUxonInParent($container, new UxonObject([
            'widget_type' => 'Menu',
            'caption' => $this->getName(),
            'buttons' => [
                // Trace server requests
                [
                    'caption' => '[T] ' . $translator->translate('CONTEXT.DEBUG.TRACE_SERVER_REQUESTS'),
                    'action' => [
                        'alias' => 'exface.Core.CallContext',
                        'context_scope' => $this->getScope()->getName(),
                        'context_alias' => $this->getAliasWithNamespace(),
                        'operation' => $isTracing ? self::OPERATION_STOP_TRACING : self::OPERATION_START_TRACING,
                        'icon' => $isTracing ? icons::TOGGLE_ON : Icons::TOGGLE_OFF
                    ]
                ],
                // Trace front-end errors
                [
                    'caption' => '[F] ' . $translator->translate('CONTEXT.DEBUG.TRACE_JS'),
                    'id' => 'DebugContext_JSTrace_toggle',
                    'action' => [
                        'alias' => 'exface.Core.CallContext',
                        'context_scope' => $this->getScope()->getName(),
                        'context_alias' => $this->getAliasWithNamespace(),
                        'operation' => $this->isTracingJs() ? self::OPERATION_STOP_TRACING_JS : self::OPERATION_START_TRACING_JS,
                        'icon' => $this->isTracingJs() ? icons::TOGGLE_ON : Icons::TOGGLE_OFF
                    ]
                ], 
                // Intercept communication
                [
                    'caption' => '[C] ' . $translator->translate('CONTEXT.DEBUG.INTERCEPT_COMMUNICATION'),
                    'icon' => $this->isInterceptingCommunication() ? icons::TOGGLE_ON : Icons::TOGGLE_OFF,
                    'action' => ($this->isInterceptingCommunication() ? 
                        [
                            'alias' => 'exface.Core.CallContext',
                            'context_scope' => $this->getScope()->getName(),
                            'context_alias' => $this->getAliasWithNamespace(),
                            'operation' => self::OPERATION_STOP_INTERCEPTING,
                            'icon' => $this->isInterceptingCommunication() ? icons::TOGGLE_ON : Icons::TOGGLE_OFF
                        ] : 
                        [
                            'alias' => 'exface.Core.ShowDialog',
                            'icon' => $this->isInterceptingCommunication() ? icons::TOGGLE_ON : Icons::TOGGLE_OFF,
                            'dialog' => [
                                'icon' => Icons::ENVELOPE_OPEN_O,
                                'height' => 'auto',
                                'width' => 1,
                                'widgets' => [
                                    [
                                        'widget_type' => 'Message',
                                        'type' => 'info',
                                        'text' => $translator->translate('CONTEXT.DEBUG.INTERCEPT_COMMUNICATION_HINT')
                                    ],
                                    [
                                        'widget_type' => 'Input',
                                        'caption' => 'Send to users (,)',
                                        'data_column_name' => '_intercept_to_users',
                                        'value' => $this->getWorkbench()->getConfig()->getOption('DEBUG.INTERCEPT_AND_SEND_TO_USERS')
                                    ],
                                    [
                                        'widget_type' => 'Input',
                                        'caption' => 'Send to roles (,)',
                                        'data_column_name' => '_intercept_to_roles',
                                        'value' => $this->getWorkbench()->getConfig()->getOption('DEBUG.INTERCEPT_AND_SEND_TO_USER_ROLES')
                                    ]
                                ],
                                'buttons' => [
                                    [
                                        'caption' => $translator->translate('CONTEXT.DEBUG.INTERCEPT_COMMUNICATION'),
                                        'visibility' => WidgetVisibilityDataType::PROMOTED,
                                        'align' => EXF_ALIGN_OPPOSITE,
                                        'icon' => Icons::CHECK,
                                        'action' => [
                                            'alias' => 'exface.Core.CallContext',
                                            'context_scope' => $this->getScope()->getName(),
                                            'context_alias' => $this->getAliasWithNamespace(),
                                            'operation' => self::OPERATION_START_INTERCEPTING
                                        ]
                                        
                                    ]
                                ]
                            ]
                        ]
                    )
                ], 
                // View server traces
                [
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
                                    'width' => 3,
                                    'filters' => [
                                        [
                                            'attribute_alias' => 'ACTION'
                                        ],
                                        [
                                            'attribute_alias' => 'PAGE'
                                        ],
                                        [
                                            'attribute_alias' => 'URL'
                                        ],
                                        [
                                            'attribute_alias' => 'USER',
                                            'value' => $this->getWorkbench()->getSecurity()->getAuthenticatedToken()->getUsername()
                                        ]
                                    ],
                                    'columns' => [
                                        [
                                            'attribute_alias' => 'NAME',
                                            'caption' => $translator->translate('CONTEXT.DEBUG.TRACE_NAME')
                                        ],
                                        [
                                            'attribute_alias' => 'ACTION'
                                        ],
                                        [
                                            'attribute_alias' => 'PAGE'
                                        ],
                                        [
                                            'attribute_alias' => 'URL'
                                        ],
                                        [
                                            'attribute_alias' => 'USER'
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
                                            'caption' => 'Open trace',
                                            'action' => [
                                                'alias' => 'exface.Core.ShowObjectInfoDialog',
                                                'disable_buttons' => false
                                            ],
                                            'bind_to_double_click' => true
                                        ], [
                                            'caption' => 'Delete trace',
                                            'action_alias' => 'exface.Core.DeleteObject'
                                        ], [
                                            'caption' => 'Start tracing',
                                            'action' => [
                                                'alias' => 'exface.Core.CallContext',
                                                'context_scope' => $this->getScope()->getName(),
                                                'context_alias' => $this->getAliasWithNamespace(),
                                                'operation' => self::OPERATION_START_TRACING,
                                                'icon' => Icons::TOGGLE_ON
                                            ]
                                        ], [
                                            'caption' => 'Stop tracing',
                                            'refresh_input' => true,
                                            'action' => [
                                                'alias' => 'exface.Core.CallContext',
                                                'context_scope' => $this->getScope()->getName(),
                                                'context_alias' => $this->getAliasWithNamespace(),
                                                'operation' => self::OPERATION_STOP_TRACING,
                                                'icon' => Icons::TOGGLE_OFF
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ], 
                // View front-end traces
                [
                    'caption' => $translator->translate('CONTEXT.DEBUG.TRACES_JS_LIST_BUTTON'),
                    'icon' => Icons::EXTERNAL_LINK,
                    'disabled' => $this->isTracingJs() === false,
                    'action' => [
                        'alias' => 'exface.Core.CustomFacadeScript',
                        'script' => <<<JS
    
    var oEvent = jQuery.Event("debugShowJsTrace");
    $(document).trigger(oEvent);
    if (! oEvent.isDefaultPrevented()) {
        throw {'message': 'This facade does not support front-end tracing'};
    }
JS
                    ]
                ],
                // Soft-delete transparency
                [
                    'caption' => $translator->translate('CONTEXT.DEBUG.SHOW_HIDDEN'),
                    'action' => [
                        'alias' => 'exface.Core.CallContext',
                        'context_scope' => $this->getScope()->getName(),
                        'context_alias' => $this->getAliasWithNamespace(),
                        'operation' => $isShowingHidden ? self::OPERATION_STOP_SHOW_HIDDEN : self::OPERATION_START_SHOW_HIDDEN,
                        'icon' => $isShowingHidden ? icons::TOGGLE_ON : Icons::TOGGLE_OFF
                    ]
                ], 
                // Clear cache
                [
                    'action' => [
                        'alias' => 'exface.Core.ClearCache',
                        'clear_opcache' => true
                    ]
                ], 
                // Clean up expired data
                [
                    'action_alias' => 'exface.Core.CleanUp'
                ], 
                // JS Debugger
                [
                    'caption' => 'JS Debugger',
                    'action' => [
                        'alias' => 'exface.Core.CustomFacadeScript',
                        'script' => <<<JS
(function () { 
    if (eruda === undefined) {
        var script = document.createElement('script'); 
        script.src="vendor/npm-asset/eruda/eruda.js"; 
        document.body.appendChild(script); 
        script.onload = function () { 
            eruda.init();
            eruda.show();
        } 
    } else {
        eruda.show();
    }
})();
JS
                    ]
                ],
                // Maintenance mode - e.g. for updates
                [
                    'caption' => $translator->translate('CONTEXT.DEBUG.MAINTENANCE_MODE'),
                    'hint' => $translator->translate('CONTEXT.DEBUG.MAINTENANCE_MODE_HINT'),
                    'action' => [
                        'alias' => 'exface.Core.CallContext',
                        'context_scope' => $this->getScope()->getName(),
                        'context_alias' => $this->getAliasWithNamespace(),
                        'operation' => $isMaintenanceMode ? self::OPERATION_STOP_MAINTENANCE : self::OPERATION_START_MAINTENANCE,
                        'icon' => $isMaintenanceMode ? icons::TOGGLE_ON : Icons::TOGGLE_OFF
                    ]
                ]
            ]
        ]));
        
        $container->addWidget($menu);
        return $container;
    }
    
    /**
     * 
     * @return Tracer|NULL
     */
    protected function getTracer() : ?Tracer
    {
        return $this->tracer;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::handle()
     */
    public function handle(TaskInterface $task, string $operation = null): ResultInterface
    {
        if ($operation === self::OPERATION_START_INTERCEPTING && $task->hasInputData()) {
            $inputSheet = $task->getInputData();
            if ($col = $inputSheet->getColumns()->get('_intercept_to_users')) {
                $this->getWorkbench()->getConfig()->setOption('DEBUG.INTERCEPT_AND_SEND_TO_USERS', $col->getValue(0), AppInterface::CONFIG_SCOPE_SYSTEM);
            }
            if ($col = $inputSheet->getColumns()->get('_intercept_to_roles')) {
                $this->getWorkbench()->getConfig()->setOption('DEBUG.INTERCEPT_AND_SEND_TO_USER_ROLES', $col->getValue(0), AppInterface::CONFIG_SCOPE_SYSTEM);
            }
        }
        return parent::handle($task, $operation);
    }

    /**
     * @return InstallationContextScope
     */
    protected function getScopeInstallation() : InstallationContextScope
    {
        return $this->getWorkbench()->getContext()->getScopeInstallation();
    }

    /**
     * Get the duration in SECONDS since tracing was last started in this context.
     * Returns FALSE if this context is not currently tracing.
     * 
     * @return float|false
     */
    public function getTraceDuration() : float|false
    {
        $traceStarted = $this->getScopeInstallation()->getVariable(self::VAR_TRACE_STARTED);
        if($traceStarted === null) {
            return false;
        }
        
        return time() - $traceStarted;
    }
}