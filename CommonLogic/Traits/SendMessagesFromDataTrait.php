<?php
namespace exface\Core\CommonLogic\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\ConfigPlaceholders;
use exface\Core\Templates\Placeholders\TranslationPlaceholders;
use exface\Core\Templates\Placeholders\DataRowPlaceholders;
use exface\Core\Templates\Placeholders\FormulaPlaceholders;
use exface\Core\Communication\Messages\Envelope;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Factories\CommunicationFactory;
use exface\Core\CommonLogic\Security\Authorization\DataAuthorizationPoint;
use exface\Core\Interfaces\Communication\UserRecipientInterface;
use exface\Core\Interfaces\Communication\RecipientGroupInterface;
use exface\Core\Interfaces\Communication\RecipientInterface;

/**
 * This trait allows to send communication messages configured in a UXON array.
 * 
 * @author Andrej Kabachnik
 *
 */
trait SendMessagesFromDataTrait 
{        
    /**
     * 
     * @param UxonObject $messagesConfig
     * @param DataSheetInterface $dataSheet
     * @param PlaceholderResolverInterface[] $additionalPlaceholders
     * @return Envelope[]
     */
    protected function getMessageEnvelopes(UxonObject $messagesConfig, DataSheetInterface $dataSheet = null, array $additionalPlaceholders = []) : array
    {
        $messages = [];
        
        $tplSelectors = [];
        foreach ($messagesConfig as $uxon) {
            if (null !== $tplSel = $uxon->getProperty('template')) {
                $tplSelectors[] = $tplSel;
            }
        }
        /* @var $templates \exface\Core\Interfaces\Communication\CommunicationTemplateInterface[] */
        if (! empty($tplSelectors)) {
            $templates = CommunicationFactory::createTemplatesFromModel($this->getWorkbench(), $tplSelectors);
        }
        
        foreach ($messagesConfig as $uxon) {
            if (null !== $tplSel = $uxon->getProperty('template')) {
                foreach ($templates as $tpl) {
                    if ($tpl->getSelector()->toString() === $tplSel) {
                        $uxon = $tpl->getMessageUxon()->extend($uxon);
                        break;
                    }
                }
            }
            $json = $uxon->toJson();
            $renderer = new BracketHashStringTemplateRenderer($this->getWorkbench());
            $renderer->addPlaceholder(new ConfigPlaceholders($this->getWorkbench()));
            $renderer->addPlaceholder(new TranslationPlaceholders($this->getWorkbench()));
            foreach ($additionalPlaceholders as $resolver) {
                $renderer->addPlaceholder($resolver);
            }
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
                        $message = new Envelope($this->getWorkbench(), $renderedUxon);
                        
                        if ($this->willSendOnlyForAuthorizedData()) {
                            foreach ($this->getRecipientsThatCannotSeeData($message->getRecipients(), $dataSheet, $rowNo) as $recipient) {
                                $message->addRecipientToExclude($recipient);
                            }
                        }
                        
                        $messages[] = $message;
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
    
    /**
     * 
     * @param RecipientInterface[] $recipients
     * @param DataSheetInterface $dataSheet
     * @param int $rowNo
     * @return array
     */
    protected function getRecipientsThatCannotSeeData(array $recipients, DataSheetInterface $dataSheet, int $rowNo) : array
    {
        /* @var $authPoint \exface\Core\CommonLogic\Security\Authorization\DataAuthorizationPoint */
        $authPoint = $dataSheet->getWorkbench()->getSecurity()->getAuthorizationPoint(DataAuthorizationPoint::class);
        $authSheet = $dataSheet->copy()->removeRows();
        $excluded = [];
        foreach ($recipients as $recipient) {
            switch (true) {
                case $recipient instanceof RecipientGroupInterface:
                    $excluded = array_merge($excluded, $this->getRecipientsThatCannotSeeData($recipient->getRecipients(), $dataSheet, $rowNo));
                    break;
                case $recipient instanceof UserRecipientInterface:
                    $user = $recipient->getUser();
                    $authSheet->getFilters()->removeAll();
                    $authSheet = $authPoint->authorize($authSheet, [DataAuthorizationPoint::OPERATION_READ], $user);
                    $authSheet->addRow($dataSheet->getRow($rowNo), false, false);
                    $authSheet = $authSheet->extract($authSheet->getFilters(), true);
                    if ($authSheet->isEmpty() === true) {
                        $excluded[] = $recipient;
                    }
                    break;
            }
        }
        return $excluded;
    }
    
    /**
     * 
     * @return bool
     */
    protected abstract function willSendOnlyForAuthorizedData() : bool;
}