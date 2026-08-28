Feature: User can configure the module with two projects and verify that saving on one project pipes to another

    Scenario: User can create a series of projects using xml files
        Given I login to REDCap with the user "Test_Admin"
        And I create a new project named "Auto Record Generation Target" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "AutoRecordGeneration_Target.REDCap.xml", and clicking the "Create Project" button
        Then I should see "Auto Record Generation Target" 
        And I click on "My Projects"
        And I create a new project named "Auto Record Generation Source" by clicking on "New Project" in the menu bar, selecting "Practice / Just for fun" from the dropdown, choosing file "AutoRecordGeneration_Source.REDCap.xml", and clicking the "Create Project" button
        Then I should see "Auto Record Generation Source"

    Scenario: I can configure the module to point from one project to another
        Given I click on the link labeled "Manage"
        And I click on "Enable a module"
        Then I should see "Available Modules"
        And I click on the button labeled "Enable" in the row labeled "Auto Record Generation"
        Then I should NOT see "Available Modules"
        And I click on "Configure" in the row labeled "Auto Record Generation"
        And I select "Auto Record Generation Target" on the dropdown field labeled "1. Project in which to Generate New Record"
        And I select the checkbox option for the field labeled "1. Trigger save record save hook on target project" 
        And I select "Race" on the dropdown field labeled "1. Field to Trigger Record Generation"
        And I select the radio option "No" for the field labeled "1. Overwrite data in destination project record every time data is saved in this project"
        And I click on the button labeled "Save"
        Then I should NOT see "Configure Module: EOC Audit Tool Dashboard"

    Scenario: When I save a record, it updates the target project
        Given I click on "Add / Edit Records"
        And I click on "Add new record"
        And I enter "Kyle" in the field labeled "First Name"
        And I enter "McGuffin" in the field labeled "Last Name"
        And I enter "01/01/1980" in the field labeled "Date of Birth"
        And I select the radio option "Male" in the field labeled "Sex"
        And I select the radio option "White" in the field labeled "Race"
        And I enter "Test Notes" in the field labeled "Question 1"
        And I click on the button labeled "Save & Exit Form"
        And I click on "My Projects"
        And I click on the link labeled "Auto Record Generation Target"
        And I click on the link labeled "Add / Edit Records"
        And I select "1" on the dropdown field labeled "Choose an existing Record ID"
        Then I should NOT see "You may view an existing record/response by selecting it from the drop-down lists below"
        And I click on the icon in the column labeled "Status" and the row labeled "Receive"
        Then I should see "Kyle"
        And I should see "McGuffin"
        And I should see "01/01/1980"
        And I should NOT see "Test Notes"
