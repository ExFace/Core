<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\DataSources\DataConnectionConfigurationError;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Exceptions\UxonMapError;
use exface\Core\Exceptions\ModelBuilders\ModelBuilderNotAvailableError;
use exface\Core\Interfaces\Selectors\DataConnectorSelectorInterface;
use exface\Core\Events\DataConnection\OnBeforeConnectEvent;
use exface\Core\Events\DataConnection\OnConnectEvent;
use exface\Core\Events\DataConnection\OnBeforeDisconnectEvent;
use exface\Core\Events\DataConnection\OnDisconnectEvent;
use exface\Core\Events\DataConnection\OnBeforeQueryEvent;
use exface\Core\Events\DataConnection\OnQueryEvent;
use exface\Core\Interfaces\Selectors\DataConnectionSelectorInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\CommonLogic\Traits\MetaModelPrototypeTrait;
use exface\Core\Uxon\ConnectionSchema;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Interfaces\UserInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Widgets\Form;
use exface\Core\Widgets\LoginPrompt;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Interfaces\Selectors\UserSelectorInterface;
use exface\Core\Factories\UserFactory;
use exface\Core\DataTypes\MessageTypeDataType;
use exface\Core\DataTypes\EncryptedDataType;
use exface\Core\Exceptions\EncryptionError;
use exface\Core\Exceptions\UxonSyntaxError;
use exface\Core\Factories\ConditionFactory;
use exface\Core\CommonLogic\Selectors\DataConnectionSelector;

abstract class AbstractDataConnector implements DataConnectionInterface
{
    use ImportUxonObjectTrait {
		importUxonObject as importUxonViaTrait;
	}
	use MetaModelPrototypeTrait;

    private $config_array = array();

    private $exface = null;
    
    private $prototypeSelector = null;
    
    private $id = null;
    
    private $alias = null;
    
    private $alias_namespace = null;
    
    private $name = '';
    
    private $connected = false;
    
    private $readonly = false;
    
    private $timeZone = null;
    
    private $filterContextUxon = null;
    
    private $uxon = null;
    
    /**
     *
     * @deprecated Use DataConnectionFactory instead!
     */
    public function __construct(DataConnectorSelectorInterface $prototypeSelector, UxonObject $config = null)
    {
        $this->exface = $prototypeSelector->getWorkbench();
        $this->prototypeSelector = $prototypeSelector;
        if ($config !== null) {
            $this->importUxonObject($config);
        }
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAlias()
     */
    public function getAlias()
    {
        return $this->alias ?? '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::setAlias()
     */
    public function setAlias(string $alias, string $namespace = null) : DataConnectionInterface
    {
        $this->alias = $alias;
        $this->alias_namespace = $namespace;
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAliasWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        return ($this->getNamespace() ? $this->getNamespace() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER : '') . $this->getAlias();
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\AliasInterface::getNamespace()
     */
    public function getNamespace()
    {
        return $this->alias_namespace ?? '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::getId()
     */
    public function getId() : ?string
    {
        return $this->id;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::setId()
     */
    public function setId(string $uid) : DataConnectionInterface
    {
        $this->id = $uid;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::getName()
     */
    public function getName() : string
    {
        return $this->name;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::setName()
     */
    public function setName(string $string) : DataConnectionInterface
    {
        $this->name = $string;
        return $this;
    }
    
    public function hasModel() : bool
    {
        return $this->id !== null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::getSelector()
     */
    public function getSelector() : ?DataConnectionSelectorInterface
    {
        if ($this->id !== null) {
            return new DataConnectionSelector($this->getWorkbench(), $this->id);
        }
        if ($this->alias !== null) {
            return new DataConnectionSelector($this->getWorkbench(), $this->getAliasWithNamespace());
        }
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::getPrototypeSelector()
     */
    public function getPrototypeSelector() : DataConnectorSelectorInterface
    {
        return $this->prototypeSelector;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return $this->uxon ?? new UxonObject();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        try {
            $this->uxon = $uxon;
            return $this->importUxonViaTrait($uxon);
        } catch (UxonMapError $e) {
            throw new DataConnectionConfigurationError($this, 'Invalid data connection configuration: ' . $e->getMessage(), '6T4F41P', $e);
        }
        return;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::connect()
     */
    public final function connect()
    {
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeConnectEvent($this));
        $result = $this->performConnect();
        $this->connected = true;
        $this->getWorkbench()->eventManager()->dispatch(new OnConnectEvent($this));
        return $result;
    }

    protected abstract function performConnect();
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::isConnected()
     */
    public function isConnected() : bool
    {
        return $this->connected;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::disconnect()
     */
    public final function disconnect()
    {
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeDisconnectEvent($this));
        $result = $this->performDisconnect();
        $this->connected = false;
        $this->getWorkbench()->eventManager()->dispatch(new OnDisconnectEvent($this));
        return $result;
    }

    protected abstract function performDisconnect();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::query()
     */
    public final function query(DataQueryInterface $query) : DataQueryInterface
    {
        if ($this->isConnected() === false) {
            $this->connect();
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeQueryEvent($this, $query));        
        
        if ((null !== $tz = $this->getTimeZone()) && $query->getTimeZone() === null) {
            $query->setTimeZone($tz);
        }
        $result = $this->performQuery($query);
        
        $this->getWorkbench()->eventManager()->dispatch(new OnQueryEvent($this, $query));
        return $result;
    }

    protected abstract function performQuery(DataQueryInterface $query);

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::transactionStart()
     */
    public abstract function transactionStart();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::transactionCommit()
     */
    public abstract function transactionCommit();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::transactionRollback()
     */
    public abstract function transactionRollback();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::transactionIsStarted()
     */
    public abstract function transactionIsStarted();
    
    
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\SqlDataConnectorInterface::getModelBuilder()
     */
    public function getModelBuilder()
    {
        throw new ModelBuilderNotAvailableError('No model builder implemented for data connector ' . $this->getAliasWithNamespace() . '!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::isReadOnly()
     */
    public function isReadOnly() : bool
    {
        return $this->readonly;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::setReadOnly()
     */
    public function setReadOnly(bool $trueOrFalse) : DataConnectionInterface
    {
        $this->readonly = $trueOrFalse;
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return ConnectionSchema::class;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token, bool $updateUserCredentials = true, UserInterface $credentialsOwner = null, bool $credentialsArePrivate = null) : AuthenticationTokenInterface
    {
        try {
            $this->performConnect();
            return $token;
        } catch (DataConnectionFailedError $e) {
            throw new AuthenticationFailedError($this, 'Authentication failed!', null, $e);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::createLoginWidget()
     */
    public function createLoginWidget(iContainOtherWidgets $container, bool $saveCredentials = true, UserSelectorInterface $credentialsOwner = null) : iContainOtherWidgets
    {
        $container->addWidget(WidgetFactory::createFromUxonInParent($container, new UxonObject([
            'widget_type' => 'Form',
            'caption' => $this->getName(),
            'widgets' => [
                [
                    'widget_type' => 'Message',
                    'type' => MessageTypeDataType::INFO,
                    'text' => $this->getWorkbench()->getCoreApp()->getTranslator()->translate('SECURITY.CONNECTIONS.AUTHENTICATION_NOT_SUPPORTED')
                ]
            ]
        ])));
        return $container;
    }
    
    /**
     * Creates a default login-form within a `LoginPrompt` widget with inputs common for all data sources.
     * 
     * Use this method to quickly create a basic login form. Just add inputs specific for your
     * authentification method (e.g. `USERNAME` and `PASSWORD` inputs) and add the form to the
     * `LoginPrompt` or whereevery you need to display it.
     * 
     * The form will have disabled fields showing the current connection, the save-credentials flag
     * and the selected user UID. It also automatically includes `RELOAD_ON_SUCCESS` = `false` if
     * the `LoginPrompt` is placed within another widget. This means, that the entire browser tab
     * will be refreshed for connection-login-prompts unless they are placed within another widget
     * (e.g. a button) - in other words `LoginPrompt`s in error messages.
     * 
     * NOTE: The created form will not be added to the `LoginPrompt` automatically!
     * 
     * See AbstractSqlConnector::createLoginWidget() for example usage.
     * 
     * @param LoginPrompt $loginPrompt
     * @param bool $saveCredentials
     * @param UserSelectorInterface $saveCredentialsForUser
     * @return Form
     */
    protected function createLoginForm(LoginPrompt $loginPrompt, bool $saveCredentials = true, UserSelectorInterface $saveCredentialsForUser = null) : Form
    {
        $loginForm = WidgetFactory::create($loginPrompt->getPage(), 'Form', $loginPrompt);
        $loginForm->setObjectAlias('exface.Core.LOGIN_DATA');
        
        $userUid = null;
        if ($saveCredentialsForUser !== null) {
            if ($saveCredentialsForUser->isUid()) {
                $userUid = $saveCredentialsForUser->toString();
            } else {
                $user = UserFactory::createFromSelector($saveCredentialsForUser);
                $userUid = $user->getUid();
            }
        }
        
        $loginForm->setCaption($this->getName());
        
        $loginForm->setWidgets(new UxonObject([
            [
                'widget_type' => 'InputHidden',
                'attribute_alias' => 'CONNECTION',
                'value' => $this->getId()
            ],[
                'attribute_alias' => 'CONNECTION__LABEL',
                'readonly' => true,
                'value' => $this->getName()
            ],[
                'attribute_alias' => 'CONNECTION_SAVE',
                'value' => $saveCredentials ? 1 : 0
            ],[
                'widget_type' => 'InputHidden',
                'attribute_alias' => 'CONNECTION_SAVE_FOR_USER',
                'value' => $userUid ?? ''
            ]   
        ]));
        
        if ($loginPrompt->hasParent() === true) {
            $loginForm->addWidget(WidgetFactory::createFromUxonInParent($loginForm, new UxonObject([
                'widget_type' => 'InputHidden',
                'attribute_alias' => 'RELOAD_ON_SUCCESS',
                'value' => false
            ])));
        }
        
        $loginForm->addButton($loginForm->createButton(new UxonObject([
            'action_alias' => 'exface.Core.Login',
            'align' => EXF_ALIGN_OPPOSITE,
            'visibility' => WidgetVisibilityDataType::PROMOTED
        ])));
        
        return $loginForm;
    }
    
    /**
     * Saves a credential set for this connection either with or without a user association.
     * 
     * If a user is provided, the credential set is associated with this user automatically.
     * Depending on $credentialsArePrivate the credential set will be marked private or become
     * shareable. If $credentialsArePrivate is NULL, the set will still be treated as private
     * if the credential set owner happens to be the user currently logged on.
     * 
     * NOTE: user-association only works for authenticated users as anonymous users can't have 
     * credential sets!
     * 
     * @param UxonObject $uxon
     * @param string|NULL $credentialSetName
     * @param UserInterface|NULL $user
     * @param bool|NULL $credentialsArePrivate
     * 
     * @throws RuntimeException
     * 
     * @return AbstractDataConnector
     */
    protected function saveCredentials(UxonObject $uxon, string $credentialSetName = null, UserInterface $user = null, bool $credentialsArePrivate = null) : AbstractDataConnector
    {
        if (($user !== null && $user->isAnonymous() === true) || $this->hasModel() === false || $uxon->isEmpty() === true) {
            return $this;
        }
        
        $credData = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.DATA_CONNECTION_CREDENTIALS');
        $credData->getColumns()->addMultiple(['NAME', 'DATA_CONNECTION', 'DATA_CONNECTOR_CONFIG', 'PRIVATE']);
        $credData->getFilters()->addConditionFromString('DATA_CONNECTION', $this->getId(), ComparatorDataType::EQUALS);
        
        // If saving credentials for a specific user, we need to see if the user already has credentials 
        // for this connection first.
        if ($user !== null) {
            $credData->getFilters()->addConditionFromString('USER_CREDENTIALS__USER', $user->getUid(), ComparatorDataType::EQUALS);
            $credData->dataRead();
            
            $isPrivate = $credentialsArePrivate ?? $user->is($this->getWorkbench()->getSecurity()->getAuthenticatedUser());
        } else {
            $isPrivate = false;
        }
        
        $transaction = $this->getWorkbench()->data()->startTransaction();
        
        switch (true) {
            // If our user already has a credential set for this connection, update or replace it
            case $user !== null && $credData->countRows() === 1:
                // If we are saving private credentials and the existing credential set is private
                // too - just update it.
                if ($isPrivate === true && $credData->getCellValue('PRIVATE', 0) == 1) {
                    try {
                        $oldUxon = UxonObject::fromJson(EncryptedDataType::decrypt(EncryptedDataType::getSecret($this->getWorkbench()), $credData->getCellValue('DATA_CONNECTOR_CONFIG', 0), EncryptedDataType::ENCRYPTION_PREFIX_DEFAULT));
                    } catch (EncryptionError|UxonSyntaxError $e) {
                        $this->getWorkbench()->getLogger()->logException($e);
                        $oldUxon = new UxonObject();
                    }
                    $uxon = $oldUxon->extend($uxon);
                    $credData->setCellValue('DATA_CONNECTOR_CONFIG', 0, $uxon->toJson());
                    $credData->dataUpdate(false, $transaction);
                    break;
                } else {
                    // Otherwise create a new credential set and replace the old one with it
                    
                    // If the old set was private - remove it! Otherwise just unlink from the user
                    if ($credData->getCellValue('PRIVATE', 0) == 1) {
                        // Deleting the credential set will automatically delete user links!
                        $credData->dataDelete($transaction);
                    } else {
                        $credUserData = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_CREDENTIALS');
                        $credUserData->getFilters()->addConditionFromString('USER', $user->getUid(), ComparatorDataType::EQUALS);
                        $credUserData->getFilters()->addConditionFromString('DATA_CONNECTION_CREDENTIALS', $credData->getUidColumn()->getCellValue(0), ComparatorDataType::EQUALS);
                        $credUserData->dataDelete($transaction);
                    }
                    
                    // Now continue with the next case-statement to create a new credential set an
                    // link it to the user.
                    // Don't forget to empty $credData, so it can be repopulated in the next step!
                    $credData->removeRows();
                }
            // If there is no credential set yet, create one
            case $user === null || ($user !== null && $credData->isEmpty()):
                $credData->addRow([
                    'NAME' => $credentialSetName ?? $this->getName(),
                    'DATA_CONNECTOR_CONFIG' => $uxon->toJson(),
                    'DATA_CONNECTION' => $this->getId(),
                    'PRIVATE' => ($isPrivate === true ? '1' : '0')
                ]);
                $credData->dataCreate(false, $transaction);
                
                if ($user !== null) {
                    $credUserData = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.USER_CREDENTIALS');
                    $credUserData->addRow([
                        'USER' => $user->getUid(),
                        'DATA_CONNECTION_CREDENTIALS' => $credData->getUidColumn()->getCellValue(0)
                    ]);
                    $credUserData->dataCreate(false, $transaction);
                }
                
                break;
            default:
                throw new RuntimeException('Cannot save user credentials: multiple credential sets found for user "' . $user->getUsername() . '" and data connection "' . $this->getAliasWithNamespace() . '"!');
        }
        
        $transaction->commit();
        
        // Apply the configuratio to this instance too!
        // First extract the credentials properties from the current UXON model of the connection,
        // then merge them with the just saved credentials values and import that merged UXON
        $currentUxon = $this->exportUxonObject()->extract(array_keys($uxon->toArray()));
        $mergedUxon = $currentUxon->extend($uxon);
        $this->importUxonObject($mergedUxon);
        
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::getTimeZone()
     */
    public function getTimeZone() : ?string
    {
        return $this->property;
    }
    
    /**
     * The time zone expected for all values inside this data source, that do not have an explicit time zone defined
     * 
     * @uxon-property time_zone
     * @uxon-type string 
     * 
     * @param string $value
     * @return AbstractDataConnector
     */
    public function setTimeZone(string $value) : AbstractDataConnector
    {
        $this->property = $value;
        return $this;
    }
    
    /**
     * 
     * @return UxonObject|NULL
     */
    protected function getFilterContext() : ?UxonObject
    {
        return $this->filterContextUxon;
    }
    
    /**
     * Adds conditions to the filter context (in the application context scope)
     * 
     * This feature allows to add global filters to all queries over certain object. This may be
     * usefull, if a connection should only make parts of the data visible. 
     * 
     * **NOTE:** in general, it is better to use the data authorization point to hide data on certain
     * occasions. Concider data authorization first, before using hard-coded filter context
     * conditions here!
     * 
     * @uxon-property filter_context
     * @uxon-type \exface\Core\CommonLogic\Model\Condition[]
     * 
     * @param UxonObject $uxonObjectOrArray
     * @return AbstractDataConnector
     */
    protected function setFilterContext(UxonObject $uxonObjectOrArray) : AbstractDataConnector
    {
        if ($uxonObjectOrArray->isEmpty()) {
            return $this;
        }
        
        $this->filterContextUxon = $uxonObjectOrArray;
        
        // If there is only one filter, make an array out of it (needed for backwards compatibility)
        if (! $uxonObjectOrArray->isArray()){
            $uxonObjectOrArray = new UxonObject([$uxonObjectOrArray->toArray()]);
        }
        
        // Register the filters in the application context scope
        foreach ($uxonObjectOrArray as $filter) {
            $condition = ConditionFactory::createFromUxon($this->getWorkbench(), $filter);
            $this->getWorkbench()->getContext()->getScopeApplication()->getFilterContext()->addCondition($condition);
        }
        
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::isExactly()
     */
    public function isExactly($selectorOrString) : bool
    {
        if (is_string($selectorOrString)) {
            $selector = new DataConnectionSelector($this->getWorkbench(), $selectorOrString);
        }
        switch (true) {
            case $selector->isUid() && $this->id !== null:
                return strcasecmp($this->id, $selector->toString()) === 0;
            case $selector->isAlias() && $this->alias !== null:
                return strcasecmp($this->getAliasWithNamespace(), $selector->toString()) === 0;
        }
        return false;
    }
}