<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Exception;

/** The output took fewer bytes than it was given, so what it holds is incomplete. */
class WriteFailedException extends \RuntimeException {
}
