<?php

namespace App\Contracts;

interface PatientDataAPI
{
    /**
     * Query patient data from api by $hn.
     *
     * @param string
     * @return array
     */
    public function getPatient($hn);

    /**
     * Query admission data from api by $an.
     *
     * @param string
     * @return array
     */
    public function getAdmission($an);

    /**
     * Query lastest admission data from api by $hn.
     *
     * @param string
     * @return array
     */
    public function getPatientRecentlyAdmit($hn);
}
