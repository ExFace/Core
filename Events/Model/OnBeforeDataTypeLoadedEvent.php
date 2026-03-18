<?php
namespace exface\Core\Events\Model;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\AppInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Event fired before a data type is instantiated by the model loader.
 * 
 * Listeners to this even can modify the UXON configuration of the behavior.
 * 
 * @event exface.Core.Model.OnBeforeDataTypeLoaded
 *
 * @author Andrej Kabachnik
 *
 */
class OnBeforeDataTypeLoadedEvent extends AbstractEvent
{
    private WorkbenchInterface $workbench;
    
    private string $prototype;
    private string $dataTypeAlias;
    private string $dataTypeUid;
    private string $appAlias;
    private UxonObject $uxon;

    /**
     *
     * @param string $prototype
     * @param string $dataTypeAlias
     * @param string $appAlias
     * @param UxonObject $uxon
     */
    public function __construct(WorkbenchInterface $workbench, string $prototype, string $dataTypeAlias, string $dataTypeUid, string $appAlias, UxonObject $uxon)
    {
        $this->workbench = $workbench;
        $this->prototype = $prototype;
        $this->dataTypeAlias = $dataTypeAlias;
        $this->dataTypeUid = $dataTypeUid;
        $this->appAlias = $appAlias;
        $this->uxon = $uxon;
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
     * @return string
     */
    public function getPrototype() : string
    {
        return $this->prototype;
    }

    /**
     *
     * @return string
     */
    public function getDataTypeAlias() : string
    {
        return $this->dataTypeAlias;
    }

    /**
     * @return string
     */
    public function getDataTypeUid() : string
    {
        return $this->dataTypeUid;
    }
    
    /**
     * 
     * @return AppInterface
     */
    public function getApp() : AppInterface
    {
        return $this->getWorkbench()->getApp($this->appAlias);
    }
    
    /**
     * 
     * @return UxonObject
     */
    public function getUxon() : UxonObject
    {
        return $this->uxon;
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Model.OnBeforeDataTypeLoaded';
    }
}