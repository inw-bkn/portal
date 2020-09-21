<?php

namespace App\APIs;

use App\Contracts\PatientDataAPI;

class SIMHISPatientAPI implements PatientDataAPI
{
    protected $patient;
    protected $serverError = [
                'ok' => false,
                'status' => 500,
                'error' => 'server',
                'body' => 'Server Error'
            ];

    public function getPatient($hn)
    {
        $functionname = config('app.SIMHIS_PATIENT_FUNCNAME');
        $action = "http://tempuri.org/" . $functionname;

        if (($response = $this->executeCurl($this->composeSOAP($functionname, 'hn', $hn), $action)) === false) {
            return $this->serverError;
        }

        $xml = simplexml_load_string($response);
        $namespaces = $xml->getNamespaces(true);
        $response = $xml->children($namespaces['soap'])
                        ->Body
                        ->children($namespaces[""])
                        ->SearchPatientDataDescriptionTypeExcludeDResponse
                        ->SearchPatientDataDescriptionTypeExcludeDResult
                        ->children($namespaces['diffgr'])
                        ->diffgram
                        ->children()
                        ->Result
                        ->children()
                        ->PatResult
                        ->children();

        $data = (array) $response;

        if (($reply = $this->noResult($data['return_code'])) !== false) {
            return $reply;
        }

        return [
            'ok' => true,
            'found' => true,
            'alive' => $this->patientAlive($hn),
            'hn' => $hn,
            'patient_name' => trim((!is_object($data['title']) ? trim($data['title']) : '') . ' ' . (!is_object($data['patient_firstname']) ? trim($data['patient_firstname']) : '') . ' ' . (!is_object($data['patient_surname']) ? trim($data['patient_surname']) : '')),
            'title' => !is_object($data['title']) ? trim($data['title']) : null,
            'first_name' => !is_object($data['patient_firstname']) ? trim($data['patient_firstname']) : null,
            'middle_name' => !is_object($data['patient_middlename']) ? trim($data['patient_middlename']) : null,
            'last_name' => !is_object($data['patient_surname']) ? trim($data['patient_surname']) : null,
            'document_id' => !is_object($data['identity_card_no']) ? trim($data['identity_card_no']) : null,
            'dob' => !is_object($data['birth_date']) ? $this->castDateFormat(trim($data['birth_date'])) : null,
            'gender' => !is_object($data['sex']) ? (trim($data['sex']) === 'หญิง' ? 'female' : 'male') : null,
            'race' => !is_object($data['race_name']) ? trim($data['race_name']) : null,
            'nation' => !is_object($data['nationality_name']) ? trim($data['nationality_name']) : null,
            'tel_no' => trim((!is_object($data['present_tele_no']) ? trim($data['present_tele_no']) : '') . ' ' . (!is_object($data['mobile_no']) ? trim($data['mobile_no']) : '')),
            'spouse' => !is_object($data['marrier_name']) ? trim($data['marrier_name']) : null,
            'address' => !is_object($data['present_address']) ? trim($data['present_address']) : null,
            'postcode' => !is_object($data['zipcode']) ? trim($data['zipcode']) : null,
            'province' => !is_object($data['province']) ? trim($data['province']) : null,
            'insurance_name' => !is_object($data['patient_type_name']) ? trim($data['patient_type_name']) : null,
            'marital_status' => !is_object($data['marriage_stat_name']) ? trim($data['marriage_stat_name']) : null,
            'alternative_contact' => trim((!is_object($data['connected_relation_name']) ? trim($data['connected_relation_name']) : '') . ' ' . (!is_object($data['connected_name']) ? trim($data['connected_name']) : '') . ' ' . trim(!is_object($data['connected_tele_no']) ? $data['connected_tele_no'] : '')),
        ];
    }

    public function getAdmission($an)
    {
        $functionname = config('app.SIMHIS_ADMISSION_FUNCNAME');
        $action = "http://tempuri.org/" . $functionname;

        if (($response = $this->executeCurl($this->composeSOAP($functionname, 'AN', $an, 'UserName'), $action)) === false) {
            return $this->serverError;
        }

        $xml = simplexml_load_string($response);
        $namespaces = $xml->getNamespaces(true);
        $response = $xml->children($namespaces['soap'])
                        ->Body
                        ->children($namespaces[""])
                        ->SearchInpatientAllByANResponse
                        ->SearchInpatientAllByANResult
                        ->children($namespaces['diffgr'])
                        ->diffgram
                        ->children()
                        ->Result
                        ->children()
                        ->InpatientResult
                        ->children();

        $data = (array) $response;
        
        if (($reply = $this->noResult($data['return_code'])) !== false) {
            return $reply;
        }

        return $this->handleAdmitData($data);
    }

    public function getPatientAdmissions($hn)
    {
        $functionname = config('app.SIMHIS_PATIENT_ADMISSIONS_FUNCNAME');
        $action = "http://tempuri.org/" . $functionname;

        if (($response = $this->executeCurl($this->composeSOAP($functionname, 'HN', $hn, 'UserName'), $action)) === false) {
            return $this->serverError;
        }

        $xml = simplexml_load_string($response);
        $namespaces = $xml->getNamespaces(true);
        $response = $xml->children($namespaces['soap'])
                        ->Body
                        ->children($namespaces[""])
                        ->SearchInpatientAllResponse
                        ->SearchInpatientAllResult
                        ->children($namespaces['diffgr'])
                        ->diffgram
                        ->children()
                        ->Result
                        ->children();

        $admissions = ((array) $response)['InpatientResult'];
        $admissions = is_array($admissions) ?
                        array_map(function ($admission) { return (array) $admission; }, $admissions) :
                        [(array) $admissions];
        
        if (($reply = $this->noResult($admissions[0]['return_code'])) !== false) {
            return $reply;
        }
        
        $admissions = array_map(function ($admission) { return $this->handleAdmitData($admission); }, $admissions);
        return $admissions;
    }

    public function getPatientRecentlyAdmit($hn)
    {
        $admissions = $this->getPatientAdmissions($hn);
        if (isset($admissions['found']) && $admissions['found'] === false) {
            return $admissions;
        }

        return collect($admissions)->last();
    }

    protected function executeCurl($strSOAP, $action)
    {
        $headers = [
            "Host: " . config('app.SIMHIS_SERVICE_HOST'),
            "Content-Type: text/xml; charset=utf-8",
            "SOAPAction: \"" . $action . "\"",
            "Transfer-Encoding: chunked",
        ];

        $ch = curl_init();
        // curl_setopt($ch, CURLOPT_VERBOSE, true); // for debug
        curl_setopt($ch, CURLOPT_URL, config('app.SIMHIS_API_URL'));
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // set connection timeout.
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $strSOAP);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
        curl_setopt($ch, CURLOPT_USERPWD, config('app.SIMHIS_SERVER_USERNAME') . ":" . config('app.SIMHIS_SERVER_PASSWORD'));

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false || strpos($response, '<div id="header"><h1>Server Error</h1></div>') !== false) {
            return false;
        }

        return str_replace('&#x', '', $response);
    }

    protected function handleAdmitData($data)
    {
        if (!$this->patient) {
            $this->patient = $this->getPatient($data['hn']);
        }

        return [
            'ok' => true,
            'found' => true,
            'alive' => $data['return_code'] === '0',
            'hn' => $data['hn'],
            'an' => $data['an'],
            'dob' => $this->patient['dob'],
            'gender' => $this->patient['gender'],
            'patient_name' => $this->patient['patient_name'],
            'patient' => $this->patient,
            'ward_name' => !is_object($data['ward_name']) ? trim($data['ward_name']) : null,
            'ward_name_short' => !is_object($data['ward_brief_name']) ? trim($data['ward_brief_name']) : null,
            'admitted_at' => $this->castSirirajDateTimeFormat(!is_object($data['admission_date']) ? trim($data['admission_date']) : '', !is_object($data['admission_time']) ? trim($data['admission_time']) : ''),
            'discharged_at' => $this->castSirirajDateTimeFormat(!is_object($data['discharge_date']) ? trim($data['discharge_date']) : '', !is_object($data['discharge_time']) ? trim($data['discharge_time']) : ''),
            'attending' => !is_object($data['doctor_name']) ? trim($data['doctor_name']) : null,
            'attending_license_no' => !is_object($data['refer_doctor_code']) ? trim($data['refer_doctor_code']) : null,
            'discharge_type' => !is_object($data['discharge_type_name']) ? trim($data['discharge_type_name']) : null,
            'discharge_status' => !is_object($data['discharge_status_name']) ? trim($data['discharge_status_name']) : null,
            'department' => !is_object($data['patient_dept_name']) ? trim($data['patient_dept_name']) : null,
            'division' => !is_object($data['patient_sub_dept_name']) ? trim($data['patient_sub_dept_name']) : null,
        ];
    }

    private function castDateFormat($value)
    {
        if (strlen($value) == 8) {
            $yy = substr($value, 0, 4) - 543;
            $mm = substr($value, 4, 2) == '00' ? '07' : substr($value, 4, 2);
            $dd = substr($value, 6, 2) == '00' ? '15' : substr($value, 6, 2);
            return $yy . '-' . $mm . '-' . $dd;
        }

        return null;
    }

    private function castSirirajDateTimeFormat($datePart, $timePart)
    {
        if (strlen($datePart) == 8) {
            $yy = substr($datePart, 0, 4) - 543;
            $mm = substr($datePart, 4, 2) == '00' ? '07' : substr($datePart, 4, 2);
            $dd = substr($datePart, 6, 2) == '00' ? '15' : substr($datePart, 6, 2);

            $timePart = str_pad($timePart, 4, '0', STR_PAD_LEFT);

            $timePart = substr($timePart, 0, 2) . ':' . substr($timePart, 2);

            return $yy . '-' . $mm . '-' . $dd . ' ' . $timePart . ':00';
        }

        return null;
    }

    private function composeSOAP($functionname, $keyName, $keyValue, $userTag = 'Username')
    {
        $SOAPStr  = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
        $SOAPStr .= "<soap:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\">";
        $SOAPStr .= "<soap:Body>";
        $SOAPStr .= "<" . $functionname . " xmlns=\"http://tempuri.org/\">";
        $SOAPStr .= "<" . $keyName . ">" . $keyValue . "</" . $keyName . ">";
        $SOAPStr .= "<" . $userTag . ">" . config('app.SIMHIS_API_USERNAME') . "</" . $userTag . ">";
        $SOAPStr .= "<Password>" . config('app.SIMHIS_API_PASSWORD') . "</Password>";
        $SOAPStr .= "<RequestComputerName></RequestComputerName>";
        $SOAPStr .= "</" . $functionname . ">";
        $SOAPStr .= "</soap:Body>";
        $SOAPStr .= "</soap:Envelope>";

        return $SOAPStr;
    }

    private function noResult($code)
    {
        $reply = ['ok' => true, 'found' => false];
        switch ($code) {
            case '0': // found
                return false;
            case '3': // dead
                return false;
            case '1':
                $reply['body'] = 'not found';
                return $reply;
            case '2':
                $reply['body'] = 'cancel';
                return $reply;
            case '4':
                $reply['body'] = 'error';
                return $reply;
            case '9':
                $reply['body'] = 'not allowed';
                return $reply;
            default:
                return false;
        }
    }

    private function patientAlive($hn)
    {
        $functionname = config('app.SIMHIS_PATIENT_ALIVE_FUNCNAME');
        $action = "http://tempuri.org/" . $functionname;

        if (($response = $this->executeCurl($this->composeSOAP($functionname, 'hn', $hn), $action)) === false) {
            return $this->serverError;
        }

        $xml = simplexml_load_string($response);
        $namespaces = $xml->getNamespaces(true);
        $response = $xml->children($namespaces['soap'])
                        ->Body
                        ->children($namespaces[""])
                        ->SearchPatientDataResponse
                        ->SearchPatientDataResult
                        ->children($namespaces['diffgr'])
                        ->diffgram
                        ->children()
                        ->Result
                        ->children()
                        ->PatResult
                        ->children();

        $data = (array) $response;

        return $data['return_code'] === '0';
    }
}
