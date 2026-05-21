<?php
/**
 * Etechflow_AbandonedCart - Manual cron trigger for the send pipeline.
 *
 * `bin/magento etechflow:abc:send`
 *
 * Fires the SendReminders + SendQueuedEmails crons immediately rather
 * than waiting for the 5-minute tick. Useful for testing rule changes
 * during launch and during ops investigations.
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Console\Command;

use Etechflow\AbandonedCart\Cron\SendQueuedEmails;
use Etechflow\AbandonedCart\Cron\SendReminders;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendCommand extends Command
{
    public function __construct(
        private readonly SendReminders $sendReminders,
        private readonly SendQueuedEmails $sendQueuedEmails,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('etechflow:abc:send');
        $this->setDescription('Manually run the abandoned-cart scan + send pipeline');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Running SendReminders…</info>');
        $t0 = microtime(true);
        $this->sendReminders->execute();
        $output->writeln(sprintf('  done in %.2fs', microtime(true) - $t0));

        $output->writeln('<info>Running SendQueuedEmails…</info>');
        $t1 = microtime(true);
        $this->sendQueuedEmails->execute();
        $output->writeln(sprintf('  done in %.2fs', microtime(true) - $t1));

        $output->writeln('<comment>Check var/log/system.log for per-cart counts.</comment>');
        return Command::SUCCESS;
    }
}
