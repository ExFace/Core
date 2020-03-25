<?php
namespace exface\Core\Exceptions\Security;

use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Widgets\DataTable;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\CommonLogic\Security\Authorization\CombinedPermission;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;

/**
 * Exception thrown if authorization fails on an authorization point
 *
 * @author Andrej Kabachnik
 *        
 */
class AccessPermissionDeniedError extends AccessDeniedError implements AuthorizationExceptionInterface
{
    private $permission = null;
    
    private $authorizationPoint = null;
    
    private $subject = null;
    
    private $object = null;
    
    public function __construct(AuthorizationPointInterface $authPoint, PermissionInterface $permission, UserImpersonationInterface $subject, $object, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->permission = $permission;
        $this->authorizationPoint = $authPoint;
        $this->subject = $subject;
        $this->object = $object;
    }
    
    public function createDebugWidget(DebugMessage $error_message)
    {
        $error_message = parent::createDebugWidget($error_message);
        $permission = $this->getPermission();
        
        $tab = $error_message->createTab();
        $tab->setCaption('Access Policies');
        $tab->setColumnsInGrid(2);
        
        $tab->addWidget($this->createSummary($error_message));
        
        if ($permission instanceof CombinedPermission) {
            $tab->addWidget($this->createPoliciesTable($tab, $permission->getCombinedPermissions()));
        }
        
        $tab->addWidget($this->createAuthPointInfo($error_message));
        
        
        $error_message->addTab($tab);
        
        return $error_message;
    }
    
    public function getPermission() : PermissionInterface
    {
        return $this->permission;
    }
    
    public function getAuthorizationPoint() : AuthorizationPointInterface
    {
        return $this->authorizationPoint;
    }
    
    protected function getPermissionText(PermissionInterface $p) : string
    {
        switch (true) {
            case $p->isDenied() : return 'Denied';
            case $p->isPermitted() : return 'Permitted';
            case $p->isIndeterminate() : return 'Indeterminate';
            case $p->isNotApplicable() : return 'Not applicable';
        }
    }
    
    public function getSubject() : UserImpersonationInterface
    {
        return $this->subject;
    }
    
    protected function createSummary(iContainOtherWidgets $parent) : WidgetInterface
    {
        $permission = $this->getPermission();
        $summaryGroup = WidgetFactory::createFromUxonInParent($parent, new UxonObject([
            'widget_type' => 'WidgetGroup',
            'caption' => 'Summary',
            'width' => 1,
            'widgets' => [
                [
                    'widget_type' => 'Display',
                    'caption' => 'User',
                    'value' => $this->getSubjectText()
                ],
                [
                    'widget_type' => 'Display',
                    'caption' => 'Resource',
                    'value' => $this->getObjectText()
                ],
                [
                    'widget_type' => 'Display',
                    'caption' => 'Resulting permission',
                    'value' => $this->getPermissionText($permission)
                ],
                [
                    'widget_type' => 'Display',
                    'caption' => 'Evaluated policies',
                    'value' => $permission instanceof CombinedPermission ? count($permission->getCombinedPermissions()) : 0
                ]
            ]
        ]));
        
        return $summaryGroup;
    }
    
    protected function createAuthPointInfo(iContainOtherWidgets $parent) : WidgetInterface
    {
        $summaryGroup = WidgetFactory::createFromUxonInParent($parent, new UxonObject([
            'widget_type' => 'WidgetGroup',
            'caption' => 'Authorization Point',
            'width' => 1,
            'widgets' => [
                [
                    'widget_type' => 'Display',
                    'caption' => 'Authorization point',
                    'value' => $this->getAuthorizationPoint()->getName()
                ],
                [
                    'widget_type' => 'Display',
                    'caption' => 'Alias',
                    'value' => $this->getAuthorizationPoint()->getAliasWithNamespace()
                ],
                [
                    'widget_type' => 'Display',
                    'caption' => 'Default effect',
                    'value' => $this->getAuthorizationPoint()->getDefaultPolicyEffect()->getLabelOfValue()
                ],
                [
                    'widget_type' => 'Display',
                    'caption' => 'Policy combining algorithm',
                    'value' => $this->getAuthorizationPoint()->getPolicyCombiningAlgorithm()->getLabelOfValue()
                ]
            ]
        ]));
        
        return $summaryGroup;
    }
    
    /**
     * 
     * @param PermissionInterface[] $permissions
     * @return DataTable
     */
    protected function createPoliciesTable(iContainOtherWidgets $parent, iterable $permissions) : WidgetInterface
    {
        $group = WidgetFactory::createFromUxonInParent($parent, new UxonObject([
            'widget_type' => 'WidgetGroup',
            'caption' => 'Policies',
            'height' => '100%'
        ]));
        
        $table = WidgetFactory::createFromUxonInParent($parent, new UxonObject([
            'widget_type' => 'DataTable',
            'object_alias' => 'exface.Core.AUTHORIZATION_POLICY',
            'lazy_loading' => false,
            'paginate' => false,
            'hide_header' => true,
            'hide_footer' => true
        ]));
        $group->addWidget($table);
        
        $dataSheet = DataSheetFactory::createFromObject($table->getMetaObject());
        
        foreach ($permissions as $permission) {
            $policy = $permission->getPolicy();
            $dataSheet->addRow([
                'EFFECT' => $policy ? $policy->getEffect()->__toString() : '',
                'NAME' => $policy ? $policy->getName() : '',
                'PERMISSION' => $this->getPermissionText($permission)
            ]);
        }
        
        $table->prefill($dataSheet);
        
        return $group;
    }
    
    protected function getSubjectText() : string
    {
        return $this->subject->isAnonymous() ? 'Anonymous' : $this->subject->getUsername();
    }
    
    public function getObject()
    {
        return $this->object;
    }
    
    protected function getObjectText() : string
    {
        switch (true) {
            case $this->object instanceof AliasInterface:
                return $this->object->getAliasWithNamespace();
            default:
                return get_class($this->object);
        }
    }
}