<?php
	require('./DBbackup.class.php');

	header("Content-type: text/html; charset=utf-8"); 

	$db=new DBbackup('localhost','root','','test');
	// $db->WriteTableToFile('pm_attribute');
	// $db->WriteAllToFile();
	// $db->ReadTableFromFile('./pm_attribute20120717082728.sql');	
	// $db->DownloadTable('pm_attribute');
	// $db->DownloadAllTables();
	// $db->GetFileNames('./bak');
	$db->ReadAllTablesFromFiles('./DBbackup/');
?>