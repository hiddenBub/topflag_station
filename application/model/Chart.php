<?php
/**
 * Created by PhpStorm.
 * User: Mloong
 * Date: 2018/4/19
 * Time: 16:05
 */

namespace app\model;

use app\model\station\StationData;
use Json;
use think\Model;

class Chart extends Model
{
    protected $pk = 'chart_id';
    function stepCreate($station){
        foreach ($station as $a => $b) {
            yield $b['station_id'];
        }
    }
    /***
     * TODO 分发图表查找数据源，充当分发器
     * @param $id 图表ID
     * @param null $station_id  场站ID
     * @param null $granularity_id  粒度大小默认为空
     * @return mixed
     */
    public function chartData($id,$station_id = null,$granularity_id = null)
    {
        // $id == 1 查找太阳辐射记录
        if ($id == 1) {
            // 设置查找的字段
            $fields = 'sum(GHI) AS GHIsum,sum(GTI) AS GTIsum,d.station_id,d.ctime';
            // 初始化粒度信息，此处需要粒度信息
            if (empty($granularity_id)) {
                $granularity_id = 1;
            }
            // 为1时粒度为每日
            if ($granularity_id == 1) {
                $format = [
                    '%Y-%m-%d',
                    'Y-m-d',
                ];
            } else if ($granularity_id == 2) {
                $format = ['%Y-%m', 'Y-m',];
            } else if ($granularity_id == 3) {
                $format = ['%Y', 'Y',];
            }
            $temp = StationData::field($fields) -> alias('d')
                -> join('tf_station_data_irradiance i', 'd.data_id = i.data_id')
                -> where('station_id',$station_id)
                -> where('FROM_UNIXTIME(d.ctime,"%Y-%m-%d %H") > FROM_UNIXTIME(d.ctime,"%Y-%m-%d 00")' )
                -> group('station_id,FROM_UNIXTIME(d.ctime,\''.$format['0'].'\')')
                -> select();
            $data['legend']['data']     = ['GHIsum','GTIsum'];

            $data['xAxis'][0]['data'] = [];
            foreach ($data['legend']['data'] as $index => $item) {
                $data['series'][$index] = [
                    'name'      => $item,
                    'type'      => 'bar',
                    'barGap'    => 0,
                ];
                foreach ($temp as $key => $value) {
                    $data['series'][$index]['data'][] = round($value[$item] * 60 / (1000 * 1000), 4);
                    $date = date($format['1'],$value['ctime']);
                    if (!in_array($date,$data['xAxis'][0]['data'])) $data['xAxis'][0]['data'][] = $date;
                }
            }
            $data['tooltip'] = [
                'show'          =>true,
                'trigger'       => 'axis',
                'axisPointer'   => [
                    'type'  =>'shadow'
                ]
            ];
            $data['dataZoom'] = [
                [
                    'show'  => true,
                    'start' => 94,
                    'end'   => 100,
                ],
                [
                    'type'  => 'inside',
                    'start' => 94,
                    'end'   => 100
                ],
                [
                    'show'              => true,
                    'yAxisIndex'        => 0,
                    'filterMode'        => 'empty',
                    'width'             => 30,
                    'height'            => '80%',
                    'showDataShadow'    => false,
                    'left'              => '93%'
                ]
            ];
            $data['yAxis'][0] = [
                'show'=>true,
                'name'  => '辐射累计(mJ)',
                'type'=>'value',
                'axisLabel' => [
                    'formatter' => '{value} mJ'
                ]
            ];
            $data['toolbox'] = [
                'show'      =>true,
                'orient'    =>'horizontal',
                'left'      =>'right',
                'top'       =>'top',
                'feature'   => [
                    'myTool1'   => [
                        'show'=>true,
                        'title'=>'日',
                        'icon'=>'path://M680 120c-15.5 0-28 12.5-28 28v28H372v-28c0-15.5-12.5-28-28-28s-28 12.5-28 28v28H120c-30.9 0-56 25.1-56 56v616c0 30.9 25.1 56 56 56h784c30.9 0 56-25.1 56-56V232c0-30.9-25.1-56-56-56H708v-28c0-15.5-12.5-28-28-28z m0 168c15.5 0 28-12.5 28-28v-28h196v112H120V232h196v28c0 15.5 12.5 28 28 28s28-12.5 28-28v-28h280v28c0 15.5 12.5 28 28 28zM120 848V400h784v448H120z,M540 456h-56c-15.5 0-28 12.5-28 28s12.5 28 28 28h28v252c0 15.5 12.5 28 28 28s28-12.5 28-28V484c0-15.5-12.5-28-28-28z',
                        'click'=> 'getChartData(1)'
                    ],
                    'myTool2'   => [
                        'show'=>true,
                        'title'=>'月',
                        'icon'=>'path://M832 128h-64V64h-64v64H320V64h-64v64h-64a64.19 64.19 0 0 0-64 64v640a64.19 64.19 0 0 0 64 64h640a64.19 64.19 0 0 0 64-64V192a64.19 64.19 0 0 0-64-64z m0 704H192V256h640z,M544 768V320H256v64h224v128H256v64h224v128H256v64h288zM704 320h64v448h-64z',
                        'click'=>'getChartData(2)'
                    ],'myTool3'   => [
                        'show'=>true,
                        'title'=>'年',
                        'icon'=>'path://M320 64l-64 0 0 160 64 0L320 64zM896 128l-96 0 0 64 96 0 0 704L128 896 128 192l96 0 0-64-96 0c-28.8 0-64 22.4-64 54.4l0 720c0 32 22.4 57.6 54.4 57.6l784 0c32 0 57.6-25.6 57.6-57.6L960 182.4C960 150.4 928 128 896 128zM320 736l64 0L384 384l-64 0-64 64 0 64 64 0L320 736zM672 128 352 128l0 64 320 0L672 128zM768 64l-64 0 0 160 64 0L768 64zM640 352c-35.2 0-108.8 25.6-128 32l32 64c38.4-25.6 67.2-32 96-32 70.4 0 67.2 57.6 32 96-44.8 44.8-160 163.2-160 163.2L512 736l256 0 0-64-160 0 96-96C816 454.4 774.4 352 640 352z',
                        'click'=> 'getChartData(3)'
                    ],
                ]
            ];

        }
        else if ($id == 2) {
            // 设置查找的字段
            $fields = 'sum(GHI) AS GHI,sum(GTI) AS GTI,d.station_id,d.ctime';
            // 初始化粒度信息，此处需要粒度信息
            if (empty($granularity_id)) {
                $granularity_id = 1;
            }
            // 为1时粒度为每日
            if ($granularity_id == 1) {
                $format = [
                    '%Y-%m-%d',
                    'Y-m-d',
                ];
            }
            $temp = StationData::field($fields) -> alias('d')
                -> join('tf_station_data_irradiance i', 'd.data_id = i.data_id')
                -> where('station_id',$station_id)
                -> where('GHI|GTI','>' ,1000)
                -> group('station_id,FROM_UNIXTIME(d.ctime,\''.$format['0'].'\')')
                -> select();
            $data['legend']['data']     = ['GHI','GTI'];

            $data['xAxis'][0]['data'] = [];
            foreach ($data['legend']['data'] as $index => $item) {
                $data['series'][$index] = [
                    'name'      => $item,
                    'type'      => 'bar',
                    'barGap'    => 0,
                ];
                foreach ($temp as $key => $value) {
                    $data['series'][$index]['data'][] = round($value[$item] * 60 / (1000 * 1000), 4);
                    $date = date($format['1'],$value['ctime']);
                    if (!in_array($date,$data['xAxis'][0]['data'])) $data['xAxis'][0]['data'][] = $date;
                }
            }
            $data['tooltip'] = [
                'show'          =>true,
                'trigger'       => 'axis',
                'axisPointer'   => [
                    'type'  =>'shadow'
                ]
            ];
            $data['dataZoom'] = [
                [
                    'show'  =>true,
                    'start' =>94,
                    'end'   =>100,
                ],
                [
                    'type'  => 'inside',
                    'start' => 94,
                    'end'   => 100
                ],
                [
                    'show'              => true,
                    'yAxisIndex'        => 0,
                    'filterMode'        => 'empty',
                    'width'             => 30,
                    'height'            => '80%',
                    'showDataShadow'    => false,
                    'left'              => '93%'
                ]
            ];
            $data['yAxis'][0] = [
                'show'=>true,
                'type'=>'value',
                'name'  => '辐射累计 (mJ)',
                'axisLabel' => [
                    'formatter' => '{value} mJ'
                ]
            ];
            $data['toolbox'] = [
                'show'      =>false,
            ];
        }
        else if ($id == 3) {
            // 设置查找的字段
            $fields = 'GHI,GTI,station_id,ctime';
            // 初始化粒度信息，此处需要粒度信息
            if (empty($granularity_id)) {
                $granularity_id = 1;
            }
            // 为1时粒度为每日
            if ($granularity_id == 1) {
                $format = [
                    '%H:%i',
                    'Y-m-d H:i',
                ];
                $range = [
                    '05:00',
                    '19:00'
                ];
            }
            $time = time() - 24 * 60 * 60;
            $time_max = time();
            $temp = StationData::query("SELECT
	GHI,
	GTI,
	station_id,
	ctime
FROM
	`tf_station_data` AS d
INNER JOIN `tf_station_data_irradiance` AS i ON i.data_id = d.data_id
WHERE
	station_id = $station_id
AND ctime BETWEEN $time
AND $time_max
AND FROM_UNIXTIME(ctime, '{$format['0']}') BETWEEN '{$range['0']}'
AND '{$range['1']}'
AND GHI < 1700
AND GTI < 1700
ORDER BY
	ctime");
            $data['legend']['data']     = ['GHI','GTI'];

            $data['xAxis'][0]['data'] = [];
            foreach ($data['legend']['data'] as $index => $item) {
                $data['series'][$index] = [
                    'name'      => $item,
                    'type'      => 'line',
                    'barGap'    => 0,
                ];
                foreach ($temp as $key => $value) {
                    $data['series'][$index]['data'][] = $value[$item];
                    $date = date($format['1'],$value['ctime']);
                    if (!in_array($date,$data['xAxis'][0]['data'])) $data['xAxis'][0]['data'][] = $date;
                }
            }
            $data['tooltip'] = [
                'show'          =>true,
                'trigger'       => 'axis',
                'axisPointer'   => [
                    'type'  =>'shadow'
                ]
            ];

            $data['yAxis'][0] = [
                'show'=>true,
                'type'=>'value',
                'name'  => '辐射值(W/m²)',
                'axisLabel' => [
                    'formatter' => '{value} W/m²'
                ]
            ];
            $data['toolbox'] = [
                'show'      =>false,
            ];
        }
        else if ($id == 4) {
            // 设置查找的字段
            $fields = 'GHI as GHTsum,GTI as GTIsum,station_id,d.ctime';
            // 初始化粒度信息，此处需要粒度信息
            if (empty($granularity_id)) {
                $granularity_id = 1;
            }
            // 为1时粒度为每日
            if ($granularity_id == 1) {
                $format = [
                    '%H:%i',
                    'Y-m-d H:i',
                ];
                $range = [
                    '05:00',
                    '19:00'
                ];
            }
            $time = time() - 24 * 60 * 60;
            $time_max = time();
            $temp = StationData::query("SELECT
	$fields
FROM
	`tf_station_data` AS d
INNER JOIN `tf_station_data_irradiance` AS i ON i.data_id = d.data_id
WHERE
	station_id = $station_id
AND ctime BETWEEN $time
AND $time_max
AND FROM_UNIXTIME(ctime, '{$format['0']}') BETWEEN '{$range['0']}'
AND '{$range['1']}'
ORDER BY
	ctime");
            $data['legend']['data']     = ['GHIsum','GTIsum'];

            $data['xAxis'][0]['data'] = [];
            $GHIsum = 0;$GTIsum = 0;
            foreach ($data['legend']['data'] as $index => $item) {
                $data['series'][$index] = [
                    'name'      => $item,
                    'type'      => 'line',
                    'barGap'    => 0,
                ];
                foreach ($temp as $key => $value) {
                    $$item += $value[$item] * 60 / (1000 * 1000);
                    $data['series'][$index]['data'][] = $$item;
                    $date = date($format['1'],$value['ctime']);
                    if (!in_array($date,$data['xAxis'][0]['data'])) $data['xAxis'][0]['data'][] = $date;
                }
            }
            $data['tooltip'] = [
                'show'          =>true,
                'trigger'       => 'axis',
                'axisPointer'   => [
                    'type'  =>'shadow'
                ]
            ];
            $data['dataZoom'] = [
                [
                    'show'  =>true,
                    'start' =>94,
                    'end'   =>100,
                ],
                [
                    'type'  => 'inside',
                    'start' => 94,
                    'end'   => 100
                ],
                [
                    'show'              => true,
                    'yAxisIndex'        => 0,
                    'filterMode'        => 'empty',
                    'width'             => 30,
                    'height'            => '80%',
                    'showDataShadow'    => false,
                    'left'              => '93%'
                ]
            ];
            $data['yAxis'][0] = [
                'show'=>true,
                'type'=>'value',
                'name'  => '辐射累计 (mJ)',
                'axisLabel' => [
                    'formatter' => '{value} mJ'
                ]
            ];
            $data['toolbox'] = [
                'show'      =>false,
            ];
        }
        else if ($id == 5) {
            // 设置查找的字段
            $fields = 'air_temperature as temperature,relative_humidity as humidity,station_id,ctime';
            // 初始化粒度信息，此处需要粒度信息
            if (empty($granularity_id)) {
                $granularity_id = 1;
            }
            // 为1时粒度为每日
            if ($granularity_id == 1) {
                $format = [
                    '%H:%i',
                    'Y-m-d H:i',
                ];
                $range = [
                    '05:00',
                    '19:00'
                ];
            }
            $time = time() - 24 * 60 * 60;
            $time_max = time();
            $temp = StationData::query("SELECT
	$fields
FROM
	`tf_station_data` AS d
INNER JOIN `tf_station_data_humiture` AS h ON h.data_id = d.data_id
WHERE
	station_id = $station_id
AND ctime BETWEEN $time
AND $time_max
ORDER BY
	ctime");
            $data['legend']['data']     = ['temperature','humidity'];

            $data['xAxis'][0]['data'] = [];
            foreach ($data['legend']['data'] as $index => $item) {
                $data['series'][$index] = [
                    'name'      => $item,
                    'type'      => 'line',
                    'barGap'    => 0,
                    'yAxisIndex'    => $index,
                ];
                foreach ($temp as $key => $value) {
                    $data['series'][$index]['data'][] = $value[$item];
                    $date = date($format['1'],$value['ctime']);
                    if (!in_array($date,$data['xAxis'][0]['data'])) $data['xAxis'][0]['data'][] = $date;
                }
            }
            $data['tooltip'] = [
                'show'          =>true,
                'trigger'       => 'axis',
                'axisPointer'   => [
                    'type'  =>'shadow'
                ],
                'formatter' => '时间:{b}<br />温度:{c0}°C<br />湿度:{c1}%'
            ];
            /*$data['dataZoom'] = [
                [
                    'show'  =>true,
                    'start' =>94,
                    'end'   =>100,
                ],
                [
                    'type'  => 'inside',
                    'start' => 94,
                    'end'   => 100
                ],
                [
                    'show'              => true,
                    'yAxisIndex'        => 0,
                    'filterMode'        => 'empty',
                    'width'             => 30,
                    'height'            => '80%',
                    'showDataShadow'    => false,
                    'left'              => '93%'
                ]
            ];*/
            $data['yAxis'] = [

                [
                    'type'=>'value',
                    'name'  => '温度(°C)',
                    'axisLabel' => [
                        'formatter' => '{value} °C',
                    ],
                    'min'   => -50,
                    'max'   => 50,
                    'interval'  => 5,
                ],
                [
                    'type'=>'value',
                    'name'  => '湿度(%)',
                    'axisLabel' => [
                        'formatter' => '{value} %',
                    ],
                    'min'   => 0,
                    'max'   => 100,
                    'interval'  => 10,

                ],
            ];
            $data['toolbox'] = [
                'show'      =>false,
            ];
        }
        else if ($id == 6) {
            // 风向数组
            $wind_dir_key[0] = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
            $wind_dir_key[1] = [0, 22.5, 45, 67.5, 90, 112.5, 135, 157.5, 180, 202.5, 225, 247.5, 270, 292.5, 315, 337.5, 360];
            // 风速数组
            $wind_spd_key[0] = ['<0.5m/s','0.5-2m/s','2-4m/s','4-6m/s','6-8m/s','8-10m/s','> 10m/s'];
            $wind_spd_key[1] = [0,0.5,2,4,6,8,10];
            // 风向字符串
            $wind_dir[0] = "'".implode("','",$wind_dir_key[0])."'";
            // 风向范围
            $wind_dir[1] = implode(",",$wind_dir_key[1]);
            // 风速字符串
            $wind_spd[0] = "'".implode("','",$wind_spd_key[0])."'";
            // 风速范围
            $wind_spd[1] = implode(",",$wind_spd_key[1]);
            // 现在的时间戳
            $now = time();
            // 24小时前的时间戳
            $yesterday = $now - 60 * 60 * 24;
            // 查询sql
            $sql = "SELECT 
                elt(INTERVAL (
                        w.wind_direction,{$wind_dir[1]}
                    ),
	                {$wind_dir[0]}
	            ) AS direction,
	            COUNT(d.data_id) AS 'no',
	            elt(INTERVAL (
	                    w.wind_speed,{$wind_spd[1]}
	                ),
		            {$wind_spd[0]}
		        ) AS speed
            FROM
	            `tf_station_data` AS d
            INNER JOIN `tf_station_data_wind` AS w ON d.data_id = w.data_id
            WHERE
	            station_id = {$station_id} AND ctime BETWEEN {$yesterday} AND {$now}
            GROUP BY
	            elt(
		            INTERVAL (
			            w.wind_direction,{$wind_dir[1]}
		            ),
		            {$wind_dir[0]}
	            ),
                elt(
		            INTERVAL (
			            w.wind_speed,{$wind_spd[1]}
		            ),
		            {$wind_spd[0]}
	            ) ORDER BY w.wind_direction";
            // 获取风速数据
            $res = StationData::query($sql);
            // 获取数据总量
            $count = StationData::alias(['tf_station_data'=>'d','tf_station_data_wind'=> 'w']) -> join('tf_station_data_wind','d.data_id = w.data_id') -> where("station_id = {$station_id} AND ctime BETWEEN {$yesterday} AND {$now}") -> count('d.data_id');

            $data = [
                'angleAxis' => [
                    'type'  => 'category',
                    'data'  => $wind_dir_key[0],
                    'boundaryGap'   => false,
                    'axisLabel' => [
                        'interval'  => 0,
                    ],
                    'axisLine'  => [
                        'symbolOffset' => -90,
                    ],
                    'z' => 10
//                    'clockwise' => true,
//                    'startAngle' => 120
                ],
                'radiusAxis'    =>  [
                    'name'      => '频率(%)',
                    'nameLocation'  => 'center',
                    'axisLabel' => [
                        'interval'  => 0,
                        'formatter' => '{value}%'
                    ],
//                    'inverse' => true,
                ],
                'polar'         => (object) [],
                'legend'        => [
                    'show'  => true,
                    'data'  => $wind_spd_key[0],
                    'orient'    => 'vertical',
                    'left'      => 'right',
                    'top'       => 'center',
                ],
                'tooltip'   => [
                    'show'  => true,
                    'trigger'   => 'item',
                    'axisPointer'   => [
                        'type'  =>'shadow',
                    ],
                    'formatter' => '方向:{b}<br />风速:{a}<br />{c}%'
                ]
            ];

            foreach ($wind_spd_key[0] as $key => $value)
            {
                $data['series'][$key]['type'] = 'bar';
                $data['series'][$key]['coordinateSystem'] = 'polar';
                $data['series'][$key]['name'] = $value;
                $data['series'][$key]['stack'] = 'a';
                $data['series'][$key]['data'] = array_fill(0,count($wind_dir_key[0]),(float)0);
                $data['series'][$key]['label'] = [
                    'normal'    => [
                        'show'          => true,
                        'position'      => 'insideBottom',
                        'distance'      => 15,
                        'align'         => 'left',
                        'verticalAlign' => 'middle',
                        'rotate'        => 90,
                        'formatter'     => '{value}%',
                        'fontSize'      => 12,
                        'rich'          => [
                            'name'      => [
                                'textBorderColor'   =>'#fff'
                            ]
                        ],
                    ],
                ];
            }
            foreach ($res as $k => $v)
            {

                $spd = array_search($v['speed'], $wind_spd_key[0]);
//                foreach ($wind_dir_key[0] as $key => $value) {
//                    if ($value == $v['direction']) {
//                        $d = $key;
//                    }
//                }
                $d = array_search($v['direction'], $wind_dir_key[0],true);
//                echo "[$spd][$direction]<br>";
                $data['series'][$spd]['data'][$d] = round($v['no'] / $count * 100, 2);
            }

//            dump($res);die;

        }
        return $data;
    }
}