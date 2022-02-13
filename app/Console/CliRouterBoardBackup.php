<?php

namespace App\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Src\Logger\OutputLogger;
use Src\RouterBoard\RouterBoardBackup;
use Src\RouterBoard\InputParser;

class CliRouterBoardBackup extends Command
{

    private $config;

    public function __construct(array $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    protected function configure()
    {
        $this
            ->setName('rb:backup')
            ->setDescription('Mikrotik RouterBoard backup configurations to local folder.')
            ->addArgument('action', InputArgument::OPTIONAL, 'backup', 'backup')
            ->addOption('addr', 'i', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'IPv4 address of router.')
            ->addUsage(
                '<comment>-> by default backup all routers from backup list.</comment>'
            )
            ->addUsage(
                '-i 192.168.1.1 ' .
                '<comment>-> backup one router.</comment>'
            )
            ->addUsage(
                '-i 192.168.1.1 -i 192.168.1.2:2345 ' .
                '<comment>-> backup more routers with override default port from config file.</comment>'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new OutputLogger ($output);
        $rbackup = new RouterBoardBackup($this->config, $logger);

        $action = $input->getArgument('action');

        try {
            switch ($action) {
                case "backup":
                    if (!$input->getOption('addr')) {
                        $logger->log("Action: Backup all routers from backup list.");
                        $rbackup->backupAllRouterBoards();
                        break;
                    }
                    $logger->log("Action: Backup one or more routers from input.");
                    $rbackup->backupOneRouterBoard(new InputParser($this->config, $logger, $input->getOption('addr')));
                    break;
                default:
                    $this->defaultHelp($output);
                    break;
            }
        } catch (\Exception $e) {
            $logger->log("Error: " . $e->getMessage() . " in " . $e->getFile() . " on line:" . $e->getLine(), $logger->setError());
        }
        
        return Command::SUCCESS;

    }

    /**
     * Print help to default otput
     * @param $output
     * @throws \Exception
     */
    private function defaultHelp($output)
    {
        $command = $this->getApplication()->get('help');
        $command->run(new ArrayInput(['command_name' => $this->getName()]), $output);
    }

}
