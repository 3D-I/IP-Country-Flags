<?php
/**
*
* @package phpBB Extension - IPCF 1.0.0 -(IP Country Flag)
* @copyright (c) 2005, 2008, 2017 - 3Di http://3di.space/32/
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace threedi\ipcf\migrations;

/**
 * Add the (already activated) permission "view IP Country Flags" to the Registered Users Group(s)
 * Inactive as per default for BOTS, NEWLY_REGISTERED and GUESTS.
 */
class ipcf_1_perms extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return array('\phpbb\db\migration\data\v31x\v3110');
	}

	public function update_data()
	{
		return array(
			array('permission.add', array('u_allow_ipcf')),
			array('permission.permission_set', array('REGISTERED', 'u_allow_ipcf', 'group')),
		);
	}
	public function revert_data()
	{
		return array(
			array('permission.remove', array('u_allow_ipcf')),
			array('permission.permission_unset', array('REGISTERED', 'u_allow_ipcf', 'group')),
		);
	}
}
