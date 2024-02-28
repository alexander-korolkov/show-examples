<?php

namespace Fxtm\CopyTrading\Application\Monitoring;

class IcingaMonitoringService implements MonitoringService
{
    /**
     * @var string
     */
    private $icingaUrl;

    /**
     * @var string
     */
    private $icingaUser;

    /**
     * @var string
     */
    private $icingaPassword;

    /**
     * @var string
     */
    private $icingaHostName;

    /**
     * @var string
     */
    private $icingaServiceName;

    /**
     * IcingaMonitoringService constructor.
     * @param string $icingaUrl
     * @param string $icingaUser
     * @param string $icingaPassword
     * @param string $icingaServiceName
     */
    public function __construct(
        string $icingaUrl,
        string $icingaUser,
        string $icingaPassword,
        string $icingaServiceName
    ) {
        $this->icingaUrl = $icingaUrl;
        $this->icingaUser = $icingaUser;
        $this->icingaPassword = $icingaPassword;
        $this->icingaHostName = gethostname();
        $this->icingaServiceName = $icingaServiceName;
    }


    /**
     * {@inheritdoc}
     */
    public function alert(string $message): bool
    {
        $data = [
            "exit_status" => 2,
            "plugin_output" => $message,
            "performance_data" => [],
        ];

        return $this->sendCurl($data);
    }

    /**
     * Sends curl request to icinga
     * Returns true if everything is fine
     *
     * @param array $postFields
     * @return bool
     */
    private function sendCurl(array $postFields) : bool
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->icingaUrl . rawurlencode($this->icingaHostName . '!' . $this->icingaServiceName),
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERPWD => $this->icingaUser . ":" . $this->icingaPassword,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => count($postFields),
            CURLOPT_POSTFIELDS => json_encode($postFields),
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return $status == 200;
    }
}
