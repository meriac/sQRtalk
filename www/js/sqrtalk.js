/*****************************************************************************
 *
 *  sQRtalk.org - JavaScript AJAX glue code and initalization
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

var sqrtalk = new Object;

function sqrtalk_fmt(num)
{
    num = Math.floor(num).toString();

    if(num.length<2)
	num = '0'+num;
    return num;
}

function sqrtalk_show(visible, user)
{
    document.getElementById('main').style.display = visible ? 'none':'block';
    document.getElementById('login_form').style.display = visible ? 'block':'none';
    if(!visible)
	document.getElementById('login_pwd').value='';
}

function sqrtalk_login()
{
    var usr, pwd, obj;

    usr = document.getElementById('login_usr').value;
    if(usr=='')
	alert('Please enter your user name');
    else
    {
	pwd = document.getElementById('login_pwd').value;
	if(pwd=='')
	    alert('Please enter your password');
	else
	    xajax_talk_login(usr,pwd);
    }
}

function sqrtalk_update_bar(delta)
{
    var percent, time, min, sec;

    percent = delta?((delta/sqrtalk.speaker_time_max)*100):0;

    /* calculate minutes and seconds */
    time = delta/1000;
    min = sqrtalk_fmt(time/60);
    sec = sqrtalk_fmt(time%60);

    document.getElementById('time').style.height = percent+'%';
    document.getElementById('delta_time').style.height = (100-percent)+'%';
    document.getElementById('counter').innerHTML = min+':'+sec;
}

function sqrtalk_refresh_bar()
{
    var t = new Date();
    var percent, delta;

    if((delta = sqrtalk.speaker_time - t.getTime())>=0)
    {
	sqrtalk_update_bar (delta);

	/* re-trigger next API call */
	window.setTimeout('sqrtalk_refresh_bar()', 25);
    }
    else
	if(sqrtalk.speaker_time)
	    document.getElementById('reminder').style.visibility = 'visible';
}

function sqrtalk_bar(delta, max)
{
    var t = new Date();
    sqrtalk.speaker_time_max = max*1000;
    sqrtalk.speaker_time = t.getTime()+(delta*1000);
    /* hide reminder */
    document.getElementById('reminder').style.visibility = 'hidden';
    /* start bar update */
    sqrtalk_refresh_bar();
}

function sqrtalk_refresh()
{
    if (	(xajax!='undefined')&&
		(xajax.isLoaded)&&
		(typeof xajax_talk_refresh == 'function')
		)
	xajax_talk_refresh(sqrtalk.uid, sqrtalk.last_updated);
    /* re-trigger next API call */
    window.setTimeout('sqrtalk_refresh()', 1000);
}

function sqrtalk_onload(uid)
{
    /* store talk ID in local object */
    sqrtalk.uid = uid;
    /* initialize time stamp of last update */
    sqrtalk.last_updated = 0;
    /* start AJAX refresh code */
    sqrtalk_refresh();
    /* reset talk bar */
    sqrtalk.speaker_time = 0;
    sqrtalk.speaker_time_max = 0;
    /* start time bar update */
    sqrtalk_update_bar(0);
}
