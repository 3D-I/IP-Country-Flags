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
		if ($this->curl_required() && ( $this->phpbb_ascraeus_requirements() || $this->phpbb_rhea_requirements() ))
		{
			return true;
		}
		else
		{
			$this->verbose_it();
		}
	}

	/**
	 * Check PHP requirements
	 * Requires cURL
	 *
	 * @return bool
	 */
	protected function curl_required()
	{
		return function_exists('curl_version');
	}

	/**
	 * Check phpBB 3.1 compatibility
	 * Requires phpBB 3.1.10 or greater
	 *
	 * @return bool
	 */
	protected function phpbb_ascraeus_requirements()
	{
		return phpbb_version_compare(PHPBB_VERSION, '3.1.10', '>=') && phpbb_version_compare(PHPBB_VERSION, '3.2.0-dev', '<');
	}

	/**
	 * Check phpBB 3.2 (and later) compatibility
	 * Requires phpBB 3.2.0
	 *
	 * @return bool
	 */
	protected function phpbb_rhea_requirements()
	{
		return phpbb_version_compare(PHPBB_VERSION, '3.2.0', '>=');
	}

	/**
	 * Let's tell the user what exactly is going on failure, provides a backlink.
	 *
	 * Using the User Object for the BC's sake.
	 */
	protected function verbose_it()
	{
		$this->container->get('user')->add_lang_ext('threedi/ipcf', 'ext_require');

		trigger_error($this->container->get('user')->lang['EXTENSION_REQUIREMENTS_NOTICE'] . adm_back_link(append_sid('index.' . $this->container->getParameter('core.php_ext'), 'i=acp_extensions&amp;mode=main')), E_USER_WARNING);
	}
}
