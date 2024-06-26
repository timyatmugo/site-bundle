<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteBundle\Command;

use Doctrine\DBAL\Connection;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

use function basename;
use function dirname;
use function getcwd;
use function trim;

use const DIRECTORY_SEPARATOR;

final class DumpDatabaseCommand extends Command
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        // Parent constructor call is mandatory for commands registered as services
        parent::__construct();
        $this->connection = $connection;
    }

    protected function configure(): void
    {
        $this->setDescription('Dumps the currently configured database to the provided file')
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'File name where to write the database dump',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = getcwd() . DIRECTORY_SEPARATOR . trim($input->getArgument('file'), '/');
        $targetDirectory = dirname($filePath);
        $fileName = basename($filePath);

        $fs = new Filesystem();
        if (!$fs->exists($targetDirectory)) {
            $fs->mkdir($targetDirectory);
        }

        $params = $this->connection->getParams();

        // https://dev.mysql.com/doc/refman/5.7/en/mysqldump.html#option_mysqldump_opt
        // https://dev.mysql.com/doc/refman/5.7/en/mysqldump.html#option_mysqldump_quick
        // https://dev.mysql.com/doc/refman/5.7/en/mysqldump.html#option_mysqldump_single-transaction
        $process = new Process(
            [
                'mysqldump',
                '-u',
                $params['user'],
                '-h',
                $params['host'],
                '--opt',
                '--quick',
                '--single-transaction',
                '-r',
                $targetDirectory . '/' . $fileName,
                $params['dbname'],
            ],
            null,
            [
                'MYSQL_PWD' => $params['password'],
            ],
            null,
            null,
        );

        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput());
        }

        $output->writeln('<info>Database dump complete.</info>');

        return Command::SUCCESS;
    }
}
