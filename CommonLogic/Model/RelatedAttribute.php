<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;

class RelatedAttribute implements MetaAttributeInterface
{
    private $original_attribute = null;
    
    private $relation_path = null;
    
    private $object = null;
    
    public function getRelationPath()
    {
        if (is_null($this->relation_path)) {
            return $this->getOriginalAttribute()->getRelationPath();
        }
        return $this->relation_path;
    }
    
    public function setRelationPath(MetaRelationPathInterface $path)
    {
        $this->relation_path = $path;
    }
    
    public function getOriginalAttribute()
    {
        return $this->original_attribute;
    }
    
    public function setOriginalAttribute(MetaAttributeInterface $attribute)
    {
        $this->original_attribute = $attribute;
        return $this;
    }
    
    /**
     * For every method not exlicitly defined in this class, call the original attribute.
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call($method, $arguments){
        return call_user_func_array(array($this->getOriginalAttribute(), $method), $arguments);
    }
}
