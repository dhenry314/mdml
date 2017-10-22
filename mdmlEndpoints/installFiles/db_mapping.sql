--
-- Table structure for table `resources`
--

CREATE TABLE `resources` (
  `path` varchar(767) NOT NULL,
  `ID` int(11) UNSIGNED NOT NULL auto_increment,
  `change` varchar(20) NOT NULL,
  `lastmod` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `originURI` varchar(767) NOT NULL,
  `sourceURI` varchar(767) NOT NULL,
  `hash` varchar(767) NOT NULL,
  PRIMARY KEY (`path`,`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Table structure for table `logging`
--

CREATE TABLE `logging` (
  `ID` int(11) UNSIGNED NOT NULL auto_increment,
  `logged` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tag` varchar(767) NOT NULL,
  `originURI` varchar(767) NOT NULL,
  `sourceURI` varchar(767) NOT NULL,
  `LogLevel` enum('INFO','WARNING','ERROR','') NOT NULL DEFAULT 'INFO',
  `Message` varchar(766) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;



