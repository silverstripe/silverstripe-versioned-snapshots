<?php


namespace SilverStripe\Snapshots;


use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Versioned\Versioned;

class SnapshotVersioned extends Versioned
{
    /**
     * @param SQLSelect $query
     * @param DataQuery|null $dataQuery
     */
    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        if (!$dataQuery->getQueryParam('Snapshot')) {
            return parent::augmentSQL($query, $dataQuery);
        }

        $baseTable = $this->baseTable();
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);
        $lower = $dataQuery->getQueryParam('Snapshot.lower');
        $upper = $dataQuery->getQueryParam('Snapshot.upper');
        $this->augmentSQLVersioned($query);
        $query->addInnerJoin(
            <<<SQL
            (
              SELECT 
                MAX("$itemTable"."Version") AS "MaxVersion",
                "$itemTable"."ObjectID" AS "ItemObjectID", 
                "$itemTable"."ObjectClass" AS "ItemObjectClass"
              FROM "$itemTable"
              WHERE 
                ("$itemTable"."SnapshotID" BETWEEN ? AND ?)
                AND 
                ("$itemTable"."ObjectClass" = ?)
              GROUP BY "$itemTable"."ObjectHash"
              ORDER BY "$itemTable"."Created"  
            ) 
SQL
            ,
            <<<SQL
            "MaxVersion" = "$baseTable"."Version"
            AND "ItemObjectClass" = "$baseTable"."ClassName" 
            AND "ItemObjectID" = "$baseTable"."ID"
SQL
            ,
            "{$baseTable}_Snapshots_Latest",
            20,
            [
                $lower,
                $upper,
                $this->owner->baseClass(),
            ]
        );
    }
}