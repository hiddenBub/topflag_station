<?php
/**
 * Created by PhpStorm.
 * User: wwkil
 * Date: 2018/4/12
 * Time: 15:56
 */

namespace app\test\controller;


use app\model\Source;
use app\model\station\StationData;
use app\model\station\StationDataHumiture;
use app\model\station\StationDataIrradiance;
use app\model\station\StationDataWind;
use think\Db;

class Index
{
    public function index()
    {

        $start = 2539448;
        $offest = 1000;
        $time_start = time();
        while ($list = Source::where('ID','>',$start) -> limit($offest) -> select()){
            foreach ($list as $key => $data) {
                $l = StationData::where('station_id',$data['StationNO']) -> where('ctime',strtotime($data['ObsTime'])) -> find();
                if (empty($l['data_id'])) {
                    $data_set = [
                        'station_id'    => $data['StationNO'],
                        'ctime'         => strtotime($data['ObsTime']),
                    ];
                    $model_data = new StationData($data_set);
                    $model_data -> save();
                    $res = $model_data -> data_id;
                } else {
                    $res = $l['data_id'];
                }
                echo 'processing...'.$res."\n\r";
                // 布置辐射表数据
                $irradiance_set = [
                    'data_id'   => $res,
                    'GHI'       => $data['GHI'],
                    'GTI'       => $data['GTI'],
                    'GHIsum'    => $data['GHISum'],
                    'GTIsum'    => $data['GTISum'],
                ];

                $model_irr = new StationDataIrradiance($irradiance_set);
                $model_irr -> save();
                // 布置风速风向数据
                $wind_set = [
                    'data_id'           => $res,
                    'wind_speed'        => $data['WindSpeed'],
                    'wind_direction'    => $data['WindDir'],
                ];
                $speed = explode('.',(string) $data['WindSpeed']);
                if ($speed['0'] > 20 || (isset($speed['1']) && $speed['1'] > 9)) {
                    $wind_set['wind_speed'] = 0.0;
                }
                if ($data['WindDir'] > 360 || $data['WindDir'] < 0) {
                    $wind_set['wind_direction'] = 0;
                }
                $model_wind = new StationDataWind($wind_set);
                $model_wind -> save();
                // 布置温湿度数据
                $humiture_set = [
                    'data_id'       => $res,
                    'panel_temp'    => $data['PanleTemp'],
                    'temperature'   => $data['Temperature'],
                    'humidity'      => $data['Humitity'],
                ];
                $model_humiture = new StationDataHumiture($humiture_set);
                $model_humiture -> save();
            }
            $start += $offest;
        }
        $time_end = time();
        $start_time  = date('Y-m-d H:i:s',$time_start);
        $end_time   = date('Y-m-d H:i:s',$time_end);
        $d_start    = new DateTime($start_time);
        $d_end      = new DateTime($end_time);
        $diff = $d_start->diff($d_end);
        $expand =  '总运行时间：'.$diff->format('%h小时').$diff->format('%i分钟').$diff->format('%秒');
        echo $expand;
        $output->writeln("over");
    }
}