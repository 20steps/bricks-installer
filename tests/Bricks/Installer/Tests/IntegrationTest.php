<?php

/*
 * This file is part of the Bricks Installer package.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Helmut Hoffer von Ankershoffen <hhva@20steps.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bricks\Installer\Tests;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ProcessUtils;

class IntegrationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string The root directory
     */
    private $rootDir;

    /**
     * @var Filesystem The Filesystem component
     */
    private $fs;

    public function setUp()
    {
        $this->rootDir = realpath(__DIR__.'/../../../../');
        $this->fs = new Filesystem();

        if (!$this->fs->exists($this->rootDir.'/bricks.phar')) {
            throw new \RuntimeException(sprintf("Before running the tests, make sure that the Bricks Installer is available as a 'bricks.phar' file in the '%s' directory.", $this->rootDir));
        }
    }

    public function testDemoApplicationInstallation()
    {
        $projectDir = sprintf('%s/my_test_project', sys_get_temp_dir());
        $this->fs->remove($projectDir);

        $output = $this->runCommand(sprintf('php bricks.phar demo %s', ProcessUtils::escapeArgument($projectDir)));
        $this->assertContains('Downloading the Bricks Demo Application', $output);
        $this->assertContains('Bricks Demo Application was successfully installed.', $output);

        $output = $this->runCommand('php bin/console --version', $projectDir);
        $this->assertRegExp('/Symfony version 3\.\d+\.\d+(-DEV)? - app\/dev\/debug/', $output);

        $composerConfig = json_decode(file_get_contents($projectDir.'/composer.json'), true);
    }

    /**
     * @dataProvider provideBricksInstallationData
     */
    public function testBricksInstallation($versionToInstall, $messageRegexp, $versionRegexp, $requiredPhpVersion)
    {
        $projectDir = sprintf('%s/my_test_project', sys_get_temp_dir());
        $this->fs->remove($projectDir);

        $output = $this->runCommand(sprintf('php bricks.phar new %s %s', ProcessUtils::escapeArgument($projectDir), $versionToInstall));
        $this->assertContains('Downloading Bricks...', $output);
        $this->assertRegExp($messageRegexp, $output);

        $output = $this->runCommand('php bin/console --version', $projectDir);

        $this->assertRegExp($versionRegexp, $output);

        $composerConfig = json_decode(file_get_contents($projectDir.'/composer.json'), true);
        $this->assertArrayNotHasKey(
            isset($composerConfig['config']) ? 'platform' : 'config',
            isset($composerConfig['config']) ? $composerConfig['config'] : $composerConfig,
            'The composer.json file does not define any platform configuration.'
        );
    }

    public function testBricksInstallationInCurrentDirectory()
    {
        $projectDir = sprintf('%s/my_test_project', sys_get_temp_dir());
        $this->fs->remove($projectDir);
        $this->fs->mkdir($projectDir);

        $output = $this->runCommand(sprintf('php %s/bricks.phar new . 3.0.0', $this->rootDir), $projectDir);
        $this->assertContains('Downloading Bricks...', $output);

        $output = $this->runCommand('php bin/console --version', $projectDir);
        $this->assertContains('Symfony version 3.0.0 - app/dev/debug', $output);
    }

    /**
     * Runs the given string as a command and returns the resulting output.
     * The CWD is set to the root project directory to simplify command paths.
     *
     * @param string      $command          The name of the command to execute
     * @param null|string $workingDirectory The working directory
     *
     * @return string The output of the command
     *
     * @throws ProcessFailedException If the command execution is not successful
     */
    private function runCommand($command, $workingDirectory = null)
    {
        $process = new Process($command);
        $process->setWorkingDirectory($workingDirectory ?: $this->rootDir);
        $process->mustRun();

        return $process->getOutput();
    }

    /**
     * Provides Bricks installation data.
     *
     * @return array
     */
    public function provideBricksInstallationData()
    {
        return array(
            array(
                '3.0',
                '/.*Bricks 3\.0\.\d+ was successfully installed.*/',
                '/Symfony version 3\.0\.\d+(-DEV)? - app\/dev\/debug/',
                '7.0',
            )
        );
    }
}
