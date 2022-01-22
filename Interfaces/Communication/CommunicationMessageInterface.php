<?php
namespace exface\Core\Interfaces\Communication;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\UxonObject;

interface CommunicationMessageInterface extends iCanBeConvertedToUxon
{
    public function getSubject() : ?string;
    
    public function getText() : string;
    
    public function getOptionsUxon() : UxonObject;
}