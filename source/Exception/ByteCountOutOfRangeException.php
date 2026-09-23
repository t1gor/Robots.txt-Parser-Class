<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Exception;

/** A byte count that parses but cannot mean anything: zero or negative. */
class ByteCountOutOfRangeException extends ConfigurationException {
}
