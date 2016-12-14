Building and distributing the installer
=======================================

Prerequisites
-------------
1. Install php7
2. Install [phpunit][1]
3. Install [box][2]
4. Possibly increase ulimit (e.g. ulimit -Sn 4096). Put this in your ~/.bash_profile
```bash
ulimit -Sn 4096
```

Build and test locally
----------------------
1. Change your cwd to bricks-installer directory
2. Trigger the build of bricks.phar and update of /usr/local/bin/bricks by executing
```bash
build/box
```
3. Execute unit tests by executing
```bash
phpunit
```

Test via Travis-CI and distribute
---------------------------------
1. Push to github
2. Inspect test results on [Travis][3] 
3. If all tests pass increase the $appVersion in the file bricks
4. Set the version number in the file versions.json
5. Create a git tag using the version number and push the tag to github by executing
```bash
git commit -am "your commit message"
git tag new_version
git push origin --tags
```
6. Make the bricks.phar available as https://bricks.20steps.de/downloads/installer by executing
```bash
build/upload
```

[1]:  https://phpunit.de/manual/current/en/installation.html
[2]:  https://github.com/box-project/box2
[3]:  https://travis-ci.org/20steps/bricks-installer