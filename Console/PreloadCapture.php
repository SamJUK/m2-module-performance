<?php
declare(strict_types=1);
// phpcs:disable Magento2.Functions.DiscouragedFunction -- build or shutdown time, no app to inject

namespace SamJUK\Performance\Console;

use SamJUK\Performance\Plugin\CapturePreloadClasses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

// start, browse the storefront (home, PLP, PDP, search, CMS, cart, checkout, account), stop. Commit the list.
class PreloadCapture extends Command
{
    protected function configure(): void
    {
        $this->setName('preload:capture')
            ->setDescription('Record the classes real requests load, for preload:build')
            ->addArgument('action', InputArgument::REQUIRED, 'start or stop')
            ->addOption(
                'classes',
                null,
                InputOption::VALUE_REQUIRED,
                'Class list to merge into',
                'app/etc/preload-classes.txt'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = BP . '/' . CapturePreloadClasses::DIR;
        if ($input->getArgument('action') === 'start') {
            is_dir($dir) || mkdir($dir, 0777, true);
            chmod($dir, 0777); // ponytail: written by the FPM user, read back by whoever runs stop
            $output->writeln("Capturing to $dir. Browse the storefront, then run preload:capture stop.");
            return Command::SUCCESS;
        }

        $list = BP . '/' . $input->getOption('classes');
        $names = is_file($list) ? file($list, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        $requests = glob("$dir/*.txt") ?: [];
        foreach ($requests as $file) {
            array_push($names, ...file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
            unlink($file);
        }
        is_dir($dir) && rmdir($dir);
        $names = array_unique($names);
        sort($names);
        file_put_contents($list, implode("\n", $names) . "\n");
        $output->writeln(sprintf('%d requests merged, %d classes in %s', count($requests), count($names), $list));
        return Command::SUCCESS;
    }
}
