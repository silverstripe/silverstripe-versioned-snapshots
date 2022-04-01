<?php

namespace SilverStripe\Snapshots\RelationDiffer;

use Exception;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Resettable;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\SnapshotHasher;

/**
 * In-memory cache for relation diffs
 */
class RelationDiffCache implements Resettable
{

    use Injectable;
    use SnapshotHasher;

    /**
     * @var array
     */
    protected $cachedData = [];

    /**
     * @param DataObject $object
     * @return array|null
     * @throws Exception
     */
    public function getCachedData(DataObject $object): ?array
    {
        $key = $this->getCacheKey($object);

        // Invalid cache key
        if (!$key) {
            return null;
        }

        if (array_key_exists($key, $this->cachedData)) {
            return $this->cachedData[$key];
        }

        return null;
    }

    /**
     * @param DataObject $object
     * @param array $data
     * @return $this
     * @throws Exception
     */
    public function addCachedData(DataObject $object, array $data): self
    {
        $key = $this->getCacheKey($object);

        // Invalid cache key
        if (!$key) {
            return $this;
        }

        $this->cachedData[$key] = $data;

        return $this;
    }

    public function flushCachedData(): void
    {
        $this->cachedData = [];
    }

    public static function reset(): void
    {
        static::singleton()->flushCachedData();
    }

    /**
     * @param DataObject $object
     * @return string
     * @throws Exception
     */
    protected function getCacheKey(DataObject $object): string
    {
        return $object->isInDB()
            ? sprintf(
                '%s-%s',
                $object->getUniqueKey(),
                $this->hashObjectForSnapshot($object)
            )
            : '';
    }
}
