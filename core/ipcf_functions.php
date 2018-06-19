<?php
/**
*
* @package phpBB Extension - IPCF 1.0.0 -(IP Country Flag)
* @copyright (c) 2005, 2008 , 2017 - 3Di http://3di.space/32/
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace threedi\ipcf\core;

use threedi\ipcf\core\ipcf_constants;

class ipcf_functions
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver */
	protected $db;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\extension\manager "Extension Manager" */
	protected $ext_manager;

	/** @var \phpbb\path_helper */
	protected $path_helper;

	/**
	 * Constructor
	 *
	* @param \phpbb\config\config			$config				Config Object
	 * @param \phpbb\db\driver\driver		$db					Database object
	 * @param \phpbb\user					$user				User object
	 * @param \phpbb\extension\manager		$ext_manager		Extension manager object
	 * @param \phpbb\path_helper			$path_helper		Path helper object
	 */
	public function __construct(
			\phpbb\config\config $config,
			\phpbb\db\driver\driver_interface $db,
			\phpbb\user $user,
			\phpbb\extension\manager $ext_manager,
			\phpbb\path_helper $path_helper)
	{
		$this->config		=	$config;
		$this->db			=	$db;
		$this->user			=	$user;
		$this->ext_manager	=	$ext_manager;
		$this->path_helper	=	$path_helper;

		$this->ext_path		=	$this->ext_manager->get_extension_path('threedi/ipcf', true);
		$this->ext_path_web	=	$this->path_helper->update_web_root_path($this->ext_path);
	}

	/**
	 * Returns whether cURL is available
	 *
	 * @return bool
	 */
	public function is_curl()
	{
		return function_exists('curl_version');
	}

	/**
	 * Returns the IP to Country Flag small string from the ISO Country Code
	 *
	 * @return string
	 */
	public function iso_to_flag_string_small($iso_country_code)
	{
		$country_flag = '<img class="flag_image_small" src="' . $this->ext_path_web . 'images/flags/small/' .  $iso_country_code . '.png" alt="' . $this->user->lang['country'][strtoupper($iso_country_code)] . '" title="' . $this->user->lang['country'][strtoupper($iso_country_code)] . '" />';

		return $country_flag;
	}

	/**
	 * Returns the IP to Country Flag normal string from the ISO Country Code
	 *
	 * @return string
	 */
	public function iso_to_flag_string_normal($iso_country_code)
	{
		$country_flag = '<img class="flag_image_normal" src="' . $this->ext_path_web . 'images/flags/' .  $iso_country_code . '.png" alt="' . $this->user->lang['country'][strtoupper($iso_country_code)] . '" title="' . $this->user->lang['country'][strtoupper($iso_country_code)] . '" />';

		return $country_flag;
	}

	/**
	 * Returns the IP to Country Flag for avatars string from the ISO Country Code
	 *
	 * @return string
	 */
	public function iso_to_flag_string_avatar($iso_country_code)
	{
		$country_flag = '<img class="flag_image_avatar" src="' . $this->ext_path_web . 'images/avatars/' .  $iso_country_code . '.gif" alt="' . $this->user->lang['country'][strtoupper($iso_country_code)] . '" title="' . $this->user->lang['country'][strtoupper($iso_country_code)] . '" />';

		return $country_flag;
	}

	/**
	 * Obtain Country isocode from cURL
	 *
	 * @return string country iso code
	 */
	public function obtain_country_isocode_curl($user_session_ip)
	{
		/**
		 * First check wheter cURL is available
		*/
		$is_curl = $this->is_curl();

		if ($is_curl)
		{
			/* Some code borrowed from david63's Cookie Policy ext */
			$curl_handle = curl_init();
			curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
			curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl_handle, CURLOPT_URL, 'freegeoip.net/json/' . $user_session_ip);

			/**
			 * @return mixed The IP data array, or false if error
			 */
			$ip_query = curl_exec($curl_handle);

			$http_code	= curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
			curl_close($curl_handle);

			if ($ip_query)
			{
				/* Creating an array from string */
				$ip_array = @json_decode($ip_query, true);

				if ( ($ip_array['country_code'] != '') && ($http_code == 200) )
				{
					$iso_country_code	=	strtolower($ip_array['country_code']);
				}
				else
				{
					/**
					 * error 403 forbidden (too many requests) or any other thing
					 * WO means World, aka Unknown IP
					*/
					$failure			=	ipcf_constants::FLAG_WORLD;
					$iso_country_code	=	strtolower($failure);
				}
			}
			else
			{
				/**
				 * http_code = 0, doing the dirty job here
				 * WO means World, aka Unknown IP
				*/
				$failure			=	ipcf_constants::FLAG_WORLD;
				$iso_country_code	=	strtolower($failure);
			}
		}
		/**
		 * No cURL? That shouldn't happens but..
		 * WO means World, aka Unknown IP
		*/
		else
		{
			$failure			=	ipcf_constants::FLAG_WORLD;
			$iso_country_code	=	strtolower($failure);
		}

		return ($iso_country_code);
	}

	/**
	 * Obtain Country isocode from cURL
	 *
	 * @return string session user's isocode
	 */
	public function obtain_user_isocode($user_isocode = 0)
	{
		/**
		 * Let's configure the TTL for caching
		 */
		$sql = 'SELECT u.user_id, s.session_user_id, s.session_time, s.session_ip
			FROM ' . USERS_TABLE . ' u, ' . SESSIONS_TABLE . ' s
			WHERE u.user_id > ' . ANONYMOUS . '
				AND s.session_time >= ' . (time() - $this->config['session_length']) . '
				AND u.user_id = s.session_user_id
			GROUP BY u.user_id
			ORDER BY s.session_time DESC, u.user_id, s.session_ip';
		$result = $this->db->sql_query($sql);
		/**
		 * Let's push/update the users' isocode
		 */
		while ($row = $this->db->sql_fetchrow($result))
		{
			$s_user_ip = (string) $row['session_ip'];

			$ip_to_isocode = $this->obtain_country_isocode_curl($s_user_ip);

			$s_user_id = (int) $row['session_user_id'];

			$sql = 'UPDATE ' . USERS_TABLE . '
				SET user_isocode = "' . $ip_to_isocode . '"
				WHERE user_id > ' . ANONYMOUS . '
					AND user_id = ' . (int) $s_user_id . '';
			$this->db->sql_query($sql);
		}
		$this->db->sql_freeresult($result);

		return($user_isocode);
	}
}
