<?php

namespace SilverStripe\Snapshots\RelationDiffer;

use Exception;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Resettable;
use SilverStripe\ORM\DataObject;
use SilverStripe\Snapshots\SnapshotHasher;
use SilverStripe\Versioned\Versioned;

/**
 * In-memory cache for relation diffs and stage related operations
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
     * List of all models which are fully published (we executed publish recursive)
     *
     * @var array
     */
    protected $publishedModels = [];

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

    /**
     * Mark model as published
     * This information in meant to be used only within in-memory cache
     * We can prevent some unnecessary data lookups within CMS and also bypass some sequencing issues related to
     * the "Publish" action (snapshot models being saved later than the related model)
     *
     * @param DataObject $model
     * @throws Exception
     */
    public function markAsPublished(DataObject $model): void
    {
        if (!$model->hasExtension(Versioned::class)) {
            return;
        }

        $this->publishedModels[] = $this->getCacheKey($model);
    }

    /**
     * @param DataObject $model
     * @return bool
     * @throws Exception
     */
    public function isMarkedAsPublished(DataObject $model): bool
    {
        if (!$model->hasExtension(Versioned::class)) {
            return false;
        }

        $cacheKey = $this->getCacheKey($model);

        return in_array($cacheKey, $this->publishedModels);
    }

    public function flushCachedData(): void
    {
        $this->cachedData = [];
        $this->publishedModels = [];
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
