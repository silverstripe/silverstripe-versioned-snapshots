<?php

namespace SilverStripe\Snapshots\Dev\State;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\State\TestState;
use SilverStripe\Snapshots\SnapshotPublishable;

/**
 * Disable snapshots in tests by default. Snapshots will analyze a relationship tree for objects when they are saved but
 * fixtures will not necessarily scaffold all required tables for this when the test state is scaffolded.
 *
 * Tests that rely on snapshot functionality should explicitly opt-in to snapshots by calling
 * `SnapshotPublishable::resume`.
 */
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
