<?php

namespace SilverStripe\Snapshots;

use RuntimeException;
use Throwable;

class InvalidSnapshotException extends RuntimeException
{
    public function __construct($snapshot = '', $code = 0, ?Throwable $previous = null)
    {
        $message = sprintf('Invalid snapshot: %s', $snapshot);
        parent::__construct($message, $code, $previous);
    }
}
