<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Widgets\ImageCarousel;
use exface\Core\DataTypes\UrlDataType;
use exface\Core\Widgets\DataImageGallery;

/**
 * Helps implement ImageCarousel widgets with jQuery and the slick carousel.
 * 
 * See euiImageCarousel (jEasyUI template) for an example.
 * 
 * To use this trait, you will need the following JS dependency:
 * 
 * ```
 * "npm-asset/slick-carousel": "^1.8"
 * 
 * ```
 * 
 * For the horizontal slider to work automatically with images of different
 * diemnsions, add the following code to the template's CSS. This makes sure,
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
 * @method DataImageGallery getWidget()
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
     * This method is intended to be overridden in templates, so they can add
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
        if ($this->getWidget()->getOrientation() === $this->getWidget()::ORIENTATION_VERTICAL) {
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
     * Should be overridden by template implementations
     * 
     * @return string
     */
    public function buildJsDataSource() : string
    {
        $widget = $this->getWidget();
        
        if (($urlType = $widget->getImageUrlColumn()->getDataType()) && $urlType instanceof UrlDataType) {
            $base = $urlType->getBaseUrl();
        }
        
        return <<<JS

    if ($('#{$this->getId()}').data('_loading')) return;
	{$this->buildJsBusyIconShow()}
	$('#{$this->getId()}').data('_loading', 1);
	var data = {};
    data.action = '{$widget->getLazyLoadingActionAlias()}';
	data.resource = "{$widget->getPage()->getAliasWithNamespace()}";
	data.element = "{$widget->getId()}";
	data.object = "{$widget->getMetaObject()->getId()}";
	data.data = {$this->getTemplate()->getElement($widget->getConfiguratorWidget())->buildJsDataGetter()};
    
	$.ajax({
       url: "{$this->getAjaxUrl()}",
       data: data,
       method: 'POST',
       success: function(json){
			try {
				var data = json.rows;
                var carousel = $('#{$this->getId()}');
                var src = '';
                var title = '';
				for (var i in data) {
                    src = '{$base}' + data[i]['{$widget->getImageUrlColumn()->getDataColumnName()}'];
                    title = data[i]['{$widget->getImageTitleColumn()->getDataColumnName()}'];
                    carousel.slick('slickAdd', '<div class="imagecarousel-item"><img src="' + src + '" title="' + title + '" alt="' + title + '" /></div>');
                }
		        //{$this->buildJsBusyIconHide()}
		        $('#{$this->getId()}').data('_loading', 0);
			} catch (err) {
                console.error(err);
				//{$this->buildJsBusyIconHide()}
			}
		},
		error: function(jqXHR, textStatus,errorThrown){
		   {$this->buildJsBusyIconHide()}
		   {$this->buildJsShowError('jqXHR.responseText', 'jqXHR.status + " " + jqXHR.statusText')}
		}
	});

JS;
    }
		   
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