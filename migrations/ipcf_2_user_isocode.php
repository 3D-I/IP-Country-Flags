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
 * Adds the column "user_isocode" to the fields list of the USERS_TABLE
 * Index is being populated with the default Flag "wo" (aka unknown IP)
 */
class ipcf_2_user_isocode extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		/* If doesn't exists go ahead */
		return $this->db_tools->sql_index_exists($this->table_prefix . 'users', 'user_isocode');
	}

	static public function depends_on()
	{
		return array('\threedi\ipcf\migrations\ipcf_1_perms');
	}

	public function update_schema()
	{
		return array(
			'add_columns'	=> array(
				$this->table_prefix . 'users'	=>	array(
					'user_isocode'	=> array('VCHAR:30', 'wo'),
				),
			),
		);
	}

	public function revert_schema()
	{
		return array(
			'drop_columns'	=> array(
				$this->table_prefix . 'users'	=>	array(
					'user_isocode',
				),
			),
		);
	}
}
