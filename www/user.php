<?php
/*****************************************************************************
 *
 *  sQRtalk.org - remote user profile fetching & DB based cache management
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

/* map remote CMS keys to local database columns */
$user_profile_remote2db = array(
    'biography' => 'biography',
    'firstname' => 'name_first',
    'institution' => 'institution',
    'institution_url' => 'institution_url',
    'jobtitle' => 'jobtitle',
    'phone' => 'user_phone',
    'photo' => 'user_photo',
    'profiletitle' => 'name_profile',
    'public_mailbox' => 'mail',
    'surname' => 'name_last',
    'topics' => 'keywords',
    'twitter_handle' => 'twitter',
    'username' => 'name_user',
    'discipline' => 'discipline',
    'photo_timestamp' => 'photo_timestamp',
    'photo_url' => 'photo_url',
    'website' => 'user_url',
);

function user_profile_callback($matches)
{
    $dec = hexdec($matches[1]);
    if($dec<ord(' '))
	return '';
    else
    {
	$chr = chr($dec);
	return $chr=='"'?'\"':$chr;
    }
}

/* read remote CMS profile & fix JSON format bugs and kill HTML formatting */
function user_profile_remote($uid)
{
    global $user_profile_remote2db;

    /* get user profile */
    $profile = file_get_contents(BERLINSYMPOSIUM_API_URL.$uid.BERLINSYMPOSIUM_API_TOKEN);
    /* fix JSON encoding bugs on berlinsyposium.org */
    $profile = str_replace('\\\'','\'',trim($profile));
    $profile = preg_replace_callback('/\\\\x([0-9A-F]{2})/i','user_profile_callback',$profile);
    /* replace HTML madness */
    $profile = str_replace('&nbsp;',' ',$profile);
    $profile = preg_replace('/<br[^>]*>/','\n',$profile);
    $profile = str_replace('\r','',$profile);
    $profile = strtr($profile, "\t",' ');
    $profile = preg_replace('/[ ]{2,}/',' ',$profile);
    $profile = preg_replace('/([ ]*\\\\[n]){2,}/','\n',$profile);
    $profile = preg_replace('/<[\/]*[a-z]+[^>]*>/i','',$profile);
    /* decode JSON */
    $json = json_decode($profile);

    if(($json->status==200 || $json->status==206) && isset($json->result) && isset($json->result->username))
    {
	$res = get_object_vars($json->result);

	foreach($res as $key => $value)
	    if($value=='')
		unset($res[$key]);
	    else
		if(is_string($value))
		    $value = trim($value);

	if(isset($res['taxonomy']))
	{
	    $topics = array();
	    $discipline = array();

	    foreach($res['taxonomy'] as $topic)
	    {
		$name = trim($topic->name);
		if($topic->vid == 7)
		    $discipline[] = $name;
		else
		    $topics[] = $name;
	    }
	    unset($res['taxonomy']);
	    if(count($topics))
		$res['topics'] = $topics;
	    if(count($discipline))
		$res['discipline'] = $discipline;
	}

	if(isset($res['biography']))
	    $res['biography'] = nl2br(htmlspecialchars(trim($res['biography'])),false);

	if(isset($res['profilephoto_url']))
	{
	    if(count($res['profilephoto_url'])>0 && isset($res['profilephoto_url'][0]->filepath))
	    {
		if(isset($res['profilephoto_url'][0]->timestamp))
		    $res['photo_timestamp'] = $res['profilephoto_url'][0]->timestamp;
		else
		    $res['photo_timestamp'] = time();

		$res['photo_url'] = BERLINSYMPOSIUM_PREFIX.'/'.$res['profilephoto_url'][0]->filepath;
	    }
	    unset($res['profilephoto_url']);
	}

	if(isset($res['twitter_handle']))
	    $res['twitter_handle'] = '@'.trim(ltrim($res['twitter_handle'],'@ '),' ');

	/* convert to array & sort */
	$res_array = array();
	foreach($res as $key => $value)
	    if(!is_object($value) && isset($user_profile_remote2db[$key]))
		$res_array[$user_profile_remote2db[$key]]=$value;

	/* add custom fields */
	$res_array['uid'] = $uid;
	$res_array['user_profile_url'] = BERLINSYMPOSIUM_PROFILE_URL.$uid;

	ksort($res_array);
	return $res_array;
    }
    else
	return NULL;
}

/* add protocol prefix to URL if its missing */
function user_fix_url(&$url)
{
    $url = trim($url);
    if(!preg_match('/^(http|https):\/\/.+$/i',$url))
	$url = 'http://'.$url;
    return $url;
}

/* get remote URL and combine with already cached content */
function user_profile_combined($uid,$debug)
{
    if(is_array($uid) && isset($uid['uid']))
    {
	$profile = $uid;
	$uid = $profile['uid'];
	$profile_db = array();
    }
    else
    {
	if(!$uid || !is_array($profile_db=user_profile_db_internal($uid)))
	    $profile_db = array();
	$profile = array();
    }

    if($uid && $profile_remote = user_profile_remote($uid))
    {
	/* array for all changes */
	$update = array();

	if(!isset($profile_remote['name_profile']))
	{
	    if(isset($profile_remote['name_first']))
		$name = trim($profile_remote['name_first']);
	    else
		$name = NULL;

	    if(isset($profile_remote['name_last']))
	    {
		$name_last = trim($profile_remote['name_first']);
		if($name)
		    $name.= ' '.$name_last;
		else
		    $name = $name_last;
	    }

	    if($name == '' && isset($profile_remote['name_user']))
		$name = $profile_remote['name_user'];

	    if($name != '')
		$profile_remote['name_profile'] = $name;
	}

	if(isset($profile_remote['institution_url']))
	    user_fix_url($profile_remote['institution_url']);

	if(isset($profile_remote['user_url']))
	    user_fix_url($profile_remote['user_url']);

	foreach($profile_remote as $key => $value)
	{
	    if(is_string($value))
		$value = trim($value);

	    if($value)
	    {
		$profile[$key] = $value;

		if(!isset($profile_db[$key]))
		    $update[$key] = $value;
		else
		    if($profile_db[$key]!=$value)
			$update[$key] = $value;
	    }
	}

	if(isset($profile_remote['user_url']))
	    $profile_remote['url'] = $profile_remote['user_url'];
	else
	    if(isset($profile_remote['user_profile_url']))
		$profile_remote['url'] = $profile_remote['user_profile_url'];

	/* check if caching is needed */
	if(count($update))
	{
	    $values = array();
	    foreach($update as $key => $value)
	    {
		if(is_array($value))
		    $value = implode(',',$value);
		$value = db_escape_string($value);
		if(!is_numeric($value))
		    $value='"'.$value.'"';
		$values[$key] = $value;
	    }
	    $values['last_updated'] = time();

	    $sql = 'INSERT INTO users ('.implode(',',array_keys($values)).') VALUES ('.implode(',',$values).') ON DUPLICATE KEY UPDATE ';
	
	    $first = TRUE;
	    foreach($values as $key => $value)
		if($key!='uid')
		{
		    if($first)
			$first = FALSE;
		    else
			$sql.=', ';

		    $sql.=$key.'='.$value;
		}
	    $sql.=';';

	    if(db_query($sql))
		$profile['cached'] = implode(',',array_keys($update));
	    else
		if($debug)
		    exit(mysql_error());
	}
    }

    if(count($profile))
    {
	ksort($profile);
	return $profile;
    }
    else
	return FALSE;
}

?>