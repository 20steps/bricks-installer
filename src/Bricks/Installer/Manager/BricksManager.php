<?php

namespace Bricks\Installer\Manager;

use Symfony\Component\Filesystem\Filesystem;

class BricksManager
{
    private $projectDir;
    private $fs;

    public function __construct($projectDir)
    {
        $this->projectDir = $projectDir;
        $this->fs = new Filesystem();
    }
}
