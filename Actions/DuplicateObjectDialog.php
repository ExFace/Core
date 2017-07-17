<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Constants\Icons;

class DuplicateObjectDialog extends EditObjectDialog
{

    protected function init()
    {
        parent::init();
        $this->setIconName(Icons::CLONE);
        $this->setSaveActionAlias('exface.Core.CreateData');
    }

    /**
     *
     * {@inheritdoc} In the case of the dublicate-action we need to remove the UID column from the data sheet to ensure, that the
     *               duplicated object will get new ids.
     *              
     * @see \exface\Core\Actions\ShowWidget::getPrefillDataSheet()
     */
    protected function prefillWidget()
    {
        $data_sheet = $this->getInputDataSheet();
        
        if ($data_sheet->getUidColumn()) {
            $data_sheet = $this->getWidget()->prepareDataSheetToPrefill($data_sheet);
            if (! $data_sheet->isFresh()) {
                $data_sheet->addFilterFromColumnValues($data_sheet->getUidColumn());
                $data_sheet->dataRead();
            }
            $data_sheet->getColumns()->removeByKey($data_sheet->getUidColumn()->getName());
        }
        
        $this->getWidget()->prefill($data_sheet);
    }
}
?>