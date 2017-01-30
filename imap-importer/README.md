## IMAP -> SQLite Importer

### Purpose

These scripts will connect to an IMAP mail server, open mailbox(s), download messages & extract attachments into directories, then index their contents & metadata into an SQLite database using FTS5 fulltext indices.

### Requirements

- IMAP server access. For Gmail:
	- Enable IMAP [1]
	- Enable direct IMAP access (without an OAuth 2.0 layer) [2]
	- Configure which labels show up in IMAP [3]
- PHP7.1 w/extensions: php_mbstring, php_imap, php_pdo_sqlite, php_mailparse [4]

### Usage

0. `composer install`
0. edit `CONFIG.php` as needed
0. `php import.php`

[1] https://mail.google.com/mail/u/0/#settings/fwdandpop  
[2] https://www.google.com/settings/security/lesssecureapps  
[3] https://mail.google.com/mail/u/0/#settings/labels  
[4] https://pecl.php.net/package/mailparse  