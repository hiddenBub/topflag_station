<?php
/**
 * Created by PhpStorm.
 * User: Mloong
 * Date: 2018/4/18
 * Time: 20:03
 */

namespace app\cmd\command;


use app\model\Station;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use app\model\Source;
use app\model\station\StationData;
use app\model\station\StationDataHumiture;
use app\model\station\StationDataIrradiance;
use app\model\station\StationDataWind;
use DateTime;

class Test extends Command
{
    private $config = [
        'protocol'  => 'https',
        'account'   => 'topflag_wang',
        'password'  => 'VYITzqj5s8N3b',
        'host'      => 'api.meteomatics.com',
        'time'      => '',
        'parameter' => '',
        'root'      => '/data/ftp/home',

    ];



    protected function configure()
    {
        $this->setName('test')->setDescription('Download forecast file & format it');
    }

    protected function execute(Input $input, Output $output)
    {
        $format = '%1$s://%2$s:%3$s@%4$s/%5$sT%6$sZP%7$dD:PT%8$dM/%9$s/%10$s/csv?source=mix';

        $newLine = isset($_SERVER['OS']) && $_SERVER['OS'] == 'Windows_NT' ? "\r\n" : "\n";

        $timestamp = mktime(6, 0, 0, date('m'), date('d'), date('Y'));

        // 设置系统操作时区为UTC+0
        date_default_timezone_set('UTC');
        $UTC = explode(' ',date('Y-m-d H:i:s', $timestamp));
        // 加载配置文件
        $stationConf = require_once ($this->config['root'].'/config.php');
//        dump($stationConf['qingruan']);die;
        foreach ($stationConf as $company)
        {
            // 初始化数据
            $companyName = $company['name'];
            $period = $company['period'];
            $interval = $company['interval'];
            $switch = $company['is_auto'];
            if ($switch)
            {
                foreach ($company['stations'] as $station)
                {
                    if (isset($station['period'])) $period = $station['period'];
                    if (isset($station['interval'])) $interval = $station['interval'];
                    if (isset($station['is_auto'])) $switch = $station['is_auto'];
                    $No = $station['No'];
                    foreach ($station as $ob)
                    {
                        if (isset($ob['period'])) $period = $ob['period'];
                        if (isset($ob['interval'])) $interval = $ob['interval'];
                        if (isset($ob['is_auto'])) $switch = $ob['is_auto'];
                        $type = $ob['type'];
                        $lnglat = $ob['lnglat'];
                        $paras = implode(',', $ob['parameters']);
                        $str = sprintf($format, $this->config['protocol'], $this->config['account'],
                            $this->config['password'], $this->config['host'],
                            $UTC[0], $UTC[1],
                            $period,$paras,
                            $lnglat);
                    }
                }
            }

        }


        $fileName = 'test.csv';
        $fileContent = file_get_contents('D://'.$fileName);
//        file_put_contents('D://'.$fileName, $fileContent);
        $fileContent = str_replace(';', ',', $fileContent);
        $partitionLines = myExplode(["\r","\n"],$fileContent,3, false);
        date_default_timezone_set('PRC');
        foreach ($partitionLines as $line)
        {
            $columns = explode(',',$line);
//            echo $columns[0];
            if (($time = strtotime($columns[0])) !== false)
            {
                $columns[0] = date('Y-m-d H:i:s', $time);
            }
            $line = implode(',', $columns);
            echo $line."\r\n";
//            die;
        }

    }



    public function  diffTime($start, $end) {
        $d_start    = new DateTime(date('Y-m-d H:i:s',$start));
        $d_end      = new DateTime(date('Y-m-d H:i:s',$end));
        $diff = $d_start->diff($d_end);
        return $diff;
    }

}
