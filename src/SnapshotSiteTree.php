<?php

namespace SilverStripe\Snapshots;

use Exception;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;

/**
 * Customise site tree status flags for pages to use snapshot records instead of versioned records
 *
 * @extends Extension<SiteTree>
 * @extends Extension<SnapshotSiteTree>
 * @extends Extension<SnapshotPublishable>
 */
class SnapshotSiteTree extends Extension
{
    /**
     * Extension point in @see SiteTree::getStatusFlags()
     *
     * @param mixed $flags
     * @throws Exception
     */
    protected function updateStatusFlags(mixed &$flags): void
    {
        $owner = $this->getOwner();

        if (!$owner->hasExtension(SnapshotPublishable::class)) {
            return;
        }

        if (!$owner->hasOwnedModifications()) {
            return;
        }

        $flags['modified'] = [
            'text' => _t(SiteTree::class . '.MODIFIEDONDRAFTSHORT', 'Modified'),
            'title' => _t(SiteTree::class . '.MODIFIEDONDRAFTHELP', 'Page has owned modifications'),
        ];
    }

    /**
     * @param FieldList $actions
     * @throws Exception
     */
    protected function updateCMSActions(FieldList $actions): void
    {
        $owner = $this->getOwner();

        if (!$owner->hasExtension(SnapshotPublishable::class)) {
            return;
        }

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

        if (!$hasOwned || !$canPublish) {
            return;
        }

        $publish->addExtraClass('btn-primary font-icon-rocket');
        $publish->setTitle(_t(SiteTree::class . '.BUTTONSAVEPUBLISH', 'Publish'));
        $publish->removeExtraClass('btn-outline-primary font-icon-tick');
    }
}
