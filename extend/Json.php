<?php
/**
 * Created by PhpStorm.
 * User: Mloong
 * Date: 2018/4/25
 * Time: 16:53
 */



class Json
{
    protected static $data = [
        'data'      => [],              // 数据集 -》bunch-》row-》series
//        'bunchs'    => [],              // 数据聚合依据，例如chart1中使用的是station
        'rows'      => [],              // 数据列，例如chart1中使用的是GHIsum/GTIsum
        'series'    => [],              // 数据分划，即每条数据，在chart1中使用的是time作为数据分划
    ];

    protected static $config = [
        'storage'   => [
            'root'      => './static',
            'project'   => 'site',
            'module'    => 'json',
            'name'      => '',
        ],
        'type'      => 'json',
        'dataType'  => 'array',
    ];

    protected static $source = '';

    public function __construct($cnf)
    {
        foreach ($cnf as $index => $item) {
            if (!empty($item)) {
                self::$config[$index] = array_merge(self::$config[$index],$cnf[$index]);
            }
        }
        $path = implode('/',self::$config['storage']).'.'.self::$config['type'];
        self::$source = file_get_contents($path);
        self::$data = json_decode(self::$source,true);
    }


    public function __toArray($str)
    {
        return json_decode($str,true);
    }

    public function __toObject($str)
    {
        return json_decode($str);
    }

    /***
     * @usage 判断基站ID是否存储于该json数据串中
     * @param $stationID    基站ID,存储于数据库中的数据
     * @return bool         返回判断结果
     */

    public function isBunchExit($bunchID,$bunchCol = 'stationID')
    {
        $bunchs = self::$data['bunchs'];
        foreach ($bunchs as $key => $value) {
            if ($bunchID == $value[$bunchCol]) {
                $res = true;
                break;
            }
        }
        return isset($res);
    }

    public function getBunchName($bunchID,$bunchCol = 'stationID',$bunchName = 'stationName')
    {
        $bunchs = self::$data['bunchs'];
        foreach ($bunchs as $key => $value) {
            if ($bunchID == $value[$bunchCol]) {
                $res = $value[$bunchName];
                break;
            }
        }
        return empty($res) ? false : $res;
    }

    public function getBunchIndex($bunchID,$bunchCol = 'stationID')
    {
        $bunchs = self::$data['bunchs'];
        foreach ($bunchs as $key => $value) {
            if ($bunchID == $value[$bunchCol]) {
                $res = $key;
                break;
            }
        }
        return empty($res) ? false : $res;
    }


    public function getBunchData($bunchIndex,$row)
    {
        return self::$data['data'][$bunchIndex][$row];
    }

    public function getRows()
    {
        return self::$data['rows'];
    }

    public function setBunchs($bunchs, $bunchCol = 'stationID', $bunchName = 'stationName')
    {
        foreach ($bunchs as $key => $value) {
            $bunch = [
                $bunchCol => $value['station_id'],
                $bunchName => $value['station_name'],
            ];
            // 没有在数据中的站点将会被加入进数组
            if (!(in_array($bunch,self::$data['bunchs']))) {
                // 取得站点数据的长度以保持站点数据的一致性
                $length = count(self::$data['bunchs']);
                self::$data['bunchs'][$length] = $bunch;
            }

        }
    }

    public function setRows($rows)
    {
        if (!empty($rows)) {
            foreach ($rows as $k => $v) {
                if (!in_array($v,self::$data['rows'])) {
                    $length = count(self::$data['rows']);
                    self::$data['rows'][$length] = $v;
                }
            }
        }
    }






}