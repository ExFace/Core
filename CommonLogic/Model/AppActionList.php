<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\AppInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class AppActionList extends ActionList
{

    public function getApp()
    {
        return $this->getParent();
    }

    public function setApp(AppInterface $value)
    {
        $this->setParent($value);
        return $this;
    }
}