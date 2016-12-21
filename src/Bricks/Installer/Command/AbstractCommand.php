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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Filesystem\Filesystem;

use Bricks\Installer\Manager\ComposerManager;
use Bricks\Installer\Manager\BricksManager;

/**
 * Abstract Bricks Command.
 *
 * @author Helmut Hoffer von Ankershoffen <hhva@20steps.de>
 */
class AbstractCommand extends Command
{
    /**
     * @var string The current version of the Bricks Installer
     */
    protected $appVersion;
	
	/**
	 * @var string The project dir
	 */
	protected $projectDir;
	
	/**
	 * @var string The project name
	 */
	protected $projectName;
	
	/**
	 * @var Filesystem To dump content to a file
	 */
	protected $fs;
	
	/** @var ComposerManager */
	protected $composerManager;
	
	/** @var BricksrManager */
	protected $bricksManager;
	
	/**
     * Constructor.
     *
     * @param string $appVersion The current version of the Bricks Installer
     */
    public function __construct($appVersion)
    {
        parent::__construct();

        $this->appVersion = $appVersion;
    }
	
	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->addArgument('directory', InputArgument::OPTIONAL, 'Directory of the Bricks project.', '.')
		;
	}
	/**
	 * {@inheritdoc}
	 */
	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		parent::initialize($input, $output);
		
		$this->fs = new Filesystem();
		$directory = rtrim(trim($input->getArgument('directory')), DIRECTORY_SEPARATOR);
		$this->projectDir = $this->fs->isAbsolutePath($directory) ? $directory : getcwd().DIRECTORY_SEPARATOR.$directory;
		$this->projectName = basename($directory);
		
		$this->composerManager = new ComposerManager($this->projectDir);
		$this->bricksManager = new BricksManager($this->projectDir);
		
		$bricksDetected = $this->composerManager->detectBricks();
		if ($bricksDetected) {
			$output->writeln("\n Detected bricks project in [".$this->projectDir."]\n");
		} else {
			$output->writeln("\n No bricks project detected in [".$this->projectDir."]\n");
		}
	}
	
    
}
