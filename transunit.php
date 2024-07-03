<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;

(new SingleCommandApplication())
    ->setName('Transunit')

    ->addArgument('source', InputArgument::REQUIRED, 'Location of PhpSpec tests to convert.')
    ->addArgument('destination', InputArgument::REQUIRED, 'Export directory for converted PHPUnit test.')

    ->setCode(function (InputInterface $input, OutputInterface $output): int {
        $fs = new \Symfony\Component\Filesystem\Filesystem();

        $source = $input->getArgument('source');
        $destination = $input->getArgument('destination');

        if (!$fs->isAbsolutePath($source)) {
            $source = getcwd().'/'.$source;
        }

        if (!$fs->isAbsolutePath($destination)) {
            $destination = getcwd().'/'.$destination;
        }

        \Transunit\Transunit::create()->run($source, $destination);

        return Command::SUCCESS;
    })

    ->run();
