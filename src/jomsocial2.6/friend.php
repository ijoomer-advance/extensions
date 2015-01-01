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
 * class for friend
 *
 * @package     IJoomer.Extensions
 * @subpackage  jomsocial2.6
 * @since       1.0
 */
class Friend
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
	 * used to fetch all the member
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"friend",
	 *        "extTask":"members",
	 *        "taskData":{
	 *            "pageNO":"pageNO"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function members()
	{
		$pageNO = IJReq::getTaskData('pageNO', 0, 'int');

		if ($pageNO == 0 || $pageNO == 1)
		{
			$startFrom = 0;
		}
		else
		{
			$startFrom = (PAGE_MEMBER_LIMIT * ($pageNO - 1));
		}

		$searchModel  = CFactory::getModel('search');
		$searchModel->setState('limit', PAGE_MEMBER_LIMIT);
		$searchModel->setState('limitstart', $startFrom);

		$results = $searchModel->getPeople($sorted = 'alphabetical', $filter = 'all');

		if (count($results) <= 0)
		{
			IJReq::setResponse(204);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
		else
		{
			$this->jsonarray['code']      = 200;
			$this->jsonarray['pageLimit'] = PAGE_MEMBER_LIMIT;
			$this->jsonarray['total']     = $searchModel->get('_pagination')->get('total');
		}

		foreach ($results as $key => $result)
		{
			$usr                                             = $this->jomHelper->getUserDetail($result->_userid);
			$this->jsonarray['member'][$key]['user_id']      = $usr->id;
			$this->jsonarray['member'][$key]['user_name']    = $usr->name;
			$this->jsonarray['member'][$key]['user_avatar']  = $usr->avatar;
			$this->jsonarray['member'][$key]['user_lat']     = $usr->latitude;
			$this->jsonarray['member'][$key]['user_long']    = $usr->longitude;
			$this->jsonarray['member'][$key]['user_online']  = $usr->online;
			$this->jsonarray['member'][$key]['user_profile'] = $usr->profile;
		}

		for ($i = 0, $inc = count($this->jsonarray['member']); $i < $inc; $i++)
		{
			for ($j = $i + 1, $inc = count($this->jsonarray['member']); $j < $inc; $j++)
			{
				$firstRecord  = $this->jsonarray['member'][$i];
				$secondRecord = $this->jsonarray['member'][$j];

				if ($firstRecord['online'] < $secondRecord['online'])
				{
					$this->jsonarray['member'][$i] = $secondRecord;
					$this->jsonarray['member'][$j] = $firstRecord;
				}
			}
		}

		return $this->jsonarray;
	}

	/**
	 * used to fetch all the friends
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"friend",
	 *        "extTask":"friends",
	 *        "taskData":{
	 *            "userID":"userID",
	 *            "pageNO":"pageNO"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function friends()
	{
		$pageNO  = IJReq::getTaskData('pageNO', 0, 'int');
		$userID  = IJReq::getTaskData('userID', $this->IJUserID, 'int');
		$keyword = IJReq::getTaskData('keyword', '');

		if (!$this->IJUserID)
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$access_limit = $this->jomHelper->getUserAccess($this->IJUserID, $userID);

		$query = "SELECT params
					FROM #__community_users
					WHERE userid=" . $userID;
		$this->db->setQuery($query);
		$params = new CParameter($this->db->loadResult());

		if ($access_limit < $params->get('privacyFriendsView'))
		{
			IJReq::setResponse(706);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($pageNO == 0 || $pageNO == 1)
		{
			$startFrom = 0;
		}
		else
		{
			$startFrom = (PAGE_MEMBER_LIMIT * ($pageNO - 1));
		}

		$friendsModel  = CFactory::getModel('friends');
		$friendsModel->setState('limit', PAGE_MEMBER_LIMIT);
		$friendsModel->setState('limitstart', $startFrom);

		$results = $friendsModel->getFriends($userID, 'name', true, $keyword);

		if (count($results) <= 0)
		{
			IJReq::setResponse(204);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
		else
		{
			$this->jsonarray['code']      = 200;
			$this->jsonarray['pageLimit'] = PAGE_MEMBER_LIMIT;
			$this->jsonarray['total']     = $friendsModel->getFriendsCount($userID);
		}

		foreach ($results as $key => $result)
		{
			$usr                                             = $this->jomHelper->getUserDetail($result->_userid, $userID);
			$this->jsonarray['member'][$key]['user_id']      = $usr->id;
			$this->jsonarray['member'][$key]['user_name']    = $usr->name;
			$this->jsonarray['member'][$key]['user_avatar']  = $usr->avatar;
			$this->jsonarray['member'][$key]['user_lat']     = $usr->latitude;
			$this->jsonarray['member'][$key]['user_long']    = $usr->longitude;
			$this->jsonarray['member'][$key]['user_online']  = $usr->online;
			$this->jsonarray['member'][$key]['user_profile'] = $usr->profile;
		}

		for ($i = 0, $inc = count($this->jsonarray['member']); $i < $inc; $i++)
		{
			for ($j = $i + 1, $inc = count($this->jsonarray['member']); $j < $inc; $j++)
			{
				$firstRecord  = $this->jsonarray['member'][$i];
				$secondRecord = $this->jsonarray['member'][$j];

				if ($firstRecord['user_online'] < $secondRecord['user_online'])
				{
					$this->jsonarray['member'][$i] = $secondRecord;
					$this->jsonarray['member'][$j] = $firstRecord;
				}
			}
		}

		return $this->jsonarray;
	}

	/**
	 * used to add a friend
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"friend",
	 *        "extTask":"addFriend",
	 *        "taskData":{
	 *            "memberID":"memberID",
	 *            "message":"message"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function addFriend()
	{
		if ($this->IJUserID == 0)
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// Get friend model
		$model     = CFactory::getModel('friends');

		// Get friend id for friend request
		$memberID = IJReq::getTaskData('memberID', 0, 'int');

		// Get message to sed it to user
		$message  = IJReq::getTaskData('message');

		if (!$memberID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// Add friend function call
		$model->addFriend($memberID, $this->IJUserID, $message);

		// Trigger for onFriendRequest
		$eventObject                 = new stdClass;
		$eventObject->profileOwnerId = $my->id;
		$eventObject->friendId       = $memberID;
		$this->triggerFriendEvents('onFriendRequest', $eventObject);

		$model->updateFriendCount($this->IJUserID);
		$model->updateFriendCount($memberID);

		// Get user push notification params
		$query = "SELECT `jomsocial_params`,`device_token`,`device_type`
				FROM #__ijoomeradv_users
				WHERE `userid`={$memberID}";
		$this->db->setQuery($query);
		$puser    = $this->db->loadObject();
		$ijparams = new CParameter($puser->jomsocial_params);

		// Change for id based push notification
		$pushOptions                                 = array();
		$pushOptions['detail']['content_data']['id'] = $this->IJUserID;
		$pushOptions                                 = gzcompress(json_encode($pushOptions));

		$usr          = $this->jomHelper->getUserDetail($this->IJUserID);
		$obj          = new stdClass;
		$obj->id      = null;
		$obj->detail  = $pushOptions;
		$obj->tocount = 1;
		$this->db->insertObject('#__ijoomeradv_push_notification_data', $obj, 'id');

		if ($obj->id)
		{
			$this->jsonarray['pushNotificationData']['id']         = $obj->id;
			$this->jsonarray['pushNotificationData']['to']         = $memberID;
			$this->jsonarray['pushNotificationData']['message']    = $usr->name . ' : ' . $message;
			$this->jsonarray['pushNotificationData']['type']       = 'profile';
			$this->jsonarray['pushNotificationData']['configtype'] = 'pushnotif_friends_create_connection';
		}

		$this->jsonarray['code'] = 200;

		return $this->jsonarray;
	}

	/**
	 * function for trigger Friend Events
	 *
	 * @param   string  $eventName  name of events
	 * @param   [type]  &$args      arguments
	 * @param   [type]  $target     target
	 *
	 * @return  boolean true on success or false on failure
	 */
	private function triggerFriendEvents($eventName, &$args, $target = null)
	{
		require_once JPATH_SITE . '/components/com_community/libraries/apps.php';
		$appsLib  = CAppPlugins::getInstance();
		$appsLib->loadApplications();

		$params   = array();
		$params[] = $args;

		if (!is_null($target))
			$params[] = $target;

		$appsLib->triggerEvent($eventName, $params);

		return true;
	}

	/**
	 * used to add a friend
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"friend",
	 *        "extTask":"removeFriend",
	 *        "taskData":{
	 *            "memberID":"memberID"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function removeFriend()
	{
		$memberID = IJReq::getTaskData('memberID', 0, 'int');

		if (!$memberID)
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($this->IJUserID == 0)
		{
			IJReq::setResponse(704);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($this->delete($memberID))
		{
			$this->jsonarray['code'] = 200;

			return $this->jsonarray;
		}
		else
		{
			IJReq::setResponse(500, JText::_('COM_COMMUNITY_FRIENDS_REMOVING_FRIEND_ERROR'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}
	}

	/**
	 * function for delete
	 *
	 * @param   integer  $id  id
	 *
	 * @return  boolean  true on success or false on failure
	 */
	private function delete($id)
	{
		$friend = CFactory::getUser($id);

		if (empty($this->my->id) || empty($friend->id))
			return false;

		CFactory::load('helpers', 'friends');
		$isFriend = $this->my->isFriendWith($friend->id);

		if (!$isFriend)
			return true;

		$model = CFactory::getModel('friends');

		if (!$model->deleteFriend($this->my->id, $friend->id))
			return false;

		// Substract the friend count
		$model->updateFriendCount($this->my->id);
		$model->updateFriendCount($friend->id);

		// Add user points
		// We deduct points to both parties
		CFactory::load('libraries', 'userpoints');
		CUserPoints::assignPoint('friends.remove');
		CUserPoints::assignPoint('friends.remove', $friend->id);

		// Trigger for onFriendRemove
		$eventObject                 = new stdClass;
		$eventObject->profileOwnerId = $my->id;
		$eventObject->friendId       = $friend->id;
		$this->triggerFriendEvents('onFriendRemove', $eventObject);

		return true;
	}

	/**
	 * used to approve friend request
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"friend",
	 *        "extTask":"approveRequest",
	 *        "taskData":{
	 *            "connectionID":"connectionID"
	 *        }
	 *    }
	 * @return array  jsonarray
	 */
	public function approveRequest()
	{
		$connectionId = IJReq::getTaskData('connectionID');
		$friendsModel  = CFactory::getModel('friends');

		if ($friendsModel->isMyRequest($connectionId, $this->IJUserID))
		{
			$connected = $friendsModel->approveRequest($connectionId);

			if ($connected)
			{
				$act          = new stdClass;
				$act->cmd     = 'friends.request.approve';
				$act->actor   = $connected[0];
				$act->target  = $connected[1];
				$act->title   = JText::_('COM_COMMUNITY_ACTIVITY_FRIENDS_NOW');
				$act->content = '';
				$act->app     = 'friends';
				$act->cid     = 0;

				CFactory::load('libraries', 'activities');
				CActivityStream::add($act);

				// Add user points - give points to both party
				CFactory::load('libraries', 'userpoints');
				CUserPoints::assignPoint('friends.request.approve');

				$friendId  = ($connected[0] == $this->IJUserID) ? $connected[1] : $connected[0];
				$friend    = CFactory::getUser($friendId);
				$friendUrl = CRoute::_('index.php?option=com_community&view=profile&userid=' . $friendId);
				CUserPoints::assignPoint('friends.request.approve', $friendId);

				// Need to both user's friend list
				$friendsModel->updateFriendCount($this->IJUserID);
				$friendsModel->updateFriendCount($friendId);

				CFactory::load('libraries', 'notification');

				$params = new CParameter('');
				$params->set('url', 'index.php?option=com_community&view=profile&userid=' . $this->IJUserID);
				CNotificationLibrary::add('etype_friends_create_connection', $this->IJUserID, $friend->id, JText::sprintf('COM_COMMUNITY_FRIEND_REQUEST_APPROVED', $this->my->getDisplayName()), '', 'friends.approve', $params);

				// Get user push notification params and user device token and device type
				$query = "SELECT `jomsocial_params`,`device_token`,`device_type`
						FROM #__ijoomeradv_users
						WHERE `userid`={$friendId}";
				$this->db->setQuery($query);
				$puser    = $this->db->loadObject();
				$ijparams = new CParameter($puser->jomsocial_params);

				// Change for id based push notification
				$pushOptions['detail'] = array();
				$pushOptions           = gzcompress(json_encode($pushOptions));

				$usr          = $this->jomHelper->getUserDetail($this->IJUserID);
				$obj          = new stdClass;
				$obj->id      = null;
				$obj->detail  = $pushOptions;
				$obj->tocount = 1;
				$this->db->insertObject('#__ijoomeradv_push_notification_data', $obj, 'id');

				if ($obj->id)
				{
					$this->jsonarray['pushNotificationData']['id']         = $obj->id;
					$this->jsonarray['pushNotificationData']['to']         = $friendId;
					$this->jsonarray['pushNotificationData']['message']    = str_replace('{friend}', $usr->name, JText::sprintf('COM_COMMUNITY_FRIEND_REQUEST_APPROVED'));
					$this->jsonarray['pushNotificationData']['type']       = 'friend';
					$this->jsonarray['pushNotificationData']['configtype'] = 'pushnotif_friends_request_connection';
				}

				// Trigger for onFriendApprove
				require_once JPATH_ROOT . '/components/com_community/controllers/controller.php';
				require_once JPATH_ROOT . '/components/com_community/controllers/friends.php';
				$eventObject                 = new stdClass;
				$eventObject->profileOwnerId = $this->IJUserID;
				$eventObject->friendId       = $friendId;
				CommunityFriendsController::triggerFriendEvents('onFriendApprove', $eventObject);
				unset($eventObject);
			}
		}

		$this->jsonarray['code'] = 200;
		$this->jsonarray['notification']['friendNotification'] -= 1;

		return $this->jsonarray;
	}

	/**
	 * used to reject friend request
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"friend",
	 *        "extTask":"rejectRequest",
	 *        "taskData":{
	 *            "connectionID":"connectionID"
	 *        }
	 *    }
	 * @return array  jsonarray
	 */
	public function rejectRequest()
	{
		$requestId    = IJReq::getTaskData('connectionID');
		$friendsModel  = CFactory::getModel('friends');

		if ($friendsModel->isMyRequest($requestId, $this->IJUserID))
		{
			$pendingInfo = $friendsModel->getPendingUserId($requestId);

			if ($friendsModel->rejectRequest($requestId))
			{
				// Trigger for onFriendReject
				require_once JPATH_ROOT . '/components/com_community/controllers/friends.php';
				$eventObject                 = new stdClass;
				$eventObject->profileOwnerId = $this->IJUserID;
				$eventObject->friendId       = $pendingInfo->connect_from;
				CommunityFriendsController::triggerFriendEvents('onFriendReject', $eventObject);
				unset($eventObject);
			}
		}

		$this->jsonarray['code'] = 200;
		$this->jsonarray['notification']['friendNotification'] -= 1;

		return $this->jsonarray;
	}

	/**
	 * used to search friend/member
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"friend",
	 *        "extTask":"search",
	 *        "taskData":{
	 *            "query":"query",
	 *            "pageNO":"pageno"
	 *        }
	 *    }
	 * @return array/boolean  jsonarray and true on success or false on failure
	 */
	public function search()
	{
		$qstring = IJReq::getTaskData('query', '');
		$pageNO  = IJReq::getTaskData('pageNO', 0, 'int');

		if ($pageNO == 0 || $pageNO == 1)
		{
			$startFrom = 0;
		}
		else
		{
			$startFrom = (PAGE_MEMBER_LIMIT * ($pageNO - 1));
		}

		$filter = array();
		$strict = true;
		$regex  = $strict ?
			'/^([.0-9a-z_-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,4})$/i' :
			'/^([*+!.&#$Š\'\\%\/0-9a-z^_`{}=?~:-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,4})$/i';

		// Build where condition
		$filterField = array();

		if (isset($qstring))
		{
			switch ($this->config->get('displayname'))
			{
				case 'name' :
					$field = 'name';
					break;
				default :
					$field = 'username';
					break;
			}

			$filter[] = "(UCASE(`{$field}`) like UCASE({$this->db->Quote("%{$qstring}%")}))";
		}

		$finalResult = array();
		$total       = 0;
		$avatarOnly  = false;

		if (count($filter) > 0 || count($filterField > 0))
		{
			$basicResult = null;

			if (!empty($filter) && count($filter) > 0)
			{
				$query = "SELECT distinct b.`id`
						FROM #__users b";

				if ($avatarOnly)
				{
					$query .= "	INNER JOIN #__community_users AS c ON b.`id`=c.`userid`
								AND c.`thumb` != {$this->db->Quote('components/com_community/assets/default_thumb.jpg')}";
				}

				$query .= " WHERE b.block = 0 AND " . implode(' AND ', $filter);
				$queryCnt = "SELECT COUNT(1)
							FROM ({$query}) AS z";
				$this->db->setQuery($queryCnt);
				$total = $this->db->loadResult();

				$query .= " LIMIT {$startFrom}," . PAGE_MEMBER_LIMIT;
				$this->db->setQuery($query);
				$finalResult = $this->db->loadResultArray();

				if ($this->db->getErrorNum())
				{
					IJReq::setResponse(500);
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}
			}

			if (count($finalResult) > 0)
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

			$id    = implode(",", $finalResult);
			$where = array("`id` IN (" . $id . ")");
			$datas = $this->getFiltered($where);

			if (!$datas)
			{
				IJReq::setResponse(204);
				IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

				return false;
			}

			CFactory::setActiveProfile();

			foreach ($datas as $key => $data)
			{
				$usr = $this->jomHelper->getUserDetail($data->id);

				$this->jsonarray['member'][$key]['user_id']      = $usr->id;
				$this->jsonarray['member'][$key]['user_name']    = $usr->name;
				$this->jsonarray['member'][$key]['user_avatar']  = $usr->avatar;
				$this->jsonarray['member'][$key]['user_lat']     = $usr->latitude;
				$this->jsonarray['member'][$key]['user_long']    = $usr->longitude;
				$this->jsonarray['member'][$key]['user_online']  = $usr->online;
				$this->jsonarray['member'][$key]['user_profile'] = $usr->profile;
			}

			for ($i = 0; $i < $inc; $i++)
			{
				for ($j = $i + 1; $j < $inc; $j++)
				{
					$firstRecord  = $this->jsonarray['member'][$i];

					$secondRecord = $this->jsonarray['member'][$j];

					if ($firstRecord['online'] < $secondRecord['online'])
					{
						$this->jsonarray['member'][$i] = $secondRecord;
						$this->jsonarray['member'][$j] = $firstRecord;
					}
				}
			}

			return $this->jsonarray;
		}
	}

	/**
	 * function for get Filtered
	 *
	 * @param   array  $wheres  wheres
	 *
	 * @return  mixed   friend data object on success, false on failure.
	 */
	private function getFiltered($wheres = array())
	{
		$wheres[] = 'block = 0';

		switch ($this->config->get('displayname'))
		{
			case 'name' :
				$field = 'name';
				break;
			default :
				$field = 'username';
				break;
		}

		$query = "SELECT id
				FROM #__users
				WHERE " . implode(' AND ', $wheres) . "
				ORDER BY {$field} ASC";
		$this->db->setQuery($query);

		if ($this->db->getErrorNum())
		{
			// Set the error code and return false
			IJReq::setResponse(500);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		$result = $this->db->loadObjectList();

		return $result;
	}
}
