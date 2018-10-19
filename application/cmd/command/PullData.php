<?php
/**
 * Created by PhpStorm.
 * User: Mloong
 * Date: 2018/4/18
 * Time: 20:03
 */

namespace app\cmd\command;


use app\model\Data;
use app\model\data\DataTable;
use app\model\data\DataUnit;
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
use think\exception\ErrorException;
use \think\Log;

class PullData extends Command
{

    protected function configure()
    {
        $this->setName('pulldata')->setDescription('pull LogerNet\'s dat type data file');
    }

    protected function execute(Input $input, Output $output)
    {
//        dump(0 & 8 == 0);die;
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
            // 获取数据库中的所有可用站点数据
            $station = Station::where('status','1') -> where('storage_path','NOT NULL') -> order('station_id') -> select();
            // 获取变量数据
            $variable = Data::alias('d') -> field('d.var_name,d.alias,t.bit,t.table_name,d.unit,d.range') -> join('tf_data_unit u','u.uid = d.unit')->join('tf_data_table t','d.belong = t.tid')->select();
            $tables = DataTable::all();
            // 数据表实体，用作位运算
            $tableEntity = [];
            // 遍历变量数据生成数据表实体
            array_map(function($row) use(&$tableEntity)
            {
                $tableEntity[$row['bit']][] = $row['var_name'];
            }, $variable);
            // 数据表变量实体
            $varNames = [];
            // 根据数据库的变量数据获取数据表变量名对应的dat文件变量名映射
            array_map(function($row) use(&$varNames){
                // 将所有变量名称写到一个字符串中
                $str = rtrim($row['var_name'].','.$row['alias'],',');
                // 取出当前变量的单位类型
                $uTypeID = $row['unit'];
                // 查找单位表找到所有单位
                $unit = DataUnit::where('type', $uTypeID) -> select();
                // 遍历数据表数据并格式化数据
                $unitFormat = array_map(function($r) {
                    $nameCol = rtrim($r['name'].','.$r['alias'],',');
                    return $arr =
                        [
                            'items' => explode(',', $nameCol),
                            'name'  => $r['name'],
                            'calculation'   => $r['calculation'],
                            'scale'         => $r['scale'],
                        ];
                }, $unit);
                // 将变量数据存入varNames数组
                $varNames = array_merge([
                    $row['var_name'] => [
                        'variables' => explode(',', $str),
                        'bit' => $row['bit'],
                        'unit' => $unitFormat,
                        'dataRange' => $row['range']
                    ]
                ], $varNames);
            }, $variable);
//            dump($varNames['sunshine_duration']['unit']);die;
            // 遍历站点
            foreach ($station as $s)
            {
                // 获取文件存储位置
                $path = $s['storage_path'];
                // 生成器读取数据
                $file = $this -> readAllData($path);
                /*********************初始化数据**********************/
                // 数据行号
                $i = 1;
                // 数据索引-标准变量名映射数组
                $keys=[];
                //
                $tableBit = 0;
                // 重设数据集
                $irradiance_sets = [];

                $wind_sets = [];

                $humiture_sets = [];

                $other_sets = [];

                $pressure_sets = [];

                $rain_sets = [];
                /*********************初始化结束***************************/
                foreach ($file as $line)
                {
                    try{
                        // 按照'，'拆解数据，并去掉多余的'"'
                        $data = array_map(function($row){
                            return trim(trim($row),'"');
                        },explode(',',$line));

                        // 数据结束时将所有剩余数据写入数据库
                        if ($data[0] == '' && $i > 4)
                        {
                            foreach ($tables as $table)
                            {
                                // 将表名称分割
                                $parts = explode('_',$table['table_name']);
                                // 操作模型名称
                                $modelName = 'app\model\station\\'.implode('', array_map('ucfirst',array_slice($parts,1)));
                                // 创建操作模型实例
                                $model = new $modelName();
                                // 当前变量名
                                $var = $parts[count($parts) - 1];
                                // 当前总集合名称
                                $sets = $var.'_sets';
                                if (count($$sets) > 0)
                                {
                                    $output -> writeln('nowsets:'.$sets.',setcount:'.count($$sets));
                                    $model -> saveAll($$sets);
                                    $$sets = [];
                                }
                            }
                        }
                        // 第二行为数据的变量名，在此处
                        if ($i == 2)
                        {

                            // 遍历当前数据标题，并查找在
                            foreach ($data as $key => $value)
                            {
                                array_map(function($row) use ($value, $key, &$keys, &$tableBit)
                                {
                                    if (in_array($value, $row['variables'])){

                                        $keys[$key]['name'] = $row['variables'][0];
                                        $keys[$key]['range'] = explode(',',$row['dataRange']);
                                        array_map(function($r) use ($value, $key, &$keys, &$tableBit)
                                        {
                                            $keys[$key]['units'][] = $r['items'];
                                        },$row['unit']);

                                        $bit = pow(2, $row['bit']);
                                        // 当table位中没有当前表位时将其与进值中
                                        if (!($tableBit & $bit)) $tableBit |= $bit;
                                    }
                                }, $varNames);


                            }


                        }
                        elseif ($i == 3)
                        {

                            foreach ($data as $key => $value)
                            {
//                                echo $value;
//                            dump($varNames[$value['name']][2]);
                                if (!empty($keys[$key]))
                                {
                                    $j = 0;
                                    array_map(function($row) use ($value,&$j, $key, &$keys, &$varNames)
                                    {
                                        if (in_array($value, $row)){
                                            $v = $keys[$key]['name'];
                                            $keys[$key]['calculation'] = $varNames[$v]['unit'][$j]['calculation'];
                                            $keys[$key]['scale'] = $varNames[$v]['unit'][$j]['scale'];
                                        }
                                        $j++;
                                    }, $keys[$key]['units']);
                                }
                            }
                        }
                        elseif ($i > 4 && $data[0] != '')
                        {
                            $stationid = $s['station_id'];
                            $recordTime = strtotime($data[0]);
                            $l = StationData::where('station_id', $stationid) -> where('ctime', $recordTime) -> find();
                            if (empty($l['data_id'])) {
                                $data_set = [
                                    'station_id'    => $stationid,
                                    'ctime'         => $recordTime,
                                ];
                                $model_data = new StationData($data_set);
                                $model_data -> save();
                                $res = $model_data -> data_id;
                            }
                            else
                            {
                                continue;
                            }
                            foreach ($tables as $table)
                            {
                                $parts = explode('_',$table['table_name']);
                                $modelName = 'app\model\station\\'.implode('', array_map('ucfirst',array_slice($parts,1)));
                                $model = new $modelName();
                                $var = $parts[count($parts) - 1];
                                $arr = [];
                                $$var = $model -> where('data_id',$res) -> count();
                                if ($$var < 1 && ($tableBit & pow(2, $table['bit'])) > 0) {

//                            // 布置辐射表数据
                                    $sets = $var.'_sets';
                                    // 根据keys的映射完成数据的装填
                                    foreach ($keys as $index => $item)
                                    {
                                        // 判断当前数据名是否在
                                        if (in_array($item['name'], $tableEntity[$table['bit']]))
                                        {
                                            if(strtolower($data[$index]) == 'nan'           // 判断数据是否有效
                                                || $data[$index] > $item['range'][1]
                                                || $data[$index] < $item['range'][0])
                                            {
//                                                $output->writeln($data[$index]);
                                                $arr[$item['name']] = null;
                                            }
                                            else
                                            {
                                                if (!empty($item['calculation'])){
                                                    if ($item['calculation'] == 'times')
                                                    {
                                                        $arr[$item['name']] = $data[$index] * $item['scale'];

                                                    }
                                                    else if ($item['calculation'] == 'plus')
                                                    {
                                                        $arr[$item['name']] = $data[$index] + $item['scale'];
                                                    }
                                                }
                                                else
                                                {
                                                    $arr[$item['name']] = $data[$index];
                                                }
                                            }


                                        }
                                    }
//
                                    // 将当前数据ID添加至数据中用以映射数据
                                    $arr['data_id'] = $res;
                                    $$sets = array_merge($$sets,[$arr]);
                                }

                                if (count($$sets) < $offest) continue;              // 数据未到填充总量时

                                $output ->writeln('nowsets:'.$sets.',setcount:'.count($$sets));

                                if (count($$sets) > 0)
                                {
                                    $model -> saveAll($$sets);
                                    $$sets = [];
                                }
                            }
                        }
                        $i++;
                    }
                    catch(ErrorException $e) {
                        echo $e -> getLine();
                    }


                }
            }
            $total_end = time();
            $diff = $this -> diffTime($total_start,$total_end);
            $output->writeln(iconv('utf-8','GBK','总运行时间：'.$diff->format('%h小时').$diff->format('%i分钟').$diff->format('%s秒')));

            $output->writeln("over");
        }
//        $taskkill = $_SERVER["windir"]."/system32/taskkill.exe";       //找到windows系统下tasklist的路径
//        $mypid = array_pop($pids);
//        $command = $taskkill . " /pid $mypid /f" ;
//        @exec($command);
    }

public function  diffTime($start, $end) {
    $d_start    = new DateTime(date('Y-m-d H:i:s',$start));
    $d_end      = new DateTime(date('Y-m-d H:i:s',$end));
    $diff = $d_start->diff($d_end);
    return $diff;
}

    public function readAllData($path)
    {
        $handle = fopen($path, 'rb');

        while (feof($handle)===false) {
            # code...
            yield fgets($handle);
        }

        fclose($handle);
    }

    public function getCount($model,$dataid)
    {
        return $model::where('data_id',$dataid) -> count();
    }

}
