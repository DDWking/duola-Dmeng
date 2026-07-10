$ErrorActionPreference = 'Stop'

$repositoryRoot = Split-Path -Parent $PSScriptRoot
Set-Location $repositoryRoot

docker compose --env-file .env.local -f compose.local.yaml down
Write-Host 'Local preview containers stopped. Local database and uploaded files remain under data/local.'
