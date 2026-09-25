<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Exception;

/** An option name that is not a {@see \t1gor\RobotsTxtParser\Config\Option} - usually a config typo. */
class UnknownOptionException extends ConfigurationException {
}
