<?php

/*
 * This file is part of the Bricks Installer package.
 *
 * (c) Helmut Hoffer von Ankershoffen <hhva@20steps.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bricks\Installer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application as ConsoleApplication;

/**
 * This is main Bricks Installer console application class.
 *
 * @author Jerzy Zawadzki <zawadzki.jerzy@gmail.com>
 * @author Helmut Hoffer von Ankershoffen <hhva@20steps.de>
 */
class Application extends ConsoleApplication
{
    const VERSIONS_URL = 'https://bricks.20steps.de/versions/bricks-installer.json';

    /**
     * {@inheritdoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        return parent::doRun($input, $output);
    }
}
