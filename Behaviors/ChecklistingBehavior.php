<?php
namespace exface\Core\Behaviors;

/**
 * WORK-IN-PROGRESS! Fills out a checklist base on formulas for its object on certain events.
 * 
 * The checklist is basically a set of checks - if any of them apply to the current object, a checklist item will be saved.
 * Checklist items can be warnings, hints, errors - anything, that is not critical, but important to see for the user.
 * 
 * The checklist itself should be stored in the data source, that holds the checked object. After all, checklist items are
 * bits of information about this object at a certain point in time, so they should be handled (e.g. backed up) together.
 * 
 * This behavior is similar to the `ValidatingBehavior` except for the result of the checks: in contrast to the
 * `ValidatingBehavior`, that produces errors if at least one check is applied, the `ChecklistingBehavior` merely saves
 * its findings to the data source allowing the user to deal with the separately.
 * 
 * ## Examples
 * 
 * ```
 *  {
 *      "check_on_update": [{
 *          "finding_data": {
 *              "object_alias": "my.APP.ORDER_CHECKLIST",
 *              "rows": [{
 *                  "MESSAGE": "This order includes products, that are not available for ordering yet!",
 *                  "MESSAGE_TYPE": "error",
 *                  "ORDER": "[#ID#]"
 *              }]     
 *          },
 *          "operator": "AND",
 *          "conditions": [{
 *              "expression": "[#ORDER_POS__PRODUCT__LIFECYCLE_STATE:MIN#]",
 *              "comparator": "<",
 *              "value": "50"
 *          }]
 *       }]
 *  }
 * 
 * ```
 * 
 * @author Andrej Kabachnik, Georg Bieger
 * 
 */
class ChecklistingBehavior extends AbstractValidatingBehavior
{

}