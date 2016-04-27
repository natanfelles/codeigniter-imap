<?php
/**
 * Imap_controller Class
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
 * Class Imap_controller
 *
 * @property Imap $imap
 */
class Imap_controller extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('imap');
	}


	public function index()
	{
		$config = array(
			'host'     => 'imap-mail.outlook.com',
			'encrypto' => 'ssl',
			'user'     => 'phpimapclient@outlook.com',
			'pass'     => 'Abcd12345**'
		);
		$this->imap->imap_connect($config);

		$data['output'] = array(
			'get_folders'    => $this->imap->get_folders(),
			'select_folder'  => $this->imap->select_folder('INBOX'),
			'count_messages' => $this->imap->count_messages(),
			//'count_unread_messages'   => $this->imap->count_unread_messages(),
			//'get_all_email_addresses' => $this->imap->get_all_email_addresses(),
			//'add_folder'  => $this->imap->add_folder('Test'),
			//'move_message'   => $this->imap->move_message(1, 'Test'),
			//'count_messages' => $this->imap->count_messages(),
			//'get_messages'   => $this->imap->get_messages(5),
			'get_message'    => $this->imap->get_message(1, TRUE),
			//'remove_folder'           => $this->imap->remove_folder('Notes'),
			//'remove_folder'           => $this->imap->remove_folder('Notes'),
		);

		//$data['output']['session'] = $this->session->all_userdata();

		$data['output']['elapsed_time'] = $this->benchmark->elapsed_time();
		$data['output']['memory_usage'] = $this->benchmark->memory_usage();

		$this->load->view('view', $data);
	}

}