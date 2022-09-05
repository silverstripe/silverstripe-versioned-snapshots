<?php

namespace SilverStripe\Snapshots\Migration;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotItem;
use TractorCow\Fluent\Extension\FluentExtension;

class RecalculateHashesTask extends BuildTask
{
    /**
     * @var string
     */
    private static $segment = 'recalculate-hashes-task';

    /**
     * @var string
     */
    protected $title = 'Recalculate OriginHash and ObjectHash';

    /**
     * @var string
     */
    protected $description = 'Recalculate all instances of OriginHash and ObjectHash (Fluent support included)';

    /**
     * @param HTTPRequest $request
     */
    public function run($request): void
    {
        if (class_exists(FluentExtension::class)
            && Snapshot::has_extension(FluentExtension::class)
            && SnapshotItem::has_extension(FluentExtension::class)) {
            echo 'Updating Snapshots (Localised)...' . PHP_EOL;
            $sql = 'UPDATE "VersionedSnapshot" '
                . 'INNER JOIN "VersionedSnapshot_Localised" ON "VersionedSnapshot"."ID" = "VersionedSnapshot_Localised"."RecordID" '
                . 'SET "VersionedSnapshot"."OriginHash" = MD5(CONCAT("VersionedSnapshot"."OriginClass", \':\', "VersionedSnapshot"."OriginID")), '
                . '"VersionedSnapshot_Localised"."OriginHash" = MD5(CONCAT("VersionedSnapshot"."OriginClass", \':\', "VersionedSnapshot"."OriginID"))';
            DB::query($sql);
            echo sprintf('Done, %d records updated.', DB::affected_rows()) . PHP_EOL;

            echo 'Updating Snapshot items...' . PHP_EOL;
            $sql = 'UPDATE "VersionedSnapshotItem" '
                . 'INNER JOIN "VersionedSnapshotItem_Localised" ON "VersionedSnapshotItem"."ID" = "VersionedSnapshotItem_Localised"."RecordID" '
                . 'SET "VersionedSnapshotItem"."ObjectHash" = MD5(CONCAT("VersionedSnapshotItem"."ObjectClass", \':\', "VersionedSnapshotItem"."ObjectID")), '
                . '"VersionedSnapshotItem_Localised"."ObjectHash" = MD5(CONCAT("VersionedSnapshotItem"."ObjectClass", \':\', "VersionedSnapshotItem"."ObjectID"))';
            DB::query($sql);
            echo sprintf('Done, %d records updated.', DB::affected_rows()) . PHP_EOL;

            return;
        }

        echo 'Updating Snapshots (Non localised)...' . PHP_EOL;
        $sql = 'UPDATE "VersionedSnapshot" SET "OriginHash" = MD5(CONCAT("OriginClass", \':\', "OriginID"))';
        DB::query($sql);
        echo sprintf('Done, %d records updated.', DB::affected_rows()) . PHP_EOL;

        echo 'Updating Snapshot items...' . PHP_EOL;
        $sql = 'UPDATE "VersionedSnapshotItem" SET "ObjectHash" = MD5(CONCAT("ObjectClass", \':\', "ObjectID"))';
        DB::query($sql);
        echo sprintf('Done, %d records updated.', DB::affected_rows()) . PHP_EOL;
    }
}
