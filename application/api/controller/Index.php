<?php
/**
 * Created by PhpStorm.
 * User: Mloong
 * Date: 2018/11/6
 * Time: 15:53
 */

namespace app\api\controller;

use Think\Config;

class Index
{
    protected $_data = array('status' => 0, 'error' => null, 'data' => null);
    protected $_resultSmartyType = array();

    public function setData($k, $v = null)
    {
        // 设置整个数据集
        if (is_array($k))
        {
            $this->_data = $k;
            return;
        }
        // 设置单值（二维数组）
        if (strstr($k, '.'))
        {
            $h = explode('.', $k);
            $this->_data[$h[0]][$h[1]] = $v;
        }
        // 设置单值一维数组
        else
        {
            $this->_data[$k] = $v;
        }

        return $this;
    }

    /**
     * 设置错误: $this->_data['error']...
     *
     * @param int $code 错误码
     * @param string $msg 错误消息
     * @return AdminAbstract
     */
    public function setError($code, $msg = null)
    {
        // 设置错误数组
        if (is_array($code))
        {
            $this->_data['error'] = $code;
        }
        //
        elseif (is_string($code))
        {
            if (! is_null($msg))
            {
                $this->_data['error'][$code] = $msg;
            }
            else
            {
                $this->_data['error'][] = $code;
            }
        }

        return $this;
    }

    /**
     * 设置返回数据状态: $this->_data['status']...
     *
     * @param mixed $status
     * @return AdminAbstract
     */
    public function setStatus($status)
    {
        $this->_data['status'] = $status;
        return $this;
    }

    /**
     * Get error message.
     *
     * @return array
     */
    public function getError()
    {
        return @$this->_data['error'];
    }

    /**
     * 返回错误提示信息
     *
     * @param array
     */
    public function _commonPrompt($arr = array())
    {
        $p = http_build_query($arr);
        header("Location: /commonPrompt?{$p}");
        exit;
    }

    /**
     * 接口入口 - 对内接口调用
     */
    public function index ()
    {
        $res = $this->_validate();
        if ($res['status'] != 0) {
            $this->setStatus($res['status']);
            $this->setMsg($res['error']);
            return json($this -> _data);
        }

        $params = $res['data']['params'];
        $params_app = $res['data']['params_app'];
        Config::load(APP_PATH.'api/apimap.php');


        $apimap = Config::get('apimap');
        if (! $apimap) {
            $this->setStatus(1);
            $this->setMsg('配置文件 apimap.php 缺失');

            return json($this -> _data);
        }

        $key = $params_app['app'] . '.' . $params_app['class'];

        if (! isset($apimap[$key])) {
            $this->setStatus(1);
            $this->setMsg("配置文件 apimap.php 中 {$key} 参数缺失");
//            return json($this -> _data);
        }


        $result = $apimap[$key]['class']::run($params);
        //$result = $result['data'];
        /* 接结果进行过滤 */
        $result = $this->_filter($result);
        $this->setStatus($result['status']);
        $this->setMsg($result['error']);
        $this->setData("data",array("data"=>$result['data']));
        return json($this -> _data);
    }

    private function _validate()
    {
        $res = array('status' => 0, 'error' => '', 'data' => '');

        if (! $this->input->isPOST()) {
            $res['status'] = 1;
            $res['error'] = '请求方式不正确';
            return $res;
        }

        /*$configs_apps = Zeed_Config::loadGroup('interface.apps');
        if (! $configs_apps) {
            $res['status'] = 1;
            $res['error'] = '配置文件 interface.php 缺失';
            return $res;
        }*/

        /* 获取参数，并做基础处理 */
        $params = $this->input->post();
        if (empty($params['app']) || empty($params['class']) /*|| empty($params['sign'])*/) {
            $res['status'] = 2;
            $res['error'] = '缺少参数或参数错误';
            return $res;
        }

        /* 获取密钥
        if ($configs_apps[$params['app']]) {
            $secret = $configs_apps[$params['app']]['secret'];
        } else {
            $secret = $configs_apps['default']['secret'];
        }

        $sign_local = MD5($params['app'] . $params['class'] . $secret);
//         if ($sign_local !== $params['sign']) {
//             $res['status'] = 1;
//             $res['error'] = '未经授权，拒绝访问';
//             return $res;
//         }*/

        $res['data']['configs_apps'] = $configs_apps[$params['app']];
        $res['data']['params_app'] = array(
            'app' => $params['app'],
            'class' => $params['class']
        );
        unset($params['app'], $params['class'], $params['sign']);
        $res['data']['params'] = $params;

        /* 处理上传图片 */
        if (! empty($_FILES)) {
            $this->addUploadFile($_FILES);
        }

        return $res;
    }

    /**
     * 上传图片 - 支持多张
     */
    public function addUploadFile ($file)
    {
        /* 取得该数组的key */
        foreach ($file as $k => $v) {
            if ($v['error'] == UPLOAD_ERR_OK) {
                $upload_file = $v['tmp_name'];
                $_POST[$k] = "@{$upload_file}";
            }
        }
    }

    /**
     * 对接口返回的结果进行过滤
     */
    private function _filter ($arr)
    {
        $arr_json = json_encode($arr);
        $arr_json = str_replace(':null', ':""', $arr_json);
        // $arr_json = str_replace(':[]', ':null', $arr_json);
        // $arr_json = str_replace(':{}', ':null', $arr_json);
        // 为了解决空对象输出成空数组的问题
        /*$arr_json = str_replace(':{}', ':null', $arr_json);
        $arr_json = str_replace(':[]', ':null', $arr_json);
        $arr_json = str_replace(':""', ':null', $arr_json);*/
        $arr = json_decode($arr_json, true);
        return $arr;
    }



    /**
     * 设置错误: $this->_data['msg']...
     *
     * @param int $code 错误码
     * @param string $msg 错误消息
     * @return AdminAbstract
     */
    public function setMsg($code, $msg = null)
    {
        if (is_array($code)) {
            $this->_data['error'] = $code;
        } elseif (is_string($code)) {
            if (! is_null($msg)) {
                $this->_data['error'][$code] = $msg;
            } else {
                $this->_data['error'] = $code;
            }
        }
        return $this;
    }

    /**
     * Get message.
     *
     * @return array
     */
    public function getMsg()
    {
        return @$this->_data['error'];
    }
}