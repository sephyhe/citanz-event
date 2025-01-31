<?php

namespace Cita\Event\Model;
use SilverStripe\Security\Member;
use SilverStripe\ORM\DataObject;
use Cita\Event\Layout\EventPage;
use SilverStripe\Security\Security;

/**
 * Description
 *
 * @package silverstripe
 * @subpackage mysite
 */
class RSVP extends DataObject
{
    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'RSVP';

    /**
     * Database fields
     * @var array
     */
    private static $db = [
        'FirstName'     =>  'Varchar(128)',
        'Surname'       =>  'Varchar(128)',
        'Email'         =>  'Varchar(256)',
        'NumGuests'     =>  'Int',
        'QRToken'       =>  'Varchar(64)',
        'AttendedAt'    =>  'Datetime'
    ];

    private static $indexes = [
        'Email'     =>  true,
        'QRToken'   =>  [
            'type'  =>  'unique'
        ]
    ];

    /**
     * Defines a default list of filters for the search context
     * @var array
     */
    private static $searchable_fields = [
        'Email'
    ];

    /**
     * Default sort ordering
     * @var array
     */
    private static $default_sort = ['Created' => 'DESC', 'ID' => 'DESC'];

    /**
     * Defines summary fields commonly used in table columns
     * as a quick overview of the data for this dataobject
     * @var array
     */
    private static $summary_fields = [
        'Email'         =>  'Email',
        'isMember'      =>  'is Member?',
        'NumGuests'     =>  'Number of Guests'
    ];

    /**
     * Has_one relationship
     * @var array
     */
    private static $has_one = [
        'Event'     =>  EventPage::class,
        'Member'    =>  Member::class
    ];

    public function validate()
    {
        $validator  =   parent::validate();

        if (!$this->Event()->exists()) {
            $validator->addError('You must respond to an event!');
            return $validator;
        }

        if (empty($this->Email) && !$this->Member()->exists() && !Security::getCurrentUser()) {
            $validator->addError('You need to provide your email address!');
            return $validator;
        }

        if (!empty($this->Email) && !filter_var($this->Email, FILTER_VALIDATE_EMAIL)) {
            $validator->addError('It\'s not a valid email!');
            return $validator;
        }

        if (!$this->Event()->AllowGuests && $this->NumGuests > 0) {
            $validator->addError('The event doesn\'t allow bringing guests!');
            return $validator;
        }

        if ($this->Event()->AllowGuests) {

            if (!empty($this->Event()->MaxGuests)) {
                if ($this->NumGuests > $this->Event()->MaxGuests) {
                    $validator->addError('You cannot bring more than ' . $this->Event()->MaxGuests . ' guests!');
                    return $validator;
                }
            }

            if ($this->Event()->AttendeeLimit > 0) {
                if (!$this->Event()->has_enough_seats($this->NumGuests + 1, $this)) {
                    $validator->addError('Exceeded the event\'s attendee limit!');
                    return $validator;
                }
            }
        }

        $attend_at  =   gettype($this->AttendedAt) == 'integer' ? $this->AttendedAt : strtotime($this->AttendedAt);

        if (strtotime($this->Event()->EventStart) > $attend_at) {
            $validator->addError('The event has not yet started!');
            return $validator;
        }

        return $validator;
    }

    public function isMember()
    {
        return $this->Member()->exists() ? 'Yes' : 'No';
    }

    /**
     * Event handler called before writing to the database.
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!empty($this->Email) && !$this->Member()->exists()) {
            if ($member = Member::get()->filter(['Email' => $this->Email])->first()) {
                $this->MemberID =   $member->ID;
            }
        } elseif ($this->Member()->exists() && empty($this->Email)) {
            $this->Email    =   $this->Member()->Email;
        }

        if (empty($this->QRToken)) {
            $this->QRToken  =   'rsvp-' . sha1(time() . mt_rand() . $this->Email);
        }

        if (empty($this->FirstName) && empty($this->Surname) && $this->Member()->exists()) {
            $this->FirstName    =   $this->Member()->FirstName;
            $this->Surname      =   $this->Member()->Surname;
        }
    }

    public function getTitle()
    {
        if ($this->Member()->exists()) {
            return $this->Member()->Title;
        }

        if (!empty($this->Email)) {
            return $this->Email;
        }

        return null;
    }

    public function Title()
    {
        return $this->getTitle();
    }

    public function get_portrait($s = 80, $d = 'mm', $r = 'g')
    {
        $url    =   'https://www.gravatar.com/avatar/';
        $url    .=  md5( strtolower( trim( $this->Email ) ) );
        $url    .=  "?s=$s&d=$d&r=$r";

        $url    =   $this->extend('updatePortrait', $url)[0];

        return $url;
    }

    public function get_fullname()
    {
        return trim($this->FirstName . ' ' . $this->Surname);
    }

    public function get_ical_string()
    {
        if (!$this->Event()->exists()) return null;

        $event_location =   $this->Event()->Location->exists() ? $this->Event()->Location()->get_location_string() : '';
        $lat            =   $this->Event()->Location->exists() ? $this->Event()->Location()->Lat : -41.27;
        $lng            =   $this->Event()->Location->exists() ? $this->Event()->Location()->Lat : 174.77;
        $rsvp_created   =   date('Ymd\THis\Z', strtotime($this->Created));
        $event_created  =   date('Ymd\THis\Z', strtotime($this->Event()->Created));
        $event_modified =   date('Ymd\THis\Z', strtotime($this->Event()->LastEdited));
        $event_stasrt   =   date('Ymd\THis', strtotime($this->Event()->EventStart));
        $event_end      =   date('Ymd\THis', strtotime($this->Event()->EventEnd));
        $summary        =   wordwrap($this->Event()->Title, 72, "\n", true);
        $short_desc     =   wordwrap($this->Event()->ShortDesc, 72, "\n", true);
        $link           =   $this->Event()->AbsoluteLink();
        $event_id       =   'event_' . $this->Event()->ID . '@cita.org.nz';
        $sequence       =   $this->Event()->Version;
        $ical = <<< ICALSTRING
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//CITANZ//CITANZ Events v1.0//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:Events - CITANZ
X-MS-OLK-FORCEINSPECTOROPEN:TRUE
BEGIN:VTIMEZONE
TZID:Pacific/Auckland
TZURL:http://tzurl.org/zoneinfo-outlook/Pacific/Auckland
X-LIC-LOCATION:Pacific/Auckland
BEGIN:DAYLIGHT
TZOFFSETFROM:+1200
TZOFFSETTO:+1300
TZNAME:NZDT
DTSTART:19700927T020000
RRULE:FREQ=YEARLY;BYMONTH=9;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+1300
TZOFFSETTO:+1200
TZNAME:NZST
DTSTART:19700405T030000
RRULE:FREQ=YEARLY;BYMONTH=4;BYDAY=1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
DTSTAMP:{$rsvp_created}
DTSTART;TZID=Pacific/Auckland:{$event_stasrt}
DTEND;TZID=Pacific/Auckland:{$event_end}
STATUS:CONFIRMED
SUMMARY:{$summary}
DESCRIPTION:{$short_desc}
ORGANIZER;CN=CITANZ Reminder:MAILTO:info@cita.org.nz
CLASS:PUBLIC
CREATED:{$event_created}
GEO:$lat;$lng
LOCATION:{$event_location}
URL:{$link}
SEQUENCE:{$sequence}
LAST-MODIFIED:{$event_modified}
UID:{$event_id}
END:VEVENT
END:VCALENDAR
ICALSTRING;
        return $ical;
    }
}
