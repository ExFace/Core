<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;

/**
 * A carousel with each slide being a widget.
 * 
 * The carousel is similar to the `Tabs` widget, but looks differently. It has a
 * nav-strip to switch slides and a large display area showing one slide at a time.
 * Each slide of the carousel is a `Tab` widget and can contain any number of widgets.
 * 
 * The first slide is visible by default. To see the other slides, the user mus navigate
 * using the nav-strip. You can also make any other slide visible by default by
 * setting the `active_slide` property to the sequential number of the slide (starting with 0!).
 * 
 * The nav-strip can be positioned explicitly using the `nav_position` property. It's
 * appearance can be customized by giving slides captions and/or icons. To force an
 * icon-only nav-strip, set `hide_nav_captions` to `true`. All these properties are
 * optional however - if none of them are set, the facade will render it's default
 * version of a carousel.
 * 
 * ## Examples
 * 
 * ### Carousel with data widgets
 * 
 * ```
 *  {
 *      "widget_type": "WidgetCarousel",
 *      "object_alias": "",
 *      "slides": [
 *          {
 *              "widgets"[
 *                  {
 *                      "widget_type": "DataTable"
 *                  }
 *              ]
 *          },
 *          {
 *              "widgets"[
 *                  {
 *                      "widget_type": "Chart"
 *                  }
 *              ]
 *          }
 *      ]
 *  }
 *  
 * ```
 * 
 * ### Image carousel
 * 
 * ### Carousel with data widgets
 * 
 * ```
 *  {
 *      "widget_type": "WidgetCarousel",
 *      "object_alias": "",
 *      "hide_nav_captions",
 *      "slides": [
 *          {
 *              {
 *                  "caption": "Title of image 1",
 *                  "widget_type": "Image",
 *                  "url": "..."
 *                  "width": "100%",
 *                  "height": "100%"
 *              }
 *          },
 *          {
 *              {
 *                  "caption": "Title of image 1",
 *                  "widget_type": "Image",
 *                  "url": "..."
 *                  "width": "100%",
 *                  "height": "100%"
 *              }
 *          }
 *      ]
 *  }
 *  
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetCarousel extends Tabs
{
    /**
     * Override the method to remove the UXON property tabs - the carousel uses slides instead.
     * 
     * @see \exface\Core\Widgets\Tabs::setTabs()
     */
    public function setTabs($widget_or_uxon_array) : Tabs
    {
        return $this->setSlides($widget_or_uxon_array);
    }
    
    /**
     * Slides of the carousel - each is a separate container widget
     * 
     * @uxon-property slides
     * @uxon-type \exface\Core\Widgets\Tab[]|\exface\Core\Widgets\AbstractWidget[]
     * @uxon-template [{"widgets": [{"": ""}]}]
     * 
     * @param UxonObject|Tab $widget_or_uxon_array
     * @return Tabs
     */
    public function setSlides($widget_or_uxon_array) : WidgetCarousel
    {
        return $this->setWidgets($widget_or_uxon_array);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Tabs::setActiveTab()
     */
    public function setActiveTab(int $value) : Tabs
    {
        return $this->setActiveSlide($value);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Tabs::getTabWidgetType()
     */
    protected function getTabWidgetType() : string
    {
        return "WidgetCarouselSlide";
    }
    
    /**
     * Makes the slide with the given index active instead of the first one.
     *
     * @uxon-property active_slide
     * @uxon-type integer
     * @uxon-default 0
     *
     * @param int $value
     * @return Tabs
     */
    public function setActiveSlide(int $value) : WidgetCarousel
    {
        parent::setActiveTab($value);
        return $this;
    }
}