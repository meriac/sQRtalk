<?php
/*****************************************************************************
 *
 *  sQRtalk.org - configuration settings
 *
 *  Copyright (C) 2011 Milosch Meriac <meriac@bitmanufaktur.de>
 *
 *****************************************************************************

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see http://www.gnu.org/licenses/agpl.txt

 */

/*  HTML page title suffix */
    define('SQRTALK_BANNER',' || sqrtalk.org - talk fair & square');

/*  maximum length for academic title - longer titles will be dropped */
    define('MAX_TITLE_LENGTH',10);

/*  database settings */
    define('DB_USER','sqrtalk');
    define('DB_PASSWORD','x5ewGv9dqjcD8XwL');
    define('DB_HOST','localhost');
    define('DB_DATABASE','sqrtalk');

/*  two-character conference namespace ID to ensure unique tag IDs per conference */
    define('CONFERENCE_ID','ig');

/*  prefix for QR code image autogeneration */
    define('QRCODE_URL','https://sqrtalk.com/qr/'.CONFERENCE_ID.'/');

/*  The application will fetch on demand an updated list of talks in iCAL format
    from this URL - the default URL is http://berlinsymposium.org/kalender/ical */
    define('CALENDAR_ICAL_URL','http://berlinsymposium.org/kalender/ical/');
    define('CALENDAR_FMT_TIME','H:i');

/*  HTML templates for talk and login page */
    define('TALK_TEMPLATE','../templates/talk.html');
    define('LOGIN_TEMPLATE','../templates/login.html');

/*  The moderator can increment the current speakers speech time in 1 second
    increments. The default setting is 5 minutes. After each click the speech
    time is rounded to the next increment. After three clicks the speech time
    wraps back to SPEAKER_TIME_MAX. */
    define('SPEAKER_TIME_INCREMENT',60*5);

/*  The default speech time in seconds per speaker */
    define('SPEAKER_TIME_MAX',60*3);

    define('GOOGLEDOC_PREFIX','https://docs.google.com/a/internetundgesellschaft.de/document/d/');
    define('GOOGLEDOC_POSTFIX','/edit?hl=en_US');
    define('BERLINSYMPOSIUM_DOMAIN','berlinsymposium.org');
    define('BERLINSYMPOSIUM_PREFIX','http://'.BERLINSYMPOSIUM_DOMAIN);
    define('BERLINSYMPOSIUM_PROFILE_URL',BERLINSYMPOSIUM_PREFIX.'/user/');
    define('BERLINSYMPOSIUM_RESEARCH_URL',BERLINSYMPOSIUM_PREFIX.'/research-question/');
    define('BERLINSYMPOSIUM_API_URL','https://'.BERLINSYMPOSIUM_DOMAIN.'/restful/profile/');
    define('BERLINSYMPOSIUM_API_TOKEN','/{enteryourtokenhere}');

/*  Please update the passwords for helpers and moderators here:
    - normal users have read-only access, no password needed
    - moderators are able to add speakers to panels via QR code scanning. By clicking
      on the user name set speakers active and deleting speakers from the queue.
    - helpers are able to add speakers to the queue of waiting speakers and deleting
      speakers from the queue. */
    $user_passwords = array (
	'helper' => 'SofaTrafo',
	'moderator' => 'AutoHund',
    );
?>
