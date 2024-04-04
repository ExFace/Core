<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Facades\FacadeInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class ScheduledTask extends GenericTask
{
    private $schedulerUid = null;
    
    /**
     * 
     * @param FacadeInterface $facade
     * @param ServerRequestInterface $request
     */
    public function __construct(WorkbenchInterface $workbench, UxonObject $uxon = null, string $schedulerUid = null)
    {
        parent::__construct($workbench);
        $this->schedulerUid = $schedulerUid;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }
    
    /**
     * 
     * @return string
     */
    public function getSchedulerUid() : string
    {
        return $this->schedulerUid;
    }
}