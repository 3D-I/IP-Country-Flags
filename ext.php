<?php
/**
*
* @package phpBB Extension - IPCF 1.0.0-(IP Country Flag)
* @copyright (c) 2005, 2008, 2017 - 3Di http://3di.space/32/
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace threedi\ipcf;

/**
 * IPCF Extension base
 */
class ext extends \phpbb\extension\base
{
	/**
	 * Check whether or not the extension can be enabled.
	 *
	 * @return bool
	 */
	public function is_enableable()
	{
		/* @return bool */
		$bb3110 = ( phpbb_version_compare(PHPBB_VERSION, '3.1.10', '>=') && phpbb_version_compare(PHPBB_VERSION, '3.2.0@dev', '<') );

		/* @return bool */
		$bb320 = ( phpbb_version_compare(PHPBB_VERSION, '3.2.0', '>=') );

		/**
		 * We rely on constants.php
		 */
		if ( ( ($bb320) || ($bb3110) ) && (function_exists('curl_version')) )
		{
			return true;
		}
		else
		{
			SELF::verbose_it();
		}
	}

	/**
	 * Let's tell the user what exactly is going on and provide a back-link.
	 * Using the User Object for BC.
	 */
	public function verbose_it()
	{
		$this->container->get('user')->add_lang_ext('threedi/ipcf', 'ext_require');

		trigger_error($this->container->get('user')->lang['EXTENSION_REQUIREMENTS_NOTICE'] . adm_back_link(append_sid('index.' . $this->container->getParameter('core.php_ext'), 'i=acp_extensions&amp;mode=main')), E_USER_WARNING);
	}
}
