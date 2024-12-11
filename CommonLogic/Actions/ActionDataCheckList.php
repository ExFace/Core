<?php
namespace exface\Core\CommonLogic\Actions;

use exface\Core\CommonLogic\EntityList;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\ActionDataCheckListInterface;
use exface\Core\Interfaces\DataSheets\DataCheckInterface;
use exface\Core\Interfaces\DataSheets\DataCheckListInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 * @method DataCheckInterface get()
 * @method DataCheckInterface getFirst()
 * @method DataCheckInterface|DataCheckInterface[] getIterator()
 *        
 */
class ActionDataCheckList extends EntityList implements ActionDataCheckListInterface
{
    private $disabled = false;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionDataCheckListInterface::getAction()
     */
    public function getAction() : ActionInterface
    {
        return $this->getParent();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataCheckListInterface::getForObject()
     */
    public function getForObject(MetaObjectInterface $object) : DataCheckListInterface
    {
        return $this->filter(function(DataCheckInterface $check) use ($object) {
            return $check->isApplicableToObject($object);
        });
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionDataCheckListInterface::disableAll()
     */
    public function setDisabled(bool $trueOrFalse): ActionDataCheckListInterface
    {
        $this->disabled = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionDataCheckListInterface::isDisabled()
     */
    public function isDisabled() : bool
    {
        return $this->disabled;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\EntityList::getAll()
     * @return DataCheckInterface[]
     */
    public function getAll(bool $includeInputMapperCheks = true)
    {
        $result = parent::getAll();
        if ($includeInputMapperCheks === true) {
            foreach ($this->getAction()->getInputMappers() as $inputMapper) {
                $result = array_merge($result, $inputMapper->getFromDataChecks());
            }
        }
        return $result;
    }
}