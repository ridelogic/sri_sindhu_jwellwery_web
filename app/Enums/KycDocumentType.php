<?php

namespace App\Enums;

enum KycDocumentType: string
{
    case Pan = 'pan';
    case Aadhaar = 'aadhaar';
    case Passport = 'passport';
    case VoterId = 'voter_id';
    case DrivingLicence = 'driving_licence';
    case AddressProof = 'address_proof';
    case Photo = 'photo';

    public function label(): string
    {
        return match ($this) {
            self::Pan => 'PAN Card',
            self::Aadhaar => 'Aadhaar',
            self::Passport => 'Passport',
            self::VoterId => 'Voter ID',
            self::DrivingLicence => 'Driving Licence',
            self::AddressProof => 'Address Proof',
            self::Photo => 'Photograph',
        };
    }
}
