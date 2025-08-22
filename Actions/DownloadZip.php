<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\ArchiveManager;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\CommonLogic\AbstractAction;

/**
 * Downloads multiple files as a ZIP archive
 * 
 * ## Examples
 * 
 * ### Objects with FileBehavior
 * 
 * In case we have a multi-select data widget showing an object with FileBehavior, the input data of this action
 * will be multiple rows of that object (= multiple files). This action will put all the files in an achive and
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
 * ```
 *  {
 *      "alias": "exface.Core.DownloadZip",
 *      "input_mapper": {
 *          "from_object_alias": "my.App.REPORT",
 *          "to_object_alias": "my.App.REPORT_ATTACHMENT",
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
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        $this->setIcon(Icons::DOWNLOAD);
        $this->setInputRowsMin(1);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $tempPath = $this->getWorkbench()->filemanager()->getPathToTempFolder() . DIRECTORY_SEPARATOR . 'TODO';
        $zip = new ArchiveManager($this->getWorkbench(), $tempPath);
        $inputSheet = $this->getInputDataSheet($task);
        // TODO use DataCollector to ensure the right columns are there
        foreach ($inputSheet->getRows() as $row) {
            // TODO save as temp file. Probably need to instantiate DataSourceFileInfo to access the files independently
            // from their file system: DataSourceFileInfo::fromObjectAndUID()
            // TODO add files to zip
        }
        $result = ResultFactory::createDownloadResultFromFilePath($task, $zip->getFilePath());
        
        return $result;
    }
}