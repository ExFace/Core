<?php

namespace exface\Core\Interfaces;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;

interface iSendNotifications
{
    /**
     * 
     * @param UxonObject $arrayOfMessages
     * @return iSendNotifications
     */
    public function setNotifications(UxonObject $arrayOfMessages) : iSendNotifications;
    
    /**
     *
     * @return CommunicationMessageInterface[]
     */
    public function getNotificationEnvelopes(DataSheetInterface $dataSheet = null) : array;    
}