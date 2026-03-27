<?php
/**
 * Admin Helper attachment AI scan CLI command.
 *
 * @package bastien59960/adminhelper
 * @license GPL-2.0-only
 */

namespace bastien59960\adminhelper\console;

use bastien59960\adminhelper\service\attachment_ai_manager;
use phpbb\console\command\command;
use phpbb\user;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class attachment_ai_scan extends command
{
    /** @var attachment_ai_manager */
    protected $manager;

    public function __construct(user $user, attachment_ai_manager $manager)
    {
        $this->manager = $manager;

        parent::__construct($user);
    }

    protected function configure()
    {
        $this
            ->setName('adminhelper:attachment-ai-scan')
            ->setDescription('Scan forum image attachments and flag AI-generated images in Admin Helper.')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Batch size per scan pass (default: 500).', 500)
            ->addOption('max-seconds', null, InputOption::VALUE_REQUIRED, 'Optional total time budget in seconds (0 = run until completion).', 0)
            ->addOption('sleep-ms', null, InputOption::VALUE_REQUIRED, 'Optional pause between passes in milliseconds.', 0)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $batch = max(1, min(1000, (int) $input->getOption('batch')));
        $max_seconds = max(0, (int) $input->getOption('max-seconds'));
        $sleep_ms = max(0, (int) $input->getOption('sleep-ms'));

        $stats_before = $this->manager->get_scan_stats();

        $io->title('Admin Helper - AI attachment scan');
        $io->writeln('Total image attachments  : <info>' . (int) ($stats_before['attachments_candidates'] ?? 0) . '</info>');
        $io->writeln('Already processed        : <info>' . (int) ($stats_before['attachments_processed'] ?? 0) . '</info>');
        $io->writeln('Remaining                : <info>' . (int) ($stats_before['attachments_remaining'] ?? 0) . '</info>');
        $io->writeln('Batch size               : <info>' . $batch . '</info>');
        $io->writeln('Time budget              : <info>' . ($max_seconds > 0 ? $max_seconds . 's' : 'unlimited') . '</info>');
        $io->newLine();

        if ((int) ($stats_before['attachments_remaining'] ?? 0) === 0) {
            $io->success('No remaining image attachments to scan.');
            return 0;
        }

        $started_at = microtime(true);
        $passes = 0;
        $processed = 0;
        $detected = 0;
        $clean = 0;
        $errors = 0;
        $timed_out = false;

        while (true) {
            $elapsed = microtime(true) - $started_at;
            if ($max_seconds > 0 && $elapsed >= $max_seconds) {
                $timed_out = true;
                break;
            }

            $pass_budget = ($max_seconds > 0)
                ? max(1.0, min(15.0, $max_seconds - $elapsed))
                : 15.0;

            $result = $this->manager->scan_attachment_batch($batch, $pass_budget);
            $passes++;
            $processed += (int) ($result['processed'] ?? 0);
            $detected += (int) ($result['detected'] ?? 0);
            $clean += (int) ($result['clean'] ?? 0);
            $errors += (int) ($result['errors'] ?? 0);

            $io->writeln(sprintf(
                'Pass %d | run %+d (detected %d / clean %d / errors %d) | global %d/%d | remaining %d',
                $passes,
                (int) ($result['processed'] ?? 0),
                (int) ($result['detected'] ?? 0),
                (int) ($result['clean'] ?? 0),
                (int) ($result['errors'] ?? 0),
                (int) ($result['processed_total'] ?? 0),
                (int) ($result['total_candidates'] ?? 0),
                (int) ($result['remaining'] ?? 0)
            ));

            if ((int) ($result['processed'] ?? 0) === 0 || (int) ($result['remaining'] ?? 0) === 0) {
                $timed_out = $timed_out || !empty($result['timed_out']);
                break;
            }

            if ($sleep_ms > 0) {
                usleep($sleep_ms * 1000);
            }
        }

        $elapsed_total = microtime(true) - $started_at;
        $stats_after = $this->manager->get_scan_stats();

        $io->newLine();
        $io->success(sprintf(
            'Scan finished in %.2fs. Processed in run: %d (detected: %d, clean: %d, errors: %d). Global progress: %d/%d, remaining: %d.',
            $elapsed_total,
            $processed,
            $detected,
            $clean,
            $errors,
            (int) ($stats_after['attachments_processed'] ?? 0),
            (int) ($stats_after['attachments_candidates'] ?? 0),
            (int) ($stats_after['attachments_remaining'] ?? 0)
        ));

        if ($timed_out && (int) ($stats_after['attachments_remaining'] ?? 0) > 0) {
            $io->warning('Time budget reached before completion. Re-run the command to continue.');
        }

        return 0;
    }
}
