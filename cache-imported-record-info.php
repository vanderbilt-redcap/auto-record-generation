<?php
$recordInfo = json_decode($_POST['data'], true);
$_SESSION["auto-record-generation-module-record-info"] = $recordInfo;

echo 'success';