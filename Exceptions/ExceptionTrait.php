<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Exceptions\WarningExceptionInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Widgets\ErrorMessage;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\DataTypes\MessageTypeDataType;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * This trait contains a default implementation of ExceptionInterface to be used on-top
 * of built-in PHP exceptions.
 * 
 * @see ExceptionInterface
 *
 * @author Andrej Kabachnik
 *        
 */
trait ExceptionTrait {
    
    use ImportUxonObjectTrait;
    
    private $logLevel = null;

    private $alias = null;

    private $id = null;

    private $exception_widget = null;

    private $system = false;

    private $support_mail = false;
    
    private $messageData = null;
    
    private $useExceptionMessageAsTitle = false;

    public function __construct($message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
    }

    public function exportUxonObject()
    {
        return new UxonObject();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::isWarning()
     */
    public function isWarning()
    {
        if ($this instanceof WarningExceptionInterface) {
            return true;
        }
        return false;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::isError()
     */
    public function isError()
    {
        return $this->isWarning() ? false : true;
    }

    /**
     * Creates an ErrorMessage widget representing the exception.
     *
     * Do not override this method in order to customize the ErrorMessage widget - implement create_debug_widget() instead.
     * It is more convenient and does not require taking care of event handling, etc.
     *
     * @param UiPageInterface $page            
     * @return ErrorMessage
     */
    public function createWidget(UiPageInterface $page)
    {
        // Make sure, the widget is generated only once. Otherwise different parts of the code might get different widgets (with different ids).
        if (! is_null($this->exception_widget)) {
            return $this->exception_widget;
        }
        // Create a new error message
        /* @var $tabs \exface\Core\Widgets\ErrorMessage */
        $debug_widget = WidgetFactory::create($page, 'ErrorMessage');
        $debug_widget->setMetaObject($page->getWorkbench()->model()->getObject('exface.Core.MESSAGE'));
        
        $debug_widget = $this->createDebugWidget($debug_widget);
        
        // Save the widget in case create_widget() is called again
        $this->exception_widget = $debug_widget;
        
        return $debug_widget;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $page = $debug_widget->getPage();
        // Add a tab with a user-friendly error description
        if ($debug_widget->findChildById('error_tab') === false) {
            $error_tab = $debug_widget->createTab();
            $error_tab->setId('error_tab');
            $error_tab->setCaption($debug_widget->getWorkbench()->getCoreApp()->getTranslator()->translate('ERROR.CAPTION'));
            $error_tab->setNumberOfColumns(1);
            if ($this->getAlias()) {
                try {
                    $error_ds = $this->getMessageModelData($page->getWorkbench(), $this->getAlias());
                    $error_heading = WidgetFactory::create($page, 'TextHeading', $error_tab)
                        ->setHeadingLevel(2)
                        ->setValue($debug_widget->getWorkbench()->getCoreApp()->getTranslator()->translate('ERROR.CAPTION') . ' ' . $this->getAlias() . ': ' . $error_ds->getCellValue('TITLE', 0));
                    $error_tab->addWidget($error_heading);
                    $error_text = WidgetFactory::create($page, 'Text', $error_tab)
                        ->setValue($this->getMessage());
                    $error_tab->addWidget($error_text);
                    if ($hint = $error_ds->getCellValue('HINT', 0)) {
                        $error_hint = WidgetFactory::create($page, 'Message', $error_tab)
                        ->setText($hint)
                        ->setType(MessageTypeDataType::HINT);
                        $error_tab->addWidget($error_hint);
                    }
                    $error_descr = WidgetFactory::create($page, 'Markdown', $error_tab)
                        ->setAttributeAlias('DESCRIPTION')
                        ->setHideCaption(true);
                    $error_tab->addWidget($error_descr);
                    $error_tab->prefill($error_ds);
                } catch (\Throwable $e) {
                    $debug_widget->getWorkbench()->getLogger()->logException(new RuntimeException('Cannot fetch message with code "' . $this->getAlias() . '" - falling back to simplified error display!', null, $e));
                    $error_heading = WidgetFactory::create($page, 'TextHeading', $error_tab)->setHeadingLevel(2)->setValue($this->getMessage());
                    $error_tab->addWidget($error_heading);
                }
            } else {
                $error_heading = WidgetFactory::create($page, 'TextHeading', $error_tab)->setHeadingLevel(2)->setValue($this->getMessage());
                $error_tab->addWidget($error_heading);
            }
            
            /** @var Message $support_hint */
            $support_hint = WidgetFactory::create($page, 'Message', $error_tab);

            $support_hint->setText($debug_widget->getWorkbench()->getCoreApp()->getTranslator()->translate('ERROR.SUPPORT_HINT', ['%error_id%' => 'LOG-'.$this->getId(), '%system_name%' => $this->getSystemByPage($page), '%support_mail%' => $this->getSupportMailByPage($page)]));
            $error_tab->addWidget($support_hint);
            
            $debug_widget->addTab($error_tab);
        }
        
        // Add a tab with the exception printout
        if ($debug_widget->findChildById('stacktrace_tab') === false) {
            $stacktrace_tab = $debug_widget->createTab();
            $stacktrace_tab->setId('stacktrace_tab');
            $stacktrace_tab->setCaption($debug_widget->getWorkbench()->getCoreApp()->getTranslator()->translate('ERROR.STACKTRACE_CAPTION'));
            $stacktrace_tab->setNumberOfColumns(1);
            $stacktrace_widget = WidgetFactory::create($page, 'Html', $stacktrace_tab);
            $stacktrace_tab->addWidget($stacktrace_widget);
            $stacktrace_widget->setHtml($page->getWorkbench()->getDebugger()->printException($this));
            $debug_widget->addTab($stacktrace_tab);
        }
        
        // Add a tab with the request printout
        if ($page->getWorkbench()->getConfig()->getOption('DEBUG.SHOW_REQUEST_DUMP') && $debug_widget->findChildById('request_tab') === false) {
            $request_tab = $debug_widget->createTab();
            $request_tab->setId('request_tab');
            $request_tab->setCaption($page->getWorkbench()->getCoreApp()->getTranslator()->translate('ERROR.REQUEST_CAPTION'));
            $request_tab->setNumberOfColumns(1);
            $request_widget = WidgetFactory::create($page, 'Html', $request_tab);
            $request_tab->addWidget($request_widget);
            $request_widget->setHtml('<pre>' . $page->getWorkbench()->getDebugger()->printVariable($_REQUEST, true, 5) . '</pre>');
            $debug_widget->addTab($request_tab);
        }
        
        // Context tab
        if ($debug_widget->findChildById('context_tab') === false){
            $context_dump = array();
            foreach ($page->getWorkbench()->getContext()->getScopes() as $context_scope){
                $context_dump[$context_scope->getName()]['id'] = $context_scope->getScopeId();
                foreach ($context_scope->getContextsLoaded() as $context){
                    $context_dump[$context_scope->getName()][$context->getAlias()] = $context->exportUxonObject();
                }
            }
            $context_tab = $debug_widget->createTab();
            $context_tab->setId('context_tab');
            $context_tab->setCaption($page->getWorkbench()->getCoreApp()->getTranslator()->translate('ERROR.CONTEXT_CAPTION'));
            $context_tab->setNumberOfColumns(1);
            $context_widget = WidgetFactory::create($page, 'Html', $context_tab);
            $context_widget->setHtml('<pre>' . $page->getWorkbench()->getDebugger()->printVariable($context_dump, true, 2) . '</pre>');
            $context_tab->addWidget($context_widget);
            $debug_widget->addTab($context_tab);
        }
        
        // Recursively enrich the error widget with information from previous exceptions
        if ($prev = $this->getPrevious()) {
            if ($prev instanceof ErrorExceptionInterface) {
                $debug_widget = $prev->createDebugWidget($debug_widget);
            }
        }
        
        return $debug_widget;
    }

    /**
     * 
     * @param Workbench $exface
     * @param string $error_code
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    public function getMessageModelData(Workbench $exface, string $error_code) : DataSheetInterface
    {
        if ($this->messageData === null) {
            if ($this->getPrevious() && $this->getPrevious() instanceof ExceptionInterface){
                $modelMessageData = $this->getPrevious()->getMessageModelData($exface, $error_code);
            } else {
                $ds = DataSheetFactory::createFromObjectIdOrAlias($exface, 'exface.Core.MESSAGE');
                $ds->getColumns()->addMultiple(['TITLE', 'HINT', 'DESCRIPTION', 'TYPE']);
                if ($error_code) {
                    $ds->getFilters()->addConditionFromString('CODE', $error_code);
                    $ds->dataRead();
                }
                $modelMessageData = $ds;
            }
            
            if ($this->getUseExceptionMessageAsTitle() === true) {
                
                $ds = DataSheetFactory::createFromObjectIdOrAlias($exface, 'exface.Core.MESSAGE');
                if (! $descr = $modelMessageData->getCellValue('DESCRIPTION', 0)) {
                    if (! $descr = $modelMessageData->getCellValue('TITLE', 0)) {
                        $descr = '';
                    }
                }
                $ds->addRow([
                    'TITLE' => parent::getMessage(),
                    'HINT' => $modelMessageData->getCellValue('HINT', 0) ?? '',
                    'DESCRIPTION' => $descr,
                    'TYPE' => $modelMessageData->getCellValue('TYPE', 0) ?? 'ERROR'
                ]);
                $modelMessageData = $ds;
            }
            
            $this->messageData = $modelMessageData;
        }
        
        return $this->messageData;
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @return string|NULL
     */
    public function getMessageTitle(WorkbenchInterface $workbench) : ?string
    {
        try {
            $ds = $this->getMessageModelData($workbench, $this->getAlias());
            return $ds->getCellValue('TITLE', 0);
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @return string|NULL
     */
    public function getMessageHint(WorkbenchInterface $workbench) : ?string
    {
        try {
            $ds = $this->getMessageModelData($workbench, $this->getAlias());
            return $ds->getCellValue('HINT', 0);
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @return string|NULL
     */
    public function getMessageDescription(WorkbenchInterface $workbench) : ?string
    {
        try {
            $ds = $this->getMessageModelData($workbench, $this->getAlias());
            return $ds->getCellValue('DESCRIPTION', 0);
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @return string|NULL
     */
    public function getMessageType(WorkbenchInterface $workbench) : ?string
    {
        try {
            $ds = $this->getMessageModelData($workbench, $this->getAlias());
            return $ds->getCellValue('TYPE', 0);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getAlias()
     */
    public function getAlias()
    {
        if (is_null($this->alias)){
            if ($this->getPrevious() && $this->getPrevious() instanceof ExceptionInterface && $alias = $this->getPrevious()->getAlias()){
                return $alias;
            } elseif ($this->getDefaultAlias() !== null) {
                return $this->getDefaultAlias();
            }
        }
        return $this->alias;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::setAlias()
     */
    public function setAlias($alias)
    {
        if (! is_null($alias)) {
            $this->alias = $alias;
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getStatusCode()
     */
    public function getStatusCode()
    {
        if ($this->getPrevious() && $this->getPrevious() instanceof ExceptionInterface && $code = $this->getPrevious()->getStatusCode()){
            return $code;
        } 
        return 500;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getId()
     */
    public function getId()
    {
        if (is_null($this->id)) {
            $this->id = $this->createId();
        }
        return $this->id;
    }

    private function createId()
    {
        return strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getLogLevel()
     */
    public function getLogLevel()
    {
        if (is_null($this->logLevel)){
            if ($this->getPrevious() && $this->getPrevious() instanceof ExceptionInterface && $this->getPrevious()->getLogLevel() != $this->getDefaultLogLevel()){
                return $this->getPrevious()->getLogLevel();
            }
            return $this->getDefaultLogLevel();
        }
        return $this->logLevel;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::setLogLevel()
     */
    public function setLogLevel($logLevel)
    {
        $this->logLevel = $logLevel;
        return $this;
    }

    public function getSystemByPage(UiPageInterface $page)
    {
        if( $this->system == FALSE) {
            // TODO #nocms how to get the installation name???
            $this->system = '';
        }
        return $this->system;
    }

    public function getSupportMailByPage(UiPageInterface $page)
    {
        if( $this->support_mail == FALSE) {
            $this->support_mail = $this->getConfigValueByPage($page, "DEBUG.SUPPORT_EMAIL_ADDRESS");
        }
        return $this->support_mail;
    }

    protected function getConfigValueByPage(UiPageInterface $page, $option) {
        $app = $page->getWorkbench()->getApp("exface.Core");
        if(!$app ) {
            return null;
        }
        $config = $app->getConfig();
        if(!$config ) {
            return null;
        }
        return $config->getOption($option);
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return null;
    }
    
    /**
     * Returns TRUE if exception message is to be used as error message title.
     * 
     * FALSE by default, thus using the message model found via error code.
     * 
     * @return bool
     */
    protected function getUseExceptionMessageAsTitle() : bool
    {
        return $this->useExceptionMessageAsTitle;
    }
    
    /**
     * Makes the errors displayed use the exception message as title instead of attempting to 
     * get the title from the message metamodel via error code (alias).
     * 
     * @param bool $value
     * @return ExceptionInterface
     */
    public function setUseExceptionMessageAsTitle(bool $value) : ExceptionInterface
    {
        $this->useExceptionMessageAsTitle = $value;
        return $this;
    }
}