# 本地预览

本地预览与生产服务器使用同一套 WordPress 主题和相册插件，但使用独立的本地数据库、上传目录与环境变量：

```text
.env.local                 本地预览专用配置
data/local/mariadb/        本地预览数据库
data/local/uploads/        本地预览上传的图片
```

它们不会进入 Git，也不会影响服务器上的 `.env`、`data/mariadb` 或 `data/uploads`。

## 前置条件

Windows 安装并启动 **Docker Desktop**。首次启动 Docker Desktop 后，确认任务栏图标显示正在运行。

## 启动

在 PowerShell 中进入项目目录后执行：

```powershell
.\scripts\preview.ps1
```

首次运行会从 `.env.local.example` 创建本地 `.env.local`，然后下载基础镜像、构建 WordPress 镜像并启动服务。

打开：

- 前台：`http://localhost:8080`
- WordPress 安装与后台：`http://localhost:8080/wp-admin`

第一次访问会看到 WordPress 安装页。创建一个仅用于本地测试的管理员账号，然后：

1. 启用“哆啦D梦的口袋”主题；
2. 启用“哆啦D梦相册”插件；
3. 按 [WordPress 首次设置清单](wordpress-setup.md) 创建首页、文章、归档和关于页面；
4. 创建测试相册并上传测试图片。

## 停止

```powershell
.\scripts\stop-preview.ps1
```

停止不会删除本地数据库和上传图片。若需要完全重置本地测试站，先停止容器，再删除 `data/local/` 与 `.env.local` 后重新运行预览脚本。

> 不要将 `.env.local`、`data/local/` 或测试上传图片提交到 GitHub。
