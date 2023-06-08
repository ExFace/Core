<?php
namespace exface\Core\CommonLogic\Communication;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;
use exface\Core\CommonLogic\Selectors\CommunicationChannelSelector;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Communication\Recipients\UserRoleRecipient;
use exface\Core\Communication\Recipients\UserRecipient;
use exface\Core\Factories\UserFactory;
use exface\Core\CommonLogic\Selectors\UserRoleSelector;
use exface\Core\Communication\Recipients\EmailRecipient;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Uxon\CommunicationMessageSchema;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Selectors\UserSelector;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Communication\RecipientInterface;
use exface\Core\Interfaces\Selectors\CommunicationTemplateSelectorInterface;
use exface\Core\CommonLogic\Selectors\CommunicationTemplateSelector;
use exface\Core\Communication\Recipients\UserMultiRoleRecipient;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Factories\CommunicationFactory;

/**
 * Base class for workbench-based messages providing common properties like `channel`, `recipient_users`, etc. 
 * 
 * @author andrej.kabachnik
 *
 */
abstract class AbstractMessage implements CommunicationMessageInterface, iCanGenerateDebugWidgets
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $channelSelector = null;
    
    private $recipientUserSelectors = [];
    
    private $recipientRoleSelectors = [];
    
    private $recipientAddresses = [];
    
    private $recipientsAddedExplicitly = [];
    
    private $recipientsCached = null;
    
    private $recipientUserFilter = null;
    
    private $recipientsToExclude = [];
    
    private $templateSelector = null;
    
    /**
     * 
     * @param UxonObject $payload
     * @param CommunicationChannelSelectorInterface $channelSelectorOrString
     * @param array $recipients
     */
    public function __construct(WorkbenchInterface $workbench, UxonObject $uxon)
    {
        $this->workbench = $workbench;
        $this->importUxonObject($uxon);
    }
    
    /**
     * The channel to send the message through
     * 
     * @uxon-property channel
     * @uxon-type metamodel:exface.Core.COMMUNICATION_CHANNEL:ALIAS_WITH_NS
     * 
     * @param CommunicationChannelSelectorInterface|string $selectorOrString
     * @return CommunicationMessageInterface
     */
    public function setChannel($selectorOrString) : CommunicationMessageInterface
    {
        $this->channelSelector = $selectorOrString;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationMessageInterface::getChannelSelector()
     */
    public function getChannelSelector(): ?CommunicationChannelSelectorInterface
    {
        if ($this->channelSelector !== null && ! $this->channelSelector instanceof CommunicationChannelSelectorInterface) {
            $this->channelSelector = new CommunicationChannelSelector($this->getWorkbench(), $this->channelSelector);
        }
        return $this->channelSelector;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationMessageInterface::getRecipients()
     */
    public function getRecipients() : array
    {
        if ($this->recipientsCached === null) {
            $this->recipientsCached = $this->getRecipientsAddedExplicitly();
            
            foreach ($this->getRecipientAddresses() as $addr) {
                if ($addr === null || $addr === '') {
                    continue;
                }
                if (null !== $recipient = $this->parseRcipientAddress($addr)) {
                    $this->recipientsCached[] = $recipient;
                }
            }
            
            $userRecipients = [];
            
            // If there are user filters, create a data sheet to read user UIDs from users, roles and the filters.
            // Otherwise use the users/roles explicitly
            if ($filterUxon = $this->recipientUserFilter) {
                $ds = DataSheetFactory::createFromObjectIdOrAlias($this->workbench, 'exface.Core.USER');
                $filterForRecipients = ConditionGroupFactory::createOR($ds->getMetaObject());
                foreach ($this->getRecipientUsers() as $str) {
                    $selector = new UserSelector($this->workbench, $str);
                    $filterForRecipients->addConditionFromString(($selector->isUid() ? 'UID' : 'USERNAME'), $str, ComparatorDataType::EQUALS);
                }
                foreach ($this->getRecipientRoles() as $str) {
                    $selector = new UserRoleSelector($this->workbench, $str);
                    $filterForRecipients->addConditionFromString(($selector->isUid() ? 'USER_ROLE_USERS__USER_ROLE' : 'USER_ROLE_USERS__USER_ROLE__ALIAS_WITH_NS'), $str, ComparatorDataType::EQUALS);
                }
                $ds->getColumns()->addFromUidAttribute();
                $ds->getFilters()->addNestedGroup($filterForRecipients);
                $ds->getFilters()->addNestedGroup(ConditionGroupFactory::createFromUxon($this->workbench, $filterUxon, $ds->getMetaObject()));
                $ds->dataRead();
                foreach ($ds->getUidColumn()->getValues() as $userUid) {
                    $userRecipients[] = new UserRecipient(UserFactory::createFromUsernameOrUid($this->workbench, $userUid));
                }
            } else {
                foreach ($this->getRecipientUsers() as $str) {
                    $userRecipients[] = new UserRecipient(UserFactory::createFromUsernameOrUid($this->workbench, $str));
                }
                foreach ($this->getRecipientRoles() as $str) {
                    if (UserMultiRoleRecipient::isMultipleRoles($str)) {
                        $userRecipients[] = new UserMultiRoleRecipient($str, $this->getWorkbench());
                    } else {
                        $userRecipients[] = new UserRoleRecipient(new UserRoleSelector($this->workbench, $str));
                    }
                }
            }
            
            $this->recipientsCached = array_merge($this->recipientsCached, $userRecipients);
        }
        return $this->recipientsCached;
    }
    
    /**
     * 
     * @param string $address
     * @return RecipientInterface|NULL
     */
    protected function parseRcipientAddress(string $address) : ?RecipientInterface
    {
        try {
            return CommunicationFactory::createRecipientFromString($address, $this->getWorkbench());
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     *
     * @return string[]
     */
    protected function getRecipientUsers() : array
    {
        foreach ($this->recipientUserSelectors as $i => $str) {
            if (Expression::detectFormula($str)) {
                unset($this->recipientUserSelectors[$i]);
                $expr = ExpressionFactory::createForObject(MetaObjectFactory::createFromString($this->getWorkbench(), 'exface.Core.USER'), $str);
                if (! $expr->isStatic()) {
                    throw new RuntimeException('Cannot use non-static expression "' . $str . '" as user recipient for communication!');
                }
                $evalStr = $expr->evaluate();
                if ($evalStr === null || $evalStr === '') {
                    continue;
                }
                foreach (explode(EXF_LIST_SEPARATOR, $evalStr) as $val) {
                    $this->recipientUserSelectors[] = $val;
                }
            }
        }
        return $this->recipientUserSelectors;
    }
    
    /**
     * List of user names or UIDs to notify or static formulas to calculate usernames/UIDs
     * 
     * @uxon-property recipient_users
     * @uxon-type metamodel:exface.Core.USER:USERNAME[]|metamodel:formula
     * @uxon-template [""]
     *
     * @param UxonObject $arrayOfStrings
     * @return CommunicationMessageInterface
     */
    public function setRecipientUsers(UxonObject $arrayOfStrings) : CommunicationMessageInterface
    {
        $this->recipientsCached = null;
        $this->recipientUserSelectors = $arrayOfStrings->toArray();
        return $this;
    }
    
    /**
     * Additional filter for user recipients (effects `recipient_users` and `recipient_roles`)
     * 
     * @uxon-property recipient_users_filter
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup
     * @uxon-template {"operator": "AND","conditions":[{"expression": "","comparator": "==","value": ""}]}
     * 
     * @param UxonObject $conditionGroupUxon
     * @return CommunicationMessageInterface
     */
    public function setRecipientUsersFilter(UxonObject $conditionGroupUxon) : CommunicationMessageInterface
    {
        $this->recipientUserFilter = $conditionGroupUxon;
        return $this;
    }
    
    /**
     *
     * @return string[]
     */
    protected function getRecipientRoles() : array
    {
        foreach ($this->recipientRoleSelectors as $i => $str) {
            if (Expression::detectFormula($str)) {
                unset($this->recipientRoleSelectors[$i]);
                $expr = ExpressionFactory::createForObject(MetaObjectFactory::createFromString($this->getWorkbench(), 'exface.Core.USER_ROLE'), $str);
                if (! $expr->isStatic()) {
                    throw new RuntimeException('Cannot use non-static expression "' . $str . '" as user role recipient for communication!');
                }
                $evalStr = $expr->evaluate();
                if ($evalStr === null || $evalStr === '') {
                    continue;
                }
                foreach (explode(EXF_LIST_SEPARATOR, $evalStr) as $val) {
                    $this->recipientRoleSelectors[] = $val;
                }
            }
        }
        return $this->recipientRoleSelectors;
    }
    
    /**
     * List of user role aliases or UIDs to notify or static formulas to calculate aliases/UIDs.
     * 
     * Each item of the list may either be a single user role or a set of roles concatenated by `+`:
     * 
     * - `exface.Core.SUPERUSER` - every user having the superuser role will receive the message
     * - `my.App.role1+my.App.role2` - only users having both roles at the same time will receive the message 
     *
     * @uxon-property recipient_roles
     * @uxon-type metamodel:exface.Core.USER_ROLE:ALIAS_WITH_NS[]|metamodel:formula
     * @uxon-template [""]
     *
     * @param UxonObject $arrayOfStrings
     * @return CommunicationMessageInterface
     */
    public function setRecipientRoles(UxonObject $arrayOfStrings) : CommunicationMessageInterface
    {
        $this->recipientsCached = null;
        $this->recipientRoleSelectors = $arrayOfStrings->toArray();
        return $this;
    }
    
    /**
     * List of explicit addresses (e.g. emails, phone numbers, etc.) to notify.
     * 
     * Use the URN syntax to specify the type of the recipient:
     * - `user://<username>` or `user://<uid>`
     * - `role://<alias>` or `role://<alias1>+<alias2>`
     * - `mailto://<email>`
     * 
     * @uxon-property recipients
     * @uxon-type array
     * @uxon-template [""]
     * 
     * @param UxonObject $arrayOfStrings
     * @return CommunicationMessageInterface
     */
    protected function setRecipients(UxonObject $arrayOfStrings) : CommunicationMessageInterface
    {
        $this->recipientsCached = null;
        $this->recipientAddresses = $arrayOfStrings->toArray();
        return $this;
    }
    
    /**
     *
     * @return string[]
     */
    protected function getRecipientAddresses() : array
    {
        return $this->recipientAddresses;
    }
    
    /**
     * 
     * @return RecipientInterface[]
     */
    protected function getRecipientsAddedExplicitly() : array
    {
        return $this->recipientsAddedExplicitly;   
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationMessageInterface::clearRecipients()
     */
    public function clearRecipients() : CommunicationMessageInterface
    {
        $this->recipientsCached = null;
        $this->recipientsAddedExplicitly = [];
        $this->recipientAddresses = [];
        $this->recipientRoleSelectors = [];
        $this->recipientUserSelectors = [];
        $this->recipientUserFilter = null;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationMessageInterface::addRecipient()
     */
    public function addRecipient(RecipientInterface $recipient) : CommunicationMessageInterface
    {
        $this->recipientsCached = null;
        $this->recipientsAddedExplicitly[] = $recipient;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        if (null !== $val = $this->getChannelSelector()) {
            $uxon->setProperty('channel', $val->toString());
        }
        if (null !== $this->recipientAddresses) {
            $uxon->setProperty('recipients', new UxonObject($this->recipientAddresses));
        }
        if (null !== $this->recipientRoleSelectors) {
            $uxon->setProperty('recipient_roles', new UxonObject($this->recipientRoleSelectors));
        }
        if (null !== $this->recipientUserSelectors) {
            $uxon->setProperty('recipient_users', new UxonObject($this->recipientUserSelectors));
        }
        if (! empty($excludes = $this->getRecipientsToExclude())) {
            $exclStrings = [];
            foreach ($excludes as $recipient) {
                $exclStrings[] = $recipient->__toString();
            }
            $uxon->setProperty('recipients_to_exclude', new UxonObject($exclStrings));
        }
        return $uxon;
    }
    
    /**
     * 
     * @param CommunicationMessageInterface $anotherMsg
     * @return CommunicationMessageInterface
     */
    public static function fromOtherMessageType(CommunicationMessageInterface $anotherMsg) : CommunicationMessageInterface
    {
        $uxon = new UxonObject();
        if (null !== $val = $anotherMsg->getChannelSelector()) {
            $uxon->setProperty('channel', $val->toString());
        }
        $msg = new self($anotherMsg->getWorkbench(), $uxon);
        foreach ($anotherMsg->getRecipients() as $recipient) {
            $msg->addRecipient($recipient);
        }
        return $msg;
    }
    
    /**
     * 
     * @return \exface\Core\Interfaces\WorkbenchInterface
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     *
     * @return string|NULL
     */
    public static function getUxonSchemaClass(): ?string
    {
        return CommunicationMessageSchema::class;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        if ($debug_widget->findChildById('communication_message_tab') === false) {
            $uxon_tab = $debug_widget->createTab();
            $uxon_tab->setId('communication_message_tab');
            $uxon_tab->setCaption('Communication message');
            $uxon_tab->addWidget(WidgetFactory::createFromUxonInParent($uxon_tab, new UxonObject([
                'widget_type' => 'InputUxon',
                'disabled' => true,
                'width' => '100%',
                'height' => '100%',
                'value' => $this->exportUxonObject()->toJson()
            ])));
            $debug_widget->addTab($uxon_tab);
        }
        return $debug_widget;
    }
    
    /**
     * 
     * @return CommunicationTemplateSelectorInterface|NULL
     */
    protected function getTemplateSelector() : ?CommunicationTemplateSelectorInterface
    {
        if (is_string($this->templateSelector)) {
            $this->templateSelector = new CommunicationTemplateSelector($this->getWorkbench(), $this->templateSelector);
        }
        return $this->templateSelector;
    }
    
    /**
     * Template to use for this message - any additional configuration will be applied on-top of the templates, eventually overriding it.
     * 
     * @uxon-property template
     * @uxon-type metamodel:exface.Core.COMMUNICATION_TEMPLATE:ALIAS_WITH_NS
     * 
     * @param string $value
     * @return CommunicationMessageInterface
     */
    protected function setTemplate(string $value) : CommunicationMessageInterface
    {
        $this->templateSelector = $value;
        return $this;
    }
    
    /**
     * 
     * @param RecipientInterface $recipient
     * @return CommunicationMessageInterface
     */
    public function addRecipientToExclude(RecipientInterface $recipient) : CommunicationMessageInterface
    {
        $this->recipientsToExclude[] = $recipient;
        return $this;
    }
    
    /**
     * 
     * @return RecipientInterface[]
     */
    public function getRecipientsToExclude() : array
    {
        foreach ($this->recipientsToExclude as $i => $recipient) {
            if (! $recipient instanceof RecipientInterface) {
                $this->recipientsToExclude[$i] = CommunicationFactory::createRecipientFromString($recipient, $this->getWorkbench());
            }
        }
        return $this->recipientsToExclude;
    }
    
    /**
     * List of addresses to NOT send this message to.
     * 
     * Use the URN syntax to specify the type of the recipient:
     * - `user://<username>` or `user://<uid>`
     * - `role://<alias>` or `role://<alias1>+<alias2>`
     * - `mailto://<email>`
     * 
     * @uxon-property recipients_to_exclude
     * @uxon-type array
     * @uxon-template [""]
     * 
     * @param UxonObject $uxonArray
     * @return CommunicationMessageInterface
     */
    protected function setRecipientsToExclude(UxonObject $uxonArray) : CommunicationMessageInterface
    {
        $this->recipientsToExclude = $uxonArray->toArray();
        return $this;
    }
}