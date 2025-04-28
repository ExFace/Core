<?php
namespace exface\Core\Uxon\Snippets;

use exface\Core\CommonLogic\Uxon\AbstractUxonSnippet;
use exface\Core\Interfaces\Uxon\UxonArraySnippetInterface;
use exface\Core\Interfaces\Uxon\UxonSnippetInterface;

/**
 * Allows you to insert any number of elements into a UXON array.
 * 
 * ### Examples
 * 
 * When configuring an array snippet, treat 
 * 
 * ```
 * 
 * // Snippet configuration.
 * {
 *     "parameters": [
 *          {
 *              "name": "ErrorText",
 *              "Description": "Fehlermeldung",
*               "required": false
 *          },
 *          {
 *               "name": "ConditionAttribute",
 *               "Description": "Attribute alias that will be used in the condition.",
 *                "required": true
 *           },
 *           {
 *                "name": "ConditionValue",
 *                "Description": "The condition will check against this value with EQUALS.",
 *                 "required": true
 *            }
 *      ],
 *      "snippet": [
 *          {
 *              "error_text": "[#ErrorText#]",
 *              "conditions": [
 *                  {
 *                      "expression": "[#ConditionAttribute#]",
 *                      "comparator": "==",
 *                      "value": "[#ConditionValue#]"
 *                  }
 *              ]
 *          }
 *      ]
 * }
 * 
 * ```
 * 
 * ```
 * 
 * // Snippet usage.
 * {
 *      "widget_type": "DataTable",
 *      "object_alias": "axenox.ETL.step_note",
 *      "columns": [
 *          {
 *              "attribute_alias": "LABEL"
 *          }
 *      ],
 *      "buttons": [
 *          {
 *              "action": {
 *                  "alias": "exface.core.ShowObjectEditDialog",
 *                  "input_invalid_if": [
 *                      {
 *                          "~snippet": "geb.testing.TEST",
 *                          "parameters": {
 *                              "ErrorText": "Datacheck failed!",
 *                              "ConditionAttribute": "message",
 *                              "ConditionValue": "INVALID"
 *                          }
 *                      }
 *                  ]
 *              }
 *          }
 *      ]
 * }
 * 
 * ```
 * 
 */
class GenericArraySnippet extends AbstractUxonSnippet implements UxonArraySnippetInterface
{

    /**
     * The snippet (template)
     *
     * @uxon-property snippet
     * @uxon-type array
     * @uxon-required true
     * @uxon-template [""]
     *
     * @param string|\exface\Core\CommonLogic\UxonObject $uxon
     * @return UxonSnippetinterface
     */
    protected function setSnippet($uxonArrayOrString) : UxonSnippetInterface
    {
        return parent::setSnippet($uxonArrayOrString);
    }
}