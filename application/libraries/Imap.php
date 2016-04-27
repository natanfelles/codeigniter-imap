<?php
/**
 * Imap Class
 * This class enables you to use the IMAP Protocol
 *
 * @package       CodeIgniter
 * @subpackage    Librarys
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
	protected $folder = '';

	/**
	 * @param array $config  Options: host, encrypto, user, pass
	 */
	public function imap_connect($config = array())
	{
		$enc = '';
		if ($config['encrypto'] != NULL && isset($config['encrypto']) && $config['encrypto'] == 'ssl')
		{
			$enc = '/imap/ssl/novalidate-cert';
		}
		else if ($config['encrypto'] != NULL && isset($config['encrypto']) && $config['encrypto'] == 'tls')
		{
			$enc = '/imap/tls/novalidate-cert';
		}
		$this->imap_mailbox = "{" . $config['host'] . $enc . "}";
		$this->imap_stream = @imap_open($this->imap_mailbox, $config['user'], $config['pass']);
		if ( ! is_resource($this->imap_stream))
		{
			$this->imap_error();
		}
	}


	protected function imap_check()
	{

		$status = imap_ping($this->imap_stream);

		return $status;
	}


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
		$folders = imap_list($this->imap_stream, $this->imap_mailbox, "*");

		return str_replace($this->imap_mailbox, "", $folders);
	}


	/**
	 * Select folder
	 *
	 * @param string $folder Folder name
	 * @return bool
	 */
	public function select_folder($folder = '')
	{
		$result = imap_reopen($this->imap_stream, $this->imap_mailbox . $folder);
		if ($result === TRUE)
		{
			$this->folder = $folder;
		}

		return $result;
	}


	/**
	 * Add folder
	 *
	 * @param string $name Folder name
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
	 * @return int
	 */
	public function count_unread_messages()
	{
		$result = imap_search($this->imap_stream, 'UNSEEN');
		if ($result === FALSE)
		{
			return 0;
		}

		return count($result);
	}


	/**
	 * Returns one email by given id
	 *
	 * @param int  $id       Message id
	 * @param bool $withbody False if you want without body
	 * @return array
	 */
	public function get_message($id = 0, $withbody = FALSE)
	{
		return $this->format_message($id, $withbody);
	}


	/**
	 * Get messages
	 *
	 * @param int    $number   Number of messages. 0 to get all
	 * @param string $order    ASC or DESC
	 * @param bool   $withbody Get message body
	 * @return array
	 */
	public function get_messages($number = 0, $order = 'DESC', $withbody = FALSE)
	{
		$total = $this->count_messages();
		$number < 1 ? : $number = $total;
		$emails = array();
		if ($order == 'DESC')
		{
			if ($total > 0)
			{
				$number -= 1;
				for ($i = $total - $number; $i <= $total; $i++)
				{
					$emails[] = $this->format_message($i, $withbody);
				}

				$emails = array_reverse($emails);
			}
		}
		else
		{
			for ($i = 1; $i <= $number; $i++)
			{
				$emails[] = $this->format_message($i, $withbody);
			}
		}

		return $emails;
	}


	/**
	 * Get unread messages
	 *
	 * @param int    $number   Number of messages. 0 to get all
	 * @param string $order    ASC or DESC
	 * @param bool   $withbody Get message body
	 * @return array
	 */
	public function get_unread_messages($number = 0, $order = 'DESC', $withbody = FALSE)
	{
		if ($number == 0)
		{
			$number = $this->count_unread_messages();
		}
		$emails = array();
		$result = imap_search($this->imap_stream, 'UNSEEN');
		if ($result)
		{
			$ids = array();
			foreach ($result as $k => $i)
			{
				$ids[] = $i;
			}
			$ids = array_chunk($ids, $number);

			$emails = array();
			foreach ($ids[0] as $id)
			{
				$emails[] = $this->format_message($id, $withbody);
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
	 * @param int  $id   Message id
	 * @param bool $seen True if message is read, false if message is unread
	 * @return bool True on success
	 */
	public function set_unseen_message($id = 0, $seen = TRUE)
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

		$flags .= (($seen == TRUE) ? '\\Seen ' : ' ');
		//echo "\n<br />".$id.": ".$flags;
		imap_clearflag_full($this->imap_stream, $id, '\\Seen', ST_UID);

		return imap_setflag_full($this->imap_stream, $id, trim($flags), ST_UID);
	}


	public function move_message($id = 0, $target = '')
	{
		return $this->move_messages(array($id), $target);
	}


	public function move_messages($ids = array(), $target = '')
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
	 * @return bool
	 */
	public function save_message_in_sent($header = '', $body = '')
	{
		return imap_append($this->imap_stream, $this->imap_mailbox . $this->get_sent(), $header . "\r\n" . $body . "\r\n", "\\Seen");
	}


	public function delete_message($id = 0)
	{
		return $this->delete_message(array($id));
	}


	public function delete_messages($ids = array())
	{
		if (imap_mail_move($this->imap_stream, implode(",", $ids), $this->get_trash(), CP_UID) == FALSE)
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
		$emails = array();
		foreach ($this->get_folders() as $folder)
		{
			$this->select_folder($folder);
			foreach ($this->get_messages(FALSE) as $message)
			{
				$emails[] = $message['from'];
				$emails = array_merge($emails, $message['to']);
				if (isset($message['cc']))
				{
					$emails = array_merge($emails, $message['cc']);
				}
			}
		}
		$this->select_folder($saveCurrentFolder);

		return array_unique($emails);
	}


	/**
	 * Clean folder content of selected folder
	 *
	 * @return bool True on success
	 */
	public function purge()
	{
		// delete trash and spam
		if ($this->folder == $this->get_trash() || strtolower($this->folder) == "spam")
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

		$filename = $partStruct->dparameters[0]->value;

		$message = imap_fetchbody($this->imap_stream, $id, $attachment['partNum']);

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

		$file = array(
			"name" => $filename,
			"size" => $attachment['size'],
		);

		if ($tmp_path != '')
		{
			$file['content'] = $tmp_path . $filename;
			$fp = fopen($file['content'], "wb");
			fwrite($fp, $message);
			fclose($fp);
		}
		else
		{
			$file['content'] = $message;
		}

		return $file;
	}


	protected function get_trash()
	{
		foreach ($this->get_folders() as $folder)
		{
			if (strtolower($folder) === "trash" || strtolower($folder) === "papierkorb")
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
			if (strtolower($folder) === "sent" || strtolower($folder) === "gesendet")
			{
				return $folder;
			}
		}

		// no sent folder found? create one
		$this->add_folder('Sent');

		return 'Sent';
	}


	protected function format_message($id = 0, $withbody = TRUE)
	{
		$header = imap_headerinfo($this->imap_stream, $id);

		// fetch unique uid
		$uid = imap_uid($this->imap_stream, $id);

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
		$email = array(
			'to'       => isset($header->to) ? $this->array_to_address($header->to) : '',
			'from'     => $this->to_address($header->from[0]),
			'date'     => $header->date,
			'subject'  => $subject,
			'uid'      => $uid,
			'unread'   => strlen(trim($header->Unseen)) > 0,
			'answered' => strlen(trim($header->Answered)) > 0
		);
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
				$arr = array();
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

		return $email;
	}


	protected function convert_to_utf8($str = '')
	{
		if (mb_detect_encoding($str, "UTF-8, ISO-8859-1, GBK") != "UTF-8")
		{
			$str = utf8_encode($str);
		}
		$str = iconv('UTF-8', 'UTF-8//IGNORE', $str);

		return $str;
	}


	protected function array_to_address($addresses = array())
	{
		$addressesAsString = array();
		foreach ($addresses as $address)
		{
			$addressesAsString[] = $this->to_address($address);
		}

		return $addressesAsString;
	}


	protected function to_address($headerinfos = array())
	{
		$email = "";

		if (isset($headerinfos->mailbox) && isset($headerinfos->host))
		{
			$email = $headerinfos->mailbox . "@" . $headerinfos->host;
		}

		if ( ! empty($headerinfos->personal))
		{
			$name = imap_mime_header_decode($headerinfos->personal);
			$name = $name[0]->text;
		}
		else
		{
			$name = $email;
		}

		$name = $this->convert_to_utf8($name);

		return $name . " <" . $email . ">";
	}


	/**
	 * Fetch header by message id
	 *
	 * @param int $id Message id
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
		$body = $this->get_part($this->imap_stream, $uid, "TEXT/HTML");
		$html = TRUE;
		// if HTML body is empty, try getting text body
		if ($body == "")
		{
			$body = $this->get_part($this->imap_stream, $uid, "TEXT/PLAIN");
			$html = FALSE;
		}
		$body = $this->convert_to_utf8($body);

		return array(
			'body' => $body,
			'html' => $html
		);
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
					$prefix = "";
					if ($partNumber)
					{
						$prefix = $partNumber . ".";
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
		$primaryMimetype = array(
			"TEXT",
			"MULTIPART",
			"MESSAGE",
			"APPLICATION",
			"AUDIO",
			"IMAGE",
			"VIDEO",
			"OTHER"
		);

		if ($structure->subtype)
		{
			return $primaryMimetype[(int)$structure->type] . "/" . $structure->subtype;
		}

		return "TEXT/PLAIN";
	}


	protected function attachments_to_name($attachments = array())
	{
		$names = array();
		foreach ($attachments as $attachment)
		{
			$names[] = array(
				'name' => $attachment['name'],
				'size' => $attachment['size']
			);
		}

		return $names;
	}


	protected function get_attachments($mailNum, $part, $partNum = '')
	{
		$attachments = array();

		if (isset($part->parts))
		{
			foreach ($part->parts as $key => $subpart)
			{
				if ($partNum != "")
				{
					$newPartNum = $partNum . "." . ($key + 1);
				}
				else
				{
					$newPartNum = ($key + 1);
				}
				$result = $this->get_attachments($mailNum, $subpart, $newPartNum);
				if (count($result) != 0)
				{
					array_push($attachments, $result);
				}
			}
		}
		else if (isset($part->disposition))
		{
			if (strtolower($part->disposition) == "attachment")
			{
				$partStruct = imap_bodystruct($this->imap_stream, $mailNum, $partNum);
				$attachmentDetails = array();
				if (isset($part->dparameters[0]))
				{
					$attachmentDetails = array(
						"name"    => $part->dparameters[0]->value,
						"partNum" => $partNum,
						"enc"     => $partStruct->encoding,
						"size"    => $part->bytes
					);
				}

				return $attachmentDetails;
			}
		}

		return $attachments;
	}

}