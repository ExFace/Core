---
description: "Use when working on PHP classes for data mappings"
name: "Data mappings"
applyTo: "CommonLogic/DataSheets/Mappings/*.php"
---
# Data mappers and their mappings

Data mappers are used to transform data based on predefined mapping rules.
A mapper (`exface\Core\CommonLogic\DataSheets\DataSheetMapper`) is applied to a 
DataSheet based on its from-object and results in a 
new DataSheet based on its to-object. Each mapper contains one or more 
mappings of different types. A mapping is a UXON prototype class, that 
implements a particular data transformation.

Each mapping must implement the 
`exface\Core\Interfaces\DataSheets\DataMappingInterface`. 

The most common mapper is the `DataColumnMapper`, that maps data columns based 
on one object to corresponding columns based on another one. Many mappings, 
that implement more complex transformations, but still have `from` and `to` 
columns/expressions are based on this class.

## Documentation of mappings

Transformations performed by mappings are often difficult to understand for 
designers. There, it is often unclear, which mapping to use for a particular 
use case and how to configure it. Therefore, it is very important to 
document mappings well:

- Add class-level Docblocks with
  - the most important UXON properties
  - the simplest meaningful configuration
  - an `Examples` section with more complex configurations for typical use cases
- Use similar wording in the configuration options of different mappings to 
make it easier to understand a new mapping. See examples in the individual 
mapping prototype classes.

## Logbooks for additional insights when tracing

Because of the described difficulty to understand mappings, it is also important 
to use the `$logbook` passed to every `map()` method and explain the applied 
transformation there. The logbook will be visible in error debug information 
or when explicitly tracing server logic.

Add as few lines as posstible to the `$logbook`, but make sure all 
transformation steps are covered.

## Reading missing data

Very often the input data (from-sheet) of a mapper does not have all the 
columns needed for the mappings to work. The mapper will automatically 
attempt to read missing data in a preparation step: see 
`DataSheetMapper::prepareFromSheet()`. Every mapping must publish all 
required column expressions in its `getRequiredExpressions()` method. This 
way, the mapper knows, what data it needs and can read it once  for the 
entire data sheet before performing the mappings.

## Order of mappings

Mappings inside of a mapper are placed in an array and executed 
one-after-another - each mapping receiving the from-sheet and the to-sheet 
from its predecessor with all the chamges. 

Theoretically, designers can define mappings one-by-one in the exact 
desired order (using the generic `mappings` UXON property), but in reality 
it is much easier to use some of the autosuggested type-specific 
properties: `column_to_column_mappings`, `lookup_mappings`, etc. The 
corresponding setter methods are called in the order of UXON properties, 
thus resulting in groups of mappings of the same type in the final array. 