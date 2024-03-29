<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\Model\UiPage;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Exceptions\Widgets\WidgetLogicError;
use exface\Core\Exceptions\Contexts\ContextAccessDeniedError;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Contexts\UserContext;
use exface\Core\Interfaces\WidgetInterface;

/**
 * The context bar shows information about the current context of the workbench.
 * 
 * Each page always has exactly one context bar. It is a special widget showing
 * icons (buttons) for every active context in the system. In a sense it is
 * similar to the system tray in Windows.
 * 
 * Every context is represented by a button showing a popup. The contents of
 * that popup is defined by the context itself.
 * 
 * The context bar can be customized in the core configuration file via the
 * config option WIDGET.CONTEXTBAR. Just like any other widget, the context bar can
 * be described via UXON - just in the config files instead of each page 
 * separately.
 *
 * @author Andrej Kabachnik
 *        
 */
class ContextBar extends Toolbar
{
    private $context_widget_map = [];
    
    protected function init(){
        parent::init();
        $this->setMetaObject($this->getWorkbench()->model()->getObject('exface.Core.CONTEXT_BASE_OBJECT'));
        return;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::getWidgets()
     */
    public function getWidgets(callable $filter_callback = null)
    {
        if (! parent::hasWidgets()){
            $this->importUxonObject($this->getWorkbench()->getConfig()->getOption('WIDGET.CONTEXTBAR'));
        }
        return parent::getWidgets($filter_callback);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::getChildren()
     */
    public function getChildren() : \Iterator
    {
        foreach ($this->getButtons() as $btn) {
            yield $btn;
        }
    }
    
    /**
     * Defines the contexts to be show in the context bar as an array.
     * 
     * Each context is represented by a UXON object with the following structure:
     *  {
     *      "context_alias": "exface.Core.DebugContext",
     *      "context_scope": "Window",
     *      "visibility": "show_always"
     *  }
     *  
     * The visibility property takes the following options, that customize the
     * appearance of the context button depending on the facade used.
     * 
     * - show_allways - makes the context button always visible
     * - show_if_not_empty - hides the context button if there is no popup content
     * - hide_allways - hides the context button allways
     * - disabled - disables loading of the context into the context bar (this 
     * way default contexts can be disabled)
     * - default - leaves showing/hiding the context upto the facade
     * - emphasized
     * 
     * @uxon-property contexts
     * @uxon-type array
     * 
     * @param UxonObject $context_uxon_objects
     * @return ContextBar
     */
    public function setContexts(UxonObject $context_uxon_objects){
        $userContextInitialized = false;
        $contextManager = $this->getWorkbench()->getContext();
        foreach ($context_uxon_objects as $uxon){
            $visibility = strtolower($uxon->getProperty('visibility'));
            if ($visibility === ContextInterface::CONTEXT_BAR_DISABED){
                continue;
            }
            $uxon->unsetProperty('visibility');
            
            try {
                $context = $contextManager->getScope($uxon->getProperty('context_scope'))->getContext($uxon->getProperty('context_alias'));
                $uxon->unsetProperty('context_scope');
                $uxon->unsetProperty('context_alias');
                if ($visibility) {
                    $context->setVisibility($visibility);
                }
                
                if (! $uxon->isEmpty()) {
                    $context->importUxonObject($uxon);
                }
                
                if ($context instanceof UserContext) {
                    $userContextInitialized = true;
                }
                
                $btn = $this->createButtonForContext($context);
                
                switch ($visibility){
                    case ContextInterface::CONTEXT_BAR_SHOW_IF_NOT_EMPTY:
                        if ($btn->getAction()->getWidget()->countWidgetsVisible() == 0){
                            $btn->setVisibility(EXF_WIDGET_VISIBILITY_OPTIONAL);
                            break;
                        }
                    case ContextInterface::CONTEXT_BAR_EMPHASIZED:
                        $btn->setVisibility(EXF_WIDGET_VISIBILITY_PROMOTED);
                        break;
                    case ContextInterface::CONTEXT_BAR_HIDE_ALLWAYS:
                        $btn->setVisibility(EXF_WIDGET_VISIBILITY_HIDDEN);
                        break;
                    default:
                        if (! defined('\\exface\\Core\\Interfaces\\Contexts\\ContextInterface::CONTEXT_BAR_' . mb_strtoupper($visibility))) {
                            throw new WidgetLogicError($this, 'Invalid context bar visibility "' . $visibility . '" set for context "' . $context->getAlias() . '"');
                        }
                }
                
                $this->addButton($btn);
            } catch (ContextAccessDeniedError $e){
                $this->getWorkbench()->getLogger()->logException($e, LoggerInterface::DEBUG);
            } catch (\Throwable $e){
                $this->getWorkbench()->getLogger()->logException($e);
            }
        }
        
        if ($userContextInitialized === false) {
            $userContext = $contextManager->getScopeUser()->getContext('exface.Core.UserContext');
            $this->addButton($this->createButtonForContext($userContext), 0);
        }
        
        return $this;
    }
    
    /**
     * 
     * @param ContextInterface $context
     * @return \exface\Core\Widgets\Button
     */
    protected function createButtonForContext(ContextInterface $context)
    {   
        /* @var $btn \exface\Core\Widgets\Button */
        $btn = $this->createButton();
        $btnId = $this->getPage()->generateWidgetId($btn, null, false) . str_replace('.', '', $context->getScope()->getName() . ucfirst($context->getAliasWithNamespace()));
        
        $btn
        ->setId($btnId)
        ->setActionAlias('exface.Core.ShowContextPopup')
        ->setHint($context->getName())
        ->setIcon($context->getIcon())
        ->setMetaObject($this->getWorkbench()->model()->getObject('exface.Core.CONTEXT_BASE_OBJECT'));
        
        $btn->getAction()->setContextAlias($context->getAliasWithNamespace());
        $btn->getAction()->setContextScope($context->getScope()->getName());
        
        $this->context_widget_map[$btn->getId()] = $context;
        
        return $btn;
    }
    
    /**
     * 
     * @param Button $button
     * @return ContextInterface|null
     */
    public function getContextForButton(Button $button)
    {
        return $this->context_widget_map[$button->getId()];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Toolbar::getButtonWidgetType()
     */
    public function getButtonWidgetType()
    {
        return 'Button';
    }
    
    /**
     * The input widget of the context bar is the context bar itself.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Button::getInputWidget()
     */
    public function getInputWidget() : WidgetInterface
    {
        return $this;
    }   
}