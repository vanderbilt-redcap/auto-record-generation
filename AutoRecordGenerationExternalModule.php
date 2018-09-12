<?php

/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 5/31/2018
 * Time: 3:28 PM
 */
namespace Vanderbilt\AutoRecordGenerationExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class AutoRecordGenerationExternalModule extends AbstractExternalModule
{
	function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance = 1) {
		$triggerField = $_POST[$this->getProjectSetting('field_flag')];
		$targetProjectID = $this->getProjectSetting('destination_project');
		if ($triggerField != "" && $targetProjectID != "" && is_numeric($targetProjectID) && $this->firstTimeSave($project_id,$record,$event_id,$this->getProjectSetting('field_flag'),$repeat_instance) !== false) {
			$targetProject = new \Project($targetProjectID);
			$sourceProject = new \Project($project_id);
			$recordData = \Records::getData($project_id,'array',array($record));

			$newRecordName = $this->parseRecordSetting($this->getProjectSetting("new_record"),$recordData[$record][$event_id]);
			//$fieldData = \MetaData::getFieldNames($project_id);
			$fieldData = $this->getProjectFields($project_id);
			//$targetFields = \MetaData::getFieldNames($targetProjectID);
			$targetFields = $this->getProjectFields($targetProjectID);
			$sourceFields = $this->getSourceFields($fieldData,$this->getProjectSetting('pipe_fields'));
			$overwrite = ($this->getProjectSetting('overwrite-record') == "overwrite" ? $this->getProjectSetting('overwrite-record') : "normal");
			//$recordData = \Records::getData($project_id,'array',array($record),$targetFields);

			$dataToPipe = array();

			foreach ($targetFields as $targetField) {
				if (in_array($targetField,$sourceFields) && $targetProject->metadata[$targetField]['element_type'] != 'descriptive' && $targetProject->metadata[$targetField]['element_enum'] == $sourceProject->metadata[$targetField]['element_enum']) {
					$dataToPipe[$targetField] = $recordData[$record][$event_id][$targetField];
				}
			}

			if ($newRecordName != "") {
				$dataToPipe[$targetProject->table_pk] = $newRecordName;
			}
			else {
				$autoRecordID = $this->getAutoID($project_id,$event_id);
				$dataToPipe[$targetProject->table_pk] = $autoRecordID;
			}

			//$this->saveData($targetProjectID,$dataToPipe[$targetProject->table_pk],$targetProject->firstEventId,$dataToPipe);
            \Records::saveData($targetProjectID, 'array', [$dataToPipe[$targetProject->table_pk] => [$targetProject->firstEventId => $dataToPipe]],$overwrite);
		}
	}

	function parseRecordSetting($recordsetting,$recorddata) {
		$returnString = $recordsetting;
		preg_match_all("/\[(.*?)\]/",$recordsetting,$matchRegEx);
		$stringsToReplace = $matchRegEx[0];
		$fieldNamesReplace = $matchRegEx[1];
		foreach ($fieldNamesReplace as $index => $fieldName) {
			$returnString = str_replace($stringsToReplace[$index],$recorddata[$fieldName],$returnString);
		}
		return $returnString;
	}

	function getSourceFields($allFields,$pipeSettings) {
		$returnFields = array();
		if (is_array($pipeSettings) && !empty($pipeSettings) && $pipeSettings[0] != "") {
			$returnFields = $pipeSettings;
		}
		else {
			$returnFields = $allFields;
		}
		return $returnFields;
	}

	function getProjectFields($projectID) {
		$fieldArray = array();
		$sql = "SELECT field_name
			FROM redcap_metadata
			WHERE project_id=$projectID
			ORDER BY field_order";
		//echo "$sql<br/>";
		$result = $this->query($sql);
		while ($row = db_fetch_assoc($result)) {
			$fieldArray[] = $row['field_name'];
		}
		return $fieldArray;
	}

	function processFieldEnum($enum) {
		$enumArray = array();
		$splitEnum = explode("\\n",$enum);
		foreach ($splitEnum as $valuePair) {
			$splitPair = explode(",",$valuePair);
			$enumArray[trim($splitPair[0])] = trim($splitPair[1]);
		}
		return $enumArray;
	}

	function getAutoId($projectId, $eventId = "") {
		$inTransaction = false;
		try {
			@db_query("BEGIN");
		}
		catch (Exception $e) {
			$inTransaction = true;
		}

		### Get a new Auto ID for the given project ###
		$sql = "SELECT DISTINCT record
			FROM redcap_data
			WHERE project_id = $projectId
				AND field_name = 'record_id'
				AND value REGEXP '^[0-9]+$'
			ORDER BY abs(record) DESC
			LIMIT 1";

		$newParticipantId = db_result($this->query($sql),0);
		if ($newParticipantId == "") $newParticipantId = 0;
		$newParticipantId++;

		$sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES
			({$projectId},{$eventId},'$newParticipantId','record_id','$newParticipantId')";

		$this->query($sql);
		@db_query("COMMIT");
		$logSql = $sql;

		# Verify the new auto ID hasn't been duplicated
		$sql = "SELECT d.field_name
			FROM redcap_data d
			WHERE d.project_id = {$projectId}
				AND d.record = '$newParticipantId'";

		$result = $this->query($sql);

		while(db_num_rows($result) > 1) {
			# Delete, increment by a random integer and attempt to re-create the record
			$sql = "DELETE FROM redcap_data
				WHERE d.project_id = $projectId
					AND d.record = '$newParticipantId'
					AND d.field_name = 'record_id'
				LIMIT 1";

			$this->query($sql);

			$newParticipantId += rand(1,10);

			@db_query("BEGIN");

			$sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES
				({$projectId},{$eventId},'$newParticipantId','record_id','$newParticipantId')";
			$logSql = $sql;

			$this->query($sql);
			@db_query("COMMIT");

			$sql = "SELECT d.field_name
				FROM redcap_data d
				WHERE d.project_id = {$projectId}
					AND d.record = '$newParticipantId'";

			$result = $this->query($sql);
		}

		\Logging::logEvent($logSql, $projectId, "INSERT", "redcap_data", $newParticipantId,"record_id='$newParticipantId'","Create Record");
		//logUpdate($logSql, $projectId, "INSERT", "redcap_data", $newParticipantId,"record_id='$newParticipantId'","Create Record");

		if($inTransaction) {
			@db_query("BEGIN");
		}
		// Return new auto id value
		return $newParticipantId;
	}

	function firstTimeSave($project_id,$record_id,$event_id,$fieldName,$instance = "1") {
	    if ($this->getProjectSetting('overwrite-record') == "overwrite") {
	        echo "This was overwrite<br/>";
	        exit;
	        return true;
        }
		$instance = (is_numeric($instance) ? (int)$instance : 1);
		$instanceSql = "";
		if ($instance > 1) {
			$instanceSql = "AND data_values LIKE '%[instance = $instance]%'";
		}
		$sql = "SELECT ts, data_values
			FROM redcap_log_event
			WHERE (description = 'Create record' OR description = 'Update record')
				AND object_type='redcap_data'
				AND pk='$record_id'
				AND event_id='$event_id'
				AND project_id='$project_id'
				AND (data_values LIKE '%$fieldName = %' OR data_values IS NULL)
				ORDER BY ts ASC";
		echo "$sql<br/>";
		$result = $this->query($sql);
		$lastts = "";
		while ($row = db_fetch_assoc($result)) {
		    echo "<pre>";
		    print_r($row);
		    echo "</pre>";
			if ($lastts != "") {
				return false;
			}
			elseif (strpos($row['data_values'],"$fieldName =") !== 0) {
				$lastts = $row['ts'];
			}
		}
		exit;
		return true;
	}
}