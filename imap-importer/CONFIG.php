<?php

const DB_NAME = 'sqlite:' . __DIR__ . '/email.sqlite3';
const DB_OPTS = [
	PDO::ATTR_ERRMODE				=> PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE	=> PDO::FETCH_ASSOC
];

// http://php.net/manual/en/function.imap-open.php#example-3943
const IMAP_ROOT = '{imap.gmail.com:993/ssl}';
const IMAP_USER = '<username>';
const IMAP_PASS = '<password>';
const IMAP_BOXES = [
	'INBOX',
	'[Gmail]/Sent Mail',
//	'[Gmail]/All Mail',
];

const FETCH_QTY = 200;