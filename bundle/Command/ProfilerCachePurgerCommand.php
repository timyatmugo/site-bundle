<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteBundle\Command;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;

final class ProfilerCachePurgerCommand extends Command
{
    private ?Profiler $profiler;

    public function __construct(?Profiler $profiler = null)
    {
        // Parent constructor call is mandatory for commands registered as services
        parent::__construct();
        $this->profiler = $profiler;
    }

    protected function configure(): void
    {
        $this->setDescription('Clears profiler cache.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->profiler === null) {
            throw new RuntimeException('To clear profiler cache, you need to be in dev mode where @profiler service is available.');
        }

        $this->profiler->purge();

        $output->writeln('<info>Clearing Profiler cache finished.</info>');

        return Command::SUCCESS;
    }
}
