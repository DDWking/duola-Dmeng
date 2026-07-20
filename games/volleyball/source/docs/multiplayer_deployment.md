# 联机服务器与个人网站部署

## 本机联调

1. 双击 `run_multiplayer_server.bat`，保持服务器窗口开启。
2. 在项目目录启动 Web 静态服务器：

   ```powershell
   python -m http.server 8895 --bind 127.0.0.1 --directory build/web
   ```

3. 打开 `http://127.0.0.1:8895/`，在“联机对战”中使用默认地址 `ws://127.0.0.1:9001`。
4. 第一名玩家创建房间，把六位房间码发给第二名玩家；第二名玩家输入房间码加入。

服务器是权威端：客户端只上传移动与动作输入，球、触球次数、得分和局分均由服务器计算。状态快照以 20Hz 下发；本地人物移动和起跳会先预测，再由快照校正。

## 腾讯云轻量服务器

服务器需要 Linux 版 Godot 4.7.1 和完整项目文件。例如项目放在 `/opt/arcade-volley`：

```bash
/opt/godot/godot --headless --path /opt/arcade-volley -- --server --port=9001
```

建议用 systemd 保持进程运行，并只让 Nginx 访问 `127.0.0.1:9001`。个人网站使用 HTTPS 时，浏览器会拒绝明文 `ws://`，必须提供 `wss://`。Nginx 示例：

```nginx
location /volleyball-ws {
    proxy_pass http://127.0.0.1:9001;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 75s;
    proxy_send_timeout 75s;
}
```

网站中的服务器地址填写：

```text
wss://你的域名/volleyball-ws
```

在腾讯云防火墙中开放网站使用的 `80/443`，不需要把 `9001` 直接暴露到公网。Web 导出目录 `build/web` 可以放进个人网站的静态目录，也可以通过 iframe 嵌入；iframe 需要允许键盘焦点。

## 房间与断线规则

- 房间码为六位大写字母或数字。
- 每个房间只允许两名游客玩家。
- 一方掉线后该房间暂停 30 秒，相同浏览器会自动尝试重连。
- 页面刷新后可在联机大厅点击“恢复上局”。
- 30 秒内未恢复时比赛直接结束，不记录任何胜负。
- 主动退出会立即结束当前房间，同样不记录胜负。
