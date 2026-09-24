<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Exception;

/** An option name that is not one of KNOWN_OPTIONS - usually a config typo. */
class UnknownOptionException extends ConfigurationException {
}
