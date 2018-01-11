<?php
defined('BASEPATH') || exit('No direct script access allowed');

$config['encrypto'] = 'tls';
$config['validate'] = true;
$config['host']     = 'domain.tld';
$config['port']     = 143;
$config['username'] = 'user@domain.tld';
$config['password'] = 'password';

$config['folders'] = [
	'inbox'  => 'INBOX',
	'sent'   => 'Sent',
	'trash'  => 'Trash',
	'spam'   => 'Spam',
	'drafts' => 'Drafts',
];

$config['expunge_on_disconnect'] = false;

$config['cache'] = [
	'active'     => false,
	'adapter'    => 'file',
	'backup'     => 'file',
	'key_prefix' => 'imap:',
	'ttl'        => 60,
];
