<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\DataTypes\MessageTypeDataType;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Selectors\AppSelector;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Interfaces\Model\MessageInterface;

/**
 * TODO - Documentation
 */
class Message implements MessageInterface
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $code = null;
    
    private $type = null;
    
    private $title = null;
    
    private $hint = null;
    
    private $description = null;
    
    private $appSelector = null;
    
    private $docsPath = null;
    
    private $modelLoaded = false;
    
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
     * @uxon-property code
     * @uxon-type string
     *
     * @param string $code
     * @return MessageInterface
     * 
     * @see MessageInterface::setCode()
     */
    public function setCode(string $code) : MessageInterface
    {
        $this->code = $code;
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
    public function getType(string $default = null) : string
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
     * @uxon-property title
     * @uxon-type string
     *
     * @param string $value
     * @return MessageInterface
     * 
     * @see MessageInterface::setTitle()
     */
    public function setTitle(string $value) : MessageInterface
    {
        $this->title = $value;
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
     *
     * @param string $value
     * @return MessageInterface
     * 
     * @see MessageInterface::setHint()
     */
    public function setHint(string $value) : MessageInterface
    {
        $this->hint = $value;
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
     * {@inheritDoc}
     * @see MessageInterface::setDescription()
     */
    public function setDescription(string $value) : MessageInterface
    {
        $this->description = $value;
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
     * @param AppInterface $value
     * @return MessageInterface
     */
    public function setApp(AppInterface $value) : MessageInterface
    {
        $this->appSelector = $value;
        return $this;
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
     * @inheritdoc 
     * @see iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject() : UxonObject
    {
        return new UxonObject([
            'title' => $this->getTitle(),
            'hint' => $this->getHint(),
            'code' => $this->getCode(),
            'type' => $this->getType()
        ]);
    }
}