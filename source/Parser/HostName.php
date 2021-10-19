<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser;

class HostName {
	public static function isValid(string $hostName): bool {
		$filtered    = filter_var($hostName, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
		$isIpAddress = false !== $filtered && static::isIpAddress($filtered);

		return false !== $filtered && !$isIpAddress;
	}

	public static function isIpAddress(string $hostName): bool {
		return $hostName === filter_var($hostName, FILTER_VALIDATE_IP);
	}
}
