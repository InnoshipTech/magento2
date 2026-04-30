<?php
namespace InnoShip\InnoShip\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Initialimport extends Command
{
    protected function configure()
    {
        $this->setName('innoship:dataimport');
        $this->setDescription('Import all Innoship data');

        parent::configure();
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $output->writeln("Import Courier List");
        $importCourierList = $objectManager->create(\InnoShip\InnoShip\Cron\Courierlist::class);
        $importCourierList->execute();
        $output->writeln("Import Courier List - DONE");
        $output->writeln("-------------------------------------------");

        $output->writeln("Import Locker List");
        $importCourierList = $objectManager->create(\InnoShip\InnoShip\Cron\Pudoinnoship::class);
        $importCourierList->execute();
        $output->writeln("Import Locker - DONE");
        $output->writeln("-------------------------------------------");

        $output->writeln("Import City's List");
        $importCourierList = $objectManager->create(\InnoShip\InnoShip\Cron\Citylist::class);
        $importCourierList->execute();
        $output->writeln("Import City's - DONE");

        return 0;
    }
}
