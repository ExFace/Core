<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\ImageCarousel;
use exface\Core\DataTypes\UrlDataType;
use exface\Core\Widgets\Imagegallery;

/**
 * Helps implement ImageCarousel widgets with jQuery and the slick carousel.
 * 
 * See euiImageCarousel (jEasyUI facade) for an example.
 * 
 * To use this trait, you will need the following JS dependency:
 * 
 * ```
 * "npm-asset/slick-carousel": "^1.8"
 * 
 * ```
 * 
 * For the horizontal slider to work automatically with images of different
 * diemnsions, add the following code to the facade's CSS. This makes sure,
 * the image height allways fits the height of the element.
 * 
 * ```
.slick-carousel.horizontal .slick-list, 
.slick-carousel.horizontal .slick-track, 
.slick-carousel.horizontal .slick-slide, 
.slick-carousel.horizontal .slick-slide img {height: 100%}

 * ```
 * 
 * @author Andrej Kabachnik
 * 
 * @method Imagegallery getWidget()
 *        
 */
trait JquerySlickGalleryTrait
{
      
    /**
     * Returns the HTML code of the carousel.
     * 
     * @return string
     */
    protected function buildHtmlCarousel() : string
    {
        return <<<HTML

<div id="{$this->getId()}" class="slick-carousel horizontal" style="height: 100%">
    <!-- Slides will be placed here programmatically -->
</div>
	
HTML;
    }
        
    /**
     * Returns the JS code to initialize the carousel and load initial data.
     * 
     * @return string
     */
    protected function buildJsCarouselInit() : string
    {
        return <<<JS
    
    {$this->buildJsFunctionPrefix()}_init();
    {$this->buildJsFunctionPrefix()}_load();

JS;
    }

    /**
     * Returns the JS code defining all sorts of functions to be used by this element.
     * 
     * @return string
     */
    protected function buildJsCarouselFunctions() : string
    {
        $output = <<<JS

function {$this->buildJsFunctionPrefix()}_init(){
    
    $("#{$this->getId()}").slick({
        infinite: false,
        {$this->buildJsSlickOrientationOptions()}
        {$this->buildJsSlickOptions()}
    });

}

function {$this->buildJsFunctionPrefix()}_load(){
	{$this->buildJsDataSource()}
}

JS;
        
        return $output;
    }
    
    /**
     * Returns the options for slick initialization (ending with a comma!).
     * 
     * This method is intended to be overridden in facades, so they can add
     * their own options like buttons, styling, etc.
     * 
     * @return string
     */
    protected function buildJsSlickOptions() : string
    {
        return <<<JS

        prevArrow: '',
        nextArrow: '',

JS;
    }
        
    protected function buildJsSlickOrientationOptions() : string
    {
        if ($this->getWidget()->isVertical() === true) {
            $verticalOptions = 'vertical: true,';
        }
        return <<<JS
        
        variableWidth: true,
        {$verticalOptions}
JS;
    }

    /**
     * 
     * {@inheritdoc}
     * @see AbstractJqueryElement::buildJsRefresh()
     */
    public function buildJsRefresh($keep_pagination_position = false)
    {
        return $this->buildJsFunctionPrefix() . "_load();";
    }

    /**
     * Returns an array of <head> tags required for this trait to work
     * 
     * @return string[]
     */
    protected function buildHtmlHeadSliderIncludes()
    {
        $includes = [];
        $includes[] = '<script type="text/javascript" src="exface/vendor/npm-asset/slick-carousel/slick/slick.min.js"></script>';
        $includes[] = '<link rel="stylesheet" type="text/css" href="exface/vendor/npm-asset/slick-carousel/slick/slick.css">';
        return $includes;
    }
    
    /**
     * Returns the JS code to read data and populate the carousel with slides.
     * 
     * Should be overridden by facade implementations
     * 
     * @return string
     */
    abstract function buildJsDataSource() : string;
		   
    /**
     * Makes a slick carousel have a default height of 6
     * 
     * @return string
     */
    protected function buildCssHeightDefaultValue()
    {
        return ($this->getHeightRelativeUnit() * 6) . 'px';
    }
}