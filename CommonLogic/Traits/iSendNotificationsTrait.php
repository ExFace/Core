<?php
namespace exface\Core\CommonLogic\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\ConfigPlaceholders;
use exface\Core\Templates\Placeholders\TranslationPlaceholders;
use exface\Core\Templates\Placeholders\ExcludedPlaceholders;
use exface\Core\Templates\Placeholders\DataRowPlaceholders;
use exface\Core\Templates\Placeholders\FormulaPlaceholders;
use exface\Core\Communication\Messages\Envelope;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\iSendNotifications;

trait iSendNotificationsTrait 
{
    private $messageUxons = null;
    
    /**
     * Array of messages to send - each with a separate message model: channel, recipients, etc.
     *
     * You can use the following placeholders inside any message model - as recipient,
     * message subject - anywhere:
     *
     * - `[#~config:app_alias:config_key#]` - will be replaced by the value of the `config_key` in the given app
     * - `[#~translate:app_alias:translation_key#]` - will be replaced by the translation of the `translation_key`
     * from the given app
     * - `[#~data:column_name#]` - will be replaced by the value from `column_name` of the data sheet,
     * for which the notification was triggered - only works with notification that have data sheets present!
     * - `[#=Formula()#]` - will evaluate the `Formula` (e.g. `=Now()`) in the context of the notification.
     * This means, static formulas will always work, while data-driven formulas will only work on notifications 
     * that have data sheets present!
     *
     * @uxon-property notifications
     * @uxon-type \exface\Core\CommonLogic\Communication\AbstractMessage
     * @uxon-template [{"channel": ""}]
     *
     * @param UxonObject $arrayOfMessages
     * @return iSendNotificationsTrait
     */
    public function setNotifications(UxonObject $arrayOfMessages) : iSendNotifications
    {
        $this->messageUxons = $arrayOfMessages;
        return $this;
    }
    
    /**
     *
     * @return CommunicationMessageInterface[]
     */
    public function getNotificationEnvelopes(DataSheetInterface $dataSheet = null) : array
    {
        $messages = [];
        foreach ($this->messageUxons as $uxon) {
            $json = $uxon->toJson();
            $renderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
            $renderer->addPlaceholder(new ConfigPlaceholders($this->getWorkbench()));
            $renderer->addPlaceholder(new TranslationPlaceholders($this->getWorkbench()));
            $renderer->addPlaceholder(new ExcludedPlaceholders('~notification:', '[#', '#]'));
            switch (true) {
                case $dataSheet !== null:
                    foreach (array_keys($dataSheet->getRows()) as $rowNo) {
                        $rowRenderer = clone $renderer;
                        $rowRenderer->addPlaceholder(
                            (new DataRowPlaceholders($dataSheet, $rowNo, '~data:'))
                            ->setSanitizeAsUxon(true)
                            );
                        $rowRenderer->addPlaceholder(
                            (new FormulaPlaceholders($this->getWorkbench(), $dataSheet, $rowNo))
                            //->setSanitizeAsUxon(true)
                            );
                        $renderedJson = $rowRenderer->render($json);
                        $renderedUxon = UxonObject::fromJson($renderedJson);
                        $messages[] = new Envelope($this->getWorkbench(), $renderedUxon);
                    }
                    break;
                default:
                    $renderer->addPlaceholder(new FormulaPlaceholders($this->getWorkbench()));
                    $renderedUxon = UxonObject::fromJson($renderer->render($json));
                    $messages[] = new Envelope($this->getWorkbench(), $renderedUxon);
            }
        }
        
        return $messages;
    }
}