<?php
namespace app\index\controller;

use app\model\Chart;
use think\Controller;
use think\Response;
use app\model\station\StationData;
use app\model\Station;
use think\request;
use think\Cache;

class Index extends Controller
{
    public function index()
    {
        $station =  Station::field('station_id,station_name') ->where('status','1') -> select();
        $charts =   Chart::field('chart_id,name') -> where('status', 1) -> select();
//        dump($station);die;
        $this -> assign('station', $station);
        $this -> assign('charts', $charts);
        return view('index');




    }

    public function inf ()
    {
        $station =  Station::field('station_id,station_name') ->where('status','1') -> select();
        $this -> assign('station', $station);
        return view('inf');
    }

    public function testDatFile()
    {
        // 数据基站ID
        $station_id = Request::instance() -> post('station');
        // 时间跨度
        $timeRange = Request::instance() -> post('timeLine');
        // 创建数据库模型
        $model = new Station();
        $station = $model -> field('storage_path,station_name') -> where('station_id', $station_id) -> find();
        if (!empty($station['storage_path']) && file_exists($station['storage_path']))
        {


            $handle = fopen($station['storage_path'], 'rb');
            $length = 0;
            // 获取时间范围时间戳
            $range = array_map('strtotime',(explode(' - ',$timeRange)));
            while (feof($handle)===false) {
                # code...
                $line = fgets($handle);

                // 遍历写入

                $lineData = explode(',',$line);
                $record = strtotime(trim($lineData[0],'"'));
                if ($record > $range[0] && $record < $range[1]){
                    $length++;
                }
            }

            fclose($handle);        // 关闭文件

            if ($length == 0) {     // 数据为空
                $res = [
                    'data'  => [],
                    'status'    => 1,
                    'msg'       => '没有数据或文件不存在',
                ];
            }
            else                    // 有数据
            {
                $res = [
                    'data'  => [],
                    'status'    => 0,
                    'msg'       => '数据正常',
                ];
            }
        }
        else
        {
            $res = [
                'data'      => [],
                'status'    => 1,
                'msg'       => '没有数据或文件不存在'
            ];
        }
        return json($res);
    }

    public function getDatFile()
    {
        // 数据基站ID
        $station_id = Request::instance() -> post('station');
        // 时间跨度
        $timeRange = Request::instance() -> post('timeLine');
        // 创建数据库模型
        $model = new Station();
        $station = $model -> field('storage_path,station_name') -> where('station_id', $station_id) -> find();
        if (!empty($station['storage_path']) && file_exists($station['storage_path']))
        {
            // 文件位置
            $tempPath = 'D:\Campbellsci\LoggerNet\temp\data.dat';
            // 读取本地数据
            $file = $this->readFile($station['storage_path']);
            // 获取时间范围时间戳
            $Range = array_map('strtotime',(explode(' - ',$timeRange)));
            // 遍历写入
            foreach ($file as $line) {

                $lineData = explode(',',$line);
                $record = strtotime(trim($lineData[0],'"'));
                if ($record === false || ($record >= $Range[0] && $record <= $Range[1])){
                    $this -> writeFile($line,$tempPath);
                }

            }

            $handle = fopen ( $tempPath, "rb" );
            $file_Size = filesize ( $tempPath );
            $file_name = iconv("UTF-8","GB2312",$station['station_name'].$timeRange . '.dat');


            $data = fread($handle,$file_Size);
            fclose ( $handle );
            unlink($tempPath);
            return octet($data,200,['Accept-Length'=>$file_Size,'Content-Disposition'=>'attachment','filename'=>$file_name]);

        }
    }

    public function writeFile($content,$path)
    {
        $file_name = $path;

        $file_pointer = fopen($file_name, "a+");

        $lock = flock($file_pointer, LOCK_EX);
// 如果版本低于PHP4.0.2， 用 2 代替 LOCK_EX

        if ($lock) {

            fwrite($file_pointer, $content);
            flock($file_pointer, LOCK_UN);
// 如果版本低于PHP4.0.2， 用 3 代替 LOCK_UN

        }

        fclose($file_pointer);
    }

    public function readFile($path)
    {
        $handle = fopen($path, 'rb');

        while (feof($handle)===false) {
            # code...
            yield fgets($handle);
        }

        fclose($handle);
    }

    public function getChart()
    {
        // 查找图标ID
        $chart_id =     Request::instance() ->  post('id');
        // 图表信息粒度
        $granularity =  Request::instance() ->  post('granularity');
        // 图表基站筛选
        $station_id =   Request::instance() ->  post('station');
        // 创建对象实例
        $model = new Chart();
        // 调用chartData方法获取图表配置以及页面显示
        $data = $model->chartData($chart_id,$station_id,$granularity);
        $res['data']    = $data;
        $res['error']   = 0;
        $res['msg']     = '获取成功';
        return json($res);
    }

    public function taskList()
    {

        // 使用memcache作为缓存介质
        $memcache = Cache::store('memcache');
        $memcache -> set('foo', 1);
        $foo = $memcache ->get ('foo');

        dump($foo);
//        $memcache=new Memcache();
//        $memcache->set('foo','bar');
//
//
//        dump($memcache->get('foo'));

        die;
        $series = range(1, 32768);
        $prime = [];
        foreach ($series as $value) {
            $flag = 0;
            for($j = 2; $j < $value; $j++) {
                if ($value % $j == 0) {
                    $flag = 1;
                    $prime[] = $value;
                    break;
                }
                else {
                    continue;
                }
            }
//            if ($flag == 0) {
//                $bin = decbin($value);
//                $len = strlen($bin);
//                $prime[$len - 1][] = $value.' - '.$bin;
//            }

        }



        dump($prime);
    }

    public function getLngLat()
    {
        $station = Station::where('status',1) -> select();
        $res = [];
        foreach ($station AS $key => $value) {
            $res[$key]['name'] = $value['station_name'];
            $res[$key]['lngLat'] = [floatval($value['lng']), floatval($value['lat'])];
        }

        return json($res);
    }

    public function phpinfo()
    {
        phpinfo();
    }
}
