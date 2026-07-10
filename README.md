# 哆啦D梦的口袋

自托管的个人摄影与文章网站。运行架构为 `Caddy + WordPress + MariaDB`，由 Docker Compose 管理。

详细的产品与架构决策见 [PROJECT.md](PROJECT.md)。

## 目录职责

```text
docker/                         Caddy 与 WordPress 镜像配置
wordpress/wp-content/themes/    自定义主题源代码
wordpress/wp-content/plugins/   自定义相册管理插件
scripts/                        服务器部署、备份与恢复脚本
data/                           运行时数据（忽略，不提交 Git）
```

`data/` 中的 MariaDB 数据、WordPress 上传图片和 Caddy 证书是持久数据；它们不在 Docker 镜像或 Git 仓库中。

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

- 更新：`./scripts/deploy.sh`
- 备份：`./scripts/backup.sh`
- 恢复：`./scripts/restore.sh database.sql uploads.tar.zst`

