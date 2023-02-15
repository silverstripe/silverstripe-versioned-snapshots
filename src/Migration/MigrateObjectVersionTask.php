<?php

namespace SilverStripe\Snapshots\Migration;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class MigrateObjectVersionTask extends BuildTask
{
    /**
     * @var string
     */
    private static $segment = 'migrate-object-version-task';

    /**
     * @var string
     */
    protected $title = 'Migrate legacy format of object version for SnapshotItem';

    /**
     * @var string
     */
    protected $description = 'Migrate "Version" DB field of VersionedSnapshotItem table to "ObjectVersion"'
    . ', this task can be run multiple times';

    /**
     * @param HTTPRequest $request
     */
    public function run($request): void
    {
        $sql = 'UPDATE "VersionedSnapshotItem" SET "ObjectVersion" = "Version" WHERE "ObjectVersion" = 0 AND "Version" > 0';
        DB::query($sql);
        echo sprintf('Done, %d records updated.', DB::affected_rows()) . PHP_EOL;
    }
}
