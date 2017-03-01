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
	protected $cache; // NOT yet in use (do we need this object?)

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
	 * @param \phpbb\cache\service				$cache		(NOT yet in use             )
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

	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'							=>	'load_language_on_setup',
			'core.permissions'							=>	'permissions',
			'core.page_header_after'					=>	'icpf_template_switch',
			'core.session_ip_after'						=>	'ipcf_no_cloudflare',
			'core.viewtopic_cache_user_data'			=>	'viewtopic_cache_user_data',
			'core.viewtopic_modify_post_row'			=>	'viewtopic_flags',
			'core.obtain_users_online_string_sql'		=>	'ipcf_obtain_users_online_string_sql_add',
			'core.obtain_users_online_string_modify'	=>	'users_online_string_flags',
		);
	}

	/**
	 * Main language file inclusion and user's isocode
	 *
	 * @event core.user_setup
	 */
	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'threedi/ipcf',
			'lang_set' => 'ipcf',
		);
		$event['lang_set_ext'] = $lang_set_ext;

		/**
		 * Check permission prior to run the code
		 */
		if ($this->auth->acl_get('u_allow_ipcf'))
		{
			$user_data = $event['user_data'];

			/**
			 * This part assigns/updates the user's isocode on login/registration
			 *
			 * Obtains a univoque isocode per user/session
			 */
			$this->ipcf_functions->obtain_user_isocode();

			$event['user_data'] = $user_data;
		}
	}

	/**
	 * Permission's language file is automatically loaded
	 *
	 * @event core.permissions
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
	 * Template switches over all
	 *
	 * @event core.page_header_after
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
		 * Is Cloudflare in action? If yes means nof correct Flags ^__O
		 * htmlspecialchars_decode returns a (string) already.
		 * Ternary Operators improve performance.
		 * I can't use the Null Coalescing Operator (PHP7) here because of BC with phpBB 3.1.x
		 */
		$ip_check = ($this->request->server('HTTP_CF_CONNECTING_IP') != '') ? htmlspecialchars_decode($this->request->server('HTTP_CF_CONNECTING_IP')) : htmlspecialchars_decode($this->request->server('REMOTE_ADDR'));

		$event['ip'] = $ip_check;
	}

	/**
	 * Modify the users' data displayed within their posts
	 *
	 * @event core.viewtopic_cache_user_data
	 */
	public function viewtopic_cache_user_data($event)
	{
		/**
		 * Check permission prior to run the code
		 */
		if ($this->auth->acl_get('u_allow_ipcf'))
		{
			$array = $event['user_cache_data'];

			/**
			 * The migration here rules, a default user_isocode is always present (wo)
			 */
			$user_isocode = (string) $event['row']['user_isocode'];
			$array['user_isocode']	=	(empty($array['user_isocode'])) ? $this->ipcf_functions->iso_to_flag_string_small($user_isocode) : '';

			/**
			 * A default Country Flag avatar is being assigned in view-topic
			 * for those users who posted and don't have one already.
			 *
			 * The WO (World) flag (unknown IP) is shown by default until
			 * they log back in then the DB will be updated with the latests iso_code.
			 *
			 * Since IPCF 1.0.0-b3.
			 */
			$array['avatar'] = (empty($array['avatar'])) ? $this->ipcf_functions->iso_to_flag_string_avatar($user_isocode) : $array['avatar'];

			$event['user_cache_data'] = $array;
		}
	}

	/**
	 * Modify the posts template block
	 *
	 * @event core.viewtopic_modify_post_row
	 */
	public function viewtopic_flags($event)
	{
		/**
		 * Check permission prior to run the code
		 */
		if ($this->auth->acl_get('u_allow_ipcf'))
		{
			/* Inspired by Annual Stars ext */
			$event['post_row'] = array_merge($event['post_row'], array(
				'POSTER_AVATAR'	=>	$event['user_poster_data']['avatar'],
				'COUNTRY_FLAG'	=>	$event['user_poster_data']['user_isocode'])
			);
		}
	}

	/**
	 * We need the availability of the user's isocode
	 * for the event "users_online_string_flags", the next event's rowset
	 *
	 * @event core.obtain_users_online_string_sql
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
	 * Now we can play with it, the users's isocode saves our day
	 *
	 * @event core.obtain_users_online_string_modify
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
				$user_id_flag = $this->ipcf_functions->iso_to_flag_string_normal($user_isocode);
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
