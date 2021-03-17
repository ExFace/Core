<?php
namespace exface\Core\Contexts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Contexts\AbstractContext;
use exface\Core\Widgets\Container;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Widgets\Menu;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\SortingDirectionsDataType;

/**
 * The ObjectBasketContext provides a unified interface to store links to selected instances of meta objects in any context scope.
 * If used in the WindowScope it can represent "pinned" objects, while in the UserScope it can be used to create favorites for this
 * user.
 *
 * Technically it stores a data sheet with instances for each object in the basket. Regardless of the input, this sheet will always
 * contain the default display columns.
 *
 * @author Andrej Kabachnik
 *        
 */
class NotificationContext extends AbstractContext
{
    private $data = null;
    
    private $counter = null;
    
    /**
     * The object basket context resides in the window scope by default.
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getDefaultScope()
     */
    public function getDefaultScope()
    {
        return $this->getWorkbench()->getContext()->getScopeUser();
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        $this->counter = $uxon->getProperty('counter');
        return;
    }

    /**
     * 
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return new UxonObject([
            'counter' => $this->counter
        ]);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIndicator()
     */
    public function getIndicator()
    {
        return $this->getNotificationData() ? $this->getNotificationData()->countRows() : 0;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIcon()
     */
    public function getIcon()
    {
        return Icons::ENVELOPE_O;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getContextBarPopup()
     */
    public function getContextBarPopup(Container $container)
    {       
        /* @var $menu \exface\Core\Widgets\Menu */
        $menu = WidgetFactory::create($container->getPage(), 'Menu', $container);
        $menu->setCaption($this->getName());
        
        // Fill with buttons
        $menu = $this->createMessageButtons($menu);
        
        $container->addWidget($menu);
        
        return $container;
    }
    
    public function getName(){
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.NOTIFICATION.NAME');
    }
    
    /**
     * 
     * @param Menu $menu
     * @return Menu
     */
    protected function createMessageButtons(Menu $menu) : Menu
    {
        $data = $this->getNotificationData();
        if ($data === null) {
            return $menu;
        }
        
        $btn = null;
        foreach ($data->getRows() as $row) {
            $btn = $menu->createButton(new UxonObject([
                'caption' => $row['TITLE'],
                'action' => [
                    'alias' => 'exface.Core.ShowDialog',
                    'widget' => UxonObject::fromJson($row['WIDGET_UXON'])
                ]
            ]));
            if ($row['ICON'] !== null) {
                $btn->setIcon($row['ICON']);
            } else {
                $btn->setShowIcon(false);
            }
            $menu->addButton($btn);
        }
        
        if ($btn) {
            $menu->addButton($menu->createButton(new UxonObject([
                'caption' => 'Clear notifications',
                'icon' => 'eraser',
                'action' => [
                    'alias' => 'exface.Core.DeleteObject',
                    'object_alias' => 'exface.Core.NOTIFICATION',
                    'input_rows_min' => 0,
                    'input_data_sheet' => [
                        'object_alias' => 'exface.Core.NOTIFICATION',
                        'filters' => [
                            'operator' => 'AND',
                            'conditions' => [
                                [
                                    'expression' => 'USER__USERNAME',
                                    'comparator' => '==',
                                    'value' => $this->getWorkbench()->getSecurity()->getAuthenticatedToken()->getUsername()
                                ]
                            ]
                        ]
                    ]
                ]
            ])));
        }
        
        return $menu;
    }
    
    protected function getNotificationData() : ?DataSheetInterface
    {
        if ($this->data !== null) {
            return $this->data;
        }
        
        $authToken = $this->getWorkbench()->getSecurity()->getAuthenticatedToken();
        if ($authToken->isAnonymous()) {
            return null;
        }
        
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.NOTIFICATION');
        $ds->getColumns()->addFromSystemAttributes();
        $ds->getColumns()->addMultiple([
            'TITLE',
            'ICON',
            'WIDGET_UXON'
        ]);
        $ds->getSorters()->addFromString('CREATED_ON', SortingDirectionsDataType::DESC);
        $ds->getFilters()->addConditionFromString('USER__USERNAME', $authToken->getUsername(), ComparatorDataType::EQUALS);
        $ds->dataRead();
        $this->data = $ds;
        return $ds;
    }
}