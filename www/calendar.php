<?php
/*****************************************************************************
 *
 *  sQRtalk.org - remote calendar caching and displaying functions
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

/* fetch remote calendar and return value array */
function calendar_fetch_remote($url)
{
    $res = FALSE;
    $calendar = FALSE;
    $event = FALSE;

    if($lines = @file($url))
	foreach($lines as $line)
	{
	    $line = trim($line);
	    if(preg_match('/^(?<key>[A-Z]+)(:|;VALUE=URI:)(?<value>.+)$/',$line,$matches))
	    {
		$key = strtolower($matches['key']);
		$value = trim($matches['value']);

		switch($key)
		{
		    case 'begin':
			switch($value)
			{
			    case 'VCALENDAR':
				$calendar = array();
				break;

			    case 'VEVENT':
				if($calendar!==FALSE)
				    $event = array();
				break;
			}
			break;

		    case 'end':
			switch($value)
			{
			    case 'VCALENDAR':
				$res = $calendar;
				$calendar = FALSE;
				break;

			    case 'VEVENT':
				if($event!==FALSE)
				{
				    /* make sure no iCAL fields are missing */
				    if(is_array($event) && (count($event)==5))
					$calendar[] = $event;
				    $event = FALSE;
				}
				break;
			}
			break;

		    case 'uid':
			if( ($event!==FALSE) && (preg_match('/^calendar\.(?<uid>[0-9]+)\.field_datetime[0-9.]+$/',$value,$matches)) )
			    $event[$key] = intval($matches['uid']);
			break;

		    case 'dtstart':
		    case 'dtend':
			if($time = @strtotime($value))
			    $event[$key] = $time;
			break;

		    case 'summary':
			if($event!==FALSE)
			{
			    $value = str_replace('\,',',',$value);
			    $value = str_replace(array('//','\n'),'',$value);
			    $value = preg_replace('/\([^)]*\)/','',$value);
			    $value = preg_replace('/[ ]{2,}/',' ',$value);
			    $event[$key] = trim($value);
			}
			break;

		    case 'url':
			if($event!==FALSE)
			    $event[$key] = $value;
			break;
		}
	    }
	}

    return $res;
}

/* fetch remote calendar and store into DB */
function calendar_update_db()
{
    header('Content-Type: text/plain; charset=UTF-8');

    if(!($calendar = calendar_fetch_remote(CALENDAR_ICAL_URL)))
    {
	echo 'Can\'t fetch/parse calendar from '.CALENDAR_ICAL_URL;
	return FALSE;
    }

    foreach($calendar as $talk)
    {
	$names = implode(',',array_keys($talk));
	$duplicates = array();

	foreach($talk as $key => &$value)
	{
	    $value = db_escape_string ($value);
	    if(!is_numeric($value))
		$value = '"'.$value.'"';
	    if($key!='uid')
		$duplicates[]=$key.'='.$value;
	}
	$values = implode(',',$talk);
	$duplicates = implode(',',$duplicates);

	$sql='INSERT INTO talks ('.$names.') VALUES ('.$values.') ON DUPLICATE KEY UPDATE '.$duplicates;
	if(!db_query($sql))
	    echo 'ERROR: '.db_error().' => "'.$sql."\"\n";
    }

    return $calendar;
}

/* display calendar */
function calendar_show_array($conference, $calendar)
{
    header('Content-Type: text/html; charset=UTF-8');

    echo "<html><head><title>I&S Workshops ".SQRTALK_BANNER."</title><link rel=\"stylesheet\" type=\"text/css\" href=\"/css/talk.css\"></head><body>"
        ."<span id=\"calendar-title\" class=\"big\">1st Berlin Symposium on Internet and Society</span>\n"
        ."<table width=\"800\" border=\"0\" class=\"calendar-list\">\n";

    $lastday = NULL;
    foreach($calendar as $talk)
    {
	$day = date('l, jS',$talk['dtstart']);
	if($day == $lastday)
	    $day = '';
	else
	{
	    $counter = 1;
	    echo "\t<tr><td colspan=\"6\">&nbsp;</td></tr>\n";
	    echo "\t<tr><td class=\"big\" colspan=\"5\">$day</td></tr>\n";
	    $lastday = $day;
	}

	echo "\t<tr>\n"
	    ."\t\t<td>$counter.</td>\n"
	    ."\t\t<td><a href=\"/$conference/$talk[uid]\">".htmlspecialchars($talk['summary'])."</a></td>\n"
	    ."\t\t<td>".($talk['gdocid']?"<a href=\"".GOOGLEDOC_PREFIX.$talk['gdocid'].GOOGLEDOC_POSTFIX."\"><img title=\"Google Docs - Talk Protocol\" src=\"/img/gdoc.png\" width=\"20\" height=\"19\"></a>":"&nbsp;")."</td>\n"
	    ."\t\t<td>".date(CALENDAR_FMT_TIME,$talk['dtstart'])."</td>\n"
	    ."\t\t<td>-</td>\n"
	    ."\t\t<td>".date(CALENDAR_FMT_TIME,$talk['dtend'])."</td>\n"
	    ."\t</tr>\n";
	$counter++;
    }

    echo "</table>\n</body></html>\n";
}

/* show cached calendar */
function calendar_show($conference)
{
    if(!($res=db_query('SELECT * FROM talks WHERE visible ORDER BY dtstart, summary ASC')))
    {
	header('Content-Type: text/plain; charset=UTF-8');
	echo 'Can\'t read calendar database';
	return FALSE;
    }

    $calendar = array();
    while($row = mysql_fetch_assoc($res))
	$calendar[$row['uid']]=$row;

    calendar_show_array($conference, $calendar);
}

/* update calendar from remoute source and display */
function calendar_update($conference)
{
    if(!($calendar = calendar_update_db()))
	return FALSE;

    calendar_show_array($conference, $calendar);
}

?>