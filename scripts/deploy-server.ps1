param(
    [Parameter(Mandatory = $true)]
    [string]$Server,
    [string]$User = 'ubuntu',
    [string]$DeployPath = '/srv/duola-dmeng',
    [string]$KeyPath = "$env:USERPROFILE\.ssh\id_ed25519"
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $KeyPath)) {
    throw "SSH key not found: $KeyPath"
}

$changes = git status --porcelain
if ($changes) {
    throw 'Commit or discard local changes before deploying.'
}

git push origin main
if ($LASTEXITCODE -ne 0) {
    throw 'GitHub push failed. Deployment was not started.'
}

$bundlePath = Join-Path $env:TEMP 'duola-dmeng-main.bundle'
$remoteBundlePath = '/tmp/duola-dmeng-main.bundle'
$sshOptions = @(
    '-i', $KeyPath,
    '-o', 'BatchMode=yes',
    '-o', 'PasswordAuthentication=no',
    '-o', 'KbdInteractiveAuthentication=no',
    '-o', 'StrictHostKeyChecking=yes'
)

try {
    Remove-Item -LiteralPath $bundlePath -Force -ErrorAction SilentlyContinue
    git bundle create $bundlePath main
    if ($LASTEXITCODE -ne 0) {
        throw 'Could not create the deployment Git bundle.'
    }

    & scp @sshOptions $bundlePath "${User}@${Server}:$remoteBundlePath"
    if ($LASTEXITCODE -ne 0) {
        throw 'Could not upload the deployment Git bundle.'
    }

    $remoteCommand = @"
set -Eeuo pipefail
cd '$DeployPath'
git fetch '$remoteBundlePath' main
git reset --hard FETCH_HEAD
docker compose up -d --build --remove-orphans
rm -f '$remoteBundlePath'
docker compose ps
"@

    & ssh @sshOptions "${User}@${Server}" $remoteCommand
    if ($LASTEXITCODE -ne 0) {
        throw 'Remote deployment failed. Check the server output above.'
    }
} finally {
    Remove-Item -LiteralPath $bundlePath -Force -ErrorAction SilentlyContinue
}
