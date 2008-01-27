-- phpMyAdmin SQL Dump
-- version 2.10.0.2
-- http://www.phpmyadmin.net
-- 
-- Host: localhost
-- Generation Time: Jan 27, 2008 at 03:52 PM
-- Server version: 5.0.27
-- PHP Version: 4.3.11RC1-dev

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
  `accessKeyId` char(20) character set ascii collate ascii_bin NOT NULL,
  `secret` char(40) character set ascii collate ascii_bin NOT NULL,
  PRIMARY KEY  (`accountID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- 
-- Dumping data for table `account`
-- 


-- --------------------------------------------------------

-- 
-- Table structure for table `images`
-- 

CREATE TABLE `images` (
  `accountID` int(11) NOT NULL,
  `amazonId` varchar(15) NOT NULL,
  `name` varchar(60) NOT NULL,
  `descr` mediumtext,
  PRIMARY KEY  (`accountID`,`amazonId`),
  UNIQUE KEY `name` (`accountID`,`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- 
-- Dumping data for table `images`
-- 


-- --------------------------------------------------------

-- 
-- Table structure for table `login`
-- 

CREATE TABLE `login` (
  `loginID` int(11) NOT NULL auto_increment,
  `name` varchar(20) NOT NULL,
  `pass` varchar(20) default NULL,
  `accountID` int(11) NOT NULL,
  `createdBy` int(11) NOT NULL,
  `createdOn` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `lastPassword` datetime NOT NULL,
  `lastLogin` datetime NOT NULL,
  PRIMARY KEY  (`loginID`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- 
-- Dumping data for table `login`
-- 


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
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- 
-- Dumping data for table `session`
-- 

