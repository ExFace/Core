<?php
namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Widgets\ErrorMessage;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\DataTypes\MessageTypeDataType;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Widgets\Message;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\Log\Logger;
use exface\Core\Interfaces\Model\MessageInterface;
use exface\Core\Factories\MessageFactory;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\CommonLogic\WidgetDimension;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\DataTypes\LogLevelDataType;

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
    
    private $messageModel = null;
    
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
            if ($this->getAlias()) {
                try {
                    $msgModel = $this->getMessageModel($page->getWorkbench());
                    $error_heading = WidgetFactory::create($page, 'TextHeading', $error_tab)
                        ->setHeadingLevel(2)
                        ->setWidth(WidgetDimension::MAX)
                        ->setValue($debug_widget->getWorkbench()->getCoreApp()->getTranslator()->translate('ERROR.CAPTION') . ' ' . $this->getAlias() . ': ' . $msgModel->getTitle());
                    $error_tab->addWidget($error_heading);
                    $error_text = WidgetFactory::create($page, 'Text', $error_tab)
                        ->setWidth(WidgetDimension::MAX)
                        ->setValue($this->getMessage());
                    $error_tab->addWidget($error_text);
                    if ($hint = $msgModel->getHint()) {
                        $error_hint = WidgetFactory::create($page, 'Message', $error_tab)
                        ->setText($hint)
                        ->setWidth(WidgetDimension::MAX)
                        ->setType(MessageTypeDataType::HINT);
                        $error_tab->addWidget($error_hint);
                    }
                    $error_descr = WidgetFactory::create($page, 'Markdown', $error_tab)
                        ->setAttributeAlias('DESCRIPTION')
                        ->setWidth(WidgetDimension::MAX)
                        ->setHideCaption(true)
                        ->setValue($msgModel->getDescription());
                    $error_tab->addWidget($error_descr);
                } catch (\Throwable $e) {
                    $debug_widget->getWorkbench()->getLogger()->logException(new RuntimeException('Cannot fetch message with code "' . $this->getAlias() . '" - falling back to simplified error display!', null, $e));
                    $error_heading = WidgetFactory::create($page, 'TextHeading', $error_tab)
                        ->setHeadingLevel(2)
                        ->setWidth(WidgetDimension::MAX)
                        ->setValue($this->getMessage());
                    $error_tab->addWidget($error_heading);
                }
            } else {
                $error_heading = WidgetFactory::create($page, 'TextHeading', $error_tab)
                    ->setHeadingLevel(2)
                    ->setWidth(WidgetDimension::MAX)
                    ->setValue($this->getMessage());
                $error_tab->addWidget($error_heading);
            }
            
            $error_tab->addWidget($this->createDebugSupportHint($error_tab));
            
            $debug_widget->addTab($error_tab);
        }
        
        // Add a tab with the exception printout
        if ($debug_widget->findChildById('stacktrace_tab') === false) {
            $stacktrace_tab = $debug_widget->createTab();
            $stacktrace_tab->setId('stacktrace_tab');
            $stacktrace_tab->setCaption($debug_widget->getWorkbench()->getCoreApp()->getTranslator()->translate('ERROR.STACKTRACE_CAPTION'));
            $stacktrace_widget = WidgetFactory::createFromUxonInParent($stacktrace_tab, new UxonObject([
                'width' => '100%',
                'height' => '100%'
            ]), 'Html');
            $stacktrace_tab->addWidget($stacktrace_widget);
            $stacktrace_widget->setHtml($page->getWorkbench()->getDebugger()->printException($this));
            $debug_widget->addTab($stacktrace_tab);
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
            $context_tab->addWidget(WidgetFactory::createFromUxonInParent($context_tab,  new UxonObject([
                'widget_type' => 'InputUxon',
                'disabled' => true,
                'width' => '100%',
                'height' => '100%',
                'value' => JsonDataType::encodeJson($context_dump)
            ])));
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
     * @param WidgetInterface $parentWidget
     * @return WidgetInterface
     */
    protected function createDebugSupportHint(WidgetInterface $parentWidget) : WidgetInterface
    {
        $hintWidget = null;
        $wb = $parentWidget->getWorkbench();
        $wbName = $wb->getUrl();
        $email = $wb->getConfig()->getOption("SERVER.SUPPORT_EMAIL_ADDRESS");
        if ($email) {
            $hintMessage = $wb->getCoreApp()->getTranslator()->translate('ERROR.SUPPORT_HINT_WITH_EMAIL', [
                '%log_id%' => 'LOG-'.$this->getId(), 
                '%system_name%' => $wbName, 
                '%support_mail%' => $email
            ]);
        } else {
            $hintMessage = $wb->getCoreApp()->getTranslator()->translate('ERROR.SUPPORT_HINT', [
                '%log_id%' => 'LOG-'.$this->getId()
            ]);
        }
        /** @var Message $hintWidget */
        $hintWidget = WidgetFactory::createFromUxonInParent($parentWidget, new UxonObject([
            'text' => $hintMessage,
            'width' => WidgetDimension::MAX
        ]), 'Message');
        
        return $hintWidget;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getMessageModel()
     */
    public function getMessageModel(WorkbenchInterface $workbench) : MessageInterface
    {
        if ($this->messageModel === null) {
            if ($this->getPrevious() && $this->getPrevious() instanceof ExceptionInterface){
                $this->messageModel = $this->getPrevious()->getMessageModel($workbench);
            } else {
                $alias = $this->getAlias();
                $aliasProvided = $alias !== null;
                if (! $aliasProvided) {
                    $alias = '6VCYFND'; // Internal error
                }
                try {
                    $this->messageModel = MessageFactory::createFromCode($workbench, $alias);
                    if (! $aliasProvided) {
                        $levelCmp = LogLevelDataType::compareLogLevels($this->getLogLevel(), LoggerInterface::WARNING);
                        switch (true) {
                            case $levelCmp < 0: $type = MessageTypeDataType::INFO; break;
                            case $levelCmp === 0: $type = MessageTypeDataType::WARNING; break;
                            default: $type = MessageTypeDataType::ERROR;
                        }
                        $this->messageModel->setType($type);
                    }
                } catch (\Throwable $e) {
                    $workbench->getLogger()->logException($e);
                    $this->messageModel = MessageFactory::createError($workbench, 'Unknown error');
                }
            }
            
            if ($this->getUseExceptionMessageAsTitle() === true) {
                $this->messageModel->setTitle($this->getMessage());
            }
        }
        return $this->messageModel;
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
        if ($this->id === null) {
            if ($this->getPrevious() instanceof ExceptionInterface) {
                $this->id = $this->getPrevious()->getId();
            } else {
                $this->id = Logger::generateLogId();
            }
        }
        return $this->id;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getLogLevel()
     */
    public function getLogLevel()
    {
        if (is_null($this->logLevel)){
            if ($this->getPrevious()){
                if ($this->getPrevious() instanceof ExceptionInterface) {
                    return $this->getPrevious()->getLogLevel();
                }
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