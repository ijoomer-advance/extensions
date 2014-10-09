<?php
/*--------------------------------------------------------------------------------
# Ijoomeradv Extension : EASYBLOG_1.5 (ccompatible with easybBlog 3.8.14427)
# ------------------------------------------------------------------------
# author Tailored Solutions - ijoomer.com
# copyright Copyright (C) 2010 Tailored Solutions. All rights reserved.
# @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
# Websites: http://www.ijoomer.com
# Technical Support: Forum - http://www.ijoomer.com/Forum/
----------------------------------------------------------------------------------*/

defined('_JEXEC') or die;

class easyblog
{
	public $classname = "easyblog";
	public $sessionWhiteList = array('categories.allCategories', 'categories.singleCategory', 'categories.category', 'categories.categoryBlog');

	function init()
	{
		include_once JPATH_SITE . '/components/com_easyblog/models/blog.php';
		include_once JPATH_SITE . '/components/com_easyblog/models/blogs.php';

		$lang =& JFactory::getLanguage();
		$lang->load('com_easyblog');
		$plugin_path = JPATH_COMPONENT_SITE . '/extensions';
		$lang->load('easyblog', $plugin_path '/easyblog', $lang->getTag(), true);
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

