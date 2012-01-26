<?php
/*****************************************************************************
 *
 *  sQRtalk.org - talk specific helper functions & talk HTML rendering
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

define('LOGIN_MSG','Logged in as ');
require_once('config.php');
require_once('xajax/xajax.inc.php');

$talk_render_debug = FALSE;

/* substitutions for AJAX call */
$talk_render_substitution = array(
    'current_speaker' => 'name_profile',
    'spk_institution' => 'institution',
    'spk_email' => 'mail',
    'spk_twitter' => 'twitter',
    'spk_keywords' => 'keywords',
    'spk_topic' => 'discipline'
);

/* hashvalue key substitution code for HTML talk template */
function talk_substitute_key($key, $talk_render_profile)
{
    global $talk_render_debug;
    global $talk_render_substitution;

    $key = strtolower($key);
    if(isset($talk_render_profile[$key]))
	return $talk_render_profile[$key];
    else
    {
	if(isset($talk_render_substitution[$key]))
	{
	    $key = $talk_render_substitution[$key];
	    if(isset($talk_render_profile[$key]))
	    {
		if(is_array($value = $talk_render_profile[$key]))
		    $value = implode(', ',$value);

		switch($key)
		{
		    case 'name_profile':
		    case 'current_speaker':
			$value =  '<a href="'.BERLINSYMPOSIUM_PROFILE_URL.$talk_render_profile['uid'].'">'.$value.'</a>';
			break;

		    case 'mail':
			$value = "<a href=\"mailto:$value?subject=[Workshop]\">$value</a>";
			break;

		    case 'twitter':
			$value = trim($value,'@ ');
			if($value)
			    $value = "<a href=\"http://twitter.com/$value\">@$value</a>";
			break;

		    case 'institution':
			if(isset($talk_render_profile['institution_url']))
			    $value = "<a href=\"$talk_render_profile[institution_url]\">$value</a>";

		    case 'keywords':
		    case 'discipline':
			$value=str_replace(',',', ',$value);
		}

		return $value;
	    }
	    else
		return '';
	}
	else
	    return $talk_render_debug?'['.$key.']':'';
    }
}

/* verify current login user role */
function talk_checkrole($role = TRUE)
{
    /* only verfiy if a session is on */
    if($role === TRUE)
	return isset($_SESSION['USER_ROLE']);

    $session_role = isset($_SESSION['USER_ROLE']) ? $_SESSION['USER_ROLE'] : FALSE;
    if($role == $session_role)
	return TRUE;
    else
	if($role == 'user')
	    return TRUE;
	else
	    return FALSE;
}

/* callback function for substituting hash-values in talk HTML template */
function talk_render_callback($match)
{
    global $talk_render_profile;

    return talk_substitute_key($match[2],$talk_render_profile);
}

/* collect talk specific information into assiciative array object */
function talk_info($talk_id, $last_updated=0)
{
    $talk_id = intval($talk_id);

    if(!($talk_id && ($res = db_query("SELECT * FROM talks WHERE uid=$talk_id LIMIT 0,1"))))
	return FALSE;

    $talk = mysql_fetch_assoc($res);
    if(!is_array($talk))
	return FALSE;

    if($last_updated && ($talk['last_updated']==$last_updated))
	return FALSE;

    $sql = "SELECT * FROM users WHERE talk_id=$talk_id";
    if($userid_current = $talk['userid_current'])
	$sql.=" OR uid=$userid_current";
    if($userid_prev = $talk['userid_prev'])
	$sql.=" OR uid=$userid_prev";
    $sql.= ' ORDER BY talk_check_in ASC';


    $queue = array();
    if($res = db_query($sql))
	while($user = mysql_fetch_assoc($res))
	    switch($uid=$user['uid'])
	    {
		case $userid_current:
		    $talk['user_current']=$user;
		    break;
		case $userid_prev:
		    $talk['user_prev']=$user;
		    break;
		default:
		    $queue[$uid]=$user;
	    }
    $talk['queue'] = $queue;

    unset($talk['userid_current']);
    unset($talk['userid_prev']);
    return $talk;
}

/* handle talk time extension by clicking on progress bar (AJAX call) */
function talk_extend()
{
    /* get talk id from session */
    if(!(isset($_SESSION['TALK_ID']) && (($talk_id=intval($_SESSION['TALK_ID']))>0)))
	return NULL;

    /* only for moderators */
    if(isset($_SESSION['USER_ROLE']) && $_SESSION['USER_ROLE']=='moderator')
    {
	if($talk = talk_info($talk_id))
	{
	    /* does the talk bar need to be updated ? */
	    if(($delta = $talk['speaker_time']-time())<=(SPEAKER_TIME_INCREMENT-10))
		$delta = SPEAKER_TIME_INCREMENT;
	    else
		$delta = (ceil(($delta + SPEAKER_TIME_INCREMENT-1) / SPEAKER_TIME_INCREMENT)%4)*SPEAKER_TIME_INCREMENT;

	    if(!$delta)
		$delta = SPEAKER_TIME_MAX;

	    if(db_query("UPDATE talks SET speaker_time_max=$delta, speaker_time=".(time()+$delta+1).', last_updated='.time().' WHERE uid='.intval($talk_id)))
	    {
		$res = new xajaxResponse();
		$res->script("sqrtalk_bar($delta,$delta);");
		return $res;
	    }
	}
    }
    return NULL;
}

/* handle clicks in talk page (AJAX call) */
function talk_click($object_id)
{
    if(!(talk_checkrole() && isset($_SESSION['TALK_ID']) && ($talk_id=$_SESSION['TALK_ID'])))
	return NULL;

    $res = NULL;

    if(preg_match('/(?<cmd>[A-Z])(?<uid>[0-9]+)/',$object_id,$matches))
    {
	$now = time();
	$user_id = intval($matches['uid']);

	$cmd = $matches['cmd'];

	/* both when moving a queued user to the speaker screen or deleting the user, that user needs to be deleted from the queue */
	if($cmd == 'A' || $cmd == 'R')
	{
	    if(!db_query("UPDATE users SET talk_id=0, talk_check_in=0, last_updated=$now WHERE uid=$user_id AND talk_id=$talk_id"))
	    {
		$res = new xajaxResponse();
		$res->alert(db_error());
		return $res;
	    }
	}

	switch($cmd)
	{
	    /* add id as speaker */
	    case 'P':
	    case 'A':
		/* rotate the current speaker out to 'prev' */
		db_query(
		    'UPDATE talks SET'.
		    '  userid_prev=userid_current,'.
		    '  userid_current='.intval($user_id).','.
		    '  speaker_time_max='.SPEAKER_TIME_MAX.','.
		    '  speaker_time='.($now+SPEAKER_TIME_MAX).','.
		    '  last_updated='.$now.' WHERE uid='.intval($talk_id));
		break;

	    /* after removing the speaker from the list, force update for all users */
	    case 'R':
		db_query("UPDATE talks SET last_updated=$now WHERE uid=$talk_id");
		break;
	}
	/* finally force talk page update for all users */
	$res = talk_refresh($talk_id);
    }
    return $res;
}

/* build clickable speaker queue HTML code for (AJAX call) */
function talk_format_queue_speaker($user,$verbose,$valid_user)
{
    if($verbose)
	$name = $user['name_profile'];
    else
	if(isset($user['name_last']) && ($name = $user['name_last']))
	{
	    if(isset($user['name_title']) && (strlen($user['name_title'])<=MAX_TITLE_LENGTH))
		$name = $user['name_title'].' '.$name;
	}
	else
	    $name = $user['name_profile'];

    /* build clickable content */
    $id = ($user['talk_panel']?'P':'A').$user['uid'];
    if($valid_user)
    {
	$name = htmlspecialchars($name);
	if(talk_checkrole('moderator'))
	    $name = "<a name=\"#$id\" href=\"#$id\" onClick=\"xajax_talk_click('$id')\">$name</a>";
    }
    else
	$name = '<a href="'.BERLINSYMPOSIUM_PROFILE_URL.$user['uid'].'" title="go to user profile" target="_blank">'.htmlspecialchars($name).'</a>';

    /* encapsulate in CSS span */
    $id = 'R'.$user['uid'];
    $name = "<span id=\"$id\" class=\"include_more\">$name";
    if($valid_user)
	$name.="&nbsp;<a class=\"remove\" name=\"$id\" href=\"#$id\" onClick=\"xajax_talk_click('$id')\" title=\"remove user from list\">[X]</a>";
    $name.= '</span>';
    return $name;
}

/* update talk information (AJAX call) */
function talk_refresh($talk_id, $last_updated=0,$res=NULL)
{
    global $talk_render_substitution;

    /* sanitize $talk_id and $last_udpdated */
    if($talk_id)
    {
	$talk_id = intval($talk_id);
	if(!isset($_SESSION['TALK_ID']))
	    $_SESSION['TALK_ID'] = $talk_id;
    }
    else
	if(isset($_SESSION['TALK_ID']))
	    $talk_id = $_SESSION['TALK_ID'];
    $last_updated = intval($last_updated);

    if($talk = talk_info($talk_id, $last_updated))
    {
	$valid_user = talk_checkrole();

	/* remember modification time */
	if(!$res)
	    $res = new xajaxResponse();

	if(isset($talk['user_prev']))
	    $content = talk_format_queue_speaker($talk['user_prev'],TRUE,$valid_user);
	else
	    $content = '';
	$res->assign('spk_previous','innerHTML',$content);

	if(isset($talk['user_current']))
	{
	    $user = $talk['user_current'];
	    foreach($talk_render_substitution as $key => $value)
	    {
		$content = talk_substitute_key($key,$user);
		$res->assign($key,'innerHTML',$content);
	    }
	}

	/* does the talk bar need to be updated ? */
	$delta = $talk['speaker_time']-time();
	$bar = ($delta>=0)?"sqrtalk_bar($delta,$talk[speaker_time_max]);":'';
	$res->script("sqrtalk.last_updated=$talk[last_updated];".$bar);

	/* update speaker queue */
	$spk = array(
	    'spk_panel' => '',
	    'spk_next'  => '',
	    'spk_later' => ''
	);

	$first = TRUE;
	foreach($talk['queue'] as $user)
	{
	    if($user['talk_panel'])
		$spk['spk_panel'].=talk_format_queue_speaker($user,FALSE,$valid_user);
	    else
		if($first)
		{
		    $first=FALSE;
		    $spk['spk_next']=talk_format_queue_speaker($user,TRUE,$valid_user);
		}
		else
		    $spk['spk_later'].=talk_format_queue_speaker($user,FALSE,$valid_user);
	}
	
	foreach($spk as $key => $value)
	    $res->assign($key,'innerHTML',$value);

	return $res;
    }
    return NULL;
}

/* handle talk login (AJAX call) */
function talk_login($user,$password)
{
    global $user_passwords;

    $res = new xajaxResponse();

    $user = strtolower($user);
    if($login = (isset($user_passwords[$user]) && ($user_passwords[$user] == $password)))
    {
	$_SESSION['USER_ROLE'] = $user;
	$res->assign('main','style.display','block');
	$res->assign('login_form','style.display','none');
	switch($user)
	{
	    case 'moderator':
		break;
	    case 'helper':
		break;
	}
    }
    else
	if(isset($_SESSION['USER_ROLE']))
	    unset($_SESSION['USER_ROLE']);

    talk_refresh(0, 0, $res);
    $res->assign('login_pwd','value','');
    $res->assign('debug','innerHTML',$login?LOGIN_MSG.' <b><font color="red">'.ucfirst($user).'</font></b>':'<font color="red">Invalid username or password entered - <b>please retry</b>.</font>');
    return $res;
}

/* render talk page in HTML format based on conference ID and talk ID */
function talk_render($uid, $conference, $talk_id, $debug)
{
    global $talk_render_profile;
    global $talk_render_debug;

    /* check if moderator */
    $role = isset($_SESSION['USER_ROLE']) ? $_SESSION['USER_ROLE'] : FALSE;

    /* XAJAX handling */
    if($debug)
	$xajax = NULL;
    else
    {
	$xajax = new xajax();
	$xajax->configure('javascript URI','/xajax/');
	if(!$role)
	    $xajax->configure('waitCursor',false);
	$xajax->register(XAJAX_FUNCTION, 'talk_refresh');
	$xajax->register(XAJAX_FUNCTION, 'talk_extend');
	$xajax->register(XAJAX_FUNCTION, 'talk_click');
	$xajax->register(XAJAX_FUNCTION, 'talk_login');
	$xajax->processRequest();
    }

    if($conference!=CONFERENCE_ID)
	return FALSE;

    /* make debug state globally available */
    $talk_render_debug = $debug;

    /* get talk information from database */
    if(!($talk = talk_info($talk_id)))
	return FALSE;

    $current_url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    $workshop = date('l, jS',$talk['dtstart']).'&nbsp;&nbsp;'.date(CALENDAR_FMT_TIME,$talk['dtstart']).'-'.date(CALENDAR_FMT_TIME,$talk['dtend']).' | <b>'.$current_url.'</b>';

    if($debug)
    {
	$profile = user_profile_db ($uid);
	if($profile && count($profile))
	    header('Content-Type: text/html; charset=UTF-8');
    }
    else
	$profile = array();

    if(!($template = @file_get_contents(TALK_TEMPLATE)))
	return 'Can\'t open template file \''.TALK_TEMPLATE.'\'';

    /* store profile globally to make it
       accessible to 'talk_render_callback' */
    $talk_render_profile = $profile;
    $talk_render_profile['counter'] = '00:00';
    $talk_render_profile['button'] = '<a href="/">Change Talk</a>';
    $talk_render_profile['login'] = '<a name="user" href="#user" onclick="sqrtalk_show(1,\''.($role?$role:'').'\');">Administration</a>';
    $talk_render_profile['debug'] = $debug?'<pre class="debug">'.print_r($profile,true).print_r($_SESSION,true).print_r($talk,true).'</pre>':'';
    $talk_render_profile['javascript'] = $debug?'':"\n\n<script type=\"text/javascript\" src=\"/js/sqrtalk.js\" charset=\"UTF-8\"></script>".$xajax->getJavascript();
    $talk_render_profile['qrcode'] = "/qr/$conference/$talk_id";
    $talk_render_profile['qrurl'] = $talk['url'];
    $talk_render_profile['logo_url'] = $talk['url'];
    $talk_render_profile['workshop'] = $workshop;
    $talk_render_profile['bodytags'] = $debug?'':' onload="sqrtalk_onload('.$talk_id.')"';
    $talk_render_profile['footer_url'] = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    $talk_render_profile['bar_extend'] = ($debug||!talk_checkrole('moderator'))?'':' onclick="xajax_talk_extend();" style="cursor: pointer;"';
    $talk_render_profile['title'] = 'I&S Talk "'.$talk['summary'].SQRTALK_BANNER;
    $talk_render_profile['debug'] = '<span id="debug" class="small">'.(isset($_SESSION['USER_ROLE']) ? LOGIN_MSG.'<b>'.ucfirst($_SESSION['USER_ROLE']).'</b>.':'').'</span>';

    if(isset($talk['gdocid']))
	$talk_render_profile['docs_url'] = GOOGLEDOC_PREFIX.$talk['gdocid'].GOOGLEDOC_POSTFIX;

    if($debug && isset($profile['name_profile']))
    {
	$name_profile = $profile['name_profile'];
	$talk_render_profile['title'] = $name_profile;
	if(isset($profile['user_url']))
	    $url = $profile['user_url'];
	else
	    $url = $uid ? BERLINSYMPOSIUM_PROFILE_URL.$uid : FALSE;
	$talk_render_profile['current_speaker_wrap'] = $url ?"<a href=\"$url\">$name_profile</a>":$name_profile;
    }
    else
	$talk_render_profile['current_speaker_wrap'] = 'WELCOME';

    if(isset($talk['summary']))
	$talk_render_profile['workshop_verbose'] = $talk['summary'];

    $talk = preg_replace_callback('/(###([A-Z0-9_]+)###)/i','talk_render_callback',$template);
    unset($talk_render_profile);

    return $talk;
}
?>
