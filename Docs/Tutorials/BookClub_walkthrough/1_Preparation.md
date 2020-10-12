# Things to do before the BookClub tutorial

We will use a MySQL database to store all the data required for the [book club tutorial](index.md).

## 1. Make sure the workbench in up and running

Follow the [installation guide](../../Installation/index.md) for your server stack.

## 2. Create a new database for the book club

Copy&paste the following SQL to your favorite SQL management tool. It will create a database named `tutorial_bookclub`. You can change that name, of course, but you will need to keep track of it in all subsequent steps yourself!

```
CREATE DATABASE IF NOT EXISTS `tutorial_bookclub` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `tutorial_bookclub`;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `book` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime NOT NULL,
  `created_by_user_id` binary(16) NOT NULL,
  `modified_on` datetime NOT NULL,
  `modified_by_user_id` binary(16) NOT NULL,
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
  `created_on` datetime NOT NULL,
  `created_by_user_id` binary(16) NOT NULL,
  `modified_on` datetime NOT NULL,
  `modified_by_user_id` binary(16) NOT NULL,
  `category_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime NOT NULL,
  `created_by_user_id` binary(16) NOT NULL,
  `modified_on` datetime NOT NULL,
  `modified_by_user_id` binary(16) NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `language` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime NOT NULL,
  `created_by_user_id` binary(16) NOT NULL,
  `modified_on` datetime NOT NULL,
  `modified_by_user_id` binary(16) NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `loan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_on` datetime NOT NULL,
  `created_by_user_id` binary(16) NOT NULL,
  `modified_on` datetime NOT NULL,
  `modified_by_user_id` binary(16) NOT NULL,
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
  `created_on` datetime NOT NULL,
  `created_by_user_id` binary(16) NOT NULL,
  `modified_on` datetime NOT NULL,
  `modified_by_user_id` binary(16) NOT NULL,
  `name` varchar(50) NOT NULL,
  `user_oid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
COMMIT;

```

## 3. Proceed with the next step

Start a new metamodel by [creating an app](2_Creating_a_new_app.md).