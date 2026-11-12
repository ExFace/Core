---
name: "TimeStampingBehavior and concurrency detection"
description: "Use when updating data multiple times consequently to avoid concurrency errors or when getting time stamping errors in general"
---
# TimeStampingBehavior

The TimeStampingBehavior takes care of optimistic locking by saving time and user of creation and last change of its object. It is often configured for a data source base object and inherited by all objects from that data source. Most SQL based objects will use it. 

It hooks into every update operation and compares the last-change time with the time in the provided data (e.g. received from UI or some logic):
- if the time matches, the operation is permitted and the time is updated to now
- if the time does not match, an exception is thrown. 

This makes the data column, that saves the last modification time crutial for all update operations (without it, no check can be made). However, outside code should not know, which column it is - therefore the behavior marks it's columns as system columns. Thus, if system columns are used for all operations as required, the modifications column will be there too. 

## Rules for typical situations

### Create + update

If you need to create some data and update it later during the same request (often when logging some precesses), reuse a copy of the create sheet for update operations. 

```php
// Create data
$createSheet = DataSheetFactory::create();
$createSheet->getColumns()->addFromSystemAttributes();
// add columns and rows for create
$createSheet->dataCreate();

// Update the same records
// Copy the create-sheet leaving only system columns
$updateSheet = $createSheet->extractSystemColumns();
// Add columns and values to update. The row order stays the same as in the create sheet. Do not overwrite system columns if not explicitly required! 
$updateSheet->dataUpdate()
```

### Multi-update 

Use the same logic as in the create+update case. But read the data before first update. Check, if that really is the data you want to update and keep passing to the system columns from on update to another. 