<?php
namespace exface\Core\CommonLogic\Communication;

use exface\Core\Interfaces\Selectors\CommunicationTemplateSelectorInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\Communication\CommunicationTemplateInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\DataTypes\StringDataType;

/**
 * 
 * @author andrej.kabachnik
 *
 */
class CommunicationTemplate implements CommunicationTemplateInterface
{
    use ImportUxonObjectTrait;
    
    private $selector = null;
    
    private $messageUxon = null;
    
    private $uxon = null;
    
    private $name = null;
    
    private $alias = null;
    
    private $namespace = null;
    
    private $uid = null;
    
    public function __construct(CommunicationTemplateSelectorInterface $selector, UxonObject $uxon)
    {
        $this->selector = $selector;
        switch (true) {
            case $selector->isAlias():
                $this->namespace = $selector->getAppAlias();
                $this->alias = StringDataType::substringAfter($this->namespace . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, $selector->toString());
                break;
            case $selector->isUid():
                $this->uid = $selector->toString();
                break;
        }
        if ($uxon !== null) {
            $this->uxon = $uxon;
            $this->importUxonObject($uxon);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationTemplateInterface::getSelector()
     */
    public function getSelector() : CommunicationTemplateSelectorInterface
    {
        return $this->selector;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationReceiptInterface::getMessage()
     */
    public function getMessageUxon(): UxonObject
    {
        return $this->messageUxon;
    }
    
    protected function setMessage(UxonObject $uxon) : CommunicationTemplate
    {
        $this->messageUxon = $uxon;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return $this->uxon ?? new UxonObject();
    }

    public function getName() : string
    {
        return $this->name;
    }
    
    /**
     * 
     * @param string $value
     * @return CommunicationTemplate
     */
    protected function setName(string $value) : CommunicationTemplate
    {
        $this->name = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getNamespace()
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAlias()
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAliasWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        return $this->namespace . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $this->alias;
    }
    
    public function getUid() : string
    {
        return $this->uid;
    }
    
    protected function setUid(string $value) : CommunicationTemplate
    {
        $this->uid = $value;
        return $this;
    }
}