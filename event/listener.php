<?php
/**
*
* @package phpBB Extension - IPCF 1.0.0 -(IP Country Flag)
* @copyright (c) 2005 - 2008 - 2016 3Di (Marco T.)
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
		* @param \phpbb\user						$user				User Object
		* @param \phpbb\db\driver\driver			$db					Database object
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
		\phpbb\template\template $template,
		\threedi\ipcf\core\ipcf_functions $ipcf_functions)
	{
		$this->auth				=	$auth;
		$this->cache			=	$cache;
		$this->config			=	$config;
		$this->db				=	$db;
		$this->user				=	$user;
		$this->template			=	$template;
		$this->ipcf_functions	=	$ipcf_functions;
	}


	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'							=>	'load_language_on_setup',
			'core.permissions'							=>	'permissions',
			'core.page_header_after'					=>	'icpf_template_switch',
			'core.viewtopic_modify_post_row'			=>	'viewtopic_flags',
			'core.obtain_users_online_string_modify'	=>	'users_online_string_flags',
		);
	}

	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'threedi/ipcf',
			'lang_set' => 'ipcf',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/* Permission's language file is automatically loaded */
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

	/* template switch over all */
	public function icpf_template_switch($event)
	{
		$this->template->assign_vars(array(
			'S_IPCF'	=>	($this->auth->acl_get('u_allow_ipcf')) ? true : false,
		));
	}

	/* Config time for cache, hinerits from View online time span */
	//$config_time_cache = ( (int) ($this->config['load_online_time'] * 60) ); // not yet in use

	public function viewtopic_flags($event)
	{
		/* Check permission before to run the code */
		if ($this->auth->acl_get('u_allow_ipcf'))
		{
			$user_id = $event['post_row']['POSTER_ID'];

			/**
			 * The Flag Image itself lies here
			*/
			$country_flag = $this->ipcf_functions->user_session_flag( (int) $user_id);

			$flag_output = array('COUNTRY_FLAG'	=>	$country_flag);
			$event['post_row'] = array_merge($event['post_row'], $flag_output);
		}
	}

	public function users_online_string_flags($event)
	{
		/* Check permission before to run the code */
		if ($this->auth->acl_get('u_allow_ipcf'))
		{
			$rowset = $event['rowset'];
			$user_online_link = $event['user_online_link'];
			$online_userlist = $event['online_userlist'];

			$flag = array();

			foreach ($rowset as $key => $value)
			{
				$flag[$value['user_id']] = $this->ipcf_functions->user_session_flag( (int) $value['user_id']);
			}
			foreach ($user_online_link as $key => $value)
			{
				$user_online_link[$key] = $flag[$key] . ' ' . $user_online_link[$key];
			}
			$event['online_userlist'] = $this->user->lang['REGISTERED_USERS'] . ' ' . implode(', ', $user_online_link);
		}
	}
}
