## IMAP -> SQLite Importer

### Requirements:

- IMAP server access. For Gmail:
	- Enable IMAP [1.0]
	- Enable direct IMAP access (without an OAuth 2.0 layer) [1.1]
	- Configure which labels show up in IMAP [1.2]
- PHP7
- enabled php_mbstring
- enabled php_imap
- enabled php_mailparse [2]
- enabled php_pdo_sqlite that's FTS5-capable (can be grabbed from recent PHP7 snapshot builds [3])
- installed html2text/html2text [4]


### Usage

0. `composer install`
0. edit `CONFIG.php` as needed
0. `php import.php`


[1.0] https://mail.google.com/mail/u/0/#settings/fwdandpop
[1.1] https://www.google.com/settings/security/lesssecureapps
[1.2] https://mail.google.com/mail/u/0/#settings/labels
[2] https://pecl.php.net/package/mailparse
[3] http://windows.php.net/snapshots/#php-7.0
[4] https://packagist.org/packages/html2text/html2text