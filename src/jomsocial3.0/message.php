<?php
/**
 * @package     IJoomer.Extensions
 * @subpackage  jomsocial3.0
 *
 * @copyright   Copyright (C) 2010 - 2015 Tailored Solutions PVT. Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;
jimport('joomla.application.component.model');
require_once JPATH_ROOT . '/components/com_community/models/models.php';

/**
 * class for message
 *
 * @package     IJoomer.Extensions
 * @subpackage  jomsocial3.0
 * @since       1.0
 */
class Message
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
	 * construct function
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
	 * uses    get user and subject vise messages
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"message",
	 *        "extTask":"conversation",
	 *        "taskData":{
	 *            "pageNO":"pageNO"
	 *        }
	 *    }
	 * @return array/boolean  true on success and false on failure and Jsonarray
	 */
	public function conversation()
	{
		$pageNO = IJReq::getTaskData('pageNO', 0, 'int');
		$limit  = PAGE_MESSAGE_LIMIT;

		if ($pageNO == 0 || $pageNO == 1)
		{
			$startFrom = 0;
		}
		else
		{
			$startFrom = ($limit * ($pageNO - 1));
		}

		$query = "SELECT cmr.*, IF(cmr.`msg_from`={$this->IJUserID}, '1', '0') as type, cm.`subject`,
				(SELECT max(cm1.`posted_on`) FROM #__community_msg as cm1 WHERE cm1.`parent`=cm.`id`) as date,
				(SELECT max(cmr1.`is_read`) FROM #__community_msg_recepient as cmr1 WHERE cmr1.`msg_parent`=cmr.`msg_id`) as rids
				FROM #__community_msg_recepient as cmr
				LEFT JOIN #__community_msg as cm on cmr.`msg_id`=cm.`id`
				WHERE (cmr.`msg_from` = {$this->IJUserID} OR cmr.`to` = {$this->IJUserID})
				AND cmr.`msg_id`=cmr.`msg_parent`
				AND cmr.`deleted`=0
				AND cm.`deleted`=0
				ORDER BY cmr.`msg_id`,cm.`posted_on` DESC, cmr.`is_read` ASC
				LIMIT {$startFrom}, {$limit}";
		$this->db->setQuery($query);
		$results = $this->db->loadObjectList();

		$query = "SELECT count(cmr.`msg_id`)
				FROM #__community_msg_recepient as cmr
				LEFT JOIN #__community_msg as cm on cmr.`msg_id`=cm.`id`
				WHERE (cmr.`msg_from` = {$this->IJUserID} OR cmr.`to` = {$this->IJUserID})
				AND cmr.`msg_id`=cmr.`msg_parent`
				AND cmr.`deleted`=0
				AND cm.`deleted`=0
				ORDER BY cmr.`msg_id`,cm.`posted_on` DESC, cmr.`is_read` ASC";
		$this->db->setQuery($query);
		$total = $this->db->loadResult();

		if (count($results) > 0)
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

		foreach ($results as $key => $value)
		{
			$this->jsonarray['messages'][$key]['id']           = $value->msg_id;
			$this->jsonarray['messages'][$key]['subject']      = $value->subject;
			$format                                            = JText::_('COM_COMMUNITY_DATE_FORMAT_LC2_24H');
			$timezone                                          = JFactory::getConfig()->get('offset');
			$dtz                                               = new DateTimeZone($timezone);
			$dt                                                = new DateTime("now", $dtz);
			$offset                                            = timezone_offset_get($dtz, $dt) / 3600;
			$date                                              = CTimeHelper::getFormattedUTC($value->date, $offset);
			$date                                              = CTimeHelper::getFormattedTime($date, $format);
			$this->jsonarray['messages'][$key]['date']         = $date;
			$this->jsonarray['messages'][$key]['outgoing']     = $value->type;
			$this->jsonarray['messages'][$key]['read']         = $value->rids;
			$usr                                               = ($value->type) ? $this->jomHelper->getUserDetail($value->to) : $this->jomHelper->getUserDetail($value->msg_from);
			$this->jsonarray['messages'][$key]['user_id']      = $usr->id;
			$this->jsonarray['messages'][$key]['user_name']    = $usr->name;
			$this->jsonarray['messages'][$key]['user_avatar']  = $usr->avatar;
			$this->jsonarray['messages'][$key]['user_profile'] = $usr->profile;
		}

		return $this->jsonarray;
	}

	/**
	 * uses    to get message detail as conversation
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"message",
	 *        "extTask":"detail",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "userID":"userID",
	 *            "pageNO":"pageNO"
	 *        }
	 *    }
	 * @return array/boolean  true on success and false on failure and Jsonarray
	 */
	public function detail()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$userID   = IJReq::getTaskData('userID', 0, 'int');
		$pageNO   = IJReq::getTaskData('pageNO', 0, 'int');
		$limit    = PAGE_MESSAGE_LIMIT;

		if ($pageNO == 0 || $pageNO == 1)
		{
			$startFrom = 0;
		}
		else
		{
			$startFrom = ($limit * ($pageNO - 1));
		}

		$query = "SELECT DISTINCT cmr.*, IF(cmr.`msg_from`={$this->IJUserID}, '1', '0') as type, cm.`body`, cm.`posted_on`
				FROM #__community_msg_recepient as cmr
				LEFT JOIN #__community_msg as cm on cmr.`msg_id`=cm.`id`
				WHERE cmr.`msg_parent`={$uniqueID}
				AND ((cmr.`msg_from`={$this->IJUserID} AND cmr.`to`={$userID}) OR (cmr.`msg_from`={$userID} AND cmr.`to`={$this->IJUserID}))
				/*AND cmr.`deleted`=0*/
				AND cm.`deleted`= 0
				ORDER BY cm.`posted_on` DESC
				LIMIT {$startFrom}, {$limit}";
		$this->db->setQuery($query);
		$results = $this->db->loadObjectList();

		$query = "SELECT count(cmr.`msg_id`)
				FROM #__community_msg_recepient as cmr
				LEFT JOIN #__community_msg as cm on cmr.`msg_id`=cm.`id`
				WHERE cmr.`msg_parent`={$uniqueID}
				AND ((cmr.`msg_from`={$this->IJUserID} AND cmr.`to`={$userID}) OR (cmr.`msg_from`={$userID} AND cmr.`to`={$this->IJUserID}))
				/*AND cmr.`deleted`=0 */
				AND cm.`deleted`=0
				ORDER BY cm.`posted_on` DESC";
		$this->db->setQuery($query);
		$total = $this->db->loadResult();

		if (count($results) > 0)
		{
			$this->jsonarray['code']      = 200;
			$this->jsonarray['pageLimit'] = $limit;
			$this->jsonarray['total']     = $total;
			$results                      = array_reverse($results);
		}
		else
		{
			IJReq::setResponse(204);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		foreach ($results as $key => $value)
		{
			$ids[]                                   = $value->msg_id;
			$this->jsonarray['messages'][$key]['id'] = $value->msg_id;

			$value->body                                       = $this->jomHelper->addAudioFile($value->body);
			$this->jsonarray['messages'][$key]['body']         = $value->body;
			$format                                            = JText::_('COM_COMMUNITY_DATE_FORMAT_LC2_24H');
			$timezone                                          = JFactory::getConfig()->get('offset');
			$dtz                                               = new DateTimeZone($timezone);
			$dt                                                = new DateTime("now", $dtz);
			$offset                                            = timezone_offset_get($dtz, $dt) / 3600;
			$date                                              = CTimeHelper::getFormattedUTC($value->posted_on, $offset);
			$date                                              = CTimeHelper::getFormattedTime($date, $format);
			$this->jsonarray['messages'][$key]['date']         = $date;
			$this->jsonarray['messages'][$key]['outgoing']     = $value->type;
			$this->jsonarray['messages'][$key]['read']         = ($this->IJUserID == $value->msg_from) ? 1 : $value->is_read;
			$usr                                               = $this->jomHelper->getUserDetail($value->msg_from);
			$this->jsonarray['messages'][$key]['user_id']      = $usr->id;
			$this->jsonarray['messages'][$key]['user_name']    = $usr->name;
			$this->jsonarray['messages'][$key]['user_avatar']  = $usr->avatar;
			$this->jsonarray['messages'][$key]['user_profile'] = $usr->profile;
		}

		$ids = implode(',', $ids);

		$query = "UPDATE #__community_msg_recepient
				SET `is_read`=1
				WHERE msg_id IN ({$ids})
				AND (`msg_from`={$userID} AND `to`={$this->IJUserID})";
		$this->db->setQuery($query);
		$this->db->Query();

		$notification = $this->jomHelper->getNotificationCount();

		if (isset($notification['notification']))
		{
			$this->jsonarray['notification'] = $notification['notification'];
		}

		return $this->jsonarray;
	}

	/**
	 * uses    to get message detail as conversation
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"message",
	 *        "extTask":"remove",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID",
	 *            "full":"full" // 0: remove sigle message, 1: remove entire thread.
	 *        }
	 *    }
	 * @return array/boolean  true on success and false on failure and Jsonarray
	 */
	public function remove()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');
		$full     = IJReq::getTaskData('full', 0, 'bool');

		$inboxModel = CFactory::getModel('inbox');

		if (!$this->IJUserID)
		{
			IJReq::setResponse(401);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if ($full)
		{
			$conv   = $inboxModel->getFullMessages($uniqueID);
			$delCnt = 0;

			$filter   = array();
			$parentId = $inboxModel->getParent($uniqueID);

			$filter ['msgId'] = $parentId;
			$filter ['to']    = $this->IJUserID;

			$data           = new stdClass;
			$data->messages = $inboxModel->getMessages($filter, true);

			$childCount = count($data->messages);

			if (!empty($conv))
			{
				foreach ($conv as $msg)
				{
					if ($inboxModel->canReply($this->IJUserID, $msg->id))
					{
						if ($inboxModel->removeReceivedMsg($msg->id, $this->IJUserID))
						{
							$delCnt++;
						}
					}
				}
			}

			$this->jsonarray['code'] = 200;

			return $this->jsonarray;
		}
		else
		{
			if ($inboxModel->removeReceivedMsg($uniqueID, $this->IJUserID))
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
	}

	/**
	 * uses  to get message detail as conversation
	 *
	 * @example the json string will be like, :
	 *    {
	 *        "extName":"jomsocial",
	 *        "extView":"message",
	 *        "extTask":"write",
	 *        "taskData":{
	 *            "uniqueID":"uniqueID", // optional if write new message
	 *            "userID":"uesrID", // optional if reply a message, comma separated
	 *            "subject":"subject", // optional if reply a message
	 *            "body":"body"
	 *        }
	 *    }
	 * @return array/boolean  true on success and false on failure and Jsonarray
	 */
	public function write()
	{
		$uniqueID = IJReq::getTaskData('uniqueID', 0, 'int');

		$audiofileupload = $this->jomHelper->uploadAudioFile();

		if ($this->IJUserID == 0)
		{
			IJReq::setResponse(401);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if (IJReq::getTaskData('userID', $this->IJUserID, '') == '')
		{
			IJReq::setResponse(400);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		CFactory::setActiveProfile();

		// Write message
		if (!$uniqueID)
		{
			$inputFilter = CFactory::getInputFilter(true);

			$data          = new stdClass;
			$data->to      = $msgData['friends'] = explode(',', IJReq::getTaskData('userID'));
			$data->subject = $msgData['subject'] = IJReq::getTaskData('subject', '');
			$data->subject = $inputFilter->clean($data->subject);

			if ($audiofileupload)
			{
				$voicedata = $audiofileupload['voicetext'];
			}
			else
			{
				$voicedata = '';
			}

			$data->body = $msgData['body'] = IJReq::getTaskData('body', '') . $voicedata;
			$data->body = $inputFilter->clean($data->body);
			$data->sent = 0;
			$model      = CFactory::getModel('user');
			$actualTo   = array();

			// Are we saving ??
			CFactory::load('libraries', 'apps');
			$appsLib     = CAppPlugins::getInstance();
			$saveSuccess = $appsLib->triggerEvent('onFormSave', array('jsform-inbox-write'));

			if (empty($saveSuccess) || !in_array(false, $saveSuccess))
			{
				// @rule: Check if user exceeded limit
				$inboxModel  = CFactory::getModel('inbox');
				$useRealName = ($this->config->get('displayname') == 'name') ? true : false;
				$maxSent     = $this->config->get('pmperday');
				$totalSent   = $inboxModel->getTotalMessageSent($this->IJUserID);

				if ($totalSent >= $maxSent && $maxSent != 0)
				{
					IJReq::setResponse(416, JText::_('COM_COMMUNITY_PM_LIMIT_REACHED'));
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				// @rule: Spam checks
				if ($this->_isSpam($this->my, $data->subject . ' ' . $data->body))
				{
					IJReq::setResponse(705, JText::_('COM_COMMUNITY_INBOX_MESSAGE_MARKED_SPAM'));
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				// Block users
				CFactory::load('helpers', 'owner');
				CFactory::load('libraries', 'block');
				$getBlockStatus = new blockUser;

				// Enable multiple recipients
				// @since 2.4
				$actualTo = $data->to;
				$actualTo = array_unique($actualTo);

				if (!(count($actualTo) > 0))
				{
					IJReq::setResponse(400, JText::_('COM_COMMUNITY_INBOX_RECEIVER_MISSING'));
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				$tempUser = array();

				foreach ($actualTo as $recepientId)
				{
					// Get name for error message show
					$user  = CFactory::getUser($recepientId);
					$name  = $user->getDisplayName();
					$thumb = $user->getThumbAvatar();

					if ($getBlockStatus->isUserBlocked($recepientId, 'inbox') && !COwnerHelper::isCommunityAdmin())
					{
						IJReq::setResponse(705, JText::_('COM_COMMUNITY_YOU_ARE_BLOCKED_BY_USER'));
						IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

						return false;
					}

					// Restrict user to send message to themselve
					if ($this->my->id == $recepientId)
					{
						IJReq::setResponse(706, JText::_('COM_COMMUNITY_INBOX_MESSAGE_CANNOT_SEND_TO_SELF'));
						IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

						return false;
					}

					// Since 2.4, to keep track previous 'to' info
					$tempUser[] = array('rid' => $recepientId, 'avatar' => $thumb, 'name' => $name);
				}

				$data->toUsersInfo = $tempUser;

				if (empty ($data->subject))
				{
					IJReq::setResponse(400, JText::_('COM_COMMUNITY_INBOX_SUBJECT_MISSING'));
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				if (empty ($data->body))
				{
					IJReq::setResponse(400, JText::_('COM_COMMUNITY_INBOX_MESSAGE_EMPTY'));
					IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

					return false;
				}

				$model = CFactory::getModel('inbox');

				$msgData ['to']     = $actualTo;
				$msgData ['action'] = 'doSubmit';

				$msgid      = $model->send($msgData);
				$data->sent = 1;

				// Add user points
				CFactory::load('libraries', 'userpoints');
				CUserPoints::assignPoint('inbox.message.send');

				// Add notification
				CFactory::load('libraries', 'notification');

				$params = new CParameter('');
				$params->set('url', 'index.php?option=com_community&view=inbox&task=read&msgid=' . $msgid);
				$params->set('message', $data->body);
				$params->set('title', $data->subject);
				$params->set('msg_url', 'index.php?option=com_community&view=inbox&task=read&msgid=' . $msgid);
				$params->set('msg', JText::_('COM_COMMUNITY_PRIVATE_MESSAGE'));

				// Change for id based push notification
				$memberslist                           = implode(',', $actualTo);
				$pushOptions                           = array();
				$pushOptions['detail']['content_data'] = $pushcontentdata;
				$pushOptions                           = gzcompress(json_encode($pushOptions));

				$usr     = $this->jomHelper->getUserDetail($this->IJUserID);
				$match   = array('{msg}', '{actor}');
				$replace = array(JText::_('COM_COMMUNITY_PRIVATE_MESSAGE'), $usr->name);
				$message = str_replace($match, $replace, JText::sprintf('COM_COMMUNITY_SENT_YOU_MESSAGE'));

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
					$this->jsonarray['pushNotificationData']['type']       = 'message';
					$this->jsonarray['pushNotificationData']['configtype'] = 'pushnotif_inbox_create_message';
				}

				$this->jsonarray['code'] = 200;

				return $this->jsonarray;
			}

			IJReq::setResponse(500);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		/*
		 * Reply message
		 */
		$filter   = JFilterInput::getInstance();
		$uniqueID = $filter->clean($uniqueID, 'int');

		if ($audiofileupload)
		{
			$voicedata = $audiofileupload['voicetext'];
		}
		else
		{
			$voicedata = '';
		}

		$body = IJReq::getTaskData('body', '') . $voicedata;
		$body = $filter->clean($body, 'string');

		$model            = CFactory::getModel('inbox');
		$message          = $model->getMessage($uniqueID);
		$messageRecepient = $model->getParticipantsID($uniqueID, $this->IJUserID);

		// Block users
		CFactory::load('helpers', 'owner');
		CFactory::load('libraries', 'block');
		$getBlockStatus = new blockUser;

		if ($getBlockStatus->isUserBlocked($messageRecepient[0], 'inbox') && !COwnerHelper::isCommunityAdmin())
		{
			IJReq::setResponse(705, JText::_('COM_COMMUNITY_YOU_ARE_BLOCKED_BY_USER'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// @rule: Spam checks
		if ($this->_isSpam($this->my, $body))
		{
			IJReq::setResponse(705, JText::_('COM_COMMUNITY_INBOX_MESSAGE_MARKED_SPAM'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if (empty ($body))
		{
			IJReq::setResponse(400, JText::_('COM_COMMUNITY_INBOX_MESSAGE_CANNOT_BE_EMPTY'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		if (empty ($messageRecepient))
		{
			IJReq::setResponse(400, JText::_('COM_COMMUNITY_INBOX_MESSAGE_CANNOT_FIND_RECIPIENT'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// Make sure we can only reply to message that belogn to current user
		if (!$model->canReply($this->IJUserID, $uniqueID))
		{
			IJReq::setResponse(706, JText::_('COM_COMMUNITY_PERMISSION_DENIED_WARNING'));
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		// Get the time without any offset!
		$date = JFactory::getDate();

		$obj            = new stdClass;
		$obj->id        = null;
		$obj->from      = $this->IJUserID;
		$obj->posted_on = $date->toSql();
		$obj->from_name = $this->my->name;
		$obj->subject   = 'RE:' . $message->subject;
		$obj->body      = $body;

		$model->sendReply($obj, $uniqueID);

		// Add user points
		CFactory::load('libraries', 'userpoints');
		CUserPoints::assignPoint('inbox.message.reply');

		// Add notification
		CFactory::load('libraries', 'notification');

		foreach ($messageRecepient as $row)
		{
			$params = new CParameter('');

			$params->set('message', $reply);
			$params->set('title', '');
			$params->set('url', 'index.php?option=com_community&view=inbox&task=read&msgid=' . $uniqueID);
			$params->set('msg_url', 'index.php?option=com_community&view=inbox&task=read&msgid=' . $uniqueID);
			$params->set('msg', JText::_('COM_COMMUNITY_PRIVATE_MESSAGE'));

			CNotificationLibrary::add('inbox_create_message', $this->IJUserID, $row, JText::sprintf('COM_COMMUNITY_SENT_YOU_MESSAGE'), '', 'inbox.sent', $params);

			// Get user push notification params and user device token and device type
			$query = "SELECT `jomsocial_params`,`device_token`,`device_type`
					FROM #__ijoomeradv_users
					WHERE `userid`={$row}";
			$this->db->setQuery($query);
			$puser    = $this->db->loadObject();
			$ijparams = new CParameter($puser->jomsocial_params);
		}

		// Change for id based push notification
		$memberslist                           = implode(',', $messageRecepient);
		$pushOptions                           = array();
		$pushOptions['detail']['content_data'] = $pushcontentdata;
		$pushOptions                           = gzcompress(json_encode($pushOptions));

		$usr     = $this->jomHelper->getUserDetail($this->IJUserID);
		$match   = array('{msg}', '{actor}');
		$replace = array(JText::_('COM_COMMUNITY_PRIVATE_MESSAGE'), $usr->name);
		$message = str_replace($match, $replace, JText::sprintf('COM_COMMUNITY_SENT_YOU_MESSAGE'));

		$obj          = new stdClass;
		$obj->id      = null;
		$obj->detail  = $pushOptions;
		$obj->tocount = count($messageRecepient);
		$this->db->insertObject('#__ijoomeradv_push_notification_data', $obj, 'id');

		if ($obj->id)
		{
			$this->jsonarray['pushNotificationData']['id']         = $obj->id;
			$this->jsonarray['pushNotificationData']['to']         = $memberslist;
			$this->jsonarray['pushNotificationData']['message']    = $message;
			$this->jsonarray['pushNotificationData']['type']       = 'message';
			$this->jsonarray['pushNotificationData']['configtype'] = 'pushnotif_inbox_create_message';
		}

		$this->jsonarray['code'] = 200;

		return $this->jsonarray;
	}

	/**
	 * _isSpam function
	 *
	 * @param   [type]  $user  [description]
	 * @param   [type]  $data  [description]
	 *
	 * @return  boolean         void
	 */
	private function _isSpam($user, $data)
	{
		// @rule: Spam checks
		if ($this->config->get('antispam_akismet_messages'))
		{
			CFactory::load('libraries', 'spamfilter');

			$filter = CSpamFilter::getFilter();
			$filter->setAuthor($user->getDisplayName());
			$filter->setMessage($data);
			$filter->setEmail($user->email);
			$filter->setURL(JURI::root());
			$filter->setType('message');
			$filter->setIP($_SERVER['REMOTE_ADDR']);

			if ($filter->isSpam())
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * write1 function
	 *
	 * @return  boolean  jsonarray
	 */
	public function write1()
	{
		$userID  = IJReq::getTaskData('userID');
		$userID  = explode(",", $userID);
		$subject = IJReq::getTaskData('subject');
		$body    = IJReq::getTaskData('body');

		// Get the time without any offset!
		$date    = JFactory::getDate();
		$cDate   = $date->toSql();

		$obj            = new stdClass;
		$obj->id        = null;
		$obj->from      = $this->IJUserID;
		$obj->posted_on = $date->toSql();
		$obj->from_name = $this->my->name;
		$obj->subject   = $subject;
		$obj->body      = $body;

		// Don't add message if user is sending message to themselve
		if ($userID != $this->IJUserID)
		{
			$this->db->insertObject('#__community_msg', $obj, 'id');

			// Update the parent
			$obj->parent = $obj->id;
			$this->db->updateObject('#__community_msg', $obj, 'id');
		}

		if (is_array($userID))
		{
			// Multiple recepint
			foreach ($userID as $user)
			{
				if ($userID != $this->IJUserID)
					$this->addReceipient($obj, $user);
			}
		}
		else
		{
			// Single recepient
			if ($userID != $this->IJUserID)
				$this->addReceipient($obj, $userID);
		}

		$this->jsonarray['code'] = 200;
		$this->jsonarray['id']   = $obj->id;

		return $this->jsonarray;
	}

	/**
	 * addRecepeient function
	 *
	 * @param   [type]  $msgObj       message object
	 * @param   [type]  $recepientId  id of recepient
	 *
	 * @return array/boolean  true on success and false on failure and Jsonarray
	 */
	private function addReceipient($msgObj, $recepientId)
	{
		$recepient             = new stdClass;
		$recepient->msg_id     = $msgObj->id;
		$recepient->msg_parent = $msgObj->parent;
		$recepient->msg_from   = $msgObj->from;
		$recepient->to         = $recepientId;

		if ($this->IJUserID != $recepientId)
			$this->db->insertObject('#__community_msg_recepient', $recepient);

		if ($this->db->getErrorNum())
		{
			IJReq::setResponse(500);
			IJException::setErrorInfo(__FILE__, __LINE__, __CLASS__, __METHOD__, __FUNCTION__);

			return false;
		}

		return true;
	}
}
