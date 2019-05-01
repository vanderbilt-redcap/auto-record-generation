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
use REDCap;

class AutoRecordGenerationExternalModule extends AbstractExternalModule
{
	function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance = 1) {
		$this->copyValuesToDestinationProjects($record, $event_id);
	}

	function copyValuesToDestinationProjects($record, $event_id) {
		$destinationProjects = $this->framework->getSubSettings('destination_projects');
		foreach ($destinationProjects as $destinationProject) {
			$this->handleDestinationProject($record, $event_id, $destinationProject);
		}
	}

	private function handleDestinationProject($record, $event_id, $destinationProject) {
		$project_id = $this->getProjectId();

		$flagFieldName = $destinationProject['field_flag'];
		$results = json_decode(REDCap::getData($project_id, 'json', $record, $flagFieldName, $event_id), true);
		$triggerField = $results[0][$flagFieldName];

		$targetProjectID = $destinationProject['destination_project'];
        $overwrite = ($destinationProject['overwrite-record'] == "overwrite" ? $destinationProject['overwrite-record'] : "normal");
        $queryLogs = $this->queryLogs("SELECT message, destination_record_id WHERE message='Auto record for $record'");
        $targetProject = new \Project($targetProjectID);
        $sourceProject = new \Project($project_id);
        $debug = $destinationProject['enable_debug_logging'];

        $destinationRecordID = "";
        while ($row = db_fetch_assoc($queryLogs)) {
            if ($row['destination_record_id'] != "") {
                $destinationRecordID = $row['destination_record_id'];
            }
        }

        $recordData = \Records::getData($project_id,'array',array($record));

        $newRecordName = $this->parseRecordSetting($destinationProject["new_record"],$recordData[$record][$event_id]);

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

		if($debug){
			$this->log("Checking values for pid $targetProjectID", [
				'$triggerField' => $triggerField,
				'$targetProjectID' => $targetProjectID,
				'$destinationRecordID' => $destinationRecordID,
				'$overwrite' => $overwrite,
				'$destRecordExists' => $destRecordExists
			]);
		}

		if ($triggerField != "" && $targetProjectID != "" && is_numeric($targetProjectID) && (($destinationRecordID == "" && $overwrite == "normal") || $overwrite == "overwrite" || !$destRecordExists)) {
			//$targetFields = \MetaData::getFieldNames($targetProjectID);
			$targetFields = $this->getProjectFields($targetProjectID);
			$sourceFields = $this->getSourceFields($project_id,$destinationProject['pipe_fields']);
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
			
			if($debug){
				$this->log("Saving data for pid $targetProjectID", [
					'json-data' => json_encode($dataToPipe, JSON_PRETTY_PRINT)
				]);
			}

			//$this->saveData($targetProjectID,$dataToPipe[$targetProject->table_pk],$targetProject->firstEventId,$dataToPipe);
            $results = \Records::saveData($targetProjectID, 'array', [$dataToPipe[$targetProject->table_pk] => [$targetProject->firstEventId => $dataToPipe]],$overwrite);

            $errors = $results['errors'];
            if(!empty($errors)){
            	error_log("The " . $this->getModuleName() . " module could not save record " . $dataToPipe[$targetProject->table_pk] . " for project $targetProjectID because of the following error(s): " . json_encode($errors, JSON_PRETTY_PRINT));
            }

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

	function getSourceFields($project_id,$pipeSettings) {
		$nonRepeatableFields = $this->getProjectFields($project_id);

		$returnFields = array();
		if (is_array($pipeSettings) && !empty($pipeSettings) && $pipeSettings[0] != "") {
			$returnFields = array_intersect($nonRepeatableFields, $pipeSettings);
		}
		else {
			$returnFields = $nonRepeatableFields;
		}
		return $returnFields;
	}

	function getProjectFields($projectID) {
		$fieldArray = array();
		$sql = "
			SELECT
				m.field_name
			FROM redcap_metadata m 
			LEFT JOIN redcap_events_arms a 
				ON a.project_id = m.project_id 
			LEFT JOIN redcap_events_metadata e 
				ON e.arm_id = a.arm_id 
			LEFT JOIN redcap_events_repeat r 
				ON r.event_id = e.event_id 
				AND r.form_name = m.form_name 
			WHERE
				m.project_id=$projectID 
				AND r.event_id IS NULL -- exclude repeatable fields since they aren't currently supported
			ORDER BY field_order
		";
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

	function redcap_module_system_enable($version) {
		// A version of this module with the older settings format could have previously been enabled.
		// Make sure any old settings are updated.
		self::ensureProperSubSettingsFormat();
	}

	function redcap_module_system_change_version($version, $old_version) {
		// This could be a transition from a version of this module with the older settings format.
		// Make sure any old settings are updated.
		self::ensureProperSubSettingsFormat();
	}

	// This function is required to update existing settings after 'pipe_fields' was wrapped in the 'destination_projects' sub settings group.
	// This should have no effect on subsequent runs and should be safe and efficient to repeatedly run indefinitely on future updates.
	private function ensureProperSubSettingsFormat() {
		$query = function($beginning, $setClause, $fieldName, $leadingBracketsRequired){
			$prefix = '';
			while($leadingBracketsRequired > 0){
				$prefix .= '[';
				$leadingBracketsRequired--;
			}

			return $this->query("
				$beginning 
					redcap_external_module_settings s
					join redcap_external_modules m
						on m.external_module_id = s.external_module_id
				$setClause
				where
					m.directory_prefix = '" . $this->PREFIX . "'
					and s.`key` = '$fieldName'
					and
						(
							type <> 'json-array'
							or
							s.value not like '$prefix%'
						)
			");
		};

		$handleField = function($fieldName, $leadingBracketsRequired) use ($query){
			$result = $query('select project_id, value from', '', $fieldName, $leadingBracketsRequired);

			while($row = $result->fetch_assoc()){
				$this->log("Logging old '$fieldName' value before wrapping in extra array", $row);

				$projectId = $row['project_id'];

				$value = $this->getProjectSetting($fieldName, $projectId);
				$this->setProjectSetting($fieldName, [$value], $projectId);
			}
		};

		$handleField('destination_project', 1);
		$handleField('field_flag', 1);
		$handleField('new_record', 1);
		$handleField('overwrite-record', 1);
		$handleField('pipe_fields', 2);
	}

	function redcap_module_import_page_top() {
		require_once __DIR__ . '/import-page-top.php';
	}

	function getEventNames(){
		global $longitudinal;
		$originalValue = $longitudinal;

		// Override the longitudinal value so that event details are returned even if the project is not longitudinal
		$longitudinal = true;

		$result = REDCap::getEventNames(true);

		$longitudinal = $originalValue;

		return $result;
	}
}
