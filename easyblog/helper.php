<?php
/**
 * @package     IJoomer.Extensions
 * @subpackage  easyblog
 *
 * @copyright   Copyright (C) 2010 - 2014 Tailored Solutions PVT. Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
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
