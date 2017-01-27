<?php
/**
*
* @package phpBB Extension - IPCF 1.0.0 -(IP Country Flag)
* @copyright (c) 2005, 2008 , 2017 - 3Di http://3di.space/32/
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace threedi\ipcf\event;

/**
* @ignore
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\cache\service */
	protected $cache;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/* @var \threedi\ipcf\core\ipcf_functions */
	protected $ipcf_functions;

	/**
		* Constructor
		*
		* @param \phpbb\auth\auth					$auth				Authentication object
		* @param \phpbb\cache\service				$cache
		* @param \phpbb\config\config				$config				Config Object
		* @param \phpbb\db\driver\driver			$db					Database object
		* @param \phpbb\user						$user				User Object
		* @param \phpbb\request\request				$request			Request object
		* @param \phpbb\template\template			$template			Template object
		* @param \threedi\ipcf\core\ipcf_functions	$ipcf_functions		Methods to be used by Class
		* @access public
	*/
	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\cache\service $cache,
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\user $user,
		\phpbb\request\request $request,
		\phpbb\template\template $template,
		\threedi\ipcf\core\ipcf_functions $ipcf_functions)
	{
		$this->auth				=	$auth;
		$this->cache			=	$cache;
		$this->config			=	$config;
		$this->db				=	$db;
		$this->user				=	$user;
		$this->request			=	$request;
		$this->template			=	$template;
		$this->ipcf_functions	=	$ipcf_functions;
	}

	/* Config time for cache, hinerits from View online time span */
	//$config_time_cache = ( (int) ($this->config['load_online_time'] * 60) ); // not yet in use
	// if empty($this->user->data['user_avatar']) // just a note to self ;)

	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'							=>	'load_language_on_setup',
			'core.permissions'							=>	'permissions',
			'core.page_header_after'					=>	'icpf_template_switch',
			'core.session_ip_after'						=>	'ipcf_no_cloudflare',
			'core.viewtopic_modify_post_row'			=>	'viewtopic_flags',
			'core.obtain_users_online_string_sql'		=>	'ipcf_obtain_users_online_string_sql_add',
			'core.obtain_users_online_string_modify'	=>	'users_online_string_flags',
		);
	}

	/**
	 * Main language file inclusion
	 */
	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'threedi/ipcf',
			'lang_set' => 'ipcf',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * Permission's language file is automatically loaded
	 */
	public function permissions($event)
	{
		$permissions = $event['permissions'];
		$permissions += array(
			'u_allow_ipcf' => array(
				'lang'	=> 'ACL_U_ALLOW_IPCF',
				'cat'	=> 'misc'
			),
		);
		$event['permissions'] = $permissions;
	}

	/**
	* template switch over all
	*/
	public function icpf_template_switch($event)
	{
		$this->template->assign_vars(array(
			'S_IPCF'			=>	($this->auth->acl_get('u_allow_ipcf')) ? true : false,
			'IPCF_CREDIT_LINE'	=>	$this->user->lang('POWERED_BY_IPCF', $this->user->lang('POWERED_BY_IPCF_DETAILS')),
		));
	}

	/**
	 * Event to alter user IP address
	 *
	 * @event core.session_ip_after
	 * @var	string	ip	REMOTE_ADDR
	 * @since 3.1.10-RC1
	 */
	public function ipcf_no_cloudflare($event)
	{
		$ip_check = $event['ip'];

		/**
		 * Is Cloudflare in action?
		 * htmlspecialchars_decode returns a (string) already.
		 * Ternary Operators improve performance.
		 * I can't use the Null Coalescing Operator (PHP7) here because of BC with phpBB 3.1.x
		 */
		$ip_check = ($this->request->server('HTTP_CF_CONNECTING_IP') != '') ? htmlspecialchars_decode($this->request->server('HTTP_CF_CONNECTING_IP')) : htmlspecialchars_decode($this->request->server('REMOTE_ADDR'));
		/**
		 * This part assigns/updates the user session's isocode on login/registration
		 * Obtaining a univoque isocode per session
		 * The goal is to save as much queries I can
		 */
		$ip_to_isocode = $this->ipcf_functions->obtain_country_isocode_curl($ip_check);

		$sql = 'SELECT u.user_id, s.session_id, s.session_user_id
			FROM ' . USERS_TABLE . ' u, ' . SESSIONS_TABLE . ' s
			WHERE u.user_id = s.session_user_id';
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$s_user_id = (int) $row['session_user_id'];

		$sql = 'UPDATE ' . USERS_TABLE . '
			SET user_isocode = "' . $ip_to_isocode . '"
			WHERE user_id = ' . $s_user_id . '';
		$this->db->sql_query($sql);

		$event['ip'] = $ip_check;
	}

	public function viewtopic_flags($event)
	{
		/**
		 * Check permission prior to run the code
		 */
		if ($this->auth->acl_get('u_allow_ipcf'))
		{
			$user_id = $event['post_row']['POSTER_ID'];
			/**
			 * The Flag Image itself lies here
			*/
			$sql = 'SELECT DISTINCT user_id, user_isocode
				FROM ' . USERS_TABLE . '
				WHERE user_id = ' . $user_id . '
					';

			$result = $this->db->sql_query($sql);
			$row2 = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			$user_isocode = (string) $row2['user_isocode'];
//--------- this @ masks a bug or an issue "unknown index" but the isocode is retrieved ---
			$country_flag = @$this->ipcf_functions->iso_to_flag_string_small($user_isocode);

			$flag_output = array('COUNTRY_FLAG'	=>	$country_flag);

			$event['post_row'] = array_merge($event['post_row'], $flag_output);
		}
	}

	/**
	 * We need the availability of the user's user_isocode
	 * for the event "users_online_string_flags", the next event's rowset
	 */
	public function ipcf_obtain_users_online_string_sql_add($event)
	{
		/**
		 * Check permission prior to run the code
		 */
		if ($this->auth->acl_get('u_allow_ipcf'))
		{
			$sql_ary = $event['sql_ary'];

			$sql_ary['SELECT'] .= ', user_isocode';

			$event['sql_ary'] = $sql_ary;
		}
	}

	/**
	 * Now we can play with it, the users's user_id saves our day
	 */
	public function users_online_string_flags($event)
	{
		/**
		 * Check permission prior to run the code
		 */
		if ($this->auth->acl_get('u_allow_ipcf'))
		{
			$rowset = $event['rowset'];
			$online_userlist = $event['online_userlist'];

			$username = $username_ipcf = array();

			foreach ($rowset as $row)
			{
				$user_isocode = $row['user_isocode'];

				$user_id = $row['user_id'];
				$user_id_flag = @$this->ipcf_functions->iso_to_flag_string_normal($user_isocode);
				$username[] = $row['username'];
				$username_ipcf[] = ($user_id_flag . ' ' . $row['username']);
			}
			if (sizeof($username))
			{
				$online_userlist = str_replace($username, $username_ipcf, $online_userlist);
			}

			$event['online_userlist'] = $online_userlist;
		}
	}
}
