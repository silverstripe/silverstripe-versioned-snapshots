<?php

namespace SilverStripe\Snapshots\Migration;

use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Validation\ValidationException;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

if (!class_exists(AbstractQueuedJob::class)) {
    return;
}

/**
 * Job version of @see PopulateSnapshotsTask
 */
class PopulateSnapshotsJob extends AbstractQueuedJob
{
    private MigrationService $migrator;

    private array $classesToProcess = [];

    /**
     * @return void
     * @throws ReflectionException
     * @throws NotFoundExceptionInterface
     */
    public function setup(): void
    {
        parent::setup();

        Environment::increaseTimeLimitTo();
        $this->addMessage('Prepping database...');
        $this->getMigrator()->setup();
        $this->classesToProcess = $this->migrator->getClassesToMigrate();
        $this->currentStep = 0;
        $this->totalSteps = sizeof($this->classesToProcess);
    }

    public function getSignature(): string
    {
        return md5(static::class);
    }

    public function process(): void
    {
        $remainingChildren = $this->classesToProcess;
        $this->addMessage(sizeof($remainingChildren) . ' classes remaining');

        if (count($remainingChildren) === 0) {
            $this->isComplete = true;

            return;
        }

        $baseClass = array_shift($remainingChildren);
        $this->addMessage('Migrating ' . $baseClass);
        $rows = $this->getMigrator()->migrate($baseClass);
        $this->addMessage('Base ID ' . $this->getMigrator()->getBaseID());
        $this->addMessage(sprintf('Migrated %d records', $rows));

        $this->classesToProcess = $remainingChildren;
        $this->currentStep += 1;
    }

    /**
     * @return void
     * @throws ReflectionException
     * @throws ValidationException
     */
    public function afterComplete(): void
    {
        parent::afterComplete();

        $this->addMessage('Seeding relation tracking...');
        $this->getMigrator()->seedRelationTracking();
        $this->addMessage('Tearing down...');
        $this->getMigrator()->tearDown();
    }

    public function getTitle(): string
    {
        return _t(PopulateSnapshotsJob::class . '.MIGRATE', 'Migrate versions tables to snapshots');
    }

    public function getJobType(): int
    {
        return QueuedJob::QUEUED;
    }

    public function setMigrator(MigrationService $migrator): PopulateSnapshotsJob
    {
        $this->migrator = $migrator;

        return $this;
    }

    public function getMigrator(): MigrationService
    {
        return $this->migrator;
    }
}
