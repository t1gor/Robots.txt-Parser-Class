<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Exception;

/** Extends InvalidArgumentException so existing catch blocks keep working. */
class ConfigurationException extends \InvalidArgumentException {
}
