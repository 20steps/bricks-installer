<?php

/*
 * This file is part of the Bricks Installer package.
 *
 * (c) Helmut Hoffer von Ankershoffen <hhva@20steps.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bricks\Installer\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Bricks\Installer\Exception\AbortException;
use Bricks\Installer\Manager\ComposerManager;

/**
 * This command creates new Bricks projects for the given Bricks version.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Helmut Hoffer von Ankershoffen <hhva@20steps.de>
 */
class NewCommand extends AbstractDownloadCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
    	parent::configure();
        $this
            ->setName('new')
            ->setDescription('Creates a new Bricks project')
            ->addArgument('version', InputArgument::OPTIONAL, 'The Bricks version to be installed (defaults to the latest stable version).', 'latest')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $directory = rtrim(trim($input->getArgument('directory')), DIRECTORY_SEPARATOR);
        $this->version = trim($input->getArgument('version'));
        $this->projectDir = $this->fs->isAbsolutePath($directory) ? $directory : getcwd().DIRECTORY_SEPARATOR.$directory;
        $this->projectName = basename($directory);

        $this->composerManager = new ComposerManager($this->projectDir);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this
                ->checkInstallerVersion()
                ->checkProjectName()
                ->checkBricksVersionIsInstallable()
                ->checkPermissions()
                ->download()
                ->extract()
                ->cleanUp()
                ->dumpReadmeFile()
                ->updateParameters()
                ->updateComposerConfig()
                ->createGitIgnore()
                ->checkBricksRequirements()
                ->displayInstallationResult()
            ;
        } catch (AbortException $e) {
            aborted:

            $output->writeln('');
            $output->writeln('<error>Aborting download and cleaning up temporary directories.</>');

            $this->cleanUp();

            return 1;
        } catch (\Exception $e) {
            // Guzzle can wrap the AbortException in a GuzzleException
            if ($e->getPrevious() instanceof AbortException) {
                goto aborted;
            }

            $this->cleanUp();
            throw $e;
        }
    }

    /**
     * Checks whether the given Bricks version is installable by the installer.
     * Due to the changes introduced in the Icu/Intl components
     * (see http://symfony.com/blog/new-in-symfony-2-6-farewell-to-icu-component)
     * not all the previous Symfony versions are installable by the installer.
     *
     * The rules to decide if the version is installable are as follows:
     *
     *   - 2.0, 2.1, 2.2 and 2.4 cannot be installed because they are unmaintained.
     *   - 2.3 can be installed starting from version 2.3.21 (inclusive)
     *   - 2.5 can be installed starting from version 2.5.6 (inclusive)
     *   - 2.6, 2.7, 2.8 and 2.9 can be installed regardless the version.
     *
     * @return $this
     *
     * @throws \RuntimeException If the given Bricks version is not compatible with this installer
     */
    protected function checkBricksVersionIsInstallable()
    {
        // validate the given version syntax
        if (!preg_match('/^latest|lts|[2-9]\.\d(?:\.\d{1,2})?(?:-(?:dev|BETA\d*|RC\d*))?$/i', $this->version)) {
            throw new \RuntimeException(sprintf(
                "The Bricks version can be a branch number (e.g. 2.8), a full version\n".
                "number (e.g. 3.1.4), a special word ('latest' or 'lts') and a unstable\n".
                "version number (e.g. 3.2.0-rc1) but '%s' was given.", $this->version
            ));
        }

        // Get the full list of Bricks versions to check if it's installable
        $client = $this->getGuzzleClient();
        $bricksVersions = json_decode($client->get('https://bricks.20steps.de/versions/bricks-platform.json')->getBody(),true);
        if (empty($bricksVersions)) {
            throw new \RuntimeException(
                "There was a problem while downloading the list of Bricks versions from\n".
                "20steps.de. Check that you are online and the following URL is accessible:\n\n".
                'https://bricks.20steps.de/versions/bricks-platform.json'
            );
        }

        // if a branch number is used, transform it into a real version number
        if (preg_match('/^[2-9]\.\d$/', $this->version)) {
            if (!isset($bricksVersions[$this->version])) {
                throw new \RuntimeException(sprintf(
                    "The selected branch (%s) does not exist, or is not maintained.\n".
                    "To solve this issue, install Bricks with the latest stable release:\n\n".
                    '%s %s %s',  $this->version, $_SERVER['PHP_SELF'], $this->getName(), $this->projectDir
                ));
            }

            $this->version = $bricksVersions[$this->version];
        }

        // if a special version name is used, transform it into a real version number
        if (in_array($this->version, array('latest', 'lts'))) {
            $this->version = $bricksVersions[$this->version];
        }

        // versions are case-sensitive in the download server (3.1.0-rc1 must be 3.1.0-RC1)
        if ($isUnstableVersion = preg_match('/^.*\-(BETA|RC)\d*$/i', $this->version)) {
            $this->version = strtoupper($this->version);
        }

        $isNonInstallable = in_array($this->version, $bricksVersions['non_installable']);
        $isInstallable = in_array($this->version, $bricksVersions['installable']);

        // installable and non-installable versions are explicitly declared in the
        // list of versions; there is an edge-case: unstable versions are not listed
        // and they are generally installable (e.g. 3.1.0-RC1)
        if ($isNonInstallable || (!$isInstallable && !$isUnstableVersion)) {
            throw new \RuntimeException(sprintf(
                "The selected version (%s) cannot be installed because it is not compatible\n".
                "with this installer or because it hasn't been published as a package yet.\n".
                "To solve this issue install Bricks manually executing the following command:\n\n".
                'composer create-project bricks/platform-standard-edition %s %s',
                $this->version, $this->projectDir, $this->version
            ));
        }

        // check that the system has the PHP version required by The Bricks version to be installed
        if (version_compare($this->version, '3.0.0', '>=') && version_compare(phpversion(), '5.5.9', '<')) {
            throw new \RuntimeException(sprintf(
                "The selected version (%s) cannot be installed because it requires\n".
                "PHP 5.5.9 or higher and your system has PHP %s installed.\n",
                $this->version, phpversion()
            ));
        }

        if ($isUnstableVersion) {
            $this->output->writeln("\n <bg=red> WARNING </> You are downloading an unstable Bricks version.");
        }

        return $this;
    }

    /**
     * Removes all the temporary files and directories created to
     * download the project and removes Bricks-related files that don't make
     * sense in a proprietary project.
     *
     * @return $this
     */
    protected function cleanUp()
    {
	    $this->output->writeln(" Cleaning up ...\n");

        $this->fs->remove(dirname($this->downloadedFilePath));

        try {
            $licenseFile = array($this->projectDir.'/LICENSE');
            $upgradeFiles = glob($this->projectDir.'/UPGRADE*.md');
            $changelogFiles = glob($this->projectDir.'/CHANGELOG*.md');

            $filesToRemove = array_merge($licenseFile, $upgradeFiles, $changelogFiles);
            $this->fs->remove($filesToRemove);
        } catch (\Exception $e) {
            // don't throw an exception in case any of the Bricks-related files cannot
            // be removed, because this is just an enhancement, not something mandatory
            // for the project
        }

        return $this;
    }

    /**
     * It displays the message with the result of installing Bricks
     * and provides some pointers to the user.
     *
     * @return $this
     */
    protected function displayInstallationResult()
    {
        if (empty($this->requirementsErrors)) {
            $this->output->writeln(sprintf(
                " <info>%s</info>  Bricks %s was <info>successfully installed</info>. Now you can:\n",
                defined('PHP_WINDOWS_VERSION_BUILD') ? 'OK' : '✔',
                $this->getInstalledBricksVersion()
            ));
        } else {
            $this->output->writeln(sprintf(
                " <comment>%s</comment>  Bricks %s was <info>successfully installed</info> but your system doesn't meet its\n".
                "     technical requirements! Fix the following issues before executing\n".
                "     your Bricks application:\n",
                defined('PHP_WINDOWS_VERSION_BUILD') ? 'FAILED' : '✕',
                $this->getInstalledBricksVersion()
            ));

            foreach ($this->requirementsErrors as $helpText) {
                $this->output->writeln(' * '.$helpText);
            }

            $checkFile = $this->isBricks3() ? 'bin/symfony_requirements' : 'app/check.php';

            $this->output->writeln(sprintf(
                " After fixing these issues, re-check Bricks requirements executing this command:\n\n".
                "   <comment>php %s/%s</comment>\n\n".
                " Then, you can:\n",
                $this->projectName, $checkFile
            ));
        }

        if ('.' !== $this->projectDir) {
            $this->output->writeln(sprintf(
                "    * Change your current directory to <comment>%s</comment>\n", $this->projectDir
            ));
        }

        $consoleDir = ($this->isBricks3() ? 'bin' : 'app');
        $serverRunCommand = version_compare($this->version, '2.6.0', '>=') && extension_loaded('pcntl') ? 'server:start' : 'server:run';

        $this->output->writeln(sprintf(
            "    * List available commands by executing <comment>bin/console</comment>.\n\n".
            "    * Run using Docker:\n".
            "        1. Build web and database container once by executing <comment>docker-compose build</comment> (this will take some time)\n".
            "        1. Run <comment>docker-compose up</comment>\n".
            "        2. Browse to the <comment>http://localhost:8000</comment> URL.\n\n".
            "    * Prepare a locally running MySQL database:\n".
            "    * Run using the integrated PHP webserver:\n".
            "        1. Run <comment>%s/console %s</comment>.\n".
            "        2. Browse to the <comment>http://localhost:8000</comment> URL.\n\n".
            "     * Run using the PHP Process Manager:\n".
	        "        1. Run <comment>vendor/bin/ppm start --bootstrap=symfony --app-env=dev  --logging=0 --debug=0 --workers=20 --socket-path=var/ppm/run/ --port=8000</comment>\n".
            "        2. Browse to the <comment>http://localhost:8000</comment> URL.\n\n".
            "    * Read the documentation at <comment>doc/getting-started.md</comment>\n",
            $consoleDir, $serverRunCommand
        ));

        return $this;
    }

    /**
     * Dump a basic README.md file.
     *
     * @return $this
     */
    protected function dumpReadmeFile()
    {
	    $this->output->writeln(" Generating README.md ...\n");

        $readmeContents = sprintf("%s\n%s\n\nBricks project created on %s.\n", $this->projectName, str_repeat('=', strlen($this->projectName)), date('F j, Y, g:i a'));
        try {
            $this->fs->dumpFile($this->projectDir.'/README.md', $readmeContents);
        } catch (\Exception $e) {
            // don't throw an exception in case the file could not be created,
            // because this is just an enhancement, not something mandatory
            // for the project
        }

        return $this;
    }

    /**
     * Updates the Bricks parameters.yml file to replace default configuration
     * values with better generated values.
     *
     * @return $this
     */
    protected function updateParameters()
    {
	    $this->output->writeln(" Updating app/config/parameters.yml ...\n");

        $filename = $this->projectDir.'/app/config/parameters.yml';

        if (!is_writable($filename)) {
            if ($this->output->isVerbose()) {
                $this->output->writeln(sprintf(
                    " <comment>[WARNING]</comment> The value of the <info>secret</info> configuration option cannot be updated because\n".
                    " the <comment>%s</comment> file is not writable.\n",
                    $filename
                ));
            }

            return $this;
        }

        $ret = str_replace('ThisTokenIsNotSoSecretChangeIt', $this->generateRandomSecret(), file_get_contents($filename));
        file_put_contents($filename, $ret);

        return $this;
    }

    /**
     * Updates the composer.json file to provide better values for some of the
     * default configuration values.
     *
     * @return $this
     */
    protected function updateComposerConfig()
    {
	    $this->output->writeln(" Updating composer.json ...\n");

        parent::updateComposerConfig();
        $this->composerManager->updateProjectConfig([
            'name' => $this->composerManager->createPackageName($this->projectName),
            'license' => 'proprietary',
            'description' => null,
            'extra' => ['branch-alias' => null],
        ]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDownloadedApplicationType()
    {
        return 'Bricks';
    }

    /**
     * {@inheritdoc}
     */
    protected function getRemoteFileUrl()
    {
        return 'https://bricks.20steps.de/downloads/bricks-platform-standard-edition_'.$this->version;
    }
}
