<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AfterLogic Software License
 *
 * This code is licensed under AfterLogic Software License.
 * For full statements of the license see LICENSE file.
 */

namespace Aurora\Modules\Calendar\Classes;

/**
 * @internal
 * 
 * @package Calendar
 * @subpackage Classes
 */
class Helper
{

	/**
	 * @param \Aurora\Modules\Calendar\Classes\Event $oEvent
	 * @param \DateTime $oNowDT
	 * @param \DateTime $oStartDT
	 *
	 * @return int|false
	 */
	public static function getActualReminderTime($oEvent, $oNowDT, $oStartDT)
	{
		$aReminders = \Aurora\Modules\Calendar\Classes\Parser::parseAlarms($oEvent);

		$iNowTS = $oNowDT->getTimestamp();

		if ($oStartDT)
		{
			$iStartEventTS = $oStartDT->getTimestamp();

			$aRemindersTime = array();
			foreach ($aReminders as $iReminder)
			{
				$aRemindersTime[] = $iStartEventTS - $iReminder * 60;
			}
			sort($aRemindersTime);
			foreach ($aRemindersTime as $iReminder)
			{
				if ($iReminder > $iNowTS)
				{
					return $iReminder;
				}
			}
		}
		return false;
	}

	/**
	 * @param \DateTime $sDtStart
	 * @param \Sabre\VObject\Component\VCalendar $oVCal
	 * @param string $sUid Default value is **null**.
	 *
	 * @return \DateTime
	 */
	public static function getNextRepeat(\DateTime $sDtStart, $oVCal, $sUid = null)
	{
		$oRecur = new \Sabre\VObject\Recur\EventIterator($oVCal, $sUid);
		$oRecur->fastForward($sDtStart);
		return $oRecur->current();
	}

	/**
	 * @param int $iData
	 * @param int $iMin
	 * @param int $iMax
	 *
	 * @return bool
	 */
	public static function validate($iData, $iMin, $iMax)
	{
		if (null === $iData)
		{
			return false;
		}
		$iData = round($iData);
		return (isset($iMin) && isset($iMax)) ? ($iMin <= $iData && $iData <= $iMax) : ($iData > 0);
	}

	/**
	 * @param \DateTime $dt
	 * @param string $sTimeZone
	 *
	 * @return int|null
	 */
	public static function getTimestamp($dt, $sTimeZone = 'UTC')
	{
		$iResult = null;

		$oDateTime = self::getDateTime($dt, $sTimeZone);
		if (null != $oDateTime)
		{
			$iResult = $oDateTime->getTimestamp();
		}

		return $iResult;
	}

	/**
	 * @param \DateTime $dt
	 * @param string $sTimeZone
	 *
	 * @return \DateTime|null
	 */
	public static function getDateTime($dt, $sTimeZone = 'UTC')
	{
		$result = null;
		if ($dt)
		{
			$result = $dt->getDateTime();
		}
		if (isset($result))
		{
			$sTimeZone = $sTimeZone === null ? 'UTC' : $sTimeZone;
			$result = $result->setTimezone(new \DateTimeZone($sTimeZone));
		}
		return $result;
	}

	/**
	 * @param \DateTime $dt
	 * @param string $format
	 *
	 * @return string
	 */
	public static function dateTimeToStr($dt, $format = 'Y-m-d H:i:s')
	{
		return $dt->format($format);
	}

	/**
	 * @param \Sabre\VObject\Component\VEvent $oVEvent
	 * @param string $sRecurrenceId
	 *
	 * @return mixed
	 */
	public static function isRecurrenceExists($oVEvent, $sRecurrenceId)
	{
		$mResult = false;
		foreach($oVEvent as $mKey => $oEvent)
		{
			if (isset($oEvent->{'RECURRENCE-ID'}))
			{
				if (self::getRecurrenceId($oEvent) === $sRecurrenceId)
				{
					$mResult = $mKey;
					break;
				}
			}
		}

		return $mResult;
	}

	/**
	 * @param \Sabre\VObject\Component $oComponent
	 *
	 * @return int
	 */
	public static function getRecurrenceId($oComponent)
	{
		$iTimestamp = 0;
		$oRecurrenceId = $oComponent->DTSTART;
		if ($oComponent->{'RECURRENCE-ID'})
		{
			$oRecurrenceId = $oComponent->{'RECURRENCE-ID'};
		}
		if (isset($oRecurrenceId))
		{
			$dRecurrence = $oRecurrenceId->getDateTime();
			$iTimestamp = $dRecurrence->getTimestamp();
		}
		return $iTimestamp;
	}

    /**
	 * @param \DateInterval $oInterval
	 *
	 * @return int
	 */
	public static function getOffsetInMinutes($oInterval)
	{
		$iMinutes = 0;
		try
		{
			$iMinutes = $oInterval->i + $oInterval->h*60 + $oInterval->d*24*60;
		}
		catch (Exception $ex)
		{
			$iMinutes = 15;
		}

		return $iMinutes;
	}

	public static function getBaseVEventIndex($oVEvents)
	{
		$iIndex = -1;
		if (isset($oVEvents))
		{
			foreach($oVEvents as $oVEvent)
			{
				$iIndex++;
				if (empty($oVEvent->{'RECURRENCE-ID'}))
				{
					break;
				}
			}
		}
		return ($iIndex >= 0) ? $iIndex : false;
	}

	/**
	 * @param int $iUserId
	 * @param \Aurora\Modules\Calendar\Classes\Event $oEvent
	 * @param \Sabre\VObject\Component\VEvent $oVEvent
	 */
	public static function populateVCalendar($iUserId, $oEvent, &$oVEvent)
	{
		$oVEvent->{'LAST-MODIFIED'} = new \DateTime('now', new \DateTimeZone('UTC'));
		$oVEvent->{'SEQUENCE'} = isset($oVEvent->{'SEQUENCE'}) ? $oVEvent->{'SEQUENCE'}->getValue() + 1 : 1;
		
		if ($oEvent->Type === 'todo')
		{
			if ($oEvent->Status)
			{
				$oVEvent->STATUS = 'COMPLETED';
				$oVEvent->{'PERCENT-COMPLETE'} = 100;
				$oVEvent->COMPLETED = new \DateTime('now', new \DateTimeZone('UTC'));
			}
			else 
			{
				$oVEvent->STATUS = 'NEEDS-ACTION';
				unset($oVEvent->{'PERCENT-COMPLETE'});
				unset($oVEvent->COMPLETED);
			}
		}
		
		$oVCal =& $oVEvent->parent;

		$oVEvent->UID = $oEvent->Id;

		if (!empty($oEvent->Start) && !empty($oEvent->End))
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			$oDTStart = self::prepareDateTime($oEvent->Start, $oUser->DefaultTimeZone);
			if (isset($oDTStart))
			{
				$oVEvent->DTSTART = $oDTStart;
				if ($oEvent->AllDay)
				{
					$oVEvent->DTSTART->offsetSet('VALUE', 'DATE');
				}
			}
			$oDTEnd = self::prepareDateTime($oEvent->End, $oUser->DefaultTimeZone);
			if (isset($oDTEnd))
			{
				$oVEvent->DTEND = $oDTEnd;
				if ($oEvent->AllDay)
				{
					$oVEvent->DTEND->offsetSet('VALUE', 'DATE');
				}
			}
		}
		else
		{
			unset($oVEvent->DTSTART);
			unset($oVEvent->DTEND);
		}

		if (isset($oEvent->Name))
		{
			$oVEvent->SUMMARY = $oEvent->Name;
		}
		if (isset($oEvent->Description))
		{
			$oVEvent->DESCRIPTION = $oEvent->Description;
		}
		if (isset($oEvent->Location))
		{
			$oVEvent->LOCATION = $oEvent->Location;
		}

		unset($oVEvent->RRULE);
		if (isset($oEvent->RRule))
		{
			$sRRULE = '';
			if (isset($oVEvent->RRULE) && null === $oEvent->RRule)
			{
				$oRRule = \Aurora\Modules\Calendar\Classes\Parser::parseRRule($iUserId, $oVCal, (string)$oVEvent->UID);
				if ($oRRule && $oRRule instanceof \Aurora\Modules\Calendar\Classes\RRule)
				{
					$sRRULE = (string) $oRRule;
				}
			}
			else
			{
				$sRRULE = (string)$oEvent->RRule;
			}
			if (trim($sRRULE) !== '')
			{
				$oVEvent->add('RRULE', $sRRULE);
			}
		}

		unset($oVEvent->VALARM);
		if (isset($oEvent->Alarms))
		{
			foreach ($oEvent->Alarms as $sOffset)
			{
				$oVEvent->add('VALARM', array(
					'TRIGGER' => self::getOffsetInStr($sOffset),
					'DESCRIPTION' => 'Alarm',
					'ACTION' => 'DISPLAY'
				));
			}
		}

		if (/*$this->oApiCapabilityManager->isCalendarAppointmentsSupported($UserId)*/ 1<>1) // TODO
		{
			$aAttendees = array();
			$aAttendeeEmails = array();
			$aObjAttendees = array();
			if (isset($oVEvent->ATTENDEE))
			{
				$aAttendeeEmails = array();
				foreach ($oEvent->Attendees as $aItem)
				{
					$sStatus = '';
					switch ($aItem['status'])
					{
						case \Aurora\Modules\Calendar\Enums\AttendeeStatus::Accepted:
							$sStatus = 'ACCEPTED';
							break;
						case \Aurora\Modules\Calendar\Enums\AttendeeStatus::Declined:
							$sStatus = 'DECLINED';
							break;
						case \Aurora\Modules\Calendar\Enums\AttendeeStatus::Tentative:
							$sStatus = 'TENTATIVE';
							break;
						case \Aurora\Modules\Calendar\Enums\AttendeeStatus::Unknown:
							$sStatus = 'NEEDS-ACTION';
							break;
					}

					$aAttendeeEmails[strtolower($aItem['email'])] = $sStatus;
				}

				$aObjAttendees = $oVEvent->ATTENDEE;
				unset($oVEvent->ATTENDEE);
				foreach($aObjAttendees as $oAttendee)
				{
					$sAttendee = str_replace('mailto:', '', strtolower((string)$oAttendee));
					$oPartstat = $oAttendee->offsetGet('PARTSTAT');
					if (in_array($sAttendee, array_keys($aAttendeeEmails)))
					{
						if (isset($oPartstat) && (string)$oPartstat === $aAttendeeEmails[$sAttendee])
						{
							$oVEvent->add($oAttendee);
							$aAttendees[] = $sAttendee;
						}
					}
					else
					{
						if (!isset($oPartstat) || (isset($oPartstat) && (string)$oPartstat != 'DECLINED'))
						{
							$oVCal->METHOD = 'CANCEL';
							$sSubject = (string)$oVEvent->SUMMARY . ': Canceled';
//							self::sendAppointmentMessage($oAccount, $sAttendee, $sSubject, $oVCal->serialize(), (string)$oVCal->METHOD);
							//TODO Notify the user
							unset($oVCal->METHOD);
						}
					}
				}
			}

			if (count($oEvent->Attendees) > 0)
			{
				if (!isset($oVEvent->ORGANIZER))
				{
					$oVEvent->ORGANIZER = 'mailto:' . $oUser->PublicId;
				}
				foreach($oEvent->Attendees as $oAttendee)
				{
					if (!in_array($oAttendee['email'], $aAttendees))
					{
						$oVEvent->add(
							'ATTENDEE',
							'mailto:' . $oAttendee['email'],
							array(
								'CN' => !empty($oAttendee['name']) ? $oAttendee['name'] : $oAttendee['email'],
								'RSVP' => 'TRUE'
							)
						);
					}
				}
			}
			else
			{
				unset($oVEvent->ORGANIZER);
			}

			if (isset($oVEvent->ATTENDEE))
			{
				foreach($oVEvent->ATTENDEE as $oAttendee)
				{
					$sAttendee = str_replace('mailto:', '', strtolower((string)$oAttendee));

					if (($sAttendee !==  $oUser->PublicId) &&
						(!isset($oAttendee['PARTSTAT']) || (isset($oAttendee['PARTSTAT']) && (string)$oAttendee['PARTSTAT'] !== 'DECLINED')))
					{
						$sStartDateFormat = $oVEvent->DTSTART->hasTime() ? 'D, F d, o, H:i' : 'D, F d, o';
						$sStartDate = self::getStrDate($oVEvent->DTSTART, $oUser->DefaultTimeZone, $sStartDateFormat);

						$oCalendar = \Aurora\System\Api::GetModule('Calendar')->GetCalendar($iUserId, $oEvent->IdCalendar);
						
						$sHtml = self::createHtmlFromEvent($oEvent, $oUser->PublicId, $sAttendee, $oCalendar->DisplayName, $sStartDate);

						$oVCal->METHOD = 'REQUEST';
//						self::sendAppointmentMessage($oAccount, $sAttendee, (string)$oVEvent->SUMMARY, $oVCal->serialize(), (string)$oVCal->METHOD, $sHtml);
						//TODO Notify the user
						unset($oVCal->METHOD);
					}
				}
			}
		}
	}

	/**
	 * @param mixed $mDateTime
	 * @param string $sTimeZone
	 *
	 * @return \DateTime
	 */
	public static function prepareDateTime($mDateTime, $sTimeZone)
	{
		$oDateTime = new \DateTime();
		if (is_numeric($mDateTime) && strlen($mDateTime) !== 8)
		{
			$oDateTime->setTimestamp($mDateTime);
			$oDateTime->setTimezone(new \DateTimeZone($sTimeZone));
		}
		else
		{
			$oDateTime = \Sabre\VObject\DateTimeParser::parse($mDateTime, new DateTimeZone($sTimeZone));
		}

		return $oDateTime;
	}

    /**
	 * @param string $iMinutes
	 *
	 * @return string
	 */
	public static function getOffsetInStr($iMinutes)
	{
		return '-PT' . $iMinutes . 'M';
	}

	/**
	 * @param \Aurora\Modules\StandardAuth\Classes\Account $oAccount
	 * @param string $sTo
	 * @param string $sSubject
	 * @param string $sBody
	 * @param string $sMethod
	 * @param string $sHtmlBody Default value is empty string.
	 *
	 * @throws \Aurora\System\Exceptions\ApiException
	 *
	 * @return \MailSo\Mime\Message
	 */
	public static function sendAppointmentMessage($oAccount, $sTo, $sSubject, $sBody, $sMethod, $sHtmlBody='')
	{
		$oMessage = self::buildAppointmentMessage($oAccount, $sTo, $sSubject, $sBody, $sMethod, $sHtmlBody);

		if ($oMessage)
		{
			try
			{
				\Aurora\System\Api::Log('IcsAppointmentActionSendOriginalMailMessage');
				return \Aurora\System\Api::ExecuteMethod('Mail::SendMessageObject', array(
					'Account' => $oAccount,
					'Message' => $oMessage
				));
			}
			catch (\Aurora\System\Exceptions\ManagerException $oException)
			{
				$iCode = \Core\Notifications::CanNotSendMessage;
				switch ($oException->getCode())
				{
					case Errs::Mail_InvalidRecipients:
						$iCode = \Core\Notifications::InvalidRecipients;
						break;
				}

				throw new \Aurora\System\Exceptions\ApiException($iCode, $oException);
			}
		}

		return false;
	}

	/**
	 * @param \Aurora\Modules\StandardAuth\Classes\Account $oAccount
	 * @param string $sTo
	 * @param string $sSubject
	 * @param string $sBody
	 * @param string $sMethod Default value is **null**.
	 * @param string $sHtmlBody Default value is empty string.
	 *
	 * @return \MailSo\Mime\Message
	 */
	public static function buildAppointmentMessage($oAccount, $sTo, $sSubject, $sBody, $sMethod = null, $sHtmlBody = '')
	{
		$oMessage = null;
		if ($oAccount && !empty($sTo) && !empty($sBody))
		{
			$oMessage = \MailSo\Mime\Message::NewInstance();
			$oMessage->RegenerateMessageId();
			$oMessage->DoesNotCreateEmptyTextPart();

			$oMailModule = \Aurora\System\Api::GetModule('Mail'); 
			$sXMailer = $oMailModule ? $oMailModule->getConfig('XMailerValue', '') : '';
			if (0 < strlen($sXMailer))
			{
				$oMessage->SetXMailer($sXMailer);
			}

			$oMessage
				->SetFrom(\MailSo\Mime\Email::NewInstance($oAccount->Email))
				->SetSubject($sSubject)
			;

			$oMessage->AddHtml($sHtmlBody);

			$oToEmails = \MailSo\Mime\EmailCollection::NewInstance($sTo);
			if ($oToEmails && $oToEmails->Count())
			{
				$oMessage->SetTo($oToEmails);
			}

			if ($sMethod)
			{
				$oMessage->SetCustomHeader('Method', $sMethod);
			}

			$oMessage->AddAlternative('text/calendar', \MailSo\Base\ResourceRegistry::CreateMemoryResourceFromString($sBody),
					\MailSo\Base\Enumerations\Encoding::_8_BIT, null === $sMethod ? array() : array('method' => $sMethod));
		}

		return $oMessage;
	}

	/**
	 * @param \DateTime $dt
	 * @param string $sTimeZone
	 * @param string $format
	 *
	 * @return string
	 */
	public static function getStrDate($dt, $sTimeZone, $format = 'Y-m-d H:i:s')
	{
		$result = null;
		$oDateTime = self::getDateTime($dt, $sTimeZone);
		if ($oDateTime)
		{
			if (!$dt->hasTime())
			{
				$format = 'Y-m-d';
			}
			$result = $oDateTime->format($format);
		}
		return $result;
	}

	/**
	 * @param \Aurora\Modules\Calendar\Classes\Event $oEvent
	 * @param string $sAccountEmail
	 * @param string $sAttendee
	 * @param string $sCalendarName
	 * @param string $sStartDate
	 *
	 * @return string
	 */
	public static function createHtmlFromEvent($oEvent, $sAccountEmail, $sAttendee, $sCalendarName, $sStartDate)
	{
		$sHtml = '';
		$aValues = array(
			'attendee' => $sAttendee,
			'organizer' => $sAccountEmail,
			'calendarId' => $oEvent->IdCalendar,
			'eventId' => $oEvent->Id
		);
		
		$aValues['action'] = 'ACCEPTED';
		$sEncodedValueAccept = \Aurora\System\Api::EncodeKeyValues($aValues);
		$aValues['action'] = 'TENTATIVE';
		$sEncodedValueTentative = \Aurora\System\Api::EncodeKeyValues($aValues);
		$aValues['action'] = 'DECLINED';
		$sEncodedValueDecline = \Aurora\System\Api::EncodeKeyValues($aValues);

		$sHref = rtrim(\MailSo\Base\Http::SingletonInstance()->GetFullUrl(), '\\/ ').'/?invite=';
		$oCalendarModule = \Aurora\System\Api::GetModule('Calendar');
		if ($oCalendarModule instanceof \Aurora\System\Module\AbstractModule)
		{
			$sHtml = file_get_contents($oCalendarModule->GetPath().'/templates/CalendarEventInvite.html');
			$sHtml = strtr($sHtml, array(
				'{{INVITE/LOCATION}}'	=> \Aurora\System\Api::I18N('INVITE/LOCATION'),
				'{{INVITE/WHEN}}'		=> \Aurora\System\Api::I18N('INVITE/WHEN'),
				'{{INVITE/DESCRIPTION}}'=> \Aurora\System\Api::I18N('INVITE/DESCRIPTION'),
				'{{INVITE/INFORMATION}}'=> \Aurora\System\Api::I18N('INVITE/INFORMATION', array('Email' => $sAttendee)),
				'{{INVITE/ACCEPT}}'		=> \Aurora\System\Api::I18N('INVITE/ACCEPT'),
				'{{INVITE/TENTATIVE}}'	=> \Aurora\System\Api::I18N('INVITE/TENTATIVE'),
				'{{INVITE/DECLINE}}'	=> \Aurora\System\Api::I18N('INVITE/DECLINE'),
				'{{Calendar}}'			=> $sCalendarName.' '.$sAccountEmail,
				'{{Location}}'			=> $oEvent->Location,
				'{{Start}}'				=> $sStartDate,
				'{{Description}}'		=> $oEvent->Description,
				'{{HrefAccept}}'		=> $sHref.$sEncodedValueAccept,
				'{{HrefTentative}}'		=> $sHref.$sEncodedValueTentative,
				'{{HrefDecline}}'		=> $sHref.$sEncodedValueDecline
			));
		}
		
		return $sHtml;
	}

	/**
	 * @param string $sString
	 *
	 * @return array
	 */
	public static function findGroupsHashTagsFromString($sString)
	{
		$aResult = array();
		
		preg_match_all("/[#]([^#\s]+)/", $sString, $aMatches);
		
		if (\is_array($aMatches) && isset($aMatches[0]) && \is_array($aMatches[0]) && 0 < \count($aMatches[0]))
		{
			$aResult = $aMatches[0];
		}
		
		return $aResult;
	}
	
}
