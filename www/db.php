<?php
/*****************************************************************************
 *
 *  sQRtalk.org - database profile functions
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

require_once('config.php');
require_once('db.php');

$db_link = NULL;

function db_connect()
{
    global $db_link;

    /* don't reconnect */
    if($db_link)
	return $db_link;

    if(!($db_link = mysql_connect (DB_HOST, DB_USER, DB_PASSWORD)))
	return NULL;

    /* select database and return link handle */
    if(mysql_select_db (DB_DATABASE, $db_link))
	mysql_set_charset ('utf8', $db_link);
    else
    {
	/* close connection in case of errors */
	mysql_close ($db_link);
	$db_link = NULL;
    }

    return $db_link;
}

function db_close()
{
    global $db_link;

    if($db_link)
    {
	mysql_close($db_link);
	$db_link = NULL;
    }
}

function db_error()
{
    global $db_link;

    return $db_link ? mysql_error($db_link):FALSE;
}

function db_query($query)
{
    global $db_link;

    if(!$db_link)
	db_connect();

    if($db_link)
	return mysql_query($query, $db_link);
    else
	return FALSE;
}

function db_escape_string($string)
{
    global $db_link;

    if(!$db_link)
	db_connect();

    if($db_link)
	return mysql_real_escape_string ($string, $db_link);
    else
	return FALSE;
}

function user_dumpid_db()
{
    header('Content-Type: text/plain');

    if($res = db_query('SELECT uid FROM users'))
	while($profile = mysql_fetch_assoc($res))
	    printf("%03u\n",$profile['uid']);
}

/* dump database backup in text format */
function user_dump_db($format)
{
    $update = FALSE;

    $format=strtolower(trim($format));
    switch($format)
    {
	case 'csv':
	    break;

	case 'update':
	    $update = TRUE;
	    break;

	default:
	    return 'Unknown file format \''.$format.'\' for database dump';
    }

    if(!($fp = fopen('php://output', 'w')))
	return 'Can\'t open stdout';

    if($res = db_query('SELECT * FROM users'))
    {
	header('Content-Type: text/csv');
	header('Content-Disposition: attachment; filename="users.csv"');
	$header = FALSE;
	while($profile = mysql_fetch_assoc($res))
	{
	    $uid = $profile['uid'];

	    if(!$header)
	    {
		$header = array_keys($profile);
		$header[] = 'speaker_qrcode';
		$header[] = 'speaker_qrcode_url';
		$header[] = 'idcard_qrcode';
		$header[] = 'idcard_qrcode_url';
		fputcsv($fp, $header);
	    }

	    if($update)
		$values = user_profile_combined($profile);

	    /* assemble CSV line */
	    $values = array_values($profile);

	    $file = sprintf('T%03u.png',$uid);
	    $values[] = $file;
	    $values[] = QRCODE_URL.$file;

	    $file = sprintf('U%03u.png',$uid);
	    $values[] = $file;
	    $values[] = QRCODE_URL.$file;

	    fputcsv($fp, $values);
	}
    }

    fclose($fp);
    return FALSE;
}

function user_profile_db_internal($uid)
{
    if($res = db_query('SELECT * FROM users WHERE uid='.intval($uid)))
    {
	if(!($profile = mysql_fetch_assoc($res)))
	    return FALSE;

	foreach($profile as $key => &$value)
	    switch($key)
	    {
		case 'keywords':
		case 'discipline':
		    $value = explode(',',$value);
		    break;
	    }

	/* add custom fields */
	$profile['uid'] = $uid;
	$profile['user_profile_url'] = BERLINSYMPOSIUM_PROFILE_URL.$uid;

	/* sort profile array */
	ksort($profile);
	return $profile;
    }
    else
	return FALSE;
}

function user_profile_db($uid)
{
    $profile = user_profile_db_internal($uid);
    if(!$profile)
	return FALSE;

    /* throw out empty keys */
    foreach($profile as $key => $value)
	if(is_array($value))
	{
	    if(!count($value))
		unset($profile[$key]);
	}
	else
	    if(trim($value)=='')
		unset($profile[$key]);

    return $profile;
}
?>
