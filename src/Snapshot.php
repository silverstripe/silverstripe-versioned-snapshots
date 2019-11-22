<?php

namespace SilverStripe\Snapshots;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Schema;
use Page;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;

/**
 * Class Snapshot
 *
 * @property string $OriginHash
 * @property string $Message
 * @property int $OriginID
 * @property string $OriginClass
 * @property int AuthorID
 * @method DataObject Origin()
 * @method Member Author()
 * @method HasManyList|SnapshotItem[] Items()
 * @package SilverStripe\Snapshots
 */
class Snapshot extends DataObject
{

    use SnapshotHasher;

    const TRIGGER_ACTION = 'action';
    const TRIGGER_MODEL = 'model';

    /**
     * Specifies which type of snapshot creation trigger is used
     * valid values
     * action - snapshot will be created via CMS actions, trigger is opt-in
     * model - snapshot will be created via model writes, trigger is opt-out
     *
     * @config
     * @var string
     */
    private static $trigger = self::TRIGGER_ACTION;

    /**
     * Whitelist of CMS actions which will create a snapshot
     *
     * @var array
     */
    private static $actions = [];

    /**
     * @var array
     */
    private static $db = [
        'OriginHash' => 'Varchar(64)',
        'Message' => 'Varchar',
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'Origin' => DataObject::class,
        'Author' => Member::class,
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'Items' => SnapshotItem::class,
    ];

    /**
     * @var array
     */
    private static $indexes = [
        'OriginHash' => true,
    ];

    /**
     * @var string
     */
    private static $table_name = 'VersionedSnapshot';

    /**
     * @var string
     */
    private static $singular_name = 'Snapshot';

    /**
     * @var string
     */
    private static $plural_name = 'Snapshots';

    /**
     * @var string
     */
    private static $default_sort = 'ID ASC';

    /**
     * @var array
     */
    private static $cascade_deletes = [
        'Items',
    ];

    /**
     * @return SnapshotItem|null
     */
    public function getOriginItem()
    {
        return $this->Items()->filter([
            'ObjectHash' => $this->OriginHash,
        ])->first();
    }

    /**
     * @return SnapshotItem|null
     */
    public function getOriginVersion()
    {
        $originItem = $this->getOriginItem();
        if ($originItem) {
            return Versioned::get_version(
                $originItem->ObjectClass,
                $originItem->ObjectID,
                $originItem->Version
            );
        }

        return null;
    }

    /**
     * @return string
     */
    public function getDate()
    {
        return $this->LastEdited;
    }

    /**
     * @return string
     */
    public function getActivityDescription()
    {
        $item = $this->getOriginItem();
        if ($item) {
            $activity = ActivityEntry::createFromSnapshotItem($item);
            return ucfirst(sprintf(
                '%s "%s"',
                $activity->Subject->singular_name(),
                $activity->Subject->getTitle()
            ));
        }

        return 'none';
    }

    /**
     * @return string
     */
    public function getActivityType()
    {
        $item = $this->getOriginItem();
        if ($item) {
            $activity = ActivityEntry::createFromSnapshotItem($item);

            return $activity->Action;
        }

        return '';
    }

    /**
     * @param null $member
     * @param array $context
     * @return bool|int
     */
    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

    /**
     * @param null $member
     * @param array $context
     * @return bool|int
     */
    public function canEdit($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

    /**
     * @param null $member
     * @param array $context
     * @return bool|int
     */
    public function canDelete($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CMS_ACCESS_CMSMain');
    }

    /**
     * @param null $member
     * @param array $context
     * @return bool
     */
    public function canView($member = null, $context = [])
    {
        return true;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $this->OriginHash = static::hashForSnapshot($this->OriginClass, $this->OriginID);
    }

    public function isActionTriggerActive()
    {
        return $this->config()->get('trigger') === static::TRIGGER_ACTION;
    }

    public function isModelTriggerActive()
    {
        return $this->config()->get('trigger') === static::TRIGGER_MODEL;
    }

    /**
     * Singleton access only
     *
     * Valid values:
     * NULL - action will not be processed into snapshot
     * empty string - action will be processed into snapshot but no message will be displayed
     * string - action will be processed into snapshot, message will be displayed
     *
     * This function is the ideal place to put action identifier logging when trying to determine
     * which action identifier needs to be used in your configuration
     * example logging code (place as the first line of the function)
     * error_log($identifier);
     * run your action in the CMS and look for your identifier in the log
     *
     * @param string|null $identifier
     * @return string|null
     */
    public function getActionMessage($identifier): ?string
    {
        $actions = $this->config()->get('actions');
        $actions = is_array($actions) ? $actions : [];

        if (!array_key_exists($identifier, $actions)) {
            return null;
        }

        return $actions[$identifier];
    }

    /**
     * Singleton access only
     *
     * @param DataObject $owner
     * @param null|DataObject $origin
     * @param string $message
     * @param array $objects
     * @throws ValidationException
     */
    public function createSnapshotFromAction(
        DataObject $owner,
        ?DataObject $origin,
        string $message,
        array $objects = []
    ): void {
        if (!$owner->isInDB()) {
            return;
        }

        if ($origin === null || !$origin->isInDB()) {
            // case 1: no origin provided or the origin got deleted
            // this means we can't point to a specific origin object

            if ($message) {
                // message is available - we can create an event to represent the change
                // the event is added to the list of objects so a matching snapshot item is created
                $event = SnapshotEvent::create();
                $event->Title = $message;
                $event->write();

                $message = ($origin === null)
                    ? $message
                    : sprintf(
                        '%s %s',
                        $message,
                        $origin->config()->get('singular_name')
                    );

                $origin = $event;
                array_unshift($objects, $origin);
            } else {
                // no message is available - fallback to the owner
                // no need to add the origin to the list of objects as it's already there
                $origin = $owner;
            }
        } elseif ($origin->ClassName === $owner->ClassName && (int) $origin->ID === (int) $owner->ID) {
            // case 2: origin is same as the owner
            // no need to add the origin to the list of objects as it's already there
            $origin = $owner;
        } else {
            // case 3: stadard origin - add it to the object list
            array_unshift($objects, $origin);
        }

        // owner is added as the last item
        array_push($objects, $owner);

        $currentUser = Security::getCurrentUser();
        $snapshot = Snapshot::create();

        $snapshot->OriginClass = $origin->baseClass();
        $snapshot->OriginID = (int) $origin->ID;
        $snapshot->Message = $message;
        $snapshot->AuthorID = $currentUser
            ? (int) $currentUser->ID
            : 0;

        $snapshot->write();

        // the rest of the objects are processed in the provided order
        foreach ($objects as $object) {
            if (!$object instanceof DataObject) {
                continue;
            }

            $item = SnapshotItem::create();
            $item->hydrateFromDataObject($object);
            $snapshot->Items()->add($item);
        }
    }

    /**
     * Singleton access only
     *
     * Use this extension point if you want to override default Snpashot creation for generic GraphQL actions
     *
     * @param Page $page
     * @param string $action
     * @param string $message
     * @param $recordOrList
     * @param array $args
     * @param $context
     * @param ResolveInfo $info
     * @return bool
     */
    public function graphQLGenericActionSnapshot(
        Page $page,
        string $action,
        string $message,
        $recordOrList,
        array $args,
        $context,
        ResolveInfo $info
    ): bool {
        $results = $this->extend(
            'overrideGraphQLGenericActionSnapshot',
            $page,
            $action,
            $message,
            $recordOrList,
            $args,
            $context,
            $info
        );

        return in_array(true, $results);
    }

    /**
     * Singleton access only
     *
     * Use this extension point if you want to override default Snpashot creation for custom GraphQL actions
     *
     * @param Page $page
     * @param string $action
     * @param string $message
     * @param Schema $schema
     * @param string $query
     * @param array $context
     * @param array $params
     * @return bool
     */
    public function graphQLCustomActionSnapshot(
        Page $page,
        string $action,
        string $message,
        Schema $schema,
        string $query,
        array $context,
        array $params
    ): bool {
        $results = $this->extend(
            'overrideGraphQLCustomActionSnapshot',
            $page,
            $action,
            $message,
            $schema,
            $query,
            $context,
            $params
        );

        return in_array(true, $results);
    }

    /**
     * Singleton access only
     *
     * Use this extension point if you want to override default Snpashot creation for GridField alter actions
     *
     * @param Page $page
     * @param string $action
     * @param string $message
     * @param GridField $gridField
     * @param array $arguments
     * @param array $data
     * @return bool
     */
    public function gridFieldAlterActionSnapshot(
        Page $page,
        string $action,
        string $message,
        GridField $gridField,
        array $arguments,
        array $data
    ): bool {
        $results = $this->extend(
            'overrideGridFieldAlterActionSnapshot',
            $page,
            $action,
            $message,
            $gridField,
            $arguments,
            $data
        );

        return in_array(true, $results);
    }

    /**
     * Singleton access only
     *
     * Use this extension point if you want to override default Snapshot creation for GridField URL handler actions
     *
     * @param Page $page
     * @param string $action
     * @param string $message
     * @param GridField $gridField
     * @return bool
     */
    public function gridFieldUrlActionSnapshot(
        Page $page,
        string $action,
        string $message,
        GridField $gridField
    ): bool {
        $results = $this->extend(
            'overrideGridFieldUrlActionSnapshot',
            $page,
            $action,
            $message,
            $gridField
        );

        return in_array(true, $results);
    }

    /**
     * Singleton access only
     *
     * Use this extension point if you want to override default Snpashot creation for Form submision actions
     *
     * @param Form $form
     * @param HTTPRequest $request
     * @param Page $page
     * @param string $action
     * @param string $message
     * @return bool
     */
    public function formSubmissionSnapshot(
        Form $form,
        HTTPRequest $request,
        Page $page,
        string $action,
        string $message
    ): bool {
        $results = $this->extend(
            'overrideFormSubmissionSnapshot',
            $form,
            $request,
            $page,
            $action,
            $message
        );

        return in_array(true, $results);
    }

    /**
     * Singleton access only
     *
     * Use this extension point if you want to override default Snpashot creation for CMS main actions
     *
     * @param Page $page
     * @param string $action
     * @param string $message
     * @return bool
     */
    public function CMSMainActionSnapshot(
        Page $page,
        string $action,
        string $message
    ): bool {
        $results = $this->extend(
            'overrideCMSMainActionSnapshot',
            $page,
            $action,
            $message
        );

        return in_array(true, $results);
    }
}
