<?php

namespace SilverStripe\Snapshots\Migration;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class MigrateObjectVersionTask extends BuildTask
{
    protected static string $commandName = 'migrate-object-version-task';

    protected string $title = 'Migrate legacy format of object version for SnapshotItem';

    protected static string $description = 'Migrate "Version" DB field of VersionedSnapshotItem table '
    . 'to "ObjectVersion", this task can be run multiple times';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $sql = <<<'SQL'
UPDATE "VersionedSnapshotItem" SET "ObjectVersion" = "Version" WHERE "ObjectVersion" = 0 AND "Version" > 0
SQL;
        DB::query($sql);
        echo sprintf('Done, %d records updated.', DB::affected_rows()) . PHP_EOL;

        return Command::SUCCESS;
    }
}
