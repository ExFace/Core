/* Fix known attribute conflicts */
DELETE FROM exf_attribute WHERE oid = 0x11e6fe78c47c2bd58b8b74e5434dc47d;
DELETE FROM exf_attribute WHERE oid = 0x11e7ba5f4c3788baa1be0050568905af;
DELETE FROM exf_attribute WHERE oid = 0x11e8ebd57f85636e87f10205857feb81;
DELETE FROM exf_attribute WHERE oid = 0x11e8ebd57f85e16087f10205857feb81;
DELETE FROM exf_attribute WHERE oid = 0x11e8ebd57f86110587f10205857feb81;
UPDATE exf_attribute SET attribute_alias = 'LABEL' WHERE oid = 0x36350000000000000000000000000000;
UPDATE exf_attribute SET attribute_alias = 'LABEL' WHERE oid = 0x11e84eeb4835bd3db1870205857feb80;
UPDATE exf_attribute SET attribute_alias = 'LABEL' WHERE oid = 0x11e7ce85ac25ec20a3da0205857feb80;
UPDATE exf_attribute SET attribute_alias = 'BUSINESS_UNIT' WHERE oid = 0x37380000000000000000000000000000;
UPDATE exf_data_type SET data_type_alias = 'GenericNumberEnum' WHERE oid = 0x11e7c39621725c1e8001e4b318306b9a;

/* Now add the indexes */
ALTER TABLE `exf_attribute` ADD UNIQUE `Alias unique per object` (`object_oid`, `attribute_alias`);
ALTER TABLE `exf_object_action` ADD UNIQUE `Alias unique per app` (`action_app_oid`, `alias`);
ALTER TABLE `exf_data_type` ADD UNIQUE `Alias unique per app` (`app_oid`, `data_type_alias`);
ALTER TABLE `exf_data_source` ADD UNIQUE `Alias unique per app` (`app_oid`, `alias`);
ALTER TABLE `exf_data_connection` ADD UNIQUE `Alias unique per app` (`alias`, `app_oid`);