<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\UxonSnippetFactory;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\DataTypes\MessageTypeDataType;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Selectors\AppSelector;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Interfaces\Model\MessageInterface;

/**
 * Messages used in errors, warnings, etc. with additional information like hints, descriptions, etc.
 * 
 * There is a global list of available message codes in `Administration > Metamodel > Messages`. Messages are parts of
 * apps, so every app can add its own message codes.
 * 
 * The UXON model of a message can either reference such a message from the app by using its `code` or define a
 * "temporary" message without a code:
 * 
 * - If you reference a message model, UXON properties like `title`, `hint`, etc. will override the corresponding
 * attributes in the message model. Properties not set in the UXON will be filled from the model.
 * - If you do not reference a `code`, you will need to define everything in the UXON. 
 * 
 * In most cases, it is recommended to use message `code`s!
 * 
 * @author Andrej Kabachnik
 * 
 */
class Message implements MessageInterface
{
    use ImportUxonObjectTrait {
        importUxonObject as importUxonObjectViaTrait;
    }
    
    private WorkbenchInterface $workbench;
    private AppSelectorInterface|null $appSelector = null;
    
    private string $code;
    private ?string $type = null;
    private ?string $title = null;
    private ?string $hint = null;
    private ?string $description = null;
    private ?string $docsPath = null;
    
    private bool $modelLoaded = false;
    private UxonObject $uxon;
    
    /**
     * @deprecated use MessageFactory instead!
     * 
     * @param WorkbenchInterface $workbench
     * @param string $code
     */
    public function __construct(WorkbenchInterface $workbench, string $code)
    {
        $this->workbench = $workbench;
        $this->code = $code;
        $this->uxon = new UxonObject();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;   
    }

    /**
     * Unique message code from Administration > Metamodel > Messages.
     * 
     * Setting a `code` here will automatically load the message model. To create a new code, add a message in 
     * the administration and export your app. Then the code will become available.
     * 
     * You can override `title`, `hint`, etc. selectively in UXON - the other properties will still be loaded 
     * from the model.
     * 
     * @uxon-property code
     * @uxon-type metamodel:exface.Core.MESSAGE:CODE
     *
     * @param string $code
     * @return MessageInterface
     * 
     * @see MessageInterface::setCode()
     */
    public function setCode(string $code) : MessageInterface
    {
        $this->code = $code;
        $this->uxon->setProperty('code', $code);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MessageInterface::getCode()
     */
    public function getCode() : string
    {
        return $this->code;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MessageInterface::getType()
     */
    public function getType(?string $default = null) : string
    {
        if ($this->type === null) {
            $this->loadModelData();
        }
        if (! MessageTypeDataType::isValidStaticValue($default)) {
            $default = MessageTypeDataType::INFO;
        }
        return $this->type ?? $default;
    }

    /**
     * Type of message: error, warning, info, success, hint or question.
     * 
     * @uxon-property type
     * @uxon-type [ERROR,WARNING,INFO,SUCCESS,HINT,QUESTION]
     *
     * @param string $value
     * @return MessageInterface
     * 
     * @see MessageInterface::setType()
     */
    public function setType(string $value) : MessageInterface
    {
        $this->type = MessageTypeDataType::cast($value);
        $this->uxon->setProperty('type', $this->type);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MessageInterface::getTitle()
     */
    public function getTitle() : string
    {
        if ($this->title === null) {
            $this->loadModelData();
        }
        return $this->title ?? '';
    }

    /**
     * Message to be shown to end users
     * 
     * @uxon-property title
     * @uxon-type string
     * @uxon-translatable true
     *
     * @param string $value
     * @return MessageInterface
     * 
     * @see MessageInterface::setTitle()
     */
    public function setTitle(string $value) : MessageInterface
    {
        $this->title = $value;
        $this->uxon->setProperty('title', $this->title);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MessageInterface::getHint()
     */
    public function getHint() : ?string
    {
        if ($this->hint === null) {
            $this->loadModelData();
        }
        return $this->hint;
    }

    /**
     * Recommendations/instructions for user to deal with this message.
     * 
     * Hints will be shown to end-users.
     * 
     * @uxon-property hint
     * @uxon-type string
     * @uxon-translatable true
     * 
     * @see MessageInterface::setHint()
     */
    public function setHint(string $value) : MessageInterface
    {
        $this->hint = $value;
        $this->uxon->setProperty('hint', $this->hint);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MessageInterface::getDescription()
     */
    public function getDescription() : ?string
    {
        if ($this->description === null) {
            $this->loadModelData();
        }
        return $this->description;
    }
    
    /**
     * Details about the message for admins and app designers: especially for errors, warnings, etc.
     * 
     * Detailed descriptions are normally not shown to end-users in contrast to `title` and `hint`.
     * 
     * @uxon-property description
     * @uxon-type string
     * @uxon-translatable true
     * 
     * @see MessageInterface::setDescription()
     */
    public function setDescription(string $value) : MessageInterface
    {
        $this->description = $value;
        $this->uxon->setProperty('description', $this->description);
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see MessageInterface::getDocsPath()
     */
    public function getDocsPath() : ?string
    {
        return $this->docsPath;
    }

    /**
     * AppDocs file with documentation related to this message (path relative to vendor folder)
     * 
     * @uxon-property docs_path
     * @uxon-type string
     * @uxon-template exface/core/Docs/...
     * 
     * @see MessageInterface::setDocsPath()
     */
    public function setDocsPath(string $value) : MessageInterface
    {
        $this->docsPath = $value;
        $this->uxon->setProperty('docs_path', $this->docsPath);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MessageInterface::getAppSelector()
     */
    public function getAppSelector() : ?AppSelectorInterface
    {
        if ($this->appSelector === null) {
            $this->loadModelData();
        }
        return $this->appSelector;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MessageInterface::setAppSelector()
     */
    public function setAppSelector($stringOrSelector) : MessageInterface
    {
        if ($stringOrSelector instanceof AppSelectorInterface) {
            $selector = $stringOrSelector;
        } else {
            $selector = new AppSelector($this->getWorkbench(), $stringOrSelector);
        }
        $this->appSelector = $selector;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MessageInterface::getApp()
     */
    public function getApp() : ?AppInterface
    {
        return ($sel = $this->getAppSelector()) ? $this->getWorkbench()->getApp($sel) : null;
    }
    
    /**
     * 
     * @return DataSheetInterface
     */
    protected function loadModelData() : MessageInterface
    {
        if ($this->modelLoaded === false && $this->code !== null) {
            $this->modelLoaded = true;
            $this->getWorkbench()->model()->getModelLoader()->loadMessageData($this);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function importUxonObject(UxonObject $uxon, array $skip_property_names = array(), bool $overwrite = true)
    {
        // Check, if we should overwrite properties already set.
        if ($overwrite === false) {
            // If not, merge the incoming and the existing UXON overwriting values in the incoming one
            $uxon = $uxon->extend($this->uxon);
        }
        $this->importUxonObjectViaTrait($uxon, $skip_property_names);
    }

    /**
     * @inheritdoc 
     * @see iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject() : UxonObject
    {
        return $this->uxon;
    }
}