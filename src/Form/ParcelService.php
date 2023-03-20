<?php

namespace GLS\Form;

use GLS\Form;
use Symfony\Component\Validator\Constraints as Assert;

class ParcelService extends Form
{
    public const T12 = 'T12';

    public const PSS = 'PSS';

    public const PRS = 'PRS';

    public const XS = 'XS';

    public const SZL = 'SZL';

    public const INS = 'INS';

    public const SBS = 'SBS';

    public const DDS = 'DDS';

    public const SDS = 'SDS';

    public const SAT = 'SAT';

    public const AOS = 'AOS';

    public const _24H = '24H';

    public const EXW = 'EXW';

    public const SM1 = 'SM1';

    public const SM2 = 'SM2';

    public const CS1 = 'CS1';

    public const TGS = 'TGS';

    public const FDS = 'FDS';

    public const FSS = 'FSS';

    public const PSD = 'PSD';

    public const DPV = 'DPV';

    /**
     * 3 letter service code, please see list of services in Appendix A.
     * @Assert\Choice(choices = {"T12", "PSS", "PRS", "XS", "SZL", "INS", "SBS", "DDS", "SDS", "SAT", "AOS", "24H", "EXW", "SM1", "SM2", "CS1", "TGS", "FDS", "FSS", "PSD", "DPV"})
     */
    protected $code;

    /**
     * parameter for service.
     */
    protected $info = '';

    /**
     * Set code.
     *
     * @param  mixed $code
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Set info.
     *
     * @param  mixed $info
     * @return $this
     */
    public function setInfo($info)
    {
        $this->info = $info;

        return $this;
    }
}
