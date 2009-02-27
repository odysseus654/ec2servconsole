-- phpMyAdmin SQL Dump
-- version 2.10.0.2
-- http://www.phpmyadmin.net
-- 
-- Host: localhost
-- Generation Time: Feb 27, 2009 at 12:13 AM
-- Server version: 5.0.27
-- PHP Version: 5.2.6

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

-- 
-- Database: `servconsole`
-- 

-- --------------------------------------------------------

-- 
-- Table structure for table `account`
-- 

CREATE TABLE `account` (
  `accountID` int(11) NOT NULL auto_increment,
  `descr` varchar(200) default NULL,
  `accessKeyId` char(20) character set ascii collate ascii_bin NOT NULL,
  `secret` char(40) character set ascii collate ascii_bin NOT NULL,
  PRIMARY KEY  (`accountID`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

-- 
-- Table structure for table `images`
-- 

CREATE TABLE `images` (
  `accountID` int(11) NOT NULL,
  `amazonId` varchar(15) NOT NULL,
  `label` varchar(60) default NULL,
  `descr` mediumtext,
  `attributes` set('windows','public','paid') default NULL,
  `arch` enum('i386','x86_64') NOT NULL default 'i386',
  `imageType` enum('kernel','ramdisk','machine') NOT NULL default 'machine',
  `kernelId` varchar(15) default NULL,
  `ramdiskId` varchar(15) default NULL,
  PRIMARY KEY  (`accountID`,`amazonId`),
  UNIQUE KEY `name` (`accountID`,`label`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

-- 
-- Table structure for table `kernels`
-- 

CREATE TABLE `kernels` (
  `amazonId` varchar(15) NOT NULL,
  `location` varchar(200) NOT NULL default 'none',
  `attributes` set('paid','amazon') default NULL,
  `arch` enum('i386','x86_64') NOT NULL default 'i386',
  `imageType` enum('kernel','ramdisk') NOT NULL default 'kernel',
  PRIMARY KEY  (`amazonId`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

-- 
-- Table structure for table `login`
-- 

CREATE TABLE `login` (
  `loginID` int(11) NOT NULL auto_increment,
  `name` varchar(20) NOT NULL,
  `pass` varchar(50) default NULL,
  `descr` varchar(200) default NULL,
  `email` varchar(100) default NULL,
  `accountID` int(11) NOT NULL,
  `createdBy` int(11) NOT NULL,
  `createdOn` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `lastPassword` datetime NOT NULL,
  `lastLogin` datetime NOT NULL,
  PRIMARY KEY  (`loginID`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

-- 
-- Table structure for table `session`
-- 

CREATE TABLE `session` (
  `sessionID` int(11) NOT NULL auto_increment,
  `loginID` int(11) NOT NULL,
  `source_ip` varchar(20) character set ascii collate ascii_bin NOT NULL,
  `lastAction` datetime NOT NULL,
  `lastHeartbeat` datetime NOT NULL,
  PRIMARY KEY  (`sessionID`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
