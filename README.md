# 哆啦D梦的口袋

自托管的个人摄影、文章与小游戏网站。运行架构为 `Caddy + WordPress + MariaDB + Godot`，由 Docker Compose 管理。

详细的产品与架构决策见 [PROJECT.md](PROJECT.md)。

## 目录职责

```text
docker/                         Caddy 与 WordPress 镜像配置
wordpress/wp-content/themes/    自定义主题源代码
wordpress/wp-content/plugins/   自定义相册管理插件
scripts/                        服务器部署、备份与恢复脚本
games/volleyball/source/       瓦力波完整 Godot 源码（唯一编辑入口）
games/volleyball/web/          网站游戏外壳与生成的 Web 运行包
games/volleyball/server/       生成的联机服务器运行包
data/                           运行时数据（忽略，不提交 Git）
```

`data/` 中的 MariaDB 数据、WordPress 上传图片和 Caddy 证书是持久数据；它们不在 Docker 镜像或 Git 仓库中。

自定义主题和相册插件以只读方式从项目工作区挂载到 WordPress 容器。日后手动部署时，服务器拉取 GitHub 代码并重建服务即可更新它们；文章、数据库和上传照片不会受影响。

## 瓦力波开发

只修改 `games/volleyball/source/` 中的 Godot 项目，不要直接编辑 `web/runtime/` 或 `server/project/` 中的生成文件。

修改完成后运行 `powershell -ExecutionPolicy Bypass -File .\\scripts\\sync-volleyball.ps1`。脚本会执行冒烟测试、重新导出 Web 版本，并同步联机服务器需要的最小 Godot 项目。源码、测试、Web 构建和网站代码随后在当前仓库中一起提交、推送和部署。

## 本地预览

Windows 本地先安装并启动 Docker Desktop，然后执行：

`powershell
.\scripts\preview.ps1
` 

打开 http://localhost:8080 即可预览；完整步骤见 [本地预览](docs/local-preview.md)。

## 首次在服务器部署

1. 安装 Docker Engine 与 Docker Compose Plugin。
2. 克隆本仓库，进入仓库目录。
3. 复制 `.env.example` 为 `.env`，填写域名、数据库密码和 WordPress salts。
4. 将域名的 A/AAAA 记录解析到服务器公网 IP，并在防火墙开放 `80`、`443`。
5. 执行 `docker compose up -d --build`。
6. 打开 `https://你的域名` 完成 WordPress 安装；在后台启用 `哆啦D梦的口袋` 主题与 `哆啦D梦相册` 插件。

Caddy 会在域名解析生效后自动申请和续期 HTTPS 证书。

## 更新与备份

- Windows 本地手动部署：

  ```powershell
  .\scripts\deploy-server.ps1 -Server 159.75.236.90
  ```

  脚本会先推送 GitHub 备份，再通过 SSH 将 Git bundle 同步到服务器并重建容器。它不属于 CI/CD，也不会改动数据库、照片和 `.env`。

- 服务器内更新：`./scripts/deploy.sh`
- 备份：`./scripts/backup.sh`
- 恢复：`./scripts/restore.sh database.sql uploads.tar.zst`

