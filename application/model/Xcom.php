<?php
/**
 * Created by PhpStorm.
 * User: Mloong
 * Date: 2019/4/1
 * Time: 18:17
 */

namespace app\model;


use think\queue\connector\Topthink;

class Xcom extends Topthink
{
    protected $options = [
        'token'         => '',
        'project_id'    => '',
        'protocol'      => 'https',
        'host'          => 'qns.topthink.com',
        'port'          => 443,
        'api_version'   => 1,
        'max_retries'   => 3,
        'default'       => 'default',
        'account'       => '',
        'password'      => '',
        'parameters'    => ''
    ];


    public function __construct($options)
    {
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        // source code is down below
//        $this->url = "{$this->options['protocol']}://{$this->options['host']}:{$this->options['port']}/v{$this->options['api_version']}/";
        $this->url = "{$this->options['protocol']}://";
        if (!empty($this->options['account']) && !empty($this->options['password']))
            $this -> url .= "{$this->options['account']}:{$this->options['password']}@";
        $this -> url .= "{$this->options['host']}:{$this->options['port']}/";
        $this -> url .= "{$this->options['parameters']}";

        $this->headers['Authorization'] = "Bearer {$this->options['token']}";

        $this->request = Request::instance();
    }
}