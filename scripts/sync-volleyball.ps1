param(
    [string]$GodotPath = 'D:\Godot\Godot_v4.7.1-stable_win64_console.exe'
)

$ErrorActionPreference = 'Stop'

$repositoryRoot = [System.IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
$gameRoot = [System.IO.Path]::GetFullPath((Join-Path $repositoryRoot 'games\volleyball\source'))
$runtimeTarget = [System.IO.Path]::GetFullPath((Join-Path $repositoryRoot 'games\volleyball\web\runtime'))
$serverTarget = [System.IO.Path]::GetFullPath((Join-Path $repositoryRoot 'games\volleyball\server\project'))
$repositoryPrefix = $repositoryRoot.TrimEnd('\') + '\'

foreach ($target in @($runtimeTarget, $serverTarget)) {
    if (-not $target.StartsWith($repositoryPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing to replace a directory outside the blog repository: $target"
    }
}

if (-not (Test-Path -LiteralPath $GodotPath)) {
    throw "Godot console executable not found: $GodotPath"
}
if (-not (Test-Path -LiteralPath (Join-Path $gameRoot 'project.godot'))) {
    throw "Godot project not found: $gameRoot"
}

& $GodotPath --headless --path $gameRoot --script res://tests/smoke_test.gd
if ($LASTEXITCODE -ne 0) {
    throw 'Volleyball smoke tests failed.'
}

$webOutput = Join-Path $gameRoot 'build\web\index.html'
New-Item -ItemType Directory -Path (Split-Path -Parent $webOutput) -Force | Out-Null
& $GodotPath --headless --path $gameRoot --export-release Web $webOutput
if ($LASTEXITCODE -ne 0) {
    throw 'Godot Web export failed.'
}

foreach ($target in @($runtimeTarget, $serverTarget)) {
    New-Item -ItemType Directory -Path $target -Force | Out-Null
    Get-ChildItem -LiteralPath $target -Force | Remove-Item -Recurse -Force
}

$webSource = Join-Path $gameRoot 'build\web'
Get-ChildItem -LiteralPath $webSource -File |
    Where-Object { $_.Extension -ne '.import' } |
    Copy-Item -Destination $runtimeTarget -Force

$runtimeHtml = Join-Path $runtimeTarget 'index.html'
$featureCheck = @'
	const missing = Engine.getMissingFeatures({
		threads: GODOT_THREADS_ENABLED,
	});
'@
$httpFallback = @'
	const missing = Engine.getMissingFeatures({
		threads: GODOT_THREADS_ENABLED,
	}).filter((feature) => !(
		window.location.protocol === 'http:'
		&& !GODOT_THREADS_ENABLED
		&& feature.startsWith('Secure Context')
	));
'@
$engineInit = 'const engine = new Engine(GODOT_CONFIG);'
$httpAudioFallback = @'
if (window.location.protocol === 'http:' && !window.isSecureContext) {
	GODOT_CONFIG['args'].push('--audio-driver', 'Dummy');
}
const engine = new Engine(GODOT_CONFIG);
'@
$runtimeHtmlContent = [System.IO.File]::ReadAllText($runtimeHtml)
if (-not $runtimeHtmlContent.Contains($featureCheck)) {
    throw 'Could not add the single-threaded HTTP fallback to the Godot Web shell.'
}
if (-not $runtimeHtmlContent.Contains($engineInit)) {
    throw 'Could not add the HTTP audio fallback to the Godot Web shell.'
}
[System.IO.File]::WriteAllText(
    $runtimeHtml,
    $runtimeHtmlContent.Replace($featureCheck, $httpFallback).Replace($engineInit, $httpAudioFallback),
    [System.Text.UTF8Encoding]::new($false)
)

Copy-Item -LiteralPath (Join-Path $gameRoot 'project.godot') -Destination $serverTarget
Copy-Item -LiteralPath (Join-Path $gameRoot 'main.tscn') -Destination $serverTarget
Copy-Item -LiteralPath (Join-Path $gameRoot 'scripts') -Destination $serverTarget -Recurse
Copy-Item -LiteralPath (Join-Path $gameRoot 'assets') -Destination $serverTarget -Recurse

$runtimeSize = (Get-ChildItem -LiteralPath $runtimeTarget -File -Recurse | Measure-Object -Property Length -Sum).Sum
$serverSize = (Get-ChildItem -LiteralPath $serverTarget -File -Recurse | Measure-Object -Property Length -Sum).Sum
Write-Host ("Volleyball sync complete: Web {0:N1} MB, server project {1:N1} MB" -f ($runtimeSize / 1MB), ($serverSize / 1MB))
