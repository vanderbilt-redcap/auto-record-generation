<?php
$recordInfo = json_decode(file_get_contents("php://input"), true);
$_SESSION["auto-record-generation-module-record-info"] = $recordInfo;

echo 'success';