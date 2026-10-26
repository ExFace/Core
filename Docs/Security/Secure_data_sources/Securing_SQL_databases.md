# Securing SQL databases

## Required priviledges

Any DB connection will always access the DB with a certain user. Although connections support different credentials per workbench user, in the case of SQL databases it is common practice to use a shared DB user for everyone. This

- Data read/write permissions according to the scope of the app (most apps will need both permissions)
- Permissions to modify DB structure (CREATE/ALTER tables, views, functions and stored procedures) in case automated database migrations are to be used - recommended!

## MariaDB

- [Step-by-step tutorial to enable data-at-rest encryption](https://webdock.io/en/docs/how-guides/security-guides/how-to-enable-encryption-mariadb)

## MySQL

- [Official documentation for data-at-rest encryption](https://dev.mysql.com/doc/refman/8.4/en/innodb-data-encryption.html)
- [Step-by-step tutorial for data encryption](https://openspecimen.atlassian.net/wiki/spaces/CAT/pages/693338127/How+to+implement+DB+encryption+for+MySQL)
- [Another tutorial for encryption and SSL](https://www.einfochips.com/blog/mysql-data-encryption-a-step-by-step-guide-to-using-a-file-based-keyring-component/)