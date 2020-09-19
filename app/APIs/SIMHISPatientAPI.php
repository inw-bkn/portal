<?php

namespace App\APIs;

use App\Contracts\PatientDataAPI;

class SIMHISPatientAPI implements PatientDataAPI
{
    public function getPatient($hn)
    {
        $functionname = config('app.SIMHIS_PATIENT_FUNCNAME');
        $action = "http://tempuri.org/" . $functionname;

        // Compose SOAP string.
        $strSOAP = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
        $strSOAP .= "<soap:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\">";
        $strSOAP .= "<soap:Body>";
        $strSOAP .= "<" . $functionname . " xmlns=\"http://tempuri.org/\">";
        $strSOAP .= "<hn>" . $hn . "</hn>";
        $strSOAP .= "<Username>" . config('app.SIMHIS_API_USERNAME') . "</Username>";
        $strSOAP .= "<Password>" . config('app.SIMHIS_API_PASSWORD') . "</Password>";
        $strSOAP .= "<RequestComputerName></RequestComputerName>";
        $strSOAP .= "</" . $functionname . ">";
        $strSOAP .= "</soap:Body>";
        $strSOAP .= "</soap:Envelope>";

        // Send the request and check the response.
        if (($response = $this->executeCurl($strSOAP, $action, env('SIRIRAJ_API_PATIENT_URL'))) === false) {
            return [
                'ok' => false,
                'status' => 500,
                'error' => 'server',
                'body' => 'Server Error'
            ];
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

        return $response;
        // map json response to array $tmp.
        foreach ($response as $key => $value) {
            $tmp[$key] = implode("", json_decode(json_encode($value, true), true));
        }

        $return_code_text = ['success.', 'hn not found.', 'hn cancel.', 'dead.', 'request error.', 'not allow.'];

        $reply['reply_code'] = $tmp['return_code'];
        $reply['reply_text'] = $return_code_text[$tmp['return_code']];

        if ($tmp['return_code'] == 0) {
            $reply['hn'] = $tmp['hn'];
            $reply['dob'] = $this->castDateFormat(trim($tmp['birth_date']));
            $reply['race'] = trim($tmp['race_name']);
            $reply['title'] = trim($tmp['title']);
            $reply['tel_no'] = trim($tmp['present_tele_no'] . " " . $tmp['mobile_no']);
            $reply['gender'] = $tmp['sex'] == 'หญิง' ? 0 : 1;
            $reply['nation'] = trim($tmp['nationality_name']);
            $reply['spouse'] = trim($tmp['marrier_name']);
            $reply['address'] = trim($tmp['present_address']);
            $reply['location'] = trim($tmp['zipcode'] . " " . $tmp['tambon']);
            $reply['province'] = trim($tmp['province']);
            $reply['last_name'] = trim($tmp['patient_surname']);
            $reply['first_name'] = trim($tmp['patient_firstname']);
            $reply['middle_name'] = trim($tmp['patient_middlename']);
            $reply['document_id'] = trim($tmp['identity_card_no']);
            $reply['patient_name'] = trim($tmp['patient_firstname']) . ' ' . trim($tmp['patient_surname']);
            $reply['insurance_name'] = trim($tmp['patient_type_name']);
            $reply['marital_status_name'] = trim($tmp['marriage_stat_name']);
            $reply['alternative_contact'] = trim($tmp['connected_relation_name'] . " " .
                $tmp['connected_name'] . " " .
                $tmp['connected_tele_no']);
        }

        return $reply;
    }

    public function getAdmission($an)
    {
        // Assign function name.
        $functionname = config('app.SIMHIS_ADMISSION_FUNCNAME');

        // The value for the SOAPAction: header
        $action = "http://tempuri.org/" . $functionname;

        // Compose SOAP string.
        $strSOAP = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
        $strSOAP .= "<soap:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\">";
        $strSOAP .= "<soap:Body>";
        $strSOAP .= "<" . $functionname . " xmlns=\"http://tempuri.org/\">";
        $strSOAP .= "<AN>" . $an . "</AN>";
        $strSOAP .= "<UserName>" . config('app.SIMHIS_API_USERNAME') . "</UserName>";
        $strSOAP .= "<Password>" . config('app.SIMHIS_API_PASSWORD') . "</Password>";
        $strSOAP .= "<RequestComputerName></RequestComputerName>";
        $strSOAP .= "</" . $functionname . ">";
        $strSOAP .= "</soap:Body>";
        $strSOAP .= "</soap:Envelope>";

        // Send the request and check the response.
        if (($response = $this->executeCurl($strSOAP, $action, env('SIRIRAJ_API_PATIENT_URL'))) === false) {
            return [
                'ok' => false,
                'status' => 500,
                'error' => 'server',
                'body' => 'Server Error'
            ];
        }

        $xml = simplexml_load_string($response);
        $namespaces = $xml->getNamespaces(TRUE);

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

        return $response;
        foreach ($response as $key => $value)
            $tmp[$key] = implode("", json_decode(json_encode($value, TRUE), TRUE));

        return $this->handleAdmitData($tmp);
    }

    public function getPatientRecentlyAdmit($hn)
    {
        // Assign function name.
        $functionname = config('app.SIMHIS_PATIENT_ADMISSIONS_FUNCNAME');

        // The value for the SOAPAction: header
        $action = "http://tempuri.org/" . $functionname;

        // Compose SOAP string.
        $strSOAP = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
        $strSOAP .= "<soap:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\">";
        $strSOAP .= "<soap:Body>";
        $strSOAP .= "<" . $functionname . " xmlns=\"http://tempuri.org/\">";
        $strSOAP .= "<HN>" . $hn . "</HN>";
        $strSOAP .= "<UserName>" . config('app.SIMHIS_API_USERNAME') . "</UserName>";
        $strSOAP .= "<Password>" . config('app.SIMHIS_API_PASSWORD') . "</Password>";
        $strSOAP .= "<RequestComputerName></RequestComputerName>";
        $strSOAP .= "</" . $functionname . ">";
        $strSOAP .= "</soap:Body>";
        $strSOAP .= "</soap:Envelope>";

        // Send the request and check the response.
        if (($response = $this->executeCurl($strSOAP, $action, env('SIRIRAJ_API_PATIENT_URL'))) === false) {
            return [
                'ok' => false,
                'status' => 500,
                'error' => 'server',
                'body' => 'Server Error'
            ];
        }

        $xml = simplexml_load_string($response);
        $namespaces = $xml->getNamespaces(TRUE);

        $responses = $xml->children($namespaces['soap'])
            ->Body
            ->children($namespaces[""])
            ->SearchInpatientAllResponse
            ->SearchInpatientAllResult
            ->children($namespaces['diffgr'])
            ->diffgram
            ->children()
            ->Result
            ->children();

        return $responses;
        if ($responses == null || count($responses) == 0) {
            return ['reply_code' => 6, 'reply_text' => 'admission record not found'];
        } else {
            $response = $responses[count($responses) - 1]; // get lastest admission
            foreach ($response as $key => $value)
                $tmp[$key] = implode("", json_decode(json_encode($value, TRUE), TRUE));

            return $this->handleAdmitData($tmp);
        }
    }

    protected function executeCurl($strSOAP, $action, $url)
    {
        $headers = [
            "Host: " . config('app.SIMHIS_SERVICE_HOST'),
            "Content-Type: text/xml; charset=utf-8",
            "SOAPAction: \"" . $action . "\"",
            "Transfer-Encoding: chunked",
        ];

        // Build the cURL session.
        $ch = curl_init();
        // curl_setopt($ch, CURLOPT_VERBOSE, true); // for debug
        curl_setopt($ch, CURLOPT_URL, $url);
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

        return $response;
    }

    protected function handleAdmitData($tmp)
    {
        $return_code_text = ['success.', 'an not found.', 'an cancel.', 'dead.', 'requet error.', 'not allow.'];
        if ($tmp['return_code'] != 0) {
            return [
                'reply_code' => $tmp['return_code'],
                'reply_text' => $return_code_text[$tmp['return_code']],
            ];
        }

        $reply = [
            'hn'  => $tmp['hn'],
            'an'  => $tmp['an'],
            'ward_name'  => trim($tmp['ward_name']),
            'reply_code' => $tmp['return_code'],
            'reply_text' => $return_code_text[$tmp['return_code']],
            'datetime_dc' => $this->castSirirajDateTimeFormat(trim($tmp['discharge_date']), trim($tmp['discharge_time'])),
            'patient_dept'  => $tmp['patient_dept'],
            'datetime_admit' => $this->castSirirajDateTimeFormat(trim($tmp['admission_date']), trim($tmp['admission_time'])),
            'attending_pln' => trim($tmp['refer_doctor_code']),
            'attending_name'  => trim($tmp['doctor_name']),
            'discharge_type'  => $tmp['discharge_type'],
            'ward_name_short'  => trim($tmp['ward_brief_name']),
            'discharge_status'  => $tmp['discharge_status'],
            'patient_sub_dept'  => $tmp['patient_sub_dept'],
            'patient_dept_name'  => $tmp['patient_dept_name'],
            'discharge_type_name'  => $tmp['discharge_type_name'],
            'discharge_status_name'  => $tmp['discharge_status_name'],
            'patient_sub_dept_name'  => $tmp['patient_sub_dept_name'],
        ];

        sleep(1);
        $patient = $this->getPatient($tmp['hn']);
        $reply['dob'] = $patient['dob'];
        $reply['gender'] = $patient['gender'];
        $reply['patient_name'] = $patient['title'] . ' ' . $patient['patient_name'];
        $reply['patient'] = $patient;

        return $reply;
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
}
