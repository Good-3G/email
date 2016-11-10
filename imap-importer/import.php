<?php

set_time_limit(1800);

require '../vendor/autoload.php';

require 'ImapSqlite.php';
require 'MimeParser.php';
require 'NestedSet.php';

require 'CONFIG.php';

define('START', microtime(true));

// set up db
$db = initDB(DB_NAME, DB_OPTS);

// thread cache
$threads = [];

// IMAP server -> db & extract attachments
fetchMail($db, $threads);

// use thread cache to set up MPTT & adjacency fields
linkThreads($db, $threads);

// build fulltext indicies
buildIndices($db);

log2("Done!");