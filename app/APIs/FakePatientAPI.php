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

    public function getAdmission($an)
    {
        $gender = ($an % 2);
        $stay = $this->faker->numberBetween(1, 28); // random length of stay from 1 to 28 days
        $hn = $an - 666666;
        $patient = $this->getPatient($hn)['profile'];
        return [
            'reply_code' => 1,
            'reply_text' => 'OK',
            'an' => $an,
            'hn' => $hn,
            'dob' => $patient['dob'],
            'gender' => $patient['gender'] == 1 ? 'male':'female',
            'patient_name' => $patient['patient_name'],
            'patient' => [
                'reply_code' => 1,
                'reply_text' => 'OK',
                'hn' => $hn,
                'profile' => [
                    'document_id' => $patient['document_id'],
                    'gender' => $patient['gender'],
                    'dob' => $patient['dob'],
                    'title' => $patient['title'],
                    'first_name' => $patient['first_name'],
                    'last_name' => $patient['last_name'],
                    'tel_no' => $patient['tel_no'],
                    'alternative_contact' => $patient['alternative_contact'],
                    'insurance_name' => $patient['insurance_name'],
                ]
            ],
            'encountered_at' => Carbon::now()->subDays($stay)->toDateTimeString(),
            'dismissed_at' => Carbon::now()->subDays($this->faker->numberBetween(1, $stay))->toDateTimeString(),
        ];
    }
    public function getPatient($hn)
    {
        $gender = ($hn % 2 === 0) ? 'female' : 'male' ;
        $data['reply_code'] = 1;
        $data['reply_text'] = 'OK';
        $data['hn'] = $hn;
        $data['profile']['document_id'] = $this->faker->ean13; // random 13 digits
        $data['profile']['gender'] = $gender;
        $data['profile']['dob'] = $this->faker->date('Y-m-d', Carbon::now()->subYears(19));
        $data['profile']['tel_no'] = $this->faker->e164PhoneNumber;
        $data['profile']['alternative_contact'] = $this->faker->name($gender === 'female' ? 'male' : 'female') . ', ' . $this->faker->e164PhoneNumber;
        $data['profile']['title'] = $this->faker->title($gender);
        $data['profile']['first_name'] = $gender ? $this->faker->firstNameFemale : $this->faker->firstNameMale;
        $data['profile']['last_name'] = $this->faker->lastName;
        $data['profile']['patient_name'] = "{$data['profile']['title']} {$data['profile']['first_name']} {$data['profile']['last_name']}";
        $data['profile']['insurance_name'] = 'UC';

        return $data;
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
