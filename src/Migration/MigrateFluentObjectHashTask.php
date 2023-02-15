<?php

namespace SilverStripe\Snapshots\Migration;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class MigrateFluentObjectHashTask extends BuildTask
{
    /**
     * @var string
     */
    private static $segment = 'migrate-fluent-object-hash-task';

    /**
     * @var string
     */
    protected $title = 'Migrate legacy fluent object hash to the new format for SnapshotItem';

    /**
     * @var string
     */
    protected $description = 'Localise "ObjectHash" DB field of VersionedSnapshotItem table';

    /**
     * @param HTTPRequest $request
     */
    public function run($request): void
    {
        $sql = <<<EOT
INSERT INTO "VersionedSnapshotItem_Localised" ("RecordID", "Locale", "ObjectHash")
SELECT "VersionedSnapshotItem"."ID" as "RecordID", "VersionedSnapshot_Localised"."Locale" as "Locale", "VersionedSnapshotItem"."ObjectHash" as "ObjectHash"
FROM "VersionedSnapshot"
INNER JOIN "VersionedSnapshot_Localised" ON "VersionedSnapshot"."ID" = "VersionedSnapshot_Localised"."RecordID"
INNER JOIN "VersionedSnapshotItem" ON "VersionedSnapshot"."ID" = "VersionedSnapshotItem"."SnapshotID"
EOT;
        DB::query($sql);
        echo sprintf('Done, %d records created.', DB::affected_rows()) . PHP_EOL;
    }
}
