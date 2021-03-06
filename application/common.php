<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 王玮 <wwkillleng@sina.com>
// +----------------------------------------------------------------------

// 应用公共文件
/**
 * php原生explode方法拓展
 * 添加去除空值参数
 * @param string|array  $delimiter  分割符
 * @param string        $string     被分割的字符串
 * @param bool          $limit      分割数量限制
 * @param bool          $allowEmpty 是否允许空字符串出现
 * @return array    分割后的数组
 */
function myExplode($delimiter,$string, $limit = 0, $allowEmpty = true)
{
    try{
        // 初始化方法所需变量
        $exploded = [];
        $myDelimiter = '︴';
        if (!is_string($delimiter) && !is_array($delimiter)) throw new InvalidArgumentException('Invalid variable: delimiter');
        if (!is_string($string)) throw new InvalidArgumentException('Invalid variable: string');
        if (!is_int($limit)) throw new InvalidArgumentException('Invalid variable: limit');
        if (!is_bool($allowEmpty)) throw new InvalidArgumentException('Invalid variable: allowEmpty');

        // 替换所有分隔符
        if (is_array($delimiter) && count($delimiter) > 0)
        {
            foreach ($delimiter as $key => $item)
            {
                $string = str_replace($item,$myDelimiter,$string);
            }
        }
        else{
            $string = str_replace($delimiter,$myDelimiter,$string);
        }

        $exploded = explode($myDelimiter,$string);

        // 剔除空项
        if (!$allowEmpty && !empty($exploded)) $exploded = array_values(array_filter($exploded));

        // 限制数据容量
        if (count($exploded) > 0 && $limit > 0) $exploded = array_slice($exploded,0, $limit);
    }
    catch (Exception $e)
    {
        echo $e -> getMessage();
    }


    return $exploded;
}

/**
 * 比较时间差
 * @param int $start 起始unix时间戳
 * @param int $end  结束unix时间戳
 * @return bool|DateInterval 经历的时间
 */
function  diffTime($start, $end) {
    $d_start    = new DateTime(date('Y-m-d H:i:s',$start));
    $d_end      = new DateTime(date('Y-m-d H:i:s',$end));
    $diff = $d_start->diff($d_end);
    return $diff;
}

/**
 * 递归数组求和
 * @param array $array 需求和的数组
 * @return int 求和后的数值
 */
function array_sum_recursive(array $array) {
    $sum = 0;
    if (is_array($array)) {
        foreach ($array as $value) {
            // 传递进来为数组，递归
            if (is_array($value)) {
                $sum += array_sum_recursive($value);
            }
            // 非数组，求和
            else {
                $sum += $value;
            }
        }
    }
    return $sum;
}

//function recursive_implode($glue = null, $array = null, $flag = true) {
//    $str = '';
//    if (is_string($glue)) {
//        $str = implode($glue,$array);
//    }
//    elseif (is_array($glue)) {
//
//        foreach ($array as &$value) {
//            if (is_array($value)) {
//                $value = recursive_implode($glue, $array, false). $glue[0];
//            }
//        }
//
//            $str = !$flag ? implode($glue[1],$array) : im;
//
//
//    }
//
//    return $str;
//}
