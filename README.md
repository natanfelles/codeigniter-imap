# IMAP Libray to CodeIgniter

This files enables you to use the IMAP protocol with the CodeIgniter Framework.

Perfect to create your own webmail.

## How to use

Load the Imap library:

```php
$this->load->library('imap');
```

Set the user login config:

```php
$config = array(
			'host'     => 'imap-mail.outlook.com',
			'encrypto' => 'ssl',
			'user'     => 'phpimapclient@outlook.com',
			'pass'     => 'Abcd12345**'
		);
```

Initialize the connection:

```php
$this->imap->imap_connect($config);
```

Get the required datas:

```php
$folders = $this->imap->get_folders();
```

## Output example

![Output Example](https://raw.githubusercontent.com/natanfelles/codeigniter-imap/master/output-example.png)