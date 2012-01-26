SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `sqrtalk`
--

-- --------------------------------------------------------

--
-- Structure for table `talks`
--

CREATE TABLE IF NOT EXISTS `talks` (
  `uid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gdocid` text COLLATE utf8_unicode_ci NOT NULL,
  `last_updated` int(10) unsigned NOT NULL,
  `userid_current` int(10) unsigned NOT NULL,
  `userid_prev` int(10) unsigned NOT NULL,
  `speaker_time` int(10) unsigned NOT NULL,
  `speaker_time_max` int(10) unsigned NOT NULL,
  `summary` text COLLATE utf8_unicode_ci NOT NULL,
  `dtstart` int(10) unsigned NOT NULL,
  `dtend` int(10) unsigned NOT NULL,
  `url` text COLLATE utf8_unicode_ci NOT NULL,
  `visible` int(10) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`uid`),
  KEY `time` (`dtstart`,`dtend`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=488 ;

-- --------------------------------------------------------

--
-- Structure for table  `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `uid` int(11) unsigned NOT NULL,
  `talk_id` int(10) unsigned NOT NULL,
  `talk_check_in` int(10) unsigned NOT NULL,
  `talk_panel` int(10) unsigned NOT NULL,
  `last_updated` int(10) unsigned NOT NULL,
  `name_user` text COLLATE utf8_unicode_ci NOT NULL,
  `mail` text COLLATE utf8_unicode_ci NOT NULL,
  `name_profile` text COLLATE utf8_unicode_ci NOT NULL,
  `name_title` text COLLATE utf8_unicode_ci NOT NULL,
  `name_first` text COLLATE utf8_unicode_ci NOT NULL,
  `name_last` text COLLATE utf8_unicode_ci NOT NULL,
  `jobtitle` text COLLATE utf8_unicode_ci NOT NULL,
  `twitter` text COLLATE utf8_unicode_ci NOT NULL,
  `institution` text COLLATE utf8_unicode_ci NOT NULL,
  `institution_url` text COLLATE utf8_unicode_ci NOT NULL,
  `user_url` text COLLATE utf8_unicode_ci NOT NULL,
  `user_phone` text COLLATE utf8_unicode_ci NOT NULL,
  `user_profile_url` text COLLATE utf8_unicode_ci NOT NULL,
  `keywords` text COLLATE utf8_unicode_ci NOT NULL,
  `discipline` text COLLATE utf8_unicode_ci NOT NULL,
  `biography` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `photo_timestamp` int(10) unsigned NOT NULL,
  `photo_url` text COLLATE utf8_unicode_ci NOT NULL,
  UNIQUE KEY `uid` (`uid`),
  KEY `users_per_talk` (`talk_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
