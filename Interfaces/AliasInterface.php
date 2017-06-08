<?php
namespace exface\Core\Interfaces;

interface AliasInterface
{
    
    /**
     * Returns the alias of this instance (e.g. "my_object" for "my.app.my_object")
     * 
     * @return string
     */
    public function getAlias();
    
    /**
     * Returns the alias of this instance with the respective namespace (e.g. "my.app.my_object" for "my.app.my_object")
     *
     * @return string
     */
    public function getAliasWithNamespace();
    
    /**
     * Returns the namespace of this instance (e.g. "my.app" for "my.app.my_object")
     *
     * @return string
     */
    public function getNamespace();
}
?>