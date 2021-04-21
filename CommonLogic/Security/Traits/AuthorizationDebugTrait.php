<?php
namespace exface\Core\CommonLogic\Security\Traits;

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
use exface\Core\Widgets\Tab;
use exface\Core\Interfaces\UserInterface;

/**
 * This trait contains everything needed to add a policies tab to a debug widget. 
 *  
 * @author Andrej Kabachnik
 *
 */
trait AuthorizationDebugTrait
{
    
    /**
     *
     * @return PermissionInterface
     */
    protected abstract function getPermission() : PermissionInterface;
    
    /**
     *
     * @return AuthorizationPointInterface
     */
    protected abstract function getAuthorizationPoint() : AuthorizationPointInterface;
    
    /**
     *
     * @return UserImpersonationInterface
     */
    protected abstract function getSubject() : UserImpersonationInterface;
    
    /**
     *
     * @return mixed
     */
    protected abstract function getObject();
    
    /**
     * 
     * @param DebugMessage $error_message
     * @return Tab
     */
    protected function createPoliciesTab(DebugMessage $error_message) : Tab
    {
        
        $permission = $this->getPermission();
        
        $tab = $error_message->createTab();
        $tab->setCaption('Access Policies');
        $tab->setColumnsInGrid(2);
        
        $tab->addWidget($this->createSummary($error_message));
        
        $tab->addWidget($this->createAuthPointInfo($error_message));
        
        if ($permission instanceof CombinedPermission) {
            $steps = [];
            foreach ($permission->getCombinedPermissions() as $step) {
                $steps[] = $step;
            }
            $steps[] = $permission;
            $tab->addWidget($this->createPoliciesTable($tab, $steps));
        }
        return $tab;
    }
    
    /**
     *
     * @param iContainOtherWidgets $parent
     * @return WidgetInterface
     */
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
                    'caption' => 'Roles',
                    'value' => implode(', ', $this->getSubject() instanceof UserInterface ? $this->getSubject()->getRoles() : $this->getAuthorizationPoint()->getWorkbench()->getSecurity()->getUser($this->getSubject())->getRoles())
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
    
    /**
     *
     * @param iContainOtherWidgets $parent
     * @return WidgetInterface
     */
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
            'height' => '14',
            'width' => '2'
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
    
    /**
     *
     * @return string
     */
    protected function getSubjectText() : string
    {
        return $this->getSubject()->isAnonymous() ? 'Anonymous' : $this->getSubject()->getUsername();
    }
    
    /**
     *
     * @return string
     */
    protected function getObjectText() : string
    {
        switch (true) {
            case $this->getObject() instanceof AliasInterface:
                return $this->getObject()->getAliasWithNamespace();
            default:
                return get_class($this->getObject());
        }
    }
}