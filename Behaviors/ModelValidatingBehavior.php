<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Widgets\MessageList;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Events\Model\OnMetaObjectModelValidatedEvent;
use exface\Core\Events\Model\OnMetaAttributeModelValidatedEvent;
use exface\Core\Events\Action\OnActionPerformedEvent;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;

/**
 * This behavior validates the model when an editor is opened for the object, it is attached to.
 * 
 * Apart from built-in validation, this behavior will dispatch the following events allowing
 * third-party code to hook in additional validation logic or even to modify the editors
 * 
 * - `OnMetaObjectModelValidated` will be fired when an editor for a meta object is opened
 * - `OnMetaAttributeModelValidated` will be fired when an editor for an attribute is opened
 * 
 * All events are fired after the built-in validation is complete. Refer to the StateMachineBehavior
 * for an example on how these events can be used.
 * 
 * @author Andrej Kabachnik
 *
 */
class ModelValidatingBehavior extends AbstractBehavior
{
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::register()
     */
    public function register() : BehaviorInterface
    {
        $handler = [
            $this,
            'handleObjectEditDialog'
        ];
        $this->getWorkbench()->eventManager()->addListener(OnActionPerformedEvent::getEventName(), $handler);
        $this->setRegistered(true);
        return $this;
    }
    
    /**
     * Dispatches xxxModelValidatedEvents when editor-dialogs are opened for meta objects or attributes.
     * 
     * @triggers \exface\Core\Events\DataSheet\OnMetaObjectModelValidatedEvent
     * @triggers \exface\Core\Events\DataSheet\OnMetaAttributeModelValidatedEvent
     * 
     * @param DataSheetEventInterface $event
     * @return void
     */
    public function handleObjectEditDialog(OnActionPerformedEvent $event)
    {
        $action = $event->getAction();
        
        if (! ($action->is('exface.Core.ShowObjectEditDialog'))) {
            return;
        }
        
        /* @var $action \exface\Core\Actions\ShowObjectEditDialog */
        if ($action->getMetaObject()->is('exface.Core.OBJECT')) {
            $widget = $action->getWidget();
            foreach ($widget->getChildrenRecursive() as $child) {
                if (($child instanceof iShowSingleAttribute) && ($child instanceof iHaveValue)) {
                    $attrAlias = $child->getAttributeAlias();
                    if (($attrAlias === 'UID' || $attrAlias === 'ALIAS')) {
                        if ($child->hasValue() === false) {
                            break;
                        }
                        try {
                            $object = $this->getWorkbench()->model()->getObject($child->getValue());
                            $this->validateObject($object, $widget->getMessageList());
                            $this->getWorkbench()->eventManager()->dispatch(new OnMetaObjectModelValidatedEvent($object, $widget->getMessageList()));
                        } catch (\Throwable $e) {
                            $code = ($e instanceof ExceptionInterface) ? ': error ' . $e->getCode() : '';
                            $widget->getMessageList()->addError($e->getMessage(), 'Failed loading meta object' . $code . '!');
                            $this->getWorkbench()->getLogger()->logException($e);
                        }
                        break;
                    }
                }
            }
        }
        
        if ($action->getMetaObject()->is('exface.Core.ATTRIBUTE')) {
            $widget = $action->getWidget();
            $foundObject = false;
            $foundAttribute = false;
            foreach ($widget->getChildrenRecursive() as $child) {
                if (($child instanceof iShowSingleAttribute) && ($child instanceof iHaveValue)) {
                    $attrAlias = $child->getAttributeAlias();
                    if (($attrAlias === 'OBJECT')) {
                        if ($child->hasValue() === false) {
                            break;
                        }
                        $foundObject = true;
                        try {
                            $object = $this->getWorkbench()->model()->getObject($child->getValue());
                        } catch (\Throwable $e) {
                            $this->getWorkbench()->getLogger()->logException($e);
                        }
                    }
                    if (($attrAlias === 'ALIAS')) {
                        if ($child->hasValue() === false) {
                            break;
                        }
                        $foundAttribute = true;
                        try {
                            $attribute = $object->getAttribute($child->getValue());
                        } catch (\Throwable $e) {
                            $this->getWorkbench()->getLogger()->logException($e);
                        }
                    }
                    
                    if ($foundAttribute === true && $foundObject === true && $attribute !== null) {
                        $this->getWorkbench()->eventManager()->dispatch(new OnMetaAttributeModelValidatedEvent($attribute, $widget->getMessageList()));
                        break;
                    }
                }
            }
        }
    }
    
    protected function validateObject(MetaObjectInterface $object, MessageList $messageList)
    {
        $this->validateObjectAttributes($object, $messageList);
        $this->validateObjectUid($object, $messageList);
        $this->validateObjectLabel($object, $messageList);
        $this->validateObjectDataSource($object, $messageList);
        return;
    }
    
    protected function validateAttribute(MetaAttributeInterface $attribute, MessageList $messageList)
    {
        // TODO add validation for attributes
        return;
    }
    
    protected function validateObjectUid(MetaObjectInterface $object, MessageList $messageList)
    {
        if ($object->hasUidAttribute() === false) {
            $messageList->addMessageByCode('734GQRL', 'Object as no UID attribute!');
        }
    }
    
    protected function validateObjectLabel(MetaObjectInterface $object, MessageList $messageList)
    {
        if ($object->hasLabelAttribute() === false) {
            $messageList->addMessageByCode('734GDAX', 'Object has no LABEL attribute!');
        } else {
            $labels = [];
            foreach ($object->getAttributes() as $attr) {
                if ($attr->isLabelForObject()) {
                    $labels[] = $attr;
                }
            }
            
            if (count($labels) > 1) {
                $messageList->addMessageByCode('73A6BVD', 'Object has multiple LABEL attributes!');
            }
        }
    }
    
    protected function validateObjectDataSource(MetaObjectInterface $object, MessageList $messageList)
    {
        if (($object->getDataAddress() === null ||  $object->getDataAddress() === '' || $object->hasDataSource() === false) && $object->isReadable()) {
            $messageList->addMessageByCode('734GUW2', 'Object without a data source cannot be readable!');
        }
    }
    
    protected function validateObjectAttributes(MetaObjectInterface $object, MessageList $messageList)
    {
        if ($object->getAttributes()->isEmpty()) {
            $messageList->addMessageByCode('734GWLL', 'Object has no attributes!');
        }
        
        if ($object->isReadable() === false && $object->getAttributes()->getReadable()->isEmpty() === false) {
            $messageList->addMessageByCode('734GZDR', 'Object is not readable, but has readable attributes!');
        }
    }
    
    protected function translate(string $messageId, array $placeholderValues = null, float $pluralNumber = null) : string
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate($messageId, $placeholderValues, $pluralNumber);
    }
}