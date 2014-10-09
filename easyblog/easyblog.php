<?php
/**
 * @package     IJoomer.extensions
 * @subpackage  easyblog
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

class easyblog
{
	public $classname = "easyblog";
	public $sessionWhiteList = array('categories.allCategories', 'categories.singleCategory', 'categories.category', 'categories.categoryBlog');

	function init()
	{
		include_once JPATH_SITE . '/components/com_easyblog/models/blog.php';
		include_once JPATH_SITE . '/components/com_easyblog/models/blogs.php';

		$lang = JFactory::getLanguage();
		$lang->load('com_easyblog');
		$plugin_path = JPATH_COMPONENT_SITE . '/extensions';
		$lang->load('easyblog', $plugin_path . '/easyblog', $lang->getTag(), true);
	}

}

function getconfig()
{
	$jsonarray = array();

	return $jsonarray;
}

function prepareHTML(&$Config)
{
	//TODO : Prepare custom html for EASYBLOG
}
}

