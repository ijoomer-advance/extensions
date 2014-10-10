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
 * class for easyblog
 *
 * @package     IJoomer.Extensions
 * @subpackage  easyblog
 * @since       1.0
 */
class easyblog
{
	public $classname = "easyblog";
	public $sessionWhiteList = array('categories.allCategories', 'categories.singleCategory', 'categories.category', 'categories.categoryBlog');

	/**
	 * init function
	 *
	 * @return  void
	 */
	function init()
	{
		include_once JPATH_SITE . '/components/com_easyblog/models/blog.php';
		include_once JPATH_SITE . '/components/com_easyblog/models/blogs.php';

		$lang = JFactory::getLanguage();
		$lang->load('com_easyblog');
		$plugin_path = JPATH_COMPONENT_SITE . '/extensions';
		$lang->load('easyblog', $plugin_path . '/easyblog', $lang->getTag(), true);
	}

	/**
	 * getconfig function
	 *
	 * @return  array of json
	 */
	function getconfig()
	{
		$jsonarray = array();

		return $jsonarray;
	}

	/**
	 * prepareHTML function for Prepare custom html for EASYBLOG
	 *
	 * @param   array  &$Config  Configuration array
	 * @return  void
	 */
	function prepareHTML(&$Config)
	{
		//TODO : Prepare custom html for EASYBLOG
	}
}
