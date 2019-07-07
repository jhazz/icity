-- MySQL Script generated by MySQL Workbench
-- Sun Jun 23 17:57:53 2019
-- Model: New Model    Version: 1.0
-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL,ALLOW_INVALID_DATES';

-- -----------------------------------------------------
-- Schema mydb
-- -----------------------------------------------------
-- -----------------------------------------------------
-- Schema test
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Schema test
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `test` DEFAULT CHARACTER SET utf8 ;
-- -----------------------------------------------------
-- Schema smart1
-- -----------------------------------------------------
-- Smart 1
USE `test` ;

-- -----------------------------------------------------
-- Table `test`.`component_sources`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`component_sources` (
  `COMPONENT_SOURCE_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `MASS_NAME` VARCHAR(50) NULL DEFAULT NULL,
  `SPECIAL_NAME` VARCHAR(50) NULL DEFAULT NULL,
  PRIMARY KEY (`COMPONENT_SOURCE_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 7
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`components`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`components` (
  `COMPONENT_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `PART_OF_COMPONENT_ID` INT(11) NULL DEFAULT NULL,
  `NAME` VARCHAR(50) NULL DEFAULT NULL,
  `DESCRIPTION` VARCHAR(150) NULL DEFAULT NULL,
  `LAST_SOURCE_ID` INT(11) NULL DEFAULT NULL,
  PRIMARY KEY (`COMPONENT_ID`),
  INDEX `FK_components_component_sources` (`LAST_SOURCE_ID` ASC),
  CONSTRAINT `FK_components_component_sources`
    FOREIGN KEY (`LAST_SOURCE_ID`)
    REFERENCES `test`.`component_sources` (`COMPONENT_SOURCE_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 18
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`component_aggregates`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`component_aggregates` (
  `COMPONENT_AGGREGATE_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `PARENT_COMPONENT_ID` INT(11) NOT NULL DEFAULT '0',
  `CHILD_COMPONENT_ID` INT(11) NOT NULL DEFAULT '0',
  `CHILD_AMOUNT` DOUBLE NOT NULL DEFAULT '0',
  `DETAILS` VARCHAR(150) NULL DEFAULT NULL,
  PRIMARY KEY (`COMPONENT_AGGREGATE_ID`),
  INDEX `FK_component_aggregate_components` (`PARENT_COMPONENT_ID` ASC),
  INDEX `FK_component_aggregate_components_2` (`CHILD_COMPONENT_ID` ASC),
  CONSTRAINT `FK_component_aggregate_components`
    FOREIGN KEY (`PARENT_COMPONENT_ID`)
    REFERENCES `test`.`components` (`COMPONENT_ID`),
  CONSTRAINT `FK_component_aggregate_components_2`
    FOREIGN KEY (`CHILD_COMPONENT_ID`)
    REFERENCES `test`.`components` (`COMPONENT_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 4
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`config_components`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`config_components` (
  `CONFIG_COMPONENT_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `PRODUCT_CONFIG_ID` INT(11) NULL DEFAULT NULL,
  `COMPONENT_ID` INT(11) NULL DEFAULT NULL,
  `DELTA_AMOUNT` DOUBLE NULL DEFAULT NULL,
  PRIMARY KEY (`CONFIG_COMPONENT_ID`),
  INDEX `FK_config_components_components` (`COMPONENT_ID` ASC),
  CONSTRAINT `FK_config_components_components`
    FOREIGN KEY (`COMPONENT_ID`)
    REFERENCES `test`.`components` (`COMPONENT_ID`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`currencies`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`currencies` (
  `CURRENCY_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `NAME` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`CURRENCY_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 4
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`customers`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`customers` (
  `CUSTOMER_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `NAME` VARCHAR(150) NOT NULL,
  `PLACE_ID` INT(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`CUSTOMER_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 3
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`sys_persons`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`sys_persons` (
  `PERSON_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `USER_ID` INT(11) NULL DEFAULT NULL,
  `FIRST_NAME` VARCHAR(50) NULL DEFAULT NULL,
  `LAST_NAME` VARCHAR(50) NULL DEFAULT NULL,
  `MIDDLE_NAME` VARCHAR(50) NULL DEFAULT NULL,
  `PHONE` VARCHAR(50) NULL DEFAULT NULL,
  `PHONE_2` VARCHAR(50) NULL DEFAULT NULL,
  `CONTACT_EMAIL` VARCHAR(150) NULL DEFAULT NULL,
  `ORG_NAME` VARCHAR(150) NULL DEFAULT NULL,
  `DATE_BEGIN` DATE NULL DEFAULT NULL,
  `DATE_END` DATE NULL DEFAULT NULL,
  PRIMARY KEY (`PERSON_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 11
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`cust_persons`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`cust_persons` (
  `CUST_PERSON_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `PERSON_ID` INT(11) NULL DEFAULT NULL,
  `CUSTOMER_ID` INT(11) NULL DEFAULT NULL,
  `DATE_BEGIN` DATE NULL DEFAULT NULL,
  `DATE_END` DATE NULL DEFAULT NULL,
  PRIMARY KEY (`CUST_PERSON_ID`),
  INDEX `FK_cust_persons_customers` (`CUSTOMER_ID` ASC),
  INDEX `FK_cust_persons_persons` (`PERSON_ID` ASC),
  CONSTRAINT `FK_cust_persons_customers`
    FOREIGN KEY (`CUSTOMER_ID`)
    REFERENCES `test`.`customers` (`CUSTOMER_ID`),
  CONSTRAINT `FK_cust_persons_persons`
    FOREIGN KEY (`PERSON_ID`)
    REFERENCES `test`.`sys_persons` (`PERSON_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 4
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`geo_points`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`geo_points` (
  `GEO_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `POSITION` POINT NULL DEFAULT NULL,
  PRIMARY KEY (`GEO_ID`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`geo_routes`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`geo_routes` (
  `ROUTE_IT` INT(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`ROUTE_IT`))
ENGINE = MyISAM
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`sys_users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`sys_users` (
  `USER_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `LOGIN` VARCHAR(50) NOT NULL,
  `ACTIVE_PERSON_ID` INT(11) NULL DEFAULT NULL,
  `IS_DISABLED` INT(11) NULL DEFAULT NULL,
  `CNONCENONCE` VARCHAR(50) NULL DEFAULT NULL,
  `PASS_HASH` VARCHAR(50) NULL DEFAULT NULL,
  `USER_EMAIL` VARCHAR(150) NULL DEFAULT NULL,
  PRIMARY KEY (`USER_ID`),
  INDEX `FK_users_persons` (`ACTIVE_PERSON_ID` ASC),
  CONSTRAINT `FK_users_persons`
    FOREIGN KEY (`ACTIVE_PERSON_ID`)
    REFERENCES `test`.`sys_persons` (`PERSON_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 13
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`orders`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`orders` (
  `ORDER_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `USER_ID` INT(11) NOT NULL DEFAULT '0',
  `CUST_PERSON_ID` INT(11) NOT NULL DEFAULT '0',
  `CUSTOMER_ID` INT(11) NOT NULL DEFAULT '0',
  `PROJECT_ID` INT(11) NOT NULL DEFAULT '0',
  `OPEN_DATE` DATE NULL DEFAULT NULL,
  `SHIPPING_PLAN_DATE` DATE NULL DEFAULT NULL,
  `NAME` VARCHAR(120) NULL DEFAULT NULL,
  `ORDER_PAYMENT_STATUS_ID` INT(11) NULL DEFAULT NULL,
  `COMMENTS` TEXT NULL DEFAULT NULL,
  `ORDER_READY_STATUS_ID` INT(11) NULL DEFAULT NULL,
  PRIMARY KEY (`ORDER_ID`),
  INDEX `FK_orders_users` (`USER_ID` ASC),
  INDEX `FK_orders_customers` (`CUSTOMER_ID` ASC),
  CONSTRAINT `FK_orders_customers`
    FOREIGN KEY (`CUSTOMER_ID`)
    REFERENCES `test`.`customers` (`CUSTOMER_ID`),
  CONSTRAINT `FK_orders_users`
    FOREIGN KEY (`USER_ID`)
    REFERENCES `test`.`sys_users` (`USER_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 11
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`product_groups`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`product_groups` (
  `PRODUCT_GROUP_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `PARENT_ID` INT(11) NULL DEFAULT NULL,
  `NAME` VARCHAR(80) NULL DEFAULT NULL,
  `SUB_NAME` VARCHAR(80) NULL DEFAULT NULL,
  `TITLE` VARCHAR(180) NULL DEFAULT NULL,
  PRIMARY KEY (`PRODUCT_GROUP_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 104
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`product_types`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`product_types` (
  `PRODUCT_TYPE_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `NAME` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`PRODUCT_TYPE_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 4
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`products`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`products` (
  `PRODUCT_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `PRODUCT_GROUP_ID` INT(11) NULL DEFAULT NULL,
  `PRODUCT_TYPE_ID1` INT(11) NULL DEFAULT NULL,
  `TITLE` VARCHAR(80) NULL DEFAULT NULL,
  `PRODUCT_TYPE_ID2` INT(11) NULL DEFAULT NULL,
  `PRODUCT_SECOND_GROUP_ID` INT(11) NULL DEFAULT NULL,
  `SKU` VARCHAR(30) NULL DEFAULT NULL,
  PRIMARY KEY (`PRODUCT_ID`),
  INDEX `FK_products_product_groups` (`PRODUCT_GROUP_ID` ASC),
  INDEX `fk_products_product_types1_idx` (`PRODUCT_TYPE_ID1` ASC),
  INDEX `fk_products_product_types2_idx` (`PRODUCT_TYPE_ID2` ASC),
  CONSTRAINT `FK_products_product_groups`
    FOREIGN KEY (`PRODUCT_GROUP_ID`)
    REFERENCES `test`.`product_groups` (`PRODUCT_GROUP_ID`),
  CONSTRAINT `fk_products_product_types1`
    FOREIGN KEY (`PRODUCT_TYPE_ID1`)
    REFERENCES `test`.`product_types` (`PRODUCT_TYPE_ID`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_products_product_types2`
    FOREIGN KEY (`PRODUCT_TYPE_ID2`)
    REFERENCES `test`.`product_types` (`PRODUCT_TYPE_ID`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
AUTO_INCREMENT = 35
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`order_item`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`order_item` (
  `ORDER_ITEM_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `PRODUCT_ID` INT(11) NOT NULL,
  `ORDER_ID` INT(11) NOT NULL,
  `AMOUNT` FLOAT NOT NULL,
  `DESCRIPTION` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`ORDER_ITEM_ID`),
  INDEX `FK_order_item_products` (`PRODUCT_ID` ASC),
  INDEX `FK_order_item_orders` (`ORDER_ID` ASC),
  CONSTRAINT `FK_order_item_orders`
    FOREIGN KEY (`ORDER_ID`)
    REFERENCES `test`.`orders` (`ORDER_ID`),
  CONSTRAINT `FK_order_item_products`
    FOREIGN KEY (`PRODUCT_ID`)
    REFERENCES `test`.`products` (`PRODUCT_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 42
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`order_payment_statuses`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`order_payment_statuses` (
  `ORDER_PAYMENT_STATUS_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `STATUS_LABEL` VARCHAR(50) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ORDER_PAYMENT_STATUS_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 5
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`order_ready_statuses`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`order_ready_statuses` (
  `ORDER_READY_STATUS_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `NAME` VARCHAR(50) NULL DEFAULT NULL,
  PRIMARY KEY (`ORDER_READY_STATUS_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 7
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`parameters`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`parameters` (
  `PARAMETER_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `PARAMETER_GROUP_ID` INT(11) NULL DEFAULT NULL,
  `NAME` VARCHAR(100) NULL DEFAULT NULL,
  `UNITS` VARCHAR(50) NULL DEFAULT NULL,
  PRIMARY KEY (`PARAMETER_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 7
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`prices`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`prices` (
  `PRICE_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `CURRENCY_ID` INT(11) NOT NULL,
  `PRODUCT_ID` INT(11) NOT NULL,
  `VALUE` DECIMAL(10,2) NOT NULL COMMENT 'себестоимость',
  PRIMARY KEY (`PRICE_ID`),
  INDEX `FK_prices_currencies` (`CURRENCY_ID` ASC),
  INDEX `FK_prices_products` (`PRODUCT_ID` ASC),
  CONSTRAINT `FK_prices_currencies`
    FOREIGN KEY (`CURRENCY_ID`)
    REFERENCES `test`.`currencies` (`CURRENCY_ID`),
  CONSTRAINT `FK_prices_products`
    FOREIGN KEY (`PRODUCT_ID`)
    REFERENCES `test`.`products` (`PRODUCT_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 7
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`product_components`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`product_components` (
  `PRODUCT_COMPONENT_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `PRODUCT_ID` INT(11) NULL DEFAULT NULL,
  `COMPONENT_ID` INT(11) NULL DEFAULT NULL,
  `AMOUNT` DOUBLE NULL DEFAULT NULL,
  PRIMARY KEY (`PRODUCT_COMPONENT_ID`),
  INDEX `FK_product_components_products` (`PRODUCT_ID` ASC),
  INDEX `FK_product_components_components` (`COMPONENT_ID` ASC),
  CONSTRAINT `FK_product_components_components`
    FOREIGN KEY (`COMPONENT_ID`)
    REFERENCES `test`.`components` (`COMPONENT_ID`),
  CONSTRAINT `FK_product_components_products`
    FOREIGN KEY (`PRODUCT_ID`)
    REFERENCES `test`.`products` (`PRODUCT_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 73
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`product_parameters`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`product_parameters` (
  `PRODUCT_PARAMETER_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `PRODUCT_ID` INT(11) NOT NULL DEFAULT '0',
  `PARAMETER_ID` INT(11) NOT NULL DEFAULT '0',
  `VALUE_STRING` VARCHAR(250) NOT NULL DEFAULT '0',
  PRIMARY KEY (`PRODUCT_PARAMETER_ID`),
  INDEX `FK__products` (`PRODUCT_ID` ASC),
  INDEX `FK__parameters` (`PARAMETER_ID` ASC),
  CONSTRAINT `FK__parameters`
    FOREIGN KEY (`PARAMETER_ID`)
    REFERENCES `test`.`parameters` (`PARAMETER_ID`),
  CONSTRAINT `FK__products`
    FOREIGN KEY (`PRODUCT_ID`)
    REFERENCES `test`.`products` (`PRODUCT_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 8
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`projects`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`projects` (
  `PROJECT_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `NAME` VARCHAR(150) NULL DEFAULT NULL,
  `CUSTOMER_ID` INT(11) NULL DEFAULT NULL,
  PRIMARY KEY (`PROJECT_ID`),
  INDEX `FK_projects_customers` (`CUSTOMER_ID` ASC),
  CONSTRAINT `FK_projects_customers`
    FOREIGN KEY (`CUSTOMER_ID`)
    REFERENCES `test`.`customers` (`CUSTOMER_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 4
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`sys_clients`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`sys_clients` (
  `CLIENT_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `CLIENT_KEY` VARCHAR(50) NULL DEFAULT NULL,
  `USER_ID` INT(11) NULL DEFAULT NULL,
  `REFRESH_TIME` TIMESTAMP NULL DEFAULT NULL,
  `OPEN_TIME` TIMESTAMP NULL DEFAULT NULL,
  `USER_ASK_REMEMBER` INT(11) NULL DEFAULT NULL,
  PRIMARY KEY (`CLIENT_ID`),
  UNIQUE INDEX `CLIENT_KEY` (`CLIENT_KEY` ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 21
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`sys_form_nonces`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`sys_form_nonces` (
  `SERVER_SECRET` VARCHAR(50) NOT NULL,
  `OPEN_TIME` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `NONCE` VARCHAR(100) NOT NULL,
  `SESSION_KEY` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`SERVER_SECRET`),
  UNIQUE INDEX `NONCE` (`NONCE` ASC))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `test`.`sys_sessions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`sys_sessions` (
  `SESSION_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `SESSION_KEY` VARCHAR(41) NULL DEFAULT NULL,
  `CLIENT_ID` INT(11) NOT NULL DEFAULT '0',
  `CLIENT_IP_ADDR` VARCHAR(50) NULL DEFAULT NULL,
  `SERIALIZED_DATA` VARCHAR(2048) NULL DEFAULT NULL,
  `OPEN_TIME` TIMESTAMP NULL DEFAULT NULL,
  `REFRESH_TIME` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`SESSION_ID`),
  INDEX `SESSION_KEY` (`SESSION_KEY` ASC),
  INDEX `fk_sys_sessions_sys_clients1_idx` (`CLIENT_ID` ASC))
ENGINE = MEMORY
AUTO_INCREMENT = 2
DEFAULT CHARACTER SET = utf8
MAX_ROWS = 1000;


-- -----------------------------------------------------
-- Table `test`.`sys_signups`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `test`.`sys_signups` (
  `SIGNUP_ID` INT(11) NOT NULL AUTO_INCREMENT,
  `LOGIN` VARCHAR(50) NOT NULL,
  `USER_EMAIL` VARCHAR(70) NOT NULL,
  `PASS_HASH` VARCHAR(150) NOT NULL,
  `CNONCENONCE` VARCHAR(200) NULL DEFAULT NULL,
  `IP_SOURCE` VARCHAR(46) NOT NULL,
  `FIRST_NAME` VARCHAR(50) NOT NULL,
  `LAST_NAME` VARCHAR(50) NOT NULL,
  `MIDDLE_NAME` VARCHAR(50) NOT NULL,
  `REQUEST_TIME` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `PHONE` VARCHAR(50) NOT NULL,
  `PHONE_2` VARCHAR(50) NOT NULL,
  `COMMENTS` TEXT NULL DEFAULT NULL,
  `ORG_NAME` VARCHAR(50) NULL DEFAULT NULL,
  PRIMARY KEY (`SIGNUP_ID`))
ENGINE = InnoDB
AUTO_INCREMENT = 22
DEFAULT CHARACTER SET = utf8;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;