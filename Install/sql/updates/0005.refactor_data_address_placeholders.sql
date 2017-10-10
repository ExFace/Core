UPDATE exf_attribute SET data_properties = REPLACE(data_properties, '#alias#', '#~alias#');
UPDATE exf_attribute SET data_properties = REPLACE(data_properties, '#value#', '#~value#');
UPDATE exf_attribute SET data = REPLACE(data, '#alias#', '#~alias#');
UPDATE exf_attribute SET data = REPLACE(data, '#value#', '#~value#');

UPDATE exf_attribute SET data_properties = REPLACE(data_properties, '#ALIAS#', '#~ALIAS#');
UPDATE exf_attribute SET data_properties = REPLACE(data_properties, '#VALUE#', '#~VALUE#');
UPDATE exf_attribute SET data = REPLACE(data, '#ALIAS#', '#~ALIAS#');
UPDATE exf_attribute SET data = REPLACE(data, '#VALUE#', '#~VALUE#');

UPDATE exf_object SET data_address_properties = REPLACE(data_address_properties, '#alias#', '#~alias#');
UPDATE exf_object SET data_address_properties = REPLACE(data_address_properties, '#value#', '#~value#');
UPDATE exf_object SET data_address = REPLACE(data_address, '#alias#', '#~alias#');
UPDATE exf_object SET data_address = REPLACE(data_address, '#value#', '#~value#');

UPDATE exf_object SET data_address_properties = REPLACE(data_address_properties, '#ALIAS#', '#~ALIAS#');
UPDATE exf_object SET data_address_properties = REPLACE(data_address_properties, '#VALUE#', '#~VALUE#');
UPDATE exf_object SET data_address = REPLACE(data_address, '#ALIAS#', '#~ALIAS#');
UPDATE exf_object SET data_address = REPLACE(data_address, '#VALUE#', '#~VALUE#');