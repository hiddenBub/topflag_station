<?php
/**
 * Created by PhpStorm.
 * User: wwkil
 * Date: 2018/4/12
 * Time: 17:56
 */

namespace app\model;
use think\Model;

class Source extends Model
{
    // 设置数据源数据表名称
    protected $table = 'obsdata';

    // 设置连接参数
    protected $connection = [
        // 数据库类型
        'type'      => 'mysql',
        // 服务器IP
        'hostname'  => '39.106.70.104',
        // 数据库名称
        'database'  => 'topflag',
        // 登陆用户名称
        'username'  => 'root',
        // 登录用户密码
        'password'  => '123456789',
        // 数据库字符集
        'charset'   => 'utf8',
        /*// 数据库类型
        'type'            => 'mysql',
        // 服务器地址
        'hostname'        => '127.0.0.1',
        // 数据库名
        'database'        => 'olddata',
        // 用户名
        'username'        => 'root',
        // 密码
        'password'        => '123456',
        // 端口
        'hostport'        => '3306',
        // 数据库字符集
        'charset'   => 'utf8',*/
        // 是否开启数据库调试模式
        'debug'     => false,
    ];

}