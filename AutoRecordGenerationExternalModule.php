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
use mysql_xdevapi\Exception;
use REDCap;

class AutoRecordGenerationExternalModule extends AbstractExternalModule
{
    function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id = NULL, $repeat_instance = 1) {
    }

	function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance = 1) {
		$this->copyValuesToDestinationProjects($record, $event_id, $instrument, $repeat_instance);
	}

	function getNewRecordName(\Project $project, $recordData,$recordSetting,$srcProjectID,$event_id,$repeat_instance = 1) {
        $newRecordID = "";
        if ($recordSetting == "") {
            $destinationRecordID = "";
            $queryLogs = $this->queryLogs("SELECT message, record WHERE message='Auto record for ".array_keys($recordData)[0]."' AND project_id=".$srcProjectID);

            while ($row = db_fetch_assoc($queryLogs)) {
                if ($row['record'] != "") {
                    $destinationRecordID = $row['record'];
                }
            }
            $newRecordID = ($destinationRecordID != "" ? $destinationRecordID : \DataEntry::getAutoId($project->project_id));
            echo "New record after logs is $newRecordID<br/>";
        }
        else {
            $validRecordData = array();

            foreach ($recordData as $recordID => $data) {
                /*if ($data['redcap_event_name'] == $uniqueEventName || !isset($data['redcap_event_name'])) {
                    $validRecordData = $data;
                }*/
                foreach ($data as $eventID => $eventData) {
                    if ($eventID == "repeat_instances") {
                        foreach ($eventData as $subEventID => $subEventData) {
                            if ($subEventID == $event_id) {
                                $destEventRepeats = $project->isRepeatingEvent($subEventID);
                                foreach ($subEventData as $subInstrument => $subInstrumentData) {
                                    $destInstrumentRepeats = ($subInstrument != "" ? $project->isRepeatingForm($subEventID, $subInstrument) : 0);
                                    foreach ($subInstrumentData as $subInstance => $subInstanceData) {
                                        if ($subInstance == $repeat_instance) {
                                            foreach ($subInstanceData as $fieldName => $fieldValue) {
                                                if ($destInstrumentRepeats || $destEventRepeats) {
                                                    $validRecordData[$fieldName] = $fieldValue;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } elseif ($eventID == $event_id) {
                        $destEventRepeats = $project->isRepeatingEvent($event_id);
                        foreach ($eventData as $fieldName => $fieldValue) {
                            $destInstrumentRepeats = $project->isRepeatingForm($eventID, $project->metadata[$fieldName]['form_name']);
                            if (!$destEventRepeats && !$destInstrumentRepeats) {
                                $validRecordData[$fieldName] = $fieldValue;
                            }
                        }
                    }
                }
            }
            $newRecordID = $this->parseRecordSetting($recordSetting,$validRecordData);
            echo "New record after parse is $newRecordID<br/>";
        }
        return $newRecordID;
    }

	function copyValuesToDestinationProjects($record, $event_id, $instrument, $repeat_instance = 1) {
		$destinationProjects = $this->framework->getSubSettings('destination_projects');

        $project_id = $this->getProjectId();
		foreach ($destinationProjects as $destinationProject) {
            $flagFieldName = $destinationProject['field_flag'];
            $flagFieldForm =
            $results = REDCap::getData($project_id, 'array', $record, $flagFieldName, $event_id);
            /*$destData = REDCap::getData($destinationProject['destination_project'],'array',$record);
            echo "Dest Data on ".$destinationProject['destination_project']." with $record<br/>";
            echo "<pre>";
            print_r($destData);
            echo "</pre>";
            $results = \Records::saveData($destinationProject['destination_project'], 'array', $destData,'overwrite');
            echo "Dest save result:<br/>";
            echo "<pre>";
            print_r($results);
            echo "</pre>";*/

            if (isset($results[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance][$flagFieldName])) {
                $triggerFieldValue = $results[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance][$flagFieldName];
            }
            elseif (isset($results[$record]['repeat_instances'][$event_id][''][$repeat_instance][$flagFieldName])) {
                $triggerFieldValue = $results[$record]['repeat_instances'][$event_id][''][$repeat_instance][$flagFieldName];
            }
            else {
                $triggerFieldValue = $results[$record][$event_id][$flagFieldName];
            }
            $triggerFieldType = $this->getFieldType($flagFieldName);
            if(in_array($triggerFieldType, ['yesno', 'truefalse'])){
                $triggerFieldSet = $triggerFieldValue === "1";
            }
            else{
                $triggerFieldSet = $triggerFieldValue != "";
            }
            if ($triggerFieldSet) {
                $this->handleDestinationProject($record, $event_id, $destinationProject, $repeat_instance);
            }
		}
	}

	private function handleDestinationProject($record, $event_id, $destinationProject, $repeat_instance = 1) {
		$project_id = $this->getProjectId();

		$targetProjectID = $destinationProject['destination_project'];
        $overwrite = ($destinationProject['overwrite-record'] == "overwrite" ? $destinationProject['overwrite-record'] : "normal");
        $targetProject = new \Project($targetProjectID);
        $sourceProject = new \Project($project_id);
        $debug = $destinationProject['enable_debug_logging'];

        $recordData = \Records::getData($project_id,'array',$record);

        $uniqueEventName = $sourceProject->getUniqueEventNames()[$event_id];

        $destRecordExists = false;

        $recordToCheck = $this->getNewRecordName($targetProject,$recordData,$destinationProject["new_record"],$project_id,$event_id,$repeat_instance);
        if ($recordToCheck != "") {
            $targetRecordSql = "SELECT record FROM redcap_data WHERE project_id='$targetProjectID' && record='$recordToCheck' LIMIT 1";
            $result = db_query($targetRecordSql);
            while ($row = db_fetch_assoc($result)) {
                if ($row['record'] == $recordToCheck) {
                    $destRecordExists = true;
                }
            }
        }

		if($debug == "1"){
			$this->log("Checking values for pid $targetProjectID", [
			    '$targetProjectID' => $targetProjectID,
				'$destinationRecordID' => $recordToCheck,
				'$overwrite' => $overwrite,
				'$destRecordExists' => $destRecordExists
			]);
		}

		if ($targetProjectID != "" && is_numeric($targetProjectID) && ((!$destRecordExists && $overwrite == "normal") || $overwrite == "overwrite")) {
			$sourceFields = $this->getSourceFields($project_id,$destinationProject['pipe_fields']);
			//$recordData = \Records::getData($project_id,'array',array($record),$targetFields);
			$dataToPipe = array();

			$dataToPipe = $this->translateRecordData($recordData,$sourceProject,$targetProject,$sourceFields,$recordToCheck,$event_id,$repeat_instance);
			//$this->saveData($targetProjectID,$dataToPipe[$targetProject->table_pk],$targetProject->firstEventId,$dataToPipe);
            /*echo "Data to pipe:<br/>";
            echo "<pre>";
            print_r($dataToPipe);
            echo "</pre>";*/
            if ($project_id == "111562") {
                echo "Data to pipe:<br/>";
                echo "<pre>";
                print_r($dataToPipe);
                echo "</pre>";
                $this->exitAfterHook();
            }
            $results = \Records::saveData($targetProjectID, 'array', $dataToPipe,$overwrite);
            $errors = $results['errors'];
            /*echo "Result:<br/>";
            echo "<pre>";
            print_r($results);
            echo "</pre>";*/
            if(!empty($errors)){
            	$errorString = stripslashes(json_encode($errors, JSON_PRETTY_PRINT));
            	$errorString = str_replace('""', '"', $errorString);

            	$message = "The " . $this->getModuleName() . " module could not copy values for record " . $dataToPipe[$targetProject->table_pk] . " from project $project_id to project $targetProjectID because of the following error(s):\n\n$errorString";
            	error_log($message);

            	$errorEmail = $this->getProjectSetting('error_email');
            	if ($errorEmail == "") $errorEmail = "james.r.moore@vumc.org";
            	if(!empty($errorEmail)){
                    ## Add check for universal from email address
                    global $from_email;
                    if($from_email != '') {
                        $headers = "From: ".$from_email."\r\n";
                    }
                    else {
                        $headers = null;
                    }
                    mail($errorEmail, $this->getModuleName() . " Module Error", $message, $headers);
            	}
            }

			if ($destinationRecordID == "") {
                $this->log("Auto record for " . $record, array("destination_record_id" => $dataToPipe[$targetProject->table_pk]));
            }
		}
	}

	private function getFieldType($fieldName) {
		if(empty($fieldName)){
			return null;
		}

		$fieldName = db_real_escape_string($fieldName);
		$result = $this->query("select element_type from redcap_metadata where project_id = " . $this->getProjectId() . " and field_name = '$fieldName'");
		$row = $result->fetch_assoc();

		return $row['element_type'];
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
        $project = new \Project($project_id);
        $allFields = array_keys($project->metadata);

		$returnFields = array();
		if (is_array($pipeSettings) && !empty($pipeSettings) && $pipeSettings[0] != "") {
			$returnFields = array_intersect($allFields, $pipeSettings);
		}
		else {
			$returnFields = $allFields;
		}
		return $returnFields;
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

	function validateSettings($settings){
		$fieldFlags = $settings['field_flag'];
		foreach($fieldFlags as $fieldName){
			$type = $this->getFieldType($fieldName);
			if($type === 'checkbox'){
				// Checkboxes would be difficult to support since there could be multiple values and it's unclear whether any/all/certain values should be considered the trigger.
				return "Checkbox fields are not currently supported as trigger fields.  Please select a different field, or change the type of the current trigger field.";
			}
		}
	}

	function translateRecordData($sourceData, \Project $sourceProject, \Project $destProject, $fieldsToUse, $recordToUse, $eventToUse = "", $instanceToUse = "") {
	    $eventMapping = array();
	    $sourceEvents = $sourceProject->eventInfo;
	    $destEvents = $destProject->eventInfo;
        $destEventIDLeft = $destEvents;
        $eventOffset = 0;
        $sourceMeta = $sourceProject->metadata;
        $destMeta = $destProject->metadata;
        $destRecordField = $destProject->table_pk;
        $destFields = array_keys($destMeta);

        $destData = array();

	    foreach ($sourceEvents as $eventID => $eventInfo) {
	        if (count($destEvents) > 1) {
	            foreach ($destEvents as $destID => $destEventInfo) {
	                if ($eventInfo['name'] == $destEventInfo['name']) {
	                    $eventMapping[$eventID] =  $destID;
	                    unset($destEventIDLeft[$destID]);
                    }
                }
            }
	        elseif (($eventToUse != "" && $eventID == $eventToUse) || $eventToUse == "") {
	            $destEventID = array_keys($destEvents)[0];

	            if ($destEventID != "") {
                    $eventMapping[$eventID] = $destEventID;
                    unset($destEventIDLeft[$destEventID]);
                    break;
                }
            }
	        $eventOffset++;
        }

        $eventOffset = 0;
	    foreach ($sourceEvents as $eventID => $eventInfo) {
	        if (!isset($eventMapping[$eventID]) && count($destEventIDLeft) > 0) {
	            $eventMapping[$eventID] = array_keys(array_slice($destEventIDLeft,$eventOffset,1,true))[0];
                $eventOffset++;
            }
        }

	    if (!empty($sourceData)) {
	        foreach ($sourceData as $recordID => $recordData) {
	            foreach ($recordData as $eventID => $eventData) {
	                if ($eventID == "repeat_instances") {
	                    foreach ($eventData as $subEventID => $subEventData) {
	                        if (isset($eventMapping[$subEventID])) {
                                $destEventID = $eventMapping[$subEventID];
                                foreach ($subEventData as $instrument => $instrumentData) {
                                    foreach ($instrumentData as $instance => $instanceData) {
                                        if (($instanceToUse != "" && $instance == $instanceToUse) || $instanceToUse == "") {
                                            foreach ($instanceData as $fieldName => $fieldValue) {
                                                if ($fieldValue == "") continue;
                                                if ((in_array($fieldName,$fieldsToUse) || empty($fieldsToUse)) && in_array($fieldName,$destFields)) {
                                                    if ($fieldName == $destRecordField && $fieldValue != "") $fieldValue = $recordToUse;
                                                    $fieldInstrument = $sourceMeta[$fieldName]['form_name'];
                                                    $instrumentRepeats = $sourceProject->isRepeatingForm($subEventID, $fieldInstrument);
                                                    if (($instrument == $fieldInstrument && !$instrumentRepeats) || ($instrument != "" && $instrument != $fieldInstrument)) continue;
                                                    $this->setDestinationData($destData, $sourceProject, $destProject, $fieldName, $fieldValue, $recordToUse, $destEventID, $instance);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
	                elseif (isset($eventMapping[$eventID])) {
	                    //TODO Need to check if a field is on a repeating/non-repeating basis when looking here for a valid field value, it will be empty ALWAYS otherwise
	                    $destEventID = $eventMapping[$eventID];
                        
	                    foreach ($eventData as $fieldName => $fieldValue) {
	                        if ((in_array($fieldName,$fieldsToUse) || empty($fieldsToUse)) && in_array($fieldName,$destFields)) {
	                            if ($fieldValue == "") continue;
                                if ($fieldName == $destRecordField && $fieldValue != "") $fieldValue = $recordToUse;
                                $fieldInstrument = $sourceMeta[$fieldName]['form_name'];
                                $instrumentRepeats = $sourceProject->isRepeatingForm($eventID, $fieldInstrument);
                                if ($instrumentRepeats) continue;
                                $this->setDestinationData($destData, $sourceProject, $destProject, $fieldName, $fieldValue, $recordToUse, $destEventID);
                            }
                        }
                    }
                }
            }
        }
	    return $destData;
    }

    function setDestinationData(&$destData, \Project $sourceProject, \Project $destProject, $srcFieldName, $srcFieldValue, $destRecord, $destEvent,$destRepeat = 1)
    {
        $destMeta = $destProject->metadata;
        $destEventForms = $destProject->eventsForms[$destEvent];

        $destInstrument = $destMeta[$srcFieldName]['form_name'];
        $destRecordField = $destProject->table_pk;
        $srcMeta = $sourceProject->metadata;
        $destInstrumentRepeats = $destProject->isRepeatingForm($destEvent, $destInstrument);
        $destEventRepeats = $destProject->isRepeatingEvent($destEvent);

        if (in_array($destInstrument,$destEventForms) && $srcMeta[$srcFieldName]['element_type'] == $destMeta[$srcFieldName]['element_type'] && $srcMeta[$srcFieldName]['element_enum'] == $destMeta[$srcFieldName]['element_enum']) {
            if ($destInstrumentRepeats) {
                $destData[$destRecord][$destEvent][$destRecordField] = $destRecord;
                //$destData[$destRecord][$destEvent]['redcap_repeat_instrument'] = "";
                //$destData[$destRecord][$destEvent]['redcap_repeat_instance'] = $destRepeat;
                $destData[$destRecord]['repeat_instances'][$destEvent][$destInstrument][$destRepeat][$srcFieldName] = $srcFieldValue;
            } elseif ($destEventRepeats) {
                $destData[$destRecord][$destEvent][$destRecordField] = $destRecord;
                //$destData[$destRecord][$destEvent]['redcap_repeat_instrument'] = "";
                //$destData[$destRecord][$destEvent]['redcap_repeat_instance'] = $destRepeat;
                $destData[$destRecord]['repeat_instances'][$destEvent][''][$destRepeat][$srcFieldName] = $srcFieldValue;
            } else {
                $destData[$destRecord][$destEvent][$srcFieldName] = $srcFieldValue;
            }
        }
    }

    /*function getAutoId($projectId,$eventId = "")
    {
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

        $newParticipantId = db_result(db_query($sql),0);
        if ($newParticipantId == "") $newParticipantId = 0;
        $newParticipantId++;

        $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES
			({$projectId},{$eventId},'$newParticipantId','record_id','$newParticipantId')";

        db_query($sql);
        @db_query("COMMIT");
        $logSql = $sql;

        # Verify the new auto ID hasn't been duplicated
        $sql = "SELECT d.field_name
			FROM redcap_data d
			WHERE d.project_id = {$projectId}
				AND d.record = '$newParticipantId'";

        $result = db_query($sql);

        while(db_num_rows($result) > 1) {
            # Delete, increment by a random integer and attempt to re-create the record
            $sql = "DELETE FROM redcap_data
				WHERE d.project_id = $projectId
					AND d.record = '$newParticipantId'
					AND d.field_name = 'record_id'
				LIMIT 1";

            db_query($sql);

            $newParticipantId += rand(1,10);

            @db_query("BEGIN");

            $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) VALUES
				({$projectId},{$eventId},'$newParticipantId','record_id','$newParticipantId')";
            $logSql = $sql;

            db_query($sql);
            @db_query("COMMIT");

            $sql = "SELECT d.field_name
				FROM redcap_data d
				WHERE d.project_id = {$projectId}
					AND d.record = '$newParticipantId'";

            $result = db_query($sql);
        }

        \Logging::logEvent($logSql, $projectId, "INSERT", "redcap_data", $newParticipantId,"record_id='$newParticipantId'","Create Record");
        //logUpdate($logSql, $projectId, "INSERT", "redcap_data", $newParticipantId,"record_id='$newParticipantId'","Create Record");

        if($inTransaction) {
            @db_query("BEGIN");
        }
        // Return new auto id value
        return $newParticipantId;
    }*/
}
