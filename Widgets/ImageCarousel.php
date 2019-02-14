<?php
namespace exface\Core\Widgets;

use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\iShowData;

/**
 * Shows an carousel with image thumbnails and a large image for the selected thumbnail.
 * 
 * 
 * 
 * @author Andrej Kabachnik
 * 
 * @method Imagegallery getDataWidget()
 *
 */
class ImageCarousel extends DataCarousel
{
    private $image_title_attribute_alias = null;
    
    private $image_url_attribute_alias = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataCarousel::initDataWidget()
     */
    protected function initDataWidget(iShowData $widget) : iShowData
    {
        if (! ($widget instanceof Imagegallery)) {
            throw new WidgetConfigurationError($this, 'Invalid data widget type ' . get_class($widget) . ' for ImageCarousel: The data widget of an ImageCarousel MUST be a Imagegallery or a derivative!');
        }
        
        $widget = parent::initDataWidget($widget);
        
        if ($this->image_url_attribute_alias !== null) {
            $widget->setImageUrlAttributeAlias($this->image_url_attribute_alias);
        }
        
        if ($this->image_title_attribute_alias !== null) {
            $widget->setImageTitleAttributeAlias($this->image_title_attribute_alias);
        }
        
        return $widget;
    }
    
    /**
     * The alias of the attribute with the image URLs
     * 
     * @uxon-property image_url_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return ImageCarousel
     */
    public function setImageUrlAttributeAlias(string $value) : ImageCarousel
    {
        $this->image_url_attribute_alias = $value;
        if ($this->isDataInitialized() === true) {
            $this->getDataWidget()->setImageUrlAttributeAlias($value);
        }        
        return $this;
    }
    
    /**
     * The alias of the attribute with the image titles
     * 
     * @uxon-property image_title_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return ImageCarousel
     */
    public function setImageTitleAttributeAlias(string $value) : ImageCarousel
    {
        $this->image_title_attribute_alias = $value;
        if ($this->isDataInitialized() === true) {
            $this->getDataWidget()->setImageTitleAttributeAlias($value);
        }
        return $this;
    }    
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataCarousel::getDefaultDataWidgetType()
     */
    protected function getDefaultDataWidgetType() : string
    {
        return 'Imagegallery';
    }
}