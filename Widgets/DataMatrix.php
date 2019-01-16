<?php
namespace exface\Core\Widgets;

/**
 * A DataTable with certain columns being transposed.
 *
 * Starting with a DataTable, you make it create additional columns with the values from the label_column as headers
 * and values taken from the data_column. The other columns will keep their values. Thus, the DataMatrix has less
 * rows than the underlying table, because some of the are summarized to a single row with more columns.
 *
 * The following example will create a color/size matrix with product stock levels out of a table listing
 * the current stock level for each color-size-combination individually:
 * 
 * ```
 *  {
 *      "widget_type": "DataMatrix",
 *      "object_alias": "PRODUCT_COLOR_SIZE",
 *      "hide_toolbars": true,
 *      "caption": "Stock matrix",
 *      "columns": [
 *          {
 *              "attribute_alias": "COLOR__LABEL"
 *          },
 *          {
 *              "attribute_alias": "SIZE",
 *              "id": "SIZE"
 *          },
 *          {
 *              "attribute_alias": "STOCKS__AVAILABLE:SUM",
 *              "id": "STOCK_AVAILABLE"
 *          }
 *      ],
 *      "label_column_id": "SIZE",
 *      "data_column_id": "STOCK_AVAILABLE",
 *      "sorters": [
 *          {
 *              "attribute_alias": "COLOR__LABEL",
 *              "direction": "ASC"
 *          },
 *          {
 *              "attribute_alias": "SIZING_LENGTH",
 *              "direction": "ASC"
 *          }
 *      ]
 *  }
 *  
 *  ```
 *
 * @author Andrej Kabachnik
 *        
 */
class DataMatrix extends DataTable
{

    protected function init()
    {
        parent::init();
        $this->setPaginate(false);
        $this->setShowRowNumbers(false);
        $this->setMultiSelect(false);
    }

    /**
     * Returns an array with the transposed columns of the matrix
     *
     * @return \exface\Core\Widgets\DataColumnTransposed[]
     */
    public function getColumnsTransposed()
    {
        $cols = array();
        foreach ($this->getColumns() as $col) {
            if ($col instanceof DataColumnTransposed) {
                $cols[] = $col;
            }
        }
        return $cols;
    }

    /**
     * Returns an array with regular (not transposed and not used as labels) columns in the matrix
     *
     * @return \exface\Core\Widgets\DataColumn[]
     */
    public function getColumnsRegular()
    {
        $cols = array();
        $label_cols = array();
        // Collect all non-transposed columns
        foreach ($this->getColumns() as $col) {
            if (! ($col instanceof DataColumnTransposed)) {
                $cols[] = $col;
            } else {
                $label_cols[] = $col->getLabelAttributeAlias();
            }
        }
        
        // Remove label columns
        foreach ($cols as $nr => $col) {
            if (in_array($col->getDataColumnName(), $label_cols)) {
                unset($cols[$nr]);
            }
        }
        
        return $cols;
    }
}
?>