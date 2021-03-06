<?php
/**
 * @package     IJoomer.Extensions
 * @subpackage  jomsocial2.6
 *
 * @copyright   Copyright (C) 2010 - 2015 Tailored Solutions PVT. Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * class for group
 *
 * @package     IJoomer.Extensions
 * @subpackage  jomsocial2.6
 * @since       1.0
 */
class Group
{
	private $jomHelper;

	private $date_now;

	private $IJUserID;

	private $mainframe;

	private $db;

	private $my;

	private $config;

	private $jsonarray = array();

	/**
	 * constructor
	 */
	public function __construct()
	{
		$this->jomHelper = new jomHelper;
		$this->date_now  = JFactory::getDate();
		$this->mainframe = JFactory::getApplication();

		// Set database object
		$this->db        = JFactory::getDBO();

		// Get login user id
		$this->IJUserID  = $this->mainframe->getUserState('com_ijoomeradv.IJUserID', 0);

		// Set the login user object
		$this->my        = CFactory::getUser($this->IJUserID);
		$this->config    = CFactory::getConfig();
		$notification    = $this->jomHelper->getNotificationCount();

		if (isset($notification['notification']))
		{
			$this->jsonarray['notification'] = $notification['notification'];
		}
	}

	/**
	 * used to fetch all categories
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"categories"
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function categories()
	{
		$groupModel  = CFactory::getModel('groups');
		$categories = $groupModel->getCategories(0);

		if (count($categories) > 0)
		{
			$this->jsonarray['code'] = 200;
		}
		else
		{
			IJReq::setResponse(204);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		foreach ($categories as $key => $value)
		{
			$query = "SELECT count(*)
					FROM #__community_groups_category
					WHERE parent={$value->id}";
			$this->db->setQuery($query);
			$count = $this->db->loadResult();

			$this->jsonarray['categories'][$key]['id']          = $value->id;
			$this->jsonarray['categories'][$key]['name']        = $value->name;
			$this->jsonarray['categories'][$key]['description'] = $value->description;
			$this->jsonarray['categories'][$key]['parent']      = $value->parent;
			$this->jsonarray['categories'][$key]['categories']  = $count;
			$this->jsonarray['categories'][$key]['groups']      = $value->count;
			$subcat                                             = $this->subCategories($value->id);
			$this->jsonarray['categories'][$key]['subCategory'] = $subcat;
		}

		$this->jsonarray['config'][0]['isGroupEnable']        = $this->config->get('enablegroups');
		$this->jsonarray['config'][0]['isGroupCreate']        = $this->config->get('creategroups');
		$this->jsonarray['config'][0]['isCreateAnnouncement'] = $this->config->get('createannouncement');
		$this->jsonarray['config'][0]['isCreateDiscussion']   = $this->config->get('creatediscussion');
		$this->jsonarray['config'][0]['isGroupPhotos']        = $this->config->get('groupphotos');
		$this->jsonarray['config'][0]['isGroupVideos']        = $this->config->get('groupvideos');
		$this->jsonarray['config'][0]['isGroupEvent']         = $this->config->get('group_events');

		return $this->jsonarray;
	}

	/**
	 * function for subCategories
	 *
	 * @param   integer  $pid  pid
	 *
	 * @return  array  jsonarray
	 */
	private function subCategories($pid)
	{
		$groupModel  = CFactory::getModel('groups');
		$categories = $groupModel->getCategories($pid);

		foreach ($categories as $key => $value)
		{
			$query = "SELECT count(*)
					FROM #__community_groups_category
					WHERE parent={$value->id}";
			$this->db->setQuery($query);
			$count = $this->db->loadResult();

			$jsonarray[$key]['id']          = $value->id;
			$jsonarray[$key]['name']        = $value->name;
			$jsonarray[$key]['description'] = $value->description;
			$jsonarray[$key]['parent']      = $value->parent;
			$jsonarray[$key]['categories']  = $count;
			$jsonarray[$key]['groups']      = $value->count;

			if ($count > 0)
			{
				$subcat                         = $this->sub_category($value->id);
				$jsonarray[$key]['subCategory'] = $subcat;
			}
		}

		return $jsonarray;
	}

	/**
	 * used to fetch all categories
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"groups",
	 *        "taskData":{
	 *            "categoryID":"categoryID", // optional: if searching
	 *            "sort":"sort",    // optional: Default is 'latest'
	 *            "query":"query", // optional: if want to search group
	 *            "type":"type", // all, my, pending, search
	 *            "pageNO":"pageNO"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function groups()
	{
		$categoryID = IJReq::getTaskData('categoryID', null, 'int');
		$sort       = IJReq::getTaskData('sort', 'latest');
		$query      = IJReq::getTaskData('query', null);
		$pageNO     = IJReq::getTaskData('pageNO', 0, 'int');
		$type       = IJReq::getTaskData('type');
		$limit      = PAGE_GROUP_LIMIT;

		if ($pageNO == 0 || $pageNO == '' || $pageNO == 1)
		{
			$startFrom = 0;
		}
		else
		{
			$startFrom = ($limit * ($pageNO - 1));
		}

		$groupModel  = CFactory::getModel('groups');

		switch ($type)
		{
			case 'all':
			case 'search':
				$skipDefaultAvatar = false;
				$totalGroups       = count($groupModel->getAllGroups($catID, $sort, $query, 999999, $skipDefaultAvatar = false));

				$groupModel->setState('limit', $limit);
				$groupModel->setState('limitstart', $startFrom);
				$groups = $groupModel->getAllGroups($categoryID, $sort, $query, $limit, $startFrom, $skipDefaultAvatar);
				break;

			case 'my':
				if (!$this->IJUserID)
				{
					IJReq::setResponse(704);
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				$totalGroups = count($groupModel->getGroups($this->IJUserID, $sort, $useLimit = false));

				$groupModel->setState('limit', $limit);
				$groupModel->setState('limitstart', $startFrom);
				$useLimit = true;
				$groups   = $groupModel->getGroups($this->IJUserID, $sort, $useLimit);
				break;

			case 'pending':
				if (!$this->IJUserID)
				{
					IJReq::setResponse(704);
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				$groupModel->setState('limit', $limit);
				$groupModel->setState('limitstart', $startFrom);
				$group = $groupModel->getGroupInvites($this->IJUserID, $sort);

				foreach ($group as $grp)
				{
					$groups[] = $groupModel->getGroup($grp->groupid);
				}

				$totalGroups = count($groups);
				break;

			default:
				IJReq::setResponse(400);
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
		}

		if (count($groups) > 0)
		{
			$this->jsonarray['code']      = 200;
			$this->jsonarray['pageLimit'] = $limit;
			$this->jsonarray['total']     = $totalGroups;
		}
		else
		{
			IJReq::setResponse(204);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		foreach ($groups as $row)
		{
			$group = JTable::getInstance('Group', 'CTable');
			$group->load($row->id);

			// Ensure that stats are up-to-date
			$group->updateStats();
			$grouplist[] = $group;
		}

		foreach ($grouplist as $key => $value)
		{
			$this->jsonarray['groups'][$key]['id']          = $value->id;
			$this->jsonarray['groups'][$key]['title']       = $value->name;
			$this->jsonarray['groups'][$key]['description'] = strip_tags($value->description);

			if ($this->config->get('groups_avatar_storage') == 'file')
			{
				$p_url = JURI::base();
			}
			else
			{
				$s3BucketPath = $this->config->get('storages3bucket');
				if (!empty($s3BucketPath))
					$p_url = 'http://' . $s3BucketPath . '.s3.amazonaws.com/';
				else
					$p_url = JURI::base();
			}

			$this->jsonarray['groups'][$key]['avatar']      = ($value->avatar == "") ? JURI::base() . 'components/com_community/assets/group.png' : $p_url . $value->avatar;
			$this->jsonarray['groups'][$key]['members']     = intval($value->membercount);
			$this->jsonarray['groups'][$key]['walls']       = intval($value->wallcount);
			$this->jsonarray['groups'][$key]['discussions'] = intval($value->discusscount);
		}

		return $this->jsonarray;
	}

	/**
	 * used to fetch all categories
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"addGroup",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID" // optional: edit fields/group
	 *            "fields":"fields" // optional: if 0: add/edit group, 1: field list.
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function addGroup()
	{
		if (!$this->my->id)
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$uniqueID = IJReq::getTaskData('uniqueID', null, 'int');
		$fields   = IJReq::getTaskData('fields', 0, 'bool');

		require_once JPATH_SITE . "/components/com_community/controllers/groups.php";
		$groupController = new CommunityGroupsController;
		$groupModel      = CFactory::getModel('Groups');

		if ($fields)
		{
			$this->jsonarray = $this->addGroupFields($uniqueID);

			if (!$this->jsonarray)
			{
				return false;
			}

			return $this->jsonarray;
		}

		if (!$uniqueID)
		{
			if (!$this->config->get('enablegroups'))
			{
				// Check if Group is enable from jomsocial backend
				IJReq::setResponse(706, JText::_('COM_COMMUNITY_GROUPS_DISABLE'));
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}

			if ($this->config->get('creategroups') && (COwnerHelper::isCommunityAdmin($this->IJUserID) || (COwnerHelper::isRegisteredUser($this->IJUserID) && $this->my->canCreateGroups())))
			{
				// Check if group creatio is allwed from jomsocial backend, and if loged in user is community admin, user is loged in? and a user has permission to create groups.
				CFactory::load('libraries', 'limits');

				if (CLimitsLibrary::exceedDaily('groups'))
				{
					IJReq::setResponse(416, JText::_('COM_COMMUNITY_GROUPS_LIMIT_REACHED'));
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				CFactory::load('helpers', 'limits');

				if (CLimitsHelper::exceededGroupCreation($this->my->id))
				{
					IJReq::setResponse(416, JText::_('COM_COMMUNITY_GROUPS_LIMIT'));
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				$data             = new stdClass;
				$data->categories = $groupModel->getCategories();

				CFactory::load('libraries', 'apps');
				$appsLib      = CAppPlugins::getInstance();
				$saveSuccess = $appsLib->triggerEvent('onFormSave', array('jsform-groups-forms'));

				if (empty($saveSuccess) || !in_array(false, $saveSuccess))
				{
					$gid = $this->save();

					if ($gid !== false)
					{
						$group  = JTable::getInstance('Group', 'CTable');
						$group->load($gid);

						// Trigger for onGroupCreate
						$groupController->triggerGroupEvents('onGroupCreate', $group);

						$this->jsonarray['code'] = 200;

						return $this->jsonarray;
					}
					else
					{
						return false;
					}
				}
			}
			else
			{
				IJReq::setResponse(706, JText::_('COM_COMMUNITY_GROUPS_DISABLE_CREATE_MESSAGE'));
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}
		}
		else
		{
			// Edit group
			$group  = JTable::getInstance('Group', 'CTable');
			$group->load($uniqueID);
			CFactory::load('helpers', 'owner');

			if (!$group->isAdmin($this->my->id) && !COwnerHelper::isCommunityAdmin($this->IJUserID))
			{
				IJReq::setResponse(706);
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}

			$gid = $this->save();

			if (!$gid)
			{
				return false;
			}
			else
			{
				$this->jsonarray['code'] = 200;

				return $this->jsonarray;
			}
		}
	}

	/**
	 * function for save data
	 *
	 * @return boolean/integer  true on success or false on failure and id
	 */
	private function save()
	{
		// Get my current data.
		$uniqueID  = IJReq::getTaskData('uniqueID', null, 'int');
		$validated = true;
		$message   = '';

		$group       = JTable::getInstance('Group', 'CTable');
		$groupModel = CFactory::getModel('Groups');

		$name        = $data['name'] = IJReq::getTaskData('name', '');
		$description = $data['description'] = IJReq::getTaskData('description', '');
		$inputFilter = CFactory::getInputFilter($this->config->get('allowhtml'));
		$description = $inputFilter->clean($description);

		$categoryId        = $data['categoryid'] = IJReq::getTaskData('categoryid', '');
		$approvals         = $data['approvals'] = IJReq::getTaskData('approvals', 0, 'bool');
		$grouprecentphotos = $data['grouprecentphotos'] = IJReq::getTaskData('grouprecentphotos', 6);
		$grouprecentvideos = $data['grouprecentvideos'] = IJReq::getTaskData('grouprecentvideos', 6);
		$grouprecentevents = $data['grouprecentevents'] = IJReq::getTaskData('grouprecentevents', 6);
		$website           = IJReq::getTaskData('website', '');

		// @rule: Test for emptyness
		if (!$uniqueID)
		{
			if (empty($name))
			{
				$validated = false;
				$message .= JText::_('COM_COMMUNITY_GROUPS_EMPTY_NAME_ERROR') . "\n";
			}

			// @rule: Test if group exists
			if ($groupModel->groupExist($name))
			{
				$validated = false;
				$message .= JText::_('COM_COMMUNITY_GROUPS_NAME_TAKEN_ERROR') . "\n";
			}

			// @rule: Test for emptyness
			if (empty($description))
			{
				$validated = false;
				$message .= JText::_('COM_COMMUNITY_GROUPS_DESCRIPTION_EMPTY_ERROR') . "\n";
			}

			if (empty($categoryId))
			{
				$validated = false;
				$message .= JText::_('COM_COMMUNITY_GROUP_CATEGORY_NOT_SELECTED') . "\n";
			}

			if ($grouprecentphotos < 1 && $this->config->get('enablephotos') && $this->config->get('groupphotos'))
			{
				$validated = false;
				$message .= JText::_('COM_COMMUNITY_GROUP_RECENT_ALBUM_SETTING_ERROR') . "\n";
			}

			if ($grouprecentvideos < 1 && $this->config->get('enablevideos') && $this->config->get('groupvideos'))
			{
				$validated = false;
				$message .= JText::_('COM_COMMUNITY_GROUP_RECENT_VIDEOS_SETTING_ERROR') . "\n";
			}

			if ($grouprecentevents < 1 && $this->config->get('enableevents') && $this->config->get('group_events'))
			{
				$validated = false;
				$message .= JText::_('COM_COMMUNITY_GROUP_RECENT_EVENTS_SETTING_ERROR') . "\n";
			}
		}
		else
		{
			$group->load($uniqueID);
			$group->bind($data);
			$removeActivity = IJReq::getTaskData('removeactivities', 0, 'bool');

			if ($removeActivity == 1)
			{
				$activityModel = CFactory::getModel('activities');
				$activityModel->removeActivity('groups', $group->id);
			}

			// Validate all fields
			if (empty($group->name))
			{
				$validated = false;
				$message .= JText::_('COM_COMMUNITY_GROUPS_EMPTY_NAME_ERROR') . "\n";
			}

			if ($groupModel->groupExist($group->name, $group->id))
			{
				$validated = false;
				$message .= JText::_('COM_COMMUNITY_GROUPS_NAME_TAKEN_ERROR') . "\n";
			}

			if (empty($group->description))
			{
				$validated = false;
				$message .= JText::_('COM_COMMUNITY_GROUPS_DESCRIPTION_EMPTY_ERROR') . "\n";
			}

			if (empty($group->categoryid))
			{
				$validated = false;
				$message .= JText::_('COM_COMMUNITY_GROUP_CATEGORY_NOT_SELECTED') . "\n";
			}
		}

		if ($validated)
		{
			// @rule: Retrieve params and store it back as raw string
			$params        = $this->_bindParams();
			$group->params = $params->toString();

			if ($uniqueID)
			{
				if ($groupModel->isAdmin($this->my->id, $group->id) || COwnerHelper::isCommunityAdmin($this->IJUserID))
				{
					$group->updateStats();
					$group->store();

					$act          = new stdClass;
					$act->cmd     = 'group.updated';
					$act->actor   = $this->my->id;
					$act->target  = 0;
					$act->title   = JText::sprintf('COM_COMMUNITY_GROUPS_GROUP_UPDATED', '{group_url}', $group->name);
					$act->content = '';
					$act->app     = 'groups';
					$act->cid     = $group->id;

					$params = new CParameter('');
					$params->set('group_url', 'index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $groupId);

					// Add activity logging
					CFactory::load('libraries', 'activities');
					CActivityStream::add($act, $params->toString());

					// Add user points
					CFactory::load('libraries', 'userpoints');
					CUserPoints::assignPoint('group.updated');

					// Update photos privacy
					$photoPermission = $group->approvals ? 35 : 0;
					$photoModel      = CFactory::getModel('photos');
					$photoModel->updatePermissionByGroup($group->id, $photoPermission);

					return $validated;
				}
			}

			CFactory::load('helpers', 'owner');

			// Bind the post with the table first
			$group->name        = $name;
			$group->description = $description;
			$group->categoryid  = $categoryId;
			$group->website     = $website;
			$group->ownerid     = $this->my->id;
			$group->created     = gmdate('Y-m-d H:i:s');
			$group->approvals   = $approvals;

			// @rule: check if moderation is turned on.
			$group->published = ($this->config->get('moderategroupcreation')) ? 0 : 1;

			// We here save the group 1st. else the group->id will be missing and causing the member connection and activities broken.
			$group->store();

			// Since this is storing groups, we also need to store the creator / admin
			// Into the groups members table
			$member            = JTable::getInstance('GroupMembers', 'CTable');
			$member->groupid  = $group->id;
			$member->memberid = $group->ownerid;

			// Creator should always be 1 as approved as they are the creator.
			$member->approved = 1;

			// @todo: Setup required permissions in the future
			$member->permissions = 1;
			$member->store();

			// @rule: Only add into activity once a group is created and it is published.
			if ($group->published)
			{
				CFactory::load('libraries', 'activities');
				$act          = new stdClass;
				$act->cmd     = 'group.create';
				$act->actor   = $this->my->id;
				$act->target  = 0;
				$act->title   = JText::sprintf('COM_COMMUNITY_GROUPS_NEW_GROUP_CATEGORY', '{group_url}', $group->name, '{category_url}', $group->getCategoryName());
				$act->content = ($group->approvals == 0) ? $group->description : '';
				$act->app     = 'groups';
				$act->cid     = $group->id;
				$act->groupid = $group->id;

				// Allow comments
				$act->comment_type = 'groups.create';
				$act->comment_id   = CActivities::COMMENT_SELF;

				// Store the group now.
				$group->updateStats();
				$group->store();

				$params = new CParameter('');
				$params->set('action', 'group.create');
				$params->set('group_url', 'index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $group->id);
				$params->set('category_url', 'index.php?option=com_community&view=groups&task=display&categoryid=' . $group->categoryid);

				// Add activity logging
				CActivityStream::add($act, $params->toString());
			}

			// Add user points
			CFactory::load('libraries', 'userpoints');
			CUserPoints::assignPoint('group.create');

			$validated = $group->id;
		}
		else
		{
			IJReq::setResponse(400, $message);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		return $validated;
	}

	/**
	 * function for bind parameters
	 *
	 * @return boolean  true on success or false on failure and parameters
	 */
	private function _bindParams()
	{
		$params  = new CParameter('');
		$groupId = IJReq::getTaskData('uniqueID', null, 'int');

		$discussordering = IJReq::getTaskData('discussordering', 0, 'bool');
		$params->set('discussordering', $discussordering);

		if (IJReq::getTaskData('photopermission-admin', 0, 'bool'))
		{
			$params->set('photopermission', GROUP_PHOTO_PERMISSION_ADMINS);
		}

		// Set the group photo permission
		if (IJReq::getTaskData('photopermission-admin', 0, 'bool'))
		{
			$params->set('photopermission', GROUP_PHOTO_PERMISSION_ADMINS);

			if (IJReq::getTaskData('photopermission-member', 0, 'bool'))
			{
				$params->set('photopermission', GROUP_PHOTO_PERMISSION_ALL);
			}
		}
		else
		{
			$params->set('photopermission', GROUP_PHOTO_PERMISSION_DISABLE);
		}

		// Set the group video permission
		if (IJReq::getTaskData('videopermission-admin', 0, 'bool'))
		{
			$params->set('videopermission', GROUP_VIDEO_PERMISSION_ADMINS);

			if (IJReq::getTaskData('videopermission-member', 0, 'bool'))
			{
				$params->set('videopermission', GROUP_VIDEO_PERMISSION_ALL);
			}
		}
		else
		{
			$params->set('videopermission', GROUP_VIDEO_PERMISSION_DISABLE);
		}

		// Set the group event permission
		if (IJReq::getTaskData('eventpermission-admin', 0, 'bool'))
		{
			$params->set('eventpermission', GROUP_EVENT_PERMISSION_ADMINS);

			if (IJReq::getTaskData('eventpermission-member', 0, 'bool'))
			{
				$params->set('eventpermission', GROUP_EVENT_PERMISSION_ALL);
			}
		}
		else
		{
			$params->set('eventpermission', GROUP_EVENT_PERMISSION_DISABLE);
		}

		$grouprecentphotos = IJReq::getTaskData('grouprecentphotos', GROUP_PHOTO_RECENT_LIMIT, 'int');

		if ($grouprecentphotos < 1 && $this->config->get('enablephotos'))
		{
			IJReq::setResponse(500, JText::_('COM_COMMUNITY_GROUP_RECENT_ALBUM_SETTING_ERROR'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$params->set('grouprecentphotos', $grouprecentphotos);

		$grouprecentvideos = IJReq::getTaskData('grouprecentvideos', GROUP_VIDEO_RECENT_LIMIT, 'int');

		if ($grouprecentvideos < 1 && $this->config->get('enablevideos'))
		{
			IJReq::setResponse(500, JText::_('COM_COMMUNITY_GROUP_RECENT_VIDEOS_SETTING_ERROR'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$params->set('grouprecentvideos', $grouprecentvideos);

		$grouprecentevent = IJReq::getTaskData('grouprecentevents', GROUP_EVENT_RECENT_LIMIT, 'int');

		if ($grouprecentevent < 1)
		{
			IJReq::setResponse(500, JText::_('COM_COMMUNITY_GROUP_RECENT_EVENTS_SETTING_ERROR'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$params->set('grouprecentevents', $grouprecentevent);

		$newmembernotification = IJReq::getTaskData('newmembernotification', 0, 'bool');
		$params->set('newmembernotification', $newmembernotification);

		$joinrequestnotification = IJReq::getTaskData('joinrequestnotification', 0, 'bool');
		$params->set('joinrequestnotification', $joinrequestnotification);

		$wallnotification = IJReq::getTaskData('wallnotification', 0, 'bool');
		$params->set('wallnotification', $wallnotification);

		$removeactivities = IJReq::getTaskData('removeactivities', 0, 'bool');
		$params->set('removeactivities', $removeactivities);

		$groupdiscussionfilesharing = IJReq::getTaskData('groupdiscussionfilesharing', 0, 'bool');
		$params->set('groupdiscussionfilesharing', $groupdiscussionfilesharing);

		$groupannouncementfilesharing = IJReq::getTaskData('groupannouncementfilesharing', 0, 'bool');
		$params->set('groupannouncementfilesharing', $groupannouncementfilesharing);

		return $params;
	}

	/**
	 * function for add Group Fields
	 *
	 * @param   integer  $uniqueID  uniqueid
	 *
	 * @return array  jsonarray
	 */
	private function addGroupFields($uniqueID)
	{
		$fiedList = array("name"                         => array("text", 1, JText::_('COM_COMMUNITY_GROUPS_TITLE')),
							"description"                  => array("textarea", 1, JText::_('COM_COMMUNITY_GROUPS_DESCRIPTION')),
							"categoryid"                   => array("select", 1, JText::_('COM_COMMUNITY_GROUP_CATEGORY')),
							"approvals"                    => array("checkbox", 0, JText::_('COM_COMMUNITY_GROUPS_PRIVATE_LABEL')),

							"grouprecentphotos"            => array("text", 0, JText::_('COM_COMMUNITY_GROUPS_RECENT_PHOTO')),
							"photopermission-admin"        => array("checkbox", 0, JText::_('COM_COMMUNITY_GROUPS_PHOTO_UPLOAD_ALOW_ADMIN')),
							"photopermission-member"       => array("checkbox", 0, JText::_('COM_COMMUNITY_GROUPS_PHOTO_UPLOAD_ALLOW_MEMBER')),

							"grouprecentvideos"            => array("text", 0, JText::_('COM_COMMUNITY_GROUPS_RECENT_VIDEO')),
							"videopermission-admin"        => array("checkbox", 0, JText::_('COM_COMMUNITY_GROUPS_VIDEO_UPLOAD_ALLOW_ADMIN')),
							"videopermission-member"       => array("checkbox", 0, JText::_('COM_COMMUNITY_GROUPS_VIDEO_UPLOAD_ALLOW_MEMBER')),

							"grouprecentevents"            => array("text", 0, JText::_('COM_COMMUNITY_GROUPS_EVENT')),
							"eventpermission-admin"        => array("checkbox", 0, JText::_('COM_COMMUNITY_GROUP_EVENTS_ADMIN_CREATION')),
							"eventpermission-member"       => array("checkbox", 0, JText::_('COM_COMMUNITY_GROUP_EVENTS_MEMBERS_CREATION')),

							"groupdiscussionfilesharing"   => array("checkbox", 0, JText::_('COM_COMMUNITY_FILES_ENABLE_SHARING')),
							"discussordering"              => array("checkbox", 0, JText::_('COM_COMMUNITY_GROUPS_DISCUSS_ORDER_CREATION_DATE')),

							"groupannouncementfilesharing" => array("checkbox", 0, JText::_('COM_COMMUNITY_FILES_ENABLE_SHARING')),

							"newmembernotification"        => array("checkbox", 0, JText::_('COM_COMMUNITY_GROUPS_NEW_MEMBER_NOTIFICATION')),
							"joinrequestnotification"      => array("checkbox", 0, JText::_('COM_COMMUNITY_GROUPS_JOIN_REQUEST_NOTIFICATION')),
							"wallnotification"             => array("checkbox", 0, JText::_('COM_COMMUNITY_GROUPS_WALL_NOTIFICATION'))
		);

		$groupModel  = CFactory::getModel('groups');
		$categories = $groupModel->getAllCategories();
		$categories = $this->getFieldCategories($categories, 0);

		$group = false;

		if ($uniqueID != '' || $uniqueID != 0)
		{
			$group  = JTable::getInstance('Group', 'CTable');
			$group->load($uniqueID);
			$params = $group->getParams();
		}

		$i                       = 0;
		$this->jsonarray['code'] = 200;

		foreach ($fiedList as $key => $field)
		{
			$this->jsonarray['fields'][$i]['name']     = $key;
			$this->jsonarray['fields'][$i]['type']     = $field[0];
			$this->jsonarray['fields'][$i]['required'] = $field[1];
			$this->jsonarray['fields'][$i]['caption']  = $field[2];

			if ($group)
			{
				// If edit event.. value should be passed to adit.
				$access = array('grouprecentphotos', 'photopermission-admin', 'photopermission-member',
					'grouprecentvideos', 'videopermission-admin', 'videopermission-member',
					'grouprecentevents', 'eventpermission-admin', 'eventpermission-member',
					'groupdiscussionfilesharing', 'discussordering', 'groupannouncementfilesharing', 'newmembernotification', 'joinrequestnotification', 'wallnotification');

				if (in_array($key, $access))
				{
					if ($key == 'photopermission-admin')
					{
						$this->jsonarray['fields'][$i]['value'] = intval(($params->get('photopermission') > 0));
					}
					elseif ($key == 'photopermission-member')
					{
						$this->jsonarray['fields'][$i]['value'] = intval(($params->get('photopermission') > 1));
					}
					elseif ($key == 'videopermission-admin')
					{
						$this->jsonarray['fields'][$i]['value'] = intval(($params->get('videopermission') > 0));
					}
					elseif ($key == 'videopermission-member')
					{
						$this->jsonarray['fields'][$i]['value'] = intval(($params->get('videopermission') > 1));
					}
					elseif ($key == 'eventpermission-admin')
					{
						$this->jsonarray['fields'][$i]['value'] = intval(($params->get('eventpermission') > 0));
					}
					elseif ($key == 'eventpermission-member')
					{
						$this->jsonarray['fields'][$i]['value'] = intval(($params->get('eventpermission') > 1));
					}
					else
					{
						$this->jsonarray['fields'][$i]['value'] = trim($params->get($key));
					}
				}
				else
				{
					$this->jsonarray['fields'][$i]['value'] = trim($group->{$key});
				}
			}
			else
			{
				$this->jsonarray['fields'][$i]['value'] = '';
			}

			if ($key == 'categoryid')
			{
				foreach ($categories as $catk => $catv)
				{
					$this->jsonarray['fields'][$i]['options'][$catk]['name']  = $catv->newname;
					$this->jsonarray['fields'][$i]['options'][$catk]['value'] = $catv->id;
				}
			}

			$i++;
		}

		return $this->jsonarray;
	}

	/**
	 * function for getFieldCategories
	 *
	 * @param   array   $categories  categories
	 * @param   [type]  $parent      parent
	 *
	 * @return  array  categories
	 */
	private function getFieldCategories($categories, $parent)
	{
		if ($parent > 0)
		{
			foreach ($categories as $key => $value)
			{
				if ($value->id == $parent)
				{
					if ($value->parent > 0)
					{
						$value->newname = $this->getFieldCategories($categories, $value->parent) . " › " . $value->name;

						return $value->newname;
					}
					else
					{
						return $value->name;
					}
				}
			}
		}
		else
		{
			foreach ($categories as $key => $value)
			{
				if ($value->parent > 0)
				{
					$value->newname = $this->getFieldCategories($categories, $value->parent) . " › " . $value->name;
				}
				else
				{
					$value->newname = $value->name;
				}
			}
		}

		return $categories;
	}

	/**
	 * used to fetch group details
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"detail",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID"
	 *        }
	 *    }
	 * @return  array jsonarray
	 */
	public function detail()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', null, 'int');

		if (!$this->IJUserID)
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$group  = JTable::getInstance('Group', 'CTable');
		$group->load($uniqueID);
		$params = $group->getParams();

		if (!$group->id)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		CFactory::load('helpers', 'owner');
		CFactory::load('helpers', 'group');

		// Get admin of group
		$groupModel  = CFactory::getModel('groups');
		$groupModel->setState('limit', 9999);
		$groupModel->setState('limitstart', 0);
		$admins = $groupModel->getAdmins($uniqueID, null);

		$this->jsonarray['code'] = 200;

		$this->jsonarray ['group'] ['category_id']   = intval($group->categoryid);
		$this->jsonarray ['group'] ['category_name'] = $groupModel->getCategoryName($group->categoryid);
		$usr                                         = $this->jomHelper->getUserDetail($group->ownerid);
		$this->jsonarray ['group'] ['user_id']       = $usr->id;
		$this->jsonarray ['group'] ['user_name']     = $usr->name;
		$this->jsonarray ['group'] ['user_profile']  = $usr->profile;
		$format                                      = "%A, %d %B %Y";
		$this->jsonarray ['group'] ['date']          = CTimeHelper::getFormattedTime($group->createdate, $format);

		// Likes
		$likes                                  = $this->jomHelper->getLikes('groups', $uniqueID, $this->IJUserID);
		$this->jsonarray ['group'] ['likes']    = $likes->likes;
		$this->jsonarray ['group'] ['dislikes'] = $likes->dislikes;
		$this->jsonarray ['group'] ['liked']    = $likes->liked;
		$this->jsonarray ['group'] ['disliked'] = $likes->disliked;

		// File count
		$query = "SELECT count(id)
		 		FROM #__community_files
		 		WHERE groupid = {$uniqueID}";
		$this->db->setQuery($query);
		$total                               = $this->db->loadResult();
		$this->jsonarray ['group'] ['files'] = intval($total);

		if (SHARE_GROUP == 1)
		{
			$this->jsonarray ['group'] ['shareLink'] = JURI::base() . "index.php?option=com_community&view=groups&task=viewgroup&groupid={$uniqueID}";
		}

		$isMember        = $group->isMember($this->my->id) ? 1 : 0;
		$isBanned        = $group->isBanned($this->my->id) ? 1 : 0;
		$waitingApproval = $groupModel->isWaitingAuthorization($this->my->id, $group->id);
		$isMine          = ($this->my->id == $group->ownerid);
		$members         = $groupModel->getAllMember($group->id);
		$isAdmin         = $groupModel->isAdmin($this->my->id, $group->id);

		$isSuperAdmin      = COwnerHelper::isCommunityAdmin($this->my->id) ? 1 : 0;
		$allowCreateEvent  = CGroupHelper::allowCreateEvent($this->my->id, $group->id);
		$allowManagePhotos = CGroupHelper::allowManagePhoto($group->id);
		$allowManageVideos = CGroupHelper::allowManageVideo($group->id);
		$allowManagealbum  = ($allowManagePhotos && $this->config->get('groupphotos') && $this->config->get('enablephotos')) ? 1 : 0;

		$isCommunityAdmin = $isSuperAdmin;
		$showEvents       = (($params->get('eventpermission') == GROUP_EVENT_PERMISSION_ADMINS || $params->get('eventpermission') == GROUP_EVENT_PERMISSION_ALL || $params->get('eventpermission') == '') && $this->config->get('group_events') && $this->config->get('enableevents')) ? 1 : 0;
		$showPhotos       = ($params->get('photopermission') == GROUP_PHOTO_PERMISSION_ADMINS || $params->get('photopermission') == GROUP_PHOTO_PERMISSION_ALL || $params->get('photopermission') == '') ? 1 : 0;
		$showVideos       = ($params->get('videopermission') == GROUP_VIDEO_PERMISSION_ADMINS || $params->get('videopermission') == GROUP_VIDEO_PERMISSION_ALL || $params->get('videopermission') == '') ? 1 : 0;
		$allowWall        = (!$this->config->get('lockgroupwalls') || ($this->config->get('lockgroupwalls') /*&& $group->isMember( $this->my->id ) && !$isBanned*/) || COwnerHelper::isCommunityAdmin());
		$isInvited        = $groupModel->isInvited($this->my->id, $group->id) ? 1 : 0;

		$this->jsonarray['group']['likeAllowed']     = intval(!($isInvited OR $isBanned) AND $isMember);

		// If user is invited to join group.
		$this->jsonarray['group']['isInvitation']    = $isInvited;
		$this->jsonarray['group']['albumpermission'] = $allowManagealbum;
		$this->jsonarray['group']['photopermission'] = $allowManagePhotos ? 1 : 0;
		$this->jsonarray['group']['videopermission'] = $allowManageVideos ? 1 : 0;
		$this->jsonarray['group']['eventpermission'] = $allowCreateEvent ? 1 : 0;
		$this->jsonarray['group']['wallpermission']  = ($allowWall && ($isMember || $isAdmin || $isSuperAdmin)) ? 1 : 0;

		if ($isInvited)
		{
			// Fetch invitor
			$query = "SELECT `creator`
					FROM #__community_groups_invite
					WHERE `groupid`={$uniqueID}
					AND `userid`={$this->IJUserID}";
			$this->db->setQuery($query);
			$invitor = $this->db->loadResult();
			$invitor = CFactory::getUser($invitor);

			// Check how many friends are the member of this group
			$friendsModel  = CFactory::getModel('friends');
			$frids        = $friendsModel->getFriendIds($this->IJUserID);
			$frdcount     = 0;

			foreach ($members as $member)
			{
				if (in_array($member->id, $frids) && $member->id != $this->IJUserID && $member->id != $creator)
				{
					$frdcount++;
				}
			}

			$invitemessage = $invitor->name . " invited you to join this group.";

			if ($frdcount)
			{
				$invitemessage .= " \n" . $frdcount . " of your friends are the members of this group.";
			}

			$this->jsonarray['group']['invitationMessage'] = $invitemessage;
			$this->jsonarray['group']['invitationicon']    = JURI::root() . 'components/com_community/templates/default/images/action/icon-invite-32.png';
		}

		$photoModel  = CFactory::getModel('photos');
		$albums     = $photoModel->getGroupAlbums($uniqueID);
		$totalAlbum = $photoModel->_pagination->total;

		$this->jsonarray['group']['isAdmin']           = intval($isAdmin);
		$this->jsonarray['group']['isCommunityAdmin']  = $isCommunityAdmin;
		$this->jsonarray['group']['isJoin']            = $isMember;
		$this->jsonarray['group']['isBanned']          = $isBanned;
		$this->jsonarray['group']['isMember']          = $isMember;
		$this->jsonarray['group']['isPrivate']         = intval($group->approvals);

		// Loged in user waiting for admin approval.
		$this->jsonarray['group']['isWaitingApproval'] = intval($waitingApproval);

		if ($isMine || $isAdmin || $isSuperAdmin)
		{
			$memberWaiting = 0;

			foreach ($members as $member)
			{
				if ($member->approved == 0)
				{
					$memberWaiting++;
				}
			}

			// Waiting user count.
			$this->jsonarray['group']['memberWaiting'] = $memberWaiting;
		}

		// Options starts from here
		$this->jsonarray['group']['menu']['shareGroup']  = 0;
		$this->jsonarray['group']['menu']['reportGroup'] = 0;

		if (($isMember) || ((!$isMember) && !$waitingApproval) || $isMine || $isAdmin || $isSuperAdmin)
		{
			$this->jsonarray['group']['menu']['shareGroup']  = 1;
			$this->jsonarray['group']['menu']['reportGroup'] = 1;

			if ($this->config->get('creatediscussion') && (($isMember && !$isBanned) && !($waitingApproval) || $isSuperAdmin))
			{
				$this->jsonarray['group']['menu']['createDiscussion'] = 1;
			}
			else
			{
				$this->jsonarray['group']['menu']['createDiscussion'] = 0;
			}

			if ($allowCreateEvent && $this->config->get('group_events') && $this->config->get('enableevents') && ($this->config->get('createevents') || COwnerHelper::isCommunityAdmin()))
			{
				$this->jsonarray['group']['menu']['createEvent'] = 1;
			}
			else
			{
				$this->jsonarray['group']['menu']['createEvent'] = 0;
			}

			if ($allowManagePhotos && $this->config->get('groupphotos') && $this->config->get('enablephotos'))
			{
				$this->jsonarray['group']['menu']['uploadPhoto'] = ($albums) ? 1 : 0;
				$this->jsonarray['group']['menu']['createAlbum'] = 1;
			}
			else
			{
				$this->jsonarray['group']['menu']['uploadPhoto'] = 0;
				$this->jsonarray['group']['menu']['createAlbum'] = 0;
			}

			if ($allowManageVideos && $this->config->get('groupvideos') && $this->config->get('enablevideos'))
			{
				$this->jsonarray['group']['menu']['addVideo'] = 1;
			}
			else
			{
				$this->jsonarray['group']['menu']['addVideo'] = 0;
			}

			if ((!$isMember && !$isBanned) && !($waitingApproval))
			{
				$this->jsonarray['group']['menu']['joinGroup'] = 1;
			}
			else
			{
				$this->jsonarray['group']['menu']['joinGroup'] = 0;
			}

			if (($isAdmin) || ($isMine) || ($isMember && !$isBanned))
			{
				$this->jsonarray['group']['menu']['inviteFriend'] = 1;
			}
			else
			{
				$this->jsonarray['group']['menu']['inviteFriend'] = 0;
			}

			if (($isMember && !$isBanned) && (!$isMine) && !($waitingApproval) && (COwnerHelper::isRegisteredUser()))
			{
				$this->jsonarray['group']['menu']['leaveGroup'] = 1;
			}
			else
			{
				$this->jsonarray['group']['menu']['leaveGroup'] = 0;
			}
		}

		if ($isMine || $isCommunityAdmin || $isAdmin)
		{
			if ($isAdmin || $isCommunityAdmin)
			{
				$this->jsonarray['group']['adminMenu']['edit']       = 1;
				$this->jsonarray['group']['adminMenu']['editAvatar'] = 1;
			}
			else
			{
				$this->jsonarray['group']['adminMenu']['edit']       = 0;
				$this->jsonarray['group']['adminMenu']['editAvatar'] = 0;
			}

			if ($isAdmin || $isCommunityAdmin)
			{
				$this->jsonarray['group']['adminMenu']['sendMail'] = 1;
			}
			else
			{
				$this->jsonarray['group']['adminMenu']['sendMail'] = 0;
			}

			if ($this->config->get('createannouncement') && $isAdmin || $isSuperAdmin)
			{
				$this->jsonarray['group']['adminMenu']['createAnnouncement'] = 1;
			}
			else
			{
				$this->jsonarray['group']['adminMenu']['createAnnouncement'] = 0;
			}

			if ($isCommunityAdmin)
			{
				$this->jsonarray['group']['adminMenu']['unpublishGroup'] = 1;
			}
			else
			{
				$this->jsonarray['group']['adminMenu']['unpublishGroup'] = 0;
			}

			if ($isCommunityAdmin || ($isMine))
			{
				$this->jsonarray['group']['adminMenu']['deleteGroup'] = 1;
			}
			else
			{
				$this->jsonarray['group']['adminMenu']['deleteGroup'] = 0;
			}
		}

		if ($group->approvals == '0' || $isAdmin || $isMine || ($isMember && !$isBanned) || $isCommunityAdmin)
		{
			$this->jsonarray['group']['option']['memberList'] = 1;
		}
		else
		{
			$this->jsonarray['group']['option']['memberList'] = 0;
		}

		if (($group->approvals == '0' && $this->config->get('enablephotos') && $this->config->get('groupphotos') && $showPhotos) || $isMember || $isAdmin || $isCommunityAdmin)
		{
			$this->jsonarray['group']['option']['albumList'] = 1;
		}
		else
		{
			$this->jsonarray['group']['option']['albumList'] = 0;
		}

		if (($group->approvals == '0' && $this->config->get('enablevideos') && $this->config->get('groupvideos') && $showVideos) || $isMember || $isAdmin || $isCommunityAdmin)
		{
			$this->jsonarray['group']['option']['videoList'] = 1;
		}
		else
		{
			$this->jsonarray['group']['option']['videoList'] = 0;
		}

		if (($group->approvals == '0' && $showEvents) || $isMember || $isAdmin || $isCommunityAdmin)
		{
			$this->jsonarray['group']['option']['eventList'] = 1;
		}
		else
		{
			$this->jsonarray['group']['option']['eventList'] = 0;
		}

		if (($group->approvals == '0' && $this->config->get('createannouncement') && $isMember) || $isAdmin || $isCommunityAdmin)
		{
			$this->jsonarray['group']['option']['announcementList'] = 1;
		}
		else
		{
			$this->jsonarray['group']['option']['announcementList'] = 0;
		}

		if (($group->approvals == '0' && $this->config->get('creatediscussion')) || $isMember || $isAdmin || $isCommunityAdmin)
		{
			$this->jsonarray['group']['option']['discussionList'] = 1;
		}
		else
		{
			$this->jsonarray['group']['option']['discussionList'] = 0;
		}

		if (($group->approvals == '0' && $allowWall) || $isMember || $isAdmin || $isCommunityAdmin)
		{
			$this->jsonarray['group']['option']['wallList'] = 1;
		}
		else
		{
			$this->jsonarray['group']['option']['wallList'] = 0;
		}

		return $this->jsonarray;
	}

	/**
	 * used to join group
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"join",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function join()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');

		if (!$uniqueID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($this->my->id == 0)
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// Load necessary tables
		$groupModel = CFactory::getModel('groups');

		if ($groupModel->isMember($this->my->id, $uniqueID))
		{
			IJReq::setResponse(707, JText::_('COM_COMMUNITY_GROUPS_ALREADY_MEMBER'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
		else
		{
			$member = $this->_saveMember($uniqueID);

			if ($member->approved)
			{
				$notification = $this->jomHelper->getNotificationCount();

				if (isset($notification['notification']))
				{
					$this->jsonarray['notification'] = $notification['notification'];
				}

				$this->jsonarray['code'] = 200;

				return $this->jsonarray;
			}
			else
			{
				$this->jsonarray['code'] = 708;

				return $this->jsonarray;
			}
		}
	}

	/**
	 * used to approve member to join group
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"approveMember",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "memberID":"memberID"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function approveMember()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$memberID = IJReq::getTaskData('memberID', 0, 'int');
		$filter   = JFilterInput::getInstance();
		$groupId  = $filter->clean($groupId, 'int');
		$memberId = $filter->clean($memberId, 'int');

		if (!$uniqueID || !$memberID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$gMember = CFactory::getUser($memberID);
		CFactory::load('helpers', 'owner');

		if (!$this->my->authorise('community.approve', 'groups.member.' . $uniqueID))
		{
			IJReq::setResponse(706, JText::_('COM_COMMUNITY_NOT_ALLOWED_TO_ACCESS_SECTION'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
		else
		{
			// Load required tables
			$member  = JTable::getInstance('GroupMembers', 'CTable');
			$group   = JTable::getInstance('Group', 'CTable');

			// Load the group and the members table
			$group->load($uniqueID);
			$member->load($memberID, $uniqueID);

			// Only approve members that is really not approved yet.
			if ($member->approved)
			{
				IJReq::setResponse(707, JText::_('COM_COMMUNITY_MEMBER_ALREADY_APPROVED'));
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}
			else
			{
				$member->approve();

				// Update member user table
				$gMember->updateGroupList(true);

				CFactory::load('libraries', 'groups');
				CGroups::joinApproved($group->id, $memberID);

				// Get user push notification params
				$query = "SELECT `jomsocial_params`,`device_token`,`device_type`
						FROM #__ijoomeradv_users
						WHERE `userid`={$memberID}";
				$this->db->setQuery($query);
				$puser    = $this->db->loadObject();
				$ijparams = new CParameter($puser->jomsocial_params);

				// Change for id based push notification
				$pushOptions                                   = array();
				$pushOptions['detail']['content_data']['id']   = $this->my->id;
				$pushOptions['detail']['content_data']['type'] = 'profile';
				$pushOptions                                   = gzcompress(json_encode($pushOptions));

				$message      = JText::sprintf('COM_COMMUNITY_GROUPS_APPROVE_MEMBER');
				$obj          = new stdClass;
				$obj->id      = null;
				$obj->detail  = $pushOptions;
				$obj->tocount = 1;
				$this->db->insertObject('#__ijoomeradv_push_notification_data', $obj, 'id');

				if ($obj->id)
				{
					$this->jsonarray['pushNotificationData']['id']         = $obj->id;
					$this->jsonarray['pushNotificationData']['to']         = $memberID;
					$this->jsonarray['pushNotificationData']['message']    = $message;
					$this->jsonarray['pushNotificationData']['type']       = 'group';
					$this->jsonarray['pushNotificationData']['configtype'] = 'pushnotif_groups_member_approved';
				}

				// Trigger for onGroupJoinApproved
				CFactory::load('controllers', 'groups');
				$group_controller_obj = new CommunityGroupsController;
				$group_controller_obj->triggerGroupEvents('onGroupJoinApproved', $group, $memberID);
			}
		}

		$this->jsonarray['code'] = 200;

		return $this->jsonarray;
	}

	/**
	 * used to join group
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"leave",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function leave()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');

		if (!$uniqueID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		require_once JPATH_ROOT . '/components/com_community/controllers/groups.php';
		$group_controller_obj = new CommunityGroupsController;

		$groupModel = CFactory::getModel('groups');

		if ($this->my->id == 0)
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$group  = JTable::getInstance('Group', 'CTable');
		$group->load($uniqueID);

		$data           = new stdClass;
		$data->groupid  = $uniqueID;
		$data->memberid = $this->my->id;

		$groupModel->removeMember($data);

		// Add user points
		CFactory::load('libraries', 'userpoints');
		CUserPoints::assignPoint('group.leave');

		// Trigger for onGroupLeave
		$group_controller_obj->triggerGroupEvents('onGroupLeave', $group, $this->my->id);

		// Store the group and update the data
		$group->updateStats();
		$group->store();

		$this->jsonarray['code'] = 200;

		return $this->jsonarray;
	}

	/**
	 * used to report group
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"report",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "discussionID":"discussionID", // only when reporting discussion.
	 *            "message":"message",
	 *            "type":"type" // group, discussion
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function report()
	{
		$uniqueID     = IJReq::getTaskData('uniqueID', 0, 'int');
		$discussionID = IJReq::getTaskData('discussionID', 0, 'int');
		$message      = IJReq::getTaskData('message');
		$type         = IJReq::getTaskData('type', null);

		if (!$uniqueID OR !$type)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		CFactory::load('libraries', 'reporting');
		$report = new CReportingLibrary;

		switch ($type)
		{
			case 'group':

				if (!$this->config->get('enablereporting') || (($this->my->id == 0) && (!$this->config->get('enableguestreporting'))))
				{
					IJReq::setResponse(706);
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				$link = JURI::base() . "index.php?option=com_community&view=groups&task=viewgroup&groupid=" . $uniqueID;

				$report->createReport(JText::_('Bad group'), $link, $message);

				$action                = new stdClass;
				$action->label         = 'Unpublish group';
				$action->method        = 'groups,unpublishGroup';
				$action->parameters    = $uniqueID;
				$action->defaultAction = true;
				break;

			case 'discussion':

				if (!$discussionID)
				{
					IJReq::setResponse(400);
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				$link = JURI::base() . "index.php?option=com_community&view=groups&task=viewdiscussion&groupid=" . $uniqueID . "&topicid=" . $discussionID;

				$report->createReport(JText::_('COM_COMMUNITY_INVALID_DISCUSSION'), $link, $message);

				$action                = new stdClass;
				$action->label         = 'Remove discussion';
				$action->method        = 'groups,removeDiscussion';
				$action->parameters    = $discussionID;
				$action->defaultAction = true;
				break;

			default:
				IJReq::setResponse(400);
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
		}

		$report->addActions(array($action));

		$this->jsonarray['code'] = 200;

		return $this->jsonarray;
	}

	/**
	 * used to delete group
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"delete",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function delete()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');

		CFactory::load('libraries', 'activities');
		CFactory::load('helpers', 'owner');
		CFactory::load('models', 'groups');

		$group  = JTable::getInstance('Group', 'CTable');
		$group->load($uniqueID);

		$groupModel   = CFactory::getModel('groups');
		$membersCount = $groupModel->getMembersCount($uniqueID);
		$isMine       = ($this->my->id == $group->ownerid);

		if (!$this->my->authorise('community.delete', 'groups.' . $uniqueID, $group))
		{
			IJReq::setResponse(706, JText::_('COM_COMMUNITY_GROUPS_NOT_ALLOWED_DELETE'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
		else
		{
			// Nothing gets deleted yet. Just show a messge to the next step
			if (empty($uniqueID))
			{
				IJReq::setResponse(400, JText::_('COM_COMMUNITY_GROUPS_ID_NOITEM'));
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}

			// Delete all group bulletins
			if (!CommunityModelGroups::deleteGroupBulletins($uniqueID))
			{
				IJReq::setResponse(500);
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}

			// Delete all group members
			if (!CommunityModelGroups::deleteGroupMembers($uniqueID))
			{
				IJReq::setResponse(500);
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}

			// Delete all group wall
			if (!CommunityModelGroups::deleteGroupWall($uniqueID))
			{
				IJReq::setResponse(500);
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}

			// Delete all group discussions
			if (!CommunityModelGroups::deleteGroupDiscussions($uniqueID))
			{
				IJReq::setResponse(500);
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}

			// Delete all group's media files
			if (!CommunityModelGroups::deleteGroupMedia($uniqueID))
			{
				IJReq::setResponse(500);
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}

			// Delete group
			$group  = JTable::getInstance('Group', 'CTable');
			$group->load($uniqueID);
			$groupData = $group;

			if ($group->delete($uniqueID))
			{
				CFactory::load('libraries', 'featured');
				$featured = new CFeatured(FEATURED_GROUPS);
				$featured->delete($uniqueID);

				jimport('joomla.filesystem.file');

				// @rule: Delete only thumbnail and avatars that exists for the specific group
				if ($groupData->avatar != 'components/com_community/assets/group.jpg' && !empty($groupData->avatar))
				{
					$path = explode('/', $groupData->avatar);
					$file = JPATH_ROOT . '/' . $path[0] . '/' . $path[1] . '/' . $path[2] . '/' . $path[3];

					if (JFile::exists($file))
					{
						JFile::delete($file);
					}
				}

				if ($groupData->thumb != 'components/com_community/assets/group_thumb.jpg' && !empty($groupData->thumb))
				{
					$path = explode('/', $groupData->thumb);
					$file = JPATH_ROOT . '/' . $path[0] . '/' . $path[1] . '/' . $path[2] . '/' . $path[3];

					if (JFile::exists($file))
					{
						JFile::delete($file);
					}
				}

				// Trigger for onGroupDelete
				// Remove from activity stream
				CActivityStream::remove('groups', $uniqueID);
				$this->jsonarray['code'] = 200;

				return $this->jsonarray;
			}
			else
			{
				$this->jsonarray['code'] = 500;

				return false;
			}
		}
	}

	/**
	 * used to delete group
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"unpublish",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function unpublish()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		CFactory::load('helpers', 'owner');

		if (!COwnerHelper::isCommunityAdmin($this->IJUserID))
		{
			IJReq::setResponse(706, JText::_('COM_COMMUNITY_GROUPS_UNPUBLISH_DENIED'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
		else
		{
			CFactory::load('models', 'groups');
			$group  = JTable::getInstance('Group', 'CTable');
			$group->load($uniqueID);

			if ($group->id == 0)
			{
				IJReq::setResponse(400, JText::_('COM_COMMUNITY_GROUPS_ID_NOITEM'));
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}
			else
			{
				$group->published = 0;

				if ($group->store())
				{
					require_once JPATH_ROOT . '/components/com_community/controllers/groups.php';
					$group_controller_obj = new CommunityGroupsController;

					// Trigger for onGroupDisable
					$group_controller_obj->triggerGroupEvents('onGroupDisable', $group);
					$this->jsonarray['code'] = 200;

					return $this->jsonarray;
				}
				else
				{
					$this->jsonarray['code'] = 500;

					return false;
				}
			}
		}
	}

	/**
	 * used to get announcement
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"announcement",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "pageNO":"pageNO"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function announcement()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$pageNO   = IJReq::getTaskData('pageNO', 0, 'int');
		$limit    = PAGE_GROUP_BULLETIN_LIMIT;

		if ($pageNO == 0 || $pageNO == '' || $pageNO == 1)
		{
			$startFrom = 0;
		}
		else
		{
			$startFrom = ($limit * ($pageNO - 1));
		}

		$bulletinModel  = CFactory::getModel('bulletins');
		$bulletinModel->setState('limit', $limit);
		$bulletinModel->setState('limitstart', $startFrom);

		$bulletins = $bulletinModel->getBulletins($uniqueID, $limit);
		$total     = $bulletinModel->_pagination->total;

		if (count($bulletins) > 0)
		{
			$this->jsonarray['code']      = 200;
			$this->jsonarray['pageLimit'] = $limit;
			$this->jsonarray['total']     = $total;
		}
		else
		{
			IJReq::setResponse(204);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		foreach ($bulletins as $key => $value)
		{
			$this->jsonarray['announcements'][$key]['id']             = $value->id;
			$this->jsonarray['announcements'][$key]['title']          = $value->title;
			$this->jsonarray['announcements'][$key]['message']        = strip_tags($value->message);
			$usr                                                      = $this->jomHelper->getUserDetail($value->created_by);
			$this->jsonarray['announcements'][$key]['user_id']        = $usr->id;
			$this->jsonarray['announcements'][$key]['user_name']      = $usr->name;
			$this->jsonarray['announcements'][$key]['user_avatar']    = $usr->avatar;
			$this->jsonarray['announcements'][$key]['user_profile']   = $usr->profile;
			$format                                                   = "%A, %d %B %Y";
			$this->jsonarray['announcements'][$key]['date']           = CTimeHelper::getFormattedTime($value->date, $format);
			$params                                                   = new CParameter($value->params);
			$this->jsonarray['announcements'][$key]['filePermission'] = $params->get('filepermission-member');

			if (SHARE_GROUP_BULLETIN == 1)
			{
				$this->jsonarray['announcements'][$key]['shareLink'] = JURI::base() . "index.php?option=com_community&view=groups&task=viewbulletin&groupid={$uniqueID}&bulletinid={$value->id}";
			}

			$query = "SELECT count(id)
					FROM #__community_files
					WHERE `groupid`={$uniqueID}
					AND `bulletinid`={$value->id}";
			$this->db->setQuery($query);
			$this->jsonarray['announcements'][$key]['files'] = $this->db->loadResult();
		}

		return $this->jsonarray;
	}

	/**
	 * used to get announcement
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"addAnnouncement",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "announcementID":"announcementID",
	 *            "title":"title",
	 *            "message":"message"
	 *            "file":"file" // boolean 0/1, 1: if members allow to upload files.
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function addAnnouncement()
	{
		$uniqueID       = IJReq::getTaskData('uniqueID', 0, 'int');
		$announcementID = IJReq::getTaskData('announcementID', 0, 'int');
		$title          = IJReq::getTaskData('title', null);
		$message        = IJReq::getTaskData('message', null);
		$file           = IJReq::getTaskData('file', 0, 'bool');
		$validated      = true;

		if (!$uniqueID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		CFactory::load('models', 'bulletins');
		CFactory::load('helpers', 'owner');

		// Load necessary models
		$groupsModel = CFactory::getModel('groups');

		$group  = JTable::getInstance('Group', 'CTable');
		$group->load($uniqueID);

		// Ensure user has really the privilege to view this page.
		if ($this->my->id != $group->ownerid && !COwnerHelper::isCommunityAdmin($this->IJUserID) && !$groupsModel->isAdmin($this->my->id, $uniqueID))
		{
			IJReq::setResponse(706);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// Get variables from query
		$bulletin  = JTable::getInstance('Bulletin', 'CTable');

		if ($announcementID)
		{
			// Edit announcement
			$bulletin->load($announcementID);
		}

		$bulletin->title   = $title;
		$bulletin->message = $message;
		$bulletin->params  = '{"filepermission-member":' . $file . '}';

		if (empty($bulletin->title))
		{
			IJReq::setResponse(400, JText::_('COM_COMMUNITY_GROUPS_BULLETIN_EMPTY'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if (empty($bulletin->message))
		{
			IJReq::setResponse(400, JText::_('COM_COMMUNITY_GROUPS_BULLETIN_BODY_EMPTY'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$bulletin->groupid    = $uniqueID;
		$bulletin->date       = gmdate('Y-m-d H:i:s');
		$bulletin->created_by = $this->my->id;
		$bulletin->published  = 1;

		$bulletin->store(true);

		if (!$announcementID)
		{
			// Send notification to all user
			$memberCount = $groupsModel->getMembersCount($uniqueID);
			$members     = $groupsModel->getMembers($uniqueID, $memberCount, true, false, SHOW_GROUP_ADMIN);

			$membersArray = array();

			foreach ($members as $row)
			{
				$membersArray[] = $row->id;
			}

			unset($members);

			// Add notification
			CFactory::load('libraries', 'notification');

			$params = new CParameter('');
			$params->set('url', 'index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $uniqueID);
			$params->set('group', $group->name);
			$params->set('subject', $bulletin->title);

			CNotificationLibrary::add('groups.create.news', $this->my->id, $membersArray, JText::sprintf('COM_COMMUNITY_GROUPS_EMAIL_NEW_BULLETIN_SUBJECT', $group->name), '', 'groups.bulletin', $params);

			// Send push notification
			// Get user push notification params and user device token and device type
			$memberslist = implode(',', $membersArray);
			$query       = "SELECT userid,`jomsocial_params`,`device_token`,`device_type`
					FROM #__ijoomeradv_users
					WHERE `userid` IN ({$memberslist})";

			$this->db->setQuery($query);
			$puserlist = $this->db->loadObjectList();

			$groupModel                    = CFactory::getModel('groups');
			$groupdata['id']               = $group->id;
			$groupdata['isAdmin']          = intval($groupModel->isAdmin($this->my->id, $group->id));
			$groupdata['isCommunityAdmin'] = COwnerHelper::isCommunityAdmin($this->my->id) ? 1 : 0;

			$announcementsdata['id']             = $bulletin->id;
			$announcementsdata['title']          = $bulletin->title;
			$announcementsdata['message']        = strip_tags($bulletin->message);
			$usr                                 = $this->jomHelper->getUserDetail($bulletin->created_by);
			$announcementsdata['user_id']        = $usr->id;
			$announcementsdata['user_name']      = $usr->name;
			$announcementsdata['user_avatar']    = $usr->avatar;
			$announcementsdata['user_profile']   = $usr->profile;
			$format                              = "%A, %d %B %Y";
			$announcementsdata['date']           = CTimeHelper::getFormattedTime($bulletin->date, $format);
			$params                              = new CParameter($bulletin->params);
			$announcementsdata['filePermission'] = $params->get('filepermission-member');

			if (SHARE_GROUP_BULLETIN == 1)
			{
				$announcementsdata['shareLink'] = JURI::base() . "index.php?option=com_community&view=groups&task=viewbulletin&groupid={$uniqueID}&bulletinid={$bulletin->id}";
			}

			$query = "SELECT count(id)
					FROM #__community_files
					WHERE `groupid`={$uniqueID}
					AND `bulletinid`={$bulletin->id}";
			$this->db->setQuery($query);
			$announcementsdata['files'] = $this->db->loadResult();

			// Change for id based push notification
			$pushOptions['detail']['content_data']['groupdetail']        = $groupdata;
			$pushOptions['detail']['content_data']['announcementdetail'] = $announcementsdata;
			$pushOptions['detail']['content_data']['type']               = 'announcement';
			$pushOptions                                                 = gzcompress(json_encode($pushOptions));

			$match        = array('{group}', '{announcement}');
			$replace      = array($group->name, $bulletin->title);
			$message      = str_replace($match, $replace, JText::sprintf('COM_COMMUNITY_GROUPS_EMAIL_NEW_BULLETIN_SUBJECT'));
			$obj          = new stdClass;
			$obj->id      = null;
			$obj->detail  = $pushOptions;
			$obj->tocount = count($puserlist);
			$this->db->insertObject('#__ijoomeradv_push_notification_data', $obj, 'id');

			if ($obj->id)
			{
				$this->jsonarray['pushNotificationData']['id']         = $obj->id;
				$this->jsonarray['pushNotificationData']['to']         = $memberslist;
				$this->jsonarray['pushNotificationData']['message']    = $message;
				$this->jsonarray['pushNotificationData']['type']       = 'group';
				$this->jsonarray['pushNotificationData']['configtype'] = 'pushnotif_groups_create_news';
			}

			// @rule: only add the activities of the news if the group is not private.
			if ($group->approvals == COMMUNITY_PUBLIC_GROUP)
			{
				// Add logging to the bulletin
				$url = CRoute::_('index.php?option=com_community&view=groups&task=viewbulletin&groupid=' . $group->id . '&bulletinid=' . $bulletin->id);

				// Add activity logging
				CFactory::load('libraries', 'activities');
				$act          = new stdClass;
				$act->cmd     = 'group.news.create';
				$act->actor   = $this->my->id;
				$act->target  = 0;
				$act->title   = $this->my->getDisplayName() . " ► " . JText::sprintf('COM_COMMUNITY_GROUPS_NEW_GROUP_NEWS', '{group_url}', $bulletin->title);
				$act->content = ($group->approvals == 0) ? JString::substr(strip_tags($bulletin->message), 0, 100) : '';
				$act->app     = 'groups';
				$act->cid     = $bulletin->groupid;

				$params = new CParameter('');
				$params->set('group_url', 'index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $group->id);

				CActivityStream::add($act, $params->toString());
			}

			// Add user points
			CFactory::load('libraries', 'userpoints');
			CUserPoints::assignPoint('group.news.create');
		}

		$this->jsonarray['code'] = 200;

		return $this->jsonarray;
	}

	/**
	 * used to delete announcement
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"deleteAnnouncement",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "announcementID":"announcementID"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function deleteAnnouncement()
	{
		$uniqueID       = IJReq::getTaskData('uniqueID', 0, 'int');
		$announcementID = IJReq::getTaskData('announcementID', 0, 'int');

		if (!$this->my->id)
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if (empty($announcementID) || empty($uniqueID))
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		CFactory::load('helpers', 'owner');
		CFactory::load('models', 'bulletins');

		$groupsModel = CFactory::getModel('groups');
		$group        = JTable::getInstance('Group', 'CTable');
		$group->load($uniqueID);

		if ($groupsModel->isAdmin($this->IJUserID, $group->id) || COwnerHelper::isCommunityAdmin($this->IJUserID))
		{
			$bulletin  = JTable::getInstance('Bulletin', 'CTable');
			$bulletin->load($announcementID);

			if ($bulletin->delete())
			{
				// Add user points
				CFactory::load('libraries', 'userpoints');
				CUserPoints::assignPoint('group.news.remove');

				$this->jsonarray['code'] = 200;

				return $this->jsonarray;
			}
		}
		else
		{
			IJReq::setResponse(706, JText::_('COM_COMMUNITY_PERMISSION_DENIED_WARNING'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
	}

	/**
	 * used to get files
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"files",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "pageNO":"pageNO",
	 *            "type":"type" // announcement, discussion, group, hits, delete
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function files()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$pageNO   = IJReq::getTaskData('pageNO', 0, 'int');
		$type     = IJReq::getTaskData('type');
		$limit    = PAGE_GROUP_FILE_LIMIT;

		if (!$uniqueID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($pageNO == 0 || $pageNO == '' || $pageNO == 1)
		{
			$startFrom = 0;
		}
		else
		{
			$startFrom = ($limit * ($pageNO - 1));
		}

		$group  = JTable::getInstance('Group', 'CTable');
		CFactory::load('helpers', 'owner');

		switch ($type)
		{
			case 'announcement':
				$type = 'bulletinid';
				break;

			case 'discussion':
				$type = 'discussionid';
				break;

			case 'group':
				$type = 'groupid';
				break;

			case 'hits':
				$query = "UPDATE `#__community_files`
						SET `hits` = `hits`+1
						WHERE `id` = {$uniqueID}";
				$this->db->setQuery($query);
				$this->db->query();
				$this->jsonarray['code'] = 200;

				return $this->jsonarray;
				break;

			case 'delete':
				$file  = JTable::getInstance('File', 'CTable');
				$file->load($uniqueID);

				if ($file->discussionid)
				{
					$ftype = 'bulletin';
				}
				elseif ($file->bulletinid)
				{
					$ftype = 'discussion';
				}

				if (!$this->my->authorise('community.delete', 'files.' . $ftype, $file))
				{
					IJReq::setResponse(706);
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				if ($file->delete($uniqueID))
				{
					$this->jsonarray['code'] = 200;

					return $this->jsonarray;
				}
				break;

			default:
				IJReq::setResponse(400);
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
		}

		$query = "SELECT count(id)
		 		FROM #__community_files
		 		WHERE {$type} = {$uniqueID}";
		$this->db->setQuery($query);
		$total = $this->db->loadResult();

		$query = "SELECT *
		 		FROM #__community_files
		 		WHERE {$type} = {$uniqueID}
				LIMIT {$startFrom} , {$limit}";
		$this->db->setQuery($query);
		$files = $this->db->loadObjectList();

		if (count($files) > 0)
		{
			$this->jsonarray['code']      = 200;
			$this->jsonarray['pageLimit'] = $limit;
			$this->jsonarray['total']     = $total;
		}
		else
		{
			IJReq::setResponse(204);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		foreach ($files as $key => $value)
		{
			$this->jsonarray['files'][$key]['id']   = $value->id;
			$this->jsonarray['files'][$key]['name'] = $value->name;

			if ($value->storage == 'file')
			{
				$p_url = JURI::base();
			}
			else
			{
				$s3BucketPath = $this->config->get('storages3bucket');

				if (!empty($s3BucketPath))
					$p_url = 'http://' . $s3BucketPath . '.s3.amazonaws.com/';
				else
					$p_url = JURI::base();
			}

			$this->jsonarray['files'][$key]['url']          = $p_url . $value->filepath;
			$this->jsonarray['files'][$key]['size']         = $value->filesize;
			$this->jsonarray['files'][$key]['hits']         = $value->hits;
			$usr                                            = $this->jomHelper->getUserDetail($value->creator);
			$this->jsonarray['files'][$key]['user_id']      = $usr->id;
			$this->jsonarray['files'][$key]['user_name']    = $usr->name;
			$this->jsonarray['files'][$key]['user_profile'] = $usr->profile;
			$format                                         = "%A, %d %B %Y";
			$this->jsonarray['files'][$key]['date']         = CTimeHelper::getFormattedTime($value->created, $format);

			if (!$group->id)
			{
				$group->load($value->groupid);
			}

			$this->jsonarray['files'][$key]['deleteAllowed'] = intval($this->IJUserID == $val->cretor OR $group->isAdmin($this->IJUserID) OR COwnerHelper::isCommunityAdmin($this->IJUserID));
		}

		return $this->jsonarray;
	}

	/**
	 * used to upload files
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"uploadFile",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "type":"type" // discussion, bulletin
	 *        }
	 *    }
	 *
	 * file should be posted to "files"
	 *
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function uploadFile()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$type     = IJReq::getTaskData('type');
		$type     = ($type == 'announcement') ? 'bulletin' : $type;
		$files    = JRequest::get('files');

		CFactory::load('helpers', 'file');
		CFactory::load('libraries', 'limits');

		if ($this->my->id == 0)
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$parentTable = JTable::getInstance(ucfirst($type), 'CTable');
		$parentTable->load($uniqueID);

		$table  = JTable::getInstance('File', 'CTable');

		CFactory::load('libraries', 'files');
		$fileLib = new CFilesLibrary;

		if (CLimitsLibrary::exceedDaily('files', $this->IJUserID))
		{
			IJReq::setResponse(416, JText::_('COM_COMMUNITY_FILES_LIMIT_REACHED'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		foreach ($files as $_file)
		{
			$ext            = pathinfo($_file['name']);
			$file->creator  = $this->IJUserID;
			$file->filesize = sprintf("%u", $_file['size']);
			$file->name     = JString::substr($_file['name'], 0, JString::strlen($_file['name']) - (JString::strlen($ext['extension']) + 1));
			$file->created  = gmdate('Y-m-d H:i:s');
			$file->type     = CFileHelper::getExtensionIcon(CFileHelper::getFileExtension($_file['name']));
			$fileName       = JUtility::getHash($_file['name'] . time()) . JString::substr($_file['name'], JString::strlen($_file['name']) - (JString::strlen($ext['extension']) + 1));

			if ($_file['error'] > 0 && $_file['error'] !== 'UPLOAD_ERR_OK')
			{
				IJReq::setResponse(500, JText::sprintf('COM_COMMUNITY_PHOTOS_UPLOAD_ERROR', $_file['error']));
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}

			if (!$fileLib->checkType($_file['name']))
			{
				IJReq::setResponse(415, JText::_('COM_COMMUNITY_IMAGE_FILE_NOT_SUPPORTED'));
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}

			switch ($type)
			{
				case 'discussion':
					$file->discussionid = $parentTable->id;
					$file->groupid      = $parentTable->groupid;
					$file->filepath     = 'images/files' . '/' . $type . '/' . $file->discussionid . '/' . $fileName;
					break;
				case 'bulletin':
					$file->bulletinid = $parentTable->id;
					$file->groupid    = $parentTable->groupid;
					$file->filepath   = 'images/files' . '/' . $type . '/' . $file->bulletinid . '/' . $fileName;
					break;
			}

			if (!JFolder::exists(JPATH_ROOT . '/images/files' . '/' . $type . '/' . $parentTable->id))
			{
				JFolder::create(JPATH_ROOT . '/images/files' . '/' . $type . '/' . $parentTable->id, (int) octdec($this->config->get('folderpermissionsphoto')));
				JFile::copy(JPATH_ROOT . '/components/com_community/index.html', JPATH_ROOT . '/files' . '/' . $type . '/' . $parentTable->id  '/index.html');
			}

			JFile::copy($_file['tmp_name'], JPATH_ROOT . '/images/files' . '/' . $type . '/' . $parentTable->id . '/' . $fileName);

			$table->bind($file);

			$table->store();

			// Add notification
			CFactory::load('libraries', 'notification');

			$params = new CParameter('');

			switch ($type)
			{
				case 'discussion':
					// Get repliers for this discussion and notify the discussion creator too
					$discussionModel = CFactory::getModel('Discussions');
					$discussion       = JTable::getInstance('Discussion', 'CTable');
					$discussion->load($parentTable->id);
					$users   = $discussionModel->getRepliers($discussion->id, $discussion->groupid);
					$users[] = $discussion->creator;

					// The person who post this, should not be getting notification email
					$key = array_search($this->IJUserID, $users);

					if ($key !== false && isset($users[$key]))
					{
						unset($users[$key]);
					}

					$params->set('url', 'index.php?option=com_community&view=groups&task=viewdiscussion&groupid=' . $discussion->groupid . '&topicid=' . $discussion->id);

					$params->set('filename', $_file['name']);
					$params->set('discussion', $discussion->title);
					$params->set('discussion_url', 'index.php?option=com_community&view=groups&task=viewdiscussion&groupid=' . $discussion->groupid . '&topicid=' . $discussion->id);
					CNotificationLibrary::add('groups_discussion_newfile', $this->IJUserID, $users, JText::sprintf('COM_COMMUNITY_GROUP_DISCUSSION_NEW_FILE_SUBJECT'), '', 'groups.discussion.newfile', $params);

					// Send push notification
					$memberlist = implode(',', $users);
					$query      = "SELECT userid,`jomsocial_params`,`device_token`,`device_type`
							FROM #__ijoomeradv_users
							WHERE `userid` IN ({$memberlist})";
					$this->db->setQuery($query);
					$puserlist = $this->db->loadObjectList();

					$group  = JTable::getInstance('Group', 'CTable');
					$group->load($discussion->groupid);
					$groupdata['id'] = $group->id;
					$groupModel      = CFactory::getModel('groups');

					$discussionsdata['id']      = $discussion->id;
					$discussionsdata['title']   = $discussion->title;
					$discussionsdata['message'] = strip_tags($discussion->message);
					$pushusr                    = $this->jomHelper->getUserDetail($discussion->creator);

					$discussionsdata['user_name']    = $pushusr->name;
					$discussionsdata['user_avatar']  = $pushusr->avatar;
					$discussionsdata['user_profile'] = $pushusr->profile;

					$format                            = "%A, %d %B %Y";
					$discussionsdata['date']           = CTimeHelper::getFormattedTime($discussion->lastreplied, $format);
					$discussionsdata['isLocked']       = intval($discussion->lock);
					$wallModel                          = CFactory::getModel('wall');
					$wallContents                      = $wallModel->getPost('discussions', $discussion->id, 9999999, 0);
					$discussionsdata['topics']         = count($wallContents);
					$params                            = new CParameter($discussion->params);
					$discussionsdata['filePermission'] = $params->get('filepermission-member');

					if (SHARE_GROUP_DISCUSSION == 1)
					{
						$discussionsdata['shareLink'] = JURI::base() . "index.php?option=com_community&view=groups&task=viewdiscussion&groupid={$uniqueID}2&topicid={$discussion->id}";
					}

					$query = "SELECT count(id)
							FROM #__community_files
							WHERE `groupid`={$group->id}
							AND `discussionid`={$discussion->id}";
					$this->db->setQuery($query);
					$discussionsdata['files'] = $this->db->loadResult();

					// Send pushnotification data
					$search  = array('{actor}', '{filename}', '{discussion}');
					$replace = array($usr->name, $_file['name'], $discussion->title);
					$message = str_replace($search, $replace, JText::sprintf('COM_COMMUNITY_GROUP_DISCUSSION_NEW_FILE_SUBJECT'));

					foreach ($puserlist as $puser)
					{
						$usr = $this->jomHelper->getUserDetail($this->IJUserID);

						if ($puser->userid == $discussion->creator)
						{
							$discussionsdata['user_id'] = 0;
						}
						else
						{
							$discussionsdata['user_id'] = $discussion->creator;
						}

						$groupdata['isAdmin']          = intval($groupModel->isAdmin($puser->userid, $group->id));
						$groupdata['isCommunityAdmin'] = COwnerHelper::isCommunityAdmin($puser->userid) ? 1 : 0;

						// Change for id based push notification
						$pushOptions                                               = array();
						$pushOptions['detail']['content_data']['groupdetail']      = $groupdata;
						$pushOptions['detail']['content_data']['discussiondetail'] = $discussionsdata;
						$pushOptions['detail']['content_data']['type']             = 'discussion';
						$pushOptions                                               = gzcompress(json_encode($pushOptions));

						$obj          = new stdClass;
						$obj->id      = null;
						$obj->detail  = $pushOptions;
						$obj->tocount = 1;
						$this->db->insertObject('#__ijoomeradv_push_notification_data', $obj, 'id');

						if ($obj->id)
						{
							$this->jsonarray['pushNotificationData']['multiid'][$puser->userid] = $obj->id;
						}
					}

					$this->jsonarray['pushNotificationData']['id']         = 0;
					$this->jsonarray['pushNotificationData']['to']         = $memberlist;
					$this->jsonarray['pushNotificationData']['message']    = $message;
					$this->jsonarray['pushNotificationData']['type']       = 'group';
					$this->jsonarray['pushNotificationData']['configtype'] = 'pushnotif_groups_create_news';

					break;
				case 'bulletin':
					break;
			}
		}

		$this->jsonarray['code'] = 200;

		return $this->jsonarray;
	}

	/**
	 * used  to get discussion
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"discussion",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "pageNO":"pageNO"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function discussion()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$pageNO   = IJReq::getTaskData('pageNO', 0, 'int');
		$limit    = PAGE_GROUP_DISCUSSION_LIMIT;

		if (!$uniqueID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($pageNO == 0 || $pageNO == '' || $pageNO == 1)
		{
			$startFrom = 0;
		}
		else
		{
			$startFrom = ($limit * ($pageNO - 1));
		}

		$discussModel  = CFactory::getModel('discussions');
		$discussModel->setState('limit', $limit);
		$discussModel->setState('limitstart', $startFrom);
		$discussions = $discussModel->getDiscussionTopics($uniqueID);
		$total       = $discussModel->_pagination->total;

		if (count($discussions) > 0)
		{
			$this->jsonarray['code']      = 200;
			$this->jsonarray['pageLimit'] = $limit;
			$this->jsonarray['total']     = $total;
		}
		else
		{
			IJReq::setResponse(204);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		foreach ($discussions as $key => $value)
		{
			$this->jsonarray['discussions'][$key]['id']           = $value->id;
			$this->jsonarray['discussions'][$key]['title']        = $value->title;
			$this->jsonarray['discussions'][$key]['message']      = strip_tags($value->message);
			$usr                                                  = $this->jomHelper->getUserDetail($value->creator);
			$this->jsonarray['discussions'][$key]['user_id']      = $usr->id;
			$this->jsonarray['discussions'][$key]['user_name']    = $usr->name;
			$this->jsonarray['discussions'][$key]['user_avatar']  = $usr->avatar;
			$this->jsonarray['discussions'][$key]['user_profile'] = $usr->profile;

			$format                                                 = "%A, %d %B %Y";
			$this->jsonarray['discussions'][$key]['date']           = CTimeHelper::getFormattedTime($value->lastreplied, $format);
			$this->jsonarray['discussions'][$key]['isLocked']       = $value->lock;
			$wallModel                                               = CFactory::getModel('wall');
			$wallContents                                           = $wallModel->getPost('discussions', $value->id, 9999999, 0);
			$this->jsonarray['discussions'][$key]['topics']         = count($wallContents);
			$params                                                 = new CParameter($value->params);
			$this->jsonarray['discussions'][$key]['filePermission'] = $params->get('filepermission-member');

			if (SHARE_GROUP_DISCUSSION == 1)
			{
				$this->jsonarray['discussions'][$key]['shareLink'] = JURI::base() . "index.php?option=com_community&view=groups&task=viewdiscussion&groupid={$uniqueID}2&topicid={$value->id}";
			}

			$query = "SELECT count(id)
					FROM #__community_files
					WHERE `groupid`={$uniqueID}
					AND `discussionid`={$value->id}";

			$this->db->setQuery($query);
			$this->jsonarray['discussions'][$key]['files'] = $this->db->loadResult();
		}

		return $this->jsonarray;
	}

	/**
	 * used to get discussion
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"discussionDetail",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "pageNO":"pageNO"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function discussionDetail()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$pageNO   = IJReq::getTaskData('pageNO', 0, 'int');
		$limit    = PAGE_GROUP_DISCUSSION_LIMIT;

		if ($pageNO == 0 || $pageNO == 1)
		{
			$startFrom = 0;
		}
		else
		{
			$startFrom = ($limit * ($pageNO - 1));
		}

		if (!$uniqueID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$query = "SELECT `groupid`
				FROM #__community_groups_discuss
				WHERE `id`={$uniqueID}";
		$this->db->setQuery($query);
		$groupID = $this->db->loadResult();

		$wallModel     = CFactory::getModel('wall');
		$groupModel    = CFactory::getModel('groups');
		$wallContents = $wallModel->getPost('discussions', $uniqueID, $limit, $startFrom);
		$total        = count($wallModel->getPost('discussions', $uniqueID, 999999, 0));

		if (count($wallContents) > 0)
		{
			$this->jsonarray['code']      = 200;
			$this->jsonarray['pageLimit'] = $limit;
			$this->jsonarray['total']     = $total;
		}
		else
		{
			IJReq::setResponse(204);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($this->config->get('user_avatar_storage') == 'file')
		{
			$p_url = JURI::base();
		}
		else
		{
			$s3BucketPath = $this->config->get('storages3bucket');
			if (!empty($s3BucketPath))
				$p_url = 'http://' . $s3BucketPath . '.s3.amazonaws.com/';
			else
				$p_url = JURI::base();
		}

		foreach ($wallContents as $key => $wallContent)
		{
			$this->jsonarray['replys'][$key]['id']            = $wallContent->id;
			$this->jsonarray['replys'][$key]['message']       = strip_tags($wallContent->comment);
			$usr                                              = $this->jomHelper->getUserDetail($wallContent->post_by);
			$this->jsonarray['replys'][$key]['user_id']       = $usr->id;
			$this->jsonarray['replys'][$key]['user_name']     = $usr->name;
			$this->jsonarray['replys'][$key]['user_avatar']   = $usr->avatar;
			$this->jsonarray['replys'][$key]['user_profile']  = $usr->profile;
			$createdate                                       = JFactory::getDate($wallContent->date);
			$createdTime                                      = CTimeHelper::timeLapse($createdate);
			$this->jsonarray['replys'][$key]['date']          = $createdTime;
			$this->jsonarray['replys'][$key]['timestamp']     = strtotime($wallContent->date);
			$this->jsonarray['replys'][$key]['deleteAllowed'] = intval($this->IJUserID == $wallContent->post_by || $groupModel->isAdmin($this->my->id, $groupID) || COwnerHelper::isCommunityAdmin($this->IJUserID));
		}

		return $this->jsonarray;
	}

	/**
	 * used to add discussion
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"addDiscussion",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "discussionID":"discussionID" // optional, when adding discussion.
	 *            "title":"title",
	 *            "message":"message",
	 *            "file":"file" // file upload permission for member, boolean value 0/1
	 *        }
	 *    }
	 * @return boolean  true on success or false on failure
	 */
	public function adddiscussion()
	{
		$uniqueID     = IJReq::getTaskData('uniqueID', 0, 'int');
		$discussionID = IJReq::getTaskData('discussionID', 0, 'int');

		if ($this->my->id == 0)
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if (!$uniqueID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$group  = JTable::getInstance('Group', 'CTable');
		$group->load($uniqueID);

		// Check if the user is banned
		$isBanned = $group->isBanned($this->my->id);

		CFactory::load('helpers', 'owner');

		if ((!$group->isMember($this->my->id) || $isBanned) && !COwnerHelper::isCommunityAdmin($this->IJUserID))
		{
			IJReq::setResponse(706, JText::_('COM_COMMUNITY_PERMISSION_DENIED_WARNING'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$discussion  = JTable::getInstance('Discussion', 'CTable');

		if ($discussionID)
		{
			$discussion->load($discussionID);
			$creator      = CFactory::getUser($discussion->creator);
			$groupsModel  = CFactory::getModel('Groups');
			$isGroupAdmin = $groupsModel->isAdmin($this->my->id, $discussion->groupid);

			if ($this->my->id != $creator->id && !$isGroupAdmin && !COwnerHelper::isCommunityAdmin())
			{
				IJReq::setResponse(706, JText::_('COM_COMMUNITY_ACCESS_FORBIDDEN'));
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}
		}

		if ($this->_saveDiscussion($discussion) !== false)
		{
			$this->jsonarray['code'] = 200;

			return $this->jsonarray;
		}
		else
		{
			return false;
		}
	}

	/**
	 * function for save Discussion
	 *
	 * @param   [type]  &$discussion  discussion
	 *
	 * @return  boolean true on success or false on failure
	 */
	private function _saveDiscussion(&$discussion)
	{
		$uniqueID                      = IJReq::getTaskData('uniqueID', 0, 'int');
		$discussionID                  = IJReq::getTaskData('discussionID', 0, 'int');
		$data['title']                 = IJReq::getTaskData('title', null);
		$data['message']               = IJReq::getTaskData('message', null);
		$data['filepermission-member'] = IJReq::getTaskData('file', 0, 'bool');
		$data['groupid']               = $uniqueID;

		$inputFilter = CFactory::getInputFilter(true);
		$groupModel   = CFactory::getModel('groups');

		$group  = JTable::getInstance('Group', 'CTable');
		$group->load($uniqueID);

		$discussion->bind($data);

		CFactory::load('helpers', 'owner');
		$creator = CFactory::getUser($discussion->creator);

		if ($this->my->id != $creator->id && !empty($discussion->creator) && !$groupsModel->isAdmin($this->my->id, $discussion->groupid) && !COwnerHelper::isCommunityAdmin())
		{
			IJReq::setResponse(706);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$isNew = is_null($discussion->id) || !$discussion->id ? true : false;

		if ($isNew)
		{
			$discussion->creator = $this->my->id;
		}

		$discussion->groupid     = $uniqueID;
		$discussion->created     = gmdate('Y-m-d H:i:s');
		$discussion->lastreplied = $discussion->created;
		$discussion->message     = $inputFilter->clean($data['message']);

		// @rule: do not allow html tags in the title
		$discussion->title = strip_tags($discussion->title);

		CFactory::load('libraries', 'apps');
		$appsLib      = CAppPlugins::getInstance();
		$saveSuccess = $appsLib->triggerEvent('onFormSave', array('jsform-groups-discussionform'));
		$validated   = true;

		if (empty($saveSuccess) || !in_array(false, $saveSuccess))
		{
			// @rule: Spam checks
			if ($this->config->get('antispam_akismet_discussions'))
			{
				CFactory::load('libraries', 'spamfilter');

				$filter = CSpamFilter::getFilter();
				$filter->setAuthor($this->my->getDisplayName());
				$filter->setMessage($discussion->title . ' ' . $discussion->message);
				$filter->setEmail($this->my->email);
				$filter->setURL(CRoute::_('index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $group->id));
				$filter->setType('message');
				$filter->setIP($_SERVER['REMOTE_ADDR']);

				if ($filter->isSpam())
				{
					IJReq::setResponse(705);
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}
			}

			if (empty($discussion->title))
			{
				IJReq::setResponse(400);
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}

			if (empty($discussion->message))
			{
				IJReq::setResponse(400);
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}

			CFactory::load('models', 'discussions');

			$params = new CParameter('');
			$params->set('filepermission-member', IJReq::getTaskData('file', 0, 'bool'));

			$discussion->params = $params->toString();

			$discussion->store();

			if ($isNew)
			{
				$group  = JTable::getInstance('Group', 'CTable');
				$group->load($uniqueID);

				// Add logging.
				$url = CRoute::_('index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $uniqueID);
				CFactory::load('libraries', 'activities');

				$act               = new stdClass;
				$act->cmd          = 'group.discussion.create';
				$act->actor        = $this->my->id;
				$act->target       = 0;
				$act->title        = $this->my->getDisplayName() . " ► " . JText::sprintf('COM_COMMUNITY_GROUPS_NEW_GROUP_DISCUSSION', '{group_url}', $group->name);
				$act->content      = $discussion->message;
				$act->app          = 'groups.discussion';
				$act->cid          = $discussion->id;
				$act->groupid      = $group->id;
				$act->group_access = $group->approvals;

				$act->like_id   = CActivities::LIKE_SELF;
				$act->like_type = 'groups.discussion';

				$params = new CParameter('');
				$params->set('action', 'group.discussion.create');
				$params->set('topic_id', $discussion->id);
				$params->set('topic', $discussion->title);
				$params->set('group_url', 'index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $group->id);
				$params->set('topic_url', 'index.php?option=com_community&view=groups&task=viewdiscussion&groupid=' . $group->id . '&topicid=' . $discussion->id);

				CActivityStream::add($act, $params->toString());

				// @rule: Add notification for group members whenever a new discussion created.
				if ($this->config->get('groupdiscussnotification') == 1)
				{
					$members = $groupModel->getMembers($uniqueID, null);
					$admins  = $groupModel->getAdmins($uniqueID, null);

					$membersArray = array();

					foreach ($members as $row)
					{
						$membersArray[] = $row->id;
					}

					foreach ($admins as $row)
					{
						$membersArray[] = $row->id;
					}

					unset($members);
					unset($admins);

					// Add notification
					CFactory::load('libraries', 'notification');

					$params = new CParameter('');
					$params->set('url', 'index.php?option=com_community&view=groups&task=viewdiscussion&groupid=' . $group->id . '&topicid=' . $discussion->id);
					$params->set('group', $group->name);
					$params->set('user', $this->my->getDisplayName());
					$params->set('subject', $discussion->title);
					$params->set('message', $discussion->message);

					CNotificationLibrary::add('groups.create.discussion', $discussion->creator, $membersArray, JText::sprintf('COM_COMMUNITY_NEW_DISCUSSION_NOTIFICATION_EMAIL_SUBJECT', $group->name), '', 'groups.discussion', $params);
				}

				$members = $groupModel->getMembers($uniqueID, null);
				$admins  = $groupModel->getAdmins($uniqueID, null);

				$membersArray = array();

				foreach ($members as $row)
				{
					$membersArray[] = $row->id;
				}

				foreach ($admins as $row)
				{
					$membersArray[] = $row->id;
				}

				unset($members);
				unset($admins);
				$membersArray = implode(',', $membersArray);

				// Get user push notification params and user device token and device type
				$query = "SELECT userid,`jomsocial_params`,`device_token`,`device_type`
						FROM #__ijoomeradv_users
						WHERE `userid` IN ({$membersArray})";
				$this->db->setQuery($query);
				$puserlist = $this->db->loadObjectList();

				$groupdata['id'] = $group->id;

				$discussionsdata['id']           = $discussion->id;
				$discussionsdata['title']        = $discussion->title;
				$discussionsdata['message']      = strip_tags($discussion->message);
				$usr                             = $this->jomHelper->getUserDetail($discussion->creator);
				$discussionsdata['user_name']    = $usr->name;
				$discussionsdata['user_avatar']  = $usr->avatar;
				$discussionsdata['user_profile'] = $usr->profile;

				$format                            = "%A, %d %B %Y";
				$discussionsdata['date']           = CTimeHelper::getFormattedTime($discussion->lastreplied, $format);
				$discussionsdata['isLocked']       = intval($discussion->lock);
				$wallModel                          = CFactory::getModel('wall');
				$wallContents                      = $wallModel->getPost('discussions', $discussion->id, 9999999, 0);
				$discussionsdata['topics']         = count($wallContents);
				$params                            = new CParameter($discussion->params);
				$discussionsdata['filePermission'] = $params->get('filepermission-member');

				if (SHARE_GROUP_DISCUSSION == 1)
				{
					$discussionsdata['shareLink'] = JURI::base() . "index.php?option=com_community&view=groups&task=viewdiscussion&groupid={$uniqueID}2&topicid={$discussion->id}";
				}

				$query = "SELECT count(id)
						FROM #__community_files
						WHERE `groupid`={$group->id}
						AND `discussionid`={$discussion->id}";
				$this->db->setQuery($query);
				$discussionsdata['files'] = $this->db->loadResult();

				$usr     = $this->jomHelper->getUserDetail($this->IJUserID);
				$match   = array('{discussion}', '{group}');
				$replace = array($discussion->title, $group->name);
				$message = str_replace($match, $replace, JText::sprintf('COM_COMMUNITY_NEW_DISCUSSION_NOTIFICATION_EMAIL_SUBJECT'));

				foreach ($puserlist as $puser)
				{
					$usr = $this->jomHelper->getUserDetail($this->IJUserID);

					$groupdata['isAdmin']          = intval($groupModel->isAdmin($puser->userid, $group->id));
					$groupdata['isCommunityAdmin'] = COwnerHelper::isCommunityAdmin($puser->userid) ? 1 : 0;

					if ($puser->userid == $group->ownerid)
					{
						$uid = 0;
					}
					else
					{
						$uid = $group->ownerid;
					}

					$discussionsdata['user_id'] = $uid;

					// Change for id based push notification
					$pushOptions                                               = array();
					$pushOptions['detail']['content_data']['groupdetail']      = $groupdata;
					$pushOptions['detail']['content_data']['discussiondetail'] = $discussionsdata;
					$pushOptions['detail']['content_data']['type']             = 'discussion';
					$pushOptions                                               = gzcompress(json_encode($pushOptions));

					$obj          = new stdClass;
					$obj->id      = null;
					$obj->detail  = $pushOptions;
					$obj->tocount = 1;
					$this->db->insertObject('#__ijoomeradv_push_notification_data', $obj, 'id');

					if ($obj->id)
					{
						$this->jsonarray['pushNotificationData']['multiid'][$puser->userid] = $obj->id;
					}
				}

				$this->jsonarray['pushNotificationData']['id']         = 0;
				$this->jsonarray['pushNotificationData']['to']         = $membersArray;
				$this->jsonarray['pushNotificationData']['message']    = $message;
				$this->jsonarray['pushNotificationData']['type']       = 'group';
				$this->jsonarray['pushNotificationData']['configtype'] = 'pushnotif_groups_create_discussion';
			}

			// Add user points
			CFactory::load('libraries', 'userpoints');
			CUserPoints::assignPoint('group.discussion.create');
		}
		else
		{
			$validated = false;
		}

		return $validated;
	}

	/**
	 * used to add /edit discussion reply
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"addDiscussionReply",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "wallID":"wallID", // optional when adding a wall.
	 *            "message":"message"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function addDiscussionReply()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$wallID   = IJReq::getTaskData('wallID', 0, 'int');
		$message  = IJReq::getTaskData('message', null);

		if (!$uniqueID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$filter   = JFilterInput::getInstance();
		$message  = $filter->clean($message, 'string');
		$uniqueId = $filter->clean($uniqueID, 'int');
		$wallID   = $filter->clean($wallID, 'int');
		$message  = strip_tags($message);

		CFactory::load('libraries', 'activities');
		CFactory::load('libraries', 'wall');

		$discussion  = JTable::getInstance('Discussion', 'CTable');
		$discussion->load($uniqueID);

		$group  = JTable::getInstance('Group', 'CTable');
		$group->load($discussion->groupid);

		$discussionModel = CFactory::getModel('Discussions');

		// If the content is false, the message might be empty.
		if (empty($message))
		{
			IJReq::setResponse(400, JText::_('COM_COMMUNITY_EMPTY_MESSAGE'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// @rule: Spam checks
		if ($this->config->get('antispam_akismet_walls'))
		{
			CFactory::load('libraries', 'spamfilter');

			$filter = CSpamFilter::getFilter();
			$filter->setAuthor($this->my->getDisplayName());
			$filter->setMessage($message);
			$filter->setEmail($this->my->email);
			$filter->setURL(CRoute::_('index.php?option=com_community&view=groups&task=viewdiscussion&groupid=' . $discussion->groupid . '&topicid=' . $discussion->id));
			$filter->setType('message');
			$filter->setIP($_SERVER['REMOTE_ADDR']);

			if ($filter->isSpam())
			{
				IJReq::setResponse(705, JText::_('COM_COMMUNITY_WALLS_MARKED_SPAM'));
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}
		}

		// Save the wall content
		$wall = CWallLibrary::saveWall($uniqueID, $message, 'discussions', $this->my, ($this->my->id == $discussion->creator), 'groups,discussion', 'wall.content', $wallID);

		if (!$wallID)
		{
			$date                     = JFactory::getDate();
			$discussion->lastreplied = $date->toMySQL();
			$discussion->store();

			// @rule: only add the activities of the wall if the group is not private.
			// Build the URL
			$discussURL = CUrl::build('groups', 'viewdiscussion', array('groupid' => $discussion->groupid, 'topicid' => $discussion->id), true);

			$act               = new stdClass;
			$act->cmd          = 'group.discussion.reply';
			$act->actor        = $this->my->id;
			$act->target       = 0;
			$act->title        = $this->my->getDisplayName() . " ► " . JText::sprintf('COM_COMMUNITY_GROUPS_REPLY_DISCUSSION', '{discuss_url}', $discussion->title);
			$act->content      = $message;
			$act->app          = 'groups.discussion.reply';
			$act->cid          = $discussion->id;
			$act->groupid      = $group->id;
			$act->group_access = $group->approvals;
			$act->like_id      = $wall->id;
			$act->like_type    = 'groups.discussion.reply';

			$params = new CParameter('');
			$params->set('action', 'group.discussion.reply');
			$params->set('wallid', $wall->id);
			$params->set('group_url', 'index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $group->id);
			$params->set('group_name', $group->name);
			$params->set('discuss_url', 'index.php?option=com_community&view=groups&task=viewdiscussion&groupid=' . $discussion->groupid . '&topicid=' . $discussion->id);

			// Add activity log
			CActivityStream::add($act, $params->toString());

			// Get repliers for this discussion and notify the discussion creator too
			$users   = $discussionModel->getRepliers($discussion->id, $group->id);
			$users[] = $discussion->creator;

			// Make sure that each person gets only 1 email
			$users = array_unique($users);

			// The person who post this, should not be getting notification email
			$key = array_search($this->my->id, $users);

			if ($key !== false && isset($users[$key]))
			{
				unset($users[$key]);
			}

			// Add notification
			CFactory::load('libraries', 'notification');

			$params = new CParameter('');
			$params->set('url', 'index.php?option=com_community&view=groups&task=viewdiscussion&groupid=' . $discussion->groupid . '&topicid=' . $discussion->id);
			$params->set('message', $message);
			$params->set('title', $discussion->title);

			CNotificationLibrary::add('groups.discussion.reply', $this->my->id, $users, JText::sprintf('COM_COMMUNITY_GROUP_NEW_DISCUSSION_REPLY_SUBJECT', $this->my->getDisplayName(), $discussion->title), '', 'groups.discussion.reply', $params);

			// Get user push notification params and user device token and device type
			$userslist = implode(',', $users);
			$query     = "SELECT userid,`jomsocial_params`,`device_token`,`device_type`
					FROM #__ijoomeradv_users
					WHERE `userid` IN ({$userslist})";
			$this->db->setQuery($query);
			$puserlist = $this->db->loadObjectList();

			$groupdata['id'] = $group->id;
			$groupModel      = CFactory::getModel('groups');

			$discussionsdata['id']           = $discussion->id;
			$discussionsdata['title']        = $discussion->title;
			$discussionsdata['message']      = strip_tags($discussion->message);
			$usr                             = $this->jomHelper->getUserDetail($discussion->creator);
			$discussionsdata['user_name']    = $usr->name;
			$discussionsdata['user_avatar']  = $usr->avatar;
			$discussionsdata['user_profile'] = $usr->profile;

			$format                            = "%A, %d %B %Y";
			$discussionsdata['date']           = CTimeHelper::getFormattedTime($discussion->lastreplied, $format);
			$discussionsdata['isLocked']       = intval($discussion->lock);
			$wallModel                          = CFactory::getModel('wall');
			$wallContents                      = $wallModel->getPost('discussions', $discussion->id, 9999999, 0);
			$discussionsdata['topics']         = count($wallContents);
			$params                            = new CParameter($discussion->params);
			$discussionsdata['filePermission'] = $params->get('filepermission-member');

			if (SHARE_GROUP_DISCUSSION == 1)
			{
				$discussionsdata['shareLink'] = JURI::base() . "index.php?option=com_community&view=groups&task=viewdiscussion&groupid={$uniqueID}2&topicid={$discussion->id}";
			}

			$query = "SELECT count(id)
					FROM #__community_files
					WHERE `groupid`={$group->id}
					AND `discussionid`={$discussion->id}";
			$this->db->setQuery($query);
			$discussionsdata['files'] = $this->db->loadResult();

			// Send pushnotification data
			$usr     = $this->jomHelper->getUserDetail($this->IJUserID);
			$match   = array('{actor}', '{discussion}');
			$replace = array($usr->name, $discussion->title);
			$message = str_replace($match, $replace, JText::sprintf('COM_COMMUNITY_GROUP_NEW_DISCUSSION_REPLY_SUBJECT'));

			foreach ($puserlist as $puser)
			{
				$usr                           = $this->jomHelper->getUserDetail($this->IJUserID);
				$groupdata['isAdmin']          = intval($groupModel->isAdmin($puser->userid, $group->id));
				$groupdata['isCommunityAdmin'] = COwnerHelper::isCommunityAdmin($puser->userid) ? 1 : 0;

				if ($puser->userid == $group->ownerid)
				{
					$uid = 0;
				}
				else
				{
					$uid = $group->ownerid;
				}

				$discussionsdata['user_id'] = $uid;

				// Change for id based push notification
				$pushOptions                                               = array();
				$pushOptions['detail']['content_data']['groupdetail']      = $groupdata;
				$pushOptions['detail']['content_data']['discussiondetail'] = $discussionsdata;
				$pushOptions['detail']['content_data']['type']             = 'discussion';
				$pushOptions                                               = gzcompress(json_encode($pushOptions));

				$obj          = new stdClass;
				$obj->id      = null;
				$obj->detail  = $pushOptions;
				$obj->tocount = 1;
				$this->db->insertObject('#__ijoomeradv_push_notification_data', $obj, 'id');

				if ($obj->id)
				{
					$this->jsonarray['pushNotificationData']['multiid'][$puser->userid] = $obj->id;
				}
			}

			$this->jsonarray['pushNotificationData']['id']         = 0;
			$this->jsonarray['pushNotificationData']['to']         = $userslist;
			$this->jsonarray['pushNotificationData']['message']    = $message;
			$this->jsonarray['pushNotificationData']['type']       = 'group';
			$this->jsonarray['pushNotificationData']['configtype'] = 'pushnotif_groups_discussion_reply';

			// Add user points
			CFactory::load('libraries', 'userpoints');
			CUserPoints::assignPoint('group.discussion.reply');

			$order = $this->config->get('group_discuss_order');
			$order = ($order == 'DESC') ? 'prepend' : 'append';
		}

		$this->jsonarray['code']   = 200;
		$this->jsonarray['wallID'] = $wall->id;

		return $this->jsonarray;
	}

	/**
	 * used to deleteDiscussion
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"deleteDiscusion",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "discussionID":"discussionID",
	 *            "wallID":"wallID", // optional when deleting discussion.
	 *            "type":"type" // discussion, reply
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function deleteDiscussion()
	{
		$uniqueID     = IJReq::getTaskData('uniqueID', 0, 'int');
		$discussionID = IJReq::getTaskData('discussionID', 0, 'int');
		$wallID       = IJReq::getTaskData('wallID', 0, 'int');
		$type         = IJReq::getTaskData('type', 'discussion');

		if ($this->my->id == 0)
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$groupsModel = CFactory::getModel('groups');
		$group        = JTable::getInstance('Group', 'CTable');
		$group->load($uniqueID);
		$isGroupAdmin = $groupsModel->isAdmin($this->my->id, $group->id);

		switch ($type)
		{
			case 'discussion';

				if (empty($discussionID) || empty($uniqueID))
				{
					IJReq::setResponse(400);
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				if (empty($discussionID) || empty($uniqueID))
				{
					IJReq::setResponse(400, JText::_('COM_COMMUNITY_INVALID_ID'));
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				CFactory::load('helpers', 'owner');
				CFactory::load('models', 'discussions');
				CFactory::load('libraries', 'activities');

				$wallModel     = CFactory::getModel('wall');
				$activityModel = CFactory::getModel('activities');
				$fileModel     = CFactory::getModel('files');
				$discussion     = JTable::getInstance('Discussion', 'CTable');
				$discussion->load($discussionID);

				if ($this->my->id == $discussion->creator || $isGroupAdmin || COwnerHelper::isCommunityAdmin())
				{
					if ($discussion->delete())
					{
						// Remove the replies to this discussion as well since we no longer need them
						$wallModel->deleteAllChildPosts($discussionID, 'discussions');

						// Remove from activity stream
						CActivityStream::remove('groups.discussion', $discussionID);

						// Remove Discussion Files
						$fileModel->alldelete($discussionID, 'discussion');
						$this->jsonarray['code'] = 200;

						return $this->jsonarray;
					}
				}
				else
				{
					IJReq::setResponse(706, JText::_('COM_COMMUNITY_GROUPS_DISCUSSION_DELETE_WARNING'));
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}
				break;

			case 'reply':
				$filter = JFilterInput::getInstance();
				$wallid = $filter->clean($wallID, 'int');

				$table  = JTable::getInstance('Wall', 'CTable');
				$table->load($wallID);

				$isGroupAdmin = $groupsModel->isAdmin($this->my->id, $group->id);

				if ($this->my->id == $discussion->creator || $this->my->id == $table->post_by || $isGroupAdmin || COwnerHelper::isCommunityAdmin())
				{
					if ($table->delete())
					{
						$this->jsonarray['code'] = 200;

						return $this->jsonarray;
					}
					else
					{
						IJReq::setResponse(500);
						IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

						return false;
					}
				}
				break;

			default:
				IJReq::setResponse(400);
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
		}

		$this->jsonarray['code'] = 200;

		return $this->jsonarray;
	}

	/**
	 * used to lock / unloack Discussion
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"lockDiscussion",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "discussionID":"discussionID"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function lockDiscussion()
	{
		$uniqueID     = IJReq::getTaskData('uniqueID', 0, 'int');
		$discussionID = IJReq::getTaskData('discussionID', 0, 'int');

		if ($this->my->id == 0)
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if (empty($discussionID) || empty($uniqueID))
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		CFactory::load('helpers', 'owner');
		CFactory::load('models', 'discussions');

		$groupsModel = CFactory::getModel('groups');
		$wallModel   = CFactory::getModel('wall');
		$discussion   = JTable::getInstance('Discussion', 'CTable');
		$discussion->load($discussionID);

		$group  = JTable::getInstance('Group', 'CTable');
		$group->load($uniqueID);
		$isGroupAdmin = $groupsModel->isAdmin($this->my->id, $group->id);

		if ($this->my->id == $discussion->creator || $isGroupAdmin || COwnerHelper::isCommunityAdmin($this->IJUserID))
		{
			$lockStatus = $discussion->lock ? false : true;

			if ($discussion->lock($discussionID, $lockStatus))
			{
				$this->jsonarray['code'] = 200;

				return $this->jsonarray;
			}
			else
			{
				IJReq::setResponse(500);
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}
		}
		else
		{
			IJReq::setResponse(706);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
	}

	/**
	 * used to edit avatar
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"editAvatar",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "discussionID":"discussionID"
	 *        }
	 *    }
	 *
	 * avatar will be posted as image.
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function editAvatar()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');

		if ($this->my->id == 0)
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$data     = new stdClass;
		$data->id = $groupid;

		$groupsModel  = CFactory::getModel('groups');
		$group        = JTable::getInstance('Group', 'CTable');
		$group->load($uniqueID);

		CFactory::load('helpers', 'owner');

		if (!$group->isAdmin($this->my->id) && !COwnerHelper::isCommunityAdmin($this->IJUserID))
		{
			IJReq::setResponse(706, JText::_('COM_COMMUNITY_ACCESS_FORBIDDEN'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		CFactory::load('helpers', 'image');

		$file = JRequest::getVar('image', '', 'FILES', 'array');

		if (!CImageHelper::isValidType($file['type']))
		{
			IJReq::setResponse(415, JText::_('COM_COMMUNITY_IMAGE_FILE_NOT_SUPPORTED'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		CFactory::load('libraries', 'apps');
		$appsLib      = CAppPlugins::getInstance();
		$saveSuccess = $appsLib->triggerEvent('onFormSave', array('jsform-groups-uploadavatar'));

		if (empty($saveSuccess) || !in_array(false, $saveSuccess))
		{
			if (empty($file))
			{
				IJReq::setResponse(400);
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}
			else
			{
				$uploadLimit = (double) $this->config->get('maxuploadsize');
				$uploadLimit = ($uploadLimit * 1024 * 1024);

				// @rule: Limit image size based on the maximum upload allowed.
				if (filesize($file['tmp_name']) > $uploadLimit && $uploadLimit != 0)
				{
					IJReq::setResponse(416, JText::_('COM_COMMUNITY_VIDEOS_IMAGE_FILE_SIZE_EXCEEDED'));
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				if (!CImageHelper::isValid($file['tmp_name']))
				{
					IJReq::setResponse(415, JText::_('COM_COMMUNITY_IMAGE_FILE_NOT_SUPPORTED'));
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}
				else
				{
					// @todo: configurable width?
					$imageMaxWidth = 160;

					// Get a hash for the file name.
					$fileName     = JUtility::getHash($file['tmp_name'] . time());
					$hashFileName = JString::substr($fileName, 0, 24);

					// @todo: configurable path for avatar storage?
					$storage = JPATH_ROOT . '/' . $this->config->getString('imagefolder') '/avatar/groups';
					$storageImage     = $storage . '/' . $hashFileName . CImageHelper::getExtension($file['type']);
					$storageThumbnail = $storage '/thumb_' . $hashFileName . CImageHelper::getExtension($file['type']);
					$image     = $this->config->getString('imagefolder') . '/avatar/groups/' . $hashFileName . CImageHelper::getExtension($file['type']);
					$thumbnail = $this->config->getString('imagefolder') . '/avatar/groups/' . 'thumb_' . $hashFileName . CImageHelper::getExtension($file['type']);

					// Generate full image
					if (!CImageHelper::resizeProportional($file['tmp_name'], $storageImage, $file['type'], $imageMaxWidth))
					{
						IJReq::setResponse(500, JText::sprintf('COM_COMMUNITY_ERROR_MOVING_UPLOADED_FILE', $destPath));
						IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

						return false;
					}

					// Generate thumbnail
					if (!CImageHelper::createThumb($file['tmp_name'], $storageThumbnail, $file['type']))
					{
						IJReq::setResponse(500, JText::sprintf('COM_COMMUNITY_ERROR_MOVING_UPLOADED_FILE', $destPath));
						IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

						return false;
					}

					// Update the group with the new image
					$groupsModel->setImage($uniqueID, $image, 'avatar');
					$groupsModel->setImage($uniqueID, $thumbnail, 'thumb');

					// @rule: only add the activities of the news if the group is not private.
					if ($group->approvals == COMMUNITY_PUBLIC_GROUP)
					{
						$url          = CRoute::_('index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $uniqueID);
						$act          = new stdClass;
						$act->cmd     = 'group.avatar.upload';
						$act->actor   = $this->my->id;
						$act->target  = 0;
						$act->title   = JText::sprintf('COM_COMMUNITY_GROUPS_NEW_GROUP_AVATAR', '{group_url}', $group->name);
						$act->content = '<img src="' . rtrim(JURI::root(), '/') . '/' . $thumbnail . '" style="border: 1px solid #eee;margin-right: 3px;" />';
						$act->app     = 'groups';
						$act->cid     = $group->id;

						$params = new CParameter('');
						$params->set('group_url', 'index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $group->id);

						CFactory::load('libraries', 'activities');
						CActivityStream::add($act, $params->toString());
					}

					// Add user points
					CFactory::load('libraries', 'userpoints');
					CUserPoints::assignPoint('group.avatar.upload');

					$this->jsonarray['code'] = 200;

					return $this->jsonarray;
				}
			}
		}

		IJReq::setResponse(500);
		IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

		return false;
	}

	/**
	 * used to add like to the group
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"like",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID" // optional, if not passed then logged in user id will be used
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function like()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');

		if (!$uniqueID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($this->jomHelper->Like('groups', $uniqueID))
		{
			$this->jsonarray['code'] = 200;

			return $this->jsonarray;
		}
		else
		{
			IJReq::setResponse(500);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
	}

	/**
	 * used to add dislike to the group
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"dislike",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID" // optional, if not passed then logged in user id will be used
	 *        }
	 *    }
	 *
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function dislike()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');

		if (!$uniqueID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($this->jomHelper->Dislike('groups', $uniqueID))
		{
			$this->jsonarray['code'] = 200;

			return $this->jsonarray;
		}
		else
		{
			IJReq::setResponse(500);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
	}

	/**
	 * used to unlike like/dislike value to the group
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"unlike",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function unlike()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');

		if (!$uniqueID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($this->jomHelper->Unlike('groups', $uniqueID))
		{
			$this->jsonarray['code'] = 200;

			return $this->jsonarray;
		}
		else
		{
			IJReq::setResponse(500);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
	}

	/**
	 * used to send mail to all members
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"sendmail",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "title":"title",
	 *            "message":"message"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function sendmail()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$message  = IJReq::getTaskData('message', null);
		$title    = IJReq::getTaskData('title', null);

		$group  = JTable::getInstance('Group', 'CTable');
		$group->load($uniqueID);

		CFactory::load('helpers', 'owner');

		if (empty($uniqueID) || (!$group->isAdmin($this->my->id) && !COwnerHelper::isCommunityAdmin($this->IJUserID)))
		{
			IJReq::setResponse(706, JText::_('COM_COMMUNITY_ACCESS_FORBIDDEN'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$groupsModel = CFactory::getModel('Groups');
		$members     = $groupsModel->getMembers($group->id, COMMUNITY_GROUPS_NO_LIMIT, COMMUNITY_GROUPS_ONLY_APPROVED, COMMUNITY_GROUPS_NO_RANDOM, COMMUNITY_GROUPS_SHOW_ADMINS);

		$errors = false;

		if (empty($message))
		{
			IJReq::setResponse(400, JText::_('COM_COMMUNITY_INBOX_MESSAGE_REQUIRED'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if (empty($title))
		{
			IJReq::setResponse(400, JText::_('COM_COMMUNITY_TITLE_REQUIRED'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// Add notification
		CFactory::load('libraries', 'notification');
		$emails = array();
		$total  = 0;

		foreach ($members as $member)
		{
			// $total += 1;
			$total++;
			$user     = CFactory::getUser($member->id);
			$emails[] = $user->id;

			// Exclude the actor
			if ($user->id == $this->my->id)
			{
				// $total -= 1;
				$total--;
			}
		}

		$params = new CParameter('');
		$params->set('url', 'index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $group->id);
		$params->set('title', $title);
		$params->set('message', $message);
		CNotificationLibrary::add('groups.sendmail', $this->my->id, $emails, JText::sprintf('COM_COMMUNITY_GROUPS_SENDMAIL_SUBJECT', $group->name), '', 'groups.sendmail', $params);

		// Send push notification
		// Get user push notification params and user device token and device type
		$memberslist = implode(',', $emails);
		$query       = "SELECT userid,`jomsocial_params`,`device_token`,`device_type`
				FROM #__ijoomeradv_users
				WHERE `userid` IN ({$memberslist})";
		$this->db->setQuery($query);
		$puserlist = $this->db->loadObjectList();

		// Change for id based push notification
		$pushOptions['detail'] = array();
		$pushOptions           = gzcompress(json_encode($pushOptions));

		$usr          = $this->jomHelper->getUserDetail($this->my->id);
		$match        = array('{group}', '{email}');
		$replace      = array($group->name, $title);
		$message      = str_replace($match, $replace, JText::sprintf('COM_COMMUNITY_GROUPS_SENDMAIL_SUBJECT'));
		$obj          = new stdClass;
		$obj->id      = null;
		$obj->detail  = $pushOptions;
		$obj->tocount = count($puserlist);
		$this->db->insertObject('#__ijoomeradv_push_notification_data', $obj, 'id');

		if ($obj->id)
		{
			$this->jsonarray['pushNotificationData']['id']         = $obj->id;
			$this->jsonarray['pushNotificationData']['to']         = $memberslist;
			$this->jsonarray['pushNotificationData']['message']    = $message;
			$this->jsonarray['pushNotificationData']['type']       = 'groupmail';
			$this->jsonarray['pushNotificationData']['configtype'] = 'pushnotif_groups_sendmail';
		}

		$this->jsonarray['code'] = 200;

		return $this->jsonarray;
	}

	/**
	 * used to get group member list
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"members",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "waiting":"waiting", // 1: waiting user list, 0: default value
	 *            "pageNO":"pageNO"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function members()
	{
		$uniqueID     = IJReq::getTaskData('uniqueID', 0, 'int');
		$pageNO       = IJReq::getTaskData('pageNO', 0, 'int');
		$limit        = PAGE_MEMBER_LIMIT;
		$randomize    = false;
		$onlyApproved = (IJReq::getTaskData('waiting', 0, 'int')) ? false : true;
		$loadAdmin    = SHOW_GROUP_ADMIN;

		if (!$uniqueID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($pageNO == 0 || $pageNO == '' || $pageNO == 1)
		{
			$startFrom = 0;
		}
		else
		{
			$startFrom = ($limit * ($pageNO - 1));
		}

		$groupModel = CFactory::getModel('groups');

		$group  = JTable::getInstance('Group', 'CTable');
		$group->load($uniqueID);

		$query = "SELECT a.memberid AS id, a.approved , b.name as name, a.permissions
				FROM {$this->db->{JOOMLA_DB_NAMEQOUTE}('#__community_groups_members')} AS a
				INNER JOIN {$this->db->{JOOMLA_DB_NAMEQOUTE}('#__users')} AS b
				WHERE b.id=a.memberid
				AND a.groupid = {$this->db->Quote($uniqueID)}
				AND b.block = {$this->db->Quote('0')}
				AND a.permissions != {$this->db->quote(COMMUNITY_GROUP_BANNED)}";

		if ($onlyApproved)
		{
			$query .= ' AND a.approved=' . $this->db->Quote('1');
		}
		else
		{
			$query .= ' AND a.approved=' . $this->db->Quote('0');
		}

		if (!$loadAdmin)
		{
			$query .= ' AND a.permissions=' . $this->db->Quote('0');
		}

		if ($randomize)
		{
			$query .= ' ORDER BY RAND() ';
		}
		else
		{
			$query .= ' ORDER BY b.`' . $this->config->get('displayname') . '`';
		}

		if (!is_null($limit))
		{
			$query .= ' LIMIT ' . $startFrom . ',' . $limit;
		}

		$this->db->setQuery($query);
		$result = $this->db->loadObjectList();

		$query = "SELECT COUNT(*)
				FROM {$this->db->{JOOMLA_DB_NAMEQOUTE}('#__community_groups_members')} AS a
				INNER JOIN {$this->db->{JOOMLA_DB_NAMEQOUTE}('#__users')} AS b
				WHERE b.id=a.memberid
				AND a.groupid = {$this->db->Quote($uniqueID)}
				AND b.block = {$this->db->Quote('0')}";

		if ($onlyApproved)
		{
			$query .= ' AND a.approved=' . $this->db->Quote('1');
		}
		else
		{
			$query .= ' AND a.approved=' . $this->db->Quote('0');
		}

		if (!$loadAdmin)
		{
			$query .= ' AND a.permissions=' . $this->db->Quote('0');
		}

		$this->db->setQuery($query);
		$total = $this->db->loadResult();

		if (count($result) > 0)
		{
			$this->jsonarray['code']      = 200;
			$this->jsonarray['pageLimit'] = PAGE_MEMBER_LIMIT;
			$this->jsonarray['total']     = $total;
		}
		else
		{
			IJReq::setResponse(204);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$cAdmin          = (int) $groupModel->isAdmin($this->IJUserID, $uniqueID);
		$cCommunityAdmin = COwnerHelper::isCommunityAdmin($this->IJUserID);

		foreach ($result as $key => $value)
		{
			$usr                                                 = $this->jomHelper->getUserDetail($value->id);
			$this->jsonarray ['members'] [$key] ['user_id']      = $usr->id;
			$this->jsonarray ['members'] [$key] ['user_name']    = $usr->name;
			$this->jsonarray ['members'] [$key] ['user_avatar']  = $usr->avatar;
			$this->jsonarray ['members'] [$key] ['user_lat']     = $usr->latitude;
			$this->jsonarray ['members'] [$key] ['user_long']    = $usr->longitude;
			$this->jsonarray ['members'] [$key] ['user_online']  = $usr->online;
			$this->jsonarray ['members'] [$key] ['user_profile'] = $usr->profile;

			// Check if admin
			$isAdmin = (int) $groupModel->isAdmin($value->id, $uniqueID);

			$this->jsonarray ['members'] [$key] ['canRemove'] = intval((($cAdmin OR $cCommunityAdmin) AND $this->IJUserID != $usr->id));
			$this->jsonarray ['members'] [$key] ['canMember'] = intval((($cAdmin OR $cCommunityAdmin) AND $isAdmin));
			$this->jsonarray ['members'] [$key] ['canAdmin']  = intval((($cAdmin OR $cCommunityAdmin) AND !$isAdmin));
			$this->jsonarray ['members'] [$key] ['canBan']    = intval((($cAdmin OR $cCommunityAdmin) AND $this->IJUserID != $usr->id AND !$isAdmin));
		}

		return $this->jsonarray;
	}

	/**
	 * used to set / unset member as admin
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"setAdmin",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "memberID":"memberID",
	 *            "admin":"admin" // 0: to set as member, 1: to set as admin.
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function setAdmin()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$memberID = IJReq::getTaskData('memberID', 0, 'int');
		$admin    = IJReq::getTaskData('admin', 0, 'bool');

		$group  = JTable::getInstance('Group', 'CTable');
		$group->load($uniqueID);

		CFactory::load('helpers', 'owner');

		if ($group->ownerid != $this->my->id && !COwnerHelper::isCommunityAdmin($this->IJUserID))
		{
			IJReq::setResponse(706, JText::_('COM_COMMUNITY_PERMISSION_DENIED_WARNING'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
		else
		{
			$member  = JTable::getInstance('GroupMembers', 'CTable');

			$member->load($memberID, $group->id);
			$member->permissions = $admin;

			$member->store();
			$this->jsonarray['code'] = 200;

			return $this->jsonarray;
		}
	}

	/**
	 * used to accept / reject invitation to join group
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"invitation",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "type":"type" // 0: reject, 1: accept.
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function invitation()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$type     = IJReq::getTaskData('type', 0, 'bool');

		$table  = JTable::getInstance('GroupInvite', 'CTable');
		$table->load($uniqueID, $this->my->id);

		if (!$table->isOwner())
		{
			IJReq::setResponse(706, JText::_('COM_COMMUNITY_INVALID_ACCESS'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($type)
		{
			$this->_saveMember($uniqueID);
		}
		else
		{
			$table->delete();
		}

		$this->jsonarray['code'] = 200;

		return $this->jsonarray;
	}

	/**
	 * function for save memeber
	 *
	 * @param   integer  $groupID  group id
	 *
	 * @return  $member
	 */
	private function _saveMember($groupID)
	{
		$group  = JTable::getInstance('Group', 'CTable');
		$group->load($groupID);
		$params = $group->getParams();

		$member  = JTable::getInstance('GroupMembers', 'CTable');

		// Set the properties for the members table
		$member->groupid  = $group->id;
		$member->memberid = $this->my->id;

		CFactory::load('helpers', 'owner');

		// @rule: If approvals is required, set the approved status accordingly.
		$member->approved = ($group->approvals == COMMUNITY_PRIVATE_GROUP) ? '0' : 1;

		// @rule: Special users should be able to join the group regardless if it requires approval or not
		$member->approved = COwnerHelper::isCommunityAdmin() ? 1 : $member->approved;

		// @todo: need to set the privileges
		$member->permissions = '0';

		$member->store();
		$owner = CFactory::getUser($group->ownerid);

		require_once JPATH_ROOT . '/components/com_community/controllers/groups.php';
		$group_controller_obj = new CommunityGroupsController;

		// Trigger for onGroupJoin
		$group_controller_obj->triggerGroupEvents('onGroupJoin', $group, $this->my->id);

		// Test if member is approved, then we add logging to the activities.
		if ($member->approved)
		{
			$act          = new stdClass;
			$act->cmd     = 'group.join';
			$act->actor   = $this->my->id;
			$act->target  = 0;
			$act->title   = JText::sprintf('COM_COMMUNITY_GROUPS_GROUP_JOIN', '{group_url}', $group->name);
			$act->content = '';
			$act->app     = 'groups';
			$act->cid     = $group->id;

			$params = new CParameter('');
			$params->set('group_url', 'index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $group->id);

			// Add logging
			CFactory::load('libraries', 'activities');
			CActivityStream::add($act, $params->toString());

			// Add user points
			CFactory::load('libraries', 'userpoints');
			CUserPoints::assignPoint('group.join');

			// Store the group and update stats
			$group->updateStats();
			$group->store();
		}

		// Send push notification
		// Get user push notification params
		$query = "SELECT `jomsocial_params`,`device_token`,`device_type`
				FROM #__ijoomeradv_users
				WHERE `userid`={$group->ownerid}";
		$this->db->setQuery($query);
		$puser    = $this->db->loadObject();
		$ijparams = new CParameter($puser->jomsocial_params);

		// Change for id based push notification
		$usr     = $this->jomHelper->getUserDetail($this->my->id);
		$search  = array('{actor}', '{multiple}', '{actors}', '{/multiple}', '{single}', '{/single}');
		$replace = array($usr->name, '', '', '', '', '');

		if ($member->approved)
		{
			$message                                       = str_replace($search, $replace, JText::sprintf('COM_COMMUNITY_GROUPS_GROUP_JOIN', $group->getLink(), $group->name));
			$pushOptions['detail']['content_data']['id']   = $this->my->id;
			$pushOptions['detail']['content_data']['type'] = 'profile';
		}
		else
		{
			$groupdata['id']          = $group->id;
			$groupdata['title']       = $group->name;
			$groupdata['description'] = strip_tags($group->description);

			if ($this->config->get('groups_avatar_storage') == 'file')
			{
				$p_url = JURI::base();
			}
			else
			{
				$s3BucketPath = $this->config->get('storages3bucket');
				if (!empty($s3BucketPath))
					$p_url = 'http://' . $s3BucketPath . '.s3.amazonaws.com/';
				else
					$p_url = JURI::base();
			}

			$groupdata['avatar']      = ($group->avatar == "") ? JURI::base() . 'components/com_community/assets/group.png' : $p_url . $group->avatar;
			$groupdata['members']     = intval($group->membercount);
			$groupdata['walls']       = intval($group->wallcount);
			$groupdata['discussions'] = intval($group->discusscount);
			$message                  = str_replace($search, $replace, JText::sprintf('COM_COMMUNITY_GROUPS_REQUESTED_NOTIFICATION', $usr->name, $group->name));

			$pushOptions['detail']['content_data']         = $groupdata;
			$pushOptions['detail']['content_data']['type'] = 'group';
		}

		$pushOptions  = gzcompress(json_encode($pushOptions));
		$obj          = new stdClass;
		$obj->id      = null;
		$obj->detail  = $pushOptions;
		$obj->tocount = 1;
		$this->db->insertObject('#__ijoomeradv_push_notification_data', $obj, 'id');

		if ($obj->id)
		{
			$this->jsonarray['pushNotificationData']['id']         = $obj->id;
			$this->jsonarray['pushNotificationData']['to']         = $group->ownerid;
			$this->jsonarray['pushNotificationData']['message']    = $message;
			$this->jsonarray['pushNotificationData']['type']       = 'group';
			$this->jsonarray['pushNotificationData']['configtype'] = 'pushnotif_groups_member_join';
		}

		return $member;
	}

	/**
	 * used to get group friend list to invite
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"friendList",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "pageNO":"pageNO"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function friendList()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$pageNO   = IJReq::getTaskData('pageNO', 0, 'int');
		$limit    = PAGE_MEMBER_LIMIT;

		if (!$this->IJUserID)
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if (!$uniqueID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($pageNO == 0 || $pageNO == 1)
		{
			$startFrom = 0;
		}
		else
		{
			$startFrom = ($limit * ($pageNO - 1));
		}

		$groupsModel = CFactory::getModel('groups');
		$members     = $groupsModel->getInviteListByName('', $this->IJUserID, $uniqueID, $startFrom, $limit);

		if (count($members) > 0)
		{
			$this->jsonarray['code']      = 200;
			$this->jsonarray['pageLimit'] = PAGE_MEMBER_LIMIT;
			$this->jsonarray['total']     = $groupsModel->total;
		}
		else
		{
			IJReq::setResponse(204);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		foreach ($members as $key => $member)
		{
			$usr = $this->jomHelper->getUserDetail($member);

			$this->jsonarray['members'][$key]['user_id']     = $usr->id;
			$this->jsonarray['members'][$key]['user_name']   = $usr->name;
			$this->jsonarray['members'][$key]['user_avatar'] = $usr->avatar;
			$this->jsonarray['members'][$key]['isinvited']   = intval($groupsModel->isInvited($member, $uniqueID));
		}

		return $this->jsonarray;
	}

	/**
	 * used to invite friends to group
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"invite",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "friends":"friends", // comma sapareted memberid
	 *            "message":"message"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function invite()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$friends  = IJReq::getTaskData('friends');
		$message  = IJReq::getTaskData('message', '');
		$friends  = explode(",", $friends);

		if (empty($friends))
		{
			IJReq::setResponse(400, JText::_('COM_COMMUNITY_INVITE_NEED_AT_LEAST_1_FRIEND'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($this->my->id == 0)
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$group  = JTable::getInstance('Group', 'CTable');
		$group->load($uniqueID);

		// Check if the user is banned
		$isBanned = $group->isBanned($this->my->id);

		if ((!$group->isMember($this->my->id) || $isBanned) && !COwnerHelper::isCommunityAdmin($this->IJUserID))
		{
			IJReq::setResponse(706, JText::_('COM_COMMUNITY_ACCESS_FORBIDDEN'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// Send push notification
		foreach ($friends as $friend)
		{
			$groupInvite           = JTable::getInstance('GroupInvite', 'CTable');
			$groupInvite->groupid = $group->id;
			$groupInvite->userid  = $friend;
			$groupInvite->creator = $this->my->id;
			$groupInvite->store();

			// Get user push notification params
			$query = "SELECT `jomsocial_params`,`device_token`,`device_type`
					FROM #__ijoomeradv_users
					WHERE `userid`={$friend}";
			$this->db->setQuery($query);
			$puser    = $this->db->loadObject();
			$ijparams = new CParameter($puser->jomsocial_params);

			if ($ijparams->get('pushnotif_groups_invite') == 1 && $friend != $this->IJUserID && !empty($puser))
			{
				$usr     = $this->jomHelper->getUserDetail($this->IJUserID);
				$match   = array('{actor}', '{group}');
				$replace = array($usr->name, $group->name);
				$message = str_replace($match, $replace, JText::sprintf('COM_COMMUNITY_GROUPS_JOIN_INVITATION_MESSAGE'));

				$groupdata['id']          = $group->id;
				$groupdata['title']       = $group->name;
				$groupdata['description'] = strip_tags($group->description);

				if ($this->config->get('groups_avatar_storage') == 'file')
				{
					$p_url = JURI::base();
				}
				else
				{
					$s3BucketPath = $this->config->get('storages3bucket');
					if (!empty($s3BucketPath))
						$p_url = 'http://' . $s3BucketPath . '.s3.amazonaws.com/';
					else
						$p_url = JURI::base();
				}

				$groupdata['avatar']      = ($group->avatar == "") ? JURI::base() . 'components/com_community/assets/group.png' : $p_url . $group->avatar;
				$groupdata['members']     = intval($group->membercount);
				$groupdata['walls']       = intval($group->wallcount);
				$groupdata['discussions'] = intval($group->discusscount);

				// Change for id based push notification
				$pushOptions['detail']['content_data']         = $groupdata;
				$pushOptions['detail']['content_data']['type'] = 'group';
				$pushOptions                                   = gzcompress(json_encode($pushOptions));

				$obj          = new stdClass;
				$obj->id      = null;
				$obj->detail  = $pushOptions;
				$obj->tocount = 1;
				$this->db->insertObject('#__ijoomeradv_push_notification_data', $obj, 'id');

				if ($obj->id)
				{
					$this->jsonarray['pushNotificationData']['id']         = $obj->id;
					$this->jsonarray['pushNotificationData']['to']         = $friend;
					$this->jsonarray['pushNotificationData']['message']    = $message;
					$this->jsonarray['pushNotificationData']['type']       = 'group';
					$this->jsonarray['pushNotificationData']['configtype'] = 'pushnotif_groups_invite';
				}
			}
		}

		// Add notification
		CFactory::load('libraries', 'notification');

		$params = new CParameter('');
		$params->set('url', 'index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $group->id);
		$params->set('groupname', $group->name);
		$params->set('message', $inviteMessage);

		CNotificationLibrary::add('groups.invite', $this->my->id, $friends, JText::sprintf('COM_COMMUNITY_GROUPS_JOIN_INVITATION_MESSAGE', $group->name), '', 'groups.invite', $params);

		$this->jsonarray['code'] = 200;

		return $this->jsonarray;
	}

	/**
	 * used to get ban member list
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"banMembers",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "pageNO":"pageNO"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function banMembers()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$pageNO   = IJReq::getTaskData('pageNO', 0, 'int');
		$limit    = PAGE_MEMBER_LIMIT;

		if (!$uniqueID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($pageNO == 0 || $pageNO == 1)
		{
			$startFrom = 0;
		}
		else
		{
			$startFrom = ($limit * ($pageNO - 1));
		}

		$groupsModel = CFactory::getModel('groups');
		$groupsModel->setState('limitstart', $startFrom);
		$members = $groupsModel->getBannedMembers($uniqueID, $limit);

		if (count($members) > 0)
		{
			$this->jsonarray['code']      = 200;
			$this->jsonarray['pageLimit'] = $limit;
			$this->jsonarray['total']     = $groupsModel->_pagination->total;
		}
		else
		{
			IJReq::setResponse(204);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$cAdmin          = (int) $groupsModel->isAdmin($this->IJUserID, $uniqueID);
		$cCommunityAdmin = COwnerHelper::isCommunityAdmin($this->IJUserID);

		foreach ($members as $key => $value)
		{
			$usr = $this->jomHelper->getUserDetail($value->id);

			$this->jsonarray ['members'] [$key] ['user_id']      = $usr->id;
			$this->jsonarray ['members'] [$key] ['user_name']    = $usr->name;
			$this->jsonarray ['members'] [$key] ['user_avatar']  = $usr->avatar;
			$this->jsonarray ['members'] [$key] ['user_lat']     = $usr->latitude;
			$this->jsonarray ['members'] [$key] ['user_long']    = $usr->longitude;
			$this->jsonarray ['members'] [$key] ['user_online']  = $usr->online;
			$this->jsonarray ['members'] [$key] ['user_profile'] = $usr->profile;

			// Check if admin
			$isAdmin = (int) $groupsModel->isAdmin($value->id, $uniqueID);

			$this->jsonarray ['members'] [$key] ['canRemove'] = intval((($cAdmin OR $cCommunityAdmin) AND $this->IJUserID != $usr->id));
			$this->jsonarray ['members'] [$key] ['canMember'] = intval((($cAdmin OR $cCommunityAdmin) AND $isAdmin));
			$this->jsonarray ['members'] [$key] ['canAdmin']  = intval((($cAdmin OR $cCommunityAdmin) AND !$isAdmin));
			$this->jsonarray ['members'] [$key] ['canBan']    = intval((($cAdmin OR $cCommunityAdmin) AND $this->IJUserID != $usr->id AND !$isAdmin));
		}

		return $this->jsonarray;
	}

	/**
	 * used to get ban member list
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"ban",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "memberID":"memberID",
	 *            "ban":"ban" // 0: to unban, 1: to ban
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function ban()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$memberID = IJReq::getTaskData('memberID', 0, 'int');
		$ban      = IJReq::getTaskData('ban', 0, 'bool');

		if (!$memberID OR !$uniqueID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if (!COwnerHelper::isRegisteredUser())
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		CFactory::load('helpers', 'owner');

		$group  = JTable::getInstance('Group', 'CTable');
		$group->load($uniqueID);

		if ($group->ownerid != $this->my->id && !COwnerHelper::isCommunityAdmin($this->IJUserID))
		{
			IJReq::setResponse(706, JText::_('COM_COMMUNITY_PERMISSION_DENIED_WARNING'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
		else
		{
			$member  = JTable::getInstance('GroupMembers', 'CTable');
			$member->load($memberID, $group->id);

			if ($ban)
			{
				$member->permissions = COMMUNITY_GROUP_BANNED;
			}
			else
			{
				$member->permissions = COMMUNITY_GROUP_MEMBER;
			}

			$member->store();

			$group->updateStats();
			$group->store();

			$this->jsonarray['code'] = 200;

			return $this->jsonarray;
		}
	}

	/**
	 * used to remove member from the group
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"removeMember",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "memberID":"memberID"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function removeMember()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$memberID = IJReq::getTaskData('memberID', 0, 'int');

		if (!COwnerHelper::isRegisteredUser())
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$groupsModel  = CFactory::getModel('groups');
		$group        = JTable::getInstance('Group', 'CTable');
		$group->load($uniqueID);

		CFactory::load('helpers', 'owner');

		if ($group->ownerid != $this->my->id && !COwnerHelper::isCommunityAdmin($this->IJUserID))
		{
			IJReq::setResponse(706, JText::_('COM_COMMUNITY_PERMISSION_DENIED_WARNING'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($group->ownerid == $memberID)
		{
			IJReq::setResponse(706, JText::_('COM_COMMUNITY_GROUPS_MEMBERS_DELETE_DENIED'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
		else
		{
			$groupMember  = JTable::getInstance('GroupMembers', 'CTable');
			$groupMember->load($memberID, $uniqueID);

			$data = new stdClass;

			$data->groupid  = $uniqueID;
			$data->memberid = $memberID;

			$groupsModel->removeMember($data);

			// Add user points
			CFactory::load('libraries', 'userpoints');
			CUserPoints::assignPoint('group.member.remove', $memberID);

			// Trigger for onGroupLeave
			require_once JPATH_ROOT . '/components/com_community/controllers/groups.php';
			$group_controller_obj = new CommunityGroupsController;
			$group_controller_obj->triggerGroupEvents('onGroupLeave', $group, $memberID);
		}

		// Store the group and update the data
		$group->updateStats();
		$group->store();
		$this->jsonarray['code'] = 200;

		return $this->jsonarray;
	}

	/**
	 * used to add wall or comment
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"group",
	 *        "extTask":"wall",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "message":"message",
	 *            "comment":"comment" // boolean 0/1, if 1 comment will be add.
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function addWall()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$comment  = IJReq::getTaskData('comment', 0, 'bool');
		$message  = strip_tags(IJReq::getTaskData('message', ''));

		$audiofileupload = $this->jomHelper->uploadAudioFile();

		if ($audiofileupload)
		{
			$message = $message . $audiofileupload['voicetext'];
		}

		if (empty($message))
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if (empty($this->my->id) || $this->my->id == 0)
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($comment)
		{
			$query = "SELECT comment_id,comment_type
					FROM `#__community_activities`
					WHERE id={$uniqueID}";
			$this->db->setQuery($query);
			$data = $this->db->loadObject();

			$table  = JTable::getInstance('Wall', 'CTable');

			$table->contentid = $data->comment_id;
			$table->type      = $data->comment_type;
			$table->comment   = $message;
			$table->post_by   = $this->my->id;

			$table->store();

			$this->jsonarray['code'] = 200;

			return $this->jsonarray;
		}
		else
		{
			$groupModel = CFactory::getModel('groups');
			$group       = JTable::getInstance('Group', 'CTable');
			$group->load($uniqueID);

			if (!$this->my->authorise('community.save', 'groups.wall.' . $uniqueID, $group))
			{
				IJReq::setResponse(706, JText::_('COM_COMMUNITY_ACCESS_FORBIDDEN'));
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}

			CFactory::load('helpers', 'owner');
			CFactory::load('libraries', 'wall');
			CFactory::load('helpers', 'url');
			CFactory::load('libraries', 'activities');

			$isAdmin = $groupModel->isAdmin($this->my->id, $group->id);

			// Store event will update all stats count data
			if ($this->config->get('antispam_akismet_walls'))
			{
				CFactory::load('libraries', 'spamfilter');

				$filter = CSpamFilter::getFilter();
				$filter->setAuthor($this->my->getDisplayName());
				$filter->setMessage($message);
				$filter->setEmail($this->my->email);
				$filter->setURL(CRoute::_('index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $uniqueID));
				$filter->setType('message');
				$filter->setIP($_SERVER['REMOTE_ADDR']);

				if ($filter->isSpam())
				{
					IJReq::setResponse(705, JText::_('COM_COMMUNITY_WALLS_MARKED_SPAM'));
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}
			}

			$act          = new stdClass;
			$act->cmd     = 'group.wall.create';
			$act->actor   = $this->my->id;
			$act->target  = 0;
			$act->title   = $message;
			$act->content = '';
			$act->app     = 'groups.wall';
			$act->cid     = $group->id;
			$act->groupid = $group->id;

			// Allow comments
			$act->comment_type = 'groups.wall';
			$act->comment_id   = CActivities::COMMENT_SELF;

			// Allow Like
			$act->like_type = 'groups.wall';
			$act->like_id   = CActivities::COMMENT_SELF;
			CActivityStream::add($act);

			// @rule: Add user points
			CFactory::load('libraries', 'userpoints');
			CUserPoints::assignPoint('group.wall.create');

			// @rule: Send email notification to members
			$groupParams = $group->getParams();

			if ($groupParams->get('wallnotification') == '1')
			{
				CFactory::load('models', 'groups');
				$model   = CFactory::getModel('groups');
				$members = $model->getMembers($uniqueID, null);
				$admins  = $model->getAdmins($uniqueID, null);

				$membersArray = array();

				foreach ($members as $row)
				{
					if ($this->my->id != $row->id)
					{
						$membersArray[] = $row->id;
					}
				}

				foreach ($admins as $row)
				{
					if ($this->my->id != $row->id)
					{
						$membersArray[] = $row->id;
					}
				}

				unset($members);
				unset($admins);

				// Add notification
				CFactory::load('libraries', 'notification');

				$params = new CParameter('');
				$params->set('url', 'index.php?option=com_community&view=groups&task=viewgroup&groupid=' . $uniqueID);
				$params->set('group', $group->name);
				$params->set('message', $message);
				CNotificationLibrary::add('groups.wall.create', $this->my->id, $membersArray, JText::sprintf('COM_COMMUNITY_NEW_WALL_POST_NOTIFICATION_EMAIL_SUBJECT', $this->my->getDisplayName(), $group->name), '', 'groups.wall', $params);
			}

			// Send push notification
			$model   = CFactory::getModel('groups');
			$members = $model->getMembers($uniqueID, null);
			$admins  = $model->getAdmins($uniqueID, null);

			$membersArray = array();

			foreach ($members as $row)
			{
				$membersArray[] = $row->id;
			}

			foreach ($admins as $row)
			{
				$membersArray[] = $row->id;
			}

			unset($members);
			unset($admins);

			$membersArray = implode(',', $membersArray);

			// Get user push notification params and user device token and device type
			$query = "SELECT userid,`jomsocial_params`,`device_token`,`device_type`
					FROM #__ijoomeradv_users
					WHERE `userid` IN ({$membersArray})";
			$this->db->setQuery($query);
			$puserlist = $this->db->loadObjectList();

			// Send pushnotification data
			$usr     = $this->jomHelper->getUserDetail($this->IJUserID);
			$match   = array('{actor}', '{group}');
			$replace = array($usr->name, $group->name);
			$message = str_replace($match, $replace, JText::sprintf('COM_COMMUNITY_NEW_WALL_POST_NOTIFICATION_EMAIL_SUBJECT'));

			foreach ($puserlist as $puser)
			{
				$groupdata['id']          = $group->id;
				$groupdata['title']       = $group->name;
				$groupdata['description'] = strip_tags($group->description);

				if ($this->config->get('groups_avatar_storage') == 'file')
				{
					$p_url = JURI::base();
				}
				else
				{
					$s3BucketPath = $this->config->get('storages3bucket');
					if (!empty($s3BucketPath))
						$p_url = 'http://' . $s3BucketPath . '.s3.amazonaws.com/';
					else
						$p_url = JURI::base();
				}

				$groupdata['avatar']      = ($group->avatar == "") ? JURI::base() . 'components/com_community/assets/group.png' : $p_url . $group->avatar;
				$groupdata['members']     = intval($group->membercount);
				$groupdata['walls']       = intval($group->wallcount);
				$groupdata['discussions'] = intval($group->discusscount);

				// Change for id based push notification
				$pushOptions                                   = array();
				$pushOptions['detail']['content_data']         = $groupdata;
				$pushOptions['detail']['content_data']['type'] = 'group';
				$pushOptions                                   = gzcompress(json_encode($pushOptions));

				$obj          = new stdClass;
				$obj->id      = null;
				$obj->detail  = $pushOptions;
				$obj->tocount = 1;
				$this->db->insertObject('#__ijoomeradv_push_notification_data', $obj, 'id');

				if ($obj->id)
				{
					$this->jsonarray['pushNotificationData']['multiid'][$puser->userid] = $obj->id;
				}
			}

			$this->jsonarray['pushNotificationData']['id']         = 0;
			$this->jsonarray['pushNotificationData']['to']         = $membersArray;
			$this->jsonarray['pushNotificationData']['message']    = $message;
			$this->jsonarray['pushNotificationData']['type']       = 'group';
			$this->jsonarray['pushNotificationData']['configtype'] = 'pushnotif_groups_wall_create';

			$this->jsonarray['code'] = 200;

			return $this->jsonarray;
		}
	}
}
