<?php
/**
 * Etechflow_AbandonedCart - Manual cleanup trigger.
 *
 * `bin/magento etechflow:abc:cleanup`
 *
 * Fires the Cleanup cron immediately. Useful before a backup run or after
 * a config change to retention windows.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Console\Command;

use Etechflow\AbandonedCart\Cron\Cleanup;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupCommand extends Command
{
    public function __construct(
        private readonly Cleanup $cleanup,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('etechflow:abc:cleanup');
        $this->setDescription('Manually run the abandoned-cart cleanup cron');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Running Cleanup cron…</info>');
        $t0 = microtime(true);
        $this->cleanup->execute();
        $output->writeln(sprintf('  done in %.2fs', microtime(true) - $t0));
        $output->writeln('<comment>Check var/log/system.log for expired/deleted counts.</comment>');
        return Command::SUCCESS;
    }
}
