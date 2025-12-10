<?php
namespace exface\Core\Events\Model;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\MetaObjectEventInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Event fired before a meta objects behavior is instantiated.
 *
 * Listeners to this even can modify the UXON configuration of the behavior.
 *
 * @event exface.Core.Model.OnBeforeSnippetLoaded
 *
 * @author Andrej Kabachnik
 *
 */
class OnBeforeSnippetLoadedEvent extends AbstractEvent
{
    private WorkbenchInterface $workbench;
    private string $prototype;
    private string $snippetUid;
    private string $snippetAlias;
    private UxonObject $uxon;
    private string $appSelectorString;

    /**
     *
     * @param string $prototype
     * @param string $snippetUid
     * @param \exface\Core\Interfaces\AppInterface $appUid
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $object
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     */
    public function __construct(WorkbenchInterface $workbench, string $prototype, string $snippetUid, string $snippetAlias, string $appSelector, UxonObject $uxon)
    {
        $this->workbench = $workbench;
        $this->prototype = $prototype;
        $this->snippetUid = $snippetUid;
        $this->snippetAlias = $snippetAlias;
        $this->appSelectorString = $appSelector;
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
    public function getSnippetUid() : string
    {
        return $this->snippetUid;
    }

    /**
     * @return string
     */
    public function getSnippetAlias() : string
    {
        return $this->snippetAlias;
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
     * @return AppInterface
     */
    public function getApp() : AppInterface
    {
        return $this->workbench->getApp($this->appSelectorString);
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
        return 'exface.Core.Model.OnBeforeSnippetLoaded';
    }
}