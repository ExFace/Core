<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\DataTypes\MessageTypeDataType;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Selectors\AppSelector;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Interfaces\Model\MessageInterface;

class Message implements MessageInterface
{
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
    public function getType() : string
    {
        if ($this->type === null) {
            $this->loadModelData();
        }
        return $this->type ?? MessageTypeDataType::INFO;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MessageInterface::setType()
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MessageInterface::setTitle()
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MessageInterface::setHint()
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
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MessageInterface::setDescription()
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
}