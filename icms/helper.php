<?php
/**
 * @package     IJoomer.Extensions
 * @subpackage  icms
 *
 * @copyright   Copyright (C) 2010 - 2014 Tailored Solutions PVT. Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

class icms_helper
{

	private $db_helper;

	function __construct()
	{
		$this->db_helper  = JFactory::getDBO();
	}

	function getCategoryList()
	{
		require_once JPATH_ADMINISTRATOR . '/components/com_categories/models/categories.php';
		$class = new CategoriesModelCategories;
		$query = $class->getListQuery();

		$this->db_helper->setQuery($query);
		$result = $this->db_helper->loadObjectList();

		return $result;
	}

	function getParseData($results)
	{
		$safeHtmlFilter = JFilterInput::getInstance(null, null, 1, 1);
		$resultData     = array();
		switch ($results['view'])
		{
			case 'article':
				$results['id']                = $safeHtmlFilter->clean($results['id'], 'int');
				$resultData['itemview']       = 'IcmsSingleArticle';
				$resultData['itemdata']['id'] = $results['id'];
				break;

			case 'featured':
				$resultData['itemview'] = 'IcmsFeaturedArticles';
				break;

			case 'category':
				$resultData['itemview']       = ($results['layout'] == 'blog') ? 'IcmsCategoryBlog' : 'IcmsAllCategory';
				$resultData['itemdata']['id'] = $results['id'];
				break;
		}

		if (!empty($resultData))
		{
			$resultData['type'] = 'icms';
		}

		return $resultData;
	}
}