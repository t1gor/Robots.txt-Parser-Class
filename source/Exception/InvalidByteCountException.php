<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Exception;

/** A byte count that is not an integer, integer string, unlimited alias or null. */
class InvalidByteCountException extends ConfigurationException {
}
