<?php

namespace App\APIs;

use Carbon\Carbon;
use Faker\Factory;
use App\Contracts\PatientDataAPI;

class FakePatientAPI implements PatientDataAPI
{
    private $faker;

    public function __construct()
    {
        $this->faker = Factory::create();
    }

    public function getPatient($hn)
    {
        $gender = ($hn % 2 === 0) ? 'female' : 'male' ;
        $title = $this->faker->title($gender);
        $fname = $gender ? $this->faker->firstNameFemale : $this->faker->firstNameMale;
        $lname = $this->faker->lastName;
        return [
            'ok' => true,
            'found' => true,
            'alive' => true,
            'hn' => $hn,
            'patient_name' => "{$title} {$fname} {$lname}",
            'title' => $title,
            'first_name' => $fname,
            'middle_name' => null,
            'last_name' => $lname,
            'document_id' => $this->faker->ean13,
            'dob' => $this->faker->date('Y-m-d', Carbon::now()->subYears(19)),
            'gender' => $gender,
            'race' => 'นิวหยวก',
            'nation' => 'นิวหยวก',
            'tel_no' => $this->faker->e164PhoneNumber,
            'spouse' => null,
            'address' => $this->faker->streetAddress,
            'postcode' => $this->faker->postcode,
            'province' => $this->faker->city,
            'insurance_name' => 'ผู้ป่วยทั่วไป',
            'marital_status' => null,
            'alternative_contact' => null,
        ];
    }

    public function getAdmission($an)
    {
        $gender = ($an % 2);
        $stay = $this->faker->numberBetween(1, 28); // random length of stay from 1 to 28 days
        $hn = $an - 666666;
        $patient = $this->getPatient($hn);
        return [
            'ok' => true,
            'found' => true,
            'alive' => true,
            'hn' => $patient['hn'],
            'an' => $an,
            'dob' => $patient['dob'],
            'gender' => $patient['gender'],
            'patient_name' => $patient['patient_name'],
            'patient' => $patient,
            'ward_name' => 'สามัญสำนึก',
            'ward_name_short' => 'สามัญ',
            'admitted_at' => Carbon::now()->subDays($stay)->toDateTimeString(),
            'discharged_at' => Carbon::now()->subDays($this->faker->numberBetween(1, $stay))->toDateTimeString(),
            'attending' => null,
            'attending_license_no' => null,
            'discharge_type' => 'WITH APPROVAL',
            'discharge_status' => 'IMPROVED',
            'department' => null,
            'division' => null,
        ];
    }

    /**
     * Query patient admissions from api by $hn.
     *
     * @param string
     * @return array
     */
    public function getPatientAdmissions($hn)
    {
        return ['ok' => true, 'found' => false, 'body' => 'not found'];
    }

    /**
     * Query lastest admission data from api by $hn.
     *
     * @param string
     * @return array
     */
    public function getPatientRecentlyAdmit($hn)
    {
        return $this->getAdmission($hn + 666666);
    }
}
