<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\Parts\Uploader;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\BinaryDataType;
use exface\Core\DataTypes\HexadecimalNumberDataType;

/**
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JsUploaderTrait
{   
    /**
     * 
     * @return Uploader
     */
    protected abstract function getUploader() : Uploader;
    
    /**
     * 
     * @param string $fileJs
     * @return string
     */
    protected function buildJsFileValidator(string $fileJs) : string
    {
        $extensions = $this->getWidget()->getUploader()->getAllowedFileExtensions();
        if (! empty($extensions)) {
            $extensionsJs = mb_strtolower(json_encode(array_unique($extensions)));
        } else {
            $extensionsJs = '[]';
        }
        
        $mimeTypes = $this->getWidget()->getUploader()->getAllowedMimeTypes();
        if (! empty($mimeTypes)) {
            $mimeTypesJs = mb_strtolower(json_encode(array_unique($mimeTypes)));
        }
        
        $maxFilenameLength = $this->getUploader()->getMaxFilenameLength() ?? 'null';
        $maxFileSize = $this->getUploader()->getMaxFileSizeMb() ?? 'null';
        
        return <<<JS
            (function(){
                var sError;
                var oFileObj = $fileJs;
                var aExtensions = $extensionsJs;
                var aMimeTypes = $mimeTypesJs;
                var fMaxFileSize = {$maxFileSize};
                var iMaxNameLength = {$maxFilenameLength};

                if (aExtensions && aExtensions.length > 0) {
                    var fileExt = (/(?:\.([^.]+))?$/).exec((file.name || '').toLowerCase())[1];
                    if (! aExtensions.includes(fileExt)) {
                        sError = "{$this->translate('WIDGET.UPLOADER.ERROR_EXTENSION_NOT_ALLOWED', ['%ext%' => ' +"\"" + fileExt  + "\"" + '])}";
                    }
                }
                // Check mime type
                var aMimeTypes = oUploadSet.getMediaTypes();
                if (aMimeTypes && aMimeTypes.length > 0) {
                    if (! aMimeTypes.includes((file.type || '').toLowerCase())) {
                        sError = "{$this->translate('WIDGET.UPLOADER.ERROR_MIMETYPE_NOT_ALLOWED', ['%type%' => ' +"\"" + file.type  + "\"" + '])}";
                    }
                }
                // Check size
                if (fMaxFileSize && fMaxFileSize > 0) {
                    if (fMaxFileSize * 1000000 < file.size) {
                        sError = "{$this->translate('WIDGET.UPLOADER.ERROR_FILE_TOO_BIG', ['%mb%' => '" + fMaxFileSize + "'])}";
                    }
                }
                // Check filename length
                if (iMaxNameLength && iMaxNameLength > 0) {
                    if (iMaxNameLength < file.name.length) {
                        sError = "{$this->translate('WIDGET.UPLOADER.ERROR_FILE_NAME_TOO_LONG', ['%length%' => '" + iMaxNameLength + "'])}";
                    }
                }
            })()
JS;
    }
    
    protected function buildJsFileContentEncoder(DataTypeInterface $contentDataType, string $fileContentJs, string $mimeTypeJs) : string
    {
        switch (true) {
            case $contentDataType instanceof BinaryDataType && $contentDataType->getEncoding() === BinaryDataType::ENCODING_BASE64:
                return "btoa($fileContentJs)";
            case $contentDataType instanceof BinaryDataType && $contentDataType->getEncoding() === BinaryDataType::ENCODING_BINARY:
                return $fileContentJs;
            case $contentDataType instanceof BinaryDataType && $contentDataType->getEncoding() === BinaryDataType::ENCODING_HEX:
                $prefix0x = HexadecimalNumberDataType::HEX_PREFIX;
                return <<<JS
                
                    function (s){
                        var v,i, f = 0, a = [];
                        s += '';
                        f = s.length;
                        
                        for (i = 0; i<f; i++) {
                            a[i] = s.charCodeAt(i).toString(16).replace(/^([\da-f])$/,"0$1");
                        }
                        
                        return '{$prefix0x}' + a.join('');
                    }($fileContentJs);
JS;
        }
        return "'data:' + {$mimeTypeJs} + ';base64,' + btoa({$fileContentJs})";
    }
}