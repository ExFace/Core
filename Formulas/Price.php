<?php
namespace exface\Core\Formulas;

use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\PriceDataType;
use exface\Core\Factories\DataSheetFactory;

/**
 * Formats prices based on a currency meta object.
 * Currency information is cached, so in general only one query per displayed
 * currency is needed.
 *
 * @author Andrej Kabachnik
 *        
 */
class Price extends \exface\Core\CommonLogic\Model\Formula
{

    function run($price, $currency_oid = null, $decimals = '', $dec_point = '', $thousands_sep = '')
    {
        if (! $price && $price !== 0)
            return;
        
        // TODO get the defaults from the meta object
        $decimals = $decimals ? $decimals : 2;
        $dec_point = $dec_point ? $dec_point : ',';
        $thousands_sep = $thousands_sep ? $thousands_sep : ' ';
        
        if ($currency_oid) {
            $curr = $this->fetchCurrency($currency_oid);
        }
        
        return number_format($price, $decimals, $dec_point, $thousands_sep) . ' ' . $curr['SUFFIX'];
    }

    private function fetchCurrency($currency_oid)
    {
        $exface = $this->getWorkbench();
        if ($cache = $exface->data()->getCache('currencies', $currency_oid))
            return $cache;
        
        $ds = DataSheetFactory::createFromObjectIdOrAlias('alexa.RMS.CURRENCY');
        $ds->getColumns()->addFromExpression('SUFFIX');
        $ds->getFilters()->addConditionFromString('OID', $currency_oid);
        $ds->dataRead();
        $currency = $ds->getRow();
        
        $exface->data()->setCache('currencies', $currency_oid, $currency);
        
        return $currency;
    }

    public function getDataType()
    {
        return DataTypeFactory::createFromString($this->getWorkbench(), 'exface.Core.Price');
    }
}
?>