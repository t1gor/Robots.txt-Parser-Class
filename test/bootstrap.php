<?php require_once dirname(__FILE__) . "/../vendor/autoload.php";

function extractMessageFromRecord(array $record) {
	return $record['message'];
}

function stringifyLogs(array $handlerRecords): string {
	return strtr("Actual logs: {logs}", [
		"{logs}" => json_encode(
			array_map('extractMessageFromRecord', $handlerRecords),
			JSON_PRETTY_PRINT
		)
	]);
}
