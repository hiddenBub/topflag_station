<?php
/**
 * Created by PhpStorm.
 * User: Mloong
 * Date: 2018/11/14
 * Time: 18:32
 */

namespace app\index\controller;


use think\Controller;

class Base extends Controller
{
    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub

        /*if(!session('adminId')){
            $this->redirect(U('Admin/Login/index'));
        };

        //判断会话是否过期
        if (time() - session('session_start_time') > C('SESSION_OPTIONS')['expire']) {
            session_destroy();//真正的销毁在这里！
            $this->redirect(U('Admin/Login/index'));
        }*/
    }
}