=======================================
GIRO Codename Palmera v2, PHP Framework
=======================================

Phew! A big commit after a long while.

This is by no means a complete solution, it is by far a work in progress and it must be considered ALPHA software, since it hasn't been tested outside my production and testing environments.

Please, if you clone, drop me a line, share your thoughts, I've spent many hours developing this, It would be cool to get some feedback for a change.

I originally developed a library that would automagically use all the comments on the framework and generated the documentation, but the framework has changed so much lately that right now it's pretty much useless, and since I'm not exactly your standards-following-commenter here, I really doubt you could generate any useful documentation with software like PHPDOC, the next big todo on my list is to unify and clarify the documentation, not just for you, I'm growing old you guys, my memory isn't the same. 

The last commit included the introduction of the SQLITE PDO object for handling caché on external files, at the time it seemed like a great idea, but, the original motto of this was to use as little dependencies as possible, and this decision goes against that, I plan to remove that functionallity in future commits and leave the DB library just for MVC controlling and not the framework itself. Having said that, the idea of having a database controlling everything on the framework is pretty attractive, it would give an impressive flexibility boost for its configuration and expansion. anyways, no one reads this, so,  enough of silly explanaition for my bad decision-taking.

Changelog
---------
- Reorganized Core Libraries.
- Reorganized the Application Library, several methods were rewritten and adapted to this "new methodology".
- Moved the Database library from LIBS to CORE, since it's now used by the framework for caché checking.
- Finally Added support for the MySQL PDO Driver.
- Database Library now uses properly the "prepare statement" for execution and querying.
- Enabled SQL exporting for MySQL driver.
- Enabled SQL importing for both SQLite and MySQL
- External file handling was moved outside the Application library to its own class named Application_external.
- Application_View Library it's now in charge of rendering and preparing-sharing scope between Views and external files.
- External files directly depending on Views are now minified and gzipped on the fly and then cached. The user can opt to not cache these files and use them dynamically, but since they are compressed each time, seems like a bad idea.
- Introduced basic HTML templating, user can now specify their templates and add elements from the Controller.
- Added a template for HTML5 and google analytics.

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

