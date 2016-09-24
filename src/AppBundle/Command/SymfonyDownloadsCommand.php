<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * This file is part of the 500million package.
 *
 * (c) Mátyás Somfai <somfai.matyas@gmail.com>
 * Created at 2016.09.22.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class SymfonyDownloadsCommand extends ContainerAwareCommand
{
    /**
     * The downloads goal.
     *
     * @var int
     */
    const GOAL = 500000000;

    /**
     * The timestamp of the last stats.
     *
     * @var int
     */
    private $updatedAt = 1474525501;

    /**
     * The total download count so far.
     *
     * @var int
     */
    private $downloads = 495733451;

    /**
     * The current download rate.
     *
     * @var int
     */
    private $perSecond = 12.219936728395;

    /**
     * Timestamp of the last refresh.
     *
     * @var int
     */
    private $refreshedAt = 0;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('symfony:downloads')
            ->setDescription('Displays the total Symfony downloads progressbar.')
            ->addOption('refresh', 'r', InputOption::VALUE_REQUIRED, 'Data refresh interval in seconds.', 600);
    }

    /**
     * {@inheritdoc}
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->eagerlyWait($input, $output);
        $this->celebrate($output);
    }

    /**
     * Calculates and renders the remaining time until the goal is reached.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function eagerlyWait(InputInterface $input, OutputInterface $output)
    {
        $progress = new ProgressBar($output, static::GOAL);
        $progress->setFormat(
            " %current%/%max% [%bar%] %percent:3s%%\nEstimated remaining time \xF0\x9F\x8E\x89  %eta%"
        );
        $progress->setMessage('-', 'eta');
        $progress->start();

        $downloads = $this->downloads;
        while ($downloads < static::GOAL) {
            $downloads = $this->calculateCurrentDownloads($input->getOption('refresh'), $output);
            $eta = $this->calculateRemainingTime($downloads);
            $progress->setMessage($eta, 'eta');
            $progress->setProgress($downloads);
            sleep(1);
        }

        $progress->finish();
    }

    /**
     * Displays celebration message.
     *
     * @param OutputInterface $output
     */
    protected function celebrate(OutputInterface $output)
    {
        $beer = "\xF0\x9F\x8D\xBA  ";
        $beers = "\xF0\x9F\x8D\xBB  ";
        $partyPopper = "\xF0\x9F\x8E\x89  ";
        $clap = "\xF0\x9F\x91\x8F  ";
        $confetti = "\xF0\x9F\x8E\x8A  ";
        $line = '';
        for ($i = 0; $i < 3; $i++) {
            $line .= $beer . $partyPopper . $beers . $clap . $confetti;
        }
        $output->writeln("\n\n" . $line . "\n");
        $output->writeln("   <info>500 million Symfony downloads reached!</info> ");
        $output->writeln("\n" . $line . "\n");
        $process = new Process('say -v "Good News" "500 million Symfony downloads reached!"');
        $process->run();
        if (!$process->isSuccessful()) {
            for ($i = 0; $i < 5; $i++) {
                $output->write("\x07");
                usleep(500000);
            }
        }
    }

    /**
     * Calculates the current total downloads.
     *
     * @param $refresh
     * @param OutputInterface $output
     * @return int
     */
    protected function calculateCurrentDownloads($refresh, OutputInterface $output)
    {
        if ($this->refreshedAt < time() - $refresh) {
            $this->refreshData($output);
        }
        $downloads = (int) round($this->downloads + $this->perSecond * (time() - $this->updatedAt));

        return $downloads;
    }

    /**
     * Refreshes the stats data.
     *
     * @param OutputInterface $output
     */
    protected function refreshData(OutputInterface $output)
    {
        if (time() % 60 > 0) {
            // Refresh max. once in a minute.
            return;
        }

        $output->writeln(
            "\nRefreshing data from https://symfony.com/500million\n",
            OutputInterface::VERBOSITY_VERBOSE
        );

        $content = file_get_contents('https://symfony.com/500million');
        if (false === $content) {
            $output->writeln(
                "\nCould not download stats page.\n",
                OutputInterface::VERBOSITY_VERBOSE
            );
            return;
        }

        $pattern = '/var stats = (.*);/';
        $result = preg_match($pattern, $content, $matches);
        if (false === $result) {
            $output->writeln(
                "\nCould not find stats.\n",
                OutputInterface::VERBOSITY_VERBOSE
            );
            return;
        }
        $stats = json_decode($matches[1]);
        if (!isset($stats->total->downloads, $stats->total->perSecond, $stats->updatedAt)) {
            $output->writeln(
                "\nCould not find required data.\n",
                OutputInterface::VERBOSITY_VERBOSE
            );
            return;
        }
        $this->downloads = $stats->total->downloads;
        $this->perSecond = $stats->total->perSecond;
        $this->updatedAt = $stats->updatedAt;
        $this->refreshedAt = time();
    }

    /**
     * Calculates the remaining time based on the current downloads and rate.
     *
     * @param $downloads
     * @return int
     */
    protected function calculateRemainingTime($downloads)
    {
        if (static::GOAL <= $downloads) {
            $eta = 0;
        } else {
            $eta = (int)round((static::GOAL - $downloads) / $this->perSecond);
        }

        return $this->formatEta($eta);
    }

    /**
     * Formats the remaining time to HH:MM:SS format.
     *
     * @param $seconds
     * @return string
     */
    protected function formatEta($seconds)
    {
        $hours = (int) floor($seconds / 3600);
        $seconds -= $hours * 3600;
        $minutes = (int) floor($seconds / 60);
        $seconds -= $minutes * 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}
