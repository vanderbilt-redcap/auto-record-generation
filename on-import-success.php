<?php
$recordInfo = $_SESSION["auto-record-generation-module-record-info"];
foreach($recordInfo as $record){
	$recordId = $record['recordId'];
	$eventId = $record['eventId'];
	$module->copyValuesToDestinationProjects($recordId, $eventId, true);
}

echo 'success';
