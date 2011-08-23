CREATE TABLE IF NOT EXISTS "scope"(
  "id"       INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL UNIQUE,
  "uuid"     VARCHAR NOT NULL UNIQUE,                       -- unique is very important --
  "appname"  VARCHAR NOT NULL,
  "expires" DATETIME NOT NULL, -- expires on next load --
  "updated" DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS "cache"(
  "id"      INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL UNIQUE,
  "path"    VARCHAR  NOT NULL UNIQUE,
  "mtime"   DATETIME NOT NULL,
  "updated" DATETIME NOT NULL
);