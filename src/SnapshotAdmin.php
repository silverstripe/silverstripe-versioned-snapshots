<?php


namespace SilverStripe\Snapshots;


use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DataObject;

/**
 * Temporary shim to provide rollback POC
 * @package SilverStripe\Snapshots
 */
class SnapshotAdmin extends LeftAndMain
{
    private static $url_segment = 'snapshot';

    private static $url_rule = '/$Action';

    private static $url_priority = 10;

    private static $required_permission_codes = 'CMS_ACCESS_CMSMain';

    private static $url_handlers = [
        'rollback/$RecordClass!/$RecordID!/$Snapshot!' => 'handleRollback'
    ];

    private static $allowed_actions = [
        'handleRollback',
    ];

    public function handleRollback(HTTPRequest $request)
    {
        $class = $request->param('RecordClass');
        $id = $request->param('RecordID');
        $snapshot = urldecode($request->param('Snapshot'));
        $class = str_replace('__', '\\', $class);
        /* @var SnapshotPublishable|SnapshotVersioned $record */
        $record = DataObject::get_by_id($class, $id);

        if (!$record) {
            $this->httpError(404);
            return;
        }

        $record->doRollbackToSnapshot($snapshot);

        return $this->redirectBack();
    }

}