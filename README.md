# chatroom
websocket实现的简单聊天室，一个是php原生websocket+vue实现的聊天室，另一个是通过swoole扩展实现的聊天室，供大家学习参考。
> 待实现功能

1. (Notification问题)win7多条消息同时接收没问题，win10多条消息时有些许延迟待解决。
1. 设置服务器60分钟无连接自动关闭服务。
1. 由于前一条，需实现心跳检测，保证打开页面时不操作不会断开连接。

# 说明

## 需放行9527端口

## 打开Notification

## 内网访问可监听127.0.0.1:9527，公网访问需监听0.0.0.0:9527端口

# 使用

```php
1.运行，进入文件目录，输入以下命令。
php websocket.php
2.访问对应的url即可
3.支持多个连接，具备私聊功能。
```
