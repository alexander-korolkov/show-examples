<?php

namespace Fxtm\CopyTrading\Application\Metrix;

class InfluxMetrixService implements MetrixService
{
    /**
     * @var \InfluxDB\Database
     */
    private $influx;

    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $port;

    /**
     * @var string
     */
    private $user;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $dbname;

    /**
     * InfluxMetrixService constructor.
     * @param string $host
     * @param string $port
     * @param string $user
     * @param string $password
     * @param string $dbname
     */
    public function __construct($host, $port, $user, $password, $dbname)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
        $this->dbname = $dbname;
    }

    /**
     * @throws \InfluxDB\Client\Exception
     */
    private function connectToInflux()
    {
        $this->influx = \InfluxDB\Client::fromDSN(
            sprintf(
                'udp+influxdb://%s:%s@%s:%s/%s',
                $this->user,
                $this->password,
                $this->host,
                $this->port,
                $this->dbname
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function write($reporter, $value, $tags = [], $additional = [], $timestamp = null)
    {
        if (!$this->influx) {
            $this->connectToInflux();
        }

        if (MetrixData::getWorker()) {
            $reporter .= '::' . MetrixData::getWorker();
        }

        $points = [
            new \InfluxDB\Point(
                $reporter,
                $value,
                $tags,
                $additional,
                $timestamp
            ),
        ];

        $this->influx->writePoints($points, \InfluxDB\Database::PRECISION_NANOSECONDS);
    }
}
