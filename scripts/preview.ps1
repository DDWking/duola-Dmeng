$ErrorActionPreference = 'Stop'

$repositoryRoot = Split-Path -Parent $PSScriptRoot
Set-Location $repositoryRoot

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Write-Error "Docker Desktop is not installed or not running. Install Docker Desktop, start it, then run this script again."
}

if (-not (Test-Path '.env.local')) {
    Copy-Item '.env.local.example' '.env.local'
    Write-Host 'Created .env.local from .env.local.example for local preview.'
}

docker compose --env-file .env.local -f compose.local.yaml up -d --build
Write-Host 'Local preview is starting: http://localhost:8080'
Write-Host 'WordPress setup/admin: http://localhost:8080/wp-admin'
