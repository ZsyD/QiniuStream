<?php

namespace ZsyD\QiniuStream;

use Exception;
use Medz\Component\WrapperInterface\WrapperInterface;
use Qiniu\Http\Client;
use Qiniu\Http\Error;

class QiniuStream implements WrapperInterface
{
    private $_writeBuffer = false;

    private $_position = 0;

    private $_objectSize = 0;

    private $_objectName = null;

    private $_objectBuffer = null;

    private $_bucketList = [];

    private $_oss = null;

    private $_bucketMgr = null;

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
            
            $info = $this->_getOssClient($path)->stat(Qiniu::getBucket(), $name);
            if ($info) {
                $this->_objectName = $name;
                $this->_objectBuffer = null;
                $this->_objectSize = (int) $info[0]['fsize'];
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
        list($iterms, $marker, $err) = $list;

        if ($iterms) {
            $this->_bucketList = $iterms;
        }

        return $this->_bucketList !== false;
    }

    public function dir_closedir()
    {
        $this->_bucketList = [];

        return true;
    }

    public function dir_readdir()
    {
        $object = current($this->_bucketList);
        if ($object !== false) {
            next($this->_bucketList);
        }

        return $object;
    }

    public function dir_rewinddir()
    {
        reset($this->_bucketList);

        return true;
    }

    public function mkdir($path, $mode, $options)
    {
        return false;
    }

    /**
     * TODO 不能重命名
     *
     * @param string $path_from
     * @param string $path_to
     * @return mixed
     */
    public function rename($path_from, $path_to)
    {
        return $this->_getOssClient($path_from)
            ->rename(Qiniu::getBucket(), $path_from, $path_to);
    }

    public function rmdir($path, $options)
    {
        return false;
    }

    public function stream_cast($cast_as)
    {

    }

    public function stream_close()
    {
        $this->_objectName = null;
        $this->_objectBuffer = null;
        $this->_objectSize = 0;
        $this->_position = 0;
        $this->_writeBuffer = false;
        unset($this->_oss);
    }

    /**
     * 判断文件指针是否结束
     *
     * @return bool
     */
    public function stream_eof()
    {
        if (!$this->_objectName) {
            return true;
        }

        return $this->_position >= $this->_objectSize;
    }

    /**
     * TODO 还有问题
     * 将缓冲内容输出到文件
     *
     * @return bool
     */
    public function stream_flush()
    {
        if (!$this->_writeBuffer) {
            return false;
        }
        //$ret = $this->_oss->putObject(AliyunOSS::getBucket(), $this->_objectName, $this->_objectBuffer);
        $ret = $this->_oss->uploadManager()->put(Qiniu::getUploadManager(), $this->_objectName, $this->_objectBuffer);
        $this->_objectBuffer = null;

        return $ret;
    }

    public function stream_lock($operation)
    {
        return false;
    }

    /**
     * 此方法不通
     *
     * @param int $count
     * @return array|\Qiniu\Http\Response|string
     */
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
                'range' => $range_start.'-'.$range_end,
            ];
           // $this->_objectBuffer .= $this->_oss->get(Qiniu::getBucket(), $this->_objectName, $options);

            $headers = Qiniu::getAuth()->authorization($this->_objectName);
            $ret = Client::get($this->_objectName, $headers);
            var_dump($ret);

            if (!$ret->ok()) {
                return array(null, new Error($this->_objectName, $ret));
            }
            return array($ret->json(), null);

        }

        $data = substr($this->_objectBuffer, $this->_position, $count);
        $this->_position += strlen($data);

        return $data;

        $ret = substr($GLOBALS[$this->_objectName], $this->_objectName, $count);
        $this->_position += strlen($ret);
        return $ret;
    }

    /**
     * 文件指针定位
     *
     * @param int $offset
     * @param int $whence
     * @return bool
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        if (!$this->_objectName) {
            return false;

            // Set position to current location plus $offset
        } elseif ($whence === SEEK_CUR) {
            $offset += $this->_position;

            // Set position to end-of-file plus $offset
        } elseif ($whence === SEEK_END) {
            $offset += $this->_objectSize;
        }

        if ($offset >= 0 && $offset <= $this->_objectSize) {
            $this->_position = $offset;

            return true;
        }

        return false;
    }

    public function stream_set_option($option, $arg1, $arg2)
    {
        return false;
    }

    /**
     * 获取流信息
     *
     * @return array
     */
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

        $info = $this->_oss->stat(Qiniu::getBucket(), $this->_objectName);

        $info = $info['0'];
        if (!empty($info)) {
            $stat['size'] = $info['fsize'];
            $stat['atime'] = time();
            $stat['mtime'] = $info['putTime'];
        }

        return $stat;
    }

    /**
     * 获取文件指针定位
     *
     * @return int
     */
    public function stream_tell()
    {
        return $this->_position;
    }

    /**
     * 写入流
     *
     * @param string $data
     * @return int
     */
    public function stream_write($data)
    {
        if (!$this->_objectName) {
            return 0;
        }
        $len = strlen($data);
        $this->_objectBuffer .= $data;
        $this->_objectSize += $len;
        // TODO: handle current position for writing!
        return $len;
    }

    /**
     * 删除链接
     *
     * @param string $path
     * @return mixed
     */
    public function unlink($path)
    {
        return $this->_getOssClient($path)->delete(Qiniu::getBucket(), $this->_getNamePart($path));
    }

    /**
     * 返回文件的信息
     *
     * @param string $path
     * @param int $flags
     * @return array
     */
    public function url_stat($path, $flags)
    {
        $stat = [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => 0777,
            'nlink'   => 0,
            'uid'     => 0,
            'gid'     => 0,
            'rdev'    => 0,
            'size'    => 0,
            'atime'   => 0,
            'mtime'   => 0,
            'ctime'   => 0,
            'blksize' => 0,
            'blocks'  => 0,
        ];
        $name = $this->_getNamePart($path);

        try {
            $info = $this->_getOssClient($path)->stat(Qiniu::getBucket(), $name);
            if (isset($info['0']) && !empty($info['0'])) {
                $info = $info['0'];
                $stat['size'] = $info['fsize'];
                $stat['atime'] = time();
                $stat['mtime'] = $info['putTime'];
                $stat['mode'] |= 0100000;
            }
        } catch (Exception $e) {
            $stat['mode'] |= 040000;
        }

        return $stat;
    }
}