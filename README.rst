====================================
GIRO Codename Palmera, PHP Framework
====================================
v2.1r6
^^^^^^

This is by no means a complete solution, it is by far a work in progress and it must be considered ALPHA software, since it hasn't been tested outside my production and testing environments.

Please, if you clone, drop me a line, share your thoughts, I've spent many hours developing this, It would be cool to get some feedback for a change.

I originally developed a library that would automagically use all the comments on the framework and generated the documentation, but the framework has changed so much lately that right now it's pretty much useless, and since I'm not exactly your standards-following-commenter here, I really doubt you could generate any useful documentation with software like PHPDOC, the next big todo on my list is to unify and clarify the documentation, not just for you, I'm growing old you guys, my memory isn't the same. 

The last commit included the introduction of the SQLITE PDO object for handling caché on external files, at the time it seemed like a great idea, but, the original motto of this was to use as little dependencies as possible, and this decision goes against that, I plan to remove that functionallity in future commits and leave the DB library just for MVC controlling and not the framework itself. Having said that, the idea of having a database controlling everything on the framework is pretty attractive, it would give an impressive flexibility boost for its configuration and expansion. anyways, no one reads this, so,  enough of silly explanaition for my bad decision-taking.

Dependencies
------------
- PHP v5.3+
- PDO sqlite3 & mysql*

Installation
------------
- Clone to any directory [or to any folder and then make a symlink] on your web server.
- create a directory named tmp.
- if you are [really] "lucky" it will show you a notice triggered from the default controller.

Notes
-----
- Remember to check the .htaccess file, It is not optimized.

TODO
----
- Test this latest commit on production. [I know, I know]
- Allow users to disable minify OR compress via config.
- Allow users to force the realoading of external view files. [auto deleting temp file after framework stopped].
- Get rid of the Instance Library, it's stupid and an overkill.

Changelog
----------
- `v2.0 Changes <http://github.com/hectormenendez/giro/blob/ab0a5c6508eef24dc19bb04b8235e2accab5928b/README.rst>`_
- `v2.1 Changes <http://github.com/hectormenendez/giro/blob/e608fe6d9f62095c376593d3cdb2bc63031c9ba0/README.rst>`_
- Added Extension Auth, a simple helper for your authentication needs.
- DB->insert() now can update upon duplicates, minor Auth update.
- Fixed a bug where existing external files were being processed as dynamic routes.
- Naming convention change for Library autoloader.
- `ISSUE#4 <http://github.com/hectormenendez/giro/issues/4]>`_ Fixing application_external bug by updating Auth to the new library naming convention.
- Enabled debug mode for dynamic files in Applicaion_External.
