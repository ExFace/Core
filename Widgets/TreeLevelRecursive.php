<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\IntegerDataType;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
class TreeLevelRecursive extends TreeLevel
{    
    private $recursionLevels = null;
    
    private $rootUid = null;

    /**
     * @return integer
     */
    public function getRecursionLevels()
    {
        return $this->recursionLevels;
    }

    /**
     * @param integer $recursionLevels
     * @return TreeLevelRecursive
     */
    public function setRecursionLevels($number)
    {
        $this->recursionLevels = IntegerDataType::cast($number);
        return $this;
    }    
    
    /**
     * 
     * @return string
     */
    public function getRootUid()
    {
        return $this->rootUid;
    }
    
    /**
     * 
     * @param string $string
     * @return TreeLevelRecursive
     */
    public function setRootUid($string)
    {
        $this->rootUid = $string;
        return $this;
    }
}
?>