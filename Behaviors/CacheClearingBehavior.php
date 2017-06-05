<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\AbstractBehavior;

/**
 * This behavior clears the workbench cache every time data of the object is
 * saved, updated or deleted.
 * 
 * @author Andrej Kabachnik
 *
 */
class CacheClearingBehavior extends AbstractBehavior
{
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractBehavior::register()
     */
    public function register()
    {
        $clearCacheMethod = array(
            $this->getWorkbench(),
            'clearCache'
        );
        $this->getWorkbench()->eventManager()->addListener($this->getObject()->getAliasWithNamespace() . '.DataSheet.UpdateData.After', $clearCacheMethod);
        $this->getWorkbench()->eventManager()->addListener($this->getObject()->getAliasWithNamespace() . '.DataSheet.CreateData.After', $clearCacheMethod);
        $this->getWorkbench()->eventManager()->addListener($this->getObject()->getAliasWithNamespace() . '.DataSheet.DeleteData.After', $clearCacheMethod);
        $this->setRegistered(true);
    }

}

?>