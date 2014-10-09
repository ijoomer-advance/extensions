<?php
/**
 * @package     IJoomer.extensions
 * @subpackage  easyblog
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

class easyblog_helper
{

	private $db_helper;

	function __construct()
	{
		$this->db_helper = JFactory::getDBO();
	}

	function getAllBlogList()
	{
		require_once JPATH_ADMINISTRATOR . '/components/com_easyblog/models/blogs.php';
		$class = new EasyBlogModelBlogs;
		$query = $class->getBlogs();

		$this->db_helper->setQuery($query);
		$result = $this->db_helper->loadObjectList();

		return $result;
	}

	function getAllBlogCategory()
	{
		require_once JPATH_ADMINISTRATOR . '/components/com_easyblog/models/categories.php';
		$class = new EasyBlogModelCategories;
		$query = $class->_buildQuery();

		$this->db_helper->setQuery($query);
		$result = $this->db_helper->loadObjectList();

		return $result;
	}
}
