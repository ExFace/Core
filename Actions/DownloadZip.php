<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\ArchiveManager;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\DataSheets\DataCollector;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Interfaces\Model\Behaviors\FileBehaviorInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\AbstractAction;

/**
 * Downloads multiple files as a ZIP archive.
 * 
 * ## Examples
 * 
 * ### Objects with FileBehavior
 * 
 * In case we have a multi-select data widget showing an object with FileBehavior, the input data of this action
 * will be multiple rows of that object (= multiple files). This action will put all the files in an archive and
 * download that zip file.
 * 
 * ```
 *  {
 *      "alias": "exface.Core.DownloadZip",
 *      "object_alias": "exface.Core.FILE"
 *  }
 * 
 * ```
 * 
 * ### All attachments of a business object
 * 
 * Assume we have an app with reports and these can have attachments. We need a button on a table with reports, that
 * will download a zip with all attachments of the selected report.
 * 
 * The attachments are stored in a file system, but for every attachment there is also a `REPORT_ATTACHMENT` object
 * in the database, that hase a relation `REPORT` and also `FileAttachmentBehavior`, that links it to the actual files.
 * 
 * **Note** the `refresh_data_after_mapping:true` below: this makes sure, data is always loaded after the filter mapping
 * was applied. If not done so, the input-sheet of the action will be empty and will not be read, so there will be no
 * download!
 * 
 * ```
 *  {
 *      "alias": "exface.Core.DownloadZip",
 *      "input_mapper": {
 *          "from_object_alias": "my.App.REPORT",
 *          "to_object_alias": "my.App.REPORT_ATTACHMENT",
 *          "refresh_data_after_mapping": true,
 *          "column_to_filter_mappings": [
 *              {"from": "ID", "to": "REPORT"}
 *          ],
 *          "column_to_column_mappings": [
 *              {"from": "ID", "to": "REPORT"}
 *          ]
 *      }
 *  }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class DownloadZip extends AbstractAction
{
    private string $zipName = 'Attachments';

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        $this->setIcon(Icons::DOWNLOAD);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $inputSheet = $this->getInputDataSheet($task);
        
        if($inputSheet->countRows() === 0) {
            return ResultFactory::createEmptyResult($task);
        }
        
        $object = $inputSheet->getMetaObject();
        $behavior = $this->findFileBehavior($object);

        if($behavior === null) {
            $error = 'Cannot download ZIP. Object "' . $object->getAliasWithNamespace() . '"';
            throw new ActionConfigurationError($this, $error . ' does not have a "FileBehavior"!');
        } else {
            $fileNameAttribute = $behavior->getFilenameAttribute();
            $fileNameAlias = $fileNameAttribute->getAliasWithRelationPath();

            $contentsAttribute = $behavior->getContentsAttribute();
            $contentsAlias = $contentsAttribute->getAliasWithRelationPath();
        }

        // Collect missing data.
        $dataCollector = DataCollector::fromConditionGroup($inputSheet->getFilters(), $object);
        $dataCollector->addAttribute($contentsAttribute);
        $dataCollector->addAttribute($fileNameAttribute);
        $dataCollector->enrich($inputSheet);

        // Create archive.
        $zipPath = $this->getWorkbench()->filemanager()->getPathToTempFolder() .
            DIRECTORY_SEPARATOR .
            'Downloads' . 
            DIRECTORY_SEPARATOR .
            $this->getZipFileName();
        $zip = new ArchiveManager($this->getWorkbench(), $zipPath);
        
        // Add files to archive.
        foreach ($inputSheet->getRows() as $row) {
            $zip->addFileFromContent($row[$fileNameAlias], $row[$contentsAlias]);
        }
        
        $zip->close();
        return ResultFactory::createDownloadResultFromFilePath($task, $zip->getFilePath());
    }

    /**
     * Fetches the first behavior on a given object that is an instance of `FileBehaviorInterface` or
     * `null` if no match was found.
     * 
     * @param MetaObjectInterface $object
     * @return FileBehaviorInterface|null
     */
    protected function findFileBehavior(MetaObjectInterface $object) : ?FileBehaviorInterface
    {
        foreach ($object->getBehaviors() as $behavior) {
            if($behavior instanceof FileBehaviorInterface) {
                return $behavior;
            }
        }
        
        return null;
    }

    /**
     * Returns the full filename of the resulting ZIP file that includes
     * both a timestamp and the file extension (.zip).
     * 
     * @return string
     */
    public function getZipFileName() : string
    {
        return date('YmdHis') . '_' . $this->getZipName() . '.zip';
    }

    /**
     * Returns the name for the ZIP as specified in the UXON.
     * 
     * @return string
     */
    public function getZipName() : string
    {
        return $this->zipName;
    }

    /**
     * @uxon-property zip_name
     * @uxon-type string
     * @uxon-template Attachments
     * 
     * @param string $name
     * @return $this
     */
    public function setZipName(string $name) : DownloadZip
    {
        $this->zipName = $name;
        return $this;
    }
}