<?php
/*****************************************************************************
 *
 *  sQRtalk.org - on-the-fly QR code generation and browser-caching
 *                calls http://phpqrcode.sourceforge.net/ PHP library
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

require_once('phpqrcode.php');

define('HTTP_EXPIRES', 60*60*24*7);
define('HTTP_DATEFORMAT', 'D, d M Y H:i:s');

$url = 'https://'.$_SERVER['HTTP_HOST'].substr($_SERVER['REQUEST_URI'],3);
$modified = filemtime(__FILE__);
$etag = md5(__FILE__.$url.$modified);

header('Pragma: public');
header('Cache-Control: maxage='.HTTP_EXPIRES);
header('Expires: ' . gmdate(HTTP_DATEFORMAT, time()+HTTP_EXPIRES) . ' GMT');
header('Last-Modified: '.gmdate(HTTP_DATEFORMAT, $modified).' GMT');
header('Etag: '.$etag);

if(	(isset($_SERVER['HTTP_IF_NONE_MATCH'])&&(trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag))||
	(isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])&&(($if_modified = @strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']))&&($modified<=$if_modified))) )
    header('HTTP/1.1 304 Not Modified');
else
    QRcode::png($url,false,0,4,0);

exit;
?>
