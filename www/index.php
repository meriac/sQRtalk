<?php
/*****************************************************************************
 *
 *  sQRtalk.org - main entry
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
    require_once('user.php');
    require_once('db.php');
    require_once('talk.php');
    require_once('calendar.php');

    /* disable caching */
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 23:42:23 GMT');

    /* start cookie based session management */
    session_start();
    $_SESSION['REMOTE_HOST'] = "$_SERVER[REMOTE_ADDR]:$_SERVER[REMOTE_PORT]";
    $_SESSION['LAST_SEEN'] = time();
    $_SESSION['LAST_URL'] = $_SERVER['REQUEST_URI'];


    if(preg_match('/^\/(?P<conference>[a-z]+)\/(?P<talk_id>[0-9]+)/i',$_SERVER['REQUEST_URI'],$matches))
    {
	$talk_id = intval($matches['talk_id']);
	$conference = strtolower($matches['conference']);
	$uid = isset($_SESSION['USER_ID'])?$_SESSION['USER_ID']:0;

	if(($talk = talk_render($uid, $conference, $talk_id, FALSE))===FALSE)
	{
	    unset($_SESSION['TALK_ID']);
	    header('Location: /');
	}
	else
	{
	    /* store selected conference session and ID */
	    $_SESSION['CONFERENCE'] = $conference;
	    $_SESSION['TALK_ID'] = $talk_id;
	    echo $talk;
	}
    }
    else if(preg_match('/^\/(?P<conference>[a-z]+)\/(?P<mode>[a-z])(?P<uid>[0-9]+)(|\.(?P<format>[a-z]+))$/i',$_SERVER['REQUEST_URI'],$matches))
    {
	$uid = intval($matches['uid']);

	/* map anonymous profile 999 to actual profile in database */
	if($uid==999)
	    $uid=601;

	$format = isset($matches['format'])?strtolower($matches['format']):NULL;
	$conference = strtolower($matches['conference']);

	if($conference!=CONFERENCE_ID)
	    header ('Location: /');
	else
	    switch($matches['mode'])
	    {
	    case 'R':
		$head=NULL;
		if (($handle = fopen("../data/research.csv", "r")) != FALSE)
		    while (($col = fgetcsv($handle, 100000, ",")) !== FALSE)
		    {
			if(!$head)
			    $head = $col;
			else
			{
			    $uid_data = intval($col[0]);
			    if($uid == $uid_data)
			    {
				$profile = array();
				foreach($head as $id => $field)
				{
				    $value = trim($col[$id],"\n\r\"\t");
				    if($value!='NULL')
					$profile[trim($head[$id],"\n\r\"")] = $value;
				}
				header ('Location: '.BERLINSYMPOSIUM_RESEARCH_URL.$profile['QuestionID']);
				break;
			    }
			}
		    }
		break;
	    case 'Q':
	    case 'U':
	    case 'T':
		switch($format)
		{
		    case '':
			if(isset($_SESSION['USER_ROLE']))
			{
			    /* remember conference-ID and user-ID ... */
			    $_SESSION['CONFERENCE'] = $conference;
			    $_SESSION['USER_ID'] = $uid;

			    /* get talk id from session */
			    $talk_id = isset($_SESSION['TALK_ID'])?intval($_SESSION['TALK_ID']):0;

			    if($talk_id)
			    {
				/* cache that person */
				if(user_profile_db ($uid))
				{
				    $now = time();
				    $moderator = ($_SESSION['USER_ROLE'] == 'moderator')?1:0;
				    db_query(
					"UPDATE users".
					"  SET talk_id=IF(talk_id=$talk_id,0,$talk_id),".
					"  talk_panel=$moderator,".
					"  last_updated=$now,".
					"  talk_check_in=last_updated".
					"  WHERE uid=$uid");
				    db_query(
					"UPDATE talks".
					"  SET last_updated=$now ".
					"  WHERE uid=$talk_id");
				}
				/* and redirect to to-level of our conference */
				header('Location: /'.$conference.'/'.$talk_id);
				exit();
			    }
			    else
				header('Location: /');
			}
			else
			    header ('Location: '.BERLINSYMPOSIUM_PROFILE_URL.$uid);
			exit();
			break;

		    /* display live profile from remote CMS */
		    case 'remote':
			header('Content-Type: text/plain; charset=UTF-8');
			print_r(user_profile_remote ($uid));
			break;

		    /* display local cached profile */
		    case 'local':
			header('Content-Type: text/plain; charset=UTF-8');
			print_r(user_profile_db ($uid));
			break;

		    /* display local cached profile and add remote updates */
		    case 'combined':
		    case 'txt':
			header('Content-Type: text/plain; charset=UTF-8');
			print_r(user_profile_combined ($uid, TRUE));
			break;

		    /* local cached profile and add remote updates - output as JSON */
		    case 'json':
			header('Content-Type: text/plain; charset=UTF-8');
			echo json_encode(user_profile_combined ($uid, FALSE));
			break;

		    /* show in HTML format by default */
		    case 'html':
		    default:
			/* remember conference-ID and user-ID ... */
			$_SESSION['CONFERENCE'] = $conference;
			$_SESSION['USER_ID'] = $uid;

			/* get talk id from session */
			$talk_id = isset($_SESSION['TALK_ID'])?$_SESSION['TALK_ID']:'';

			if(($talk = talk_render($uid, $conference, $talk_id, TRUE))===FALSE)
			    echo 'unknown talk';
			else
			    echo $talk;
		}
		break;
	    }
    }
    else
    {
	$cmd = trim($_SERVER['REQUEST_URI'],'/');

	/* handle administration area */
	if(preg_match('/^admin\/(?<module>[a-z_]+)(\.(?<function>[a-z0-9]+)|)$/',$cmd,$matches))
	{
	    $res = FALSE;
	    $module = $matches['module'];
	    $function = (isset($matches['function']))?$matches['function']:FALSE;

	    /* kick out non-authorized users */
	    if(!isset($_SESSION['USER_ROLE']))
	    {
		header ('Location: /');
		exit;
	    }

	    /* handle admin module function URLs */
	    switch($module)
	    {
		/* reset session */
		case 'logout':
		    /* reset user role */
		    if(isset($_SESSION['USER_ROLE']))
			unset($_SESSION['USER_ROLE']);

		    /* possibility to redirect to current talk with /admin/logout.123 */
		    $target = '/';
		    if(($talk_id = intval($function))>0)
			$target.='ig/'.$talk_id;

		    header('Location: '.$target);
		    exit();
		    break;

		/* dump user IDs */
		case 'userids':
		    $res = user_dumpid_db($function);
		    break;

		/* dump users */
		case 'users':
		    $res = user_dump_db($function);
		    break;

		/* calendar handling */
		case 'calendar':
		    switch($function)
		    {
			/* fetch latest calendar from remote CMS */
			case 'update':
			    calendar_update(CONFERENCE_ID);
			    break;

			/* show live calendar from remote URL */
			case 'show':
			    calendar_show(CONFERENCE_ID);
			    break;

			default:
			    $res = 'unknown calendar command';
		    }
		    break;

		default:
		    $res='unknown admin command';
		    break;
	    }

	    /* switch to plain text for all valid responses */
	    if($res)
	    {
		header('Content-Type: text/plain; charset=UTF-8');
		echo $res;
	    }
	}
	else
	    calendar_show(CONFERENCE_ID);
    }

    exit;
?>
