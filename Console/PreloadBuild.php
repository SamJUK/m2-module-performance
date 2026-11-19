<?php
declare(strict_types=1);
// phpcs:disable Magento2.Functions.DiscouragedFunction -- build or shutdown time, no app to inject

namespace SamJUK\Performance\Console;

use SamJUK\Performance\Build\ClassList;
use SamJUK\Performance\Build\DiConstants;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

// Filesystem only (no DB, no env.php), so it runs in a Docker build. Run after setup:di:compile and the
// last composer dump-autoload: di:compile replaces the DI stubs, composer dump undoes the manifest.
class PreloadBuild extends Command
{
    protected function configure(): void
    {
        $this->setName('preload:build')
            ->setDescription('Registration manifest, DI metadata constants and opcache preload list')
            ->addOption('classes', null, InputOption::VALUE_REQUIRED, 'Captured class list', 'app/etc/preload-classes.txt')
            ->addOption('ini', null, InputOption::VALUE_REQUIRED, 'Write the opcache.preload ini here')
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'opcache.preload_user', 'www-data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $meta = BP . '/generated/metadata';
        if (!is_file("$meta/global.php")) {
            $output->writeln('<error>No compiled DI in generated/metadata. Run setup:di:compile first.</error>');
            return Command::FAILURE;
        }

        $manifest = new Process([PHP_BINARY, dirname(__DIR__) . '/bin/registration-manifest.php', BP]);
        if ($manifest->run() !== 0) {
            $error = trim($manifest->getErrorOutput() . $manifest->getOutput());
            $output->writeln('<error>Registration manifest failed: ' . $error . '</error>');
            return Command::FAILURE;
        }
        $output->writeln('Registration manifest: ' . trim($manifest->getOutput()));

        $output->writeln('DI constants: ' . implode(', ', (new DiConstants())->write($meta)));

        $classes = BP . '/' . $input->getOption('classes');
        if (is_file($classes)) {
            [$kept, $dropped] = (new ClassList())->write($classes, "$meta/preload-classes.php");
            $output->writeln(sprintf('Preload classes: %d kept, %d dropped', $kept, count($dropped)));
            foreach ($dropped as $name => $why) {
                $output->writeln("  - $name ($why)", OutputInterface::VERBOSITY_VERBOSE);
            }
        } else {
            $output->writeln("<comment>No class list at $classes: preloading DI constants only. See preload:capture.</comment>");
        }

        if ($ini = $input->getOption('ini')) {
            $preload = dirname(__DIR__) . '/preload.php';
            file_put_contents($ini, "opcache.preload=$preload\nopcache.preload_user={$input->getOption('user')}\n");
            $output->writeln("Wrote $ini. Preload adds ~70MB to opcache: check opcache.memory_consumption.");
        }
        return Command::SUCCESS;
    }
}
