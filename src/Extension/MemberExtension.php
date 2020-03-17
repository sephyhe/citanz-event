<?php

namespace Cita\Event\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataExtension;
use Leochenftw\Debugger;
use Cita\Event\Model\RSVP;

class MemberExtension extends DataExtension
{
    /**
     * Has_many relationship
     * @var array
     */
    private static $has_many = [
        'RSVPs' =>  RSVP::class
    ];

    private static $cascade_deletes = [
        'RSVPs'
    ];
}
