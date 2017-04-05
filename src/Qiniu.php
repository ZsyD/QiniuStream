<?php

namespace ZsyD\QiniuStream;

use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;

class Qiniu
{
    public static $error;

    protected static $auth;

    public static $bucket;

    public static $domain;

    protected static $bucketManager;

    protected static $uploadManager;

    protected static $_wrapperClients = [];

    public function __construct($accessKey, $secretKey)
    {
        if (!$this->auth($accessKey, $secretKey)) {

            return false;
        }

        self::$bucketManager = new BucketManager(self::$auth);
        //self::$uploadManager = new UploadManager();

        return $this;
    }

    /**
     * 获取空间名称 bucket
     *
     * @return $this
     */
    public static function getBucket()
    {
        return static::$bucket;
    }

    /**
     * 设置空间名称 bucket
     *
     * @param $bucket
     * @return $this
     */
    public function setBucket($bucket)
    {
        static::$bucket = $bucket;

        return $this;
    }

    /**
     * 获取域名 $domain
     *
     * @return $this
     */
    public static function getDomain()
    {
        return static::$domain;
    }

    /**
     * 设置域名
     *
     * @param $domain
     * @return $this
     */
    public function setDomain($domain)
    {
        static::$domain = $domain;

        return $this;
    }

    public function auth($accessKey, $secretKey)
    {
        if (!self::$auth) {
            if (empty($accessKey)) {

                self::$error = '请传入accessKey';

                return false;
            }
            if (empty($secretKey)) {

                self::$error = '请传入secretKey';

                return false;
            }

            self::$auth = new Auth($accessKey, $secretKey);
        }

        return self::$auth;
    }

    public static function getAuth()
    {
        return self::$auth;
    }


    /**
     * 注册自定义类的封装协议
     *
     * @param $name
     */
    public function registerStreamWrapper($name)
    {
        stream_register_wrapper($name, "ZsyD\\QiniuStream\\QiniuStream");
        $this->registerAsClient($name);
    }

    /**
     * 注销自定义类的封装协议
     *
     * @param $name
     */
    public function unRegisterStreamWrapper($name)
    {
        stream_wrapper_unregister($name);
        $this->unRegisterAsClient($name);
    }

    /**
     * 定义封装协议
     *
     * @param $name
     * @return $this
     */
    public function registerAsClient($name)
    {
        self::$_wrapperClients[$name] = self::$bucketManager;

        return $this;
    }

    /**
     * 删除指定名称的封装协议
     *
     * @param $name
     * @return $this
     */
    public function unRegisterAsClient($name)
    {
        unset(self::$_wrapperClients[$name]);

        return $this;
    }

    /**
     * 获取指定名称的封装协议
     *
     * @param $name
     * @return mixed
     */
    public static function getWrapperClient($name)
    {
        return self::$_wrapperClients[$name];
    }

    public static function uploadManager()
    {
        $upload = new UploadManager();

        return $upload;
    }

    public static function getUploadManager()
    {
        self::$uploadManager = self::$auth->uploadToken(self::$bucket);

        return self::$uploadManager;
    }

    /**********************功能**************************/

    /**
     * 获取账号下所有空间名
     *
     * @return bool|\string[]
     */
    public function lists()
    {
        return self::$bucketManager->buckets();
    }

    public function filesList()
    {
        return self::$bucketManager->listFiles(self::$bucket);
    }

    public function fileStat($key)
    {
        return self::$bucketManager->stat(self::$bucket, $key);
    }

    public function fileRename($oldName, $newName)
    {
        return self::$bucketManager->rename(self::$bucket, $oldName, $newName);
    }

    public function upload($filePath, $key,  array $option)
    {
        $uptoken = self::$auth->uploadToken(self::$bucket, $option['key'], $option['expires'], $option['policy']);
        $uploadMgr = new UploadManager();
        // 调用 UploadManager 的 putFile 方法进行文件的上传。
        list($ret, $err) = $uploadMgr->putFile($uptoken, $key, $filePath);
        echo "\n====> putFile result: \n";
        if ($err !== null) {
            var_dump($err);
        } else {
            var_dump($ret);
        }
    }

    public function stat($key)
    {
        list($ret, $err) = self::$bucketManager->stat(self::$bucket, $key);
        if ($err !== null) {
            var_dump($err);
        } else {
            var_dump($ret);
        }
    }




}
