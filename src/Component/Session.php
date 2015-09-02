<?php

namespace tourze\Server\Component;

use tourze\Base\Base;
use tourze\Base\Component\Session as BaseSession;
use tourze\Base\Helper\File as FileHelper;
use tourze\Server\Protocol\Http as HttpProtocol;
use tourze\Server\Protocol\Http;

/**
 * Workerman架构下的会话组件
 *
 * @package tourze\Server\Component
 */
class Session extends BaseSession
{

    /**
     * @inheritdoc
     */
    public function destroy()
    {
        // 清空session数据
        $_SESSION = [];
        Base::getLog()->info(__METHOD__ . ' clean $_SESSION');

        // 删除对应的session文件
        $sessionID = HttpProtocol::getSessionID();
        $file = HttpProtocol::getSessionFile($sessionID);
        FileHelper::delete($file);
        Base::getLog()->info(__METHOD__ . ' delete session file', [
            'file' => $file,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function id($id = null)
    {
        return Http::getSessionID();
    }
}
