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

/**
 * Downloads a file from a path taken from the input data.
 * 
 * ## Example
 * 
 * ```
 *  {
 *      "alias": "exface.Core.DownloadFile",
 *      "object_alias": "exface.Core.WIDGET",
 *      "path_absolute_attribute_alias": "PATHNAME_ABSOLUTE"
 *  }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class DownloadFile extends AbstractAction
{
    private $pathAbsoluteAttributeAlias = null;

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
        $pathAttrAlias = $this->getPathAbsoluteAttributeAlias();
        if ($pathAttrAlias === null || $pathAttrAlias === '') {
            throw new ActionConfigurationError($this, 'Missing download file configuration in action "' . $this->getAliasWithNamespace() . '": please set the property `path_absolute_attribute_alias` in the action\` configuration!');
        }
        
        $data = $this->getInputDataSheet($task);
        if (! $col = $data->getColumns()->getByExpression($pathAttrAlias)) {
            $col = $data->getColumns()->getByAttribute($this->getMetaObject()->getAttribute($pathAttrAlias));
        }
        
        if (! $col) {
            throw new ActionInputMissingError($this, 'Download path attribute "' . $pathAttrAlias . '" not found input data!');
        }
        
        return ResultFactory::createDownloadResult($task, $col->getCellValue(0));
    }
    
    /**
     *
     * @return string
     */
    protected function getPathAbsoluteAttributeAlias() : string
    {
        return $this->pathAbsoluteAttributeAlias;
    }
    
    /**
     * The attribute with the absolute path to the file to be downloaded.
     * 
     * @uxon-property path_absolute_attribute_alias
     * @uxon-type metamodel:attribute
     * @uxon-required true
     * 
     * @param string $value
     * @return DownloadFile
     */
    public function setPathAbsoluteAttributeAlias(string $value) : DownloadFile
    {
        $this->pathAbsoluteAttributeAlias = $value;
        return $this;
    }
}