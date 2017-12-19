DROP TABLE IF EXISTS `poller_output_rt`;
CREATE TABLE IF NOT EXISTS poller_output_rt (
  local_data_id mediumint(8) unsigned NOT NULL default '0',
  rrd_name varchar(19) NOT NULL default '',
  `time` datetime NOT NULL default '0000-00-00 00:00:00',
  output text NOT NULL,
  poller_id int(11) NOT NULL,
  PRIMARY KEY  (local_data_id,rrd_name,`time`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

