<?php
namespace InnoShip\InnoShip\Console;

use InnoShip\InnoShip\Cron\CitylistFactory;
use InnoShip\InnoShip\Cron\CourierlistFactory;
use InnoShip\InnoShip\Cron\PudoinnoshipFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Initialimport extends Command
{
    private CourierlistFactory $courierlistFactory;
    private PudoinnoshipFactory $pudoinnoshipFactory;
    private CitylistFactory $citylistFactory;

    public function __construct(
        CourierlistFactory $courierlistFactory,
        PudoinnoshipFactory $pudoinnoshipFactory,
        CitylistFactory $citylistFactory,
        ?string $name = null
    ) {
        $this->courierlistFactory = $courierlistFactory;
        $this->pudoinnoshipFactory = $pudoinnoshipFactory;
        $this->citylistFactory = $citylistFactory;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('innoship:dataimport');
        $this->setDescription('Import all Innoship data');

        parent::configure();
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("Import Courier List");
        $importCourierList = $this->courierlistFactory->create();
        $importCourierList->execute();
        $output->writeln("Import Courier List - DONE");
        $output->writeln("-------------------------------------------");

        $output->writeln("Import Locker List");
        $importCourierList = $this->pudoinnoshipFactory->create();
        $importCourierList->execute();
        $output->writeln("Import Locker - DONE");
        $output->writeln("-------------------------------------------");

        $output->writeln("Import City's List");
        $importCourierList = $this->citylistFactory->create();
        $importCourierList->execute();
        $output->writeln("Import City's - DONE");

        return 0;
    }
}
