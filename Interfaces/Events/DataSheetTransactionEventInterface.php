<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\DataSources\DataTransactionInterface;

interface DataSheetTransactionEventInterface 
    extends DataSheetEventInterface, DataTransactionEventInterface
{

}