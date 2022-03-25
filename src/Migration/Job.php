<?php

namespace SilverStripe\Snapshots\Migration;

use SilverStripe\Core\Environment;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

if (!class_exists(AbstractQueuedJob::class)) {
    return;
}

class Job extends AbstractQueuedJob
{
    /**
     * @var MigrationService
     */
    private $migrator;

    /**
     * @var array
     */
    private $classesToProcess = [];

    /**
     * @return void
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

    /**
     * @return string
     */
    public function getSignature(): string
    {
        return md5(static::class);
    }

    /**
     * @return void
     */
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

    public function afterComplete(): void
    {
        parent::afterComplete();
        $this->addMessage('Seeding relation tracking...');
        $this->getMigrator()->seedRelationTracking();
        $this->addMessage('Tearing down...');
        $this->getMigrator()->tearDown();
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return _t(self::class . '.MIGRATE', 'Migrate versions tables to snapshots');
    }

    public function getJobType(): int
    {
        return QueuedJob::QUEUED;
    }

    /**
     * @param MigrationService $migrator
     * @return $this
     */
    public function setMigrator(MigrationService $migrator): self
    {
        $this->migrator = $migrator;

        return $this;
    }

    /**
     * @return MigrationService
     */
    public function getMigrator(): MigrationService
    {
        return $this->migrator;
    }
}
