<?php
namespace exface\Core\Exceptions;

use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\Facades\DocsFacade;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
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
use exface\Core\Factories\MetaObjectFactory;

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

    private $statusCode = null;
    
    private array $links = [];

    public function __construct($message, $alias = null, $previous = null)
    {
        parent::__construct($message, 0, $previous);
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
        // Make sure, the widget is generated only once. Otherwise, different parts of the code might get different widgets (with different ids).
        if (! is_null($this->exception_widget)) {
            return $this->exception_widget;
        }
        // Create a new error message
        /* @var $tabs \exface\Core\Widgets\ErrorMessage */
        $debug_widget = WidgetFactory::create($page, 'ErrorMessage');
        $debug_widget->setMetaObject(MetaObjectFactory::createFromString($page->getWorkbench(), 'exface.Core.MESSAGE'));
        
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

        // Appends the exception ID only once for preventing IDs like: DebugMessage_ID_ID_ID...
        if (!str_contains($debug_widget->getId(), $this->getId())) {
            $debug_widget->setId($debug_widget->getId() . '_' . $this->getId());
        }
        $translator = $debug_widget->getWorkbench()->getCoreApp()->getTranslator();
        // Add a tab with a user-friendly error description
        if ($debug_widget->findChildById('error_tab') === false) {
            $error_tab = $debug_widget->createTab();
            $error_tab->setId('error_tab');
            $error_tab->setCaption($debug_widget->getWorkbench()->getCoreApp()->getTranslator()->translate('ERROR.CAPTION'));
            if ($msgCode = $this->getAlias()) {
                try {
                    $msgModel = $this->getMessageModel($page->getWorkbench());
                    
                    $error_heading = WidgetFactory::create($page, 'Markdown', $error_tab)
                        ->setHideCaption(true)
                        ->setWidth(WidgetDimension::MAX)
                        ->setValue(<<<MD
# {$translator->translate('ERROR.CAPTION')} {$msgCode}: {$msgModel->getTitle()}

> {$this->getMessage()}
MD);
                    $error_tab->addWidget($error_heading);
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
                        ->setOpenLinksIn('popup')
                        ->setValue($msgModel->getDescription() . $this->getLinksAsMarkdown());
                    $error_tab->addWidget($error_descr);
                } catch (\Throwable $e) {
                    $eRenderer = new RuntimeException('Cannot fetch message with code "' . $this->getAlias() . '" - falling back to simplified error display!', null, $e);
                    $debug_widget->getWorkbench()->getLogger()->logException($eRenderer);
                    $error_heading = WidgetFactory::create($page, 'Markdown', $error_tab)
                        ->setWidth(WidgetDimension::MAX)
                        ->setHideCaption(true)
                        ->setValue(<<<MD
# Failed to render error properly

Could not format error **{$msgCode}** properly: 

> {$eRenderer->getMessage()} in `{$eRenderer->getFile()}` on line `{$eRenderer->getLine()}`

## Original error

> {$this->getMessage()}

MD);
                    $error_tab->addWidget($error_heading);
                }
            } else {
                $error_heading = WidgetFactory::create($page, 'Markdown', $error_tab)
                    ->setWidth(WidgetDimension::MAX)
                    ->setHideCaption(true)
                    ->setValue(<<<MD
# Internal error

> {$this->getMessage()}

MD);
                $error_tab->addWidget($error_heading);
            }
            
            $error_tab->addWidget($this->createDebugSupportHint($error_tab));
            
            $debug_widget->addTab($error_tab);
        }
        
        // Add a tab with the exception printout
        if ($debug_widget->findChildById('stacktrace_tab') === false) {
            $stacktrace_tab = $debug_widget->createTab();
            $stacktrace_tab->setId('stacktrace_tab');
            $stacktrace_tab->setCaption($translator->translate('ERROR.STACKTRACE_CAPTION'));
            $stacktrace_widget = WidgetFactory::createFromUxonInParent($stacktrace_tab, new UxonObject([
                'width' => '100%',
                'height' => '100%',
                'hide_caption' => true,
            ]), 'Markdown');
            $stacktrace_tab->addWidget($stacktrace_widget);
            $stacktrace_widget->setValue($page->getWorkbench()->getDebugger()->printExceptionAsMarkdown($this));
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
            $context_tab->setCaption($translator->translate('ERROR.CONTEXT_CAPTION'));
            $context_tab->addWidget(WidgetFactory::createFromUxonInParent($context_tab,  new UxonObject([
                'widget_type' => 'InputUxon',
                "id" => $debug_widget->getId() . '_' . $context_tab->getId() . '_InputUxon',
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
    
    protected function getLinksAsMarkdown(int $headingLevel = 2) : string
    {
        $md = '';
        foreach ($this->getLinks() as $title => $url) {
            $md .= "\n- [{$title}]({$url})";
        }
        if ($md !== '') {
            $md = "\n\n" . MarkdownDataType::makeHeading('Documentation links') . "\n" . $md;
        }
        return $md;
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
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getStatusCode()
     */
    public function getStatusCode(int $default = 500) : int
    {
        if ($this->statusCode !== null) {
            return $this->statusCode;
        }
        $prev = $this->getPrevious();
        if ($prev !== null && $prev instanceof ExceptionInterface && 0 !== $code = $prev->getStatusCode(0)){
            return $code;
        } 
        return $default;
    }

    public function setStatusCode(int $httpResponseCode) : ExceptionInterface
    {
        $this->statusCode = $httpResponseCode;
        return $this;
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

        if ($this->messageModel !== null) {
            $this->messageModel->setTitle($this->getMessage());
        }
        
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see ExceptionInterface::addLink()
     */
    public function addLink(string $title, string $url) : ExceptionInterface
    {
        $this->links[$title] = $url;
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see ExceptionInterface::getLinks()
     */
    public function getLinks() : array
    {
        $links = $this->links;
        if ($prev = $this->getPrevious()) {
            if ($prev instanceof ExceptionInterface) {
                // For our own exceptions, just generate links recursively
                $links = array_merge($prev->getLinks(), $links);
            } else {
                // For other types of exceptions, see if they happened in one of our UXON prototypes. If so,
                // add a link to this prototype docs. This should help in cases like invalid return types
                // because a required UXON property not set.
                try {
                    $prevFileClass = $prev->getTrace()[0]['class'] ?? null;
                    if ($prevFileClass) {
                        $prevFileClass = '\\' . ltrim($prevFileClass, '\\');
                        if ($prevFileClass && is_a($prevFileClass, iCanBeConvertedToUxon::class, true)) {
                            $links['UXON prototype `' . PhpClassDataType::findClassNameWithoutNamespace($prevFileClass) . '`'] = DocsFacade::buildUrlToDocsForUxonPrototype($prevFileClass);
                        }
                    }
                } catch (\Throwable $e) {
                    // Do nothing - we were just trying to find some editional information
                }
            }
        }
        
        if ($this->messageModel !== null && $msgDocsPath = $this->messageModel->getDocsPath()) {
            $filename = FilePathDataType::findFileName($msgDocsPath, false);
            $links[str_replace('_', ' ', $filename)] = DocsFacade::buildUrlToFile($msgDocsPath);
        }
        
        return array_unique($links);
    }

    /**
     * {@inheritDoc}
     * @see ExceptionInterface::findPrevious()
     */
    public function findPrevious(string $classOrInterface) : ?\Throwable
    {
        while ($prev = $this->getPrevious()) {
            if ($prev instanceof $classOrInterface) {
                return $prev;
            }
        }
        return null;
    }
}