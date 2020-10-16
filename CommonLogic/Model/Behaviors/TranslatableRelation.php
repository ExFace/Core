<?php
namespace exface\Core\CommonLogic\Model\Behaviors;

use exface\Core\Behaviors\TranslatableBehavior;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Factories\RelationPathFactory;

/**
 * Configuration object to translate related data in the dictionary of a head object.
 * 
 * For example, this allows to translate properties of all attributes in the dictionary
 * of their object.
 *
 * @author Andrej Kabachnik
 */
class TranslatableRelation implements iCanBeConvertedToUxon
{
    use ImportUxonObjectTrait;

    /**
     * 
     * @var TranslatableBehavior
     */
    private $behavior = null;
    
    private $translatable_attribute_aliases = [];
    
    private $relation_key = null;
    
    private $relationPath = null;
    
    /**
     * 
     * @param TranslatableBehavior $behavior
     * @param UxonObject $uxon
     */
    public function __construct(TranslatableBehavior $behavior, UxonObject $uxon)
    {
        $this->behavior = $behavior;
        $this->importUxonObject($uxon);
    }
    
    /**
     * Attributes to translate in this relation (with path relative to behavior object!)
     * 
     * @uxon-property translatable_attributes
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * @uxon-required true
     * 
     * @param UxonObject $uxonArray
     * @return TranslatableRelation
     */
    protected function setTranslatableAttributes(UxonObject $uxonArray) : TranslatableRelation
    {
        $this->translatable_attribute_aliases = $uxonArray->toArray();
        return $this;
    }
    
    /**
     * 
     * @param bool $relativeToBehaviorObject
     * @return array
     */
    public function getTranslatableAttributeAliases(bool $relativeToBehaviorObject = true) : array
    {
        if ($relativeToBehaviorObject) {
            return $this->translatable_attribute_aliases;
        } else {
            $aliases = [];
            foreach ($this->translatable_attribute_aliases as $alias) {
                $aliases[] = StringDataType::substringAfter($alias, RelationPath::getRelationSeparator(), $alias, false, true);
            }
            return $aliases;
        }
    }
    
    /**
     * Which attribute of the related object should be used to generate translation keys (with path relative to behavior object!)
     * 
     * The translation keys will look like `<relation_path>.<relation_key_attribute_value>.<translatable_attribute_alias>`.
     * 
     * @uxon-property relation_key_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $aliasWithRelationPathToBehaviorObject
     * @return TranslatableRelation
     */
    protected function setRelationKeyAttributeAlias(string $aliasWithRelationPathToBehaviorObject) : TranslatableRelation
    {
        $this->relation_key = $aliasWithRelationPathToBehaviorObject;
        return $this;
    }
    
    /**
     * 
     * @param bool $relativeToBehaviorObject
     * @return string
     */
    public function getRelationKeyAttributeAlias(bool $relativeToBehaviorObject = true) : string
    {
        if ($relativeToBehaviorObject) {
            return $this->relation_key;
        } else {
            return StringDataType::substringAfter($this->relation_key, RelationPath::getRelationSeparator(), $this->relation_key, false, true);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return new UxonObject([
            'relation_key_attribute_alias' => $this->getRelationKeyAttributeAlias(),
            'translatable_attributes' => $this->getTranslatableAttributeAliases()
        ]);
    }

    /**
     * 
     * @return MetaObjectInterface
     */
    public function getObject() : MetaObjectInterface
    {
        return $this->behavior->getObject();
    }
    
    /**
     * 
     * @throws BehaviorConfigurationError
     * @return MetaRelationPathInterface
     */
    public function getRelationPath() : MetaRelationPathInterface
    {
        $relPath = '';
        foreach ($this->getTranslatableAttributeAliases() as $alias) {
            $thisRelPath = StringDataType::substringBefore($alias, RelationPath::getRelationSeparator(), '', false, true);
            if ($relPath === '') {
                $relPath = $thisRelPath;
            } else {
                if ($thisRelPath !== $relPath) {
                    throw new BehaviorConfigurationError($this->getObject(), 'Invalid configuration for translatable relation: only attributes of a single related object allowed!');
                }
            }
        }
        return RelationPathFactory::createFromString($this->getObject(), $relPath);
    }
}
