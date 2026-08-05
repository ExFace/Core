---
description: "Use when working on PHP classes for formulas"
name: "Formulas"
applyTo: "Formulas/*.php"
---
# Formulas

DataSheets can contain calculated columns based on Excel-like formulas. 
These formulas are small PHP classes, that are stored in the `Formulas` 
folder of every app. They must implement the 
`\exface\Core\Interfaces\FormulaInterface`. 

If some cosmetic operations are required for data, implementing a formula is 
an easy option. Generally useful formulas belong in the Core app, while 
client-specific formulas should be implemented in the client app.

## Naming convention

Class names are the formula names used in UXON, so `=Sum()` will instantiate 
the class `\exface\Core\Formulas\Sum`. Formulas from other apps, than 
`exface\core` need the app alias as a namespace: e.g. `my.App.
MyCustomFormula` will instantiate `\my\App\Formulas\MyCustomFormula`.

## Similarity to Excel formulas

Formulas should be similar to Excel formulas in their use and behavior if 
there are corresponding formulas in Excel. However, if the use of Excel 
formulas is cumbersome, we should introduce better alternatives: e.g. 
`=Substring()` instead of the combination of `MID`, `LEFT`, `RIGHT`, and `SEARCH`.

## Implementation guidelines

Currently all formulas share the base class 
`\exface\Core\CommonLogic\Model\Formula`, which includes a lot of common 
logic. Formulas extending this class only need a few methods:

- `run()` contains the actual logic of the formula. It can have any number 
  of arguments, but they all must be optional.
- `getDataType()` returns an instance of a DataType class for the 
  expected result of the formula.

## Static vs. data-driven formulas

Formulas can be static or data-driven. Static formulas do not required a 
DataSheet to be evaluated, while non-static formulas do. The DataSheet is 
accessible from inside the formula using `$this->getDataSheet()` and 
`$this->getCurrentRowNumber()` if the formula is run in the context of a 
DataSheet. If the formula is static, these methods will return null, which 
may need an exception depending on the logic of a formula.

Wether the formula is static or not will often depend on the arguments it 
has. If all arguments are static, the formula is likely to be static as well.
There is already an `isStatic()` method in the base `Formula` class, that 
checks that.

## Exception handling

Errors in formulas must be thrown as `exface\Core\Exceptions\FormulaError` 
or a derivative of it. This ensures, that logs will contain as much 
information as possible.