Build phar
==========

1. install box (cp. https://github.com/box-project/box2)
2. possibly increase ulimit (e.g. ulimit -Sn 4096)
3. change cwd to bricks-installer directory
4. trigger build of phar using 
```
box build
```
This should result in a file called bricks.phar in the cwd.

* Inspect .travis.yml for more details