<?php
/**
 * Imap Class
 * This class enables you to use the IMAP Protocol
 *
 * @package       CodeIgniter
 * @subpackage    Libraries
 * @category      Email
 * @version       1.0.0
 * @author        Natan Felles
 * @link          http://github.com/natanfelles/codeigniter-imap
 */
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Class Imap
 *
 */
class Imap {


	/**
	 * IMAP mailbox
	 *
	 * @var string
	 */
	protected $imap_mailbox = '';

	/**
	 * IMAP stream
	 *
	 * @var resource
	 */
	protected $imap_stream = '';

	/**
	 * IMAP folder
	 *
	 * @var string
	 */
	protected $folder = 'INBOX';


	/**
	 * @param array $config Options: host, encrypto, user, pass
	 *
	 * @return bool|string True if is connected or string with the imap_error
	 */
	public function imap_connect($config = [])
	{
		$enc = '';
		if ($config['encrypto'] != NULL && isset($config['encrypto']) && $config['encrypto'] == 'ssl')
		{
			$enc = '/imap/ssl/novalidate-cert';
		}
		elseif ($config['encrypto'] != NULL && isset($config['encrypto']) && $config['encrypto'] == 'tls')
		{
			$enc = '/imap/tls/novalidate-cert';
		}
		$this->imap_mailbox = '{' . $config['host'] . $enc . '}';
		$this->imap_stream = @imap_open($this->imap_mailbox, $config['user'], $config['pass']);
		if ( ! is_resource($this->imap_stream))
		{
			return $this->imap_error();
		}
		else
		{
			return TRUE;
		}
	}


	/**
	 * Gets the last IMAP error that occurred during this page request
	 *
	 * @return string
	 */
	protected function imap_error()
	{
		return imap_last_error();
	}


	/**
	 * Get all folders names
	 *
	 * @return array
	 */
	public function get_folders()
	{
		$folders = imap_list($this->imap_stream, $this->imap_mailbox, '*');
		sort($folders);

		return str_replace($this->imap_mailbox, '', $folders);
	}


	/**
	 * Select folder
	 *
	 * @param string $folder Folder name
	 *
	 * @return bool
	 */
	public function select_folder($folder = '')
	{
		$result = imap_reopen($this->imap_stream, $this->imap_mailbox . $folder);
		if ($result)
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
	 * @return bool
	 */
	public function add_folder($name = '')
	{
		return imap_createmailbox($this->imap_stream, $this->imap_mailbox . $name);
	}


	/**
	 * Rename folder
	 *
	 * @param string $name    Current Folder name
	 * @param string $newname New Folder name
	 *
	 * @return bool
	 */
	public function rename_folder($name = '', $newname = '')
	{
		return imap_renamemailbox($this->imap_stream, $this->imap_mailbox . $name, $this->imap_mailbox . $newname);
	}


	/**
	 * Remove folder
	 *
	 * @param string $name Folder name
	 *
	 * @return bool True on success
	 */
	public function remove_folder($name = '')
	{
		return imap_deletemailbox($this->imap_stream, $this->imap_mailbox . $name);
	}


	/**
	 * Count all messages
	 *
	 * @return int
	 */
	public function count_messages()
	{
		return imap_num_msg($this->imap_stream);
	}


	/**
	 * Count unread messages
	 *
	 * @param string $mailbox Folder to count unread messages
	 *
	 * @return int
	 */
	public function count_unread_messages($mailbox = '')
	{
		$saveCurrentFolder = $this->folder;
		if (isset($mailbox))
		{
			$this->select_folder($mailbox);
		}
		$result = imap_search($this->imap_stream, 'UNSEEN');
		if (isset($mailbox))
		{
			$this->select_folder($saveCurrentFolder);
		}
		if ($result === FALSE)
		{
			return 0;
		}

		return count($result);
	}


	/**
	 * Get quota usage and limit from mail account
	 *
	 * @return array
	 */
	public function get_quota()
	{
		return imap_get_quotaroot($this->imap_stream, $this->imap_mailbox);
	}


	/**
	 * Get total size from current mailbox
	 *
	 * @return int
	 */
	public function get_mailbox_size()
	{
		$messages = $this->get_messages();
		$size = 0;
		foreach ($messages as $message)
		{
			$size += $message['size'];
		}

		return $size;
	}


	/**
	 * Returns one email by given id
	 *
	 * @param int  $id           Message id
	 * @param bool $withbody     False if you want without body
	 * @param bool $embed_images If use $withbody TRUE and you want body embeded images, set TRUE
	 *
	 * @return array
	 */
	public function get_message($id = 0, $withbody = FALSE, $embed_images = FALSE)
	{
		return $this->format_message($id, $withbody, $embed_images);
	}


	/**
	 * Get messages
	 *
	 * @param int    $number       Number of messages. 0 to get all
	 * @param int    $start        Starting message number
	 * @param string $order        ASC or DESC
	 * @param bool   $withbody     Get message body
	 * @param bool   $embed_images Get embeded images in message body
	 *
	 * @return array
	 */
	public function get_messages($number = 0, $start = 0, $order = 'DESC', $withbody = FALSE, $embed_images = FALSE)
	{
		if ($number == 0)
		{
			$number = $this->count_messages();
		}
		$emails = [];
		$result = imap_search($this->imap_stream, 'ALL');
		if ($result)
		{
			$ids = [];
			foreach ($result as $k => $i)
			{
				$ids[] = $i;
			}

			if ($order == 'DESC')
			{
				$ids = array_reverse($ids);
			}
			$ids = array_chunk($ids, $number);
			$ids = $ids[$start];

			foreach ($ids as $id)
			{
				$emails[] = $this->format_message($id, $withbody, $embed_images);
			}
		}

		return $emails;
	}


	/**
	 * Get unread messages
	 *
	 * @param int    $number   Number of messages. 0 to get all
	 * @param int    $start    Starting message number
	 * @param string $order    ASC or DESC
	 * @param bool   $withbody Get message body
	 *
	 * @return array
	 */
	public function get_unread_messages($number = 0, $start = 0, $order = 'DESC', $withbody = FALSE)
	{
		return $this->get_search_messages('UNSEEN', $number, $start, $order, $withbody);
	}


	/**
	 * @param string $criteria ALL, UNSEEN, FLAGGED, UNANSWERED, DELETED, UNDELETED, (e.g. FROM
	 *                         "joey smith")
	 * @param int    $number
	 * @param int    $start
	 * @param string $order
	 * @param bool   $withbody
	 * @param bool   $embed_images
	 *
	 * @return array
	 */
	public function get_search_messages($criteria = '',
		$number = 0,
		$start = 0,
		$order = 'DESC',
		$withbody = FALSE,
		$embed_images = FALSE)
	{
		$emails = [];
		$result = imap_search($this->imap_stream, $criteria);
		if ($number == 0)
		{
			$number = count($result);
		}
		if ($result)
		{
			$ids = [];
			foreach ($result as $k => $i)
			{
				$ids[] = $i;
			}
			$ids = array_chunk($ids, $number);
			$ids = array_slice($ids[0], $start, $number);

			$emails = [];
			foreach ($ids as $id)
			{
				$emails[] = $this->format_message($id, $withbody, $embed_images);
			}
		}
		if ($order == 'DESC')
		{
			$emails = array_reverse($emails);
		}

		return $emails;
	}


	/**
	 * Mark message as read or unread
	 *
	 * @param int  $id     Message id
	 * @param bool $unseen True if message is unread, false if message is read
	 *
	 * @return bool True on success
	 */
	public function set_unseen_message($id = 0, $unseen = TRUE)
	{
		$header = $this->get_message_header($id);
		if ($header == FALSE)
		{
			return FALSE;
		}

		$flags = "";
		$flags .= (strlen(trim($header->Answered)) > 0 ? "\\Answered " : '');
		$flags .= (strlen(trim($header->Flagged)) > 0 ? "\\Flagged " : '');
		$flags .= (strlen(trim($header->Deleted)) > 0 ? "\\Deleted " : '');
		$flags .= (strlen(trim($header->Draft)) > 0 ? "\\Draft " : '');

		$flags .= (($unseen == FALSE) ? '\\Seen ' : ' ');
		//echo "\n<br />".$id.": ".$flags;
		imap_clearflag_full($this->imap_stream, $id, '\\Seen', ST_UID);

		return imap_setflag_full($this->imap_stream, $id, trim($flags), ST_UID);
	}


	public function move_message($id = 0, $target = '')
	{
		return $this->move_messages([$id], $target);
	}


	public function move_messages($ids = [], $target = '')
	{
		if (imap_mail_move($this->imap_stream, implode(",", $ids), $target, CP_UID) === FALSE)
		{
			return FALSE;
		}

		return imap_expunge($this->imap_stream);
	}


	/**
	 * Save message in Sent folder
	 *
	 * @param string $header Message header
	 * @param string $body   Message body
	 *
	 * @return bool
	 */
	public function save_message_in_sent($header = '', $body = '')
	{
		return imap_append($this->imap_stream, $this->imap_mailbox . $this->get_sent(),
			$header . "\r\n" . $body . "\r\n", "\\Seen");
	}


	public function delete_message($id = 0)
	{
		return $this->delete_messages([$id]);
	}


	public function delete_messages($ids = [])
	{
		if (imap_mail_move($this->imap_stream, implode(',', $ids), $this->get_trash(), CP_UID) == FALSE)
		{
			return FALSE;
		}

		return imap_expunge($this->imap_stream);
	}


	/**
	 * Returns all email addresses
	 *
	 * @return array|bool Array with all email addresses or false on error
	 */
	public function get_all_email_addresses()
	{
		$saveCurrentFolder = $this->folder;
		$emails = [];
		foreach ($this->get_folders() as $folder)
		{
			$this->select_folder($folder);
			foreach ($this->get_messages() as $message)
			{
				$emails[] = $message['from'];

				foreach ($message['to'] as $to)
				{
					$emails[] = $to;
				}

				if (isset($message['cc']))
				{
					$emails = array_merge($emails, $message['cc']);
					foreach ($message['cc'] as $cc)
					{
						$emails[] = $cc;
					}
				}
			}
		}

		$contacts = [];

		foreach ($emails as $k => $i)
		{
			$contacts[$i['email']] = $i['name'];
		}

		foreach ($emails as $k => $i)
		{
			if ( ! empty($i['name']))
			{
				$contacts[$i['email']] = $i['name'];
			}
		}

		$this->select_folder($saveCurrentFolder);

		return $contacts;
	}


	/**
	 * Clean folder content of selected folder
	 *
	 * @return bool True on success
	 */
	public function purge()
	{
		// delete trash and spam
		if ($this->folder == $this->get_trash() || strtolower($this->folder) == 'spam')
		{
			if (imap_delete($this->imap_stream, '1:*') === FALSE)
			{
				return FALSE;
			}

			return imap_expunge($this->imap_stream);
			// move others to trash
		}
		else
		{
			if (imap_mail_move($this->imap_stream, '1:*', $this->get_trash()) == FALSE)
			{
				return FALSE;
			}

			return imap_expunge($this->imap_stream);
		}
	}


	/**
	 * Return content of messages attachment
	 * Save the attachment in a optional path or get the binary code in the content index
	 *
	 * @param int    $id       Message id
	 * @param int    $index    Index of the attachment - 0 to the first attachment
	 * @param string $tmp_path Optional tmp path, if not set the code will be get in the output
	 *
	 * @return array|bool False if attachement could not be get
	 */
	public function get_attachment($id = 0, $index = 0, $tmp_path = '')
	{
		// find message
		$messageIndex = imap_msgno($this->imap_stream, imap_uid($this->imap_stream, $id));
		//$header = imap_headerinfo($this->imap, $messageIndex);
		$mailStruct = imap_fetchstructure($this->imap_stream, $messageIndex);
		$attachments = $this->get_attachments($messageIndex, $mailStruct, '');

		if ($attachments == FALSE)
		{
			return FALSE;
		}

		// find attachment
		if ($index > count($attachments))
		{
			return FALSE;
		}

		$attachment = $attachments[$index];

		// get attachment body
		$partStruct = imap_bodystruct($this->imap_stream, $messageIndex, $attachment['partNum']);

		$decodedName = imap_mime_header_decode($partStruct->dparameters[0]->value);
		$filename = $this->convert_to_utf8($decodedName[0]->text);

		$message = imap_fetchbody($this->imap_stream, $id, $attachment['partNum']); // FT_UID ?

		switch ($attachment['enc'])
		{
			case 0:
			case 1:
				$message = imap_8bit($message);
				break;
			case 2:
				$message = imap_binary($message);
				break;
			case 3:
				$message = imap_base64($message);
				break;
			case 4:
				$message = quoted_printable_decode($message);
				break;
		}

		$file = [
			'name'        => $attachment['name'],
			'size'        => $attachment['size'],
			'disposition' => $attachment['disposition'],
			'reference'   => $attachment['reference'],
			'type'        => $attachment['type'],
			'content'     => $message,
		];

		if ($tmp_path != '')
		{
			$file['content'] = $tmp_path . $filename;
			$fp = fopen($file['content'], 'wb');
			fwrite($fp, $message);
			fclose($fp);
		}

		return $file;
	}


	protected function get_trash()
	{
		foreach ($this->get_folders() as $folder)
		{
			if (strtolower($folder) === 'trash' || strtolower($folder) === 'papierkorb')
			{
				return $folder;
			}
		}

		// no trash folder found? create one
		$this->add_folder('Trash');

		return 'Trash';
	}


	protected function get_sent()
	{
		foreach ($this->get_folders() as $folder)
		{
			if (strtolower($folder) === 'sent' || strtolower($folder) === 'gesendet')
			{
				return $folder;
			}
		}

		// no sent folder found? create one
		$this->add_folder('Sent');

		return 'Sent';
	}


	/**
	 * Create the final message array
	 *
	 * @param int  $id           Message uid
	 * @param bool $withbody     Define if the output will get the message body
	 * @param bool $embed_images Define if message body will show embeded images
	 *
	 * @return array
	 */
	protected function format_message($id = 0, $withbody = TRUE, $embed_images = TRUE)
	{
		$header = imap_headerinfo($this->imap_stream, $id);

		// fetch unique uid
		$uid = imap_uid($this->imap_stream, $id);

		// Check Priority
		preg_match('/X-Priority: ([\d])/mi', imap_fetchheader($this->imap_stream, $id), $matches);
		$priority = isset($matches[1]) ? $matches[1] : 3;

		// get email data
		$subject = '';
		if (isset($header->subject) && strlen($header->subject) > 0)
		{
			foreach (imap_mime_header_decode($header->subject) as $obj)
			{
				$subject .= $obj->text;
			}
		}
		$subject = @$this->convert_to_utf8($subject);
		$email = [
			'to'       => isset($header->to) ? $this->array_to_address($header->to) : '',
			'from'     => $this->to_address($header->from[0]),
			'date'     => $header->udate,
			'subject'  => $subject,
			'priority' => $priority,
			'uid'      => $uid,
			'id'       => $id,
			'unread'   => strlen(trim($header->Unseen)) > 0,
			'answered' => strlen(trim($header->Answered)) > 0,
			'flagged'  => strlen(trim($header->Flagged)) > 0,
			'deleted'  => strlen(trim($header->Deleted)) > 0,
			'size'     => $header->Size,
		];
		if (isset($header->cc))
		{
			$email['cc'] = $this->array_to_address($header->cc);
		}

		// get email body
		if ($withbody === TRUE)
		{
			$body = $this->get_body($uid);
			$email['body'] = $body['body'];
			$email['html'] = $body['html'];
		}

		// get attachments
		$mailStruct = imap_fetchstructure($this->imap_stream, $id);
		$attachments = $this->attachments_to_name($this->get_attachments($id, $mailStruct, ''));
		if (count($attachments) > 0)
		{
			foreach ($attachments as $val)
			{
				$arr = [];
				foreach ($val as $k => $t)
				{
					if ($k == 'name')
					{
						$decodedName = imap_mime_header_decode($t);
						$t = $this->convert_to_utf8($decodedName[0]->text);
					}
					$arr[$k] = $t;
				}
				$email['attachments'][] = $arr;
			}
		}

		// Modify HTML to embed images inline
		if ((count(@$email['attachments']) > 0) && (@$email['html'] == TRUE) && ($embed_images == TRUE))
		{
			$email['body'] = $this->embed_images($email);
		}

		return $email;
	}


	/**
	 * HTML embed inline images
	 *
	 * @param array $email
	 *
	 * @return string
	 */
	protected function embed_images($email)
	{
		$html_embed = $email['body'];

		foreach ($email['attachments'] as $key => $attachment)
		{
			if ($attachment['disposition'] == 'inline' && ! empty($attachment['reference']))
			{
				$file = $this->get_attachment($email['uid'], $key);

				$reference = str_replace(['<', '>'], '', $attachment['reference']);
				$img_embed = 'data:image/' . strtolower($file['type']) . ';base64,' . base64_encode($file['content']);

				$html_embed = str_replace('cid:' . $reference, $img_embed, $html_embed);
			}
		}

		return $html_embed;
	}


	/**
	 * Return general mailbox statistics
	 *
	 * @return object|false object
	 */
	public function get_mailbox_statistics()
	{
		return is_resource($this->imap_stream) ? imap_mailboxmsginfo($this->imap_stream) : FALSE;
	}


	protected function convert_to_utf8($str = '')
	{
		if (mb_detect_encoding($str, 'UTF-8, ISO-8859-1, GBK') != 'UTF-8')
		{
			$str = utf8_encode($str);
		}
		$str = iconv('UTF-8', 'UTF-8//IGNORE', $str);

		return $str;
	}


	protected function array_to_address($addresses = [])
	{
		$addressesAsString = [];
		foreach ($addresses as $address)
		{
			$addressesAsString[] = $this->to_address($address);
		}

		return $addressesAsString;
	}


	protected function to_address($headerinfos = [])
	{
		$from = [];

		if (isset($headerinfos->mailbox) && isset($headerinfos->host))
		{
			$from['email'] = $headerinfos->mailbox . '@' . $headerinfos->host;
		}
		else
		{
			$from['email'] = '';
		}

		if ( ! empty($headerinfos->personal))
		{
			$name = imap_mime_header_decode($headerinfos->personal);
			$name = $name[0]->text;
			$from['name'] = $this->convert_to_utf8($name);
		}
		else
		{
			$from['name'] = '';
		}

		return $from;
	}


	/**
	 * Fetch header by message id
	 *
	 * @param int $id Message id
	 *
	 * @return bool|object Message header on success
	 */
	protected function get_message_header($id = 0)
	{
		$count = $this->count_messages();
		for ($i = 1; $i <= $count; $i++)
		{
			$uid = imap_uid($this->imap_stream, $i);
			if ($uid == $id)
			{
				$header = imap_headerinfo($this->imap_stream, $i);

				return $header;
			}
		}

		return FALSE;
	}


	protected function get_body($uid = 0)
	{
		$body = $this->get_part($this->imap_stream, $uid, 'TEXT/HTML');
		$html = TRUE;
		// if HTML body is empty, try getting text body
		if ($body == '')
		{
			$body = $this->get_part($this->imap_stream, $uid, 'TEXT/PLAIN');
			$html = FALSE;
		}
		$body = $this->convert_to_utf8($body);

		return [
			'body' => $body,
			'html' => $html,
		];
	}


	protected function get_part($imap, $uid = 0, $mimetype = '', $structure = FALSE, $partNumber = FALSE)
	{
		if ( ! $structure)
		{
			$structure = imap_fetchstructure($imap, $uid, FT_UID);
		}
		if ($structure)
		{
			if ($mimetype == $this->get_mime_type($structure))
			{
				if ( ! $partNumber)
				{
					$partNumber = 1;
				}
				$text = imap_fetchbody($imap, $uid, $partNumber, FT_UID | FT_PEEK);
				switch ($structure->encoding)
				{
					case 3:
						return imap_base64($text);
					case 4:
						return imap_qprint($text);
					default:
						return $text;
				}
			}

			// multipart
			if ($structure->type == 1)
			{
				foreach ($structure->parts as $index => $subStruct)
				{
					$prefix = '';
					if ($partNumber)
					{
						$prefix = $partNumber . '.';
					}
					$data = $this->get_part($imap, $uid, $mimetype, $subStruct, $prefix . ($index + 1));
					if ($data)
					{
						return $data;
					}
				}
			}
		}

		return FALSE;
	}


	protected function get_mime_type($structure)
	{
		$primaryMimetype = [
			'TEXT',
			'MULTIPART',
			'MESSAGE',
			'APPLICATION',
			'AUDIO',
			'IMAGE',
			'VIDEO',
			'OTHER',
		];

		if ($structure->subtype)
		{
			return $primaryMimetype[(int)$structure->type] . '/' . $structure->subtype;
		}

		return 'TEXT/PLAIN';
	}


	protected function attachments_to_name($attachments = [])
	{
		$names = [];
		foreach ($attachments as $attachment)
		{
			if (isset($attachment[0]['name']))
			{
				$names[] = [
					'name'        => $attachment[0]['name'],
					'size'        => $attachment[0]['size'],
					'disposition' => $attachment['disposition'],
					'reference'   => $attachment['reference'],
				];
			}
			else
			{
				$names[] = [
					'name'        => $attachment['name'],
					'size'        => $attachment['size'],
					'disposition' => $attachment['disposition'],
					'reference'   => $attachment['reference'],
				];
			}
		}

		return $names;
	}


	protected function get_attachments($mailNum, $part, $partNum = '')
	{
		$attachments = [];

		if (isset($part->parts))
		{
			foreach ($part->parts as $key => $subpart)
			{
				if ($partNum != '')
				{
					$newPartNum = $partNum . '.' . ($key + 1);
				}
				else
				{
					$newPartNum = ($key + 1);
				}
				$result = $this->get_attachments($mailNum, $subpart, $newPartNum);
				if (count($result) != 0)
				{
					if (isset($result[0]['name']))
					{
						foreach ($result as $inline)
						{
							array_push($attachments, $inline);
						}
					}
					else
					{
						array_push($attachments, $result);
					}
				}
			}
		}
		else
		{
			if (isset($part->disposition))
			{
				if (in_array(strtolower($part->disposition), ['attachment', 'inline']))
				{
					$partStruct = imap_bodystruct($this->imap_stream, $mailNum, $partNum);
					$reference = isset($partStruct->id) ? $partStruct->id : '';
					$attachmentDetails = [];
					if (isset($part->dparameters[0]))
					{
						$attachmentDetails = [
							'name'        => $part->dparameters[0]->value,
							'partNum'     => $partNum,
							'enc'         => @$partStruct->encoding,
							'size'        => $part->bytes,
							'reference'   => $reference,
							'disposition' => $part->disposition,
							'type'        => $part->subtype,
						];
					}

					return $attachmentDetails;
				}
			}
			else
			{
				if (isset($part->subtype) && in_array($part->subtype, ['JPEG', 'GIF', 'PNG']))
				{

					$partStruct = imap_bodystruct($this->imap_stream, $mailNum, $partNum);
					$reference = isset($partStruct->id) ? $partStruct->id : '';
					$disposition = empty($reference) ? 'attachment' : 'inline';
					if (isset($part->dparameters[0]->value))
					{
						$name = $part->dparameters[0]->value;
					}
					elseif ($part->parameters[0]->value)
					{
						$name = $part->parameters[0]->value;
					}
					else
					{
						$name = 'unknown';
					}
					$attachmentDetails = [];
					if (isset($part->dparameters[0]))
					{
						$attachmentDetails = [
							'name'        => $name,
							'partNum'     => $partNum,
							'enc'         => $partStruct->encoding,
							'size'        => $part->bytes,
							'reference'   => $reference,
							'disposition' => $disposition,
							'type'        => $part->subtype,
						];
					}

					return $attachmentDetails;
				}
			}
		}

		return $attachments;
	}


	public function __destruct()
	{
		if (is_resource($this->imap_stream))
		{
			imap_close($this->imap_stream);
		}
	}

}
