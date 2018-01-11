<?php
/**
 * Imap Class
 * This class enables you to use the IMAP Protocol
 *
 * @package    CodeIgniter
 * @subpackage Libraries
 * @category   Email
 * @version    1.0.0-dev
 * @author     Natan Felles
 * @link       http://github.com/natanfelles/codeigniter-imap
 */

defined('BASEPATH') || exit('No direct script access allowed');

/**
 * Class Imap
 */
class Imap
{
	/**
	 * IMAP mailbox - The full "folder" path
	 *
	 * @var string
	 */
	protected $mailbox;

	/**
	 * IMAP stream
	 *
	 * @var resource
	 */
	protected $stream;

	/**
	 * The current folder
	 *
	 * @var string
	 */
	protected $folder = 'INBOX';

	/**
	 * [$search_criteria description]
	 *
	 * @var string
	 */
	protected $search_criteria;

	/**
	 * [$CI description]
	 *
	 * @var CI_Controller
	 */
	protected $CI;

	/**
	 * [$config description]
	 *
	 * @var array
	 */
	protected $config = [];

	/**
	 * [__construct description]
	 *
	 * @param array $config
	 */
	public function __construct(array $config = [])
	{
		$this->CI =& get_instance();

		if (! empty($config))
		{
			$this->config = $config;
			//$this->connect();
		}
	}

	/**
	 * @param array $config Options: host, encrypto, user, pass, port, folders
	 *
	 * @return boolean True if is connected
	 */
	public function connect(array $config = [])
	{
		$config       = array_replace_recursive($this->config, $config);
		$this->config = $config;

		if ($config['cache']['active'] === true)
		{
			$this->CI->load->driver('cache',
				[
				'adapter'    => $config['cache']['adapter'],
				'backup'     => $config['cache']['backup'],
				'key_prefix' => $config['cache']['key_prefix'],
				]);
		}

		$enc = '';

		if (isset($config['port']))
		{
			$enc .= ':' . $config['port'];
		}

		if (isset($config['encrypto']))
		{
			$enc .= '/' . $config['encrypto'];
		}

		if (isset($config['validate']) && $config['validate'] === false)
		{
			$enc .= '/novalidate-cert';
		}

		$this->mailbox = '{' . $config['host'] . $enc . '}';
		$this->stream  = imap_open($this->mailbox, $config['username'], $config['password']);

		//show_error($this->get_last_error());

		return is_resource($this->stream);
	}

	protected function set_cache($cache_id, $data)
	{
		if ($this->config['cache']['active'] === true)
		{
			return $this->CI->cache->save($cache_id, $data, $this->config['cache']['ttl']);
		}

		return true;
	}

	protected function get_cache($cache_id)
	{
		if ($this->config['cache']['active'] === true)
		{
			return $this->CI->cache->get($cache_id);
		}

		return false;
	}

	/**
	 * [set_timeout description]
	 *
	 * @param integer $timeout
	 * @param string  $type    open, read, write, or close
	 *
	 * @return boolean
	 */
	public function set_timeout(int $timeout = 60, string $type = 'open')
	{
		$types = [
			'open'  => IMAP_OPENTIMEOUT,
			'read'  => IMAP_READTIMEOUT,
			'write' => IMAP_WRITETIMEOUT,
			'close' => IMAP_CLOSETIMEOUT,
		];

		return imap_timeout($types[$type], $timeout);
	}

	/**
	 * [get_timeout description]
	 *
	 * @param string $type open, read, write, or close
	 *
	 * @return integer
	 */
	public function get_timeout(string $type = 'open')
	{
		$types = [
			'open'  => IMAP_OPENTIMEOUT,
			'read'  => IMAP_READTIMEOUT,
			'write' => IMAP_WRITETIMEOUT,
			'close' => IMAP_CLOSETIMEOUT,
		];

		return imap_timeout($types[$type]);
	}

	/**
	 * [ping description]
	 *
	 * @return boolean
	 */
	public function ping()
	{
		//return $this->fun('ping');
		return imap_ping($this->stream);
	}

	/**
	 * [disconnect description]
	 *
	 * @return boolean
	 */
	public function disconnect()
	{
		if (is_resource($this->stream))
		{
			if ($this->config['expunge_on_disconnect'] === true)
			{
				$this->select_folder($this->get_trash_folder());
				$this->mark_as_deleted($this->search());
				$this->expunge();
			}

			// Clears all errors before to close
			// See: https://github.com/natanfelles/codeigniter-imap/issues/5#issuecomment-355453233
			imap_errors();

			return imap_close($this->stream);
		}

		return true;
	}

	/**
	 * [set_expunge_on_disconnect description]
	 *
	 * @param boolean $active
	 *
	 * @return Imap
	 */
	public function set_expunge_on_disconnect(bool $active = true)
	{
		$this->config['expunge_on_disconnect'] = $active;

		return $this;
	}

	/**
	 * [expunge description]
	 *
	 * @return boolean
	 */
	public function expunge()
	{
		return imap_expunge($this->stream);
	}

	/**
	 * Gets the last IMAP error that occurred during this page request
	 *
	 * @return string|boolean Last error message or false if no errors
	 */
	public function get_last_error()
	{
		return imap_last_error();
	}

	/**
	 * [get_errors description]
	 *
	 * @return array|boolean Array of errors or false if no errors
	 */
	public function get_errors()
	{
		return imap_errors();
	}

	public function get_alerts()
	{
		return imap_alerts();
	}

	/**
	 * Get all folders names
	 *
	 * @return array Array of folder names. If an item is an array then
	 *               this is an associative array of "folder" => [subfolders]
	 */
	public function get_folders()
	{
		$folders = imap_list($this->stream, $this->mailbox, '*');
		$folders = $this->get_subfolders(str_replace($this->mailbox, '', $folders));

		sort($folders);

		return $folders;
	}

	protected function get_subfolders($folders)
	{
		for ($i = 0; $i < count($folders); $i++)
		{
			if (isset(explode('.', $folders[$i])[1]))
			{
				$folders[$i] = $this->get_subfolders($folders[$i]);
			}
		}

		// for ($i = 0; $i < count($folders); $i++)
		// {
		// 	if (strpos($folders[$i],'.') !== false)
		// 	{
		// 		$folders[$folders[$i]] = $this->static_dot_notation($folders[$i]);
		// 	}
		// }

		return $folders;
	}

	// function static_dot_notation($string, $value = null)
	// {
	// 	static $return;

	// 	$token = strtok($string, '.');

	// 	$ref =& $return;

	// 	while ($token !== false)
	// 	{
	// 		$ref   =& $ref[$token];
	// 		$token = strtok('.');
	// 	}

	// 	$ref = $value;

	// 	return $return;
	// }

	/**
	 * Select folder
	 *
	 * @param string $folder Folder name
	 *
	 * @return boolean
	 */
	public function select_folder(string $folder)
	{
		if ($result = imap_reopen($this->stream, $this->mailbox . $folder))
		{
			$this->folder = $folder;
		}

		return $result;
	}

	/**
	 * Add folder
	 *
	 * @param string $name Folder name
	 *
	 * @return boolean
	 */
	public function add_folder(string $folder_name)
	{
		return imap_createmailbox($this->stream, $this->mailbox . $folder_name);
	}

	/**
	 * Rename folder
	 *
	 * @param string $name     Current folder name
	 * @param string $new_name New folder name
	 *
	 * @return boolean    TRUE on success or FALSE on failure.
	 */
	public function rename_folder(string $name, string $new_name)
	{
		return imap_renamemailbox($this->stream, $this->mailbox . $name, $this->mailbox . $new_name);
	}

	/**
	 * Remove folder
	 *
	 * @param string $folder_name
	 *
	 * @return boolean TRUE on success or FALSE on failure.
	 */
	public function remove_folder(string $folder_name)
	{
		return imap_deletemailbox($this->stream, $this->mailbox . $folder_name);
	}

	/**
	 * Count all messages in the current or given folder,
	 * optionally matching a criteria
	 *
	 * @param string $folder
	 * @param string $flag_criteria Ex: RECENT, SEEN, UNSEEN, FROM "a@b.cc"
	 *
	 * @return integer
	 */
	public function count_messages(string $folder = null, string $flag_criteria = null)
	{
		$current_folder = $this->folder;

		if (isset($folder))
		{
			$this->select_folder($folder);
		}

		if ($flag_criteria)
		{
			$count = count($this->search($flag_criteria));
		}
		else
		{
			$count = imap_num_msg($this->stream);
		}

		if (isset($folder))
		{
			$this->select_folder($current_folder);
		}

		return $count;
	}

	/**
	 * Get quota usage and limit from mail account
	 *
	 * @return array
	 */
	public function get_quota(string $folder = null)
	{
		$current_folder = $this->folder;

		if (isset($folder))
		{
			$this->select_folder($folder);
		}

		$quota = imap_get_quotaroot($this->stream, $this->mailbox . $folder);

		if (isset($folder))
		{
			$this->select_folder($current_folder);
		}

		return $quota;
	}

	/**
	 * [move_messages description]
	 *
	 * @param mixed  $uids   Array list of uid's or a comma separated list of uids
	 * @param string $target
	 *
	 * @return boolean
	 */
	public function move_messages($uids, string $target)
	{
		if (is_array($uids))
		{
			$uids = implode(',', $uids);
		}

		if (imap_mail_move($this->stream, str_replace(' ', '', $uids), $target, CP_UID))
		{
			// Expunge is necessary to remove the original message that was
			// automatically marked as deleted when moved
			return imap_expunge($this->stream);
		}

		return false;
	}

	public function move_to_inbox($uids)
	{
		return $this->move_messages($uids, $this->get_inbox_folder());
	}

	public function move_to_trash($uids)
	{
		return $this->move_messages($uids, $this->get_trash_folder());
	}

	public function move_to_draft($uids)
	{
		return $this->move_messages($uids, $this->get_draft_folder());
	}

	public function move_to_spam($uids)
	{
		return $this->move_messages($uids, $this->get_spam_folder());
	}

	public function move_to_sent($uids)
	{
		return $this->move_messages($uids, $this->get_sent_folder());
	}

	/**
	 * [move_messages description]
	 *
	 * @param mixed $uids Array list of uid's or a comma separated list of uids
	 *
	 * @return boolean
	 */
	public function mark_as_read($uids)
	{
		return $this->message_setflag($uids, 'Seen');
	}

	/**
	 * [move_messages description]
	 *
	 * @param mixed $uids Array list of uid's or a comma separated list of uids
	 *
	 * @return boolean
	 */
	public function mark_as_unread($uids)
	{
		return $this->message_clearflag($uids, 'Seen');
	}

	/**
	 * [move_messages description]
	 *
	 * @param mixed $uids Array list of uid's or a comma separated list of uids
	 *
	 * @return boolean
	 */
	public function mark_as_answered($uids)
	{
		return $this->message_setflag($uids, 'Answered');
	}

	/**
	 * [move_messages description]
	 *
	 * @param mixed $uids Array list of uid's or a comma separated list of uids
	 *
	 * @return boolean
	 */
	public function mark_as_unanswered($uids)
	{
		return $this->message_clearflag($uids, 'Answered');
	}

	/**
	 * [move_messages description]
	 *
	 * @param mixed $uids Array list of uid's or a comma separated list of uids
	 *
	 * @return boolean
	 */
	public function mark_as_flagged($uids)
	{
		return $this->message_setflag($uids, 'Flagged');
	}

	/**
	 * [move_messages description]
	 *
	 * @param mixed $uids Array list of uid's or a comma separated list of uids
	 *
	 * @return boolean
	 */
	public function mark_as_unflagged($uids)
	{
		return $this->message_clearflag($uids, 'Flagged');
	}

	/**
	 * [move_messages description]
	 *
	 * @param mixed $uids Array list of uid's or a comma separated list of uids
	 *
	 * @return boolean
	 */
	public function mark_as_deleted($uids)
	{
		return $this->message_setflag($uids, 'Deleted');
	}

	/**
	 * [move_messages description]
	 *
	 * @param mixed $uids Array list of uid's or a comma separated list of uids
	 *
	 * @return boolean
	 */
	public function mark_as_undeleted($uids)
	{
		return $this->message_clearflag($uids, 'Deleted');
	}

	/**
	 * [move_messages description]
	 *
	 * @param mixed $uids Array list of uid's or a comma separated list of uids
	 *
	 * @return boolean
	 */
	public function mark_as_draft($uids)
	{
		return $this->message_setflag($uids, 'Draft');
	}

	/**
	 * [move_messages description]
	 *
	 * @param mixed $uids Array list of uid's or a comma separated list of uids
	 *
	 * @return boolean
	 */
	public function mark_as_undraft($uids)
	{
		return $this->message_clearflag($uids, 'Draft');
	}

	/**
	 * [message_setflag description]
	 *
	 * @param mixed  $uids Array list of uid's or a comma separated list of uids
	 * @param string $flag
	 *
	 * @return boolean
	 */
	protected function message_setflag($uids, string $flag)
	{
		if (is_array($uids))
		{
			$uids = implode(',', $uids);
		}

		return imap_setflag_full($this->stream, str_replace(' ', '', $uids), '\\' . ucfirst($flag), ST_UID);
	}

	/**
	 * [message_clearflag description]
	 *
	 * @param mixed  $uids Array list of uid's or a comma separated list of uids
	 * @param string $flag
	 *
	 * @return boolean
	 */
	protected function message_clearflag($uids, string $flag)
	{
		if (is_array($uids))
		{
			$uids = implode(',', $uids);
		}

		return imap_clearflag_full($this->stream, str_replace(' ', '', $uids), '\\' . ucfirst($flag), ST_UID);
	}

	/**
	 * Get all email addresses from all messages
	 *
	 * @return array Array with all email addresses
	 */
	public function get_all_email_addresses()
	{
		$cache_id = 'email_addresses';

		if (($cache = $this->get_cache($cache_id)) !== false)
		{
			return $cache;
		}

		$current_folder = $this->folder;
		$contacts       = [];

		foreach ($this->get_folders() as $folder)
		{
			$this->select_folder($folder);

			$uids = $this->search();

			foreach ($uids as $uid)
			{
				$msg = $this->get_message($uid);

				// As we get the messages uid's ordering by newest we do not
				// need to replace the name if we already have the most recent
				$contacts[$msg['from']['email']] = isset($contacts[$msg['from']['email']])
												   ? $contacts[$msg['from']['email']]
												   : $msg['from']['name'];

				foreach (['to', 'cc', 'bcc'] as $field)
				{
					foreach ($msg[$field] as $i)
					{
						$contacts[$i['email']] = isset($contacts[$i['email']])
												 ? $contacts[$i['email']]
												 : $i['name'];
					}
				}
			}
		}

		ksort($contacts);

		$this->select_folder($current_folder);

		$this->set_cache($cache_id, $contacts);

		return $contacts;
	}

	/**
	 * Return content of messages attachment
	 * Save the attachment in a optional path or get the binary code in the content index
	 *
	 * @param integer $uid   Message uid
	 * @param integer $index Index of the attachment - 0 to the first attachment
	 *
	 * @return array|boolean False if attachment could not be get
	 */
	public function get_attachment(int $uid, int $index = 0)
	{
		$cache_id = $this->folder . ':message_' . $uid . ':attachment_' . $index;

		if (($cache = $this->get_cache($cache_id)) !== false)
		{
			return $cache;
		}

		$id         = imap_msgno($this->stream, $uid);
		$structure  = imap_fetchstructure($this->stream, $id);
		$attachment = $this->_get_attachments($uid, $structure, '', $index);

		$this->set_cache($cache_id, $attachment);

		if (empty($attachment))
		{
			return false;
		}

		return $attachment;
	}

	/**
	 * [get_attachments description]
	 *
	 * @param integer $uid
	 * @param array   $indexes
	 *
	 * @return array
	 */
	public function get_attachments(int $uid, array $indexes = [])
	{
		$attachments = [];

		foreach ($indexes as $index)
		{
			$attachments[] = $this->get_attachment($uid, (int)$index);
		}

		return $attachments;
	}

	/**
	 * [_get_attachments description]
	 *
	 * @param integer      $uid
	 * @param object       $structure
	 * @param string       $part_number
	 * @param integer|null $index
	 * @param boolean      $with_content
	 *
	 * @return array
	 */
	protected function _get_attachments(int $uid, $structure, string $part_number = '',	int $index = null)
	{
		$id          = imap_msgno($this->stream, $uid);
		$attachments = [];

		if (isset($structure->parts))
		{
			foreach ($structure->parts as $key => $sub_structure)
			{
				$new_part_number = empty($part_number) ? $key + 1 : $part_number . '.' . ($key + 1);

				$results = $this->_get_attachments($uid, $sub_structure, $new_part_number);

				if (count($results))
				{
					if (isset($results[0]['name']))
					{
						foreach ($results as $result)
						{
							array_push($attachments, $result);
						}
					}
					else
					{
						array_push($attachments, $results);
					}
				}

				// If we already have the given indexes return here
				if (! is_null($index) && isset($attachments[$index]))
				{
					return $attachments[$index];
				}
			}
		}
		else
		{
			$attachment = [];

			if (isset($structure->dparameters[0]))
			{
				$bodystruct   = imap_bodystruct($this->stream, $id, $part_number);
				$decoded_name = imap_mime_header_decode($bodystruct->dparameters[0]->value);
				$filename     = $this->convert_to_utf8($decoded_name[0]->text);
				$content      = imap_fetchbody($this->stream, $id, $part_number);
				$content      = (string)$this->struc_decoding($content, $bodystruct->encoding);

				$attachment = [
					'name'         => (string)$filename,
					'part_number'  => (string)$part_number,
					'encoding'     => (int)$bodystruct->encoding,
					'size'         => (int)$structure->bytes,
					'reference'    => isset($bodystruct->id) ? (string)$bodystruct->id : '',
					'disposition'  => (string)strtolower($structure->disposition),
					'type'         => (string)strtolower($structure->subtype),
					'content'      => $content,
					'content_size' => strlen($content),
				];
			}

			return $attachment;
		}

		return $attachments;
	}

	/**
	 * [struc_decoding description]
	 *
	 * @param string  $text
	 * @param integer $encoding
	 *
	 * @see http://php.net/manual/pt_BR/function.imap-fetchstructure.php
	 *
	 * @return string
	 */
	protected function struc_decoding(string $text, int $encoding = 5)
	{
		switch ($encoding)
		{
			case ENC7BIT: // 0 7bit
				return $text;
			case ENC8BIT: // 1 8bit
				return imap_8bit($text);
			case ENCBINARY: // 2 Binary
				return imap_binary($text);
			case ENCBASE64: // 3 Base64
				return imap_base64($text);
			case ENCQUOTEDPRINTABLE: // 4 Quoted-Printable
				return quoted_printable_decode($text);
			case ENCOTHER: // 5 other
				return $text;
			default:
				return $text;
		}
	}

	protected function get_default_folder(string $type)
	{
		foreach ($this->get_folders() as $folder)
		{
			if (strtolower($folder) === strtolower($this->config['folders'][$type]))
			{
				return $folder;
			}
		}

		// No folder found? Create one
		$this->add_folder($this->config['folders'][$type]);

		return $this->config['folders'][$type];
	}

	protected function get_inbox_folder()
	{
		return $this->get_default_folder('inbox');
	}

	protected function get_trash_folder()
	{
		return $this->get_default_folder('trash');
	}

	protected function get_sent_folder()
	{
		return $this->get_default_folder('sent');
	}

	protected function get_spam_folder()
	{
		return $this->get_default_folder('spam');
	}

	protected function get_draft_folder()
	{
		return $this->get_default_folder('draft');
	}

	/**
	 * Create the final message array
	 *
	 * @param integer $uid          Message uid
	 * @param boolean $with_body    Define if the output will get the message body
	 * @param boolean $embed_images Define if message body will show embeded images
	 *
	 * @return array|boolean
	 */
	public function get_message(int $uid)
	{
		$cache_id = $this->folder . ':message_' . $uid;

		if (($cache = $this->get_cache($cache_id)) !== false)
		{
			return $cache;
		}

		// TODO: Maybe put this check before try get from cache
		// then we will know if the msg already exists
		$id = imap_msgno($this->stream, $uid);

		// If id is zero the message do not exists
		if ($id === 0)
		{
			return false;
		}

		$header = imap_headerinfo($this->stream, $id);

		// Check Priority
		preg_match('/X-Priority: ([\d])/mi', imap_fetchheader($this->stream, $id), $matches);
		$priority = isset($matches[1]) ? $matches[1] : 3;

		$subject = '';

		if (isset($header->subject) && strlen($header->subject) > 0)
		{
			foreach (imap_mime_header_decode($header->subject) as $decoded_header)
			{
				$subject .= $decoded_header->text;
			}
		}

		$email = [
			'id'          => (int)$id,
			'uid'         => (int)$uid,
			'from'        => isset($header->from[0]) ? (array)$this->to_address($header->from[0]) : [],
			'to'          => isset($header->to) ? (array)$this->array_to_address($header->to) : [],
			'cc'          => isset($header->cc) ? (array)$this->array_to_address($header->cc) : [],
			'bcc'         => isset($header->bcc) ? (array)$this->array_to_address($header->bcc) : [],
			'reply_to'    => isset($header->reply_to) ? (array)$this->array_to_address($header->reply_to) : [],
			//'return_path' => isset($header->return_path) ? (array)$this->array_to_address($header->return_path) : [],
			'message_id'  => $header->message_id,
			'in_reply_to' => isset($header->in_reply_to) ? (string)$header->in_reply_to : '',
			'references'  => isset($header->references) ? explode(' ', $header->references) : [],
			'date'        => $header->date,//date('c', strtotime(substr($header->date, 0, 30))),
			'udate'       => (int)$header->udate,
			'subject'     => $this->convert_to_utf8($subject),
			'priority'    => (int)$priority,
			'recent'      => strlen(trim($header->Recent)) > 0,
			'read'        => strlen(trim($header->Unseen)) < 1,
			'answered'    => strlen(trim($header->Answered)) > 0,
			'flagged'     => strlen(trim($header->Flagged)) > 0,
			'deleted'     => strlen(trim($header->Deleted)) > 0,
			'draft'       => strlen(trim($header->Draft)) > 0,
			'size'        => (int)$header->Size,
			'attachments' => (array)$this->_get_attachments($uid, imap_fetchstructure($this->stream, $id)),
			'body'        => $this->get_body($uid),
		];

		$email = $this->embed_images($email);

		for ($i = 0; $i < count($email['attachments']); $i++)
		{
			if ($email['attachments'][$i]['disposition'] !== 'attachment')
			{
				unset($email['attachments'][$i]);
			}
		}

		$this->set_cache($cache_id, $email);

		return $email;
	}

	/**
	 * Get messages
	 *
	 * @param array|string $uids         Array list of uid's or a comma separated list of uids
	 * @param boolean      $with_body    Define if the output will get the message body
	 * @param boolean      $embed_images Define if message body will show embeded images
	 *
	 * @return array|boolean
	 */
	public function get_messages($uids)
	{
		$messages = [];

		if (empty($uids))
		{
			return false;
		}

		if (is_string($uids))
		{
			$uids = explode(',', $uids);
		}

		foreach ($uids as $uid)
		{
			$messages[] = $this->get_message((int)$uid);
		}

		return $messages;
	}

	/**
	 * [get_eml description]
	 *
	 * @param integer $uid [description]
	 *
	 * @see https://stackoverflow.com/questions/7496266/need-to-save-a-copy-of-email-using-imap-php-and-then-can-be-open-in-outlook-expr
	 *
	 * @return string      [description]
	 */
	public function get_eml(int $uid)
	{
		$headers = imap_fetchheader($this->stream, $uid, FT_UID | FT_PREFETCHTEXT);
		$body    = imap_body($this->stream, $uid, FT_UID);

		return $headers . "\n" . $body;
	}

	public function fun(string $function, ...$params)
	{
		array_unshift($params, $this->stream);

		return call_user_func_array("imap_{$function}", $params);
	}

	/**
	 * [get_threads description]
	 *
	 * @see https://stackoverflow.com/questions/16248448/php-creating-a-multidimensional-array-of-message-threads-from-a-multidimensional
	 *
	 * @return array
	 */
	public function get_threads()
	{
		$thread = imap_thread($this->stream, SE_UID);
		$items  = [];

		foreach ($thread as $key => $uid)
		{
			$item = explode('.', $key);

			$node = (int)$item[0];

			$items[$node]['node'] = $node;


			switch ($item[1]) {
				case 'num':
					$items[$node]['num'] = $uid;
					$message = $this->get_message($uid);
					$items[$node]['msg'] = $message['subject'] . ' - ' . $message['date'];
					break;
				case 'next':
					$items[$node]['next'] = $uid; // node id
					break;
				case 'branch':
					$items[$node]['branch'] = $uid; // node id
					break;
			}
		}

		return $items;
	}

	/**
	 * Paginate uid's returning messages by "page" number
	 *
	 * @param array   $uids
	 * @param integer $page     Starts with 1
	 * @param integer $per_page
	 *
	 * @return array
	 */
	public function paginate(array $uids, int $page = 1, int $per_page = 10)
	{
		if (count($uids) < $per_page * $page)
		{
			return [];
		}

		return $this->get_messages(array_slice($uids, $per_page * $page - $per_page, $per_page));
	}

	/**
	 * Embed inline images in HTML Body
	 *
	 * @param array $email The email message
	 *
	 * @return array
	 */
	protected function embed_images(array $email)
	{
		foreach ($email['attachments'] as $key => $attachment)
		{
			if ($attachment['disposition'] === 'inline' && ! empty($attachment['reference']))
			{
				$reference = str_replace(['<', '>'], '', $attachment['reference']);
				$img_embed = 'data:image/' . $attachment['type'] . ';base64,' . base64_encode($attachment['content']);

				$email['body']['html'] = str_replace('cid:' . $reference, $img_embed, $email['body']['html']);
			}
		}

		return $email;
	}

	/**
	 * [search description]
	 *
	 * @param string  $search_criteria
	 *                                 ALL - return all messages matching the rest of the criteria
	 *                                 ANSWERED - match messages with the \\ANSWERED flag set
	 *                                 BCC "string" - match messages with "string" in the Bcc: field
	 *                                 BEFORE "date" - match messages with Date: before "date"
	 *                                 BODY "string" - match messages with "string" in the body of the message
	 *                                 CC "string" - match messages with "string" in the Cc: field
	 *                                 DELETED - match deleted messages
	 *                                 FLAGGED - match messages with the \\FLAGGED (sometimes referred to as Important or Urgent) flag set
	 *                                 FROM "string" - match messages with "string" in the From: field
	 *                                 KEYWORD "string" - match messages with "string" as a keyword
	 *                                 NEW - match new messages
	 *                                 OLD - match old messages
	 *                                 ON "date" - match messages with Date: matching "date"
	 *                                 RECENT - match messages with the \\RECENT flag set
	 *                                 SEEN - match messages that have been read (the \\SEEN flag is set)
	 *                                 SINCE "date" - match messages with Date: after "date"
	 *                                 SUBJECT "string" - match messages with "string" in the Subject:
	 *                                 TEXT "string" - match messages with text "string"
	 *                                 TO "string" - match messages with "string" in the To:
	 *                                 UNANSWERED - match messages that have not been answered
	 *                                 UNDELETED - match messages that are not deleted
	 *                                 UNFLAGGED - match messages that are not flagged
	 *                                 UNKEYWORD "string" - match messages that do not have the keyword "string"
	 *                                 UNSEEN - match messages which have not been read yet
	 * @param string  $sort_by
	 *                             One of:
	 *                             'date' = message Date
	 *                             'arrival' = arrival date
	 *                             'from' = mailbox in first From address
	 *                             'subject' = message subject
	 *                             'to' = mailbox in first To address
	 *                             'cc' = mailbox in first cc address
	 *                             'size' = size of message in octets
	 * @param boolean $descending
	 *
	 * @see http://php.net/manual/pt_BR/function.imap-sort.php
	 * @see http://php.net/manual/pt_BR/function.imap-search.php
	 *
	 * @return array Array of uid's matching the search criteria
	 */
	public function search(string $search_criteria = 'ALL', string $sort_by = 'date', bool $descending = true)
	{
		$search_criteria = $this->search_criteria . ' ' . $search_criteria;

		$this->search_criteria = '';

		$criterias = [
			'date'    => SORTDATE, // message Date
			'arrival' => SORTARRIVAL, // arrival date
			'from'    => SORTFROM, // mailbox in first From address
			'subject' => SORTSUBJECT, // message subject
			'to'      => SORTTO, // mailbox in first To address
			'cc'      => SORTCC, // mailbox in first cc address
			'size'    => SORTSIZE, // size of message in octets
		];

		return imap_sort($this->stream, $criterias[$sort_by], (int)$descending, SE_UID, $search_criteria, 'UTF-8');
	}

	/**
	 * [search_body description]
	 *
	 * @param string $str
	 *
	 * @return Imap
	 */
	public function search_body($str)
	{
		$this->search_criteria .= ' BODY "' . $str . '"';

		return $this;
	}

	/**
	 * [search_body description]
	 *
	 * @param string $str
	 *
	 * @return Imap
	 */
	public function search_subject($str)
	{
		$this->search_criteria .= ' SUBJECT "' . $str . '"';

		return $this;
	}

	/**
	 * [search_body description]
	 *
	 * @param string|integer $str String on valid format (D, d M Y) or a timestamp
	 *
	 * @return Imap
	 */
	public function search_on_date($str)
	{
		if (is_numeric($str))
		{
			$str = date('D, d M Y', $str);
		}

		$this->search_criteria .= ' ON "' . $str . '"';

		return $this;
	}

	/**
	 * Return general folder statistics
	 *
	 * @param string $folder
	 *
	 * @return array
	 */
	public function get_folder_stats(string $folder = null)
	{
		$current_folder = $this->folder;

		if (isset($folder))
		{
			$this->select_folder($folder);
		}

		$stats = imap_mailboxmsginfo($this->stream);

		if ($stats)
		{
			$stats = [
				'unread'   => $stats->Unread,
				'deleted'  => $stats->Deleted,
				'messages' => $stats->Nmsgs,
				'size'     => $stats->Size,
				'Date'     => $stats->Date,
				'date'     => date('c', strtotime(substr($stats->Date, 0, 30))),
				'recent'   => $stats->Recent,
			];
		}

		if (isset($folder))
		{
			$this->select_folder($current_folder);
		}

		return $stats;
	}

	/**
	 * [convert_to_utf8 description]
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	protected function convert_to_utf8(string $str)
	{
		if (mb_detect_encoding($str, 'UTF-8, ISO-8859-1, GBK') !== 'UTF-8')
		{
			$str = utf8_encode($str);
		}

		$str = iconv('UTF-8', 'UTF-8//IGNORE', $str);

		return $str;
	}

	/**
	 * [array_to_address description]
	 *
	 * @param array $addresses
	 *
	 * @return array
	 */
	protected function array_to_address($addresses = [])
	{
		$formated = [];

		foreach ($addresses as $address)
		{
			$formated[] = $this->to_address($address);
		}

		return $formated;
	}

	/**
	 * [to_address description]
	 *
	 * @param object $headerinfos
	 *
	 * @return array
	 */
	protected function to_address($headerinfos)
	{
		$from = [
			'email' => '',
			'name'  => '',
		];

		if (isset($headerinfos->mailbox) && isset($headerinfos->host))
		{
			$from['email'] = $headerinfos->mailbox . '@' . $headerinfos->host;
		}

		if (! empty($headerinfos->personal))
		{
			$name         = imap_mime_header_decode($headerinfos->personal);
			$name         = $name[0]->text;
			$from['name'] = empty($name) ? '' : $this->convert_to_utf8($name);
		}

		return $from;
	}

	/**
	 * [get_body description]
	 *
	 * @param integer $uid
	 *
	 * @return array
	 */
	protected function get_body(int $uid)
	{
		return [
			'html'  => $this->get_part($uid, 'TEXT/HTML'),
			'plain' => $this->get_part($uid, 'TEXT/PLAIN'),
		];
	}

	/**
	 * [get_part description]
	 *
	 * @param integer        $uid
	 * @param string         $mimetype
	 * @param object|boolean $structure   The bodystruct or false to none
	 * @param string|boolean $part_number Part number or false to none
	 *
	 * @return string
	 */
	protected function get_part(int $uid, $mimetype = '', $structure = false, $part_number = '')
	{
		if (! $structure)
		{
			$structure = imap_fetchstructure($this->stream, $uid, FT_UID);
		}

		if ($structure)
		{
			if ($mimetype === $this->get_mime_type($structure))
			{
				if (! $part_number)
				{
					$part_number = '1';
				}

				$text = imap_fetchbody($this->stream, $uid, $part_number, FT_UID | FT_PEEK);

				return $this->struc_decoding($text, $structure->encoding);
			}

			if ($structure->type === TYPEMULTIPART) // 1 multipart
			{
				foreach ($structure->parts as $index => $subStruct)
				{
					$prefix = '';

					if ($part_number)
					{
						$prefix = $part_number . '.';
					}

					$data = $this->get_part($uid, $mimetype, $subStruct, $prefix . ($index + 1));

					if ($data)
					{
						return $data;
					}
				}
			}
		}

		return false;
	}

	/**
	 * [get_mime_type description]
	 *
	 * @param object $structure
	 *
	 * @see http://php.net/manual/pt_BR/function.imap-fetchstructure.php
	 *
	 * @return string
	 */
	protected function get_mime_type($structure)
	{
		$primary_body_types = [
			TYPETEXT        => 'TEXT',
			TYPEMULTIPART   => 'MULTIPART',
			TYPEMESSAGE     => 'MESSAGE',
			TYPEAPPLICATION => 'APPLICATION',
			TYPEAUDIO       => 'AUDIO',
			TYPEIMAGE       => 'IMAGE',
			TYPEVIDEO       => 'VIDEO',
			TYPEMODEL       => 'MODEL',
			TYPEOTHER       => 'OTHER',
		];

		if ($structure->ifsubtype)
		{
			return strtoupper($primary_body_types[(int)$structure->type] . '/' . $structure->subtype);
		}

		return 'TEXT/PLAIN';
	}

	/**
	 * [__destruct description]
	 */
	public function __destruct()
	{
		// TODO: Maybe is not necessary auto-close everytime
		// Analyze it
		if (is_resource($this->stream))
		{
			imap_errors();
			imap_close($this->stream);
		}
	}

}
