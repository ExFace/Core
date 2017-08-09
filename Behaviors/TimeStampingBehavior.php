<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\AbstractBehavior;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\Events\DataSheetEvent;
use exface\Core\Interfaces\Actions\iUndoActions;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\Exceptions\Behaviors\ConcurrentWriteError;
use exface\Core\Exceptions\Behaviors\ConcurrentWritesCannotBePreventedWarning;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;

class TimeStampingBehavior extends AbstractBehavior
{

    private $created_on_attribute_alias = null;

    private $updated_on_attribute_alias = null;

    private $check_for_conflicts_on_update = true;

    private $disabled = false;

    public function register()
    {
        $this->getUpdatedOnAttribute()->setSystem(true)->setDefaultAggregateFunction('MAX');
        if ($this->getCheckForConflictsOnUpdate()) {
            $this->getWorkbench()->eventManager()->addListener($this->getObject()->getAliasWithNamespace() . '.DataSheet.UpdateData.Before', array(
                $this,
                'checkForConflictsOnUpdate'
            ));
        }
        $this->setRegistered(true);
    }

    public function getCreatedOnAttributeAlias()
    {
        return $this->created_on_attribute_alias;
    }

    public function setCreatedOnAttributeAlias($value)
    {
        $this->created_on_attribute_alias = $value;
        return $this;
    }

    public function getUpdatedOnAttributeAlias()
    {
        return $this->updated_on_attribute_alias;
    }

    public function setUpdatedOnAttributeAlias($value)
    {
        $this->updated_on_attribute_alias = $value;
        return $this;
    }

    public function getCheckForConflictsOnUpdate()
    {
        return $this->check_for_conflicts_on_update;
    }

    public function setCheckForConflictsOnUpdate($value)
    {
        $this->check_for_conflicts_on_update = $value;
        return $this;
    }

    /**
     *
     * @return Attribute
     */
    public function getCreatedOnAttribute()
    {
        return $this->getObject()->getAttribute($this->getCreatedOnAttributeAlias());
    }

    /**
     *
     * @return Attribute
     */
    public function getUpdatedOnAttribute()
    {
        return $this->getObject()->getAttribute($this->getUpdatedOnAttributeAlias());
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractBehavior::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('created_on_attribute_alias', $this->getCreatedOnAttributeAlias());
        $uxon->setProperty('updated_on_attribute_alias', $this->getUpdatedOnAttributeAlias());
        $uxon->setProperty('check_for_conflicts_on_update', $this->getCheckForConflictsOnUpdate());
        return $uxon;
    }

    public function checkForConflictsOnUpdate(DataSheetEvent $event)
    {
        if ($this->isDisabled())
            return;
        
        $data_sheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not
        // extended from it.
        if (! $data_sheet->getMetaObject()->isExactly($this->getObject())) {
            return;
        }
        
        // Check if the updated_on column is present in the sheet
        $updated_column = $data_sheet->getColumns()->getByAttribute($this->getUpdatedOnAttribute());
        if (! $updated_column) {
            throw new DataSheetColumnNotFoundError($data_sheet, 'Cannot check for potential update conflicts in TimeStamping behavior: column "' . $this->getUpdatedOnAttributeAlias() . '" not found in given data sheet!');
        }
        $update_nr = count($updated_column->getValues());
        
        $conflict_rows = array();
        // See, if the UndoAction is performed currently. It needs special treatment
        $current_action = $this->getWorkbench()->context()->getScopeWindow()->getActionContext()->getCurrentAction();
        if ($current_action instanceof iUndoActions) {
            // FIXME To check for conflicts when performing and undo, we need to see, if the timestamp changed
            // since the undone action had been performed. The current problem is, however, that we do not store
            // the resulting data sheet of actions in the action history. So for now, undo will work without any
            // timestamp check. The probability of conflicts within the 3-5 seconds, when the undo link is displayed
            // is very small. Still, this really needs to be fixed!
        } else {
            // Check the current update timestamp in the data source
            $check_sheet = $data_sheet->copy()->removeRows();
            $check_sheet->addFilterFromColumnValues($data_sheet->getUidColumn());
            $check_sheet->dataRead();
            
            $check_column = $check_sheet->getColumns()->getByAttribute($this->getUpdatedOnAttribute());
            $check_nr = count($check_column->getValues());
            
            if ($check_nr == $update_nr) {
                // beim Bearbeiten eines einzelnen Objektes ueber einfaches Bearbeiten, Massenupdate in Tabelle, Massenupdate
                // ueber Knopf, ueber Knopf mit Filtern $check_nr == 1, $update_nr == 1
                // beim Bearbeiten mehrerer Objekte ueber Massenupdate in Tabelle $check_nr == $update_nr > 1
                foreach ($updated_column->getValues() as $row_nr => $updated_val) {
                    $check_val = $check_column->getCellValue($check_sheet->getUidColumn()->findRowByValue($data_sheet->getUidColumn()->getCellValue($row_nr)));
                    try {
                        if (empty($data_sheet->getUidColumn()->getValues()[$row_nr])) {
                            // Beim Massenupdate mit Filtern wird als TS_UPDATE-Wert die momentane Zeit mitgeliefert, die natuerlich neuer
                            // ist, als alle Werte in der Datenbank. Es werden jedoch keine oid-Werte uebergeben, da nicht klar ist welche
                            // Objekte betroffen sind. Momentan wird daher das Update einfach gestattet, spaeter soll hier eine Warnung
                            // ausgegeben werden.
                            throw new ConcurrentWritesCannotBePreventedWarning('Cannot check for concurrent writes on mass updates via filters', '6T6I04D');
                        }
                        $updated_date = new \DateTime($updated_val);
                        $check_date = new \DateTime($check_val);
                        /*
                         * FIXME These commented out lines were a workaround for a problem of oracle SQL delivering an other date format by default
                         * (with milliseconds). This would cause the Check to fail, if the attribute with the timestamp had a formatter. The
                         * formatter would change the timestamp in the GUI, thus the comparison would naturally fail. This should not be
                         * neccessary as long as timestamping attributes do not use formatters. The lines should be removed after some testing.
                         * $format = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('GLOBAL.DEFAULT_DATETIME_FORMAT');
                         * $v_date = new \DateTime($val);
                         * $val_date = new \DateTime($v_date->format($format));
                         * $c_date = new \DateTime($check_val);
                         * $check_date = new \DateTime($c_date->format($format));
                         */
                    } catch (\Exception $e) {
                        $updated_date = 0;
                        $check_date = 0;
                    }
                    
                    if ($updated_date != $check_date) {
                        $conflict_rows[] = $row_nr;
                    }
                }
            } else if ($check_nr > 1 && $update_nr == 1) {
                // beim Bearbeiten mehrerer Objekte ueber Massenupdate ueber Knopf, mehrerer Objekte ueber Knopf mit Filtern
                // $check_nr > 1, $update_nr == 1
                $updated_val = $updated_column->getValues()[0];
                $check_val = DataColumn::aggregateValues($check_column->getValues(), $check_column->getAttribute()->getDefaultAggregateFunction());
                
                try {
                    if (empty($data_sheet->getUidColumn()->getValues()[0])) {
                        // Beim Massenupdate mit Filtern wird als TS_UPDATE-Wert die momentane Zeit mitgeliefert, die natuerlich neuer
                        // ist, als alle Werte in der Datenbank. Es werden jedoch keine oid-Werte uebergeben, da nicht klar ist welche
                        // Objekte betroffen sind. Momentan wird daher das Update einfach gestattet, spaeter soll hier eine Warnung
                        // ausgegeben werden.
                        throw new ConcurrentWritesCannotBePreventedWarning('Cannot check for concurrent writes on mass updates via filters', '6T6I04D');
                    }
                    $updated_date = new \DateTime($updated_val);
                    $check_date = new \DateTime($check_val);
                } catch (\Exception $e) {
                    $updated_date = 0;
                    $check_date = 0;
                }
                
                if ($updated_date != $check_date) {
                    $conflict_rows = array_keys($check_column->getValues(), $check_val);
                }
            }
        }
        
        if (count($conflict_rows) > 0) {
            $data_sheet->dataMarkInvalid();
            throw new ConcurrentWriteError($data_sheet, 'Cannot update data in data sheet with "' . $data_sheet->getMetaObject()->getAliasWithNamespace() . '": row(s) ' . implode(',', $conflict_rows) . ' changed by another user!');
        }
    }
}

?>