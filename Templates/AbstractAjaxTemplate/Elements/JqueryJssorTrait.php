<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Widgets\ImageCarousel;

/**
 * Helps implement ImageCarousel widgets with jQuery and the Jssor slider.
 * 
 * See euiImageCarousel (jEasyUI template) and lteImageCarousel (AdminLTE tempalte)
 * for examples.
 * 
 * To use this trait, you will need the following JS dependencies:
 * 
 * ```
 * "components/handlebars.js": "^4",
 * "bower-asset/jssor": "^22"
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 * 
 * @method ImageCarousel getWidget()
 *        
 */
trait JqueryJssorTrait
{

    /**
     * 
     * @return string
     */
    protected function buildCssSliderHeight() : string
    {
        return "height: {$this->getHeight()};";
    }
    
    /**
     * 
     * @return string
     */
    protected function buildHtmlSlider() : string
    {
        return <<<HTML

<div id="{$this->getId()}" style="position: relative; margin: 0 auto; top: 0px; left: 0px; {$this->buildCssSliderHeight()} width: 960px; overflow: hidden; visibility: hidden;">
    <div data-u="slides" class="slides" style="cursor: default; position: relative; top: 0px; left: 240px; width: 720px; height: 400px; overflow: hidden;">
        <!-- Slides -->
    </div>
    <!-- Thumbnail Navigator -->
    <div data-u="thumbnavigator" class="jssor-nav" style="position:absolute;left:0px;top:0px;width:240px;height:400px;" data-autocenter="2">
        <!-- Thumbnail Item Skin Begin -->
        <div data-u="slides" style="cursor: default;">
            <div data-u="prototype" class="p">
                <div class="w">
                    <div data-u="thumbnailtemplate" class="t"></div>
                </div>
                <div class="c"></div>
            </div>
        </div>
        <!-- Thumbnail Item Skin End -->
    </div>
    <!-- Arrow Navigator -->
    <span data-u="arrowleft" class="jssora05l" style="top:158px;left:248px;width:40px;height:40px;" data-autocenter="2"></span>
    <span data-u="arrowright" class="jssora05r" style="top:158px;right:8px;width:40px;height:40px;" data-autocenter="2"></span>
</div>
	
HTML;
    }
    
    protected function buildHtmlImageTemplate() : string
    {
        $widget = $this->getWidget();
        return <<<HTML

<script type="text/x-handlebars-template" id="{$this->getId()}_tpl">
{ {#data}}
    <div data-p="150.00" style="display: none;">
		<div data-u="image" class="img-wrap" >
			<img src="{ {{$widget->getImageUrlColumnId()}}}"/>
		</div>
		<div data-u="thumb" class="thumb-wrap">
			<img src="{ {{$widget->getImageUrlColumnId()}}}" />
		</div>
	</div>
{ {/data}}
</script>

HTML;
    }

    /**
     * 
     * @return string
     */
    protected function buildJsSliderInit() : string
    {
        $widget = $this->getWidget();
        $default_sorters = '';
        
        // sorters
        foreach ($widget->getSorters() as $sorter) {
            $column_exists = false;
            foreach ($widget->getColumns() as $nr => $col) {
                if ($col->getAttributeAlias() == $sorter->getProperty('attribute_alias')) {
                    $column_exists = true;
                    $default_sorters .= '[ ' . $nr . ', "' . $sorter->getProperty('direction') . '" ], ';
                }
            }
            if (! $column_exists) {
                // TODO add a hidden column
            }
        }
        // Remove tailing comma
        if ($default_sorters) {
            $default_sorters = substr($default_sorters, 0, - 2);
        }
        
        $output = <<<JS

function {$this->buildJsFunctionPrefix()}startSlider(){
            
    var options = {
      \$AutoPlay: true,
      \$SlideshowOptions: {
        \$Class: \$JssorSlideshowRunner$,
        \$TransitionsOrder: 1
      },
      \$ArrowNavigatorOptions: {
        \$Class: \$JssorArrowNavigator$
      },
      \$ThumbnailNavigatorOptions: {
        \$Class: \$JssorThumbnailNavigator$,
        \$Rows: 1,
        \$Cols: 3,
        \$SpacingX: 14,
        \$SpacingY: 12,
        \$Orientation: 2,
        \$Align: 156
      }
    };
    
    var {$this->getId()}slider = new \$JssorSlider$("{$this->getId()}", options);
    
    // BOF make slider responsive
    function ScaleSlider() {
        var refSize = {$this->getId()}slider.\$Elmt.parentNode.clientWidth;
        if (refSize) {
            //refSize = Math.min(refSize, 960);
            //refSize = Math.max(refSize, 300);
            {$this->getId()}slider.\$ScaleWidth(refSize);
        }
        else {
            window.setTimeout(ScaleSlider, 30);
        }
    }
    ScaleSlider();
    $(window).bind("load", ScaleSlider);
    $(window).bind("resize", ScaleSlider);
    $(window).bind("orientationchange", ScaleSlider);
    // EOF make slider responsive

}

function {$this->buildJsFunctionPrefix()}load(){
	if ($('#{$this->getId()}').data('loading')) return;
	{$this->buildJsBusyIconShow()}
	$('#{$this->getId()}').data('loading', 1);
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
				var data = json;
				if (data.data.length > 0) {
					var template = Handlebars.compile($('#{$this->getId()}_tpl').html().replace(/\{\s\{\s\{/g, '{{{').replace(/\{\s\{/g, '{{'));
			        var elements = $(template(data));
			        $('#{$this->getId()} .slides').append(elements);
			        {$this->buildJsFunctionPrefix()}startSlider();
		        }
		        {$this->buildJsBusyIconHide()}
		        $('#{$this->getId()}').data('loading', 0);
			} catch (err) {
				{$this->buildJsBusyIconHide()}
			}
		},
		error: function(jqXHR, textStatus,errorThrown){
		   {$this->buildJsBusyIconHide()}
		   {$this->buildJsShowError('jqXHR.responseText', 'jqXHR.status + " " + jqXHR.statusText')}
		}
	});
}

JS;
        
        return $output;
    }

    /**
     * 
     * @param boolean $keep_pagination_position
     * @return string
     */
    public function buildJsRefresh($keep_pagination_position = false)
    {
        return $this->buildJsFunctionPrefix() . "load();";
    }

    /**
     * 
     * @return string[]
     */
    public function buildHtmlHeadTags()
    {
        $includes = parent::buildHtmlHeadTags();
        $includes[] = '<link rel="stylesheet" type="text/css" href="exface/vendor/exface/AdminLteTemplate/Templates/js/jssor/skin.css">';
        $includes[] = '<script type="text/javascript" src="exface/vendor/bower-asset/jssor/js/jssor.slider.min.js"></script>';
        return $includes;
    }
}