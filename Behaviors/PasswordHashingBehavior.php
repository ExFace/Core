<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\DataTypes\PasswordHashDataType;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;

/**
 * This behavior will hash password attribute values when data is created or updated.
 * 
 * **NOTE:** The attribute MUST have the data type "exface.Core.PasswordHash" to work with this behavior!
 * 
 * @author Andrej Kabachnik
 *
 */
class PasswordHashingBehavior extends AbstractBehavior
{    
    private $passwordAttribute = null;
    
    private $hashAlgorithm = null;

    public function register() : BehaviorInterface
    {
        $this->getPasswordAttribute()->getDataType()->setSensitiveData(true);
        // Give the event handlers a hight priority to make sure, the passwords are encoded before
        // any other behaviors get their hands on the data!
        $this->getWorkbench()->eventManager()
        ->addListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'handleOnCreateEvent'], 1000)
        ->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'handleOnCreateEvent'], 1000);
        $this->setRegistered(true);
        return $this;
    }
    
    /**
     * 
     * @param DataSheetEventInterface $event
     * @throws BehaviorConfigurationError
     */
    public function handleOnCreateEvent(DataSheetEventInterface $event) 
    {
        if ($this->isDisabled()) {
            return;
        }
            
        $data_sheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $data_sheet->getMetaObject()->is($this->getObject())) {
            return;
        }
        
        if ($data_sheet->hasAggregations() || $data_sheet->hasAggregateAll()) {
            return;
        }
        
        // Check if the updated_on column is present in the sheet
        if ($column = $data_sheet->getColumns()->getByAttribute($this->getPasswordAttribute())) {
            $type = $column->getDataType();
            if (! ($type instanceof PasswordHashDataType)) {
                throw new BehaviorConfigurationError($this->getObject(), 'Cannot use PasswordHashingBehavior on attribute "' . $this->getPasswordAttributeAlias() . '": the attribute MUST have the data type "exface.Core.PasswordHash"!');
            }
            foreach ($column->getValues(false) as $rowNr => $value) {
                if ($type::isHash($value) === false && ! $type->isValueEmpty($value)) {
                    $column->setValue($rowNr, $type->hash($type->parse($value)));
                }
            }
        }
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
}