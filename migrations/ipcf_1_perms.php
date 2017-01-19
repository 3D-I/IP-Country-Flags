<?php
/**
*
* @package phpBB Extension - IPCF 1.0.0 -(IP Country Flag)
* @copyright (c) 2005, 2008, 2017 - 3Di http://3di.space/32/
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace threedi\ipcf\migrations;

/*
	* add permission to view IP Country Flags to the Registered Users Group
	* Excluded are: BOTS, NEWLY REGISTERED, GUESTS
	* It is possible to give them this permission in ACP/permissions, though.
	*/
class ipcf_1_perms extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return array('\phpbb\db\migration\data\v310\dev');
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
