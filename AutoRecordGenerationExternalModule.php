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
		if ($triggerField != "" && $targetProjectID != "" && is_numeric($targetProjectID)) {
			$targetProject = new \Project($targetProjectID);
			$sourceProject = new \Project($project_id);
			$recordData = \Records::getData($project_id,'array',array($record));

			$newRecordName = $this->parseRecordSetting($this->getProjectSetting("new_record"),$recordData[$record][$event_id]);
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
			//TODO In case of not explicitly defined new record name, need to implement way to automatically generate a new record ID
			if ($newRecordName != "") {
				$dataToPipe[$targetProject->table_pk] = $newRecordName;
			}

			$this->saveData($targetProjectID,$dataToPipe[$targetProject->table_pk],$targetProject->firstEventId,$dataToPipe);
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
		$result = db_query($sql);
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
}