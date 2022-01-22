<?php
namespace exface\Core\CommonLogic\Communication;

use exface\Core\Interfaces\Communication\CommunicationChannelInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\AliasTrait;

abstract class AbstractCommunicationChannel implements CommunicationChannelInterface
{
    use ImportUxonObjectTrait;
    
    use AliasTrait;
    
    private $name = '';
    
    private $workbench = null;
    
    public function __construct(WorkbenchInterface $workbench, UxonObject $config)
    {
        $this->importUxonObject($config);
        $this->workbench = $workbench;
    }
    
    public function getName(): string
    {
        return $this->getName();
    }
    
    protected function setName(string $name) : CommunicationChannelInterface
    {
        $this->name = $name;
        return $this;
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
     * @return string|NULL
     */
    public static function getUxonSchemaClass(): ?string
    {
        return null;
    }
}