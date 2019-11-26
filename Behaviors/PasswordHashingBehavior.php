<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;

/**
 * This behavior will hash password attribute values when data is created or updated.
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
        // Give the event handlers a hight priority to make sure, the passwords are encoded before
        // any other behaviors get their hands on the data!
        $this->getWorkbench()->eventManager()
        ->addListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'handleOnCreateEvent'], 1000)
        ->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'handleOnUpdateEvent'], 1000);
        $this->setRegistered(true);
        return $this;
    }
    
    public function handleOnCreateEvent(OnBeforeCreateDataEvent $event) 
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
        
        // Check if the updated_on column is present in the sheet
        if ($column = $data_sheet->getColumns()->getByAttribute($this->getPasswordAttribute())) {
            foreach ($column->getValues(false) as $rowNr => $value) {
                $column->setValue($rowNr, $this->hash($value));
            }
        }
        return;
    }
    
    public function handleOnUpdateEvent(OnBeforeUpdateDataEvent $event)
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
        
        // Check if the updated_on column is present in the sheet
        if ($column = $data_sheet->getColumns()->getByAttribute($this->getPasswordAttribute())) {
            foreach ($column->getValues(false) as $rowNr => $value) {
                if ($this->isHash($value) === false) {
                    $column->setValue($rowNr, $this->hash($value));
                }
            }
        }
        return;
    }
    
    protected function isHash(string $password) : bool
    {
        $nfo = password_get_info($password);
        return $nfo['algo'] !== 0;
    }
    
    protected function hash(string $password) : string
    {
        return password_hash($password, $this->getHashAlgorithmConstant());
    }

    public function getPasswordAttributeAlias()
    {
        return $this->passwordAttribute;
    }

    /**
     * Alias of the attribute holding the password to be hashed.
     * 
     * @uxon-property password_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return PasswordHashingBehavior
     */
    public function setPasswordAttributeAlias(string $value) : PasswordHashingBehavior
    {
        $this->passwordAttribute = $value;
        return $this;
    }

    public function getCheckForConflictsOnUpdate()
    {
        return $this->check_for_conflicts_on_update;
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
        $uxon->setProperty('hash_algorithm', $this->getHashAlgorithm());
        return $uxon;
    }
    
    /**
     *
     * @return int
     */
    protected function getHashAlgorithmConstant() : int
    {
        if ($this->hashAlgorithm !== null) {
            return constant('PASSWORD_' . strtoupper($this->hashAlgorithm));
        } else {
            return PASSWORD_DEFAULT;
        }
    }
    
    /**
     * 
     * @return string
     */
    protected function getHashAlgorithm() : string
    {
        return $this->hashAlgorithm;
    }
    
    /**
     * One of the password hashing algorithms suppoerted by PHP.
     * 
     * @link https://www.php.net/manual/en/function.password-hash.php
     * 
     * @uxon-property hash_algorithm
     * @uxon-type [default,bcrypt,argon2i,argon2id]
     * 
     * @param string $value
     * @return PasswordHashingBehavior
     */
    public function setHashAlgorithm(string $value) : PasswordHashingBehavior
    {
        $this->hashAlgorithm = $value;
        return $this;
    }
}