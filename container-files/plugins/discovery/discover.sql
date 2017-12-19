

DROP TABLE IF EXISTS `plugin_discover_hosts`;
CREATE TABLE `plugin_discover_hosts` (
  `hostname` varchar(100) NOT NULL default '',
  `ip` varchar(17) NOT NULL default '',
  `hash` varchar(12) NOT NULL default '',
  `community` varchar(100) NOT NULL default '',
  `sysName` varchar(100) NOT NULL default '',
  `sysLocation` varchar(255) NOT NULL default '',
  `sysContact` varchar(255) NOT NULL default '',
  `sysDescr` varchar(255) NOT NULL default '',
  `sysUptime` int(32) NOT NULL default '0',
  `os` varchar(64) NOT NULL default '',
  `snmp` tinyint(4) NOT NULL default '0',
  `known` tinyint(4) NOT NULL default '0',
  `up` tinyint(4) NOT NULL default '0',
  `time` int(11) NOT NULL default '0',
  PRIMARY KEY  (`ip`)
) ENGINE=MyISAM;


-- 
-- Table structure for table `plugin_discover_template`
-- 

DROP TABLE IF EXISTS `plugin_discover_template`;
CREATE TABLE `plugin_discover_template` (
  `id` int(8) NOT NULL auto_increment,
  `host_template` int(8) NOT NULL default '0',
  `tree` int(12) NOT NULL default '0',
  `snmp_version` tinyint(3) NOT NULL default '1',
  `sysdescr` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM;

