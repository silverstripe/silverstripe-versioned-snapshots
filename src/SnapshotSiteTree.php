<?php


namespace SilverStripe\Snapshots;


use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataExtension;

class SnapshotSiteTree extends DataExtension
{
    /**
     * @param $flags
     */
    public function updateStatusFlags(&$flags)
    {
        $owner = $this->owner;
        if (!$owner->hasExtension(SnapshotPublishable::class)) {
            return;
        }
        /* @var SnapshotPublishable|SiteTree $owner */
        if ($owner->hasOwnedModifications()) {
            $flags['modified'] = [
                'text' => _t(SiteTree::class . '.MODIFIEDONDRAFTSHORT', 'Modified'),
                'title' => _t(SiteTree::class . '.MODIFIEDONDRAFTHELP', 'Page has owned modifications'),
            ];
        }

    }

    /**
     * @param FieldList $actions
     */
    public function updateCMSActions(FieldList $actions)
    {
        $owner = $this->owner;
        if (!$owner->hasExtension(SnapshotPublishable::class)) {
            return;
        }

        /* @var SnapshotPublishable|SiteTree $owner */
        $canPublish = $owner->canPublish();
        $canEdit = $owner->canEdit();
        $hasOwned = $owner->hasOwnedModifications();

        // If "rollback" hasn't been added, check for owned modifications.
        if (!$actions->fieldByName('ActionsMenus.MoreOptions.action_rollback')) {
            if ($hasOwned && $canEdit) {
                $actions->addFieldToTab(
                    'ActionMenus.MoreOptions',
                    FormAction::create('rollback', _t(SiteTree::class . '.BUTTONCANCELDRAFT', 'Cancel draft changes'))
                        ->setDescription(_t(
                            SiteTree::class . '.BUTTONCANCELDRAFTDESC',
                            'Delete your draft and revert to the currently published page'
                        ))
                        ->addExtraClass('btn-secondary')
                );
            }
        }
        $publish = $actions->fieldByName('MajorActions.action_publish');
        if (!$publish) {
            return;
        }
        if ($hasOwned && $canPublish) {
            $publish->addExtraClass('btn-primary font-icon-rocket');
            $publish->setTitle(_t(SiteTree::class . '.BUTTONSAVEPUBLISH', 'Publish'));
            $publish->removeExtraClass('btn-outline-primary font-icon-tick');
        }
    }

    public function updateCMSFields(FieldList $fields)
    {
        if (!$this->owner->hasExtension(SnapshotPublishable::class)) {
            return;
        }
        /* @var SnapshotPublishable|SiteTree $owner*/
        $owner = $this->owner;

        $snapshots = $owner->getRelevantSnapshots();
        if ($snapshots->exists()) {
            $items = array_reduce($snapshots->toArray(), function ($acc, $curr) {
                $class = str_replace('\\', '__', $this->owner->baseClass());
                return $acc . sprintf(
                        '<li style="margin:15px;">%s (%s) [<a target="_blank" href="%s">preview</a>] [<a href="%s">rollback</a>]</li>',
                        $curr->obj('Created')->Ago(),
                        $curr->obj('Created'),
                        $this->owner->Link() . '?archiveDate=' . $curr->LastEdited,
                        '/admin/snapshot/rollback/' . $class . '/' . $this->owner->ID . '/' . urlencode($curr->Created)
                    );
            }, '');
            $list = LiteralField::create(
                'snapshotlist',
                '<ul>' . $items . '</ul>'
            );
            $fields->addFieldToTab('Root.Snapshots', $list);

        }
    }

}