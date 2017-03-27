<?php

namespace ZsyD\QiniuStream;

use Exception;
use Medz\Component\WrapperInterface\WrapperInterface;

class QiniuStream implements WrapperInterface
{
    private $_writeBuffer = false;

    private $_position = 0;

    private $_objectSize = 0;

    private $_objectName = null;

    private $_objectBuffer = null;

    private $_bucketList = [];

    private $_oss = null;

    public $error;

    /**
     * Extract object name from URL.
     *
     * @param string $path
     *
     * @return string
     */
    protected function _getNamePart($path)
    {
        $url = parse_url($path);
        if ($url['host']) {
            return !empty($url['path']) ? $url['host'].$url['path'] : $url['host'];
        }

        return '';
    }

    protected function _getOssClient($path)
    {
        if ($this->_oss === null) {
            $url = explode(':', $path);

            if (empty($url)) {
                throw new Exception("Unable to parse URL $path");
            }

            $this->_oss = Qiniu::getWrapperClient($url[0]);

            if (!$this->_oss) {
                throw new Exception("Unknown client for wrapper {$url[0]}");
            }
        }

        return $this->_oss;
    }

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $name = $this->_getNamePart($path);
        if (strpbrk($mode, 'wax')) {
            $this->_objectName = $name;
            $this->_objectBuffer = null;
            $this->_objectSize = 0;
            $this->_position = 0;
            $this->_writeBuffer = true;
            $this->_getOssClient($path);

            return true;
        } else {
            var_dump(9);exit;
            $info = $this->_getOssClient($path)->getObjectMeta(Qiniu::getBucket(), $name);
            if ($info) {
                $this->_objectName = $name;
                $this->_objectBuffer = null;
                $this->_objectSize = (int) $info['content-length'];
                $this->_position = 0;
                $this->_writeBuffer = false;

                return true;
            }
        }

        return false;
    }

    public function dir_opendir($path, $options)
    {
        $dirName = $this->_getNamePart($path).'/';
        if (preg_match('@^([a-z0-9+.]|-)+://$@', $path) || $dirName == '/') {
            $list = $this->_getOssClient($path)->listFiles(Qiniu::getBucket());
        } else {
            $list = $this
                ->_getOssClient($path)
                ->listFiles(Qiniu::getBucket(), $dirName);
        }

        foreach ((array) $list->getPrefixList() as $l) {
            array_push($this->_bucketList, basename($l->getPrefix()));
        }

        foreach ((array) $list->getObjectList() as $l) {
            if ($l == $dirName) {
                continue;
            }

            array_push($this->_bucketList, basename($l->getKey()));
        }

        return $this->_bucketList !== false;
    }

    public function dir_closedir()
    {

    }

    public function dir_readdir()
    {

    }

    public function dir_rewinddir()
    {

    }

    public function mkdir($path, $mode, $options)
    {

    }

    public function rename($path_from, $path_to)
    {

    }

    public function rmdir($path, $options)
    {

    }

    public function stream_cast($cast_as)
    {

    }

    public function stream_close()
    {

    }

    public function stream_eof()
    {

    }

    public function stream_flush()
    {

    }

    public function stream_lock($operation)
    {

    }



    public function stream_read($count)
    {
        if (!$this->_objectName) {
            return '';
        }
        // make sure that count doesn't exceed object size
        if ($count + $this->_position > $this->_objectSize) {
            $count = $this->_objectSize - $this->_position;
        }

        $range_start = $this->_position;
        $range_end = $this->_position + $count;

        // Only fetch more data from OSS if we haven't fetched any data yet (postion=0)
        // OR, the range end position is greater than the size of the current object
        // buffer AND if the range end position is less than or equal to the object's
        // size returned by OSS
        if (($this->_position == 0) || (($range_end > strlen($this->_objectBuffer)) && ($range_end <= $this->_objectSize))) {
            $options = [
                OssClient::OSS_RANGE => $range_start.'-'.$range_end,
            ];
            $this->_objectBuffer .= $this->_oss->get(Qiniu::getBucket(), $this->_objectName, $options);
        }

        $data = substr($this->_objectBuffer, $this->_position, $count);
        $this->_position += strlen($data);

        return $data;

        $ret = substr($GLOBALS[$this->_objectName], $this->_objectName, $count);
        $this->_position += strlen($ret);
        return $ret;
    }

    public function stream_seek($offset, $whence = SEEK_SET)
    {

    }

    public function stream_set_option($option, $arg1, $arg2)
    {

    }

    public function stream_stat()
    {
        if (!$this->_objectName) {
            return [];
        }

        $stat = [];
        $stat['dev'] = 0;
        $stat['ino'] = 0;
        $stat['mode'] = 0777;
        $stat['nlink'] = 0;
        $stat['uid'] = 0;
        $stat['gid'] = 0;
        $stat['rdev'] = 0;
        $stat['size'] = 0;
        $stat['atime'] = 0;
        $stat['mtime'] = 0;
        $stat['ctime'] = 0;
        $stat['blksize'] = 0;
        $stat['blocks'] = 0;

        if (($slash = strstr($this->_objectName, '/')) === false || $slash == strlen($this->_objectName) - 1) {
            /* bucket */
            $stat['mode'] |= 040000;
        } else {
            $stat['mode'] |= 0100000;
        }

        return $stat;
        $info = $this->_oss->getObjectMeta(AliyunOSS::getBucket(), $this->_objectName);
        $info = $info['_info'];
        if (!empty($info['_info'])) {
            $stat['size'] = $info['download_content_length'];
            $stat['atime'] = time();
            $stat['mtime'] = $info['filetime'];
        }

        return $stat;
    }

    public function stream_tell()
    {

    }

    public function stream_write($data)
    {

    }

    public function unlink($path)
    {

    }

    public function url_stat($path, $flags)
    {

    }
}