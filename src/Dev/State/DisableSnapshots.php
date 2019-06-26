<?php

namespace SilverStripe\Snapshots\Dev\State;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\State\TestState;
use SilverStripe\Snapshots\SnapshotPublishable;

class DisableSnapshots implements TestState
{

    /**
     * Called on setup
     *
     * @param SapphireTest $test
     */
    public function setUp(SapphireTest $test)
    {
        // Skip tests in this modules' namespace
        if (strpos(get_class($test), 'SilverStripe\\Snapshots\\') === 0) {
            return;
        }

        SnapshotPublishable::pause();
    }

    /**
     * Called on tear down
     *
     * @param SapphireTest $test
     */
    public function tearDown(SapphireTest $test)
    {
        SnapshotPublishable::resume();
    }

    /**
     * Called once on setup
     *
     * @param string $class Class being setup
     */
    public function setUpOnce($class)
    {
        // noop
    }

    /**
     * Called once on tear down
     *
     * @param string $class Class being torn down
     */
    public function tearDownOnce($class)
    {
        // noop
    }
}
