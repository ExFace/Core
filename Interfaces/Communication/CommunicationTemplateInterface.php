<?php
namespace exface\Core\Interfaces\Communication;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\AliasInterface;

interface CommunicationTemplateInterface extends iCanBeConvertedToUxon, AliasInterface
{
    public function getMessageUxon() : UxonObject;
    
    public function getName() : string;
}