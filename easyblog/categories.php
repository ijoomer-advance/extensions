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

class categories
{

	private $db;

	function __construct()
	{
		$this->db =& JFactory::getDBO();
	}
}
