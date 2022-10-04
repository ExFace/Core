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