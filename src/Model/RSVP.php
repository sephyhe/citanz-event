<?php

namespace Leochenftw\SSEvent\Model;
use SilverStripe\Security\Member;
use SilverStripe\ORM\DataObject;
use Leochenftw\SSEvent\Layout\EventPage;

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
        'Email'     =>  'Varchar(256)',
        'NumGuests' =>  'Int',
        'QRToken'   =>  'Varchar(40)'
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
    private static $default_sort = ['Created' => 'DESC'];

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

    public function populateDefaults()
    {
        if ($member = Member::currentUser()) {
            $this->MemberID =   $member->ID;
        }
    }

    public function validate()
    {
        $validator  =   parent::validate();

        if (!$this->Event()->exists()) {
            $validator->addError('You must respond to an event!');
            return $validator;
        }

        if (!$this->Event()->AllowGuests && $this->NumGuests > 0) {
            $validator->addError('The event doesn\'t allow bringing guests!');
            return $validator;
        }

        if ($this->Event()->AllowGuests) {

            if ($this->NumGuests > 5) {
                $validator->addError('You cannot bring more than 5 guests!');
                return $validator;
            }

            if ($this->Event()->AttendeeLimit > 0) {
                if (!$this->Event()->has_enough_seats($this->NumGuests + 1, $this)) {
                    $validator->addError('Exceeded the event\'s attendee limit!');
                    return $validator;
                }
            }
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
        if ($this->Member()->exists()) {
            $this->Email    =   $this->Member()->Email;
        } elseif (!empty($this->Email)) {
            if ($member = Member::get()->filter(['Email' => $this->Email])->first()) {
                $this->MemberID =   $member->ID;
            }
        }

        if (empty($this->QRToken)) {
            $this->QRToken  =   sha1(time() . mt_rand() . $this->Email);
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
}
