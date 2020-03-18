<?php


namespace SilverStripe\Snapshots\Migration;

use Psr\Log\LoggerInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;

class Task extends BuildTask
{
    private static $segment = 'snapshot-migration';

    /**
     * @var MigrationService
     */
    private $migrator;

    /**
     * @var string
     */
    protected $description = 'Migrate _versions tables to snapshots';

    /**
     * MigrationTask constructor.
     * @param MigrationService $service
     */
    public function __construct(MigrationService $service)
    {
        parent::__construct();
        $this->migrator = $service;
    }

    /**
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        $logger = Injector::inst()->get(LoggerInterface::class);

        $logger->info('Prepping database...');
        $this->migrator->setup();
        $classes = $this->migrator->getClassesToMigrate();
        $logger->info('Migrating ' . sizeof($classes) . ' classes');

        foreach ($classes as $class) {
            $logger->info("Migrating $class");
            $rows = $this->migrator->migrate($class);
            $logger->info("$rows records migrated");
        }
        $this->migrator->seedRelationTracking();
        $this->migrator->tearDown();
    }
}
