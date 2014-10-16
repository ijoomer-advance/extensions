<?php
/**
 * @package     IJoomer.Extensions
 * @subpackage  easyblog
 *
 * @copyright   Copyright (C) 2010 - 2014 Tailored Solutions PVT. Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

/**
 * class for categories
 *
 * @package     IJoomer.Extensions
 * @subpackage  easyblog
 * @since       1.0
 */
class Categories
{
	private $db;

	/**
	 * constructor
	 */
	public function __construct()
	{
		$this->db = JFactory::getDBO();
	}
}
