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
	const RECORD_CREATED_BY_MODULE = "auto_record_module_saved";

    function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id = NULL, $repeat_instance = 1) {
        //The below code was necessary for resetting module settings for a project so it could be reset with all new records.

        /*if (in_array($project_id,array(103538,102495,106458,102710,111557,111562,116774,116805,116831))) {
            $remove = $this->removeLogs("DELETE WHERE message LIKE 'Auto record for%'");
            echo "<pre>";
            print_r($remove);
            echo "</pre>";
        }*/

        //This code is for switching up where a record goes to fix old, invalid log mapping
        /*$recordChange = array(
            185=>1058,366=>1149,5=>"Laycox, Gloria June",312=>1119,493=>1203,358=>1145,549=>1228,43=>"Wade, Chester A",413=>1172,69=>1002,387=>1163,276=>1100,294=>1109,226=>1072,434=>1179,383=>1161,372=>1152,228=>1074,327=>1133,6=>"Alexander, Ronetta Renee",418=>1174,253=>1088,155=>1050,227=>1073,159=>1051,78=>1020,362=>1153,310=>1117,281=>1102,238=>1081,129=>1036,546=>1222,367=>1150,231=>1079,247=>1085,79=>1007,519=>1216,483=>1199,306=>1115,318=>1130,71=>1004,282=>1108,430=>1180,136=>1040,289=>1105,365=>1148,474=>1202,244=>1083,102=>1026,340=>1140,85=>1011,112=>1027,348=>1142,391=>1165,7=>"Cook, Allen R",233=>1077,328=>1134,73=>1005,167=>1054,475=>1195,339=>1138,432=>1182,191=>1061,293=>1107,117=>1031,41=>"Ruiz, Susie",162=>1052,239=>1082,131=>1037,33=>"Engidaye, Worknesh G",303=>1113,308=>1132,304=>1114,438=>1184,292=>1124,127=>1034,52=>1000,209=>1067,272=>1098,9=>"Eastridge, Joanne T",466=>1193,385=>1162,251=>1087,184=>1057,507=>1207,230=>1075,186=>1059,378=>1159,471=>1201,350=>1158,60=>"Devault, Michael J",63=>"Stephens, Susan",165=>1053,314=>1120,360=>1146,207=>1070,437=>1183,89=>1015,133=>1038,174=>1055,555=>1227,334=>1136,201=>1069,421=>1187,12=>"Tuan, Alh",515=>1213,116=>1030,405=>1171,255=>1090,125=>1033,321=>1127,221=>1086,151=>1049,347=>1141,422=>1175,375=>1156,264=>1096,34=>"Ruiz, Emeterio Medina",389=>1164,263=>1094,178=>1056,417=>1173,331=>1135,218=>1071,14=>"Goodrum, Stephen Carline",70=>1003,527=>1219,363=>1154,149=>1047,351=>1143,486=>1200,92=>1018,371=>1151,467=>1190,35=>"Mina, Mina L",511=>1211,83=>1010,476=>1196,97=>1023,88=>1014,37=>"Younan, Nagwa",119=>1032,113=>1028,145=>1044,220=>1078,192=>1062,462=>1189,544=>1221,287=>1104,86=>1012,439=>1185,379=>1168,16=>"Abdelmalak, Maria",260=>1093,433=>1178,397=>1169,521=>1212,498=>1204,455=>1188,104=>1022,138=>1041,478=>1197,533=>1215,232=>1076,529=>1214,94=>1019,99=>1024,423=>1176,275=>1122,380=>1160,503=>1224,442=>1186,68=>1001,541=>1220,77=>1008,543=>1223,31=>"Granstaff, Mary Eleanor",505=>1206,19=>"Cathcart, Cyril L",325=>1131,20=>"Nelson, John",285=>1106,273=>1099,38=>"Hernandez Perez=> Bertha",74=>1006,27=>"Cook, Jack H",237=>1080,54=>"Akin, James Leon",256=>1091,512=>1209,22=>"Garcia, Eba",29=>"Lian, Do Khan",30=>"Alvarado Cadena, Alfredo",28=>"Bonilla, Manuel De Jesus",75=>1009,39=>"Haji, Bardo",48=>"Franklin, Donnie",59=>"Vargas, Ricardo",134=>1039,90=>1017,87=>1013,91=>1016,114=>1029,100=>1021,106=>1025,111=>1035,206=>1065,141=>1042,190=>1060,144=>1043,146=>1045,197=>1064,200=>1066,150=>1048,210=>1068,195=>1063,148=>1046,254=>1089,357=>1147,317=>1123,284=>1103,297=>1112,280=>1101,245=>1084,311=>1118,309=>1116,266=>1095,271=>1097,295=>1110,299=>1125,257=>1092,404=>1170,377=>1157,343=>1139,355=>1144,424=>1177,315=>1121,320=>1126,374=>1155,396=>1167,322=>1128,316=>1129,338=>1137,392=>1166,469=>1191,479=>1198,470=>1192,444=>1194,563=>1231,427=>1181,499=>1205,508=>1208,560=>1230,520=>1218,552=>1226,536=>1217,514=>1210,556=>1229,548=>1225,
        );
        if (in_array($record,array_keys($recordChange)) && $project_id == "110730") {
            $this->removeLogs("DELETE WHERE message = 'Auto record for $record' AND project_id=$project_id");
            $logID = $this->log("Auto record for " . $record, ["destination_record_id" => $recordChange[$record]]);
        }*/
    }

	function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance = 1) {
		## Prevent hook from being called multiple times on each project/record pair
		if(defined(self::RECORD_CREATED_BY_MODULE.$project_id."~".$record) &&
				constant(self::RECORD_CREATED_BY_MODULE.$project_id."~".$record) == 1) {
			return;
		}

		define(self::RECORD_CREATED_BY_MODULE.$project_id."~".$record,1);

		## Make REDCap think we're importing from ODM so it allows certain "errors"
		if(!defined('CREATE_PROJECT_ODM')) {
			define("CREATE_PROJECT_ODM",1);
		}

		## In case this gets triggered by a cron, set PID
		$_GET['pid'] = $project_id;

		$this->copyValuesToDestinationProjects($record, $event_id, $repeat_instance);
	}

	function getNewRecordName(\Project $project, $recordData,$recordSetting,$srcProjectID,$event_id,$repeat_instance = 1) {
        $newRecordID = "";

        if ($recordSetting == "") {
            $destinationRecordID = "";
            $queryLogs = $this->queryLogs("SELECT message, record, destination_record_id WHERE message='Auto record for ".array_keys($recordData)[0]."'");

            while ($row = db_fetch_assoc($queryLogs)) {
                if ($row['destination_record_id'] != "") {
                    $destinationRecordID = $row['destination_record_id'];
                }
                elseif ($row['record'] != "") {
                    $destinationRecordID = $row['record'];
                }
            }
            $newRecordID = ($destinationRecordID != "" ? $destinationRecordID : \DataEntry::getAutoId($project->project_id));
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
                                                if ($fieldValue != "") {
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
        }
        return $newRecordID;
    }

	function copyValuesToDestinationProjects($record, $event_id, $repeat_instance = 1) {
		$destinationProjects = $this->framework->getSubSettings('destination_projects');

        $project_id = $this->getProjectId();
        $currentProject = new \Project($project_id);
        $eventName = $currentProject->uniqueEventNames[$event_id];

		foreach ($destinationProjects as $destinationProject) {
            $flagFieldName = $destinationProject['field_flag'];
            $results = json_decode(REDCap::getData($project_id, 'json', $record, $flagFieldName, $event_id),true);

            ## Need to set default value as $flagFieldName may not exist
            $triggerFieldValue = "";
            foreach ($results as $indexData) {
                if ((!isset($indexData['redcap_event_name']) || $indexData['redcap_event_name'] == $eventName) && $indexData[$flagFieldName] != "") {
                    $triggerFieldValue = $indexData[$flagFieldName];
                }
            }
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

            /*if (isset($results[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance][$flagFieldName])) {
                $triggerFieldValue = $results[$record]['repeat_instances'][$event_id][$instrument][$repeat_instance][$flagFieldName];
            }
            elseif (isset($results[$record]['repeat_instances'][$event_id][''][$repeat_instance][$flagFieldName])) {
                $triggerFieldValue = $results[$record]['repeat_instances'][$event_id][''][$repeat_instance][$flagFieldName];
            }
            else {
                $triggerFieldValue = $results[$record][$event_id][$flagFieldName];
            }*/
            $triggerFieldType = $this->getFieldType($flagFieldName);
            if(in_array($triggerFieldType, ['yesno', 'truefalse'])){
                $triggerFieldSet = $triggerFieldValue === "1";
            }
            else{
                $triggerFieldSet = $triggerFieldValue != "";
            }
            //echo "Trigger field set: ".($triggerFieldSet ? "True" : "False")."<br/>";
            if ($triggerFieldSet) {
                $this->handleDestinationProject($record, $event_id, $destinationProject, $repeat_instance);
            }
		}
		//$this->exitAfterHook();
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
            $table = $this->getDataTable($targetProjectID);
            $targetRecordSql = "SELECT record FROM $table WHERE project_id=? && record=? LIMIT 1";
            $result = db_query($targetRecordSql,[$targetProjectID,$recordToCheck]);

            while ($row = db_fetch_assoc($result)) {
                if ($row['record'] == $recordToCheck) {
                    $destRecordExists = true;
                }
            }
        }

		if($debug == "1"){
			$this->log("Checking values for pid $targetProjectID", [
			    'targetProjectID' => $targetProjectID,
				'destinationRecordID' => $recordToCheck,
				'overwrite' => $overwrite,
				'destRecordExists' => $destRecordExists
			]);
		}

		if ($targetProjectID != "" && is_numeric($targetProjectID) && ((!$destRecordExists && $overwrite == "normal") || $overwrite == "overwrite")) {
			$sourceFields = $this->getSourceFields($project_id,$destinationProject['pipe_fields']);
			//$recordData = \Records::getData($project_id,'array',array($record),$targetFields);
			$dataToPipe = array();

			$dataToPipe = $this->translateRecordData($recordData,$sourceProject,$targetProject,$sourceFields,$recordToCheck,$event_id,$repeat_instance);

			if ($recordToCheck != "") {
                $results = \Records::saveData($targetProjectID, 'array', $dataToPipe,$overwrite);
                $errors = $results['errors'];
                /*echo "Result:<br/>";
                echo "<pre>";
                print_r($results);
                echo "</pre>";*/
                if(!empty($errors)){
                    $errorString = stripslashes(json_encode($errors, JSON_PRETTY_PRINT));
                    $errorString = str_replace('""', '"', $errorString);

                    $message = "The " . $this->getModuleName() . " module could not copy values for record " . $recordToCheck . " from project $project_id to project $targetProjectID because of the following error(s):\n\n$errorString";
                    error_log($message);

                    $errorEmail = $this->getProjectSetting('error_email');
                    //if ($errorEmail == "") $errorEmail = "james.r.moore@vumc.org";
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
                else {
                    if ($destinationProject["new_record"] == "") {
                        $newRecord = true;
                        $queryLogs = $this->queryLogs("SELECT message, record, destination_record_id WHERE message='Auto record for " . array_keys($recordData)[0] . "'");

                        while ($row = db_fetch_assoc($queryLogs)) {
                            if ($row['destination_record_id'] != "" || $row['record'] != "") {
                                $newRecord = false;
                            }
                        }

                        if ($newRecord) {
                            $logID = $this->log("Auto record for " . $record, ["destination_record_id" => $recordToCheck]);
                            //echo "Log ID: $logID for " . $recordToCheck . "<br/>";
                        }
                    }
					$target_project_index = array_search($targetProjectID, $this->getProjectSetting("destination_project"));
					if ($this->getProjectSetting("trigger_save_hook_flag")[$target_project_index] === true) {
						global $Proj;
						## Call the save record hook on the new record
						# Cache get params to reset later
						$oldId = $_GET['id'];
						$oldPid = $_GET['pid'];
						$oldProj = $Proj;

						## Set the $_GET parameter to avoid errors / source project being affected
						$_GET['pid'] = $targetProjectID;
						$_GET['id'] = array_keys($dataToPipe)[0];
						$Proj = $targetProject;

						## Prevent module errors from crashing the whole import process
						## NOTE: this does NOT catch errors thrown while the target module's redcap_save_record hook is running;
						## errors from the target module will be handled as if the target module itself were running
						try {
							$redcap_save_record_args = [
								/* $project_id = */ $_GET['pid'],
								/* $record = */ $_GET['id'],
								/* $instrument = */ NULL,
								/* $event_id = */ $targetProject->firstEventId,
								/* $group_id = */ NULL,
								/* $survey_hash = */ NULL,
								/* $response_id = */ NULL,
								/* $repeat_instance = */ $repeat_instance
							];
							ExternalModules::callHook("redcap_save_record", $redcap_save_record_args);
						}
						catch(\Exception $e) {
							error_log("External Module Error - Project: ".$_GET['pid']." - Record: ".$_GET['id'].": ".$e->getMessage());
						}

						$_GET['id'] = $oldId;
						$_GET['pid'] = $oldPid;
						$Proj = $oldProj;
					}
                }
            }
		}
		//$this->exitAfterHook();
	}

	private function getFieldType($fieldName) {
		if(empty($fieldName)){
			return null;
		}

		$fieldName = db_real_escape_string($fieldName);
        $sql = "select element_type 
                from redcap_metadata 
                where project_id = ? 
                and field_name = ?";
		$result = $this->query($sql,[$this->getProjectId(),$fieldName]);
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

            $sql = "
				$beginning 
					redcap_external_module_settings s
					join redcap_external_modules m
						on m.external_module_id = s.external_module_id
				$setClause
				where
					m.directory_prefix = '" . $this->PREFIX . "'
					and s.`key` = ?
					and
						(
							type <> 'json-array'
							or
							s.value not like '$prefix%'
						)";
			return $this->query($sql,[$fieldName]);
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
			if($type === 'sav'){
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

        if (in_array($destInstrument,$destEventForms) && $srcMeta[$srcFieldName]['element_type'] == $destMeta[$srcFieldName]['element_type'] && $this->matchEnum($srcMeta[$srcFieldName]['element_enum'],$destMeta[$srcFieldName]['element_enum'])) {
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

    function matchEnum($srcEnum, $destEnum) {
        $destEnum = str_replace(' \n','\n',$destEnum);
        $destEnum = str_replace('\n ','\n',$destEnum);
        $destEnum = str_replace(' ,',',',$destEnum);
        $destEnum = str_replace(', ',',',$destEnum);
        $destEnum = rtrim($destEnum);
        $srcEnum = str_replace(' \n','\n',$srcEnum);
        $srcEnum = str_replace('\n ','\n',$srcEnum);
        $srcEnum = str_replace(' ,',',',$srcEnum);
        $srcEnum = str_replace(', ',',',$srcEnum);
        $srcEnum = rtrim($srcEnum);

        if ($srcEnum == $destEnum) {
            return true;
        }
        return false;
    }

    function getDataTable($project_id){
        return method_exists('\REDCap', 'getDataTable') ? \REDCap::getDataTable($project_id) : "redcap_data"; 
    }

    function escape($arg){
        return $this->framework->escape($arg);
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

        $table = $this->getDataTable($projectId);
        $sql = "INSERT INTO $table (project_id, event_id, record, field_name, value) VALUES
			({$projectId},{$eventId},'$newParticipantId','record_id','$newParticipantId')";

        db_query($sql);
        @db_query("COMMIT");
        $logSql = $sql;

        # Verify the new auto ID hasn't been duplicated
        $table = $this->getDataTable($projectId);
        $sql = "SELECT d.field_name
			FROM $table d
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

            $table = $this->getDataTable($projectId);
            $sql = "INSERT INTO $table (project_id, event_id, record, field_name, value) VALUES
				({$projectId},{$eventId},'$newParticipantId','record_id','$newParticipantId')";
            $logSql = $sql;

            db_query($sql);
            @db_query("COMMIT");

            $table = $this->getDataTable($projectId);
            $sql = "SELECT d.field_name
				FROM $table d
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
