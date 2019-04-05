<?php
/**
 * Created by PhpStorm.
 * User: Mloong
 * Date: 2018/4/18
 * Time: 20:03
 */

namespace app\cmd\command;



use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use app\model\Data;
use app\model\data\DataTable;
use app\model\data\DataUnit;
use app\model\Station;
use app\model\Source;
use app\model\station\StationData;
use app\model\station\StationDataHumiture;
use app\model\station\StationDataIrradiance;
use app\model\station\StationDataWind;
use DateTime;
use think\exception\ErrorException;
use \think\Log;

class schedulePull extends Command
{

    protected function configure()
    {
        $this->setName('schedule')->setDescription('pull LogerNet\'s dat type data file');
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
                        'dataRange' => $row['range'],
                        'table'     => $row['table_name']
                    ]
                ], $varNames);
            }, $variable);
//            dump($varNames);die;
            $mongoDb = Db::connect(Config('mongo_db'));
            $mongoDb -> table('data') -> delete(true);
            $count = $mongoDb -> table('data') -> count();
            $output -> writeln('records total:'.$count);
            $partten = '/(?!\d|"),(?! )/';
            $offset = 10000;
            // 遍历站点
            foreach ($station as $s)
            {
                // 查找数据库中创建时间最大值
                $max = $s['offset'];
                // 获取文件存储位置
                $path = $s['storage_path'];

                // 数据索引-标准变量名映射数组
                $keys=[];

                // 数据表位，是否有该表数据依据
                $tableBit = 0;
                if ($s['source_data'] == 'file')
                {

                    // 数据行号
                    $i = 1;
                    // 数据索引-标准变量名映射数组
                    $handle = fopen($path,'r');
                    $startLine = 2;
                    $endLine = 5;
                    $head = [];
                    $data = [];
                    $startdata = [];

                    while ($line = fgets($handle))
                    {
                        if ($i >= $startLine && $i <= $endLine) {
                            $head[] = array_map(function($row) use($i,&$keys){
                                return trim(trim($row),'"');
                            },preg_split($partten, $line));
                            if ($i == $startLine)
                            {
                                foreach ($head[0] as $key => $value)
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
                            elseif($i == $startLine + 1)
                            {
//                                dump($head[1]);die;
                                foreach ($head[1] as $key => $value)
                                {
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
                            // 最后一行为第一条数据
                            if ($i == $endLine) {
                                $startdata = array_pop($head);
                                break;
                            }

                        }
                        $i++;
                    }
//                    dump($keys);die;
                    $pos = 0;
                    $body = [];
                    $line = '';
                    while (fseek($handle, $pos, SEEK_END) !== -1)
                    {
                        $character = (string) fgetc($handle);
                        if ($character != "\n" && $character != "\r")
                        {
                            $line .= $character;
                        }
                        elseif($character == "\n" && !empty($line))
                        {
                            // 取得正序字符串
                            $ascend = strrev($line);
                            // 将数据分割成标准数据
                            $enddata = array_map(function($row){
                                return trim(trim($row),'"');
                            }, preg_split($partten, $ascend));
                            unset($ascend,$character,$line,$pos);
                            break;
                        }
                        $pos --;
                    }
                    fclose($handle);
                    // 获取当前操作文件大小
                    $fileSize = filesize($path);
                    // 初始化文件移动结果
                    $moveRes = false;
                    // 文件是否大于1G
                    // 由于该文件每分钟执行，则可以保证每个文件不会过大
                    $fileName = '';
                    if ($fileSize > pow(1024, 3))
                    {
                        $fileName = pathinfo($path,PATHINFO_DIRNAME).'\\'.pathinfo($path,PATHINFO_FILENAME).strtotime($startdata[0]).'_'.strtotime($enddata[0]).pathinfo($path, PATHINFO_EXTENSION);
                        $moveRes = rename($path,$fileName);
                    }
                    // 没有移动文件或文件移动失败

                    $file = !empty($fileName) && file_exists($fileName) ? $fileName : $path;
                    $output -> writeln('open file :'.$file);
                    $contents = file_get_contents($file,false, null, $max);
                    // 按行拆分数据为数组
                    $data = explode("\n",$contents);
                    // 遍历行数据
                    foreach ($data as $no => $line)
                    {
                        // 数据按照正则拆分列
                        $d = array_map(function($row) use ($head,$s,$mongoDb) {
                            // 获取数据数组
                            return trim(trim($row),'"');
                            // 判断当前数据是否是一个标准数据

                        }, preg_split($partten, $line));
                        $arr = [];
                        if (($d[0] = strtotime($d[0])) !== false)             // 数据为标准数据
                        {
                            $arr['station_id'] = $s['station_id'];
                            $arr['TIMESTAMP']   = $d[0];
                            $arr['tableBit']   = $tableBit;
                            foreach ($keys as $index => $item)
                            {
                                if(strtolower($d[$index]) == 'nan'           // 判断数据是否有效
                                    || $d[$index] > $item['range'][1]
                                    || $d[$index] < $item['range'][0])
                                {
                                    $arr[$item['name']] = null;
                                }
                                else
                                {
                                    if (!empty($item['calculation'])){
                                        if ($item['calculation'] == 'times')
                                        {
                                            $arr[$item['name']] = $d[$index] * $item['scale'];

                                        }
                                        else if ($item['calculation'] == 'plus')
                                        {
                                            $arr[$item['name']] = $d[$index] + $item['scale'];
                                        }
                                    }
                                    else
                                    {
                                        $arr[$item['name']] = $d[$index];
                                    }
                                }

                            }
                            // 将当前数据ID添加至数据中用以映射数据


                            // 将数据添加至mongodb
                            $mongoDb->table('data') -> insert($arr);
                            unset($headNew,$bodyNew);
                        }
                    }
                    $output -> writeln('file closed');

                    $set['station_id'] = $s['station_id'];
                    $set['offset'] = $moveRes ? 0 : filesize($file);
                    $saveRes = Station::update($set);


                }
                elseif ($s['source_data'] == 'database')
                {
                    $stationID = $s['shadow'] > 0 ? $s['shadow'] : $s['station_id'];

                    $modelName = 'app\model\\'.$path;
                    $output ->writeln('open database '.$path);
                    $model = new $modelName();
                    while($list = $model -> alias('d')
                        -> join('tf_station_data_humiture h', 'd.data_id=h.data_id')
                        -> join('tf_station_data_irradiance i', 'i.data_id = d.data_id')
                        -> join('tf_station_data_wind w', 'd.data_id = w.data_id')
                        -> field('d.data_id,d.station_id,d.ctime AS TIMESTAMP,h.panel_temp,h.temperature,h.humidity,i.GHI,i.GTI,w.wind_speed,w.wind_direction')
                        -> where("d.station_id = {$stationID}")
                        -> where("d.data_id > $max")
                        -> order('d.ctime')
                        -> limit($offset)
                        -> select())
                    {
                        $output ->writeln('processing '.count($list).' records');
                        foreach($list as $l)
                        {
                            $l = [
                                'station_id'    => $s['station_id'],
                                'TIMESTAMP'     => $l['TIMESTAMP'],
                                'tableBit'      => 35,
                                'panel_temp'    => $l['panel_temp'],
                                'air_temperature'   => $l['temperature'],
                                'relative_humidity'      => $l['humidity'],
                                'GHI'           => $l['GHI'],
                                'GTI'           => $l['GTI'],
                                'wind_speed'    => $l['wind_speed'],
                                'wind_direction' => $l['wind_direction'],
                            ];
                            $mongoDb -> table('data') -> insert($l);
                        }
//                        dump($list);die;

                        $max = $list[count($list) - 1]['data_id'];
                    }

                    $set = [
                        'station_id'    => $s['station_id'],
                        'offset'        => $max,
                    ];
                    $saveRes = Station::update($set);
                    $output ->writeln('database closed');
                }
//                if ($saveRes)
//                {
                $count = $mongoDb -> table('data') -> count();
                $output -> writeln('records total :'.$count);
//                }


#######################################################################################


            }

            $start = 0;
            try{
                foreach ($station as $index => $stationItem)
                {
                    $stationData = $mongoDb -> table('data') -> where('station_id','=',$stationItem['station_id']) -> limit(0,1) -> find();
                    // 当前有数据则更改数据
                    if (!empty($stationData))
                    {
                        $stationData = array_keys($stationData);
                        $keys = [];
                        foreach ($stationData as $r)
                        {
                            if (array_key_exists($r,$varNames)){
                                $keys[] = $r;
                            }
                        }
                        $fieldsData['fields'] = implode(',',$keys);
                        Station::where('station_id','=',$stationItem['station_id']) -> update($fieldsData);
                    }

                }


                // 从mongoDB中获取数据
                while($raw = $mongoDb -> table('data') -> where('TIMESTAMP','>=', $start) -> order('TIMESTAMP','asc') -> limit($offset) -> select())
                {
                    // dump($raw);die;

                    $last = $raw[count($raw) - 1];

                    if($raw[0]['TIMESTAMP'] == $last['TIMESTAMP']) break;
                    $data_id = StationData::max('data_id');
//                    dump($raw);die;
                    /*********************初始化数据**********************/
                    // 重设数据集
                    $irradiance = [];
                    $irradiance_sets = [];

                    $wind = [];
                    $wind_sets = [];

                    $humiture = [];
                    $humiture_sets = [];

                    $other = [];
                    $other_sets = [];

                    $pressure = [];
                    $pressure_sets = [];

                    $rain = [];
                    $rain_sets = [];

                    $data_sets = [];
                    /*********************初始化结束***************************/
                    // 输出当前处于工作区的数据
                    $output->writeln(iconv('utf-8','GBK', 'processing...'.date('Y-m-d H:i:s', $raw[0]['TIMESTAMP']).' - '.date('Y-m-d H:i:s', $raw[count($raw) - 1]['TIMESTAMP'])));

                    foreach ($raw as $key => $item)
                    {
                        if ((count($data_sets) + 1) > 0 && (count($data_sets) + 1) % ($offset / 10) == 0) echo iconv('utf-8','GBK','■');
                        $next = (int)$data_id + 1;
                        $l = StationData::where('station_id', $item['station_id']) -> where('ctime', $item['TIMESTAMP']) -> find();
                        if (empty($l['data_id']) && count($data_sets) == 0 ) {

                            $data_sets[] = [
                                'data_id'       => $next,
                                'station_id'    => $item['station_id'],
                                'ctime'         => $item['TIMESTAMP'],
                            ];

                            $res = $next;
                            $data_id = $next;
                        }
                        elseif (empty($l['data_id']) && count($data_sets) > 0 && ($data_sets[count($data_sets) - 1]['station_id'] != $item['station_id'] || $data_sets[count($data_sets) - 1]['ctime'] != $item['TIMESTAMP']))
                        {
                            $data_sets[] = [
                                'data_id'       => $next,
                                'station_id'    => $item['station_id'],
                                'ctime'         => $item['TIMESTAMP'],
                            ];

                            $res = $next;
                            $data_id = $next;
                        }
                        else{
                            continue;
                        }
                        $temp = [];

                        foreach ($item as $k => $v)
                        {
                            if (array_key_exists($k,$varNames)){
                                $parts = explode('_',$varNames[$k]['table']);
                                $var = $parts[count($parts) - 1];
                                $temp[$var][$k] = strtolower($v) == 'null' ? null : $v;
                                if (!in_array($k,$$var)) $$var = array_merge($$var,[$k]);
                            }

                        }
//                        dump($$var);die;
                        foreach ($temp as $name => &$t)
                        {
                            $sets = $name.'_sets';
                            $t['data_id'] = $res;
                            $$sets = array_merge($$sets,[$t]);
                        }

                    }
                    $timein = time();
//                    dump($data_sets);die;
                    $model_data = new StationData();
                    $model_data -> saveAll($data_sets, false, false);
                    foreach ($tables as $table)
                    {
                        $parts = explode('_',$table['table_name']);
                        $var = $parts[count($parts) - 1];
                        $sets = $parts[count($parts) - 1].'_sets';
                        if (!empty($$sets))
                        {
                            $fields = implode(',', $$var);
                            $sql = "INSERT INTO `{$table['table_name']}` (data_id,{$fields}) VALUES ";
                            $st = '';
                            foreach ($$sets as $value)
                            {
                                $st .= '('.$value['data_id'].',';
                                foreach ($$var as $va)
                                {
                                    if(array_key_exists($va,$value))
                                    {
                                        if (strtolower($value[$va]) == 'null' || is_null($value[$va])  ) {
                                            $st .= 'null,';
                                        }
                                        elseif(!is_numeric($value[$va]))
                                        {
                                            $st .= "'{$value[$va]}',";
                                        }
                                        else
                                        {
                                            $st .= $value[$va].',';
                                        }
                                    }
                                    else
                                    {
                                        $st .= 'null,';
                                    }
                                    

                                }
                                $st = rtrim($st,',');
                                $st .= '),';
                            }
                            $sql .= rtrim($st,',');
// $output -> writeln($sql);
                            DB::execute($sql);

                        }
                    }
                    $timeout = time();
                    $timeDiff = $this -> diffTime($timein,$timeout);
                    $output->writeln(iconv('utf-8','GBK','插入耗时：'.$timeDiff->format('%h小时').$timeDiff->format('%i分钟').$timeDiff->format('%s秒')));
                    $start = $raw[count($raw) - 1]['TIMESTAMP'];
                    echo "\n";
                }
            }
            catch(ErrorException $e) {
                echo 'line:'.$e -> getLine().',error:'.$e ->getMessage()."\n".$e -> getFile();
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
