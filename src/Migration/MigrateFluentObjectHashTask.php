<?php

namespace SilverStripe\Snapshots\Migration;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class MigrateFluentObjectHashTask extends BuildTask
{
    protected static string $commandName = 'migrate-fluent-object-hash-task';

    protected string $title = 'Migrate legacy fluent object hash to the new format for SnapshotItem';

    protected static string $description = 'Localise "ObjectHash" DB field of VersionedSnapshotItem table';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $sql = <<<'SQL'
INSERT INTO "VersionedSnapshotItem_Localised" ("RecordID", "Locale", "ObjectHash")
SELECT "VersionedSnapshotItem"."ID" as "RecordID", "VersionedSnapshot_Localised"."Locale" as "Locale",
       "VersionedSnapshotItem"."ObjectHash" as "ObjectHash"
FROM "VersionedSnapshot"
INNER JOIN "VersionedSnapshot_Localised" ON "VersionedSnapshot"."ID" = "VersionedSnapshot_Localised"."RecordID"
INNER JOIN "VersionedSnapshotItem" ON "VersionedSnapshot"."ID" = "VersionedSnapshotItem"."SnapshotID"
SQL;
        DB::query($sql);
        echo sprintf('Done, %d records created.', DB::affected_rows()) . PHP_EOL;

        return Command::SUCCESS;
    }
}
