<?php
/**
 * Created by PhpStorm.
 * User: Mloong
 * Date: 2018/11/14
 * Time: 18:02
 */

namespace app\model\user;

use app\model\station\StationData;
use Json;
use think\Model;
class Sign extends Model
{
    /***
     * @param $account
     * @param $password
     * @param $redirect
     */
    public function in($account, $password, $redirect)
    {
        $admin = Db::table('user')->where(['name'=>$account, 'password'=>$password])->find();
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

    private function encrypt($explicit, $salt)
    {

    }

    private function createSalt($length = 6)
    {

        // salt取值范围
        $range = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890~!@#$%^&*()_+';
        // 打乱字符串
        $shuffle = str_shuffle(str_shuffle($range));
        // 截取需要的子串
        $sub = substr($shuffle,rand(0,strlen($shuffle) - $length), $length);
        // 返回子串
        return $sub;

    }
    /***
     * 检查登录状态
     */
    public function check($userID)//检查登录
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

    public function out($userID) {
        Session::clear();
        $this->redirect('index/index');
    }
}