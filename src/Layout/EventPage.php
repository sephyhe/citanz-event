<?php

namespace Cita\Event\Layout;
use Cita\Event\Model\EventLocation;
use gorriecoe\LinkField\LinkField;
use gorriecoe\Link\Models\Link;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\HTMLEditor\HtmlEditorField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Member;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Assets\Image;
use SilverShop\HasOneField\HasOneButtonField;
use Cita\Event\Model\RSVP;
use \SilverStripe\Forms\GridField\GridField;
use \SilverStripe\Forms\GridField\GridFieldConfig_Base;
use \SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use \SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use Bummzack\SortableFile\Forms\SortableUploadField;
use Page;

/**
 * Description
 *
 * @package silverstripe
 * @subpackage mysite
 */
class EventPage extends Page
{
    private static $icon = 'cita/citanz-event: client/img/event.png';
    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'EventPage';
    private static $description = 'Like the name says: an event Page :)';

    /**
     * Database fields
     * @var array
     */
    private static $db = [
        'ShortDesc'     =>  'Text',
        'QRToken'       =>  'Varchar(40)',
        'EventVideo'    =>  'HTMLText',
        'EventStart'    =>  'Datetime',
        'EventEnd'      =>  'Datetime',
        'AttendeeLimit' =>  'Int',
        'AllowGuests'   =>  'Boolean',
        'MaxGuests'     =>  'Int'
    ];

    /**
     * Default sort ordering
     * @var array
     */
    private static $default_sort = ['EventStart' => 'DESC'];

    public function populateDefaults()
    {
        $this->QRToken  =   sha1(time() . mt_rand() . mt_rand());
    }

    private static $indexes = [
        'QRToken'   =>  [
            'type'  =>  'unique'
        ]
    ];

    /**
     * Has_one relationship
     * @var array
     */
    private static $has_one = [
        'FeaturedImage' => Image::class,
        'Location' => EventLocation::class,
        'WebinarLink' => Link::class
    ];

    /**
     * Relationship version ownership
     * @var array
     */
    private static $owns = [
        'FeaturedImage',
        'EventPhotos'
    ];

    /**
     * Has_many relationship
     * @var array
     */
    private static $has_many = [
        'RSVPs'     =>  RSVP::class
    ];

    private static $cascade_deletes = [
        'RSVPs'
    ];

    /**
     * Many_many relationship
     * @var array
     */
    private static $many_many = [
        'EventPhotos'   =>  Image::class
    ];

    /**
     * Defines Database fields for the Many_many bridging table
     * @var array
     */
    private static $many_many_extraFields = [
        'EventPhotos' => [
            'SortOrder' => 'Int'
        ]
    ];

    /**
     * CMS Fields
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->fieldByName('Root.Main.Title')->setDescription('QR Code: ' . $this->AbsoluteLink() . 'turnup/' . $this->QRToken);
        $fields->removeByName([
            'WebinarLinkID'
        ]);
        $fields->addFieldsToTab(
            'Root.Main',
            [
                TextareaField::create(
                    'ShortDesc',
                    'Short Description'
                )->setDescription('It will  be used in the iCal file attached to the RSVP confirmation email. If you don\'t know what that is, leave it blank.'),
                UploadField::create(
                    'FeaturedImage',
                    'FeaturedImage'
                ),
                DatetimeField::create(
                    'EventStart',
                    'Start'
                ),
                DatetimeField::create(
                    'EventEnd',
                    'End'
                ),
                TextField::create(
                    'AttendeeLimit',
                    'Attendee Limit'
                )->setDescription('0 means no limit.'),
                CheckboxField::create(
                    'AllowGuests',
                    'Allow Guests'
                ),
                TextField::create(
                    'MaxGuests',
                    'Max. number of guests can a RSVP bring'
                ),
                HasOneButtonField::create($this, "Location"),
                LinkField::create(
                    'WebinarLink',
                    'External Link',
                    $this
                )->setDescription('e.g. a link going to the online webinar address')
            ],
            'URLSegment'
        );

        $fields->addFieldToTab(
            'Root.RSVPs',
            $gf = GridField::create('RSVPs', 'RSVPs', $this->RSVPs())
        );

        if (Member::currentUser() && Member::currentUser()->isDefaultadmin()) {
            $gf->setConfig(GridFieldConfig_RecordEditor::create());
        } else {
            $gf->setConfig(GridFieldConfig_RecordViewer::create());
        }

        $fields->addFieldsToTab(
            'Root.EventGallery',
            [
                SortableUploadField::create(
                    'EventPhotos', 'Event photo gallery'
                )->setDescription('Photos taken during the event'),
                HtmlEditorField::create(
                    'EventVideo',
                    'Event Video'
                )->setDescription('The video taken during the event')
            ]
        );

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }

    public function has_enough_seats($n, $rsvp)
    {
        return $this->AttendeeLimit - $this->get_total_attendee_count($rsvp) - $n >= 0;
    }

    public function get_total_attendee_count($exclude = null)
    {
        $n      =   0;
        if (!empty($exclude)) {
            if ($exclude->exists()) {
                $rsvps  =   $this->RSVPs()->exclude(['ID' => $exclude->ID]);
            } else {
                $rsvps  =   $this->RSVPs();
            }
        } else {
            $rsvps  =   $this->RSVPs();
        }

        foreach ($rsvps as $rsvp) {
            $n += ($rsvp->NumGuests + 1);
        }

        return $n;
    }

    public function already_signed_up()
    {
        if ($member = Member::currentUser()) {
            return $this->RSVPs()->filter(['MemberID' => $member->ID])->first();
        }

        return false;
    }
}
