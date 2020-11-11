# Things to do before the BookClub tutorial

#### < Previous | [BookClub tutorial](index.md) | [Next >](02_Creating_a_new_app.md)

## 1. Check the requirements

The tutorial assumes, that 

- you have the workbench up and running as described in the [installation guides](../../Installation/index.md)).
- you are logged on as a superuser (e.g. `admin/password`).
- your current language is english (either set for your user or as `SERVER.DEFAULT_LOCALE` in [System.config.json](../../Administration/Configuration/index.md).
- you have the `exface.JEasyUIFacade` as your default facade. This should be the case if you did not change anything in the installation guide.
- you have the `exface.UI5Facade` installed. This is actually not a must, but the example app will be rendered using the SAP OpenUI5 JavaScript framework as a good example for responsive design.

Should you use another user or facade, the screenshots may look different from what you see. The general logic remains the same!

## 2. Create a new database for the book club

We will use a MySQL database to store all the data required for the [book club tutorial](index.md).

Copy&paste the following SQL to your favorite SQL management tool. It will create a database named `tutorial_bookclub`. You can change that name, of course, but you will need to keep track of it in all subsequent steps yourself!

```
CREATE DATABASE IF NOT EXISTS `tutorial_bookclub` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `tutorial_bookclub`;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `book` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by_user_id` binary(16) DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by_user_id` binary(16) DEFAULT NULL,
  `title` varchar(50) NOT NULL,
  `series` varchar(50) DEFAULT NULL,
  `author` varchar(200) DEFAULT NULL,
  `isbn` varchar(13) DEFAULT NULL,
  `publisher` varchar(50) DEFAULT NULL,
  `book_size` varchar(10) NULL DEFAULT '',
  `year` int(4) DEFAULT NULL,
  `age_min` int(2) DEFAULT NULL,
  `age_max` int(2) DEFAULT NULL,
  `pages` int(11) DEFAULT NULL,
  `language_id` int(11) DEFAULT NULL,
  `owner_id` int(11) NOT NULL,
  `owner_comment` varchar(200) DEFAULT NULL,
  `owner_rating` int(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `book_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by_user_id` binary(16) DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by_user_id` binary(16) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by_user_id` binary(16) DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by_user_id` binary(16) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `language` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by_user_id` binary(16) DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by_user_id` binary(16) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `loan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by_user_id` binary(16) DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by_user_id` binary(16) DEFAULT NULL,
  `member_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `taken` date NOT NULL,
  `given_back` date DEFAULT NULL,
  `comment` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `member` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime DEFAULT NULL,
  `created_by_user_id` binary(16) DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by_user_id` binary(16) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `user_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
COMMIT;

```

## 3. Proceed with the next step

Start a new metamodel by [creating an app](02_Creating_a_new_app.md).

### < Previous | [BookClub tutorial](index.md) | [Next >](02_Creating_a_new_app.md)