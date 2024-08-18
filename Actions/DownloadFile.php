<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Facades\HttpFileServerFacade;
use exface\Core\DataTypes\MimeTypeDataType;
use exface\Core\CommonLogic\Model\Expression;

/**
 * Downloads a file either represented by input data or linked to.
 * 
 * If the input object has the FileBehavior, no extra configuration is needed - the
 * download will work right out of the box. Otherwise you can explicitly specify
 * the attribute containing the path to the file to download in `file_path_attribute`.
 * 
 * ## Examples
 * 
 * ### Objects with FileBehavior
 * 
 * ```
 *  {
 *      "alias": "exface.Core.DownloadFile",
 *      "object_alias": "exface.Core.FILE"
 *  }
 * 
 * ```
 * 
 * ### Objects with links to files
 * 
 * ```
 *  {
 *      "alias": "exface.Core.DownloadFile",
 *      "file_path_attribute": "PATHNAME_ABSOLUTE"
 *  }
 * 
 * ```
 * 
 * ### Download an HTTP request from a log table
 * 
 * ```
 *  {
 *      "alias": "exface.Core.DownloadFile",
 *      "file_name_attribute": "=Concatenate('HTTP_Request_', DateTime(CREATED_ON, 'yyyyMMdd_HHmmss'))",
 *      "file_mime_type_attribute": "http_content_type",
 *      "file_content_attribute": "http_body"
 *  }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class DownloadFile extends AbstractAction
{
    private $filePathAttributeAlias = null;
    
    private $fileContentAttributeAlias = null;
    
    private $fileTypeAttributeAlias = null;
    
    private $fileNameAttributeAlias = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        $this->setIcon(Icons::DOWNLOAD);
        $this->setInputRowsMax(1);
        $this->setInputRowsMin(1);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $data = $this->getInputDataSheet($task);
        switch (true) {
            case $this->isFilePathInData():
                $pathAttrAlias = $this->getPathAbsoluteAttributeAlias();
                if ($pathAttrAlias === null || $pathAttrAlias === '') {
                    throw new ActionConfigurationError($this, 'Missing download file configuration in action "' . $this->getAliasWithNamespace() . '": please set the property `path_absolute_attribute_alias` in the action\` configuration!');
                }
                
                if (! $col = $data->getColumns()->getByExpression($pathAttrAlias)) {
                    $col = $data->getColumns()->getByAttribute($this->getMetaObject()->getAttribute($pathAttrAlias));
                }
                
                if (! $col) {
                    throw new ActionInputMissingError($this, 'Download path attribute "' . $pathAttrAlias . '" not found input data!');
                }
                
                $path = $col->getCellValue(0);
                if (! FilePathDataType::isAbsolute($path)) {
                    $path = $this->getWorkbench()->getInstallationPath() . DIRECTORY_SEPARATOR . $path;
                }
                
                $result = ResultFactory::createDownloadResultFromFilePath($task, $path);
                break;
            case $this->isFileContentInData():
                $contentAttrAlias = $this->getFileContentAttributeAlias();
                if (! $contentCol = $data->getColumns()->getByExpression($contentAttrAlias)) {
                    $contentCol = $data->getColumns()->getByAttribute($this->getMetaObject()->getAttribute($contentAttrAlias));
                }
                if (! $contentCol) {
                    $contentCol = $data->getColumns()->addFromExpression($contentAttrAlias);
                }
                
                $filenameAttrAlias = $this->getFileNameAttributeAlias();
                if (! $filenameCol = $data->getColumns()->getByExpression($filenameAttrAlias)) {
                    if (! Expression::detectCalculation($filenameAttrAlias)) {
                        $filenameCol = $data->getColumns()->getByAttribute($this->getMetaObject()->getAttribute($filenameAttrAlias));
                    }
                }
                if (! $filenameCol) {
                    $filenameCol = $data->getColumns()->addFromExpression($filenameAttrAlias);
                }
                
                $mimeTypeAttrAlias = $this->getFileMimeTypeAttributeAlias();
                if ($mimeTypeAttrAlias !== null) {
                    if (! $mimeCol = $data->getColumns()->getByExpression($mimeTypeAttrAlias)) {
                        $mimeCol = $data->getColumns()->getByAttribute($this->getMetaObject()->getAttribute($mimeTypeAttrAlias));
                    }
                    if (! $mimeCol) {
                        $mimeCol = $data->getColumns()->addFromExpression($mimeTypeAttrAlias);
                    }
                }
                
                if (! $data->isFresh()) {
                    if ($data->hasUidColumn(true)) {
                        $data->getFilters()->addConditionFromColumnValues($data->getUidColumn());
                        $data->dataRead();
                    } else {
                        throw new ActionInputMissingError($this, 'Not enough input data to download as a file!');
                    }
                }
                
                $content = $contentCol->getValue(0);
                $filename = $filenameCol->getValue(0);
                if (FilePathDataType::findExtension($filename) === null && $mimeCol) {
                    $mimeType = $mimeCol->getValue(0);
                    if (null !== $ext = MimeTypeDataType::findFileExtension($mimeType)) {
                        $filename .= '.' . $ext;
                    }
                }
                $fm = $this->getWorkbench()->filemanager();
                $path = $fm->getPathToCacheFolder() . DIRECTORY_SEPARATOR . 'Downloads' . DIRECTORY_SEPARATOR . $filename;
                $fm->dumpFile($path, $content);
                $result = ResultFactory::createDownloadResultFromFilePath($task, $path);
                break;
            default:
                if (! $data->hasUidColumn(true)) {
                    throw new ActionInputMissingError($this, 'Download of data not possible for data sheets without UID values!');
                }
                
                $url = HttpFileServerFacade::buildUrlToDownloadData($data->getMetaObject(), $data->getUidColumn()->getValue(0));
                $result = ResultFactory::createDownloadResultFromUrl($task, $url);
        }
        
        return $result;
    }
    
    protected function isFilePathInData() : bool
    {
        return $this->filePathAttributeAlias !== null;
    }
    
    /**
     *
     * @return string|NULL
     */
    protected function getPathAbsoluteAttributeAlias() : ?string
    {
        return $this->filePathAttributeAlias;
    }
    
    /**
     * The attribute with the path to the file to be downloaded (either absolute or relative to workbench).
     * 
     * @uxon-property file_path_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return DownloadFile
     */
    protected function setFilePathAttribute(string $value) : DownloadFile
    {
        $this->filePathAttributeAlias = $value;
        return $this;
    }
    
    /**
     * @deprecated use setFilePathAttribute
     */
    protected function setPathAbsoluteAttributeAlias(string $value) : DownloadFile
    {
        return $this->setFilePathAttribute($value);
    }
    
    /**
     * 
     * @return bool
     */
    protected function isFileContentInData() : bool
    {
        return $this->fileContentAttributeAlias !== null;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getFileContentAttributeAlias() : ?string
    {
        return $this->fileContentAttributeAlias;
    }
    
    /**
     * Alias of the attribute containing the file content - e.g. a binary attribute
     * 
     * If set, the action will download a file made from its input data - regardless
     * of whether the input object has the `FileBehavior` or not.
     * 
     * It is a good idea to set `file_mime_type_attribute` in this case too!
     * 
     * @uxon-property file_content_attribute
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return DownloadFile
     */
    protected function setFileContentAttribute(string $alias) : DownloadFile
    {
        $this->fileContentAttributeAlias = $alias;
        return $this;
    }
    
    /**
     * Alias of the attribute containing the mime type for the download.
     * 
     * This only works together with `file_content_attribute`.
     *
     * @uxon-property file_mime_type_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $alias
     * @return DownloadFile
     */
    protected function setFileMimeTypeAttribute(string $alias) : DownloadFile
    {
        $this->fileTypeAttributeAlias = $alias;
        return $this;
    }
    
    /**
     * 
     * @throws ActionConfigurationError
     * @return string|NULL
     */
    protected function getFileMimeTypeAttributeAlias() : ?string
    {
        $alias = $this->fileTypeAttributeAlias;
        if ($alias !== null && $this->fileContentAttributeAlias === null) {
            throw new ActionConfigurationError($this, 'Cannot use `file_mime_type_attribute` without `file_content_attribute` in action "' . $this->getAliasWithNamespace() . '"!');
        }
        return $alias;
    }
    
    /**
     * Alias of the attribute containing the filename for the download.
     *
     * This only works together with `file_content_attribute`.
     *
     * @uxon-property file_name_attribute
     * @uxon-type metamodel:attribute
     *
     * @param string $alias
     * @return DownloadFile
     */
    protected function setFileNameAttribute(string $alias) : DownloadFile
    {
        $this->fileNameAttributeAlias = $alias;
        return $this;
    }
    
    /**
     *
     * @throws ActionConfigurationError
     * @return string|NULL
     */
    protected function getFileNameAttributeAlias() : ?string
    {
        $alias = $this->fileNameAttributeAlias;
        if ($alias !== null && $this->fileContentAttributeAlias === null) {
            throw new ActionConfigurationError($this, 'Cannot use `file_name_attribute` without `file_content_attribute` in action "' . $this->getAliasWithNamespace() . '"!');
        }
        return $alias;
    }
}