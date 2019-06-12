<?php

namespace SilverStripe\Snapshots;

use RuntimeException;
use Throwable;

class InvalidSnapshotException extends RuntimeException
{
    public function __construct($snapshot = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct(sprintf('Invalid snapshot: %s', $snapshot), $code, $previous);
    }
}
