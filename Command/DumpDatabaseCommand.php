<?php

namespace Netgen\Bundle\MoreBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ProcessBuilder;
use RuntimeException;

class DumpDatabaseCommand extends ContainerAwareCommand
{
    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this->setName('ngmore:database:dump')
            ->setDescription('Dumps the currently configured database to the provided file')
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'File name where to write the database dump'
            );
    }

    /**
     * Executes the current command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input An InputInterface instance
     * @param \Symfony\Component\Console\Output\OutputInterface $output An OutputInterface instance
     *
     * @throws \RuntimeException When an error occurs
     *
     * @return null|int null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        $databaseName = $container->getParameter('database_name');
        $databaseHost = $container->getParameter('database_host');
        $databaseUser = $container->getParameter('database_user');
        $databasePassword = $container->getParameter('database_password');

        $filePath = getcwd() . DIRECTORY_SEPARATOR . trim($input->getArgument('file'), '/');
        $targetDirectory = dirname($filePath);
        $fileName = basename($filePath);

        $fs = new Filesystem();
        if (!$fs->exists($targetDirectory)) {
            $fs->mkdir($targetDirectory);
        }

        $processBuilder = new ProcessBuilder(
            array(
                'mysqldump',
                '-u',
                $databaseUser,
                '-h',
                $databaseHost,
                '-r',
                $targetDirectory . '/' . $fileName,
                $databaseName,
            )
        );

        $processBuilder->setEnv('MYSQL_PWD', $databasePassword);
        $processBuilder->setTimeout(null);

        $process = $processBuilder->getProcess();
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput());
        }

        $output->writeln('<info>Database dump complete.</info>');
    }
}