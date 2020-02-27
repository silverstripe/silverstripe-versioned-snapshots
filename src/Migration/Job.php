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
    public function setup()
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
    public function getSignature()
    {
        return md5(get_class($this));
    }

    /**
     * @return void
     */
    public function process(): void
    {
        $remainingChildren = $this->classesToProcess;
        $this->addMessage(sizeof($remainingChildren) . ' classes remaining');
        if (empty($remainingChildren)) {
            $this->isComplete = true;
            return;
        }
        $baseClass = array_shift($remainingChildren);
        $this->addMessage("Migrating $baseClass");
        $rows = $this->getMigrator()->migrate($baseClass);
        $this->addMessage("Base ID " . $this->getMigrator()->getBaseID());
        $this->addMessage("Migrated $rows records");

        $this->classesToProcess = $remainingChildren;
        $this->currentStep++;
    }

    /**
     * @return void
     */
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
    public function getTitle()
    {
        return _t(__CLASS__ . '.MIGRATE', 'Migrate versions tables to snapshots');
    }

    /**
     * @return string
     */
    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    /**
     * @param MigrationService $migrator
     * @return $this
     */
    public function setMigrator(MigrationService $migrator)
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
