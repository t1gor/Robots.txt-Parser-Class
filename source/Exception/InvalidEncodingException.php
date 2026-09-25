<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Exception;

/** A configured encoding that is not a charset name at all - usually the wrong type. */
class InvalidEncodingException extends ConfigurationException {
}
