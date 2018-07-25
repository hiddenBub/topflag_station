<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
//function merge_recursion ($array1,$array2)
//{
//    if (is_array($array1) && is_array($array2)) {
//        foreach ($array2 as $index => $item) {
//            if (is_array($item))
//        }
//    }
//}

function myExplode($delimiter, $string, $limit = false, $allowEmpty = true)
{
    if ($limit) {
        $exploded = explode($delimiter,$string,$limit);
    }
    else {
            $exploded = explode($delimiter,$string);
    }

    if ($allowEmpty && !empty($exploded))
    {
        $exploded = array_values(array_filter($exploded));
    }
    return $exploded;
}

function  diffTime($start, $end) {
    $d_start    = new DateTime(date('Y-m-d H:i:s',$start));
    $d_end      = new DateTime(date('Y-m-d H:i:s',$end));
    $diff = $d_start->diff($d_end);
    return $diff;
}

function array_sum_recursive($array) {
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
