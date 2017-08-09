<?php
namespace exface\Core\Contexts;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\Contexts\AbstractContext;
use exface\Core\CommonLogic\Constants\Colors;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Container;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\CommonLogic\Log\Handlers\LogfileHandler;
use exface\Core\CommonLogic\Log\Handlers\DebugMessageFileHandler;
use exface\Core\Events\ActionEvent;
use exface\Core\Actions\ShowContextPopup;
use exface\Core\Actions\ContextApi;
use exface\Core\CommonLogic\Log\Handlers\BufferingHandler;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Exceptions\Contexts\ContextAccessDeniedError;
use exface\Core\CommonLogic\Profiler;
use exface\Core\Interfaces\Contexts\ContextInterface;

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
    
    private $log_handlers = array();
    
    private $profiler = null;
    
    public function __construct(NameResolverInterface $name_resolver){
        parent::__construct($name_resolver);
        
        if ($name_resolver->getWorkbench()->context()->getScopeUser()->isUserAnonymous()){
            throw new ContextAccessDeniedError($this, 'The debug context cannot be used for anonymous users!');
        }
        
        $this->profiler = new Profiler($name_resolver->getWorkbench());
    }
    
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
        $value = BooleanDataType::parse($true_or_false);
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
        // Log everything
        $workbench = $this->getWorkbench();
        $this->log_handlers = [
            new BufferingHandler(
                new LogfileHandler("exface", $this->getTraceFileName(), $workbench, LoggerInterface::DEBUG)
            ),
            new BufferingHandler(
                new DebugMessageFileHandler($workbench, $workbench->filemanager()->getPathToLogDetailsFolder(), ".json", LoggerInterface::DEBUG)
            )
        ];
        foreach ($this->log_handlers as $handler){
            $workbench->getLogger()->appendHandler($handler);
        }
        
        $workbench->eventManager()->addListener('#.Action.Perform.Before', array(
            $this,
            'skipSystemActionsEventHanlder'
        ));
        
        return $this;
    }
    
    public function skipSystemActionsEventHanlder(ActionEvent $e)
    {
        $action = $e->getAction();
        if ((($action instanceof ShowContextPopup) && $action->getContext() === $this)
        || $action instanceof ContextApi && $action->getContext() === $this){
            foreach ($this->log_handlers as $handler){
                $handler->setDisabled(true);
            }
        }
    }
    
    /**
     * 
     * @return string
     */
    protected function getTraceFileName(){
        $workbench = $this->getWorkbench();
        $now = \DateTime::createFromFormat('U.u', microtime(true));
        $time = $now->format("Y-m-d H-i-s-u");
        return $workbench->filemanager()->getPathToLogFolder() . DIRECTORY_SEPARATOR . 'traces' . DIRECTORY_SEPARATOR . $time . '.csv';
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
        return $this->getWorkbench()->context()->getScopeWindow();
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
        return Colors::DEFAULT;
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
        $data_list->addColumn(WidgetFactory::create($container->getPage(), 'DataColumn', $data_list)->setAttributeAlias('NAME'));
        
        // Add the START button
        /* @var $button \exface\Core\Widgets\Button */
        $button = $data_list->createButton()
            ->setActionAlias('exface.Core.ContextApi')
            ->setCaption($this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.DEBUG.START'));
        $button->getAction()
            ->setContextScope($this->getScope()->getName())
            ->setContextAlias($this->getAliasWithNamespace())
            ->setOperation('startDebugging')
            ->setIconName(Icons::BUG);
        $data_list->addButton($button);
        
        /* @var $button \exface\Core\Widgets\Button */
        $button = $data_list->createButton()
            ->setActionAlias('exface.Core.ContextApi')
            ->setCaption($this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.DEBUG.STOP'))
            ->setIconName(Icons::PAUSE);
        $button->getAction()
            ->setContextScope($this->getScope()->getName())
            ->setContextAlias($this->getAliasWithNamespace())
            ->setOperation('stopDebugging');
        $data_list->addButton($button);
        
        // Add the detail button an bind it to the left click
        /* @var $details_button \exface\Core\Widgets\DataButton */
        $details_button = $data_list->createButton()
            ->setActionAlias('exface.Core.ShowObjectDialog')
            ->setBindToLeftClick(true)
            ->setHidden(true);
        $data_list->addButton($details_button);
        
        $container->addWidget($data_list);
        return $container;
    }
    
    /**
     * @return Profiler
     */
    public function getProfiler()
    {
        return $this->profiler;
    }    
}
?>