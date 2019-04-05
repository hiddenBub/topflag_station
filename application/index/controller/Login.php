<?php
/**
 * Created by PhpStorm.
 * User: Mloong
 * Date: 2018/11/6
 * Time: 14:59
 */

namespace app\index\controller;

use think\Db;
use think\Validate;
use think\Controller;
use think\Session;
use think\Request;

class Login extends Controller
{
    /**
     * 构造时执行初始化方法
     * 并在初始化时检查登录状态
     */
    public function initialize()
    {
        $event = controller('login','controller');
        $event->check();
    }

    //用户登录
    public function index()
    {
        if($this->request->isAjax()){
            $account = $this->request->param('account');
            $password = $this->request->param('password');
            $admin = Db::table('admin')->where(['account'=>$account, 'password'=>$password])->find();
            if($admin['id']){
                Session::set('admin',$admin);

                //判断是否应该跳转到上次url
                if(!empty(Session::get('redirect_url'))){
                    $url = Session::get('redirect_url');
                    Session::delete('redirect_url');
                }else{
                    $url = url('index/index');
                }

                exit( json_encode(['check'=>1, 'msg'=>'登录成功！', 'url'=>$url]) );

            }else{
                exit( json_encode(['check'=>0, 'msg'=>'账号或密码错误']) );
            }
        }

        if(is_numeric(Session::get('admin.id'))){
            $this->redirect('index/index');
        }
        return $this->fetch();
    }

    public function check()//检查登录
    {
        if(!is_numeric(Session::get('admin.id'))){

            if($this->request->isAjax()){
                exit( json_encode(['check'=>2, 'url'=>url('login/index')]) );
            }else{
                redirect()->remember();
                $this->redirect('login/index');
            }
        }
    }

    public function out() {
        Session::clear();
        $this->redirect('index/index');
    }
}



