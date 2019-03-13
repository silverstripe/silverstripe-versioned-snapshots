<?php


namespace SilverStripe\Snapshots;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataExtension;

class SnapshotExtension extends DataExtension
{
    public function updateCMSFields(FieldList $fields)
    {
        if (!$this->owner->hasExtension(SnapshotPublishable::class)) {
            return;
        }
        /* @var SnapshotPublishable|SiteTree $owner*/
        $owner = $this->owner;
        if ($owner->hasOwnedModifications()) {
            $fields->unshift(
                HeaderField::create('modifications', 'This page has owned modifications')
                    ->setAttribute('style', 'color: orange;')
            );
            $activity = $owner->getActivityFeed();
            $items = array_reduce($activity->toArray(), function ($acc, $curr) {
                return $acc . sprintf(
                    '<li style="margin:15px;">[%s] %s "%s" was %s</li>',
                    $curr->Date,
                    $curr->Subject->singular_name(),
                    $curr->Subject->getTitle(),
                    $curr->Action
                );
            }, '');
            $list = LiteralField::create(
                'activitylist',
                '<ul>' . $items . '</ul>'
            );
            $fields->addFieldToTab('Root.Activity', $list);
            $fields->fieldByName('Root.Activity')->setTitle('Activity (' . $activity->count() . ')');

//            $tab = Tab::create(
//                'Activity',
//                'Activity (' . $activity->count() . ')',
//                [
//                    LiteralField::create(
//                        'activitylist',
//                        '<ul>' . $items . '</ul>'
//                    ),
//                ]
//            );
//            $fields->fieldByName('Root')->push($tab);
        }
    }
}
