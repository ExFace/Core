<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iSupportLazyLoading;
use exface\Core\Widgets\Traits\iSupportLazyLoadingTrait;
use exface\Core\Exceptions\Widgets\WidgetPropertyNotSetError;

/**
 * InputCombo is similar to InputSelect extended by an autosuggest, that supports lazy loading.
 * It also can optionally accept new values.
 *
 * @see InputCombo
 *
 * @author Andrej Kabachnik
 */
class InputCombo extends InputSelect implements iSupportLazyLoading
{
    use iSupportLazyLoadingTrait {
        getLazyLoadingActionAlias as getLazyLoadingActionAliasViaTrait;
        setLazyLoadingActionAlias as setLazyLoadingActionAliasViaTrait;
    }
    
    // FIXME move default value to template config option WIDGET.INPUTCOMBO.MAX_SUGGESTION like PAGE_SIZE of tables
    private $max_suggestions = 20;

    private $allow_new_values = false;

    private $autoselect_single_suggestion = true;

    /**
     * Returns the alias of the action to be called by the lazy autosuggest.
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::getLazyLoadingActionAlias()
     */
    public function getLazyLoadingActionAlias()
    {
        try {
            $result = $this->getLazyLoadingActionAliasViaTrait();
        } catch (WidgetPropertyNotSetError $e) {
            $this->setLazyLoadingActionAlias('exface.Core.Autosuggest');
            $result = $this->getLazyLoadingActionAliasViaTrait();
        }
        return $result;
    }

    /**
     * Defines the alias of the action to be called by the autosuggest.
     * 
     * Default: exface.Core.Autosuggest.
     *
     * @uxon-property lazy_loading_action_alias
     * @uxon-type string
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iSupportLazyLoading::setLazyLoadingActionAlias()
     */
    public function setLazyLoadingActionAlias($value)
    {
        return $this->setLazyLoadingActionAliasViaTrait($value);
    }

    public function getAllowNewValues()
    {
        return $this->allow_new_values;
    }

    /**
     * Set to TRUE to allow values not present in the autosuggest - FALSE by default.
     *
     * @uxon-property allow_new_values
     * @uxon-type boolean
     *
     * @param boolean $value            
     * @return \exface\Core\Widgets\InputCombo
     */
    public function setAllowNewValues($value)
    {
        $this->allow_new_values = \exface\Core\DataTypes\BooleanDataType::cast($value);
        return $this;
    }

    public function getMaxSuggestions()
    {
        return $this->max_suggestions;
    }

    /**
     * Limits the number of suggestions loaded for every autosuggest.
     *
     * @uxon-property max_suggestions
     * @uxon-type number
     *
     * @param integer $value            
     * @return \exface\Core\Widgets\InputCombo
     */
    public function setMaxSuggestions($value)
    {
        $this->max_suggestions = intval($value);
        return $this;
    }

    public function getAutoselectSingleSuggestion()
    {
        return $this->autoselect_single_suggestion;
    }

    /**
     * Set to FALSE to disable automatic selection of the suggested value if only one suggestion found.
     *
     * @uxon-property autoselect_single_suggestion
     * @uxon-type boolean
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