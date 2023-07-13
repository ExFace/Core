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
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsDateFormatter;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Actions\DownloadFile;
use exface\Core\Widgets\DataButton;

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
.slick-carousel .slick-slide {overflow: hidden;}
.slick-carousel .slick-slide.selected {border: 5px solid #9cc8f7}
.slick-lightbox .slick-prev.slick-arrow {z-index: 1}
.imagecarousel-file {height: 100%; width: 150px; position: relative; background-color: #f5f5f5; cursor: pointer;}
.imagecarousel-file > i {position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 200%}
.imagecarousel-overlay {position: absolute; top: 0; width: 100%; height: 100%}

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
    use JsUploaderTrait;
    
    private $btnZoom = null;
    
    private $btnMinus = null;
    
    private $btnBrowse = null;
    
    private $btnDownload = null;
    
    /**
     *
     * @param string $oParamsJs
     * @param string $onUploadCompleteJs
     * @return string
     */
    protected abstract function buildJsUploadSend(string $oParamsJs, string $onUploadCompleteJs) : string;
    
    /**
     * Returns the HTML code of the carousel.
     * 
     * @return string
     */
    protected function buildHtmlCarousel() : string
    {
        return <<<HTML

<div id="{$this->getIdOfSlick()}" class="slick-carousel horizontal" style="height: 100%">
    <!-- Slides will be placed here programmatically -->
</div>
	
HTML;
    }
    
    protected function getIdOfSlick() : string
    {
        return $this->getId();
    }
    
    protected function buildJsSlickInit() : string
    {
        if ($this->getWidget()->isZoomable()) {
            if ($this->getWidget()->hasImageTitleColumn()) {
                $lightboxCaption = "caption: function(element, info){ return $('<p></p>').html(exfTools.string.nl2br(element.title)).prop('outerHTML'); },";
            }
            
            $zoomOnClickJs = $this->getWidget()->isZoomOnClick() ? 'true' : 'false';
            
            $lightboxInit = <<<JS

    $("#{$this->getIdOfSlick()}")
    .slickLightbox({
        src: 'src-download',
        itemSelector: '.imagecarousel-item img',
        shouldOpen: function(slick, element, event){
            return $("#{$this->getIdOfSlick()}").data('_exfZoomOnClick') || false;
        },
        {$lightboxCaption}
    })
    .data('_exfZoomOnClick', {$zoomOnClickJs});

JS;
        } else {
            $lightboxInit = '';
        }
        
        return <<<JS

    $("#{$this->getIdOfSlick()}").slick({
        infinite: false,
        {$this->buildJsSlickOrientationOptions()}
        {$this->buildJsSlickOptions()}
    });
    {$lightboxInit}
    
    $(document).on('click', '#{$this->getIdOfSlick()} .imagecarousel-item', function(e) {
        var jqEl = $(e.target);
        $('#{$this->getIdOfSlick()} .imagecarousel-item').removeClass('selected');
        jqEl.closest('.imagecarousel-item').addClass('selected');
        {$this->getOnChangeScript()}
    });

    $("#{$this->getIdOfSlick()}").append({$this->escapeString($this->buildHtmlNoDataOverlay(), true, false)});

JS;
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
     * Returns an array of <head> tags required for this trait to work
     * 
     * @return string[]
     */
    protected function buildHtmlHeadTagsSlick()
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
        
        if ($this->getWidget()->isUploadEnabled()) {
            // The jQuery UI widget factory, can be omitted if jQuery UI is already included -->
            $includes[] = '<script src="vendor/bower-asset/blueimp-file-upload/js/vendor/jquery.ui.widget.js"></script>';
            // The Load Image plugin is included for the preview images and image resizing functionality -->
            $includes[] = '<script src="vendor/npm-asset/blueimp-load-image/js/load-image.all.min.js"></script>';
            // The Iframe Transport is required for browsers without support for XHR file uploads -->
            $includes[] = '<script src="vendor/bower-asset/blueimp-file-upload/js/jquery.iframe-transport.js"></script>';
            // The basic File Upload plugin -->
            $includes[] = '<script src="vendor/bower-asset/blueimp-file-upload/js/jquery.fileupload.js"></script>';
            // The File Upload processing plugin -->
            $includes[] = '<script src="vendor/bower-asset/blueimp-file-upload/js/jquery.fileupload-process.js"></script>';
            // The File Upload image preview & resize plugin -->
            $includes[] = '<script src="vendor/bower-asset/blueimp-file-upload/js/jquery.fileupload-image.js"></script>';
            /*
            // The File Upload audio preview plugin -->
            $includes[] = '<script src="vendor/bower-asset/blueimp-file-upload/js/jquery.fileupload-audio.js"></script>';
            // The File Upload video preview plugin -->
            $includes[] = '<script src="vendor/bower-asset/blueimp-file-upload/js/jquery.fileupload-video.js"></script>';
            // The File Upload validation plugin -->
            */
            $includes[] = '<script src="vendor/bower-asset/blueimp-file-upload/js/jquery.fileupload-validate.js"></script>';
            $includes[] = '<script src="vendor/bower-asset/paste.js/paste.js"></script>';
            $includes = array_merge($includes, $this->getDateFormatter()->buildHtmlHeadIncludes($this->getFacade()), $this->getDateFormatter()->buildHtmlBodyIncludes($this->getFacade()));
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
        $widget = $this->getWidget();
        $translator = $widget->getWorkbench()->getCoreApp()->getTranslator();
        
        if ($widget->isUploadEnabled()) {
            $this->btnBrowse = $btnGrp->addButton($btnGrp->createButton(new UxonObject([
                'widget_type' => 'DataButton',
                'icon' => Icons::FOLDER_OPEN_O,
                'caption' => $translator->translate('WIDGET.IMAGEGALLERY.BUTTON_BROWSE'),
                'hide_caption' => true,
                'align' => 'right',
                'action' => [
                    'alias' => 'exface.Core.CustomFacadeScript',
                    'script' => <<<JS
                        $('#{$this->getIdOfSlick()}-uploader').click();
JS
                ]
            ])), $index);
            
            if ($widget->getUploader()->isInstantUpload() === false) {
                $this->btnMinus = $btnGrp->addButton($btnGrp->createButton(new UxonObject([
                'widget_type' => 'DataButton',
                'icon' => Icons::TRASH,
                'caption' => $translator->translate('WIDGET.IMAGEGALLERY.BUTTON_REMOVE'),
                'hide_caption' => true,
                'align' => 'right',
                'action' => [
                    'alias' => 'exface.Core.CustomFacadeScript',
                    'script' => <<<JS
                        var jqCarousel = $('#{$this->getIdOfSlick()}');
                        var jqActiveSlide = jqCarousel.find('.imagecarousel-item.selected');
                        var iSlideIdx = jqActiveSlide.index();
                        var oData = jqCarousel.data('_exfData');
                        if (jqActiveSlide.length !== 0 && oData.rows !== undefined && iSlideIdx > -1) {
                            jqCarousel.slick('slickRemove', iSlideIdx);
                            oData.rows.splice(iSlideIdx, 1);
                            if (oData.rows.length === 0) {
                                $('#{$this->getIdOfSlick()}-nodata').show();
                            }
                            jqCarousel.data('_exfData', oData);
                        }
JS
                    ]
                ])), $index);
            }
        }
        
        if ($widget->isDownloadEnabled()) {
            $filenameJs = $widget->hasFilenameColumn() ? "oRow['{$widget->getFilenameColumn()->getDataColumnName()}']" : "''";
            $this->btnDownload = $btnGrp->addButton($btnGrp->createButton(new UxonObject([
                'widget_type' => 'DataButton',
                'icon' => Icons::DOWNLOAD,
                'caption' => $translator->translate('WIDGET.IMAGEGALLERY.BUTTON_DOWNLOAD'),
                'hide_caption' => true,
                'align' => 'right',
                'action' => [
                    'alias' => 'exface.Core.CustomFacadeScript',
                    'script' => <<<JS
                        var aRows = {$this->buildJsDataGetter(ActionFactory::createFromString($this->getWorkbench(), DownloadFile::class, $widget))}.rows || [];
                        aRows.forEach(function(oRow) {
                            var sUrl = oRow['{$widget->getImageUrlColumn()->getDataColumnName()}'];
                            var a;

                            if (! sUrl) {
                                {$this->buildJsShowMessageError($this->escapeString($this->translate('WIDGET.IMAGEGALLERY.CANNOT_DOWNLOAD_WITHOUT_URL')))}
                            }

                            a = $("<a>").attr("href", sUrl).attr("download", {$filenameJs}).appendTo("body");
                            a[0].click();
                            a.remove();
                        });
                        
JS
                ]
            ])), $index);
        }
        
        if ($widget->isZoomable()) {
            $this->btnZoom = $btnGrp->addButton($btnGrp->createButton(new UxonObject([
                'widget_type' => 'DataButton',
                'icon' => Icons::SEARCH_PLUS,
                'caption' => $translator->translate('WIDGET.IMAGEGALLERY.BUTTON_ZOOM'),
                'hide_caption' => true,
                'align' => 'right',
                'action' => [
                    'alias' => 'exface.Core.CustomFacadeScript',
                    'script' => <<<JS
                        var jqActiveSlide;
                        var jqCarousel = $('#{$this->getIdOfSlick()}');
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
        }
        
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
        $dataObj = $this->getMetaObjectForDataGetter($action);
        // Determine the columns we need in the actions data
        $colNamesList = implode(',', $widget->getActionDataColumnNames());
        
        if ($action !== null && $action->isDefinedInWidget() && $action->getWidgetDefinedIn() instanceof DataButton) {
            $customMode = $action->getWidgetDefinedIn()->getInputRows();
        } else {
            $customMode = null;
        }
        
        switch (true) {
            case $customMode === DataButton::INPUT_ROWS_ALL:
            case $action === null:
                return "($('#{$this->getIdOfSlick()}').data('_exfData') || {oId: '{$widget->getMetaObject()->getId()}', rows: []})";
                break;
                
            // If the button requires none of the rows explicitly
            case $customMode === DataButton::INPUT_ROWS_NONE:
                return '{}';
                
            // If we are reading, than we need the special data from the configurator
            // widget: filters, sorters, etc.
            case $action instanceof iReadData:
                return $this->getFacade()->getElement($widget->getConfiguratorWidget())->buildJsDataGetter($action);
            
            // Use a subsheet for non-instant uploads
            case $customMode === DataButton::INPUT_ROWS_ALL_AS_SUBSHEET:    
            case $widget->isUploadEnabled() && $widget->getUploader()->isInstantUpload() === false
            && $action->implementsInterface('iModifyData')
            && ! $dataObj->is($widget->getMetaObject())
            && $action->getInputMapper($widget->getMetaObject()) === null:
                // If the action is based on the same object as the widget's parent, use the widget's
                // logic to find the relation to the parent. Otherwise try to find a relation to the
                // action's object and throw an error if this fails.
                if ($widget->hasParent() && $dataObj->is($widget->getParent()->getMetaObject()) && $relPath = $widget->getObjectRelationPathFromParent()) {
                    $relAlias = $relPath->toString();
                } elseif ($relPath = $dataObj->findRelationPath($widget->getMetaObject())) {
                    $relAlias = $relPath->toString();
                }
                
                if ($relAlias === null || $relAlias === '') {
                    throw new WidgetConfigurationError($widget, 'Cannot use data from widget "' . $widget->getId() . '" with action on object "' . $dataObj->getAliasWithNamespace() . '": no relation can be found from widget object to action object', '7CYA39T');
                }
                
                return <<<JS
(function(){
    var oData = ($('#{$this->getIdOfSlick()}').data('_exfData') || {});
    // Remove any keys, that are not in the columns of the widget
    oData.rows = (oData.rows || []).map(({ $colNamesList }) => ({ $colNamesList }));

    return {
        oId: '{$dataObj->getId()}',
        rows: [
            {
                '{$relAlias}': $.extend(
                    {}, 
                    {"oId": "{$widget->getMetaObject()->getId()}"}, 
                    oData
                )
            }
        ],
        filters: [
        
        ]
    }
})()
JS;
            break;
        }
        
        return <<<JS
(function(){
    var jqCarousel = $('#{$this->getIdOfSlick()}');
    var aSelectedRows = [];
    var aAllRows = ((jqCarousel.data('_exfData') || {}).rows || []);
    jqCarousel.find('.imagecarousel-item.selected').each(function(i, jqItem){
        aSelectedRows.push(aAllRows[$(jqItem).index()]);
    });
    // Remove any keys, that are not in the columns of the widget
    aSelectedRows = aSelectedRows.map(({ $colNamesList }) => ({ $colNamesList }));
    return {
        oId: '{$widget->getMetaObject()->getId()}',
        rows: aSelectedRows
    };
})()
JS;
    }
    
    protected function buildJsSlickSlidesFromData(string $jqSlickJs, string $oDataJs) : string
    {
        $widget = $this->getWidget();
        
        if (($urlType = $widget->getImageUrlColumn()->getDataType()) && $urlType instanceof UrlDataType) {
            $base = $urlType->getBaseUrl();
        }
        
        if ($widget->hasCustomThumbnails()) {
            $thumbJs = "'{$base}' + oRow['{$widget->getThumbnailUrlColumn()->getDataColumnName()}']";
        } else {
            // If there is no explicit thumbnail URL column, use the uid to generate a URL to the
            // HttpFileServerFacade. 
            // IMPORTANT: the UID must not contain slashes - even if they are URL encoded (%2F) as
            // many servers (like Apache) will disallow this for security reasons. So if the UID
            // contains a slash, encode it as Base64 first and prefix it by `base64,` - similarly
            // to a DataURI.
            $thumbJs = <<<JS
                        function(){
                            var sUid = oRow['{$widget->getUidColumn()->getDataColumnName()}'];
                            if (sUid == null) {
                                sUid = '';
                            }
                            if (sUid.includes('/')){
                                sUid = 'base64,' + btoa(sUid);
                            }
                            return '{$base}' + ('{$widget->buildUrlForThumbnail('[#~uid#]', 260, 190)}').replace('[#~uid#]', encodeURIComponent(sUid));
                        }()
JS;
        }
        
        if ($widget->hasMimeTypeColumn()) {
            $mimeTypeJs = "oRow['{$widget->getMimeTypeColumn()->getDataColumnName()}']";
        } else {
            $mimeTypeJs = 'null';
        }
        
        $tooltipJs = '';
        foreach ($widget->getColumns() as $col) {
            if ($col->isHidden()) {
                continue;
            }
            $colFormatter = $this->getFacade()->getDataTypeFormatter($col->getDataType());
            $tooltipJs .= ($tooltipJs !== '' ? 'sTooltip += "\\n";' : '') . "sTooltip += " . ($col->getCaption() ? '"' . $this->escapeString($col->getCaption(), false) . ': " ' : '""') . " + " . $colFormatter->buildJsFormatter("oRow['{$col->getDataColumnName()}']") . ";\n";
        }
        $titleJs = '';
        if ($widget->hasImageTitleColumn()) {
            $col = $widget->getImageTitleColumn();
            $titleJs = 'sTitle = ' . $this->getFacade()->getDataTypeFormatter($col->getDataType())->buildJsFormatter("oRow['{$col->getDataColumnName()}']") . ';';
            $tooltipJs = "sTooltip += sTitle;\n" . ($tooltipJs !== '' ? 'sTooltip += "\\n\\n";' . $tooltipJs : '');
        }
        
        
        return <<<JS

                (function(){
                    var aRows = ($oDataJs.rows || []);
                    $jqSlickJs.data('_exfData', $oDataJs);
    
                    $jqSlickJs.slick('slickRemove', null, null, true);
    
    				aRows.forEach(function(oRow, i) {
                        var sSrc = {$thumbJs};
                        var sSrcLarge = '{$base}' + oRow['{$widget->getImageUrlColumn()->getDataColumnName()}'];
                        var sTitle = '';
                        var sTooltip = '';
                        var sMimeType = {$mimeTypeJs};
                        var sIcon = '';

                        {$titleJs}
                        {$tooltipJs}

                        if (sMimeType === null || sMimeType.startsWith('image')) {
                            $jqSlickJs.slick('slickAdd', {$this->buildJsSlideTemplate("'<img src=\"' + sSrc + '\" src-download=\"' + sSrcLarge + '\" title=\"' + sTooltip + '\" alt=\"' + sTitle + '\" />'")});
                        } else {
                            switch (sMimeType.toLowerCase()) {
                                case 'application/pdf': sIcon = 'fa fa-file-pdf-o'; break;
                                default: sIcon = 'fa fa-file-o';
                            }
                            $jqSlickJs.slick('slickAdd', {$this->buildJsSlideTemplateFile('sTitle', 'sMimeType', '', 'sTooltip')});
                        }
                    });

                    if (aRows.length > 0) {
                        $('#{$this->getIdOfSlick()}-nodata').hide();
                    } else {
                        $('#{$this->getIdOfSlick()}-nodata').show();
                    }

                })();

JS;
    }
    
    /**
     * 
     * @param string $imgJs
     * @param string $cssClass
     * @return string
     */
    protected function buildJsSlideTemplate(string $imgJs, string $cssClass = '') : string
    {
        return "'<div class=\"imagecarousel-item {$cssClass}\">' + {$imgJs} + '</div>'";
    }
    
    /**
     * 
     * @param string $sFileNameJs
     * @param string $sMimeTypeJs
     * @param string $cssClass
     * @return string
     */
    protected function buildJsSlideTemplateFile(string $sFileNameJs, string $sMimeTypeJs, string $cssClass = '', string $sTooltipJs = null) : string
    {
        $sTooltipJs = $sTooltipJs ?? $sFileNameJs;
        return <<<JS
                            (function(){
                                switch ($sMimeTypeJs.toLowerCase()) {
                                    case 'application/pdf': sIcon = 'fa fa-file-pdf-o'; break;
                                    default: sIcon = 'fa fa-file-o';
                                }
                                return {$this->buildJsSlideTemplate("'<i class=\"' + sIcon + '\" title=\"' + $sTooltipJs + '\"></i><div class=\"imagecarousel-title\">' + $sFileNameJs + '</div>'", $cssClass . ' imagecarousel-file')};
                            })()

JS;
    }
    
    /**
     * Returns a JS snippet, that empties the table (removes all rows).
     *
     * @return string
     */
    protected function buildJsDataResetter() : string
    {
        return <<<JS
        
            $('#{$this->getIdOfSlick()}')
                .data('_exfData', {})
                .slick('slickRemove', null, null, true);
            $('#{$this->getIdOfSlick()}-nodata').show();
           
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
    
    /**
     * TODO move this method to a JquerFileUploaderTrait
     *
     * @param string $jqSlickJs
     * @return string
     */
    protected function buildJsUploaderInit(string $jqSlickJs, string $uploaderSlideCssClass = '') : string
    {
        if ($this->getWidget()->isUploadEnabled() === false) {
            return '';
        }
        
        $widget = $this->getWidget();
        $uploader = $this->getWidget()->getUploader();
        $uploadButtonEl = $this->getFacade()->getElement($uploader->getInstantUploadButton());
        if ($widget->getUploader()->isInstantUpload()) {
            $uploadJs = $this->buildJsUploadSend('oParams', $this->buildJsBusyIconHide() . $uploadButtonEl->buildJsTriggerActionEffects($uploader->getInstantUploadAction()));
        } else {
            $uploadJs = $this->buildJsUploadStore('oParams', $this->buildJsBusyIconHide());
        }
        
        $filenameColName = DataColumn::sanitizeColumnName($uploader->getFilenameAttribute()->getAliasWithRelationPath());
        $contentColName = DataColumn::sanitizeColumnName($uploader->getFileContentAttribute()->getAliasWithRelationPath());
        $fileColumnsJs = '';
        if ($uploader->hasFileModificationTimeAttribute()) {
            $fileColumnsJs .= DataColumn::sanitizeColumnName($uploader->getFileModificationTimeAttribute()->getAliasWithRelationPath()) . ": file.lastModified,";
        }
        if ($uploader->hasFileSizeAttribute()) {
            $fileColumnsJs .= DataColumn::sanitizeColumnName($uploader->getFileSizeAttribute()->getAliasWithRelationPath()) . ": file.size,";
        }
        if ($uploader->hasFileMimeTypeAttribute()) {
            $fileColumnsJs .= DataColumn::sanitizeColumnName($uploader->getFileMimeTypeAttribute()->getAliasWithRelationPath()) . ": file.type,";
        }
            
        // TODO Use built-in file uploading instead of a custom $.ajax request to
        // be able to take advantage of callbacks like fileuploadfail, fileuploadprogressall
        // etc. To get the files from the XHR on server-side, we could replace their names
        // by the corresponding data column names and teach the data reader middleware to
        // place $_FILES in the data sheet if the column names match.
        // TODO There was a very strange bug with the file.preview in OpenUI5 if the carousel was placed
        // inside a Wizard: the preview was empty and its data URL length was 1614. A workaround for this
        // case was added to use the object URL of the file in an <img> tag instead. Need to find a better
        // solution in future!
        $output = <<<JS
            
    /*$jqSlickJs.slick('slickAdd', '<a class="imagecarousel-upload pastearea"><i class="fa fa-upload"></i></a>');
    $jqSlickJs.find('.imagecarousel-upload').on('click', function(){
        var jqA = $(this);
        if (! jqA.hasClass('armed')) {
            jqA.addClass('armed');
            jqA.children('.fa-upload').hide();
            jqA.append('<span>Paste or drag file here</span>');
        } else {
            jqA.removeClass('armed');
            jqA.children('span').remove();
            jqA.children('.fa-upload').show();
        }
    });*/

    /*
    $('#{$this->getId()}').on('dragenter', function(){
        $('#{$this->getIdOfSlick()}-nodata').hide();
        $('#{$this->getIdOfSlick()}-dropzone').show();
    })

    $('#{$this->getId()}').on('dragleave', function(){console.log('leave');
        $('#{$this->getIdOfSlick()}-dropzone').hide();
        $('#{$this->getIdOfSlick()}-nodata').show();
    })*/

    $('#{$this->getIdOfSlick()}')
        .addClass('pastearea')
    	.pastableNonInputable()
    	.on('pasteImage', function(ev, data){
            $('#{$this->getIdOfSlick()}').fileupload('add', {files: [data.blob]});
        });

    $('#{$this->getIdOfSlick()}').append('<input id="{$this->getIdOfSlick()}-uploader" style="display:none" type="file" name="files[]" multiple="">');
   
    $('#{$this->getIdOfSlick()}').fileupload({
        url: '{$this->getAjaxUrl()}',
        dataType: 'json',
        autoUpload: true,
        previewMaxHeight: ($('#{$this->getIdOfSlick()}').height() - 20),
        previewMaxWidth: $('#{$this->getIdOfSlick()}').width(),
        previewCrop: false,
        formData: {
            resource: '{$this->getPageId()}',
            element: '{$uploader->getInstantUploadButton()->getId()}',
            object: '{$widget->getMetaObject()->getId()}',
            action: '{$uploader->getInstantUploadAction()->getAliasWithNamespace()}'
        },
        dropZone: $('#{$this->getIdOfSlick()}')
    })
    .on('fileuploadsend', function(e, data) {
        var oParams = data.formData;
        
        data.files.forEach(function(file){
            var fileReader = new FileReader();
            var bFileValid = false; 

            if (file.name === undefined || file.name === '') {
                file.name = 'Upload_' + {$this->getDateFormatter()->buildJsFormatDateObject('(new Date())', 'yyyyMMdd_HHmmss')} + '.png';
            }

            bFileValid = {$this->buildJsFileValidator('file', "function(sError, oFileObj) { {$this->buildJsShowError('sError')} }")}
                
            if (bFileValid === false) {
                return;
            }

            $('#{$this->getIdOfSlick()}-nodata').hide();
            if (file.type.startsWith('image')){console.log('preview');
                // If upload preview is available, use it - otherwise use an <img> with src set to the object URL
                if (file.preview && file.preview.toDataURL().length > 1614) {
                    $jqSlickJs.slick('slickAdd', $({$this->buildJsSlideTemplate('""', '.imagecarousel-pending')}).append(file.preview)[0]);
                } else {
                    $jqSlickJs.slick('slickAdd', $({$this->buildJsSlideTemplate('""', '.imagecarousel-pending')}).append('<img src="' + URL.createObjectURL(file) + '">')[0]);
                }
            } else {
                $jqSlickJs.slick('slickAdd', $({$this->buildJsSlideTemplateFile('file.name', 'file.type', '.imagecarousel-pending')}));
            }
            fileReader.onload = function () {
                var sContent = {$this->buildJsFileContentEncoder($uploader->getFileContentAttribute()->getDataType(), 'fileReader.result', 'file.type')};

                {$this->buildJsBusyIconShow()};

                oParams.data = {
                    oId: '{$this->getMetaObject()->getId()}',
                    rows: [{
                        {$filenameColName}: (file.name || 'Upload_' + {$this->getDateFormatter()->buildJsFormatDateObject('(new Date())', 'yyyyMMdd_HHmmss')} + '.png'),
                        {$fileColumnsJs}
                        {$contentColName}: sContent,
                    }]
                };
                {$this->buildJsBusyIconShow()}
                {$uploadJs}
            };
            fileReader.readAsBinaryString(file);
        });
        return false;
    });
JS;
                
                return $output;
    }
    
    /**
     *
     * @return JsDateFormatter
     */
    protected function getDateFormatter() : JsDateFormatter
    {
        return new JsDateFormatter(DataTypeFactory::createFromString($this->getWorkbench(), DateTimeDataType::class));
    }
    
    /**
     * 
     * @param string $oParamsJs
     * @param string $onUploadCompleteJs
     * @return string
     */
    protected function buildJsUploadStore(string $oParamsJs, string $onUploadCompleteJs) : string
    {
        return <<<JS

            (function(){
                var oData = $('#{$this->getIdOfSlick()}').data('_exfData') || {};
                var oNew = $oParamsJs.data;
                if (Object.keys(oData).length === 0) {
                    oData = oNew;
                } else {
                    oData.rows = oData.rows.concat(oNew.rows);
                }
                $('#{$this->getIdOfSlick()}').data('_exfData', oData);
                {$onUploadCompleteJs}
            })();

JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildHtmlNoDataOverlay() : string
    {
        if ($this->getWidget()->isUploadEnabled()) {
            $message = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('WIDGET.IMAGEGALLERY.HINT_UPLOAD');
        } else {
            $message = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('WIDGET.IMAGEGALLERY.HINT_EMPTY');
        }
        return <<<HTML
        
            <div id="{$this->getIdOfSlick()}-nodata" class="imagecarousel-overlay">
                <div class="imagecarousel-nodata">
                    <i class="fa fa-file-image-o" aria-hidden="true"></i>
                    <div>
                        {$message}
                    </div>
                </div>
            </div>
            
HTML;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildHtmlUploadOverlay() : string
    {
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        return <<<HTML
        
            <div id="{$this->getIdOfSlick()}-uploader" class="imagecarousel-overlay">
                <div class="imagecarousel-uploader">
                    <i class="fa fa-mouse-pointer" aria-hidden="true"></i>
                    <div>
                        {$translator->translate('WIDGET.IMAGEGALLERY.HINT_DROP_HERE')}
                    </div>
                </div>
                <div class="imagecarousel-uploader">
                    <i class="fa fa-clipboard" aria-hidden="true"></i>
                    <div>
                        {$translator->translate('WIDGET.IMAGEGALLERY.HINT_PASTE_HERE')}
                    </div>
                </div>
                <div class="imagecarousel-uploader">
                    <i class="fa fa-folder-open-o" aria-hidden="true"></i>
                    <div>
                        {$translator->translate('WIDGET.IMAGEGALLERY.BUTTON_BROWSE')}
                    </div>
                </div>
            </div>
            
HTML;
    }
}