<?php
namespace exface\Core\Widgets;

class InputHidden extends Input
{
    protected function init()
    {
        parent::init();
        $this->setHidden(true);
        $this->setVisibility(EXF_WIDGET_VISIBILITY_HIDDEN);
    }
    
    /**
     * {@inheritDoc}
     * 
     * Since hidden inputs cannot actually accept any user input, they can only be required if they do
     * not reference an attribute or that attribute has not fallback value (e.g. a default or fixed value).
     * Although the user cannot directly influence a hidden field, it can still be filled by live
     * references. 
     * 
     * @see \exface\Core\Widgets\Input::isRequired()
     */
    public function isRequired()
    {
        $required = parent::isRequired();
        if ($required && ($this->hasAttributeReference() && $this->getAttribute()->hasFallbackValue())) {
            return false;
        }
        return $required;
    }
}
?>