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
use think\console\Output;
use app\model\Source;
use app\model\station\StationData;
use app\model\station\StationDataHumiture;
use app\model\station\StationDataIrradiance;
use app\model\station\StationDataWind;
use DateTime;

class Test extends Command
{

    protected function configure()
    {
        $this->setName('test')->setDescription('Here is the remark ');
    }

    protected function execute(Input $input, Output $output)
    {
        /***查看WINDOWS系统进程列表,并查找指定进程是否存在*/
        $tasklist = $_SERVER["windir"]."/system32/tasklist.exe";       //找到windows系统下tasklist的路径
        // 运行两次tasklist.exe根据已产生实例的窗口pid不会变化的原理比较两次运行结果，查找是否有已经产生实例的cmd窗口
        @exec($tasklist,$arr1);     //运行第一次tasklist.exe,返回一个数组$arr1
        @exec($tasklist,$arr2);     //运行第二次tasklist.exe,返回一个数组$arr2
        //用循环打印进程列表
        $cmds = 0;
        foreach($arr1 as $value1){
            $list = myExplode(" ",$value1);
            //查找指定进程
            if(!empty($list) && 'cmd.exe'==$list[0])
            {
                foreach ($arr2 as $value2)
                {
                    // 如果两次运行结果不相同，则说明没有运行实例即可以
                    if ($value1 == $value2)
                    {
                        $cmds++;
                        $info = myExplode(' ',$value2);
                        $pids[] = $info[1];
                    }
                }
            }
        }
        if ($cmds == 1)
        {
            // 查找数据库中创建时间最大值
            $max = StationData::max('ctime');
            // 通过时间最大值获取时间字符串
            $start = date('Y-m-d H:i:s', $max);
            // 设置每次查询偏移量
            $offest = 10000;
            // 获取程序开始时间
            $total_start = time();
            // 获取数据库中的所有站点数据
            $station = Station::select();
            // 通过起始时间获取未格式化的数据
            do
            {
                $list = Source::where('ObsTime','>=', $start) -> where('StationName','NOT NULL') -> where('StationNO', '>',0) -> limit($offest) -> order('ObsTime') -> select();
                // 插入起始时间
                $InsertStart = time();
                // 重设数据集
                $irradiance_sets = [];

                $wind_sets = [];

                $humiture_sets = [];
                // 命令行写当前数据起止时间
                $output->writeln(iconv('utf-8','GBK',"processing...{$list[0]['ObsTime']} - {$list[count($list) - 1]['ObsTime']}"));
                // 遍历当前未格式化的数据
                foreach ($list as $key => $data) {
                    if (count($irradiance_sets) > 0 && count($irradiance_sets) % ($offest / 10) == 0) echo iconv('utf-8','GBK','■');
                    $stationid = 0;
                    // 站点名称和站点编号有一个为空值、0时跳过该条数据
                    if (empty($data['StationName']) || empty($data['StationNO'])) continue;
                    foreach ($station as $s) {
                        if ($s['station_name'] == $data['StationName'] && $s['station_id'] == $data['StationNO']) {
                            $stationid = $s['station_id'];
                        }
                    }
                    // 未在映射中找到数据时跳过该条
                    if ($stationid == 0) continue;
                    $l = StationData::where('station_id',$stationid) -> where('ctime',strtotime($data['ObsTime'])) -> find();
                    if (empty($l['data_id'])) {
                        $data_set = [
                            'station_id'    => $stationid,
                            'ctime'         => strtotime($data['ObsTime']),
                        ];
                        $model_data = new StationData($data_set);
                        $model_data -> save();
                        $res = $model_data -> data_id;
                    }
                    else {
                        continue;
                    }

                    $i = StationDataIrradiance::where('data_id',$res) -> count();
                    if ($i < 1) {
                        // 布置辐射表数据
                        $irradiance_set = [
                            'data_id'   => $res,
                            'GHI'       => $data['GHI'],
                            'GTI'       => $data['GTI'],
                            'GHIsum'    => $data['GHISum'],
                            'GTIsum'    => $data['GTISum'],
                        ];
                        $irradiance_sets[] = $irradiance_set;
                        /*$irradiance_sets =
                        $model_irr = new StationDataIrradiance($irradiance_set);
                        $model_irr -> save();*/
                    }
                    $w = StationDataWind::where('data_id',$res) -> count();
                    if ($w < 1) {
                        // 布置风速风向数据
                        $wind_set = [
                            'data_id'           => $res,
                            'wind_speed'        => $data['WindSpeed'],
                            'wind_direction'    => $data['WindDir'],
                        ];
                        $speed = explode('.',$data['WindSpeed']);
                        $wind_sets[] = $wind_set;
                        /*$model_wind = new StationDataWind($wind_set);
                        $model_wind -> save();*/
                    }
                    $h = StationDataHumiture::where('data_id',$res) -> count();
                    if ($h < 1) {
                        // 布置温湿度数据
                        $humiture_set = [
                            'data_id'       => $res,
                            'panel_temp'    => $data['PanleTemp'],
                            'temperature'   => $data['Temperature'],
                            'humidity'      => $data['Humitity'],
                        ];
                        $humiture_sets[] = $humiture_set;

                    }

                }

                $model_irr = new StationDataIrradiance();
                $model_irr -> saveAll($irradiance_sets);
                $model_wind = new StationDataWind();
                $model_wind -> saveAll($wind_sets);
                $model_humiture = new StationDataHumiture();
                $model_humiture -> saveAll($humiture_sets);

                $InsertEnd = time();
                $diff = $this -> diffTime($InsertStart,$InsertEnd);
                $output->writeln(iconv('utf-8','GBK','插入耗时：'.$diff->format('%h小时').$diff->format('%i分钟').$diff->format('%s秒')));

                $start = $list[count($list) - 1]['ObsTime'];
            }
            while ($start < date('Y-m-d H:i:00',time() - 60));
            $total_end = time();
            $diff = $this -> diffTime($total_start,$total_end);
            $output->writeln(iconv('utf-8','GBK','总运行时间：'.$diff->format('%h小时').$diff->format('%i分钟').$diff->format('%s秒')));

            $output->writeln("over");
        }
        $taskkill = $_SERVER["windir"]."/system32/taskkill.exe";       //找到windows系统下tasklist的路径
        $mypid = array_pop($pids);
        $command = $taskkill . " /pid $mypid /f" ;
        @exec($command);
    }

public function  diffTime($start, $end) {
    $d_start    = new DateTime(date('Y-m-d H:i:s',$start));
    $d_end      = new DateTime(date('Y-m-d H:i:s',$end));
    $diff = $d_start->diff($d_end);
    return $diff;
}

}
