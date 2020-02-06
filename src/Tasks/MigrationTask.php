<?php


namespace SilverStripe\Snapshots\Tasks;


use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotItem;
use SilverStripe\Versioned\Versioned;

class MigrationTask extends BuildTask
{
    private static $segment = 'snapshot-migration';

    public function run($request)
    {
        $snapshotsTable = DataObject::getSchema()->baseDataTable(Snapshot::class);
        $itemsTable = DataObject::getSchema()->baseDataTable(SnapshotItem::class);
        // Clear out old snapshots

        echo "Deleting old snapshots\n";
        DB::query("DELETE FROM \"$snapshotsTable\"");
        DB::query("DELETE FROM \"$itemsTable\"");

        // Create a "temporary" table to look up ClassName => BaseClassName
        // This can't be a true temporary table because it needs to be accessed
        // multiple times in a single query
        echo "Creating temporary table...\n";
        DB::query("DROP TABLE IF EXISTS \"__ClassNameLookup\"");
        DB::create_table(
            '__ClassNameLookup',
            [
                'ObjectClassName' => 'varchar(255) not null',
                'BaseClassName' => 'varchar(255) not null',
            ]
        );

        $map = [];
        foreach (ClassInfo::subclassesFor(DataObject::class, false) as $class) {
            $map[str_replace('\\', '\\\\', $class)] = str_replace('\\', '\\\\', Injector::inst()->get($class)->baseClass());
        }
        $lines = [];
        foreach ($map as $className => $baseClassName) {
            $lines[] = sprintf("('%s', '%s')", $className, $baseClassName);
        }
        $values = implode(",\n", $lines);
        $query = <<<SQL
            INSERT INTO "__ClassNameLookup"
            ("ObjectClassName", "BaseClassName")
            VALUES
            $values
SQL;

        DB::query($query);

        $usedBaseTables = [];
        $baseID = 0;

        foreach (ClassInfo::subclassesFor(DataObject::class, false) as $class) {
            /* @var DataObject $sng */
            $sng = $class::singleton();
            if (!$sng->hasExtension(Versioned::class)) {
                continue;
            }

            $baseTable = $sng->baseTable();

            if (isset($usedBaseTables[$baseTable])) {
                echo "Table $baseTable has already been migrated. Skipping\n";
                continue;
            }

            $versionsTable = $baseTable . '_Versions';
            $usedBaseTables[$baseTable] = true;

            echo "**** Migrating $versionsTable\n";
            $baseClassSubquery = <<<SQL
                        (
                            SELECT "BaseClassName"
                                FROM "__ClassNameLookup"
                                WHERE "ObjectClassName" = "ClassName"
                                LIMIT 1
                        )
SQL;

            DB::query(
                "INSERT INTO \"$snapshotsTable\"
                (
                    \"ID\",
                    \"Created\",
                    \"LastEdited\",
                    \"OriginHash\",
                    \"Message\",
                    \"AuthorID\",
                    \"OriginID\",
                    \"OriginClass\"
                )
                (
                    SELECT
                        \"ID\" + $baseID,
                        \"Created\",
                        \"LastEdited\",
                        MD5(CONCAT($baseClassSubquery, ':', \"RecordID\")),
                        'Previous version',
                        \"AuthorID\",
                        \"RecordID\",
                        $baseClassSubquery
                    FROM
                        \"$versionsTable\"
                    ORDER BY \"ID\" ASC
                )
                ");
            DB::query(
                "INSERT INTO \"$itemsTable\"
                (
                    \"Created\",
                    \"LastEdited\",
                    \"Version\",
                    \"WasPublished\",
                    \"WasDraft\",
                    \"WasDeleted\",
                    \"ObjectHash\",
                    \"Modification\",
                    \"SnapshotID\",
                    \"ParentID\",
                    \"ObjectID\",
                    \"ObjectClass\"
                )
                (
                    SELECT
                        \"Created\",
                        \"LastEdited\",
                        \"Version\",
                        \"WasPublished\",
                        \"WasDraft\",
                        \"WasDeleted\",
                        MD5(CONCAT($baseClassSubquery, ':', \"RecordID\")),
                        1,
                        \"ID\" + $baseID,
                        0,
                        \"RecordID\",
                        $baseClassSubquery
                    FROM
                        \"$versionsTable\"
                    ORDER BY \"ID\" ASC
                )
                ");

            $rows = DB::affected_rows();
            echo "Added $rows new snapshots\n";
            $baseID = DB::query("SELECT MAX(ID) FROM \"$snapshotsTable\"")->value();
        }
        echo "Deleting temporary table...\n";
        DB::query("DROP TABLE \"__ClassNameLookup\"");
        echo "Done.";
    }
}
