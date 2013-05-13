-- **********************************************************
-- *                                                        *
-- * IMPORTANT NOTE                                         *
-- *                                                        *
-- * Do not import this file manually but use the TYPOlight *
-- * install tool to create and maintain database tables!   *
-- *                                                        *
-- **********************************************************

--
-- Table `tl_member`
--

CREATE TABLE `tl_member` (
  `harvest_membership` varchar(255) NOT NULL default '',
  `harvest_id` int(10) unsigned NOT NULL default '0',
  `harvest_client_id` int(10) unsigned NOT NULL default '0',
  `harvest_invoice` int(10) unsigned NOT NULL default '0',
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


-- --------------------------------------------------------

--
-- Table `tl_page`
--

CREATE TABLE `tl_page` (
  `harvest_memberships` blob NULL,
  `harvest_due` int(3) unsigned NOT NULL default '0',
  `harvest_category` varchar(32) NOT NULL default '',
  `harvest_format` varchar(255) NOT NULL default '',
  `harvest_notes` text NULL,
  `harvest_message` text NULL,
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
