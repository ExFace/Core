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
     * Returns the JS code to validate file size, mime type, extension, etc. and call $onErrorJs if validation fails
     * 
     * The argument $fileJs should be a standard Javascript [file object](https://developer.mozilla.org/en-US/docs/Web/API/File).
     * 
     * The argument $fnOnErrorJs must be a javascript callable with the following signature: `function(sError, oFileObj)`.
     * When called, it will receive a string error text as well as the file object above.
     * 
     * @param string $fileJs
     * @param string $fnOnErrorJs
     * @return string
     */
    protected function buildJsFileValidator(string $fileJs, string $fnOnErrorJs) : string
    {
        $extensions = $this->getWidget()->getUploader()->getAllowedFileExtensions();
        if (! empty($extensions)) {
            $extensions = array_unique($extensions);
            $extensionsJs = mb_strtolower(json_encode($extensions));
        } else {
            $extensionsJs = '[]';
        }
        
        $mimeTypes = $this->getWidget()->getUploader()->getAllowedMimeTypes();
        if (! empty($mimeTypes)) {
            $mimeTypes = array_unique($mimeTypes);
            $mimeTypesJs = mb_strtolower(json_encode($mimeTypes));
        } else {
            $mimeTypesJs = '[]';
        }
        
        $maxFilenameLength = $this->getUploader()->getMaxFilenameLength() ?? 'null';
        $maxFileSize = $this->getUploader()->getMaxFileSizeMb() ?? 'null';
        
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        return <<<JS
            (function(oFileObj, fnOnError){
                var sError;
                var aExtensions = $extensionsJs;
                var aMimeTypes = $mimeTypesJs;
                var fMaxFileSize = {$maxFileSize};
                var iMaxNameLength = {$maxFilenameLength};

                if (aExtensions && aExtensions.length > 0) {
                    var fileExt = (/(?:\.([^.]+))?$/).exec((oFileObj.name || '').toLowerCase())[1];
                    if (! aExtensions.includes(fileExt)) {
                        sError = {$this->escapeString($translator->translate('WIDGET.UPLOADER.ERROR_EXTENSION_NOT_ALLOWED', ['%extensions%' => implode(', ', $extensions)]))};
                    }
                }
                // Check mime type
                if (aMimeTypes && aMimeTypes.length > 0) {
                    if (! aMimeTypes.includes((oFileObj.type || '').toLowerCase())) {
                        sError = {$this->escapeString($translator->translate('WIDGET.UPLOADER.ERROR_MIMETYPE_NOT_ALLOWED', ['%mimetypes%' => implode(', ', $mimeTypes)]))};
                    }
                }
                // Check size
                if (fMaxFileSize && fMaxFileSize > 0) {
                    if (fMaxFileSize * 1024*1024 < oFileObj.size) {
                        sError = {$this->escapeString($translator->translate('WIDGET.UPLOADER.ERROR_FILE_TOO_BIG', ['%mb%' => $this->getUploader()->getMaxFileSizeMb()]))};
                    }
                }
                // Check filename length
                if (iMaxNameLength && iMaxNameLength > 0) {
                    if (iMaxNameLength < oFileObj.name.length) {
                        sError = {$this->escapeString($translator->translate('WIDGET.UPLOADER.ERROR_FILENAME_TOO_LONG', ['%length%' => $this->getUploader()->getMaxFilenameLength()]))};
                    }
                }

                if (sError !== undefined) {
                    fnOnError(sError, oFileObj);
                    return false;
                }

                return true;
            })($fileJs, $fnOnErrorJs)
JS;
    }
    
    /**
     * 
     * @param DataTypeInterface $contentDataType
     * @param string $fileContentJs
     * @param string $mimeTypeJs
     * @return string
     */
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
                    }($fileContentJs)
JS;
        }
        return "'data:' + {$mimeTypeJs} + ';base64,' + btoa({$fileContentJs})";
    }
    
    protected function getHintForUploadRestrictions() : string
    {
        $hint = '';
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        
        $extensions = $this->getWidget()->getUploader()->getAllowedFileExtensions();
        if (! empty($extensions)) {
            $extensions = array_unique($extensions);
            $hint .= PHP_EOL . '- ' . $translator->translate('WIDGET.UPLOADER.HINT_EXTENSION_NOT_ALLOWED', ['%extensions%' => implode(', ', $extensions)]);
        } 
        
        $mimeTypes = $this->getWidget()->getUploader()->getAllowedMimeTypes();
        if (! empty($mimeTypes)) {
            $mimeTypes = array_unique($mimeTypes);
            $hint .= PHP_EOL . '- ' . $translator->translate('WIDGET.UPLOADER.HINT_MIMETYPE_NOT_ALLOWED', ['%mimetypes%' => implode(', ', $mimeTypes)]);
        }
        
        $maxFileSize = $this->getUploader()->getMaxFileSizeMb();
        if ($maxFileSize !== null) {
            $hint .= PHP_EOL . '- ' . $translator->translate('WIDGET.UPLOADER.HINT_FILE_TOO_BIG', ['%mb%' => $maxFileSize]);
        }
        $maxFilenameLength = $this->getUploader()->getMaxFilenameLength();
        $hint .= PHP_EOL . '- ' . $translator->translate('WIDGET.UPLOADER.HINT_FILENAME_TOO_LONG', ['%length%' => $maxFilenameLength]);
        return $hint;
    }

    /**
     * Returns a JS promise that will resolve to a JS File object with a resized image
     * 
     * Usage in JS facade elements:
     * 
     * ```
     *  {$this->buildJsResizeImageFile()}
     *  .then(function(oFileResized){
     *      // Do something with the resized file
     *  })
     * ```
     * 
     * @param string $fileObjectJs
     * @param string $maxSizeJs
     * @return string
     */
    protected function buildJsResizeImageFile(string $fileObjectJs, string $maxSizeJs, string $blobQualityJs = '0.9') : string
    {
        return <<<JS

            (function(oFile, iMaxSize, fQuality) {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.readAsDataURL(oFile);
                    reader.onload = event => {
                        const img = new Image();
                        img.src = event.target.result;
                        img.onload = () => {
                            const { width, height } = img;
                            let newWidth = width;
                            let newHeight = height;

                            // Maintain aspect ratio
                            if (width > height) {
                                if (width > iMaxSize) {
                                    newWidth = iMaxSize;
                                    newHeight = (height * iMaxSize) / width;
                                }
                            } else {
                                if (height > iMaxSize) {
                                    newHeight = iMaxSize;
                                    newWidth = (width * iMaxSize) / height;
                                }
                            }

                            const canvas = document.createElement('canvas');
                            canvas.width = newWidth;
                            canvas.height = newHeight;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0, newWidth, newHeight);

                            // Convert to Blob and create new File
                            canvas.toBlob(blob => {
                                if (!blob) {
                                    return reject(new Error("Failed to create blob"));
                                }
                                const newFile = new File([blob], oFile.name, {
                                    type: oFile.type,
                                    lastModified: oFile.lastModified
                                });
                                resolve(newFile);
                            }, oFile.type, fQuality); // Adjust quality if needed (0.0 to 1.0)
                        };
                        img.onerror = error => reject(error);
                    };
                    reader.onerror = error => reject(error);
                });
            })($fileObjectJs, $maxSizeJs, $blobQualityJs)
JS;
    }
}