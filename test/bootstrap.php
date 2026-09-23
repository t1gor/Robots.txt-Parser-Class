<?php require_once dirname(__FILE__) . "/../vendor/autoload.php";

use Monolog\LogRecord;

// Monolog 3 hands out LogRecord objects; they keep array access for the old keys.
function extractMessageFromRecord(LogRecord $record) {
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
