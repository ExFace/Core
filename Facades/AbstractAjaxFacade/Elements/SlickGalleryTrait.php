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
                $lightboxCaption = "caption: function(element, info){var oData = $('#{$this->getIdOfSlick()}').data('_exfData'); if (oData) {return (oData.rows || [])[info.index]['{$this->getWidget()->getImageTitleColumn()->getDataColumnName()}'] } else {return '';} },";
            }
            
            $zoomOnClickJs = $this->getWidget()->getHideHeader() ? 'true' : 'false';
            
            $lightboxInit = <<<JS

    $("#{$this->getIdOfSlick()}")
    .slickLightbox({
        src: 'src',
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
        $this->btnZoom = $btnGrp->addButton($btnGrp->createButton(new UxonObject([
            'widget_type' => 'DataButton',
            'icon' => 'arrows-alt',
            'caption' => 'Zoom',
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
                return "($('#{$this->getIdOfSlick()}').data('_exfData') || {oId: '{$widget->getMetaObject()->getId()}', rows: []})";
                break;
            case $action instanceof iReadData:
                // If we are reading, than we need the special data from the configurator
                // widget: filters, sorters, etc.
                return $this->getFacade()->getElement($widget->getConfiguratorWidget())->buildJsDataGetter($action);
        }
        
        return <<<JS
(function(){
    var jqCarousel = $('#{$this->getIdOfSlick()}');
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
    
    protected function buildJsSlickSlidesFromData(string $jqSlickJs, string $oDataJs) : string
    {
        $widget = $this->getWidget();
        if (($urlType = $widget->getImageUrlColumn()->getDataType()) && $urlType instanceof UrlDataType) {
            $base = $urlType->getBaseUrl();
        }
        if ($widget->hasMimeTypeColumn()) {
            $mimeTypeJs = "oRow['{$widget->getMimeTypeColumn()->getDataColumnName()}']";
        } else {
            $mimeTypeJs = 'null';
        }
        
        return <<<JS

                (function(){
                    $jqSlickJs.data('_exfData', $oDataJs);
    
                    $jqSlickJs.slick('removeSlide', null, null, true);
    
    				($oDataJs.rows || []).forEach(function(oRow, i) {
                        var sSrc = '{$base}' + oRow['{$widget->getImageUrlColumn()->getDataColumnName()}'];
                        var sTitle = oRow['{$widget->getImageTitleColumn()->getDataColumnName()}'];
                        var sMimeType = {$mimeTypeJs};
                        var sIcon = '';
                        if (sMimeType === null || sMimeType.startsWith('image')) {
                            $jqSlickJs.slick('slickAdd', {$this->buildJsSlideTemplate("'<img src=\"' + sSrc + '\" title=\"' + sTitle + '\" alt=\"' + sTitle + '\" />'")});
                        } else {
                            switch (sMimeType.toLowerCase()) {
                                case 'application/pdf': sIcon = 'fa fa-file-pdf-o'; break;
                                default: sIcon = 'fa fa-file-o';
                            }
                            $jqSlickJs.slick('slickAdd', {$this->buildJsSlideTemplate("'<i class=\"' + sIcon + '\" title=\"' + sTitle + '\" alt=\"' + sTitle + '\"></i>'", 'imagecarousel-icon')});
                        }
                    });
    
                    $('#{$this->getIdOfSlick()} .imagecarousel-item').click(function(e) {
                        $('#{$this->getIdOfSlick()} .imagecarousel-item').removeClass('selected');
                        $(e.target).closest('.imagecarousel-item').addClass('selected');
                    });
                })();

JS;
    }
    
    protected function buildJsSlideTemplate(string $imgJs, string $cssClass = '') : string
    {
        return "'<div class=\"imagecarousel-item {$cssClass}\">' + {$imgJs} + '</div>'";
    }
    
    /**
     * Returns a JS snippet, that empties the table (removes all rows).
     *
     * @return string
     */
    protected function buildJsDataResetter() : string
    {
        return <<<JS
        
            $('#{$this->getIdOfSlick()} .slick-track').empty();
            $('#{$this->getIdOfSlick()}').data('_exfData', {});
           
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
        
        $filenameColName = DataColumn::sanitizeColumnName($uploader->getFilenameAttribute()->getAliasWithRelationPath());
        $contentColName = DataColumn::sanitizeColumnName($uploader->getFileContentAttribute()->getAliasWithRelationPath());
        $fileModificationColumnJs = '';
        if ($uploader->hasFileLastModificationTimeAttribute()) {
            $fileModificationColumnJs = DataColumn::sanitizeColumnName($uploader->getFileModificationAttribute()->getAliasWithRelationPath()) . ": file.lastModified,";
        }
        $mimeTypeColumnJs = '';
        if ($uploader->hasFileMimeTypeAttribute()) {
            $mimeTypeColumnJs = DataColumn::sanitizeColumnName($uploader->getFileMimeTypeAttribute()->getAliasWithRelationPath()) . ": file.type,";
        }
        
        $maxFileSizeInBytes = $uploader->getMaxFileSizeMb()*1024*1024;
            
        // TODO Use built-in file uploading instead of a custom $.ajax request to
        // be able to take advantage of callbacks like fileuploadfail, fileuploadprogressall
        // etc. To get the files from the XHR on server-side, we could replace their names
        // by the corresponding data column names and teach the data reader middleware to
        // place $_FILES in the data sheet if the column names match.
        $output = <<<JS
            
    $jqSlickJs.slick('slickAdd', '<a class="imagecarousel-upload pastearea"><i class="fa fa-upload"></i></a>');
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
    });
    
	$('#{$this->getIdOfSlick()} .pastearea').pastableNonInputable();
	$('#{$this->getIdOfSlick()} .pastearea').on('pasteImage', function(ev, data){
        $('#{$this->getIdOfSlick()} .imagecarousel-upload').fileupload('add', {files: [data.blob]});
    });
    
    $('#{$this->getIdOfSlick()} .imagecarousel-upload').fileupload({
        url: '{$this->getAjaxUrl()}',
        dataType: 'json',
        autoUpload: true,
        {$this->buildJsUploadAcceptedFileTypesFilter()}
        maxFileSize: {$maxFileSizeInBytes},
        previewMaxHeight: $('#{$this->getIdOfSlick()} .imagecarousel-upload').height(),
        previewMaxWidth: $('#{$this->getIdOfSlick()}').width(),
        previewCrop: false,
        formData: {
            resource: '{$this->getPageId()}',
            element: '{$uploader->getInstantUploadButton()->getId()}',
            object: '{$widget->getMetaObject()->getId()}',
            action: '{$uploader->getInstantUploadAction()->getAliasWithNamespace()}'
        },
        dropZone: $('#{$this->getIdOfSlick()} .imagecarousel-upload')
    })
    .on('fileuploadsend', function(e, data) {
        var oParams = data.formData;
        
        data.files.forEach(function(file){
            var fileReader = new FileReader();
            $jqSlickJs.slick('slickAdd', $({$this->buildJsSlideTemplate('""')}).append(file.preview)[0]);
            fileReader.onload = function () {
                var sContent = {$this->buildJsFileContentEncoder($uploader->getFileContentAttribute()->getDataType(), 'fileReader.result', 'file.type')};
                {$this->buildJsBusyIconShow()};
                oParams.data = {
                    oId: '{$this->getMetaObject()->getId()}',
                    rows: [{
                        '{$filenameColName}': (file.name || 'Upload_' + {$this->getDateFormatter()->buildJsFormatDateObject('(new Date())', 'yyyyMMdd_HHmmss')} + '.png'),
                        {$fileModificationColumnJs}
                        {$mimeTypeColumnJs}
                        '{$contentColName}': sContent,
                    }]
                };
                {$this->buildJsBusyIconShow()}
                {$this->buildJsUploadSend('oParams', $this->buildJsBusyIconHide() . $uploadButtonEl->buildJsTriggerActionEffects($uploader->getInstantUploadAction()))}
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
     * Generates the acceptedFileTypes option with a corresponding regular expressions if allowed_extensions is set
     * for the widget
     *
     * @return string
     */
    protected function buildJsUploadAcceptedFileTypesFilter()
    {
        $uploader = $this->getWidget()->getUploader();
        if ($uploader->getAllowedFileExtensions()) {
            return 'acceptFileTypes: /(\.|\/)(' . str_replace(array(
                ',',
                ' '
            ), array(
                '|',
                ''
            ), $uploader->getAllowedFileExtensions()) . ')$/i,';
        } else {
            return '';
        }
    }
}