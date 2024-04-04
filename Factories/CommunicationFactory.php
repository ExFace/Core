<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Communication\CommunicationChannelInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Selectors\CommunicationChannelSelector;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Communication\Messages\TextMessage;
use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;
use exface\Core\CommonLogic\Communication\CommunicationChannel;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\Selectors\CommunicationMessageSelectorInterface;
use exface\Core\Interfaces\Communication\CommunicationTemplateInterface;
use exface\Core\Interfaces\Selectors\CommunicationTemplateSelectorInterface;
use exface\Core\CommonLogic\Communication\CommunicationTemplate;
use exface\Core\CommonLogic\Selectors\CommunicationTemplateSelector;
use exface\Core\CommonLogic\Selectors\CommunicationMessageSelector;
use exface\Core\Interfaces\Communication\RecipientInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Communication\Recipients\EmailRecipient;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Communication\Recipients\UserRecipient;
use exface\Core\Communication\Recipients\UserRoleRecipient;
use exface\Core\CommonLogic\Selectors\UserRoleSelector;
use exface\Core\Communication\Recipients\UserMultiRoleRecipient;

/**
 * Produces components related to the communication framework
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class CommunicationFactory extends AbstractSelectableComponentFactory
{

    /**
     * 
     * @param SelectorInterface $selector
     * @param array $constructorArguments
     * @return \exface\Core\Interfaces\Communication\CommunicationChannelInterface|mixed
     */
    public static function createFromSelector(SelectorInterface $selector, array $constructorArguments = null)
    {
        switch (true) { 
            case $selector instanceof CommunicationChannelSelectorInterface:
                return $selector->getWorkbench()->model()->getModelLoader()->loadCommunicationChannel($selector);
            case $selector instanceof CommunicationTemplateSelectorInterface:
                return static::createTemplateFromSelector($selector);
        }
        return parent::createFromSelector($selector, $constructorArguments);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $aliasOrClassOrPath
     * @return CommunicationChannelInterface
     */
    public static function createChannelFromString(WorkbenchInterface $workbench, string $alias) : CommunicationChannelInterface
    {
        $selector = new CommunicationChannelSelector($workbench, $alias);
        return $workbench->model()->getModelLoader()->loadCommunicationChannel($selector);
    }
    
    /**
     * 
     * @param CommunicationChannelSelectorInterface $selector
     * @return CommunicationChannelInterface
     */
    public static function createChannelEmpty(CommunicationChannelSelectorInterface $selector) : CommunicationChannelInterface
    {
        return new CommunicationChannel($selector);
    }

    /**
     * 
     * @param string $name
     * @param string $prototype
     * @param UxonObject $uxon
     * @param WorkbenchInterface $workbench
     */
    public static function createChannelFromUxon(string $prototype, UxonObject $uxon, WorkbenchInterface $workbench) : CommunicationChannelInterface
    {
        $channel = self::createChannelFromString($workbench, $prototype);
        $channel->importUxonObject($uxon);
        return $channel;
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param CommunicationMessageSelectorInterface|string $prototype
     * @return CommunicationMessageInterface
     */
    public static function createMessageFromPrototype(WorkbenchInterface $workbench, $prototype, UxonObject $uxon = null) : CommunicationMessageInterface
    {
        if ($prototype instanceof CommunicationMessageSelectorInterface) {
            $selector = $prototype;
        } else {
            $selector = new CommunicationMessageSelector($workbench, $prototype);
        }
        return parent::createFromSelector($selector, [$workbench, $uxon ?? new UxonObject()]);
    }
    
    /**
     * 
     * @param string $text
     * @param string $subject
     * @return CommunicationMessageInterface
     */
    public static function createSimpleMessage(string $text) : CommunicationMessageInterface
    {
        return new TextMessage(new UxonObject([
            'text' => $text
        ]));
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param CommunicationTemplateSelectorInterface|string $selectorStrings
     * @return CommunicationTemplateInterface[]
     */
    public static function createTemplatesFromModel(WorkbenchInterface $workbench, array $selectorStrings) : array
    {
        $selectors = [];
        foreach ($selectorStrings as $sel) {
            if ($sel instanceof CommunicationTemplateSelectorInterface) {
                $selectors[] = $sel;
            } else {
                $selectors[] = new CommunicationTemplateSelector($workbench, $sel);
            }
        }
        return $workbench->model()->getModelLoader()->loadCommunicationTemplates($selectors);
    }
    
    /**
     * 
     * @param CommunicationTemplateSelectorInterface $selector
     * @return CommunicationTemplateInterface
     */
    public static function createTemplateFromSelector(CommunicationTemplateSelectorInterface $selector) : CommunicationTemplateInterface
    {
        return static::createTemplatesFromModel($selector->getWorkbench(), [$selector])[0];
    }
    
    /**
     * 
     * @param CommunicationTemplateSelectorInterface $selector
     * @param UxonObject $uxon
     * @return CommunicationTemplateInterface
     */
    public static function createTemplateFromUxon(CommunicationTemplateSelectorInterface $selector, UxonObject $uxon) : CommunicationTemplateInterface
    {
        return new CommunicationTemplate($selector, $uxon);
    }
    
    /**
     * Instatiates a communication recipient from an address in DSN syntax
     * 
     * - `user://<username>` or `user://<uid>`
     * - `role://<alias>` or `role://<alias1>+<alias2>`
     * - `mailto://<email>`
     * 
     * @param string $dsn
     * @param WorkbenchInterface $workbench
     * 
     * @throws RuntimeException
     * 
     * @return RecipientInterface
     */
    public static function createRecipientFromString(string $dsn, WorkbenchInterface $workbench) : RecipientInterface
    {
        switch (true) {
            // mailto:user@gmail.com
            case StringDataType::startsWith($dsn, 'mailto:'):
                $email = trim(ltrim(StringDataType::substringAfter($dsn, 'mailto:'), '/'));
                if (false !== $filtered = filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return new EmailRecipient($filtered);
                }
                break;
            // `role://<alias>` or `role://<uid>` or `role://<alias1>+<alias2>`
            case StringDataType::startsWith($dsn, 'role:'):
                $selectorString = StringDataType::substringAfter($dsn, 'role://');
                if (UserMultiRoleRecipient::isMultipleRoles($selectorString)) {
                    return new UserMultiRoleRecipient($selectorString, $workbench);
                } else {
                    return new UserRoleRecipient(new UserRoleSelector($workbench, $selectorString));
                }
            // `user://<username>` or `user://<uid>`
            case StringDataType::startsWith($dsn, 'user:'):
                $selectorString = StringDataType::substringAfter($dsn, 'user://');
                return new UserRecipient(UserFactory::createFromModel($workbench, $selectorString));
            default:
                if (false !== $filtered = filter_var($dsn, FILTER_VALIDATE_EMAIL)) {
                    return new EmailRecipient($filtered);
                }
        }
        throw new RuntimeException('Cannot parse "' . $dsn . '" as communication recipient - invalid syntax?');
    }
}