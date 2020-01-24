<?php


namespace SilverStripe\Snapshots\Tests;

use DateTime;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMSEvents\Listener\Form\Listener;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\EventDispatcher\Dispatch\Dispatcher;
use SilverStripe\EventDispatcher\Event\EventContextInterface;
use SilverStripe\EventDispatcher\Symfony\Event;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Snapshots\ActivityEntry;
use SilverStripe\Snapshots\SnapshotPublishable;
use SilverStripe\Snapshots\SnapshotVersioned;
use SilverStripe\Snapshots\Tests\SnapshotTest\BaseJoin;
use SilverStripe\Snapshots\Tests\SnapshotTest\Block;
use SilverStripe\Snapshots\Tests\SnapshotTest\BlockPage;
use SilverStripe\Snapshots\Tests\SnapshotTest\Gallery;
use SilverStripe\Snapshots\Tests\SnapshotTest\GalleryImage;
use SilverStripe\Snapshots\Tests\SnapshotTest\GalleryImageJoin;
use SilverStripe\Versioned\ChangeSetItem;
use SilverStripe\Versioned\Versioned;

class SnapshotTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected $usesTransactions = false;

    protected static $extra_dataobjects = [
        BlockPage::class,
        Block::class,
        Gallery::class,
        GalleryImage::class,
        BaseJoin::class,
        GalleryImageJoin::class,
        ChangeSetItem::class,
    ];

    /**
     * @var SiteTree
     */
    private $currentPage;

    public function testFundamentals()
    {
        // Model:
        // BlockPage
        //  -> (has_many/owns) -> Blocks
        //      -> (has_many/owns) -> Gallery
        //          -> (many_many/owns) -> GalleryImage

        /* @var DataObject|SnapshotPublishable $a1 */
        $a1 = new BlockPage(['Title' => 'A1 Block Page']);
        $this->editingPage($a1);
        $this->formSaveObject($a1);
        $this->formPublishObject($a1);

        /* @var DataObject|SnapshotPublishable $a2 */
        $a2 = new BlockPage(['Title' => 'A2 Block Page']);
        $this->editingPage($a2);
        $this->formSaveObject($a2);
        $this->formPublishObject($a2);

        // A1 Block page edits
        $this->editingPage($a1);

        /* @var DataObject|SnapshotPublishable $a1Block1 */
        $a1Block1 = new Block(['Title' => 'Block 1 on A1', 'ParentID' => $a1->ID]);

        $this->formSaveObject($a1Block1);
        $a1Block2 = new Block(['Title' => 'Block 2 on A1', 'ParentID' => $a1->ID]);
        $this->formSaveObject($a1Block2);

        // A1
        //   block1 (draft, new) *
        //   block2 (draft, new) *

        $this->editingPage($a2);

        /* @var DataObject|SnapshotPublishable $a2Block1 */
        $a2Block1 = new Block(['Title' => 'Block 1 on A2', 'ParentID' => $a2->ID]);
        $this->formSaveObject($a2Block1);

        // A1
        //   block1 (draft, new)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new) *

        $this->editingPage($a1);

        $a1->Title = 'A1 Block Page -- changed';
        $this->formSaveObject($a1);

        // A1 (draft, modified) *
        //   block1 (draft, new)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        $a1Block1->Title = 'Block 1 on A1 -- changed';
        $this->formSaveObject($a1Block1);

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

        $this->editingPage($a1);

        // Testing third level
        /* @var DataObject|SnapshotPublishable|Versioned $gallery1 */
        $gallery1 = new Gallery(['Title' => 'Gallery 1 on Block 1 on A1', 'BlockID' => $a1Block1->ID]);
        $this->formSaveObject($gallery1);

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
        $this->formSaveObject($gallery1);

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

        // Testing many_many
        /* @var DataObject|SnapshotPublishable $galleryItem1 */
        $galleryItem1 = new GalleryImage(['URL' => '/gallery/image/1']);
        /* @var DataObject|SnapshotPublishable $galleryItem2 */
        $galleryItem2 = new GalleryImage(['URL' => '/gallery/image/2']);

        $this->formSaveRelations($gallery1, 'Images', [$galleryItem1, $galleryItem2]);

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
        $this->assertCount(6, $a1->getPublishableObjects());
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, ActivityEntry::CREATED],
                [$a1Block2, ActivityEntry::CREATED],
                [$a1, ActivityEntry::MODIFIED],
                [$a1Block1, ActivityEntry::MODIFIED],
                [$gallery1, ActivityEntry::CREATED],
                [$gallery1, ActivityEntry::MODIFIED],
                [$galleryItem1, ActivityEntry::ADDED],
                [$galleryItem2, ActivityEntry::ADDED],
            ]
        );

        $this->assertPublishableObjectsContains(
            $a1->getPublishableObjects(),
            [
                $a1Block1,
                $a1Block2,
                $a1,
                $gallery1,
                $galleryItem1,
                $galleryItem2,
            ]
        );

        $this->editingPage($a2);

        /* @var DataObject|SnapshotPublishable $gallery1a */
        $gallery1a = new Gallery(['Title' => 'Gallery 1 on Block 1 on A2', 'BlockID' => $a2Block1->ID]);
        $this->formSaveObject($gallery1a);

        $this->formSaveRelations($gallery1a, 'Images', [$galleryItem1]);

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
        $this->formSaveObject($galleryItem1);

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
        $this->assertCount(9, $activity);
        $this->assertCount(6, $a1->getPublishableObjects());
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
                [$galleryItem1, ActivityEntry::MODIFIED],
            ]
        );
        $this->assertPublishableObjectsContains(
            $a1->getPublishableObjects(),
            [
                $a1Block1,
                $a1Block2,
                $a1,
                $gallery1,
                $galleryItem1,
                $galleryItem2,
            ]
        );

        $this->editingPage($a1);

        // Publish, and clear the slate
        $this->formPublishObject($a1);

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

        $this->editingPage($a2);
        $this->formPublishObject($a2);

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

        $this->editingPage($a1);

        $gallery1->Title = 'Gallery 1 is changed';
        $this->formSaveObject($gallery1);

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

        $this->editingPage($a1);

        $gallery1->Title = 'Gallery 1 changed';
        $this->formSaveObject($gallery1);

        // A1 (published)
        //   block1 (published)
        //       gallery1 (draft, modified) *
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)

        $this->assertTrue($a1Block1->hasOwnedModifications());

        $a1->Title = 'A1 changed';
        $this->formSaveObject($a1);

        // A1 (draft, modified) *
        //   block1 (published)
        //       gallery1 (draft, modified)
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)


        // Publish the intermediary block
        $this->formPublishObject($a1Block1);

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

        $this->formPublishObject($a1);

        // A1 (published) *
        //   block1 (published)
        //       gallery1 (published)
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)

        $this->assertFalse($a1->hasOwnedModifications());

        $a1Block1->Title = "A1 is changed again";
        $this->formSaveObject($a1Block1);

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

        $this->editingPage($a1);

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $a1Block1->Title = 'Block 1 changed';
        $this->formSaveObject($a1Block1);

        // A1 (published)
        //   block1 (draft, modified) *
        //       gallery1 (published)
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)

        $this->assertTrue($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $this->editingPage($a2);

        $a1Block1->ParentID = $a2->ID;
        $this->formSaveObject($a1Block1);
        $blockMoved = $a1Block1;

        // A1 (published)
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)
        //   block1-moved-from-A1 (draft, modified) *
        //       gallery1 (published)

        // Change of ownership. A1 no longer has modifications, but A2 does.

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

        $this->editingPage($a1);

        // This should do nothing. A1 has nothing publishable anymore.=
        $this->formPublishObject($a1);

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

        $this->editingPage($a2);

        $this->formPublishObject($a2);

        // A1 (published)
        //   block2 (published)
        // A2 (published)
        //   block1 (published)
        //       gallery1a (published)
        //   block1-moved-from-A1 (published) *
        //       gallery1 (published)

        $this->assertEmpty($a2->getActivityFeed());
        $this->assertFalse($a2->hasOwnedModifications());

        $this->editingPage($a2);

        $blockMoved->Title = "The moved block is modified";
        $this->formSaveObject($blockMoved);

        $gallery1->Title = "The gallery that belongs to the moved block is modified";
        $this->formSaveObject($gallery1);

        $item = new GalleryImage(['URL' => '/belongs/to/moved/block']);
        $item->write();

        $this->formSaveRelations($gallery1, 'Images', [$item]);


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
            [$item, ActivityEntry::ADDED],
        ]);

        // Move the block back to A1
        // Refresh the block so that changed fields flushes
        $blockMoved = DataObject::get_by_id(Block::class, $blockMoved->ID, false);
        $blockMoved->ParentID = $a1->ID;

        $this->editingPage($a1);
        $this->formSaveObject($blockMoved);

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
            [$item, ActivityEntry::ADDED],
            [$blockMoved, ActivityEntry::MODIFIED],
        ]);

        $this->editingPage($a2);
        $this->formPublishObject($a2);

        $this->editingPage($a1);
        $this->formPublishObject($a1);

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
        $this->formSaveRelations($gallery1, 'Images', [$item], ActivityEntry::REMOVED);

        $this->editingPage($a2);

        $this->formSaveRelations($gallery2, 'Images', [$item], ActivityEntry::ADDED);

        $item->URL = '/new/url';
        $this->formSaveObject($item);

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
            [$item, ActivityEntry::REMOVED],
        ]);

        $activity = $a2->getActivityFeed();
        $this->assertCount(2, $activity);
        $this->assertActivityContains($activity, [
            [$item, ActivityEntry::ADDED],
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
        $this->editingPage($a1);
        $a1Block1->Title = 'Take one for the team';
        $this->formSaveObject($a1Block1);

        $a1Block2->Title = 'You got this';
        $this->formSaveObject($a1Block2);

        $gallery = new Gallery(['Title' => 'A new gallery for block 2', 'BlockID' => $a1Block2->ID]);
        $this->formSaveObject($gallery);

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
        $this->editingPage($a2);
        $this->formSaveObject($a1Block2);

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

        $this->editingPage($a1);

        $this->assertFalse($a1->hasOwnedModifications());
        $this->assertFalse($a2->hasOwnedModifications());

        $this->formDeleteObject($a1Block1);

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

        $this->editingPage($a2);

        $a2Block1->Title = 'Change change change';
        $this->formSaveObject($a2Block1);

        $gallery2->Title = 'Changey McChangerson';
        $this->formSaveObject($gallery2);

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

        $this->formDeleteObject($a2Block1);
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

        $this->editingPage($a1);

        $this->assertCount(2, $a1->Blocks());
        $this->assertCount(1, $a2->Blocks());

        $this->assertCount(1, $a1Block1->Galleries());
        $this->assertCount(1, $a2Block1->Galleries());

        $stamp1 = $this->sleep(1);

        $a1Block1->Title = 'A1 Block 1 changed';
        $this->formSaveObject($a1Block1);

        $stamp2 = $this->sleep(1);

        $a1Block2->Title = 'A1 Block 2 changed';
        $this->formSaveObject($a1Block2);

        $stamp3 = $this->sleep(1);

        $a1Block1->Title = 'A1 Block 1 changed again';
        $this->formSaveObject($a1Block1);

        $stamp4 = $this->sleep(1);

        $gallery1->Title = 'new-gallery title';
        $this->formSaveObject($gallery1);

        $stamp5 = $this->sleep(1);

        $this->editingPage($a2);

        $a2Block2 = new Block([
            'Title' => 'Block 2 on A2',
            'ParentID' => $a2->ID,
        ]);

        $this->formSaveObject($a2Block2);

        $stamp6 = $this->sleep(1);

        $a2Block1->Title = 'A2 Block 1 changed';
        $this->formSaveObject($a2Block1);

        $stamp7 = $this->sleep(1);
        $a2->Title = 'The new A2';
        $this->formSaveObject($a2);

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

        $this->editingPage($a1);

        $this->assertCount(2, $a1->Blocks());
        $this->assertCount(1, $a2->Blocks());

        $this->assertCount(1, $a1Block1->Galleries());
        $this->assertCount(1, $a2Block1->Galleries());

        $stamp1 = $this->sleep(1);

        $a1Block1->Title = 'A1 Block 1 changed';
        $this->formSaveObject($a1Block1);

        $stamp2 = $this->sleep(1);

        $a1Block2->Title = 'A1 Block 2 changed';
        $this->formSaveObject($a1Block2);

        $stamp3 = $this->sleep(1);

        $a1Block1->Title = 'A1 Block 1 changed again';
        $this->formSaveObject($a1Block1);

        $stamp4 = $this->sleep(1);

        $gallery1->Title = 'new-gallery title';
        $this->formSaveObject($gallery1);

        $stamp5 = $this->sleep(1);

        $this->editingPage($a2);

        $a2Block2 = new Block([
            'Title' => 'Block 2 on A2',
            'ParentID' => $a2->ID,
        ]);

        $this->formSaveObject($a2Block2);

        $stamp6 = $this->sleep(1);

        $a2Block1->Title = 'A2 Block 1 changed';
        $this->formSaveObject($a2Block1);

        $stamp7 = $this->sleep(1);
        $a2->Title = 'The new A2';
        $this->formSaveObject($a2);

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

        $this->formPublishObject($a2);

        // Still has only one block because the draft stage was a rolled back snapshot.
        $this->assertCount(1, $a2->Blocks());

        $a2 = $a2->doRollbackToSnapshot($stamp7);
        $this->assertEquals('The new A2', $a2->Title);
        $this->assertCount(2, $a2->Blocks());

        $this->formPublishObject($a2);
        $a2 = $a2->getAtVersion(Versioned::LIVE);
        $this->assertCount(2, $a2->Blocks());
    }

    public function testWonkyOwner()
    {
        $page = new BlockPage(['Title' => 'The Page']);
        $this->editingPage($page);
        $this->formSaveObject($page);
        $this->formPublishObject($page);

        // This block is saved in isolation
        $this->editingPage(null);

        $block = new Block(['Title' => 'The Block', 'ParentID' => 0]);
        $block->write();

        $block->ParentID = $page->ID;
        $this->editingPage($page);
        $this->formSaveObject($block);

        $activity = $page->getActivityFeed();
        $this->assertCount(1, $activity);
        $this->assertActivityContains(
            $activity,
            [
                [$block, ActivityEntry::MODIFIED]
            ]
        );
    }

    public function testChangeToUnpublishedOwner()
    {
        $page = new BlockPage(['Title' => 'The Page']);
        $this->editingPage($page);

        $this->formSaveObject($page);

        $this->editingPage(null);

        $block = new Block(['Title' => 'The Block']);
        $block->write();

        $this->editingPage($page);
        $block->ParentID = $page->ID;
        $this->formSaveObject($block);

        $activity = $page->getActivityFeed();

        $this->assertCount(2, $activity);
        $this->assertActivityContains(
            $activity,
            [
                [$page, ActivityEntry::CREATED],
                [$block, ActivityEntry::MODIFIED]
            ]
        );
    }

    public function testMany()
    {
        $p = new BlockPage(['Title' => 'The Page']);
        $this->editingPage($p);
        $this->formSaveObject($p);

        $b = new Block(['Title' => 'The Block on The Page', 'ParentID' => $p->ID]);
        $this->formSaveObject($b);

        $g = new Gallery(['Title' => 'The Gallery on The Block on The Page', 'BlockID' => $b->ID]);
        $this->formSaveObject($g);

        $this->formPublishObject($p);

        $this->assertFalse($p->hasOwnedModifications());
        $this->assertCount(0, $p->getActivityFeed());
        $this->assertCount(0, $p->getPublishableObjects());

        $i = new GalleryImage(['URL' => '/gallery/image/1']);
        $this->formSaveRelations($g, 'Images', [$i]);

        $activity = $p->getActivityFeed();
        $this->assertActivityContains(
            $activity,
            [
                [$i, ActivityEntry::ADDED, $g]
            ]
        );
        $this->assertCount(1, $p->getActivityFeed());
    }

    public function testPlainActivityFeed()
    {
        $page = new BlockPage();
        $this->editingPage($page);
        $page->Title = 'The Page -- version 1';
        $this->formSaveObject($page);

        $page->Title = 'The Page -- version 2';
        $this->formSaveObject($page);

        $page->Title = 'The Page -- version 3';
        $this->formSaveObject($page);

        $activity = $page->getActivityFeed();
        $this->assertCount(3, $activity);
        $this->assertCount(1, $page->getPublishableObjects());
        $this->assertActivityContains(
            $activity,
            [
                [$page, ActivityEntry::CREATED],
                [$page, ActivityEntry::MODIFIED],
                [$page, ActivityEntry::MODIFIED],
            ]
        );

        $versionedActivity = $page->getActivityFeed(1);
        $this->assertCount(3, $versionedActivity);
        $this->assertActivityContains(
            $versionedActivity,
            [
                [$page, ActivityEntry::CREATED],
                [$page, ActivityEntry::MODIFIED],
                [$page, ActivityEntry::MODIFIED],
            ]
        );

        $this->formPublishObject($page);

        $activity = $page->getActivityFeed();
        $this->assertCount(0, $activity);
        $this->assertCount(0, $page->getPublishableObjects());

        $versionedActivity = $page->getActivityFeed(1);
        $this->assertCount(4, $versionedActivity);
        $this->assertActivityContains(
            $versionedActivity,
            [
                [$page, ActivityEntry::CREATED],
                [$page, ActivityEntry::MODIFIED],
                [$page, ActivityEntry::MODIFIED],
                [$page, ActivityEntry::PUBLISHED],
            ]
        );

        $page->Title = 'The Page -- version 5';
        $this->formSaveObject($page);

        $page->Title = 'The Page -- version 6';
        $this->formSaveObject($page);

        $activity = $page->getActivityFeed();
        $this->assertCount(2, $activity);
        $this->assertCount(1, $page->getPublishableObjects());
        $this->assertActivityContains(
            $activity,
            [
                [$page, ActivityEntry::MODIFIED],
                [$page, ActivityEntry::MODIFIED],
            ]
        );

        $versionedActivity = $page->getActivityFeed(1);
        $this->assertCount(6, $versionedActivity);
        $this->assertActivityContains(
            $versionedActivity,
            [
                [$page, ActivityEntry::CREATED],
                [$page, ActivityEntry::MODIFIED],
                [$page, ActivityEntry::MODIFIED],
                [$page, ActivityEntry::PUBLISHED],
                [$page, ActivityEntry::MODIFIED],
                [$page, ActivityEntry::MODIFIED]
            ]
        );

        $versionedActivity = $page->getActivityFeed(3, 5);
        $this->assertCount(3, $versionedActivity);
        $this->assertActivityContains(
            $versionedActivity,
            [
                [$page, ActivityEntry::MODIFIED],
                [$page, ActivityEntry::PUBLISHED],
                [$page, ActivityEntry::MODIFIED],
            ]
        );

        $this->formPublishObject($page);

        $versionedActivity = $page->getActivityFeed(3);
        $this->assertCount(5, $versionedActivity);
        $this->assertActivityContains(
            $versionedActivity,
            [
                [$page, ActivityEntry::MODIFIED],
                [$page, ActivityEntry::PUBLISHED],
                [$page, ActivityEntry::MODIFIED],
                [$page, ActivityEntry::MODIFIED],
                [$page, ActivityEntry::PUBLISHED],
            ]
        );
    }

    public function testNestedActivityFeed()
    {
        $p = new BlockPage(['Title' => 'Page -- v01']);
        $this->editingPage($p);
        $this->formSaveObject($p);

        $b = new Block(['Title' => 'Block -- v01', 'ParentID' => $p->ID]);
        $this->formSaveObject($b);

        $g = new Gallery(['Title' => 'Gallery -- v01', 'BlockID' => $b->ID]);
        $this->formSaveObject($g);

        $this->formPublishObject($p);

        $this->assertFalse($p->hasOwnedModifications());
        $this->assertCount(0, $p->getActivityFeed());
        $this->assertCount(0, $p->getPublishableObjects());

        $i = new GalleryImage(['URL' => '/gallery/image/1']);
        $this->formSaveRelations($g, 'Images', [$i]);

        $activity = $p->getActivityFeed();
        $this->assertActivityContains(
            $activity,
            [
                [$i, ActivityEntry::ADDED, $g]
            ]
        );

        $b->Title = 'Block -- v02';
        $this->formSaveObject($b);

        $this->formPublishObject($p);

        $i->URL = '/gallery/image/2';
        $this->formSaveObject($i);

        $activity = $p->getActivityFeed();
        $this->assertActivityContains(
            $activity,
            [
                [$i, ActivityEntry::MODIFIED]
            ]
        );

        $this->formPublishObject($p);

        $a = $p->getActivityFeed(2);
        $this->assertActivityContains($a, [
            [$i, ActivityEntry::ADDED, $g],
            [$b, ActivityEntry::MODIFIED],
            [$p, ActivityEntry::PUBLISHED],
            [$i, ActivityEntry::MODIFIED],
            [$p, ActivityEntry::PUBLISHED],
        ]);

        $a = $p->getActivityFeed(2, 4);
        $this->assertActivityContains($a, [
            [$i, ActivityEntry::ADDED, $g],
            [$b, ActivityEntry::MODIFIED],
            [$p, ActivityEntry::PUBLISHED],
            [$i, ActivityEntry::MODIFIED],
            [$p, ActivityEntry::PUBLISHED],
        ]);

        $a = $p->getActivityFeed(2, 3);
        $this->assertActivityContains($a, [
            [$i, ActivityEntry::ADDED, $g],
            [$b, ActivityEntry::MODIFIED],
            [$p, ActivityEntry::PUBLISHED],
            [$i, ActivityEntry::MODIFIED]
        ]);
    }

    /**
     * @param ArrayList $activity
     * @param array $objs
     */
    private function assertActivityContains(ArrayList $activity, $objs = [])
    {
        $this->assertCount(sizeof($objs), $activity);
        foreach ($activity as $i => $entry) {
            if (!isset($objs[$i][2])) {
                $objs[$i][2] = null;
            }
            /* @var DataObject|SnapshotPublishable $obj */
            list ($obj, $action, $owner) = $objs[$i];
            $expectedHash = $obj->isInDB()
                ? SnapshotPublishable::hashObjectForSnapshot($obj)
                : SnapshotPublishable::hashForSnapshot($obj->ClassName, $obj->OldID);
            $this->assertEquals(
                $expectedHash,
                SnapshotPublishable::hashObjectForSnapshot($entry->Subject)
            );
            $this->assertEquals($action, $entry->Action);
        }
    }

    /**
     * @param ArrayList $items
     * @param array $objs
     */
    private function assertPublishableObjectsContains(ArrayList $items, $objs = [])
    {
        $this->assertCount(sizeof($objs), $items);
        foreach ($items as $i => $dataObject) {
            $obj= $objs[$i];
            $expectedHash = $obj->isInDB()
                ? SnapshotPublishable::hashObjectForSnapshot($obj)
                : SnapshotPublishable::hashForSnapshot($obj->ClassName, $obj->OldID);
            $this->assertEquals(
                $expectedHash,
                SnapshotPublishable::hashObjectForSnapshot($dataObject)
            );
        }
    }

    /**
     * Virtual "sleep" that doesn't actually slow execution, only advances DBDateTime::now()
     *
     * @param int $minutes
     * @return string
     */
    private function sleep($minutes)
    {
        $now = DBDatetime::now();
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $now->getValue());
        $date->modify("+{$minutes} minutes");
        $stamp = $date->format('Y-m-d H:i:s');
        DBDatetime::set_mock_now($stamp);

        return $stamp;
    }


    private function buildState($publish = true)
    {
        /* @var DataObject|SnapshotPublishable $a1 */
        $a1 = new BlockPage(['Title' => 'A1 Block Page']);
        $this->editingPage($a1);
        $this->formSaveObject($a1);

        /* @var DataObject|SnapshotPublishable $a2 */
        $a2 = new BlockPage(['Title' => 'A2 Block Page']);
        $this->editingPage($a2);
        $this->formSaveObject($a2);

        $this->editingPage($a1);
        /* @var DataObject|SnapshotPublishable $a1Block1 */
        $a1Block1 = new Block(['Title' => 'Block 1 on A1', 'ParentID' => $a1->ID]);
        $this->formSaveObject($a1Block1);
        $a1Block2 = new Block(['Title' => 'Block 2 on A1', 'ParentID' => $a1->ID]);
        $this->formSaveObject($a1Block2);

        $this->editingPage($a2);
        /* @var DataObject|SnapshotPublishable $a2Block1 */
        $a2Block1 = new Block(['Title' => 'Block 1 on A2', 'ParentID' => $a2->ID]);
        $this->formSaveObject($a2Block1);

        $this->editingPage($a1);
        /* @var DataObject|SnapshotPublishable|Versioned $gallery1 */
        $gallery1 = new Gallery(['Title' => 'Gallery 1 on Block 1 on A1', 'BlockID' => $a1Block1->ID]);
        $this->formSaveObject($gallery1);

        $this->editingPage($a2);
        /* @var DataObject|SnapshotPublishable|Versioned $gallery1 */
        $gallery2 = new Gallery(['Title' => 'Gallery 2 on Block 1 on A2', 'BlockID' => $a2Block1->ID]);
        $this->formSaveObject($gallery2);

        if ($publish) {
            $this->formPublishObject($a1);
            $this->formPublishObject($a2);
        }

        return [$a1, $a2, $a1Block1, $a1Block2, $a2Block1, $gallery1, $gallery2];
    }

    private function debugActivity($activity)
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

    private function debugPublishable($items)
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

    private function formSaveObject(DataObject $object)
    {
        $object->write();
        $actionName = $object instanceof SiteTree ? 'save' : 'doSave';
        $event = $this->createEvent($object, $actionName);
        $this->dispatch($event);
    }

    private function formPublishObject(DataObject $object)
    {
        $object->write();
        $object->publishRecursive();
        $event = $this->createEvent($object, 'publish');
        $this->dispatch($event);
    }

    private function formUnpublishObject(DataObject $object)
    {
        $object->doUnpublish();
        $event = $this->createEvent($object, 'unpublish');
        $this->dispatch($event);
    }

    private function formDeleteObject(DataObject $object)
    {
        $object->doArchive();
        $event = $this->createEvent($object, 'doDelete');
        $this->dispatch($event);
    }

    /**
     * Relation saves need to be wrapped in NOW() increments because they rely on
     * timestamp driven history
     * @param DataObject $object
     * @param string $component
     * @param DataObject[] $items
     * @param string $type
     */
    private function formSaveRelations(DataObject $object, $component, array $items, $type = ActivityEntry::ADDED)
    {
        $this->sleep(2);
        $method = $type === ActivityEntry::ADDED ? 'add' : 'remove';
        foreach ($items as $item) {
            $object->$component()->$method($item);
        }
        $event = $this->createEvent($object, 'doSave');
        $this->dispatch($event);
        $this->sleep(2);
    }

    private function dispatch(EventContextInterface $event)
    {
        Dispatcher::singleton()->trigger(Listener::EVENT_NAME, $event);
    }

    private function createEvent(DataObject $object, string $actionName): Event
    {
        if (!$this->currentPage) {
            return Event::create($actionName);
        }
        $form = Form::create(
            CMSPageEditController::singleton(),
            'EditForm',
            FieldList::create(),
            FieldList::create()
        );

        $form->loadDataFrom($object);
        $page = $this->currentPage->isInDB()
            ? DataObject::get_by_id(get_class($this->currentPage), $this->currentPage->ID)
            : $this->currentPage;

        return Event::create(
            $actionName,
            [
                'page' => $page,
                'form' => $form,
            ]
        );
    }

    private function editingPage(?DataObject $page = null)
    {
        $this->currentPage = $page;
    }
}
