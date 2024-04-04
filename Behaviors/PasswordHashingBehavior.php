<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\DataTypes\PasswordDataType;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Interfaces\Model\Behaviors\DataModifyingBehaviorInterface;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;

/**
 * This behavior will hash password attribute values when data is created or updated.
 * 
 * **NOTE:** The attribute MUST have the data type "exface.Core.Password" to work with this behavior!
 * 
 * @author Andrej Kabachnik
 *
 */
class PasswordHashingBehavior extends AbstractBehavior implements DataModifyingBehaviorInterface
{    
    private $passwordAttribute = null;
    
    private $hashAlgorithm = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::register()
     */
    public function register() : BehaviorInterface
    {
        $this->getPasswordAttribute()->getDataType()->setSensitiveData(true);
        return parent::register();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        // Give the event handlers a hight priority to make sure, the passwords are encoded before
        // any other behaviors get their hands on the data!
        $this->getWorkbench()->eventManager()
        ->addListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'handleDataEvent'], $this->getPriority())
        ->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'handleDataEvent'], $this->getPriority());
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        // Give the event handlers a hight priority to make sure, the passwords are encoded before
        // any other behaviors get their hands on the data!
        $this->getWorkbench()->eventManager()
        ->removeListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'handleDataEvent'])
        ->removeListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'handleDataEvent']);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::getPriority()
     */
    public function getPriority() : ?int
    {
        return parent::getPriority() ?? 1000;
    }
    
    /**
     * 
     * @param DataSheetEventInterface $event
     * @throws BehaviorConfigurationError
     */
    public function handleDataEvent(DataSheetEventInterface $event) 
    {
        if ($this->isDisabled()) {
            return;
        }
            
        $data_sheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $data_sheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        if ($data_sheet->hasAggregations() || $data_sheet->hasAggregateAll()) {
            return;
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));
        
        // Check if the updated_on column is present in the sheet
        if ($column = $data_sheet->getColumns()->getByAttribute($this->getPasswordAttribute())) {
            $type = $column->getDataType();
            if (! ($type instanceof PasswordDataType)) {
                throw new BehaviorConfigurationError($this, 'Cannot use PasswordHashingBehavior on attribute "' . $this->getPasswordAttributeAlias() . '": the attribute MUST have the data type "exface.Core.PasswordHash"!');
            }
            foreach ($column->getValues(false) as $rowNr => $value) {
                if ($type::isHash($value) === false && ! $type->isValueEmpty($value)) {
                    $column->setValue($rowNr, $type->hash($type->parse($value)));
                }
            }
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event));
        return;
    }

    /**
     * 
     * @return string
     */
    public function getPasswordAttributeAlias() : string
    {
        return $this->passwordAttribute;
    }

    /**
     * Alias of the attribute holding the password to be hashed.
     * 
     * @uxon-property password_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $value
     * @return PasswordHashingBehavior
     */
    public function setPasswordAttributeAlias(string $value) : PasswordHashingBehavior
    {
        $this->passwordAttribute = $value;
        return $this;
    }

    /**
     *
     * @return MetaAttributeInterface
     */
    public function getPasswordAttribute()
    {
        return $this->getObject()->getAttribute($this->getPasswordAttributeAlias());
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('password_attribute_alias', $this->getPasswordAttributeAlias());
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\Behaviors\DataModifyingBehaviorInterface::getAttributesModified()
     */
    public function getAttributesModified(): array
    {
        return [
            $this->getPasswordAttribute()
        ];
    }
    
    public function canAddColumnsToData(): bool
    {
        return false;
    }
}