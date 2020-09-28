<div class="modal" tabindex="-1" role="dialog" id="auto-record-generation-import-modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <p>Please wait while the <?=$this->getModuleName()?> module processes the imported records...</p>
      </div>
    </div>
  </div>
</div>

<?=$this->initializeJavascriptModuleObject()?>

<script>
	$(function(){
		var module = <?=$this->framework->getJavascriptModuleObjectName()?>;
		module = module // This line exists solely to prevent PHPStorm uninitialized variable warnings after this line

		module.cacheRecordAndEventIds = function(){
			var eventNamesById = <?=json_encode($this->getEventNames())?>;
			eventNamesById = eventNamesById // This line exists solely to prevent PHPStorm uninitialized variable warnings after this line

			var eventIdsByName = {}
			for(var id in eventNamesById){
				var name = eventNamesById[id]
				eventIdsByName[name] = id
			}

			var comptable = $('table#comptable')

			var defaultEventId = null
			var recordInfo = []
			comptable.find('tr').each(function(index, row){
				row = $(row)

				if(index === 0){
					// This is the first header row.  Do nothing.
				}
				else if(index === 1){
					// This is the second header row.
					var eventIds = Object.keys(eventNamesById)
					if(eventIds.length === 1) {
						defaultEventId = eventIds[0]
					}
					else{
						var secondColumnName = row.find('th.comp_fieldname:nth-child(2)').text()
						if(secondColumnName !== 'redcap_event_name'){
							module.displayAndThrowError('The event id column could not be found.')
						}
					}
				}
				else{
					if(row.is(':empty')){
						// The last row in the table is always empty.  Skip it.
						return
					}

					var recordId = row.find('th.comp_recid > span:first-child').text()
					var eventId
					if(defaultEventId){
						eventId = defaultEventId
					}
					else{
						var eventName = row.find('td:first').text()
						eventId = eventIdsByName[eventName]

						if(!eventId){
							module.displayAndThrowError('The following event name does not exist: ' + eventName)
						}
					}

					recordInfo.push({
						'recordId': recordId,
						'eventId': eventId
					})
				}
			})

			module.cacheRecordInfo(recordInfo)
		}

		module.displayAndThrowError = function(error){
			var error = 'The <?=$this->getModuleName()?> module is not able to run on import.  Please report this issue.  ' + error
			alert(error)
			throw error // throw the error to prevent further code from executing
		}

		module.cacheRecordInfo = function(recordInfo){
			$.post(<?=json_encode($this->getUrl('cache-imported-record-info.php'))?>, {data: JSON.stringify(recordInfo)}, function(data){
				if(data !== 'success'){
					module.displayAndThrowError('Unexpected cache result: ' + data)
				}
			})
		}

		module.onImportSuccess = function(){
			var modal = $('#auto-record-generation-import-modal');
			modal.modal({
				backdrop: 'static', // prevent closing by clicking outside the modal
				keyboard: false // prevent the escape key from closing the modal
			})

			$.post(<?=json_encode($this->getUrl('on-import-success.php'))?>, '', function(data){
				modal.modal('hide')

				if(data !== 'success'){
					module.displayAndThrowError('The following error was returned: ' + data)
				}
			})
		}

		if(module.isImportReviewPage()){
			module.cacheRecordAndEventIds()
		}
		else if(module.isImportSuccessPage()){
			module.onImportSuccess()
		}
	})
</script>