<?php


namespace Fxtm\CopyTrading\Application\Common;


use Memcache;

class MemcacheSemaphoreImpl implements Semaphore
{

    /**
     * @var Memcache
     */
    private $memcache;

    /**
     * @var string|null
     */
    private $id = null;

    /**
     * MemcacheSemaphoreImpl constructor.
     * @param $host
     * @param int $port
     */
    public function __construct($host, $port = 11211)
    {
        $this->memcache = new Memcache();
        $this->memcache->addserver($host, $port);
    }

    /**
     * Tries to get lock until timeout. Waits until resource is locked.
     * @param string $id resource identifier
     * @param int $timeout timeout
     * @return bool
     */
    public function acquire(string $id, int $timeout = 3): bool
    {

        if(empty($id)) {
            throw new \InvalidArgumentException('Resource id cant be empty');
        }

        if($timeout < 1) {
            throw new \InvalidArgumentException('Timeout cant be less than 1');
        }

        $startTime = time();
        while (!$this->memcache->add($id, 1, null, 60)) {
            sleep(1);
            if(time() - $startTime > $timeout) {
                return false;
            }
        }
        $this->id = $id;
        return true;
    }

    /**
     * Releases lock from resource with $id; Resource must be locked by this semaphore otherwise LogicException
     *  will be thrown
     * @param string $id resource identifier
     */
    public function release(string $id)
    {

        if(empty($id)) {
            throw new \InvalidArgumentException('Resource id cant be empty');
        }

        if($this->id != $id) {
            throw new \LogicException("The instance does hold lock of resource {$id}");
        }

        $this->memcache->delete($id);

        $this->id = null;
    }

}