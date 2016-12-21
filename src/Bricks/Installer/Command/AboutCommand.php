<?php

/*
 * This file is part of the Bricks Installer package.
 *
 * @author Fabien Potencier <fabien@symfony.com
 * @author Helmut Hoffer von Ankershoffen <hhva@20steps.de>
 */

namespace Bricks\Installer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Bricks\Installer\Manager\ComposerManager;

/**
 * This command provides information about the Bricks Installer.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class AboutCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
    	parent::configure();
        $this
            ->setName('about')
            ->setDescription('About this installer')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $commandHelp = <<<COMMAND_HELP

 Bricks Installer (%s)
 %s

 This is the official installer to start new projects based on the
 Bricks platform.

 To create a new project called <info>blog</info> in the current directory using
 the <info>latest stable version</info> of Bricks, execute the following command:

   <comment>%s new blog</comment>

 Create a project based on the <info>Bricks Long Term Support version</info> (LTS):

   <comment>%3\$s new blog lts</comment>

 Create a project based on a <info>specific Bricks branch</info>:

   <comment>%3\$s new blog 3.0</comment> or <comment>%3\$s new blog 3.1</comment>

 Create a project based on a <info>specific Bricks version</info>:

   <comment>%3\$s new blog 3.0.0</comment> or <comment>%3\$s new blog 3.0.1</comment>

 Create a <info>demo application</info> to learn how a Bricks application works:

   <comment>%3\$s demo</comment>

COMMAND_HELP;

        // show the self-update information only when using the PHAR file
        if ('phar://' === substr(__DIR__, 0, 7)) {
            $commandUpdateHelp = <<<COMMAND_UPDATE_HELP

 Updating the Bricks Installer
 ------------------------------

 New versions of the Bricks Installer are released regularly. To <info>update your
 installer</info> version, execute the following command:

   <comment>%3\$s self-update</comment>

COMMAND_UPDATE_HELP;

            $commandHelp .= $commandUpdateHelp;
        }

        $output->writeln(sprintf($commandHelp,
            $this->appVersion,
            str_repeat('=', 20 + strlen($this->appVersion)),
            $this->getExecutedCommand()
        ));
    }

    /**
     * Returns the executed command.
     *
     * @return string The executed command
     */
    private function getExecutedCommand()
    {
        $pathDirs = explode(PATH_SEPARATOR, $_SERVER['PATH']);
        $executedCommand = $_SERVER['PHP_SELF'];
        $executedCommandDir = dirname($executedCommand);

        if (in_array($executedCommandDir, $pathDirs)) {
            $executedCommand = basename($executedCommand);
        }

        return $executedCommand;
    }
}
