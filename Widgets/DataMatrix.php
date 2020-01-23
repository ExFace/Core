<?php
namespace exface\Core\Widgets;

/**
 * A DataTable with certain columns being transposed.
 * 
 * The matrix is created by transposing certain colums of the underlying table. This means, the values of
 * the transposed column are distributed over multiple columns automatically created from values of another
 * column. For example,
 * 
 * | product_no | color | stock | size |
 * |------------|-------|-------|------|
 * | P1         | white |   1   |   S  |
 * | P1         | white |   2   |   M  |
 * | P1         | white |   3   |   L  |
 * | P1         | black |   4   |   S  |
 * | P1         | black |   5   |   M  |
 * 
 * ... will become ...
 * 
 * | product_no | color | S | M | L |
 * |------------|-------|---|---|---|
 * | P1         | white | 1 | 2 | 3 |
 * | P1         | black | 4 | 5 |   |
 * 
 * ... if the `stock` column is transposed used the `size` column as `label_attribute_alias`.
 * 
 * Note: the columns created for the transposed values replace the column with their label. You can 
 * transpose multipe columns - either specifying the same `label_attribute_alias` to get the transposed 
 * rows one-below-another or a different `label_attribute_alias` to have multiple transposed blocks
 * side-by-side (each replacing it's own label-column).
 * 
 * Any `DataTable` can be easily transformed into a `DataMatrix` simply by specifying the 
 * `DataColumnTransposed` as `widget_type` of the column to be transposed and giving it a
 * `label_attribute_alias`. On the other hand, any `DataMatrix` can be turned back into a table just
 * by changing it's `widget_type`.
 *
 * ## Examples
 *
 * Here is the configuration for the above color/size matrix with product stock levels. It is created
 * from a table listing the current `stock_available` property for each product-color-size-combination 
 * individually:
 * 
 * ```
 *  {
 *    "object_alias": "my.App.stock",
 *    "widget_type": "DataMatrix",
 *    "columns": [
 *      {
 *        "attribute_alias": "product__LABEL"
 *      },
 *      {
 *        "attribute_alias": "product__color"
 *      },
 *      {
 *        "attribute_alias": "stock_available:SUM",
 *        "widget_type": "DataColumnTransposed",
 *        "label_attribute_alias": "product__size"
 *      },
 *      {
 *        "attribute_alias": "product__size"
 *      }
 *    ],
 *    "sorters": [
 *      {
 *        "attribute_alias": "product__LABEL",
 *        "direction": "asc"
 *      },
 *      {
 *        "attribute_alias": "product__color",
 *        "direction": "asc"
 *      },
 *      {
 *        "attribute_alias": "product__size",
 *        "direction": "asc"
 *      }
 *    ]
 *  }
 *  
 *  ```
 *  
 * The following example shows total stock levels for a storage rack by it's coordinates (aussuming, each
 * location has an aisle-number and a section-number, but each aisle-section can have multiple locations)
 * 
 * ```
 * {
 *   "object_alias": "my.App.storage_location",
 *   "widget_type": "DataMatrix",
 *   "aggregate_by_attribute_alias": [
 *     "rack__aisle",
 *     "rack__secion"
 *   ],
 *   "columns": [
 *     {
 *       "attribute_alias": "rack__aisle"
 *     },
 *     {
 *       "attribute_alias": "stock_available:SUM",
 *       "widget_type": "DataColumnTransposed",
 *       "label_attribute_alias": "rack__section"
 *     },
 *     {
 *       "attribute_alias": "rack__section"
 *     }
 *   ],
 *   "sorters": [
 *     {
 *       "attribute_alias": "rack__aisle",
 *       "direction": "asc"
 *     },
 *     {
 *       "attribute_alias": "rack__section",
 *       "direction": "asc"
 *     }
 *   ]
 * }
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class DataMatrix extends DataTable
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::init()
     */
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