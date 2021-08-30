<?php declare(strict_types=1);

namespace Utils;

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
