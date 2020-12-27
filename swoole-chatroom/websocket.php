<?php

/**
  //创建websocket服务器对象，监听api.biugle.cn:9527端口//直接写0.0.0.0也可公网访问//写127.0.0.1只能内网访问
  $ws = new swoole_websocket_server("api.biugle.cn", 9527);
  //监听WebSocket连接打开事件
  $ws->on('open', function ($ws, $request) {
  $userName = $request->get['userName'];
  if (!empty($ws->fd)) {
  foreach ($ws->fd as $fd => $value) {
  $ws->push($fd, json_encode([
  'userId' => 'admin',
  'type' => 'message',
  'user' => '系统',
  'data' => "[" . $request->fd . "]" . $userName . " 进来了！\n",
  'time' => date('Y-m-d H:i:s', time())
  ]));
  }
  }
  $ws->fd[$request->fd] = $userName; //给请求者设置id
  $ws->push($request->fd, json_encode([
  'clientId' => $request->fd,
  'userId' => 'admin',
  'type' => 'setId',
  'user' => '系统',
  'data' => "hello, welcome！\n",
  'time' => date('Y-m-d H:i:s', time())
  ]));

  foreach ($ws->fd as $fd => $userName) {
  $ws->push($fd, json_encode([
  'type' => 'list',
  'data' => $ws->fd
  ]));
  }
  });

  //监听WebSocket消息事件
  $ws->on('message', function ($ws, $frame) {
      $requestData = json_decode($frame->data);
      if (!empty($requestData)) {
        if ($requestData->type == 'o2o') {
            $sendData = json_encode([
                'userId' => $frame->fd, //对应存储在$ws->fd中的用户id
                'type' => 'message',
                'user' => $ws->fd[$frame->fd], //存储到$ws->fd中的用户名字
                'data' => $requestData->message . "\n",
                'to' => $requestData->receiver,
                'time' => date('Y-m-d H:i:s', time())
            ]);
            $ws->push($frame->fd, $sendData);
            $ws->push($requestData->receiver ?? $frame->fd, $sendData);
            return;
        }
        if ($requestData->type == 'message') {
            foreach ($ws->fd as $fd => $userName) {
              $ws->push($fd, json_encode([
                  'userId' => $frame->fd, //对应存储在$ws->fd中的用户id
                  'type' => 'message',
                  'user' => $ws->fd[$frame->fd], //存储到$ws->fd中的用户名字
                  'data' => $requestData->message . "\n",
                  'time' => date('Y-m-d H:i:s', time())
              ]));
            }
            return;
        }
      }
      foreach ($ws->fd as $fd => $userName) {
        $ws->push($fd, json_encode([
            'userId' => 'admin',
            'type' => 'message',
            'user' => '系统',
            'data' => '未知消息： ' . $frame->data . "\n",
            'time' => date('Y-m-d H:i:s', time())
        ]));
      }
  });

  //监听WebSocket连接关闭事件
  $ws->on('close', function ($ws, $fd) {
  unset($ws->fd[$fd]);
  if (count( (array) $ws->fd) >= 1) {
  foreach ($ws->fd as $fdk => $userName) {
  $ws->push($fdk, json_encode([
  'userId' => 'admin',
  'type' => 'message',
  'user' => '系统',
  'data' => "[" . $fd . "]" . $userName . " 离开了！\n",
  'time' => date('Y-m-d H:i:s', time())
  ]));
  $ws->push($fdk, json_encode([
  'type' => 'list',
  'data' => $ws->fd
  ]));
  }
  }
  });


  //  onRequest回调
  //  WebSocket\Server 继承自 Http\Server
  //  设置了onRequest回调，WebSocket\Server也可以同时作为http服务器
  //  未设置onRequest回调，WebSocket\Server收到http请求后会返回http 400错误页面
  //  如果想通过接收http触发所有websocket的推送，需要注意作用域的问题，面向过程请使用global对WebSocket\Server进行引用，面向对象可以把WebSocket\Server设置成一个成员属性

  // $ws->on('request', function ($request, $response) {
  //   global $ws; //调用外部的$ws
  //   // 接收http请求从get获取message参数的值，给用户推送，例如：ws://api.biugle.cn:9527?message=hello world!
  //   // $ws->connections遍历所有websocket连接用户的fd，给所有用户推送
  //   foreach ($ws->connections as $fd) {
  //     // 需要先判断是否是正确的websocket连接，否则有可能会push失败
  //     if ($ws->isEstablished($fd)) {
  //       $ws->push($fd, $request->get['message']);
  //     }
  //   }
  // });

  $ws->start();

 * */
//上面面向过程写法-------------------------------------------------------------------------------------------------------下面面向对象写法//

class WebsocketChatRoom {

  public $server;

  public function __construct() {
    //创建websocket服务器对象，监听api.biugle.cn:9527端口
    $this->server = new swoole_websocket_server("api.biugle.cn", 9527);

    //监听WebSocket连接打开事件
    $this->server->on('open', function ($ws, $request) {
      $userName = $request->get['userName'];
      if (!empty($ws->fd)) {
        foreach ($ws->fd as $fd => $value) {
          $ws->push($fd, json_encode([
              'userId' => 'admin',
              'type' => 'message',
              'user' => '系统',
              'data' => "[" . $request->fd . "]" . $userName . " 进来了！\n",
              'time' => date('Y-m-d H:i:s', time())
          ]));
        }
      }
      $ws->fd[$request->fd] = $userName; //给请求者设置id
      $ws->push($request->fd, json_encode([
          'clientId' => $request->fd,
          'userId' => 'admin',
          'type' => 'setId',
          'user' => '系统',
          'data' => "hello, welcome！\n",
          'time' => date('Y-m-d H:i:s', time())
      ]));

      foreach ($ws->fd as $fd => $userName) {
        $ws->push($fd, json_encode([
            'type' => 'list',
            'data' => $ws->fd
        ]));
      }
    });

//监听WebSocket消息事件
    $this->server->on('message', function ($ws, $frame) {
      $requestData = json_decode($frame->data);
      if (!empty($requestData)) {
        if ($requestData->type == 'o2o') {
            $sendData = json_encode([
                'userId' => $frame->fd, //对应存储在$ws->fd中的用户id
                'type' => 'message',
                'user' => $ws->fd[$frame->fd], //存储到$ws->fd中的用户名字
                'data' => $requestData->message . "\n",
                'to' => $requestData->receiver,
                'time' => date('Y-m-d H:i:s', time())
            ]);
            $ws->push($frame->fd, $sendData);
            $ws->push($requestData->receiver ?? $frame->fd, $sendData);
            return;
        }
        if ($requestData->type == 'message') {
            foreach ($ws->fd as $fd => $userName) {
              $ws->push($fd, json_encode([
                  'userId' => $frame->fd, //对应存储在$ws->fd中的用户id
                  'type' => 'message',
                  'user' => $ws->fd[$frame->fd], //存储到$ws->fd中的用户名字
                  'data' => $requestData->message . "\n",
                  'time' => date('Y-m-d H:i:s', time())
              ]));
            }
            return;
        }
      }
      foreach ($ws->fd as $fd => $userName) {
        $ws->push($fd, json_encode([
            'userId' => 'admin',
            'type' => 'message',
            'user' => '系统',
            'data' => '未知消息： ' . $frame->data . "\n",
            'time' => date('Y-m-d H:i:s', time())
        ]));
      }
    });

//监听WebSocket连接关闭事件
    $this->server->on('close', function ($ws, $fd) {
      unset($ws->fd[$fd]);
      if (count((array) $ws->fd) >= 1) {
        foreach ($ws->fd as $fdk => $userName) {
          $ws->push($fdk, json_encode([
              'userId' => 'admin',
              'type' => 'message',
              'user' => '系统',
              'data' => "[" . $fd . "]" . $userName . " 离开了！\n",
              'time' => date('Y-m-d H:i:s', time())
          ]));
          $ws->push($fdk, json_encode([
              'type' => 'list',
              'data' => $ws->fd
          ]));
        }
      }
    });

    /**
     * onRequest回调
     * WebSocket\Server 继承自 Http\Server
     * 设置了onRequest回调，WebSocket\Server也可以同时作为http服务器
     * 未设置onRequest回调，WebSocket\Server收到http请求后会返回http 400错误页面
     * 如果想通过接收http触发所有websocket的推送，需要注意作用域的问题，面向过程请使用global对WebSocket\Server进行引用，面向对象可以把WebSocket\Server设置成一个成员属性
     * */
    $this->server->on('request', function (swoole_http_request $request, swoole_http_response $response) {
      // 接收http请求从get获取message参数的值，给用户推送，例如：ws://api.biugle.cn:9527?message=hello world!
      // $this->server->connections 遍历所有websocket连接用户的fd，给所有用户推送
      foreach ($this->server->connections as $fd) {
        // 需要先判断是否是正确的websocket连接，否则有可能会push失败
        if ($this->server->isEstablished($fd)) {
          $this->server->push($fd, $request->get['message']);
        }
      }
    });

    $this->server->start();
  }

}

new WebsocketChatRoom();


