<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\ButtonGroup;
use exface\Core\Interfaces\Actions\iReadData;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Factories\ActionFactory;
use exface\Core\Actions\SaveData;
use exface\Core\DataTypes\UrlDataType;
use exface\Core\Widgets\Button;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;

/**
 * Helps implement ImageCarousel widgets with jQuery and the slick.
 * 
 * See
 * 
 * - EuiImageCarousel (jEasyUI facade) for an example.
 * - https://kenwheeler.github.io/slick for more configuration options.
 * - https://github.com/mreq/slick-lightbox for lightbox zoom options.
 * 
 * To use this trait, you will need the following JS dependency:
 * 
 * ```
 * "npm-asset/slick-carousel": "^1.8",
 * "npm-asset/slick-lightbox" : "~0.2.12"
 * 
 * ```
 * 
 * If your facade is based on the `AbstractAjaxFacade`, add these configuration options
 * to the facade config file. Make sure, each config option points to an existing
 * inlcude file!
 * 
 * ```
 *  "LIBS.SLICK.SLICK_JS": "npm-asset/slick-carousel/slick/slick.min.js",
 *	"LIBS.SLICK.SLICK_CSS": "npm-asset/slick-carousel/slick/slick.css",
 *	"LIBS.SLICK.THEME_CSS": "npm-asset/slick-carousel/slick/slick-theme.css",
 *	"LIBS.SLICK.LIGHTBOX_JS": "npm-asset/slick-lightbox/dist/slick-lightbox.min.js",
 *	"LIBS.SLICK.LIGHTBOX_CSS": "npm-asset/slick-lightbox/dist/slick-lightbox.css",
 *	
 * ```
 * 
 * For the horizontal slider to work automatically with images of different
 * diemnsions, add the following code to the facade's CSS. This makes sure,
 * the image height allways fits the height of the element.
 * 
 * ```
.slick-carousel.horizontal .slick-list {padding: 10px; box-sizing: border-box;}
.slick-carousel.horizontal .slick-list, 
.slick-carousel.horizontal .slick-track, 
.slick-carousel.horizontal .slick-slide, 
.slick-carousel.horizontal .slick-slide img {height: 100%}
.slick-carousel.horizontal .slick-slide {margin-left: 10px; box-sizing: border-box;}
.slick-carousel.horizontal .slick-prev.slick-arrow {left: 5px; z-index: 1;}
.slick-carousel.horizontal .slick-next.slick-arrow {right: 5px; z-index: 1;}
.slick-carousel.horizontal .slick-arrow:before {color: #404040}
.slick-carousel .slick-slide.selected {border: 5px solid #9cc8f7}
.slick-lightbox .slick-prev.slick-arrow {z-index: 1}

 * ```
 * 
 * @link https://kenwheeler.github.io/slick/
 * 
 * @author Andrej Kabachnik
 * 
 * @method \exface\Core\Widgets\Imagegallery getWidget()
 *        
 */
trait SlickGalleryTrait
{
    private $btnZoom = null;
    
    /**
     * Returns the JS code to read data and populate the carousel with slides.
     *
     * Should be overridden by facade implementations
     *
     * @return string
     */
    protected abstract function buildJsDataSource() : string;
    
    protected abstract function buildJsUploaderInit() : string;
    
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
    setTimeout(function(){
        {$this->buildJsFunctionPrefix()}_load();
    }, 0);

JS;
    }

    /**
     * Returns the JS code defining all sorts of functions to be used by this element.
     * 
     * @return string
     */
    protected function buildJsCarouselFunctions() : string
    {
        if ($this->getWidget()->isZoomable()) {
            if ($this->getWidget()->hasImageTitleColumn()) {
                $lightboxCaption = "caption: function(element, info){var oData = $('#{$this->getId()}').data('_exfData'); if (oData) {return (oData.rows || [])[info.index]['{$this->getWidget()->getImageTitleColumn()->getDataColumnName()}'] } else {return '';} },";
            }
            
            $zoomOnClickJs = $this->getWidget()->getHideHeader() ? 'true' : 'false';
            
            $lightboxInit = <<<JS

    $("#{$this->getId()}")
    .slickLightbox({
        src: 'src',
        itemSelector: '.imagecarousel-item img',
        shouldOpen: function(slick, element, event){
            return $("#{$this->getId()}").data('_exfZoomOnClick') || false;
        },
        {$lightboxCaption}
    })
    .data('_exfZoomOnClick', {$zoomOnClickJs});

JS;
        } else {
            $lightboxInit = '';
        }
        $output = <<<JS

function {$this->buildJsFunctionPrefix()}_init(){
    
    $("#{$this->getId()}").slick({
        infinite: false,
        {$this->buildJsSlickOrientationOptions()}
        {$this->buildJsSlickOptions()}
    });
    {$lightboxInit}
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
        return "";
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
        $facade = $this->getFacade();
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.SLICK.SLICK_JS') . '"></script>';
        $includes[] = '<link rel="stylesheet" type="text/css" href="' . $facade->buildUrlToSource('LIBS.SLICK.SLICK_CSS') . '">';
        $includes[] = '<link rel="stylesheet" type="text/css" href="' . $facade->buildUrlToSource('LIBS.SLICK.THEME_CSS') . '">';
        
        if ($this->getWidget()->isZoomable()) {
            $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.SLICK.LIGHTBOX_JS') . '"></script>';
            $includes[] = '<link rel="stylesheet" type="text/css" href="' . $facade->buildUrlToSource('LIBS.SLICK.LIGHTBOX_CSS') . '">';
        }
        
        return $includes;
    }
    
    /**
     * 
     * @param ButtonGroup $btnGrp
     * @param int $index
     */
    protected function addCarouselFeatureButtons(ButtonGroup $btnGrp, int $index = 0) : void
    {        
        $this->btnZoom = $btnGrp->addButton($btnGrp->createButton(new UxonObject([
            'widget_type' => 'DataButton',
            'icon' => 'arrows-alt',
            'caption' => 'Zoom',
            'align' => 'right',
            'action' => [
                'alias' => 'exface.Core.CustomFacadeScript',
                'script' => <<<JS
                    var jqActiveSlide;
                    var jqCarousel = $('#{$this->getId()}');
                    jqCarousel.data('_exfZoomOnClick', true); 
                    jqActiveSlide = jqCarousel.find('.imagecarousel-item.selected');
                    if (jqActiveSlide.length === 0) {
                        jqActiveSlide = jqCarousel.find('.imagecarousel-item:first-of-type'); 
                    }
                    jqActiveSlide.find('img').click();
                    setTimeout(function(){ 
                        jqCarousel.data('_exfZoomOnClick', false) 
                    }, 100);
JS
            ]
        ])), $index);
        
        return;
    }
    
    /**
     * 
     * @return Button|NULL
     */
    protected function getZoomButton() : ?Button
    {
        return $this->btnZoom;
    }
    
    /**
     * 
     * @see AbstractJqueryElement::buildJsValueGetter()
     */
    public function buildJsValueGetter($columnName = null, $row = null)
    {
       if (is_null($columnName)) {
            if ($this->getWidget()->hasUidColumn() === true) {
                $col = $this->getWidget()->getUidColumn();
            } else {
                throw new FacadeRuntimeError('Cannot create a value getter for a data widget without a UID column: either specify a column to get the value from or a UID column for the table.');
            }
        } else {
            if (! $col = $this->getWidget()->getColumnByDataColumnName($columnName)) {
                $col = $this->getWidget()->getColumnByAttributeAlias($columnName);
            }
        }
        
        $delimiter = $col->isBoundToAttribute() ? $col->getAttribute()->getValueListDelimiter() : EXF_LIST_SEPARATOR;
        
        return <<<JS
(function(){
    var aSelectedRows = {$this->buildJsDataGetter(ActionFactory::createFromString($this->getWorkbench(), SaveData::class))}.rows;
    var aVals = [];
    aSelectedRows.forEach(function(oRow){
        aVals.push(oRow['{$col->getDataColumnName()}']);
    })
    return aVals.join('{$delimiter}');
})()
JS;
    }
    
    /**
     * 
     * @see AbstractJqueryElement::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        $widget = $this->getWidget();
        
        switch (true) {
            case $action === null:
                return "($('#{$this->getId()}').data('_exfData') || {oId: '{$widget->getMetaObject()->getId()}', rows: []})";
                break;
            case $action instanceof iReadData:
                // If we are reading, than we need the special data from the configurator
                // widget: filters, sorters, etc.
                return $this->getFacade()->getElement($widget->getConfiguratorWidget())->buildJsDataGetter($action);
        }
        
        return <<<JS
(function(){
    var jqCarousel = $('#{$this->getId()}');
    var aSelectedRows = [];
    var aAllRows = ((jqCarousel.data('_exfData') || {}).rows || []);
    jqCarousel.find('.imagecarousel-item.selected').each(function(i, jqItem){
        aSelectedRows.push(aAllRows[$(jqItem).index()]);
    });
    return {
        oId: '{$widget->getMetaObject()->getId()}',
        rows: aSelectedRows
    };
})()
JS;
    }
    
    protected function buildJsCarouselSlidesFromData(string $jqSlickJs, string $oDataJs) : string
    {
        $widget = $this->getWidget();
        if (($urlType = $widget->getImageUrlColumn()->getDataType()) && $urlType instanceof UrlDataType) {
            $base = $urlType->getBaseUrl();
        }
        
        return <<<JS

                (function(){
                    var src = '';
                    var title = '';
    				var aRows = $oDataJs.rows;
                    $jqSlickJs.data('_exfData', json);
    
                    $jqSlickJs.slick('removeSlide', null, null, true);
    
    				for (var i in aRows) {
                        src = '{$base}' + aRows[i]['{$widget->getImageUrlColumn()->getDataColumnName()}'];
                        title = aRows[i]['{$widget->getImageTitleColumn()->getDataColumnName()}'];
                        $jqSlickJs.slick('slickAdd', {$this->buildJsSlideTemplate("'<img src=\"' + src + '\" title=\"' + title + '\" alt=\"' + title + '\" />'")});
                    }
    
                    $('#{$this->getId()} .imagecarousel-item').click(function(e) {
                        $('#{$this->getId()} .imagecarousel-item').removeClass('selected');
                        $(e.target).closest('.imagecarousel-item').addClass('selected');
                    });
                })();

JS;
    }
    
    protected function buildJsSlideTemplate(string $imgJs) : string
    {
        return "'<div class=\"imagecarousel-item\">' + {$imgJs} + '</div>'";
    }
    
    /**
     * Returns a JS snippet, that empties the table (removes all rows).
     *
     * @return string
     */
    protected function buildJsDataResetter() : string
    {
        return <<<JS
        
            $('#{$this->getId()} .slick-track').empty();
            $('#{$this->getId()}').data('_exfData', {});
           
JS;
    }
    
    /**
     * 
     * @see AbstractJqueryElement::buildCssWidthDefaultValue()
     */
    protected function buildCssWidthDefaultValue() : string
    {
        return $this->getWidget()->isHorizontal() ? '100%' : $this->getWidthRelativeUnit() . 'px';
    }
    
    /**
     * Makes a slick carousel have a default height of 6
     *
     * @see AbstractJqueryElement::buildCssHeightDefaultValue()
     */
    protected function buildCssHeightDefaultValue()
    {
        return $this->getWidget()->isHorizontal() ? ($this->getHeightRelativeUnit() * 6) . 'px' : '100%';
    }
}