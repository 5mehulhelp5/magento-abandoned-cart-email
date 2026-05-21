<?php
/**
 * Etechflow_AbandonedCart - Performance micro-benchmark.
 *
 * `bin/magento etechflow:abc:perf [--iterations=N] [--json[=path]]`
 *
 * Per §15: every module ships this. Measures min/median/p95/max per hot
 * path so ops can spot regressions pre/post deploy.
 *
 * Hot paths benchmarked:
 *   - Config::isEnabled            (frontend observer fires this first)
 *   - LicenseValidator::isValid    (frontend observer second check)
 *   - Config::getAbandonmentThresholdMinutes (cron loop reads this)
 *   - CartStatus::toOptionArray    (admin grid status column)
 *   - bin2hex(random_bytes(32))    (token generation per cart save)
 *
 * @category   ETechFlow
 * @package    Etechflow_AbandonedCart
 */
declare(strict_types=1);

namespace Etechflow\AbandonedCart\Console\Command;

use Etechflow\AbandonedCart\Model\Config;
use Etechflow\AbandonedCart\Model\LicenseValidator;
use Etechflow\AbandonedCart\Model\Source\CartStatus;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PerfCommand extends Command
{
    private const DEFAULT_ITERATIONS = 1000;

    public function __construct(
        private readonly Config $config,
        private readonly LicenseValidator $licenseValidator,
        private readonly CartStatus $cartStatusSource,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('etechflow:abc:perf');
        $this->setDescription('Micro-benchmark hot paths in Etechflow_AbandonedCart');
        $this->addOption(
            'iterations',
            'i',
            InputOption::VALUE_REQUIRED,
            'Iterations per path',
            (string) self::DEFAULT_ITERATIONS
        );
        $this->addOption(
            'json',
            null,
            InputOption::VALUE_OPTIONAL,
            'Emit results as JSON (optionally to file)',
            false
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $iterations = max(10, (int) $input->getOption('iterations'));

        $paths = [
            'Etechflow_ABC_Config_isEnabled'             => fn() => $this->config->isEnabled(),
            'Etechflow_ABC_License_isValid'              => fn() => $this->licenseValidator->isValid(),
            'Etechflow_ABC_Config_getAbandonmentMinutes' => fn() => $this->config->getAbandonmentThresholdMinutes(),
            'Etechflow_ABC_CartStatus_toOptionArray'     => fn() => $this->cartStatusSource->toOptionArray(),
            'Etechflow_ABC_RestoreToken_generate'        => static fn() => bin2hex(random_bytes(32)),
        ];

        $results = [];
        foreach ($paths as $name => $fn) {
            $results[$name] = $this->measure($fn, $iterations);
        }

        $jsonOpt = $input->getOption('json');
        if ($jsonOpt !== false) {
            $payload = json_encode(
                ['iterations' => $iterations, 'results' => $results],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );
            if (is_string($jsonOpt) && $jsonOpt !== '') {
                file_put_contents($jsonOpt, $payload);
                $output->writeln(sprintf('<info>JSON written to %s</info>', $jsonOpt));
            } else {
                $output->writeln($payload);
            }
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Iterations: %d</info>', $iterations));
        $output->writeln('');
        $output->writeln(sprintf('  %-45s %10s %10s %10s %10s', 'Path', 'min(ms)', 'med(ms)', 'p95(ms)', 'max(ms)'));
        $output->writeln(str_repeat('-', 90));
        foreach ($results as $name => $r) {
            $output->writeln(sprintf(
                '  %-45s %10.4f %10.4f %10.4f %10.4f',
                $name,
                $r['min'],
                $r['median'],
                $r['p95'],
                $r['max']
            ));
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{min: float, median: float, p95: float, max: float}
     */
    private function measure(callable $fn, int $iterations): array
    {
        // Warm-up — exclude JIT / autoload spikes
        for ($i = 0; $i < 10; $i++) {
            $fn();
        }

        $samples = [];
        for ($i = 0; $i < $iterations; $i++) {
            $t0 = microtime(true);
            $fn();
            $samples[] = (microtime(true) - $t0) * 1000.0;
        }
        sort($samples);

        $count = count($samples);
        $p95Idx = max(0, (int) ceil($count * 0.95) - 1);
        $medIdx = (int) floor($count / 2);

        return [
            'min'    => $samples[0],
            'median' => $samples[$medIdx],
            'p95'    => $samples[$p95Idx],
            'max'    => $samples[$count - 1],
        ];
    }
}
