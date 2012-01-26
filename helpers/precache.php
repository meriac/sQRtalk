#!/usr/bin/php
<?php
/*****************************************************************************
 *
 *  sQRtalk.org - pre-cache user profiles into DB to decouple pages access
 *                from CMS performance. You need to run this script from a
 *                cron-job to update the DB regularly
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

require_once('../www/config.php');
require_once('../www/db.php');
require_once('../www/user.php');

if(!($res=db_query('SELECT MAX(uid) as max_uid FROM users')))
    exit('Can\'t select from database');

if(!($row = mysql_fetch_assoc($res)))
    exit('Can\'t read from database');

$max_uid = $row['max_uid']+300;

for($uid=0;$uid<=$max_uid;$uid++)
{
    echo 'Reading ID'.$uid.' ['.round(100*($uid/$max_uid))."%]";

    $profile = user_profile_combined($uid,TRUE);

    if(isset($profile['name_profile']))
	echo " Found user '$profile[name_profile]'";
    if(isset($profile['cached']))
	echo " --- Cached $profile[cached]\n";
    else
	echo "\n";
}

?>