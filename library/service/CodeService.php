<?php
/**
 * @author  : axios
 * @email   : axioscros@aliyun.com
 * @blog    :  http://hanxv.cn
 * @datetime: 2017/8/2 上午8:56
 */

namespace library\service;

use tpr\db\DbRedis;

/**
 * Class CodeService
 * @desc    短信验证码功能服务类
 * @package library\service
 */
class CodeService
{
    public static $code = 200;

    public static $msg = '';

    public static $prefix = '_verify_code_';

    public static $instance;

    public static $rest = '';

    public function __construct($rest)
    {
        self::$rest = $rest;
    }

    public static function key($to)
    {
        return self::$rest . self::$prefix . $to;
    }

    public static function rest($rest = 'default')
    {
        if (self::$instance == null) {
            self::$instance = new static($rest);
        }

        if (self::$rest != $rest) {
            self::$rest = $rest;
        }

        return self::$instance;
    }

    public function set($to, $time = 60)
    {
        $key  = self::key($to);
        $code = mt_rand(100000, 999999);

        $result = SmsService::instance('sms.' . self::$rest)->sendCode($to, $code, 'code');

        if ($result) {
            DbRedis::init()->redis()->set($key, strval($code), $time);
        } else {
            self::$code = "500";
            self::$msg  = "message send service error";
        }
        return $result;
    }

    protected static function get($to)
    {
        $key = self::key($to);
        return DbRedis::init()->redis()->exists($key) ? DbRedis::init()->redis()->get($key) : null;
    }

    public function check($to, $code)
    {
        $verify_code = self::get($to);

        if (is_null($verify_code)) {
            self::$code = 40411;
            return false;
        }

        if (strval($code) != strval($verify_code)) {
            $count_error_key = self::key($to) . 'error';
            $result          = DbRedis::init()->count($count_error_key);
            if ($result === false) {
                $result     = DbRedis::init()->redis()->set($count_error_key, 1, ['ex' => 60]);
                self::$code = 4000;
            }
            if ($result > 3) {
                DbRedis::init()->redis()->delete(self::key($to));
                DbRedis::init()->redis()->delete($count_error_key);
                self::$code = 40411;
            }
            return false;
        }

        return true;
    }
}