<?php

$chatRoom = new WebsocketChatRoom("0.0.0.0", "9527");
$chatRoom->listenClients();

Class WebsocketChatRoom {

  /**
   * 套接字
   * @var resource 
   */
  private $_socket; //信息传输流

  /**
   * 连接用户资料
   * @var array 
   */
  private $_users; //[userId=>resource]
  
  /**
   * 连接用户信息
   * @var array 
   */
  private $_userNames; //[userId=>$userName]
  
  /**
   * 客户端总连接池
   * @var array 
   */
  private $_clients; //[clientId=>resource]

  public function __construct(string $address, int $port) {
    $this->_userNames = [];
    $this->_socket = $this->_websocket($address, $port);
  }

  /**
   * 建立套接字，监听服务。
   * 套接字就是一个网络资料传输流 https://iter01.com/441107.html
   * @param string $address 地址 必须填写ip https://learnku.com/articles/36363
   * @param int $port 端口
   * @return resource scoket套接字
   */
  private function _websocket(string $address, int $port) {
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP); //调用socket_create方法，创建套接字系统资源。
    if ($socket === false) {
      echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
    } else {
      echo "Create socket successful!(author:hxb)\n";
    }
    socket_bind($socket, $address, $port); //绑定监听的地址和端口号
    socket_listen($socket, 10); //告诉系统监听此套接字
    return $socket;
  }

  /**
   * 建立一个死循环，监听套接字变化。
   */
  public function listenClients() {
    $this->_clients[] = $this->_socket;
    while (true) {
      $changes = $this->_clients;
      $write = null;
      $except = null;
      //阻塞，直到连接有新变化才返回
      socket_select($changes, $write, $except, null); //接受套接字数组，并等待它们更改状态。＜0表示有错bai误，其他值表示可操作的socket个数 https://www.php.net/manual/en/function.socket-select.php
      foreach ($changes as $change) {
        if ($this->_socket == $change) {//如果当前套接字有变化（有新连接）
          $newSocket = socket_accept($this->_socket); //接受来自客户端的连接
          $this->_clients[] = $newSocket; //将新客户端放入连接池
          $requestData = null;
          socket_recv($newSocket, $requestData, 2048, 0); //读取数据，$request为接收的数据
          $this->_doHandshake($newSocket, $requestData); //握手
        } else {//已经连上的客户端则收发消息即可
          $requestData = null;
          socket_recv($change, $requestData, 2048, 0);
          if (empty($requestData)) {
            continue;
          }
          $this->sendMessage($change, $requestData);
        }
      }
    }
  }

  /**
   * 接收/发送数据
   * 假设三种情况：
   * 1.新连接的用户：type="setId"；
   * 2.发送消息给所有人：type="message"
   * 3.发送消息给单个人：type="o2o"
   * 3.关闭连接：no type
   * @param resource $socket 套接字
   * @param string $requestData 接收到的消息(二进制)
   */
  public function sendMessage($socket, string $requestData) {
    $decodeMsg = json_decode($this->unpack($requestData));
    $sendType = $decodeMsg->type ?? null; //发送消息类型
    if (empty($sendType)) { //断开连线
      $closeUserId = $this->_searchUsers($socket);
      $closeUserName = $this->_userNames[$closeUserId];
      $closeClientId = $this->_searchClients($socket);
      //把存在数组里面的资料清除
      unset($this->_userNames[$closeUserId]);
      unset($this->_users[$closeUserId]);
      unset($this->_clients[$closeClientId]);
      socket_close($socket); //关闭对应socket
      $sendData = [
          'userId' => 'admin',
          'type' => 'message',
          'user' => '系统',
          'data' => '[' . $closeClientId . ']' . $closeUserName . " 离开了！\n",
          'time' => null
      ];
      $this->sendAll($sendData);
      $this->sendUserList();
      return;
    }

    if ($sendType == "setId") { //新用户连接
      //第一次进入添加聊天名字，把姓名和socket保存起来
      $newUserName = $decodeMsg->name;
      array_push($this->_userNames, $newUserName);
      $this->_users[$this->_searchUserId($newUserName)] = $socket;
      $newClientId = $this->_searchClients($socket);
      $sendData = [
          'clientId' => $newClientId,
          'userId' => 'admin',
          'type' => 'setId',
          'user' => '系统',
          'data' => "hello, welcome！\n",
          'time' => null
      ];
      $sendMsg = $this->pack(json_encode($sendData));
      socket_write($socket, $sendMsg, strlen($sendMsg)); //发给自己
      $sendAllData = [
          'userId' => 'admin',
          'type' => 'message',
          'user' => '系统',
          'data' => '[' . $newClientId . ']' . $newUserName . " 进来了！\n",
          'time' => null
      ];
      $this->sendAll($sendAllData);
      $this->sendUserList();
      return;
    }

    $userId = $this->_searchUsers($socket) ?? null;
    $userName = $this->_userNames[$userId] ?? '系统';
    $clientId = $this->_searchClients($socket) ?? 'admin';
    $message = $decodeMsg->message ?? null;
    if ($sendType == "o2o") { //发送给个人
      $receiver = $decodeMsg->receiver;
      $sendData = [
          'userId' => $clientId,
          'type' => 'message',
          'user' => $userName,
          'data' => $message,
          'to' => $receiver,
          'time' => date('Y-m-d H:i:s', time())
      ];
      $sendMsg = $this->pack(json_encode($sendData));
      socket_write($socket, $sendMsg, strlen($sendMsg)); //发给自己
      socket_write($this->_clients[$receiver] ?? $socket, $sendMsg, strlen($sendMsg)); //发给对方
      return;
    }
    $sendData = [
        'userId' => $clientId,
        'type' => 'message',
        'user' => $userName,
        'data' => ($clientId == 'admin') ? '未知消息： ' . $message : $message,
        'time' => date('Y-m-d H:i:s', time())
    ];
    $this->sendAll($sendData);
  }

  /**
   * 发送消息给所有人
   * @param array $sendData 要发送的数据
   */
  public function sendAll($sendData) {
    $sendPackMsg = $this->pack(json_encode($sendData));
    foreach ($this->_users as $userSocket) {
      socket_write($userSocket, $sendPackMsg, strlen($sendPackMsg));
    }
  }

  /**
   * 发送用户列表给所有人
   */
  public function sendUserList() {
    $userList = $this->_getUserList();
    $sendData = [
        'type' => 'list',
        'data' => $userList,
    ];
    $this->sendAll($sendData);
  }

  /**
   * 获取用户列表数据
   */
  private function _getUserList() {
    $userList = [];
    foreach ($this->_users as $userId => $socket) {
      foreach ($this->_clients as $clientId => $sock) {
        if ($sock == $socket) {
          $userList[$clientId] = $this->_userNames[$userId];
          continue;
        }
      }
    }
    return $userList;
  }
  
  /**
  * 根据$suserName在userNames里面查找相应的$userId
  * @param string $suserName
  */
  private function _searchUserId($newUserName) {
      $newUserId = null;
      foreach($this->_userNames as $userId => $userName){
        $userName == $newUserName && $newUserId = $userId; 
      }
      return $newUserId;
  }

  /**
   * 根据$socket在users里面查找相应的$userId
   * @param resource $socket 
   */
  private function _searchUsers($socket) {
    foreach ($this->_users as $userId => $userSocket) {
      if ($userSocket == $socket) {
        return $userId;
      }
    }
    return null;
  }

  /**
   * 根据$socket在clients里面查找相应的$clientId
   * @param resource $socket
   */
  private function _searchClients($socket) {
    foreach ($this->_clients as $clientId => $userSocket) {
      if ($userSocket == $socket) {
        return $clientId;
      }
    }
    return null;
  }

  /**
   * 握手
   * @param resource $socket 套接字
   * @param string $request 请求头信息
   * @return bool
   */
  private function _doHandshake($socket, string $request) {
    $match = null;
    $decodeKey = null;
    if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $request, $match)) {
      $decodeKey = $match[1];
    }
    $newEncodeKey = base64_encode((sha1($decodeKey . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true)));
    $newHeader = "HTTP/1.1 101 Switching Protocols\r\n";
    $newHeader .= "Upgrade: websocket\r\n";
    $newHeader .= "Sec-WebSocket-Version: 13\r\n";
    $newHeader .= "Connection: Upgrade\r\n";
    $newHeader .= "Sec-WebSocket-Accept: " . $newEncodeKey . "\r\n\r\n";
    socket_write($socket, $newHeader, strlen($newHeader)); //发送response信息
    return true;
  }

  /**
   * 加密数据
   * @param type $sendData
   * @return string
   */
  public function pack($sendData) {
    $a = str_split($sendData, 125);
    if (count($a) == 1) {
      return "\x81" . chr(strlen($a[0])) . $a[0];
    }
    $ns = "";
    foreach ($a as $o) {
      $ns .= "\x81" . chr(strlen($o)) . $o;
    }
    return $ns;
  }

  /**
   * 解码接收到的数据(二进制)
   * @param $requestData 接收到的数据
   * @return null|string
   */
  public function unpack($requestData) {
    $len = $masks = $data = $decoded = null;
    if (!empty($requestData)) {
      $len = ord($requestData[1]) & 127;
    }
    if ($len === 126) {
      $masks = substr($requestData, 4, 4);
      $data = substr($requestData, 8);
    } else if ($len === 127) {
      $masks = substr($requestData, 10, 4);
      $data = substr($requestData, 14);
    } else {
      $masks = substr($requestData, 2, 4);
      $data = substr($requestData, 6);
    }
    for ($index = 0; $index < strlen($data); $index++) {
      $decoded .= $data[$index] ^ $masks[$index % 4];
    }
    return $decoded;
  }

  /**
   * 关闭socket
   */
  public function close() {
    socket_close($this->_sockets);
  }
  
  function __destruct() {
    $this->close();
  }

}
