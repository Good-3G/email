## IMAP -> SQLite Importer

### Requirements:

- IMAP server access. For Gmail:
	- Enable IMAP [1]
	- Enable direct IMAP access (without an OAuth 2.0 layer) [2]
	- Configure which labels show up in IMAP [3]
- PHP7
- enabled php_mbstring
- enabled php_imap
- enabled php_mailparse [4]
- enabled php_pdo_sqlite that's FTS5-capable (can be grabbed from recent PHP7 snapshot builds [5])
- installed html2text/html2text [6]


### Usage

0. `composer install`
0. edit `CONFIG.php` as needed
0. `php import.php`


[1] https://mail.google.com/mail/u/0/#settings/fwdandpop  
[2] https://www.google.com/settings/security/lesssecureapps  
[3] https://mail.google.com/mail/u/0/#settings/labels  
[4] https://pecl.php.net/package/mailparse  
[5] http://windows.php.net/snapshots/#php-7.0  
[6] https://packagist.org/packages/html2text/html2text  