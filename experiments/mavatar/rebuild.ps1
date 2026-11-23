param([string]$Project = 'frieren-chest')
$ErrorActionPreference = 'Stop'
if ($Project -notmatch '^[A-Za-z0-9][A-Za-z0-9_-]*$') { throw 'Invalid local project name.' }
$mavatarProject = 'projects/' + $Project
. (Join-Path $PSScriptRoot 'env.ps1')
Push-Location (Join-Path $PSScriptRoot 'studio')
try {
    & uv run --with numpy --with pillow --with opencv-python-headless tools/build-layers.py $mavatarProject --rig rig.draft.json
    if ($LASTEXITCODE -ne 0) { throw 'Layer build failed.' }
    if (Test-Path -LiteralPath ($mavatarProject + '/accessory-underlay/painting.png')) {
        & uv run --with numpy --with pillow --with opencv-python-headless ../restore-accessory-underlay.py $mavatarProject
        if ($LASTEXITCODE -ne 0) { throw 'Accessory underlay restoration failed.' }
    }
    & uv run tools/variant-requests.py $mavatarProject
    if ($LASTEXITCODE -ne 0) { throw 'Variant mask generation failed.' }
    & uv run tools/build-sprites.py $mavatarProject
    if ($LASTEXITCODE -ne 0) { throw 'Sprite validation failed; previous runtime archive was kept.' }
    & uv run --with numpy --with pillow --with opencv-python-headless ../build-awkward-overlay.py $Project
    if ($LASTEXITCODE -ne 0) { throw 'Awkward overlay build failed; previous runtime archive was kept.' }
    # Upstream Python atomic staging uses owner-only temporary-file ACLs on Windows.
    # Restore normal project inheritance so the editor and the desktop account can read it.
    & icacls ($mavatarProject + '/built') /reset /T /C | Select-Object -Last 1
    & icacls ($mavatarProject + '/rig.json') /reset | Select-Object -Last 1
    & npm run validate-rig -- ($mavatarProject + '/rig.json')
    if ($LASTEXITCODE -ne 0) { throw 'Rig validation failed.' }
    & npm run render-poses -- $mavatarProject
    if ($LASTEXITCODE -ne 0) { throw 'Pose rendering failed.' }
    $mavatarExport = $mavatarProject + '/frieren-' + (Get-Date -Format 'yyyyMMdd-HHmmss-fff') + '.mavatar'
    & npm run pack-avatar -- $mavatarProject --no-source -o $mavatarExport
    if ($LASTEXITCODE -ne 0) { throw 'Avatar export failed.' }
    Copy-Item -LiteralPath $mavatarExport -Destination ($mavatarProject + '/frieren-runtime.mavatar') -Force
    & node ../check-demo.mjs $Project
    if ($LASTEXITCODE -ne 0) { throw 'Demo checks failed; see review-demo/results.json.' }
} finally {
    Pop-Location
}
