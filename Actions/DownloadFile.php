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

/**
 * Downloads a file either represented by input data or linked to.
 * 
 * If the input object is some sort of a file there is no special configuration required
 * 
 * ## Examples
 * 
 * ### File-based objects
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
 * @author Andrej Kabachnik
 *
 */
class DownloadFile extends AbstractAction
{
    private $filePathAttributeAlias = null;

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
        if ($this->isFile()) {
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
            
            $result = ResultFactory::createDownloadResultFromFile($task, $path);
        } else {
            if (! $data->hasUidColumn(true)) {
                throw new ActionInputMissingError($this, 'Download of data not possible for data sheets without UID values!');
            }
            
            $url = HttpFileServerFacade::buildUrlToDownloadData($data->getMetaObject(), $data->getUidColumn()->getValue(0));
            $result = ResultFactory::createDownloadResultFromUrl($task, $url);
        }
        
        return $result;
    }
    
    protected function isFile() : bool
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
     * The attribute with the absolute path to the file to be downloaded.
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
}