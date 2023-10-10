<?php

namespace SilverStripe\Snapshots\Tests;

use Exception;
use Page;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Snapshots\Snapshot;
use SilverStripe\Snapshots\SnapshotPublishable;

class IncompleteDataTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'IncompleteDataTest.yml';

    /**
     * @return void
     * @throws Exception
     */
    protected function setUp(): void
    {
        DBDatetime::set_mock_now('2020-01-01 00:00:00');

        parent::setUp();
    }

    /**
     * @return void
     * @throws ValidationException
     * @throws Exception
     */
    public function testGetActivityFeed(): void
    {
        /** @var Page|SnapshotPublishable $page1 */
        $page1 = $this->objFromFixture(Page::class, 'page1');

        /** @var Page|SnapshotPublishable $page2 */
        $page2 = $this->objFromFixture(Page::class, 'page2');

        $actions = [
            '2020-01-02 00:00:00' => [
                $page1,
                [
                    $page2,
                ],
            ],
            '2020-01-03 00:00:00' => [
                $page2,
                [
                    $page1,
                ],
            ],
            '2020-01-04 00:00:00' => [
                $page1,
                [
                    $page2,
                ],
            ],
        ];

        // Create some mock actions
        foreach ($actions as $time => $data) {
            [$origin, $models] = $data;
            DBDatetime::set_mock_now($time);
            $snapshot = Snapshot::singleton()->createSnapshot($origin, $models);
            $snapshot->write();
        }

        // Remove all traces of page 2 to create an incomplete data set
        $page2->doArchive();
        $query = SQLDelete::create(
            '"SiteTree_Versions"',
            [
                '"RecordID"' => $page2->ID,
            ]
        );
        $query->execute();

        $this->assertCount(2, $page1->getActivityFeed(), 'We expect only entries which have complete data');
    }
}
