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
        $overwrite = ($this->getProjectSetting('overwrite-record') == "overwrite" ? $this->getProjectSetting('overwrite-record') : "normal");
        $queryLogs = $this->queryLogs("SELECT message, destination_record_id WHERE message='Auto record for $record'");
        $targetProject = new \Project($targetProjectID);
        $sourceProject = new \Project($project_id);

        $destinationRecordID = "";
        while ($row = db_fetch_assoc($queryLogs)) {
            if ($row['destination_record_id'] != "") {
                $destinationRecordID = $row['destination_record_id'];
            }
        }

        $recordData = \Records::getData($project_id,'array',array($record));

        $newRecordName = $this->parseRecordSetting($this->getProjectSetting("new_record"),$recordData[$record][$event_id]);

        $destRecordExists = false;
        $recordToCheck = ($newRecordName != "" ? $newRecordName : $destinationRecordID);
        if ($recordToCheck != "") {
            $targetRecordSql = "SELECT record FROM redcap_data WHERE project_id='$targetProjectID' && record='$recordToCheck' LIMIT 1";
            $result = db_query($targetRecordSql);
            while ($row = db_fetch_assoc($result)) {
                if ($row['record'] == $recordToCheck) {
                    $destRecordExists = true;
                }
            }
        }

		if ($triggerField != "" && $targetProjectID != "" && is_numeric($targetProjectID) && (($destinationRecordID == "" && $overwrite == "normal") || $overwrite == "overwrite" || !$destRecordExists)) {
			//$fieldData = \MetaData::getFieldNames($project_id);
			$fieldData = $this->getProjectFields($project_id);
			//$targetFields = \MetaData::getFieldNames($targetProjectID);
			$targetFields = $this->getProjectFields($targetProjectID);
			$sourceFields = $this->getSourceFields($fieldData,$this->getProjectSetting('pipe_fields'));
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
			    if ($destinationRecordID != "") {
                    $dataToPipe[$targetProject->table_pk] = $destinationRecordID;
                }
                else {
                    $autoRecordID = $this->getAutoID($targetProjectID, $event_id);
                    $dataToPipe[$targetProject->table_pk] = $autoRecordID;
                }
			}

			//$this->saveData($targetProjectID,$dataToPipe[$targetProject->table_pk],$targetProject->firstEventId,$dataToPipe);
            \Records::saveData($targetProjectID, 'array', [$dataToPipe[$targetProject->table_pk] => [$targetProject->firstEventId => $dataToPipe]],$overwrite);
			if ($destinationRecordID == "") {
                $this->log("Auto record for " . $record, array("destination_record_id" => $dataToPipe[$targetProject->table_pk]));
            }
		}
	}

	function parseRecordSetting($recordsetting,$recorddata) {
		$returnString = $recordsetting;
		preg_match_all("/\[(.*?)\]/",$recordsetting,$matchRegEx);
		$stringsToReplace = $matchRegEx[0];
		$fieldNamesReplace = $matchRegEx[1];
		foreach ($fieldNamesReplace as $index => $fieldName) {
			$returnString = db_real_escape_string(str_replace($stringsToReplace[$index],$recorddata[$fieldName],$returnString));
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
}