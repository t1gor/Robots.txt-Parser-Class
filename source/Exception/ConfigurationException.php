<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Exception;

/**
 * Extends InvalidArgumentException so existing catch blocks keep working, while still letting
 * callers single out configuration problems.
 *
 * @see ConfigurationExceptionFactory to build one.
 */
class ConfigurationException extends \InvalidArgumentException {
}
