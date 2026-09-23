<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Exception;

/** Something was asked of the parser before it was given anything to parse. */
class NoContentException extends \LogicException {
}
