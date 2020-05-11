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
            $steps = [];
            foreach ($permission->getCombinedPermissions() as $step) {
                $steps[] = $step;
            }
            $steps[] = $permission;
            $tab->addWidget($this->createPoliciesTable($tab, $steps));
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
                    'value' => $permission->toXACMLDecision()
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
                    'caption' => 'Class',
                    'value' => '\\' . get_class($this->getAuthorizationPoint())
                ],
                [
                    'widget_type' => 'Display',
                    'caption' => 'Disabled',
                    'value_data_type' => 'exface.Core.Boolean',
                    'value' => $this->getAuthorizationPoint()->isDisabled()
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
            'caption' => 'Evaluation Steps',
            'height' => '100%'
        ]));
        $tableUxon = new UxonObject([
            'widget_type' => 'DataTable',
            'object_alias' => 'exface.Core.AUTHORIZATION_POLICY',
            'lazy_loading' => false,
            'paginate' => false,
            'hide_header' => true,
            'hide_footer' => true,
            'columns' => [
                [
                    'attribute_alias' => 'EFFECT'
                ],
                [
                    'attribute_alias' => 'NAME'
                ],
                [
                    'data_column_name' => 'DECISION',
                    'caption' => 'Decision'
                ]
            ]
        ]);
        $table = WidgetFactory::createFromUxonInParent($parent, $tableUxon);
        $group->addWidget($table);
        
        $dataSheet = DataSheetFactory::createFromObject($table->getMetaObject());
        $dataSheet->getColumns()->addMultiple([
            'EFFECT',
            'NAME'
        ]);
        $dataSheet->getColumns()->addFromExpression('DECISION');
        
        foreach ($permissions as $permission) {
            switch (true) {
                case $policy = $permission->getPolicy():
                    $name = $policy ? $policy->getName() : '';
                    $effect = $policy ? $policy->getEffect()->__toString() : '';
                    break;
                case $permission instanceof CombinedPermission:
                    $name = 'Combining algorithm "' . $permission->getPolicyCombiningAlgorithm()->getValue() . '"';
                    $effect = '';
                    break;
            }
            $dataSheet->addRow([
                'EFFECT' => $effect,
                'NAME' => $name,
                'DECISION' => $permission->toXACMLDecision()
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