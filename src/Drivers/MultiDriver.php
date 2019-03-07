<?php

namespace Goez\RedisDataHelper\Drivers;

class MultiDriver extends AbstractDriver
{
    /**
     * @var bool
     */
    private $withKey = false;

    /**
     * 每次 SCAN 時的 COUNT 數
     *
     * @var int
     */
    private $countPerScan;

    /**
     * @return MultiDriver
     */
    public function withKey()
    {
        $this->withKey = true;
        return $this;
    }

    /**
     * @param $number
     * @return MultiDriver
     */
    public function countPerScan($number)
    {
        $this->countPerScan = (int) $number;
        return $this;
    }

    /**
     * 取得設定的 countPerScan，不存在則用 ({key 總數量} / 5)
     *
     * @return int
     */
    private function getCountPerScan()
    {
        if (!$this->countPerScan) {
            // default value
            $totalKeys = $this->client->dbsize();
            return ceil($totalKeys / 6);
        }

        return $this->countPerScan;
    }

    /**
     * @return int
     */
    public function delete()
    {
        $keys = $this->getKeys();
        if (!empty($keys)) {
            return $this->client->del($keys);
        }
        return 0;
    }

    /**
     * @return array
     */
    public function keys()
    {
        return $this->getKeys();
    }

    /**
     * @param callable|null $callback
     * @return array
     */
    public function get(callable $callback = null)
    {
        $keys = $this->getKeys();
        if (empty($keys)) {
            return [];
        }
        $result = $this->client->mget($keys);
        $values = array_map(function ($item) use ($callback) {
            $value = json_decode($item, true);
            return is_callable($callback) ? $callback($value) : $value;
        }, $result);
        return $this->withKey ? array_combine($keys, $values) : $values;
    }

    /**
     * @return int
     */
    public function count()
    {
        $keys = (array) $this->getKeys();
        return count($keys);
    }

    /**
     * Get target keys (array) or search by pattern (string)
     *
     * @return array
     */
    private function getKeys()
    {
        return is_array($this->key) ?
            $this->key :
            $this->scanAll();
    }

    /**
     * @return array
     */
    private function scanAll()
    {
        $countPerScan = $this->getCountPerScan();
        if (empty($this->key)) {
            return [];
        }

        $cursor = '0';
        $bufferArray = [];
        $options = [
            'MATCH' => $this->key,
            'COUNT' => $countPerScan,
        ];

        do {
            list($cursor, $result) = $this->client->scan($cursor, $options);
            $bufferArray[] = $result;
        } while ($cursor !== '0');

        return $this->flattenArray($bufferArray);
    }

    /**
     * Flatten 2D array into 1D array
     * @param $array
     * @return array
     */
    private function flattenArray($array)
    {
        $result = [];
        foreach ($array as $item) {
            foreach ($item as $value) {
                $result[] = $value;
            }
        }

        return $result;
    }
}
