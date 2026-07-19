# 瓦力波部署包

- `web/`：网站上的独立游戏页；`web/runtime/` 由 Godot Web 导出生成。
- `server/`：权威联机服务器镜像；`server/project/` 是运行服务器需要的最小 Godot 项目副本。
- 源项目位于同级目录的 `排球游戏` 仓库，部署前运行 `scripts/sync-volleyball.ps1` 同步。

不要直接修改 `web/runtime/` 或 `server/project/`，它们会在下次同步时被覆盖。
