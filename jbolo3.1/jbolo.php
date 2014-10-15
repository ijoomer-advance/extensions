<?php
/**
 * @package     IJoomer.Extensions
 * @subpackage  jbolo3.1
 *
 * @copyright   Copyright (C) 2010 - 2014 Tailored Solutions PVT. Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * class for jbolo
 *
 * @package     IJoomer.Extensions
 * @subpackage  jbolo3.1
 * @since       1.0
 */
class Jbolo
{
	public $classname = "jbolo";

	public $sessionWhiteList = array('ichatmain.polling',
		'ichatmain.pushChatToNode',
		'ichatmain.initiateNode',
		'ichatmain.chathistory',
		'ichatmain.upload_file');

	/**
	 * function for initialization
	 *
	 * @return  void
	 */
	public function init()
	{
		$lang  = JFactory::getLanguage();
		$lang->load('com_jbolo');
		$lang->load('jbolo', JPATH_COMPONENT_SITE . '/extensions/jbolo', $lang->getTag(), true);
	}

	/**
	 * function for get configuration
	 *
	 * @return  array jsonarray
	 */
	public function getconfig()
	{
		$jsonarray                 = array();
		$params                    = JComponentHelper::getParams('com_jbolo');
		$jsonarray['chathistory']  = $params->get('chathistory');
		$jsonarray['groupchat']    = $params->get('groupchat');
		$jsonarray['maxChatUsers'] = $params->get('maxChatUsers');
		$jsonarray['sendfile']     = $params->get('sendfile');
		$jsonarray['maxSizeLimit'] = $params->get('maxSizeLimit');

		return $jsonarray;
	}

	/**
	 * function for write configuration
	 *
	 * @param   array  &$d  d
	 *
	 * @return  void
	 */
	public function write_configuration(&$d)
	{
		$db    = JFactory::getDbo();
		$query = 'SELECT *
				  FROM #__ijoomeradv_jbolo_config';
		$db->setQuery($query);
		$my_config_array = $db->loadObjectList();

		foreach ($my_config_array as $ke => $val)
		{
			if (isset($d[$val->name]))
			{
				$sql = "UPDATE #__ijoomeradv_jbolo_config
						SET value='{$d[$val->name]}'
						WHERE name='{$val->name}'";
				$db->setQuery($sql);
				$db->query();
			}
		}
	}

	/**
	 * function for prepareHTML
	 *
	 * @param   array  &$config  Configuration array
	 *
	 * @return  void
	 */
	public function prepareHTML(&$config)
	{
		// Jbolo related html tags
	}
}

/**
 * class for jbolo_menu
 *
 * @package     IJoomer.Extensions
 * @subpackage  jbolo3.1
 * @since       1.0
 */
class Jbolo_Menu
{
	/**
	 * function for getRequiredInput
	 *
	 * @param   string  $extension    extension
	 * @param   string  $extTask      extension task
	 * @param   [type]  $menuoptions  menuoptions
	 *
	 * @return  void
	 */
	public function getRequiredInput($extension, $extTask, $menuoptions)
	{
		$menuoptions = json_decode($menuoptions, true);
		$db          = JFactory::getDbo();

		switch ($extTask)
		{
		}
	}

	/**
	 * function for set Required Input
	 *
	 * @param   string  $extension    extension name
	 * @param   string  $extView      extension view
	 * @param   string  $extTask      extension task
	 * @param   [type]  $remoteTask   remote task
	 * @param   [type]  $menuoptions  menu option
	 * @param   mixed   $data         data
	 *
	 * @return void
	 */
	public function setRequiredInput($extension, $extView, $extTask, $remoteTask, $menuoptions, $data)
	{
		$db      = JFactory::getDBO();
		$options = null;

		switch ($extTask)
		{
		}

		if ($options)
		{
			$sql = "UPDATE #__ijoomeradv_menu
					SET menuoptions = '" . $options . "'
					WHERE views = '" . $extension . "." . $extView . "." . $extTask . "." . $remoteTask . "'
					AND id='" . $data['id'] . "'";

			$db->setQuery($sql);
			$db->query();
		}
	}
}
