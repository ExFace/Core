<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Widgets\Traits\iSupportLazyLoadingTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\CommonLogic\DataSheets\DataAggregation;

/**
 * InputCombo is similar to InputSelect extended by an autosuggest, that supports lazy loading.
 * It also can optionally accept new values.
 * 
 * @see InputSelect
 *
 * @author Andrej Kabachnik
 */
class InputCombo extends InputSelect implements iSupportLazyLoading
{
    use iSupportLazyLoadingTrait {
        setLazyLoadingAction as setLazyLoadingActionViaTrait;
    }
    
    // FIXME move default value to facade config option WIDGET.INPUTCOMBO.MAX_SUGGESTION like PAGE_SIZE of tables
    private $max_suggestions = 20;

    private $allow_new_values = null;

    private $autoselect_single_suggestion = true;

    /**
     * Defines the alias of the action to be called by the autosuggest.
     * 
     * @uxon-property lazy_loading_action
     * @uxon-type \exface\Core\CommonLogic\AbstractAction
     * @uxon-template {"alias": "exface.Core.Autosuggest"}
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingAction()
     */
    public function setLazyLoadingAction(UxonObject $uxon) : iSupportLazyLoading
    {
        $this->setLazyLoadingActionViaTrait($uxon);
        return $this;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see \exface\Core\Widgets\Traits\iSupportLazyLoadingTrait::getLazyLoadingActionUxonDefault()
     */
    protected function getLazyLoadingActionUxonDefault() : UxonObject
    {
        return new UxonObject([
           "alias" => "exface.Core.AutoSuggest" 
        ]);
    }
    
    /**
     * Returns the relation, this widget represents or FALSE if the widget stands for a direct attribute.
     * This shortcut function is very handy because a InputComboTable often stands for a relation.
     *
     * @return MetaRelationInterface|NULL
     */
    public function getRelation() : ?MetaRelationInterface
    {
        if ($this->isRelation()) {
            $relAlias = DataAggregation::stripAggregator($this->getAttributeAlias());
            return $this->getMetaObject()->getRelation($relAlias);
        }
        return null;
    }
    
    /**
     *
     * @return bool
     */
    public function isRelation() : bool
    {
        return $this->isBoundToAttribute() === true && $this->getAttribute()->isRelation() === true;
    }

    /**
     * 
     * @param bool $default
     * @return bool
     */
    public function getAllowNewValues() : bool
    {
        if ($this->allow_new_values === null) {
            return ! $this->isRelation();
        }
        return $this->allow_new_values;
    }

    /**
     * Set to TRUE to allow values not present in the autosuggest or FALSE to forbid.
     * 
     * By default, new values are allowed unless the widget is used for a relation
     * (i.e. for selecting foreign keys).
     *
     * @uxon-property allow_new_values
     * @uxon-type boolean
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\InputCombo
     */
    public function setAllowNewValues(bool $value) : InputCombo
    {
        $this->allow_new_values = $value;
        return $this;
    }

    public function getMaxSuggestions()
    {
        return $this->max_suggestions;
    }

    /**
     * Limits the number of suggestions loaded for every autosuggest.
     * 
     * The default value depends on the facade used.
     *
     * @uxon-property max_suggestions
     * @uxon-type integer
     *
     * @param integer $value            
     * @return \exface\Core\Widgets\InputCombo
     */
    public function setMaxSuggestions($value)
    {
        $this->max_suggestions = intval($value);
        return $this;
    }

    public function getAutoselectSingleSuggestion() : bool
    {
        return $this->autoselect_single_suggestion;
    }

    /**
     * Set to FALSE to disable automatic selection of the suggested value if only one suggestion found.
     *
     * @uxon-property autoselect_single_suggestion
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\InputCombo
     */
    public function setAutoselectSingleSuggestion($value)
    {
        $this->autoselect_single_suggestion = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }
}
?>