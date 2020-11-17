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
    private $is_debugging = false;
    
    private $tracer = null;
    
    /**
     * Returns TRUE if the debugger is active and FALSE otherwise
     * 
     * @return boolean
     */
    public function isDebugging()
    {
        return $this->is_debugging;
    }
    
    /**
     * 
     * @param boolean $true_or_false
     */
    public function setDebugging($true_or_false)
    {
        $value = BooleanDataType::cast($true_or_false);
        if ($value){
            $this->startDebugging();
        } else {
            $this->stopDebugging();
        }
    }
    /**
     * Starts the debugger for the current context scope
     * 
     * @return DebugContext
     */
    public function startDebugging()
    {
        $this->is_debugging = true;
        $this->startTracer();
        
        $this->getWorkbench()->eventManager()->addListener(OnBeforeActionPerformedEvent::getEventName(), array(
            $this,
            'skipContextActionsEventHanlder'
        ));
        
        return $this;
    }
    
    public function skipContextActionsEventHanlder(OnBeforeActionPerformedEvent $e)
    {
        $action = $e->getAction();
        if ((($action instanceof ShowContextPopup) && $action->getContext() === $this)
        || $action instanceof ContextApi && $action->getContext() === $this){
            $this->getTracer()->disable();
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
    
    public function importUxonObject(UxonObject $uxon){
        if ($uxon->hasProperty('debugging')){
            $this->setDebugging($uxon->getProperty('debugging'));
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
        /* @var $data_list \exface\Core\Widgets\DataList */
        $data_list = WidgetFactory::create($container->getPage(), 'DataList', $container)
        ->setMetaObject($this->getWorkbench()->model()->getObject('exface.Core.TRACE_LOG'))
        ->setCaption($this->getName())
        ->setLazyLoading(false)
        ->setPaginate(false)
        ->setPaginatePageSize(10)
        ->addSorter('NAME', 'DESC');
        
        // Disable global actions and basket action as we know exactly, what we
        // want to do here
        $data_list->getToolbarMain()
        ->setIncludeGlobalActions(false)
        ->setIncludeObjectBasketActions(false);
        
        // Add the filename column (the UID column is always there)
        $data_list->addColumn($data_list->createColumnFromAttribute($data_list->getMetaObject()->getAttribute('NAME')));
        
        // Add the START button
        /* @var $button \exface\Core\Widgets\Button */
        $button = $data_list->createButton()
            ->setActionAlias('exface.Core.ContextApi')
            ->setCaption($this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.DEBUG.START'));
        $button->getAction()
            ->setContextScope($this->getScope()->getName())
            ->setContextAlias($this->getAliasWithNamespace())
            ->setOperation('startDebugging')
            ->setIcon(Icons::BUG);
        $data_list->addButton($button);
        
        /* @var $button \exface\Core\Widgets\Button */
        $button = $data_list->createButton()
            ->setActionAlias('exface.Core.ContextApi')
            ->setCaption($this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.DEBUG.STOP'))
            ->setIcon(Icons::PAUSE);
        $button->getAction()
            ->setContextScope($this->getScope()->getName())
            ->setContextAlias($this->getAliasWithNamespace())
            ->setOperation('stopDebugging');
        $data_list->addButton($button);
        
        // Add the detail button an bind it to the left click
        /* @var $details_button \exface\Core\Widgets\DataButton */
        $details_button = $data_list->createButton()
            ->setActionAlias('exface.Core.ShowObjectInfoDialog')
            ->setBindToLeftClick(true)
            ->setHidden(true);
        $details_button->getAction()->setDisableButtons(false);
        $data_list->addButton($details_button);
        
        $container->addWidget($data_list);
        return $container;
    }
    
    /**
     * @return Tracer
     */
    public function getTracer() : Tracer
    {
        return $this->tracer;
    }    
    
    protected function startTracer() : Tracer
    {
        $this->tracer = new Tracer($this->getWorkbench());
        return $this->tracer;
    }
}
?>