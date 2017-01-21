<?php
/**
*
* @package phpBB Extension - IPCF 1.0.0 -(IP Country Flag)
* @copyright (c) 2005 - 2008 - 2016 3Di (Marco T.)
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace threedi\ipcf\core;

use threedi\ipcf\core\ipcf_constants;

class ipcf_functions
{
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
	 * @param \phpbb\db\driver\driver		$db					Database object
	 * @param \phpbb\user					$user				User object
	 * @param \phpbb\extension\manager		$ext_manager		Extension manager object
	 * @param \phpbb\path_helper			$path_helper		Path helper object
	 */
	public function __construct(
			\phpbb\db\driver\driver_interface $db,
			\phpbb\user $user,
			\phpbb\extension\manager $ext_manager,
			\phpbb\path_helper $path_helper)
	{
		$this->db			= $db;
		$this->user			= $user;
		$this->ext_manager	= $ext_manager;
		$this->path_helper	= $path_helper;

		$this->ext_path		= $this->ext_manager->get_extension_path('threedi/ipcf', true);
		$this->ext_path_web	= $this->path_helper->update_web_root_path($this->ext_path);
	}

	/**
	 * Obtain suser_session_flag
	 *
	 * @return string user_session_flag
	 */
	public function user_session_flag($user_id)
	{
		$sql = 'SELECT DISTINCT session_ip
			FROM ' . SESSIONS_TABLE . '
				WHERE session_user_id = ' . $user_id . '
					AND ' . $user_id . ' > ' . ANONYMOUS . '';
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);

		$user_session_ip = $row['session_ip'];

		$user_session_flag = $this->obtain_country_flag_string($user_session_ip);

		$this->db->sql_freeresult($result);

		return $user_session_flag;
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

// TODO: handling the future implementation of Flag Normal and Flag Avatar
// Flag Small is right for topics and posts and so on..

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
	 * Returns the IP to Country Flag for Avatars string from the ISO Country Code
	 *
	 * @return string
	 */
	public function iso_to_flag_string_avatar($iso_country_code)
	{
		$country_flag = '<img class="flag_image_avatar" src="' . $this->ext_path_web . 'images/avatars/' .  $iso_country_code . '.gif" alt="' . $this->user->lang['country'][strtoupper($iso_country_code)] . '" title="' . $this->user->lang['country'][strtoupper($iso_country_code)] . '" />';

		return $country_flag;
	}

	/**
	 * Obtain Country Flag string from cURL
	 *
	 * @return string country_flag
	 */
	public function obtain_country_flag_string_curl($user_session_ip)
	{
		/* Some code borrowed from david63's Cookie Policy ext */
		$curl_handle = curl_init();
		curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
		curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl_handle, CURLOPT_URL, 'freegeoip.net/json/' . $user_session_ip);

		/* return (string) or false (bool) */
		$ip_query = curl_exec($curl_handle);

		$http_code	= curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
		curl_close($curl_handle);

		if ($ip_query)
		{
			/* Creating an array from string*/
			$ip_array = @json_decode($ip_query, true);

			if ( ($ip_array['country_code'] != '') && ($http_code == 200) )
			{
				$iso_country_code	=	strtolower($ip_array['country_code']);
				$country_flag		=	$this->iso_to_flag_string_small($iso_country_code);
			}
			/**
			 * Unknown or reserved IPS here
			*/
			else
			{
				/**
				 * error 403 forbidden (too many requests) or any other thing
				 * WO represents my flag of World, aka Unknown IP
				*/
				$failure			=	ipcf_constants::FLAG_WORLD;
				$iso_country_code	=	strtolower($failure);
				$country_flag		=	$this->iso_to_flag_string_small($iso_country_code);
			}
		}
		else
		{
			/**
			 * http_code = 0, doing the dirty job here
			 * WO represents my flag of World, aka Unknown IP
			*/
			$failure			=	ipcf_constants::FLAG_WORLD;
			$iso_country_code	=	strtolower($failure);
			$country_flag		=	$this->iso_to_flag_string_small($iso_country_code);
		}

		return ($country_flag);
	}

	/**
	 * Obtain Country Flag string from file_get_contents
	 *
	 * @return string country_flag
	 */
	public function obtain_country_flag_string_fcg($user_session_ip)
	{
		/**
		 * 16386 = countryCode,status fields, using magic numbers to save bandwidth
		*/
		$json_response = @json_decode(file_get_contents('freegeoip.net/json/' . $user_session_ip));

		if (($json_response->country_code) != '')
		{
			$iso_country_code	=	strtolower($json_response->countryCode);
			$country_flag		=	$this->iso_to_flag_string_small($iso_country_code);
		}
		/**
		 * Unknown or reserved IPS here
		*/
		else if (($json_response->status) == 'fail')
		{
			/**
			 * WO represents my flag of World, aka Unknown IP
			*/
			$failure			=	ipcf_constants::FLAG_WORLD;
			$iso_country_code	=	strtolower($failure);
			$country_flag		=	$this->iso_to_flag_string_small($iso_country_code);
		}
		else
		{
			/**
			 * Server's outage, doing the dirty job here
			 * WO represents my flag of World, aka Unknown IP
			*/
			$failure			=	ipcf_constants::FLAG_WORLD;
			$iso_country_code	=	strtolower($failure);
			$country_flag		=	$this->iso_to_flag_string_small($iso_country_code);
		}

		return ($country_flag);
	}

	/**
	 * Obtain Country Flag string
	 *
	 * @return string country_flag
	 */
	public function obtain_country_flag_string($user_session_ip)
	{
		/**
		 * The Flag Image itself lies here
		 * First we check if cURL is available here
		*/
		$is_curl = $this->is_curl();

		if ($is_curl)
		{
			$country_flag = ( $this->obtain_country_flag_string_curl($user_session_ip) );
		}
		/**
		 * No cURL? Let's try another approach with file_get_contents
		*/
		else
		{
			$country_flag = 'wo';
			//$country_flag = ( $this->obtain_country_flag_string_fcg($user_session_ip) );
		}

		return ($country_flag);
	}
}
