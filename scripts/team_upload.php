<?php
/*
    This script gets the details of each chapter's executive committee
    (where team list upload is enabled for that chapter) and uploads the
    team list to each chapter's website. This option can be enabled/disabled,
    and the FTP details can be specified, in the chapter settings in the
    Robogals Global Admin Panel in myRobogals
*/
$myrg_dbh = mysql_connect('localhost', 'myrobogals', 'myrobogals');
mysql_select_db('myrobogals', $myrg_dbh);
$sql = "SELECT id, ftp_host, ftp_user, ftp_pass, ftp_path, name FROM auth_group WHERE `upload_exec_list` = 1";
$result = mysql_query($sql, $myrg_dbh);
$ftps = array();
while ($row = mysql_fetch_row($result)) {
	$ftps[intval($row[0])] = array(
		'host' => $row[1],
		'user' => $row[2],
		'pass' => $row[3],
		'path' => $row[4],
		'chapter_name' => $row[5]);
}
mysql_free_result($result);
foreach ($ftps as $chapter_id => $ftp) {
	$fp = fopen('tmp.html', 'w');
	fwrite($fp, "\n<!--\n      This file is uploaded from myRobogals every 24 hours.\n      Do not edit this file, as your changes will be overwritten.\n\n      If your executive committee has changed,\n      please email secretary robogals.org to update our records.\n-->\n");
	$sql = "SELECT u.first_name, u.last_name, pt.description, u.email FROM rgprofile_position AS p, rgprofile_positiontype AS pt, auth_user AS u WHERE p.positionChapter_id = {$chapter_id} AND p.position_date_end IS NULL AND p.positionType_id = pt.id AND p.user_id = u.id ORDER BY pt.rank ASC";
	$result = mysql_query($sql, $myrg_dbh);
	while ($row = mysql_fetch_row($result)) {
		fwrite($fp, "<p><strong>{$row[0]} {$row[1]}</strong><br /><em>{$row[2]}</em><br />{$row[3]}</p>\n");
	}
	mysql_free_result($result);
	fclose($fp);
	//echo "Got information for ".$ftp['chapter_name']."\n";
	//echo "Going to connect to ".$ftp['host']."\n";
	$conn_id = ftp_connect($ftp['host']);
	if (!$conn_id) {
		echo "Could not connect to FTP host ".$ftp['host']." for chapter ".$ftp['chapter_name']."\n";
		continue;
	}
	//echo "Logging in with username ".$ftp['user']." and password ".$ftp['pass']."\n";
	$login_result = ftp_login($conn_id, $ftp['user'], $ftp['pass']);
	if (!$login_result) {
		echo "FTP login details rejected for chapter ".$ftp['chapter_name']."\n";
		ftp_close($conn_id);
		continue;
	}
	//echo "Setting passive mode\n";
	ftp_pasv($conn_id, true);
	$directory = dirname($ftp['path']);
	$filename = basename($ftp['path']);
	//echo "Changing directory to ".$directory."\n";
	if (!ftp_chdir($conn_id, $directory)) {
		echo "Unable to change directory to ".$directory." for ".$ftp['chapter_name']."\n";
		ftp_close($conn_id);
		continue;
	}
	//echo "Uploading file as ".$filename."\n";
	$upload = ftp_put($conn_id, $filename, 'tmp.html', FTP_ASCII);
	if (!$upload) {
		echo "FTP upload failed for chapter ".$ftp['chapter_name']."\n";
	}
	ftp_close($conn_id);
	//echo "Finished for ".$ftp['chapter_name']."\n";
}
mysql_close($myrg_dbh);
?>
