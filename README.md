# Auto Record Generation External Module
This module is designed to allow for the data from one REDCap project to be migrated to another. This migration is handled by a trigger data field being saved. The module is designed to migrate any or every data field in the source project that matches a data field in the destination project by both name and type.

---

### Explanation of Module settings and functionality:
- "Project in Which to Generate New Record":
    This is a list of any REDCap projects that the user setting up the module has access to. If the required REDCap project is not in this dropdown, add yourself to the required REDCap project as a user.

- "Trigger save record save hook on target project":
    Toggle to execute the `redcap_save_record` hook in the destination project after records are copied over, simulating a user clocking "Save" on the _first event_ in the destination project. May be necessary to allow external modules in the target project to act as expected on auto generated records.
    
- "Field to Trigger Record Generation":
    This needs to be a data field on the project that needs to have a value entered to trigger the data migration. Any value will trigger this action.

- "Name for New Record":
    This setting determines how to name the record in the destination project. If this setting is left blank, the code will use auto-numbered record IDs. This setting accepts basic data piping as present in setting up REDCap data fields. For example, if you wanted to use the value from the data field with name "record_name" as the record ID in the destination project, the value for this setting would be `[record_name]`. This setting will also accept REDCap smart variables.
    
- "Overwrite data in destination project record every time data is saved in this project"
    This Yes/No setting manages whether the data from this project needs to be migrated to the destination project every time the record is saved. The default behavior for this module is to only migrate data the first time the triggering field is saved on a particular record. If "Yes" is selected for this setting, the data will be migrated every time the trigger field is saved, and will overwrite the data found in the destination project's record.
    
- "Data Field to Pipe to New Record":
    This is a repeatable setting that specifies what data gets migrated to the destination project. The field name in the source project and the destination project must match, and they must be the same type of field. If no field names are specified here, then every fields in the source project that matches a field in the destination project will be migrated when the trigger occurs.

### Longitudinal Projects
The module has several mechanisms for matching events between two projects that are longitudinal. First, it will try to match events between projects based on them sharing the same name. After that, it will attempt to match events based on the order. So, it will assume the first event in the source project should be added to the first event in the destination project.
In the case of a source project only having one event and generating records in a project that is longitudinal, it will default to only putting data into the first event in the destination project.
In the case of a source project being longitudinal and generating records in a project with only one event, the module by default will attempt to save the data from every event into the only event in the destination project. This can lead to overwriting of data in the destination record. If you have this situation, it is strongly advised that you specify a 'Name for New Record' setting that makes use of REDCap piping and smart variables to generate different destination records for each event in the source project.

### Projects with Repeating Instruments/Events
When a source project and destination project both using repeating events/instruments, the module will align data based on instance number. So, if you save data in instance 2 in the source project, it will place that data into instance 2 of the record in the destination project.
In the case of a source project not repeating and generating records in a project that is repeating, it will default to only putting data into the first instance in the destination project.
In the case of a source project being repeating and generating records in a project that doesn't repeat, the module by default will attempt to save the data from every instance into the same destination record. This can lead to overwriting of data in the destination record. If you have this situation, it is strongly advised that you specify a 'Name for New Record' setting that makes use of REDCap piping and smart variables to generate different destination records for each instance in the source project.
