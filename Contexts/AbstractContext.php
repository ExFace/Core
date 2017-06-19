<?php
namespace exface\Core\Contexts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;

abstract class AbstractContext implements ContextInterface
{

    private $exface = null;

    private $scope = null;

    private $alias = null;
    
    private $indicator = null;
    
    private $visibility = null;

    public function __construct(\exface\Core\CommonLogic\Workbench $exface)
    {
        $this->exface = $exface;
    }

    /**
     * Returns the scope of this speicific context
     *
     * @return AbstractContextScope
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * Sets the scope for this specific context
     *
     * @param AbstractContextScope $context_scope            
     * @return AbstractContext
     */
    public function setScope(ContextScopeInterface $context_scope)
    {
        $this->scope = $context_scope;
        return $this;
    }

    /**
     * Returns the default scope for this type of context.
     *
     * @return \exface\Core\Contexts\Scopes\windowContextScope
     */
    public function getDefaultScope()
    {
        return $this->getWorkbench()->context()->getScopeWindow();
    }

    /**
     *
     * @return \exface\Core\CommonLogic\Workbench
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     * Returns a serializable UXON object, that represents the current contxt, thus preparing it to be saved in a session,
     * cookie, database or whatever is used by a context scope.
     * What exactly ist to be saved, strongly depends on the context type: an action context needs an acton alias and, perhaps, a data backup,
     * a filter context needs to save it's filters conditions, etc. In any case, the serialized version should contain enoght
     * data to restore the context completely afterwards, but also not to much data in order not to consume too much space in
     * whatever stores the respective context scope.
     *
     * @return UxonObject
     */
    public function exportUxonObject()
    {
        return $this->getWorkbench()->createUxonObject();
    }

    /**
     * Restores a context from it's UXON representation.
     * The input is whatever export_uxon_object() produces for this context type.
     *
     * @param
     *            UxonObject
     * @return AbstractContext
     */
    public function importUxonObject(UxonObject $uxon)
    {
        return $this;
    }

    /**
     * Returns the alias (name) of the context - e.g.
     * "Filter" for the FilterContext, etc.
     *
     * @return string
     */
    public function getAlias()
    {
        if (! $this->alias) {
            $this->alias = substr(get_class($this), (strrpos(get_class($this), "\\") + 1), - 7);
        }
        return $this->alias;
    }
    
    /**
     * @return string
     */
    public function getIndicator()
    {
        return $this->indicator;
    }
    
    /**
     * 
     * @param string $indicator
     * @return \exface\Core\Contexts\AbstractContext
     */
    public function setIndicator($indicator)
    {
        $this->indicator = $indicator;
        return $this;
    }
    
    /**
     * Returns the visibility of this context.
     * 
     * @return string
     */
    public function getVisibility()
    {
        if (is_null($this->visibility)){
            $this->setVisibility(EXF_WIDGET_VISIBILITY_NORMAL);
        }
        return $this->visibility;
    }
    
    /**
     * Sets the visibility of the context. Accepts one of the EXF_WIDGET_VISIBILITY_xxx constants.
     *
     * @param string $visibility
     * @return \exface\Core\Contexts\AbstractContext
     */
    public function setVisibility($value)
    {
        $value = mb_strtolower($value);
        if ($value != EXF_WIDGET_VISIBILITY_HIDDEN && $value != EXF_WIDGET_VISIBILITY_NORMAL && $value != EXF_WIDGET_VISIBILITY_OPTIONAL && $value != EXF_WIDGET_VISIBILITY_PROMOTED) {
            throw new \UnexpectedValueException('Invalid visibility value "' . $value . '" for context "' . $this->getAlias() . '"!');
            return;
        }
        $this->visibility = $value;
        return $this;
    }
 
}
?>