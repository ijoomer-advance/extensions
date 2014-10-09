<?php
/**
 * @package     IJoomer.extensions
 * @subpackage  easyblog
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

class categories
{

	private $db;

	function __construct()
	{
		$this->db = JFactory::getDBO();
	}
}
