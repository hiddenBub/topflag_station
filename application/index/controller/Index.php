<?php
namespace app\index\controller;

use app\model\Chart;
use think\Controller;
use app\model\station\StationData;
use app\model\Station;
use think\request;

class Index extends Controller
{
    public function index()
    {
        $station =  Station::field('station_id,station_name') ->where('status','1') -> select();
        $charts =   Chart::field('chart_id,name') -> select();
//        dump($station);die;
        $this -> assign('station', $station);
        $this -> assign('charts', $charts);
        return view('index');



//        return '<style type="text/css">*{ padding: 0; margin: 0; } .think_default_text{ padding: 4px 48px;} a{color:#2E5CD5;cursor: pointer;text-decoration: none} a:hover{text-decoration:underline; } body{ background: #fff; font-family: "Century Gothic","Microsoft yahei"; color: #333;font-size:18px} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.6em; font-size: 42px }</style><div style="padding: 24px 48px;"> <h1>:)</h1><p> ThinkPHP V5<br/><span style="font-size:30px"><十年>   </十年>磨一剑 - 为API开发设计的高性能框架</span></p><span style="font-size:22px;">[ V5.0 版本由 <a href="http://www.qiniu.com" target="qiniu">七牛云</a> 独家赞助发布 ]</span></div><script type="text/javascript" src="https://tajs.qq.com/stats?sId=9347272" charset="UTF-8"></script><script type="text/javascript" src="https://e.topthink.com/Public/static/client.js"></script><think id="ad_bd568ce7058a1091"></think>';
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
            $res[$key]['latLng'] = [floatval($value['lat']),floatval($value['lng'])];
        }

        return json($res);
    }

    public function phpinfo()
    {
        phpinfo();
    }
}
