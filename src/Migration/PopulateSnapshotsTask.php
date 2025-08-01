<?php

namespace SilverStripe\Snapshots\Migration;

use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use ReflectionException;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class PopulateSnapshotsTask extends BuildTask
{
    protected static string $commandName = 'snapshot-migration';

    protected string $title = 'Populate snapshots data';

    protected static string $description = 'Migrate _versions tables to snapshots';

    private MigrationService $migrator;

    /**
     * MigrationTask constructor.
     *
     * @param MigrationService $service
     */
    public function __construct(MigrationService $service)
    {
        parent::__construct();

        $this->migrator = $service;
    }

    /**
     * @param InputInterface $input
     * @param PolyOutput $output
     * @return int
     * @throws ReflectionException
     * @throws ValidationException
     * @throws NotFoundExceptionInterface
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        /** @var LoggerInterface $logger */
        $logger = singleton(LoggerInterface::class);

        $logger->info('Prepping database...');
        $this->migrator->setup();
        $classes = $this->migrator->getClassesToMigrate();
        $logger->info('Migrating ' . sizeof($classes) . ' classes');

        foreach ($classes as $class) {
            $logger->info('Migrating ' . $class);
            $rows = $this->migrator->migrate($class);
            $logger->info($rows . ' records migrated');
        }

        $this->migrator->seedRelationTracking();
        $this->migrator->tearDown();

        return Command::SUCCESS;
    }
}
