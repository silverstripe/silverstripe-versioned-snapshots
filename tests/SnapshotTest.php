<?php


namespace SilverStripe\Snapshots\Tests;

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Snapshots\ActivityEntry;
use SilverStripe\Snapshots\SnapshotPublishable;
use SilverStripe\Snapshots\SnapshotVersioned;
use SilverStripe\Snapshots\Tests\SnapshotTest\Block;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTest\Gallery;
use SilverStripe\Snapshots\Tests\SnapshotTest\GalleryImage;
use SilverStripe\Snapshots\Tests\SnapshotTest\GalleryImageJoin;
use SilverStripe\Versioned\ChangeSetItem;
use SilverStripe\Versioned\Versioned;
use DateTime;

class SnapshotTest extends FunctionalTest
{

    protected $usesDatabase = true;

    protected $usesTransactions = false;

    protected static $extra_dataobjects = [
        BlockPage::class,
        Block::class,
        Gallery::class,
        GalleryImage::class,
        GalleryImageJoin::class,
        ChangeSetItem::class,
    ];

    public function testSnapshotFundamentals()
    {
        // Model:
        // BlockPage
        //  -> (has_many/owns) -> Blocks
        //      -> (has_many/owns) -> Gallery
        //          -> (many_many/owns) -> GalleryImage

        /* @var DataObject|SnapshotPublishable $a1 */
        $a1 = new BlockPage(['Title' => 'A1 Block Page']);
        $a1->write();
        $a1->publishRecursive();

        /* @var DataObject|SnapshotPublishable $a2 */
        $a2 = new BlockPage(['Title' => 'A2 Block Page']);
        $a2->write();
        $a2->publishRecursive();

        /* @var DataObject|SnapshotPublishable $a1Block1 */
        $a1Block1 = new Block(['Title' => 'Block 1 on A1', 'ParentID' => $a1->ID]);
        $a1Block1->write();
        $a1Block2 = new Block(['Title' => 'Block 2 on A1', 'ParentID' => $a1->ID]);
        $a1Block2->write();

        // A1
        //   block1 (draft, new) *
        //   block2 (draft, new) *

        /* @var DataObject|SnapshotPublishable $a2Block1 */
        $a2Block1 = new Block(['Title' => 'Block 1 on A2', 'ParentID' => $a2->ID]);
        $a2Block1->write();

        // A1
        //   block1 (draft, new)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new) *

        $a1->Title = 'A1 Block Page -- changed';
        $a1->write();

        // A1 (draft, modified) *
        //   block1 (draft, new)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        $a1Block1->Title = 'Block 1 on A1 -- changed';
        $a1Block1->write();

        // A1 (draft, modified)
        //   block1 (draft, modified) *
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        // A1 will publish its two blocks
        $this->assertTrue($a1->hasOwnedModifications());

        // Since publishing:
        //  two new blocks created
        //  one of those was then modified.
        $activity = $a1->getActivityFeed();
        $this->assertCount(4, $activity);
        $this->assertCount(3, $a1->getPublishableObjects());
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, ActivityEntry::CREATED],
                [$a1Block2, ActivityEntry::CREATED],
                [$a1, ActivityEntry::MODIFIED],
                [$a1Block1, ActivityEntry::MODIFIED],
            ]
        );

        $this->assertPublishableObjectsContains(
            $a1->getPublishableObjects(),
            [
                $a1Block1,
                $a1Block2,
                $a1
            ]
        );

        // Testing third level
        /* @var DataObject|SnapshotPublishable|Versioned $gallery1 */
        $gallery1 = new Gallery(['Title' => 'Gallery 1 on Block 1 on A1', 'BlockID' => $a1Block1->ID]);
        $gallery1->write();

        // A1 (draft, modified)
        //   block1 (draft, modified)
        //       gallery1 (draft, new) *
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        // A1 will now publish two blocks and a gallery
        $this->assertTrue($a1->hasOwnedModifications());

        // Since last publish:
        //  two blocks were created
        //  one block was modified
        //  one gallery created.
        $activity = $a1->getActivityFeed();
        $this->assertCount(5, $activity);
        $this->assertCount(4, $a1->getPublishableObjects());
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, ActivityEntry::CREATED],
                [$a1Block2, ActivityEntry::CREATED],
                [$a1, ActivityEntry::MODIFIED],
                [$a1Block1, ActivityEntry::MODIFIED],
                [$gallery1, ActivityEntry::CREATED],
            ]
        );

        $this->assertPublishableObjectsContains(
            $a1->getPublishableObjects(),
            [
                $a1Block1,
                $a1Block2,
                $a1,
                $gallery1,
            ]
        );

        $gallery1->Title = 'Gallery 1 on Block 1 on A1 -- changed';
        $gallery1->write();

        // A1 (draft, modified)
        //   block1 (draft, modified)
        //       gallery1 (draft, modified) *
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        // A1 will still publish two blocks and a gallery
        $this->assertTrue($a1->hasOwnedModifications());

        // Since last publish:
        //  two blocks were created
        //  one block was modified
        //  one gallery created
        //  one gallery was modified
        $activity = $a1->getActivityFeed();
        $this->assertCount(6, $activity);
        $this->assertCount(4, $a1->getPublishableObjects());
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, ActivityEntry::CREATED],
                [$a1Block2, ActivityEntry::CREATED],
                [$a1, ActivityEntry::MODIFIED],
                [$a1Block1, ActivityEntry::MODIFIED],
                [$gallery1, ActivityEntry::CREATED],
                [$gallery1, ActivityEntry::MODIFIED],
            ]
        );

        $this->assertPublishableObjectsContains(
            $a1->getPublishableObjects(),
            [
                $a1Block1,
                $a1Block2,
                $a1,
                $gallery1,
            ]
        );

        // TODO: fix many_many:
        //  - image does not find its owners
        //  - join does not find its owners
        //  - gallery does not find its owners

        // Testing many_many
        /* @var DataObject|SnapshotPublishable $galleryItem1 */
        $galleryItem1 = new GalleryImage(['URL' => '/gallery/image/1']);
        /* @var DataObject|SnapshotPublishable $galleryItem2 */
        $galleryItem2 = new GalleryImage(['URL' => '/gallery/image/2']);

        $gallery1->Images()->add($galleryItem1);
        $gallery1->Images()->add($galleryItem2);

        // A1 (draft, modified)
        //   block1 (draft, modified)
        //       gallery1 (draft, new)
        //          image1 (draft, new, added) *
        //          image2 (draft, new, added) *
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        $activity = $a1->getActivityFeed();
        $this->assertCount(8, $activity);
        $this->assertCount(5, $a1->getPublishableObjects());
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, ActivityEntry::CREATED],
                [$a1Block2, ActivityEntry::CREATED],
                [$a1, ActivityEntry::MODIFIED],
                [$a1Block1, ActivityEntry::MODIFIED],
                [$gallery1, ActivityEntry::CREATED],
                [$gallery1, ActivityEntry::MODIFIED],
                [$galleryItem1, ActivityEntry::ADDED, $gallery1],
                [$galleryItem2, ActivityEntry::ADDED, $gallery1],
            ]
        );

        $this->assertPublishableObjectsContains(
            $a1->getPublishableObjects(),
            [
                $a1Block1,
                $a1Block2,
                $gallery1,
                $galleryItem1,
                $galleryItem2,
            ]
        );

        /* @var DataObject|SnapshotPublishable $gallery1a */
        $gallery1a = new Gallery(['Title' => 'Gallery 1 on Block 1 on A2', 'BlockID' => $a2Block1->ID]);
        $gallery1a->write();
        $gallery1a->Images()->add($galleryItem1);

        // A1 (draft, modified)
        //   block1 (draft, modified)
        //       gallery1 (draft, new)
        //          image1 (draft, new, added)
        //          image2 (draft, new, added)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)
        //       gallery1a (draft, new) *
        //          image1 (draft, new, added) *

        $this->assertTrue($a2->hasOwnedModifications());

        $activity = $a2->getActivityFeed();
        $this->assertCount(3, $activity);
        $this->assertCount(3, $a2->getPublishableObjects());
        $this->assertActivityContains(
            $activity,
            [
                [$a2Block1, ActivityEntry::CREATED],
                [$gallery1a, ActivityEntry::CREATED],
                [$galleryItem1, ActivityEntry::ADDED, $gallery1a],
            ]
        );

        $this->assertPublishableObjectsContains(
            $a2->getPublishableObjects(),
            [
                $a2Block1,
                $gallery1a,
                $galleryItem1,
            ]
        );

        $galleryItem1->URL = '/changed/url';
        $galleryItem1->write();

        // A1 (draft, modified)
        //   block1 (draft, modified)
        //       gallery1 (draft, new)
        //          image1 (draft, modified, added) *
        //          image2 (draft, new, added)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)
        //       gallery1a (draft, new)
        //          image1 (draft, modified, added) *
        $this->assertTrue($a2->hasOwnedModifications());

        $activity = $a2->getActivityFeed();
        $this->assertCount(4, $activity);
        $this->assertCount(3, $a2->getPublishableObjects());
        $this->assertActivityContains(
            $activity,
            [
                [$a2Block1, ActivityEntry::CREATED],
                [$gallery1a, ActivityEntry::CREATED],
                [$galleryItem1, ActivityEntry::ADDED, $gallery1a],
                [$galleryItem1, ActivityEntry::MODIFIED],
            ]
        );

        $this->assertPublishableObjectsContains(
            $a2->getPublishableObjects(),
            [
                $a2Block1,
                $gallery1a,
                $galleryItem1,
            ]
        );

        $this->assertTrue($a1->hasOwnedModifications());

        $activity = $a1->getActivityFeed();
        $this->assertCount(8, $activity);
        $this->assertCount(5, $a1->getPublishableObjects());
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, ActivityEntry::CREATED],
                [$a1Block2, ActivityEntry::CREATED],
                [$a1Block1, ActivityEntry::MODIFIED],
                [$gallery1, ActivityEntry::CREATED],
                [$gallery1, ActivityEntry::MODIFIED],
                [$galleryItem1, ActivityEntry::ADDED, $gallery1],
                [$galleryItem2, ActivityEntry::ADDED, $gallery1],
                [$galleryItem1, ActivityEntry::MODIFIED],
            ]
        );
        $this->assertPublishableObjectsContains(
            $a1->getPublishableObjects(),
            [
                $a1Block1,
                $a1Block2,
                $gallery1,
                $galleryItem1,
                $galleryItem2,
            ]
        );

        // Publish, and clear the slate
        $a1->publishRecursive();

        // A1 (published) *
        //   block1 (published) *
        //       gallery1 (published) *
        //          image1 (published) *
        //          image2 (published) *
        //   block2 (published) *
        // A2
        //   block1 (draft, new)
        //       gallery1a (draft, new)
        //          image1 (added) *

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertTrue($a2->hasOwnedModifications());

        $a2->publishRecursive();

        // A1 (published)
        //   block1 (published)
        //       gallery1 (published)
        //          image1 (published)
        //          image2 (published)
        //   block2 (published)
        // A2
        //   block1 (published) *
        //       gallery1a (published) *
        //          image1 (published) *

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $this->assertEmpty($a1->getActivityFeed());
        $this->assertEmpty($a2->getActivityFeed());

        $this->assertEmpty($a1->getPublishableObjects());
        $this->assertEmpty($a2->getPublishableObjects());
    }

    public function testRevertChanges()
    {
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $gallery1 */
        list ($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();

        $gallery1->Title = 'Gallery 1 is changed';
        $gallery1->write();

        // A1 (published)
        //   block1 (published)
        //       gallery1 (draft, modified) *
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)

        $this->assertTrue($a1->hasOwnedModifications());

        $activity = $a1->getActivityFeed();
        $this->assertCount(1, $activity);
        $this->assertActivityContains($activity, [
            [$gallery1, ActivityEntry::MODIFIED],
        ]);

        $gallery1->doRevertToLive();

        // A1 (published)
        //   block1 (published)
        //       gallery1 (reverted to live) *
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)
        $this->assertEmpty($a1->getActivityFeed());
    }

    public function testIntermediaryObjects()
    {
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a2 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1Block1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $gallery1 */
        list ($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();

        $gallery1->Title = 'Gallery 1 changed';
        $gallery1->write();

        // A1 (published)
        //   block1 (published)
        //       gallery1 (draft, modified) *
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)

        $this->assertTrue($a1Block1->hasOwnedModifications());

        $a1->Title = 'A1 changed';
        $a1->write();

        // A1 (draft, modified) *
        //   block1 (published)
        //       gallery1 (draft, modified)
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)


        // Publish the intermediary block
        $a1Block1->publishRecursive();

        // A1 (draft, modified)
        //   block1 (published)
        //       gallery1 (published) *
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)

        // Block no longer has modified state
        $this->assertFalse($a1Block1->hasOwnedModifications());
        // Nor does the gallery
        $this->assertFalse($gallery1->hasOwnedModifications());
        // Changed block page still does
        $this->assertTrue($a1->hasOwnedModifications());

        $a1->publishRecursive();

        // A1 (published) *
        //   block1 (published)
        //       gallery1 (published)
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)

        $this->assertFalse($a1->hasOwnedModifications());

        $a1Block1->Title = "A1 is changed again";
        $a1Block1->write();

        // A1 (draft, modified) *
        //   block1 (published)
        //       gallery1 (published)
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)
        $this->assertTrue($a1->hasOwnedModifications());
    }

    public function testChangeOwnershipStructure()
    {
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a2 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1Block1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $gallery1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $gallery2 */
        list ($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $a1Block1->Title = 'Block 1 changed';
        $a1Block1->write();

        // A1 (published)
        //   block1 (draft, modified) *
        //       gallery1 (published)
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)

        $this->assertTrue($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $a1Block1->ParentID = $a2->ID;
        $a1Block1->write();
        $blockMoved = $a1Block1;

        // A1 (published)
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)
        //   block1-moved-from-A1 (draft, modified) *
        //       gallery1 (published)

        // Change of ownership. A1 no longer has modifications, but A2 does.
        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertTrue($a2->hasOwnedModifications());

        // A1 doesn't have activity anymore.
        $activity = $a1->getActivityFeed();
        $this->assertEmpty($activity);

        // A2 has a modified block.
        // One modification for the local change, and one for the
        // change of ownership (foreign key)
        $activity = $a2->getActivityFeed();
        $this->assertActivityContains($activity, [
            [$blockMoved, ActivityEntry::MODIFIED],
            [$blockMoved, ActivityEntry::MODIFIED],
        ]);

        // This should do nothing. A1 has nothing publishable anymore.
        $a1->publishRecursive();

        // A1 (published)
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)
        //   block1-moved-from-A1 (draft, modified)
        //       gallery1 (published)

        $this->assertEmpty($a1->getActivityFeed());

        // A2 still has the two modification entries listed above
        $activity = $a2->getActivityFeed();
        $this->assertActivityContains($activity, [
            [$blockMoved, ActivityEntry::MODIFIED],
            [$blockMoved, ActivityEntry::MODIFIED],
        ]);

        $a2->publishRecursive();

        // A1 (published)
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)
        //   block1-moved-from-A1 (published) *
        //       gallery1 (published)

        $this->assertEmpty($a2->getActivityFeed());
        $this->assertFalse($a2->hasOwnedModifications());

        $blockMoved->Title = "The moved block is modified";
        $blockMoved->write();

        $gallery1->Title = "The gallery that belongs to the moved block is modified";
        $gallery1->write();

        $item = new GalleryImage(['URL' => '/belongs/to/moved/block']);
        $item->write();

        $gallery1->Images()->add($item);

        // A1 (published)
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)
        //   block1-moved-from-A1 (draft, modified) *
        //       gallery1 (draft, modified) *
        //          image (draft, new, added) *


        $this->assertTrue($a2->hasOwnedModifications());
        $this->assertFalse($a1->hasOwnedModifications());

        $activity = $a2->getActivityFeed();
        $this->assertCount(3, $activity);
        $this->assertActivityContains($activity, [
            [$blockMoved, ActivityEntry::MODIFIED],
            [$gallery1, ActivityEntry::MODIFIED],
            [$item, ActivityEntry::ADDED, $gallery1],
        ]);

        // Move the block back to A1
        // Refresh the block so that changed fields flushes
        $blockMoved = DataObject::get_by_id(Block::class, $blockMoved->ID, false);
        $blockMoved->ParentID = $a1->ID;
        $blockMoved->write();

        // A1 (published)
        //   block1-moved-back-to-A1 (draft, modified) *
        //       gallery1 (draft, modified) *
        //          image (draft, new, added) *
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)

        $this->assertTrue($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $this->assertEmpty($a2->getActivityFeed());

        $activity = $a1->getActivityFeed();
        $this->assertCount(4, $activity);

        // Moved block has a modification for the local change,
        // and also one for the ownership change (foreign key)
        $this->assertActivityContains($activity, [
            [$blockMoved, ActivityEntry::MODIFIED],
            [$gallery1, ActivityEntry::MODIFIED],
            [$item, ActivityEntry::ADDED, $gallery1],
            [$blockMoved, ActivityEntry::MODIFIED],
        ]);

        $a2->publishRecursive();
        $a1->publishRecursive();

        // A1 (published)
        //   block1-moved-back-to-A1 (published) *
        //       gallery1 (published) *
        //          image (published) *
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());
        $this->assertEmpty($a1->getActivityFeed());
        $this->assertEmpty($a2->getActivityFeed());

        // Move a many_many
        $gallery1->Images()->remove($item);
        $gallery2->Images()->add($item);

        $item->URL = '/new/url';
        $item->write();

        // A1 (published)
        //   block1-moved-back-to-A1 (published)
        //       gallery1 (published)
        //          image1 (removed) --------------->
        //   block2 (published)                     |
        // A2 (published)                           |
        //   block1 (published)                     |
        //       gallery1a (published)              |
        //          image1 (added, modified) <------|

        $activity = $a1->getActivityFeed();
        $this->assertCount(1, $activity);
        $this->assertActivityContains($activity, [
            [$item, ActivityEntry::REMOVED, $gallery1],
        ]);

        $activity = $a2->getActivityFeed();
        $this->assertCount(2, $activity);
        $this->assertActivityContains($activity, [
            [$item, ActivityEntry::ADDED, $gallery2],
            [$item, ActivityEntry::MODIFIED],
        ]);
    }

    public function testPartialActivityMigration()
    {
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a2 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1Block1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1Block2 */
        list ($a1, $a2, $a1Block1, $a1Block2) = $this->buildState();

        // Test that we can transplant a node and relevant activity will be migrated
        // but unrelated activity will be preserved.
        $a1Block1->Title = 'Take one for the team';
        $a1Block1->write();

        $a1Block2->Title = 'You got this';
        $a1Block2->write();

        $gallery = new Gallery(['Title' => 'A new gallery for block 2', 'BlockID' => $a1Block2->ID]);
        $gallery->write();

        // A1 (published)
        //   block1 (draft, modified) *
        //       gallery1 (published)
        //   block2 (draft, modified) *
        //       gallery2 (draft, new) *
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)

        $activity = $a1->getActivityFeed();
        $this->assertCount(3, $activity);
        $this->assertActivityContains($activity, [
            [$a1Block1, ActivityEntry::MODIFIED],
            [$a1Block2, ActivityEntry::MODIFIED],
            [$gallery, ActivityEntry::CREATED],
        ]);

        // Move one modified block, but leave the other.
        $a1Block2->ParentID = $a2->ID;
        $a1Block2->write();

        // A1 (published)
        //   block1 (draft, modified) *
        //       gallery1 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)
        //   block2-moved-from-A1 (draft, modified) *
        //       gallery2 (draft, new) *

        // Now A1 only shows activity for the local change on block 1
        $activity = $a1->getActivityFeed();
        $this->assertCount(1, $activity);
        $this->assertActivityContains($activity, [
            [$a1Block1, ActivityEntry::MODIFIED],
        ]);

        // And the other activity is now on A2
        $activity = $a2->getActivityFeed();
        $this->assertCount(3, $activity);
        $this->assertActivityContains($activity, [
            [$a1Block2, ActivityEntry::MODIFIED],
            [$gallery, ActivityEntry::CREATED],
            [$a1Block2, ActivityEntry::MODIFIED], // <--- the migration
        ]);
    }

    public function testDeletions()
    {
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a2 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1Block1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1Block2 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a2Block1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $gallery2 */
        list ($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $a1Block1->delete();

        // A1 (published)
        //   block1 (deleted) *
        //       gallery1 (published)
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)

        $activity = $a1->getActivityFeed();
        $this->assertCount(1, $activity);
        $this->assertActivityContains($activity, [
            [$a1Block1, ActivityEntry::DELETED],
        ]);

        $a2Block1->Title = 'Change change change';
        $a2Block1->write();

        $gallery2->Title = 'Changey McChangerson';
        $gallery2->write();

        // A1 (published)
        //   block1 (deleted)
        //       gallery1 (published)
        //   block2 (published)
        // A2 (published)
        //   block1 (draft, modified) *
        //       gallery1a (draft, modified) *

        $activity = $a2->getActivityFeed();
        $this->assertCount(2, $activity);
        $this->assertActivityContains($activity, [
            [$a2Block1, ActivityEntry::MODIFIED],
            [$gallery2, ActivityEntry::MODIFIED],
        ]);

        $a2Block1->delete();
        // A1 (published)
        //   block1 (deleted)
        //       gallery1 (published)
        //   block2 (published)
        // A2 (published)
        //   block1 (deleted) *
        //       gallery1a (draft, modified)

        $activity = $a2->getActivityFeed();
        $this->assertCount(3, $activity);
        $this->assertActivityContains($activity, [
            [$a2Block1, ActivityEntry::MODIFIED],
            [$gallery2, ActivityEntry::MODIFIED],
            [$a2Block1, ActivityEntry::DELETED],
        ]);
    }

    public function testGetAtSnapshot()
    {
        $stamp0 = $this->sleep(1);

        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a2 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1Block1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1Block2 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a2Block1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $gallery2 */
        list ($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();

        $this->assertCount(2, $a1->Blocks());
        $this->assertCount(1, $a2->Blocks());

        $this->assertCount(1, $a1Block1->Galleries());
        $this->assertCount(1, $a2Block1->Galleries());

        $stamp1 = $this->sleep(1);

        $a1Block1->Title = 'A1 Block 1 changed';
        $a1Block1->write();

        $stamp2 = $this->sleep(1);

        $a1Block2->Title = 'A1 Block 2 changed';
        $a1Block2->write();

        $stamp3 = $this->sleep(1);

        $a1Block1->Title = 'A1 Block 1 changed again';
        $a1Block1->write();

        $stamp4 = $this->sleep(1);

        $gallery1->Title = 'new-gallery title';
        $gallery1->write();

        $stamp5 = $this->sleep(1);

        $a2Block2 = new Block([
            'Title' => 'Block 2 on A2',
            'ParentID' => $a2->ID,
        ]);

        $a2Block2->write();

        $stamp6 = $this->sleep(1);

        $a2Block1->Title = 'A2 Block 1 changed';
        $a2Block1->write();

        $stamp7 = $this->sleep(1);
        $a2->Title = 'The new A2';
        $a2->write();

        // Sanity check the activity
        $this->assertCount(4, $a1->getActivityFeed());
        $this->assertCount(3, $a2->getActivityFeed());

        // Get A1 from its first title change
        $this->assertEquals('A1 Block 1 changed again', $a1Block1->Title);
        $oldA1Block1 = $a1Block1->getAtSnapshot($stamp2);
        $this->assertEquals('A1 Block 1 changed', $oldA1Block1->Title);

        // Check related objects
        $a1Blocks = $a1->Blocks()->sort('Created ASC, ID ASC');
        $this->assertEquals('new-gallery title', $a1Blocks->first()->Galleries()->first()->Title);

        $oldA1 = $a1->getAtSnapshot($stamp1);
        $oldA1Blocks = $oldA1->Blocks()->sort('Created ASC, ID ASC');
        $this->assertCount(2, $oldA1Blocks);
        $this->assertCount(1, $oldA1Blocks->first()->Galleries());
        $this->assertEquals('Gallery 1 on Block 1 on A1', $oldA1Blocks->first()->Galleries()->first()->Title);

        $oldA1 = $a1->getAtSnapshot($stamp1);
        $oldA1Blocks = $oldA1->Blocks()->sort('Created ASC, ID ASC');
        $this->assertCount(2, $oldA1Blocks);
        $this->assertEquals('A1 Block 1 changed', $oldA1Blocks->first()->Title);
        $this->assertEquals('Block 2 on A1', $oldA1Blocks->last()->Title);
        $this->assertCount(1, $oldA1Blocks->first()->Galleries());
        $this->assertEquals('Gallery 1 on Block 1 on A1', $oldA1Blocks->first()->Galleries()->first()->Title);

        $oldA1 = $a1->getAtSnapshot($stamp2);
        $oldA1Blocks = $oldA1->Blocks()->sort('Created ASC, ID ASC');
        $this->assertCount(2, $oldA1Blocks);
        $this->assertEquals('A1 Block 1 changed', $oldA1Blocks->first()->Title);
        $this->assertEquals('A1 Block 2 changed', $oldA1Blocks->last()->Title);
        $this->assertCount(1, $oldA1Blocks->first()->Galleries());
        $this->assertEquals('Gallery 1 on Block 1 on A1', $oldA1Blocks->first()->Galleries()->first()->Title);

        $oldA1 = $a1->getAtSnapshot($stamp3);
        $oldA1Blocks = $oldA1->Blocks()->sort('Created ASC, ID ASC');
        $this->assertCount(2, $oldA1Blocks);
        $this->assertEquals('A1 Block 1 changed again', $oldA1Blocks->first()->Title);
        $this->assertEquals('A1 Block 2 changed', $oldA1Blocks->last()->Title);
        $this->assertCount(1, $oldA1Blocks->first()->Galleries());
        $this->assertEquals('Gallery 1 on Block 1 on A1', $oldA1Blocks->first()->Galleries()->first()->Title);

        $oldA1 = $a1->getAtSnapshot($stamp4);
        $oldA1Blocks = $oldA1->Blocks()->sort('Created ASC, ID ASC');
        $this->assertCount(2, $oldA1Blocks);
        $this->assertEquals('A1 Block 1 changed again', $oldA1Blocks->first()->Title);
        $this->assertEquals('A1 Block 2 changed', $oldA1Blocks->last()->Title);
        $this->assertCount(1, $oldA1Blocks->first()->Galleries());
        $this->assertEquals('new-gallery title', $oldA1Blocks->first()->Galleries()->first()->Title);

        // Get A2 before its title was changed
        $this->assertEquals('The new A2', $a2->Title);
        $oldA2 = $a2->getAtSnapshot($stamp5);
        $this->assertEquals('A2 Block Page', $oldA2->Title);

        // Get A2 before its second block was added
        $this->assertCount(2, $a2->Blocks());
        $oldA2 = $a2->getAtSnapshot($stamp4);
        $this->assertCount(1, $oldA2->Blocks());


    }

    public function testRollbackSnapshot()
    {
        $stamp0 = $this->sleep(1);

        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a2 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1Block1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a1Block2 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $a2Block1 */
        /* @var DataObject|SnapshotVersioned|SnapshotPublishable $gallery2 */
        list ($a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2) = $this->buildState();

        $this->assertCount(2, $a1->Blocks());
        $this->assertCount(1, $a2->Blocks());

        $this->assertCount(1, $a1Block1->Galleries());
        $this->assertCount(1, $a2Block1->Galleries());

        $stamp1 = $this->sleep(1);

        $a1Block1->Title = 'A1 Block 1 changed';
        $a1Block1->write();

        $stamp2 = $this->sleep(1);

        $a1Block2->Title = 'A1 Block 2 changed';
        $a1Block2->write();

        $stamp3 = $this->sleep(1);

        $a1Block1->Title = 'A1 Block 1 changed again';
        $a1Block1->write();

        $stamp4 = $this->sleep(1);

        $gallery1->Title = 'new-gallery title';
        $gallery1->write();

        $stamp5 = $this->sleep(1);

        $a2Block2 = new Block([
            'Title' => 'Block 2 on A2',
            'ParentID' => $a2->ID,
        ]);

        $a2Block2->write();

        $stamp6 = $this->sleep(1);

        $a2Block1->Title = 'A2 Block 1 changed';
        $a2Block1->write();

        $stamp7 = $this->sleep(1);
        $a2->Title = 'The new A2';
        $a2->write();

        $stamp8 = $this->sleep(1);

        $beforeRolledBackA1Block1 = $a1->Blocks()->sort('Created ASC, ID ASC')->first();
        $this->assertEquals('A1 Block 1 changed again', $beforeRolledBackA1Block1->Title);
        $this->assertEquals('new-gallery title', $beforeRolledBackA1Block1->Galleries()->first()->Title);

        $a1 = $a1->doRollbackToSnapshot($stamp2);

        $rolledBackA1Block1 = $a1->Blocks()->sort('Created ASC, ID ASC')->first();
        $this->assertEquals('A1 Block 1 changed', $rolledBackA1Block1->Title);
        $this->assertEquals('Gallery 1 on Block 1 on A1', $rolledBackA1Block1->Galleries()->first()->Title);
        $this->assertCount(2, $a2->Blocks());
        $a2 = $a2->doRollbackToSnapshot($stamp2);
        $this->assertCount(1, $a2->Blocks());

        $a2 = $a2->getAtVersion(Versioned::LIVE);
        $this->assertCount(1, $a2->Blocks());

        $a2->publishRecursive();

        // Still has only one block because the draft stage was a rolled back snapshot.
        $this->assertCount(1, $a2->Blocks());

        $a2 = $a2->doRollbackToSnapshot($stamp7);
        $this->assertEquals('The new A2', $a2->Title);
        $this->assertCount(2, $a2->Blocks());

        $a2->publishRecursive();
        $a2 = $a2->getAtVersion(Versioned::LIVE);
        $this->assertCount(2, $a2->Blocks());
    }

    /**
     * @param ArrayList $activity
     * @param array $objs
     */
    protected function assertActivityContains(ArrayList $activity, $objs = [])
    {
        $this->assertCount(sizeof($objs), $activity);
        foreach ($activity as $i => $entry) {
            if (!isset($objs[$i][2])) {
                $objs[$i][2] = null;
            }
            /* @var DataObject|SnapshotPublishable $obj */
            list ($obj, $action, $owner) = $objs[$i];
            $expectedHash = $obj->isInDB()
                ? SnapshotPublishable::hashObject($obj)
                : SnapshotPublishable::hash($obj->ClassName, $obj->OldID);
            $this->assertEquals(
                $expectedHash,
                SnapshotPublishable::hashObject($entry->Subject)
            );
            $this->assertEquals($action, $entry->Action);
            if ($owner) {
                $this->assertEquals(
                    SnapshotPublishable::hashObject($owner),
                    SnapshotPublishable::hashObject($entry->Owner)
                );
            }
        }
    }

    /**
     * @param ArrayList $items
     * @param array $objs
     */
    protected function assertPublishableObjectsContains(ArrayList $items, $objs = [])
    {
        $this->assertCount(sizeof($objs), $items);
        foreach ($items as $i => $dataObject) {
            $obj= $objs[$i];
            $expectedHash = $obj->isInDB()
                ? SnapshotPublishable::hashObject($obj)
                : SnapshotPublishable::hash($obj->ClassName, $obj->OldID);
            $this->assertEquals(
                $expectedHash,
                SnapshotPublishable::hashObject($dataObject)
            );
        }
    }

    /**
     * Virtual "sleep" that doesn't actually slow execution, only advances DBDateTime::now()
     *
     * @param int $minutes
     * @return string
     */
    protected function sleep($minutes)
    {
        $now = DBDatetime::now();
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $now->getValue());
        $date->modify("+{$minutes} minutes");
        $stamp = $date->format('Y-m-d H:i:s');
        DBDatetime::set_mock_now($stamp);

        return $stamp;
    }


    protected function buildState($publish = true)
    {
        /* @var DataObject|SnapshotPublishable $a1 */
        $a1 = new BlockPage(['Title' => 'A1 Block Page']);
        $a1->write();

        /* @var DataObject|SnapshotPublishable $a2 */
        $a2 = new BlockPage(['Title' => 'A2 Block Page']);
        $a2->write();

        /* @var DataObject|SnapshotPublishable $a1Block1 */
        $a1Block1 = new Block(['Title' => 'Block 1 on A1', 'ParentID' => $a1->ID]);
        $a1Block1->write();
        $a1Block2 = new Block(['Title' => 'Block 2 on A1', 'ParentID' => $a1->ID]);
        $a1Block2->write();

        /* @var DataObject|SnapshotPublishable $a2Block1 */
        $a2Block1 = new Block(['Title' => 'Block 1 on A2', 'ParentID' => $a2->ID]);
        $a2Block1->write();

        /* @var DataObject|SnapshotPublishable|Versioned $gallery1 */
        $gallery1 = new Gallery(['Title' => 'Gallery 1 on Block 1 on A1', 'BlockID' => $a1Block1->ID]);
        $gallery1->write();

        /* @var DataObject|SnapshotPublishable|Versioned $gallery1 */
        $gallery2 = new Gallery(['Title' => 'Gallery 2 on Block 1 on A2', 'BlockID' => $a2Block1->ID]);
        $gallery2->write();

        if ($publish) {
            $a1->publishRecursive();
            $a2->publishRecursive();
        }

        return [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2];
    }

    protected function debugActivity($activity)
    {
        $list = [];
        foreach ($activity as $entry) {
            $list[] = sprintf(
                '[%s] %s #%s (%s)',
                $entry->Action,
                $entry->Subject->ClassName,
                $entry->Subject->ID,
                $entry->Subject->getTitle()
            );
        }

        return implode("\n", $list);
    }

    protected function debugPublishable($items)
    {
        $list = [];
        foreach ($items as $item) {
            $list[] = sprintf(
                '%s #%s (%s)',
                $item->ClassName,
                $item->ID,
                $item->getTitle()
            );
        }

        return implode("\n", $list);
    }
}
